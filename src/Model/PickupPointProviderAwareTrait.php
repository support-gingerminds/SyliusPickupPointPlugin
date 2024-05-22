<?php

declare(strict_types=1);

namespace Setono\SyliusPickupPointPlugin\Model;

use Doctrine\ORM\Mapping as ORM;

trait PickupPointProviderAwareTrait
{
    /** @ORM\Column(name="pickup_point_provider", type="string", nullable=true) */
    #[ORM\Column(name:"pickup_point_provider", type: 'string', nullable: true)]
    protected ?string $pickupPointProvider;

    public function hasPickupPointProvider(): bool
    {
        return $this->pickupPointProvider !== null;
    }

    public function setPickupPointProvider(?string $pickupPointProvider): void
    {
        $this->pickupPointProvider = $pickupPointProvider;
    }

    public function getPickupPointProvider(): ?string
    {
        return $this->pickupPointProvider;
    }
}
