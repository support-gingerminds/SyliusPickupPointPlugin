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
use Setono\SyliusPickupPointPlugin\Exception\TimeoutException;
use Setono\SyliusPickupPointPlugin\Exception\ColissimoApiException;

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

        // Colissimo's webservice requires countryCode/optionInter to be consistent:
        // optionInter = 0 means "France only" (default), 1 means "international only".
        // See La Poste's "WebService de choix de livraison" technical doc, §II.5.1.
        $countryCode = strtoupper((string) ($address->getCountryCode() ?: 'FR'));
        $optionInter = $countryCode !== 'FR' ? 1 : 0;

        try {
            $date = new \DateTime();

            $client = new \SoapClient('https://ws.colissimo.fr/pointretrait-ws-cxf/PointRetraitServiceWS/2.0?wsdl', [
                'wsdl_cache' => 0,
                'trace' => 1,
                'exceptions' => true,
                'soap_version' => SOAP_1_1,
                'encoding' => 'utf-8'
            ]);

            $cpPoints = $client->findRDVPointRetraitAcheminement([
                "accountNumber" => $this->colissimoAccount,
                "password" => $this->colissimoPassword,
                "address" => $address->getStreet(),
                "zipCode" => $address->getPostcode(),
                "city" => $address->getCity(),
                "codTiersPourPartenaire" => $this->colissimoAccount,
                "countryCode" => $countryCode,
                "weight" => sprintf('%05d', $order->getTotalWeight() > 0 ? $order->getTotalWeight() : 1),
                "shippingDate" => $date->format('d/m/Y'),
                "filterRelay" => 1,
                "optionInter" => $optionInter
            ]);
        } catch (ConnectionException $e) {
            throw new TimeoutException($e);
        }

        $errorCode = isset($cpPoints->return->errorCode) ? (string) $cpPoints->return->errorCode : null;
        if (!ColissimoApiException::isBenign($errorCode)) {
            throw ColissimoApiException::fromResponse(
                $errorCode,
                $cpPoints->return->errorMessage ?? null,
                $countryCode,
                $optionInter
            );
        }

        $pickupPoints = [];
        foreach ($cpPoints->return->listePointRetraitAcheminement ?? [] as $item) {

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
                $item->typeDePoint,
                $item->codePays ?? $countryCode,
                $item->reseau ?? null
            );

            $pickupPoints[] = $this->transform($cpPoint);
        }


        return $pickupPoints;

    }

    public function findPickupPoint(PickupPointCode $code): ?PickupPointInterface
    {
        // NB: unlike findRDVPointRetraitAcheminement, La Poste's findPointRetraitAcheminementByID
        // does not accept an optionInter parameter (it looks up a point by its unique id). But it
        // DOES need a "reseau" (network) parameter for anything outside the French R01-R11 range:
        // without it, the webservice assumes a French network and returns errorCode 124
        // ("Identifiant point de retrait incorrect") even for a perfectly valid foreign id.
        // Since the id round-trips through forms/APIs as an opaque string, we smuggle the
        // network in it as "<realId>:<reseau>" (see transform()) and split it back out here.
        $countryCode = strtoupper($code->getCountryPart() ?: 'FR');

        [$realId, $reseau] = array_pad(explode(':', $code->getIdPart(), 2), 2, null);

        try {
            $date = new \DateTime();

            $client = new \SoapClient('https://ws.colissimo.fr/pointretrait-ws-cxf/PointRetraitServiceWS/2.0?wsdl', [
                'wsdl_cache' => 0,
                'trace' => 1,
                'exceptions' => true,
                'soap_version' => SOAP_1_1,
                'encoding' => 'utf-8'
            ]);

            $params = [
                "accountNumber" => $this->colissimoAccount,
                "password" => $this->colissimoPassword,
                "id" => $realId,
                "weight" => 1,
                "date" => $date->format('d/m/Y'),
                "filterRelay" => 1
            ];

            if (null !== $reseau && '' !== $reseau) {
                $params['reseau'] = $reseau;
            }

            $cpPointResponse = $client->findPointRetraitAcheminementByID($params);
        } catch (ConnectionException $e) {
            throw new TimeoutException($e);
        }

        $return = $cpPointResponse->return;

        $errorCode = isset($return->errorCode) ? (string) $return->errorCode : null;
        if (!ColissimoApiException::isBenign($errorCode)) {
            throw ColissimoApiException::fromResponse(
                $errorCode,
                sprintf(
                    '%s [debug: rawIdPart=%s realId=%s reseau=%s]',
                    $return->errorMessage ?? '',
                    $code->getIdPart(),
                    $realId ?? 'null',
                    $reseau ?? 'null'
                ),
                $countryCode,
                0
            );
        }

        if (!isset($return->pointRetraitAcheminement)) {
            return null;
        }

        $item = $return->pointRetraitAcheminement;

        if (
            null == $item->adresse1
            || null == $item->codePostal
            || null == $item->localite
            || null == $item->identifiant
            || null == $item->nom
            || null == $item->coordGeolocalisationLatitude
            || null == $item->coordGeolocalisationLongitude
            || null == $item->distanceEnMetre
            || null == $item->typeDePoint
        ) {
            return null;
        }

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
            $item->typeDePoint,
            $item->codePays ?? $countryCode,
            $item->reseau ?? $reseau
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

        // Use the country actually returned by Colissimo's webservice (codePays)
        // instead of hardcoding 'FR', so pickup points located in Belgium,
        // Switzerland, etc. are labeled correctly.
        $country = $cpPoint->getPays() ?? 'FR';

        // Smuggle the network ("reseau") into the id as "<realId>:<reseau>". This id is what
        // gets round-tripped verbatim through admin forms and the headless front API when a
        // point is selected; findPickupPoint() below splits it back apart. Without the network,
        // re-fetching a non-French point by id fails with errorCode 124 (see findPickupPoint()).
        $reseau = $cpPoint->getReseau();
        $id = (null !== $reseau && '' !== $reseau)
            ? $cpPoint->getIdCpPoint() . ':' . $reseau
            : $cpPoint->getIdCpPoint();

        $pickupPoint->setCode(new PickupPointCode($id, $this->getCode(), $country));

        $pickupPoint->setName($cpPoint->getNomEnseigne());
        $pickupPoint->setAddress($cpPoint->getAdresse1());
        $pickupPoint->setZipCode($cpPoint->getCodePostal());
        $pickupPoint->setCity($cpPoint->getLocalite());
        $pickupPoint->setLatitude($cpPoint->getCoordGeoLatitude());
        $pickupPoint->setLongitude($cpPoint->getCoordGeoLongitude());
        $pickupPoint->setCountry($country);
        $pickupPoint->setOpeningHours($cpPoint->getOpeningHours());
        $pickupPoint->setDistance($cpPoint->getDistance());
        $pickupPoint->setType($cpPoint->getType());

        return $pickupPoint;
    }
}
