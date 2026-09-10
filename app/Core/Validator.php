<?php

declare(strict_types=1);

namespace Moves\Core;

/**
 * Moves | Validator
 *
 * Valida dados recebidos pela aplicação.
 *
 * Mantém as regras de validação separadas dos controladores
 * e dos dados recebidos pela requisição.
 *
 * @author Djalma Martins
 * @package Moves\Core
 */
final class Validator
{
    /**
     * Armazena os erros encontrados durante a validação.
     *
     * @var array<string, string>
     */
    private array $errors = [];

    /**
     * Valida se um valor obrigatório foi informado.
     */
    public function required(
        string $field,
        mixed $value,
        ?string $message = null
    ): self {
        if (
            $value === null
            || (is_string($value) && trim($value) === '')
        ) {
            $this->errors[$field] = $message
                ?? 'Este campo é obrigatório.';
        }

        return $this;
    }

    /**
     * Valida um endereço de e-mail.
     */
    public function email(
        string $field,
        mixed $value,
        ?string $message = null
    ): self {
        if (
            $value !== null
            && $value !== ''
            && filter_var(
                $value,
                FILTER_VALIDATE_EMAIL
            ) === false
        ) {
            $this->errors[$field] = $message
                ?? 'Informe um endereço de e-mail válido.';
        }

        return $this;
    }

    /**
     * Valida o tamanho mínimo de uma string.
     */
    public function min(
        string $field,
        mixed $value,
        int $length,
        ?string $message = null
    ): self {
        if (
            is_string($value)
            && mb_strlen($value) < $length
        ) {
            $this->errors[$field] = $message
                ?? "Este campo deve ter no mínimo {$length} caracteres.";
        }

        return $this;
    }

    /**
     * Valida o tamanho máximo de uma string.
     */
    public function max(
        string $field,
        mixed $value,
        int $length,
        ?string $message = null
    ): self {
        if (
            is_string($value)
            && mb_strlen($value) > $length
        ) {
            $this->errors[$field] = $message
                ?? "Este campo deve ter no máximo {$length} caracteres.";
        }

        return $this;
    }

    /**
     * Verifica se existem erros de validação.
     */
    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /**
     * Verifica se todos os dados passaram na validação.
     */
    public function passes(): bool
    {
        return !$this->fails();
    }

    /**
     * Retorna todos os erros encontrados.
     *
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Retorna o erro de um campo específico.
     */
    public function error(string $field): ?string
    {
        return $this->errors[$field] ?? null;
    }
}