<?php

declare(strict_types=1);

use Moves\Core\Config;
use Moves\Core\Settings;

if (!function_exists('config')) {
    /**
     * Moves | Config Helper
     *
     * Retorna uma configuração da aplicação carregada do ambiente.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function config(
        string $key,
        mixed $default = null
    ): mixed {
        return Config::get($key, $default);
    }
}

if (!function_exists('setting')) {
    /**
     * Moves | Setting Helper
     *
     * Retorna uma configuração persistida da aplicação.
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    function setting(
        string $name,
        mixed $default = null
    ): mixed {
        return Settings::get($name, $default);
    }
}
