<?php

declare(strict_types=1);

namespace Setono\SyliusPickupPointPlugin\Exception;

use RuntimeException;

/**
 * Thrown when Colissimo's "Point Retrait" webservice responds with a business/configuration
 * error code (account not authorized, international option not activated for this account,
 * country not eligible, etc.), as opposed to a genuine "no pickup point found nearby" result.
 *
 * See La Poste's "WebService de choix de livraison" technical documentation, §III "Codes erreurs":
 *   146 Pays non éligible à Colissimo Europe
 *   201 Identifiant / mot de passe invalide
 *   202 Service non autorisé pour cet identifiant
 *   203 Option internationale non compatible avec le pays
 *   1000 Erreur système (erreur technique)
 *
 * Codes 300 (no point found after business rules) and 301 (no point found) are NOT
 * considered errors here: they represent a legitimate empty result and must not throw.
 */
final class ColissimoApiException extends RuntimeException implements ExceptionInterface
{
    /**
     * Error codes that mean "nothing found nearby", which is a normal, expected outcome.
     */
    private const BENIGN_ERROR_CODES = ['0', '300', '301'];

    public static function isBenign(?string $errorCode): bool
    {
        return null === $errorCode || in_array($errorCode, self::BENIGN_ERROR_CODES, true);
    }

    public static function fromResponse(?string $errorCode, ?string $errorMessage, string $countryCode, int $optionInter): self
    {
        return new self(sprintf(
            'Colissimo "Point Retrait" webservice returned error code %s (%s) for countryCode=%s, optionInter=%d. '
            . 'This usually means the Colissimo account is not authorized/activated for international pickup '
            . 'point search for this country (contact La Poste/Colissimo support to activate the "option '
            . 'internationale Point Retrait" on the account, and confirm the country is in their eligible list).',
            $errorCode ?? 'unknown',
            $errorMessage ?? 'no message',
            $countryCode,
            $optionInter
        ));
    }
}
