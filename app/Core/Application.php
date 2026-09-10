<?php

declare(strict_types=1);

namespace Moves\Core;
/**
 * Moves | Application
 *
 * Inicializa e executa o fluxo principal da aplicação.
 *
 * @author Djalma Martins
 * @package Moves\Core
 */
final class Application
{
    public function run(): void
    {
        echo Config::get('APP_NAME', 'Moves');
    }
}