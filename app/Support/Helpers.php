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
    /** Renderiza exclusivamente a webfont oficial Moves Icons. */
    function studio_icon(string $name, string $class = ''): string
    {
        $icons = [
            'apps'=>'icon-apps-outline', 'archive'=>'icon-archive-outline', 'bell'=>'icon-notifications-outline',
            'briefcase'=>'icon-briefcase-outline', 'bug'=>'icon-bug-outline', 'calendar'=>'icon-calendar-outline',
            'chart'=>'icon-stats-chart-outline', 'chevron-down'=>'icon-chevron-down', 'circle-help'=>'icon-help-circle-outline',
            'copy'=>'icon-copy-outline', 'dashboard'=>'icon-grid-outline', 'external-link'=>'icon-open-outline',
            'file-text'=>'icon-document-text-outline', 'globe'=>'icon-globe-outline', 'headphones'=>'icon-headset-outline',
            'image'=>'icon-image-outline', 'log-out'=>'icon-log-out-outline', 'message-square'=>'icon-chatbox-outline',
            'moon'=>'icon-moon-outline', 'newspaper'=>'icon-newspaper-outline', 'pencil'=>'icon-pencil-outline',
            'search'=>'icon-search-outline', 'settings'=>'icon-settings-outline', 'sparkles'=>'icon-sparkles-outline',
            'tag'=>'icon-pricetag-outline', 'trash'=>'icon-trash-outline', 'trash-2'=>'icon-trash-outline',
            'upload'=>'icon-cloud-upload-outline', 'users'=>'icon-people-outline', 'refresh-cw'=>'icon-refresh-outline',
            'add'=>'icon-add-outline', 'save'=>'icon-save-outline', 'eye'=>'icon-eye-outline',
        ];

        $icon = $icons[$name] ?? 'icon-help-circle-outline';
        $safeClass = preg_replace('/[^a-zA-Z0-9 _-]/', '', $class) ?: '';

        return '<i class="'.htmlspecialchars(trim($icon . ' ' . $safeClass), ENT_QUOTES, 'UTF-8').'" aria-hidden="true"></i>';
    }
}
