<?php

namespace MartinMulder\LaravelModelMetadata\Options;

use Closure;

/**
 * A named list of choices that metadata fields can use instead of a fixed list of options — e.g.
 * a package offering its own records as values, without the owner model knowing that package:
 *
 *     MetadataOptionSources::register(
 *         MetadataOptionSource::make('normenkader-onderwerpen')
 *             ->label('Onderwerpen (normenkaders)')
 *             ->options(fn (): array => Onderwerp::orderBy('naam')->pluck('naam', 'slug')->all()),
 *     );
 *
 * A metadata schema row then refers to it by name (options_source). The stored values are the
 * keys (stable, e.g. a slug), the labels are only for display.
 */
final class MetadataOptionSource
{
    private ?string $label = null;

    /** @var Closure(): array<string|int, string>|array<string|int, string> */
    private Closure|array $options = [];

    private function __construct(public readonly string $name) {}

    public static function make(string $name): self
    {
        return new self($name);
    }

    /** Human name of the source, shown when choosing it for a metadata field. */
    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * The choices: [stored key => label]. A closure is evaluated on every call, so new records
     * show up without a restart.
     *
     * @param  Closure(): array<string|int, string>|array<string|int, string>  $options
     */
    public function options(Closure|array $options): self
    {
        $this->options = $options;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label ?? $this->name;
    }

    /** @return array<string, string> [key => label], keys as strings */
    public function getOptions(): array
    {
        $options = $this->options instanceof Closure ? ($this->options)() : $this->options;
        $result = [];

        foreach ($options as $key => $label) {
            $result[(string) $key] = (string) $label;
        }

        return $result;
    }
}
