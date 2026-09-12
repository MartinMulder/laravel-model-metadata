<?php

namespace MartinMulder\LaravelModelMetadata\Exceptions;

use RuntimeException;

class RequiredMetadataKeyException extends RuntimeException
{
    public static function forKey(string $key, string $modelClass): self
    {
        return new self("The metadata key '{$key}' is required on [{$modelClass}] and cannot be deleted.");
    }
}
