<?php

namespace MartinMulder\LaravelModelMetadata\Contracts;

interface MetadataType
{
    /**
     * Cast the raw database value to the PHP type.
     *
     * @param  mixed  $value
     * @return mixed
     */
    public function cast(mixed $value): mixed;

    /**
     * Serialize the PHP value back to the database format.
     *
     * @param  mixed  $value
     * @return mixed
     */
    public function serialize(mixed $value): mixed;

    /**
     * Validate if the given value is valid for this type.
     *
     * @param  mixed  $value
     * @return bool
     */
    public function validate(mixed $value): bool;
}
