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

final class ChronopostProvider extends Provider
{

    private FactoryInterface $pickupPointFactory;

    public function __construct(FactoryInterface $pickupPointFactory)
    {
        $this->pickupPointFactory = $pickupPointFactory;
    }

    public function getCode(): string
    {
        return 'chronopost';
    }

    public function getName(): string
    {
        return 'Chronopost';
    }

    public function findPickupPoints(OrderInterface $order): iterable
    {
        if($order->getShippingAddress()) {
            $postalCode = $order->getShippingAddress()->getPostcode();
        } else {
            $postalCode = $order->getBillingAddress()->getPostcode();
        }

        try {
            $client = new \SoapClient('https://www.chronopost.fr/recherchebt-ws-cxf/PointRelaisServiceWS?wsdl', [
                'wsdl_cache' => 0,
                'trace' => 1,
                'exceptions' => true,
                'soap_version' => SOAP_1_1,
                'encoding' => 'utf-8'
            ]);

            $cpPoints = $client->rechercheBtParCodeproduitEtCodepostalEtDate([
                // 'codeProduit' => $codeProduit,
                'codePostal' => $postalCode,
                'date' => date('d/m/Y')
            ]);
        } catch (ConnectionException $e) {
            throw new TimeoutException($e);
        }

        $pickupPoints = [];
        foreach ($cpPoints->return as $item) {

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
                $item->dateArriveColis,
                $item->horairesOuvertureLundi,
                $item->horairesOuvertureMardi,
                $item->horairesOuvertureMercredi,
                $item->horairesOuvertureJeudi,
                $item->horairesOuvertureVendredi,
                $item->horairesOuvertureSamedi,
                $item->horairesOuvertureDimanche,
                $item->identifiantChronopostPointA2PAS,
                $item->localite,
                $item->nomEnseigne,
                $item->coordGeoLatitude,
                $item->coordGeoLongitude,
                $item->urlGoogleMaps,
                $openingHours
            );

            $pickupPoints[] = $this->transform($cpPoint);
        }


        return $pickupPoints;

    }

    public function findPickupPoint(PickupPointCode $code): ?PickupPointInterface
    {
        return null;
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

        return $pickupPoint;
    }
}
