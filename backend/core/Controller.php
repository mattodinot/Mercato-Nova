<?php
declare(strict_types=1);

/**
 * Base controller : recuperation du body JSON et helpers de reponse.
 */
abstract class Controller
{
    protected function jsonInput(): array
    {
        $raw = file_get_contents('php://input');
        if (!$raw) {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    protected function query(string $key, ?string $default = null): ?string
    {
        $v = $_GET[$key] ?? null;
        return is_string($v) && $v !== '' ? $v : $default;
    }
}
