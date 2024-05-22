<?php

declare(strict_types=1);

namespace Setono\SyliusPickupPointPlugin\Provider;

use Setono\SyliusPickupPointPlugin\Model\PickupPointCode;
use Setono\SyliusPickupPointPlugin\Model\PickupPointInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

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

        $client = new \SoapClient('https://www.chronopost.fr/recherchebt-ws-cxf/PointRelaisServiceWS?wsdl', [
            'wsdl_cache' => 0,
            'trace' => 1,
            'exceptions' => true,
            'soap_version' => SOAP_1_1,
            'encoding' => 'utf-8'
        ]);

        $response = $client->rechercheBtParCodeproduitEtCodepostalEtDate([
            'codePostal' => $postalCode,
            'date' => date('d/m/Y')
        ]);

        $pickupPoints = [];
        foreach ($response as $item) {
            $pickupPoints[] = $this->transform($item);
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

    private function transform(ParcelShop $parcelShop): PickupPointInterface
    {
        /** @var PickupPointInterface|object $pickupPoint */
        $pickupPoint = $this->pickupPointFactory->createNew();

        Assert::isInstanceOf($pickupPoint, PickupPointInterface::class);

        $pickupPoint->setCode(new PickupPointCode($parcelShop->getNumber(), $this->getCode(), $parcelShop->getCountryCode()));
        $pickupPoint->setName($parcelShop->getCompanyName());
        $pickupPoint->setAddress($parcelShop->getStreetName());
        $pickupPoint->setZipCode($parcelShop->getZipCode());
        $pickupPoint->setCity($parcelShop->getCity());
        $pickupPoint->setCountry($parcelShop->getCountryCode());
        $pickupPoint->setLatitude((float) $parcelShop->getLatitude());
        $pickupPoint->setLongitude((float) $parcelShop->getLongitude());

        return $pickupPoint;
    }
}
