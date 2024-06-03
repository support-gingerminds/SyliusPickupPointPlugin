<?php

declare(strict_types=1);

namespace Setono\SyliusPickupPointPlugin\Model;

interface PickupPointAwareInterface
{
    public function hasPickupPointIdentifier(): bool;

    public function setPickupPointIdentifier(?string $pickupPointIdentifier): void;

    public function getPickupPointIdentifier(): ?string;
}
