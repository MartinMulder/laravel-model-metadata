<?php

namespace MartinMulder\LaravelModelMetadata\Attributes;

use Attribute;
use MartinMulder\LaravelModelMetadata\Options\MetadataOptionSourceRegistry;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class RequiresMetadata
{
    /**
     * @param  array<int, mixed>|null  $options  fixed allowed values
     * @param  string|null  $optionsSource  name of a registered option source (MetadataOptionSources),
     *   used instead of $options: the values are its keys, its labels are for display
     */
    public function __construct(
        public readonly string $key,
        public readonly mixed $default,
        public readonly string $type = 'string',
        public readonly ?array $options = null,
        public readonly bool $required = true,
        public readonly ?string $optionsSource = null,
    ) {
    }

    /**
     * The choices as [value => label]: from the option source, or the fixed options (value and
     * label alike). Null when the key is unconstrained — no options, an empty list, or a source
     * that isn't registered (e.g. the package offering it isn't installed).
     *
     * @return array<string|int, string>|null
     */
    public function optionLabels(): ?array
    {
        if ($this->optionsSource !== null) {
            $options = app(MetadataOptionSourceRegistry::class)->get($this->optionsSource)?->getOptions();

            return $options === null || $options === [] ? null : $options;
        }

        if ($this->options === null || $this->options === []) {
            return null;
        }

        $values = array_map(fn ($option) => (string) $option, $this->options);

        return array_combine($values, $values);
    }

    /**
     * Whether a value is one of the allowed choices. Fixed options compare strictly (as before);
     * source keys compare as strings, since form state and stored JSON may differ in type.
     */
    public function allows(mixed $value): bool
    {
        if ($this->optionsSource !== null) {
            $options = $this->optionLabels();

            // No value (a cleared field) is not a choice to check.
            return $value === null || $options === null || array_key_exists((string) $value, $options);
        }

        return $this->options === null || $this->options === [] || in_array($value, $this->options, true);
    }
}
