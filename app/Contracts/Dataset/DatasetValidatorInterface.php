<?php

namespace App\Contracts\Dataset;

interface DatasetValidatorInterface
{
    /**
     * Validate a decoded row payload against the project schema.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $schema
     */
    public function validate(array $payload, ?array $schema): bool;
}
