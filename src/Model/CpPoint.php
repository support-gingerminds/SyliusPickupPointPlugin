<?php

declare(strict_types=1);

namespace Setono\SyliusPickupPointPlugin\Model;

use stdClass;

final class CpPoint
{
    private string $adresse1;
    private string $codePostal;
    private string $dateArriveColis;
    private string $horairesOuvertureDimanche;
    private string $horairesOuvertureLundi;
    private string $horairesOuvertureMardi;
    private string $horairesOuvertureMercredi;
    private string $horairesOuvertureJeudi;
    private string $horairesOuvertureSamedi;
    private string $horairesOuvertureVendredi;
    private string $identifiantChronopostPointA2PAS;
    private string $localite;
    private string $nomEnseigne;
    private float $coordGeoLatitude;
    private float $coordGeoLongitude;
    private string $urlGoogleMaps;

    private int $distance;

    // Values to find later
    /*private int $distanceEnMetre;
    private string $typeDePoint;*/

    private array $openingHours;

    public function __construct(
        string $adresse1,
        string $codePostal,
        string $dateArriveColis,
        ?string $horairesOuvertureLundi,
        ?string $horairesOuvertureMardi,
        ?string $horairesOuvertureMercredi,
        ?string $horairesOuvertureJeudi,
        ?string $horairesOuvertureVendredi,
        ?string $horairesOuvertureSamedi,
        ?string $horairesOuvertureDimanche,
        string $identifiantChronopostPointA2PAS,
        string $localite,
        string $nomEnseigne,
        float $coordGeoLatitude,
        float $coordGeoLongitude,
        string $urlGoogleMaps,
        array $openingHours = [],
        int $distance
    ) {
        $this->adresse1 = $adresse1;
        $this->codePostal = $codePostal;
        $this->dateArriveColis = $dateArriveColis;
        $this->horairesOuvertureDimanche = $horairesOuvertureDimanche;
        $this->horairesOuvertureLundi = $horairesOuvertureLundi;
        $this->horairesOuvertureMardi = $horairesOuvertureMardi;
        $this->horairesOuvertureMercredi = $horairesOuvertureMercredi;
        $this->horairesOuvertureJeudi = $horairesOuvertureJeudi;
        $this->horairesOuvertureSamedi = $horairesOuvertureSamedi;
        $this->horairesOuvertureVendredi = $horairesOuvertureVendredi;
        $this->identifiantChronopostPointA2PAS = $identifiantChronopostPointA2PAS;
        $this->localite = $localite;
        $this->nomEnseigne = $nomEnseigne;
        $this->coordGeoLatitude = $coordGeoLatitude;
        $this->coordGeoLongitude = $coordGeoLongitude;
        $this->urlGoogleMaps = $urlGoogleMaps;
        $this->openingHours = $openingHours;
        $this->distance = $distance;
    }

    public static function createFromStdClass(stdClass $stdClass): self
    {
        $openingHours = [];

        $openingHours['Monday'] = $stdClass->horairesOuvertureLundi;
        $openingHours['Tuesday'] = $stdClass->horairesOuvertureMardi;
        $openingHours['Wednesday'] = $stdClass->horairesOuvertureMercredi;
        $openingHours['Thursday'] = $stdClass->horairesOuvertureJeudi;
        $openingHours['Friday'] = $stdClass->horairesOuvertureVendredi;
        $openingHours['Saturday'] = $stdClass->horairesOuvertureSamedi;
        $openingHours['Sunday'] = $stdClass->horairesOuvertureDimanche;

        return new self(
            $stdClass->adresse1,
            $stdClass->codePostal,
            $stdClass->dateArriveeColis,
            $stdClass->horairesOuvertureLundi,
            $stdClass->horairesOuvertureMardi,
            $stdClass->horairesOuvertureMercredi,
            $stdClass->horairesOuvertureJeudi,
            $stdClass->horairesOuvertureVendredi,
            $stdClass->horairesOuvertureSamedi,
            $stdClass->horairesOuvertureDimanche,
            $stdClass->identifiantChronopostPointA2PAS,
            $stdClass->localite,
            $stdClass->nomEnseigne,
            $stdClass->coordGeoLatitude,
            $stdClass->coordGeoLongitude,
            $stdClass->urlGoogleMaps,
            $openingHours,
            $stdClass->distanceEnMetre
        );
    }

    public function getAdresse1(): string
    {
        return $this->adresse1;
    }

    public function getCodePostal(): string
    {
        return $this->codePostal;
    }

    public function getDateArriveColis(): string
    {
        return $this->dateArriveColis;
    }

    public function getIdCpPoint(): string
    {
        return $this->identifiantChronopostPointA2PAS;
    }

    public function getLocalite(): string
    {
        return $this->localite;
    }

    public function getNomEnseigne(): string
    {
        return $this->nomEnseigne;
    }

    public function getCoordGeoLatitude(): float
    {
        return $this->coordGeoLatitude;
    }

    public function getCoordGeoLongitude(): float
    {
        return $this->coordGeoLongitude;
    }

    public function getUrlGoogleMaps(): string
    {
        return $this->urlGoogleMaps;
    }

    public function getOpeningHours(): array
    {
        return $this->openingHours;
    }

    public function getDistance(): int
    {
        return $this->distance;
    }
}
