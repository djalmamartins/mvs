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

if (!function_exists('studio_icon')) {
    /** Renderiza um ícone do sprite local do Studio. */
    function studio_icon(string $name, string $class = ''): string
    {
        $allowed = [
            'apps', 'archive', 'bell', 'bug', 'calendar', 'chart', 'chevron-down',
            'circle-help', 'copy', 'dashboard', 'external-link', 'file-text', 'globe',
            'headphones', 'image', 'log-out', 'message-square', 'moon', 'newspaper',
            'pencil', 'search', 'settings', 'sparkles', 'tag', 'trash', 'upload', 'users',
        ];

        if (!in_array($name, $allowed, true)) {
            $name = 'circle-help';
        }

        $safeClass = preg_replace('/[^a-zA-Z0-9 _-]/', '', $class) ?: '';

        return '<svg class="studio-icon '.htmlspecialchars($safeClass, ENT_QUOTES, 'UTF-8').'" aria-hidden="true"><use href="#studio-icon-'.htmlspecialchars($name, ENT_QUOTES, 'UTF-8').'" /></svg>';
    }
}
