<?php

declare(strict_types=1);

namespace Setono\SyliusPickupPointPlugin\Provider;

use Setono\SyliusPickupPointPlugin\Client\Chronopost\ClientInterface;
use Setono\SyliusPickupPointPlugin\Model\CpPoint;
use Setono\SyliusPickupPointPlugin\Model\PickupPointCode;
use Setono\SyliusPickupPointPlugin\Model\PickupPointInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Webmozart\Assert\Assert;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Http\Client\Exception\HttpException;

final class ColissimoProvider extends Provider
{
    public function __construct(
        private FactoryInterface   $pickupPointFactory,
        private HttpClientInterface $client,
        private string $colissimoAccount,
        private string $colissimoPassword
    )
    {
    }

    public function getCode(): string
    {
        return 'colissimo';
    }

    public function getName(): string
    {
        return 'Colissimo';
    }

    public function findPickupPoints(OrderInterface $order): iterable
    {
        if ($order->getShippingAddress()) {
            $address = $order->getShippingAddress();
        } else {
            $address = $order->getBillingAddress();
        }

        try {
            $date = new \DateTime();

            $client = new \SoapClient('https://ws.colissimo.fr/pointretrait-ws-cxf/PointRetraitServiceWS/2.0?wsdl', [
                'wsdl_cache' => 0,
                'trace' => 1,
                'exceptions' => true,
                'soap_version' => SOAP_1_1,
                'encoding' => 'utf-8'
            ]);

            if (property_exists($client, 'findRDVPointRetraitAcheminement')) {
                $cpPoints = $client->findRDVPointRetraitAcheminement([
                    "accountNumber" => $this->colissimoAccount,
                    "password" => $this->colissimoPassword,
                    "address" => $address->getStreet(),
                    "zipCode" => $address->getPostcode(),
                    "city" => $address->getCity(),
                    "codTiersPourPartenaire" => $this->colissimoAccount,
                    "countryCode" => $address->getCountryCode(),
                    "weight" => sprintf('%05d', $order->getTotalWeight() > 0 ? $order->getTotalWeight() : 1),
                    "shippingDate" => $date->format('d/m/Y'),
                    "filterRelay" => 1
                ]);
            } else {
                throw new HttpException();
            }
        } catch (ConnectionException $e) {
            throw new TimeoutException($e);
        }

        $pickupPoints = [];
        foreach ($cpPoints->return->listePointRetraitAcheminement as $item) {

            $openingHours = [
                'lundi' => $item->horairesOuvertureLundi,
                'mardi' => $item->horairesOuvertureMardi,
                'mercredi' => $item->horairesOuvertureMercredi,
                'jeudi' => $item->horairesOuvertureJeudi,
                'vendredi' => $item->horairesOuvertureVendredi,
                'samedi' => $item->horairesOuvertureSamedi,
                'dimanche' => $item->horairesOuvertureDimanche,
            ];

            $cpPoint = new CpPoint(
                $item->adresse1,
                $item->codePostal,
                $date->format('d/m/Y'),
                $item->horairesOuvertureLundi,
                $item->horairesOuvertureMardi,
                $item->horairesOuvertureMercredi,
                $item->horairesOuvertureJeudi,
                $item->horairesOuvertureVendredi,
                $item->horairesOuvertureSamedi,
                $item->horairesOuvertureDimanche,
                $item->identifiant,
                $item->localite,
                $item->nom,
                floatval($item->coordGeolocalisationLatitude),
                floatval($item->coordGeolocalisationLongitude),
                'https://www.google.com/maps?&z=16&q=' . $item->coordGeolocalisationLatitude . ',' . $item->coordGeolocalisationLongitude,
                $openingHours,
                $item->distanceEnMetre,
                $item->typeDePoint
            );

            $pickupPoints[] = $this->transform($cpPoint);
        }


        return $pickupPoints;

    }

    public function findPickupPoint(PickupPointCode $code): ?PickupPointInterface
    {
        try {
            $date = new \DateTime();

            $client = new \SoapClient('https://ws.colissimo.fr/pointretrait-ws-cxf/PointRetraitServiceWS/2.0?wsdl', [
                'wsdl_cache' => 0,
                'trace' => 1,
                'exceptions' => true,
                'soap_version' => SOAP_1_1,
                'encoding' => 'utf-8'
            ]);

            if (property_exists($client, 'findPointRetraitAcheminementByID')) {
                $cpPointResponse = $client->findPointRetraitAcheminementByID([
                    "accountNumber" => $this->colissimoAccount,
                    "password" => $this->colissimoPassword,
                    "id" => $code->getIdPart(),
                    "weight" => 1,
                    "date" => $date->format('d/m/Y'),
                    "filterRelay" => 1
                ]);
            } else {
                throw new HttpException();
            }
        } catch (ConnectionException $e) {
            throw new TimeoutException($e);
        }

        $item = $cpPointResponse->return->pointRetraitAcheminement;

        $openingHours = [
            'lundi' => $item->horairesOuvertureLundi,
            'mardi' => $item->horairesOuvertureMardi,
            'mercredi' => $item->horairesOuvertureMercredi,
            'jeudi' => $item->horairesOuvertureJeudi,
            'vendredi' => $item->horairesOuvertureVendredi,
            'samedi' => $item->horairesOuvertureSamedi,
            'dimanche' => $item->horairesOuvertureDimanche,
        ];

        $cpPoint = new CpPoint(
            $item->adresse1,
            $item->codePostal,
            $date->format('d/m/Y'),
            $item->horairesOuvertureLundi,
            $item->horairesOuvertureMardi,
            $item->horairesOuvertureMercredi,
            $item->horairesOuvertureJeudi,
            $item->horairesOuvertureVendredi,
            $item->horairesOuvertureSamedi,
            $item->horairesOuvertureDimanche,
            $item->identifiant,
            $item->localite,
            $item->nom,
            floatval($item->coordGeolocalisationLatitude),
            floatval($item->coordGeolocalisationLongitude),
            'https://www.google.com/maps?&z=16&q=' . $item->coordGeolocalisationLatitude . ',' . $item->coordGeolocalisationLongitude,
            $openingHours,
            $item->distanceEnMetre,
            $item->typeDePoint
        );

        return $this->transform($cpPoint);
    }

    public function findAllPickupPoints(): iterable
    {
        return [];
    }

    private function transform(CpPoint $cpPoint): PickupPointInterface
    {
        /** @var PickupPointInterface|object $pickupPoint */
        $pickupPoint = $this->pickupPointFactory->createNew();

        Assert::isInstanceOf($pickupPoint, PickupPointInterface::class);

        $pickupPoint->setCode(new PickupPointCode($cpPoint->getIdCpPoint(), $this->getCode(), 'FR'));

        $pickupPoint->setName($cpPoint->getNomEnseigne());
        $pickupPoint->setAddress($cpPoint->getAdresse1());
        $pickupPoint->setZipCode($cpPoint->getCodePostal());
        $pickupPoint->setCity($cpPoint->getLocalite());
        $pickupPoint->setLatitude($cpPoint->getCoordGeoLatitude());
        $pickupPoint->setLongitude($cpPoint->getCoordGeoLongitude());
        $pickupPoint->setCountry('FR');
        $pickupPoint->setOpeningHours($cpPoint->getOpeningHours());
        $pickupPoint->setDistance($cpPoint->getDistance());
        $pickupPoint->setType($cpPoint->getType());

        return $pickupPoint;
    }
}
