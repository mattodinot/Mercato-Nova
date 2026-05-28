<?php
declare(strict_types=1);

/**
 * Helpers de validation cote serveur (en plus de la validation cote client).
 * Volontairement simples : conformes au contexte pedagogique du projet.
 */
final class Validator
{
    public static function email(?string $v): bool
    {
        return is_string($v) && filter_var($v, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Mot de passe : min 8 caracteres, au moins 1 lettre et 1 chiffre.
     */
    public static function password(?string $v): bool
    {
        return is_string($v)
            && strlen($v) >= 8
            && preg_match('/[A-Za-z]/', $v) === 1
            && preg_match('/[0-9]/', $v) === 1;
    }

    public static function nonEmpty(?string $v, int $max = 255): bool
    {
        return is_string($v) && trim($v) !== '' && mb_strlen($v) <= $max;
    }

    public static function inEnum(mixed $v, array $allowed): bool
    {
        return is_string($v) && in_array($v, $allowed, true);
    }

    public static function positiveNumber(mixed $v): bool
    {
        return is_numeric($v) && (float) $v > 0;
    }
}
