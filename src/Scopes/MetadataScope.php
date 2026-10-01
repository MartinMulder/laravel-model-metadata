<?php

namespace MartinMulder\LaravelModelMetadata\Scopes;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Describes how instances of one HasMetadata model ("owner") are grouped into metadata scopes,
 * so a package can define scopes for its own models — e.g. documents scoped by document type:
 *
 *     MetadataScope::for(Document::class)
 *         ->label('Document')
 *         ->scopedBy(DocumentType::class, key: 'slug', title: 'naam', label: 'Document type')
 *         ->resolveUsing(fn (Document $document) => $document->type?->slug);
 *
 * Register it with MetadataScopes::register() in the package's service provider. The admin UI
 * then offers the owner and its scope values as choices instead of free text.
 */
final class MetadataScope
{
    private ?string $label = null;

    private ?string $scopeLabel = null;

    /** @var Closure(): array<string|int, string>|array<string|int, string>|null */
    private Closure|array|null $options = null;

    private ?Closure $resolver = null;

    /** @var class-string<Model>|null */
    private ?string $source = null;

    private string $sourceKey = 'id';

    /**
     * @param  class-string<Model>  $owner
     */
    private function __construct(public readonly string $owner) {}

    /**
     * @param  class-string<Model>  $owner  the HasMetadata model
     */
    public static function for(string $owner): self
    {
        return new self($owner);
    }

    /** Human name of the owner model, e.g. "Document". */
    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /** Human name of what a scope value is, e.g. "Document type". */
    public function scopeLabel(string $label): self
    {
        $this->scopeLabel = $label;

        return $this;
    }

    /**
     * The possible scope values with their labels: [scope value => label].
     *
     * @param  Closure(): array<string|int, string>|array<string|int, string>  $options
     */
    public function options(Closure|array $options): self
    {
        $this->options = $options;

        return $this;
    }

    /**
     * How an owner instance determines its scope. Without a resolver, the model's own
     * metadataScope() decides (null by default).
     *
     * @param  Closure(Model): (string|int|null)  $resolver
     */
    public function resolveUsing(Closure $resolver): self
    {
        $this->resolver = $resolver;

        return $this;
    }

    /**
     * Scope values are records of another model (the "source"), identified by $key. Fills the
     * options from that model and lets the source model manage its fields through the
     * ProvidesMetadataScope trait and the MetadataSchemasRelationManager.
     *
     * @param  class-string<Model>  $source
     */
    public function scopedBy(string $source, string $key = 'id', ?string $title = null, ?string $label = null): self
    {
        $this->source = $source;
        $this->sourceKey = $key;
        $this->scopeLabel ??= $label ?? class_basename($source);
        $this->options ??= function () use ($source, $key, $title): array {
            $title ??= $key;

            return $source::query()->orderBy($title)->pluck($title, $key)->all();
        };

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label ?? class_basename($this->owner);
    }

    public function getScopeLabel(): string
    {
        return $this->scopeLabel ?? 'Scope';
    }

    public function hasOptions(): bool
    {
        return $this->options !== null;
    }

    /** @return array<string, string> */
    public function getOptions(): array
    {
        $options = $this->options instanceof Closure ? ($this->options)() : ($this->options ?? []);

        $result = [];
        foreach ($options as $value => $label) {
            $result[(string) $value] = (string) $label;
        }

        return $result;
    }

    /** Label of one scope value; the raw value when it is not (or no longer) an option. */
    public function getOptionLabel(?string $scope): ?string
    {
        if ($scope === null) {
            return null;
        }

        return $this->getOptions()[$scope] ?? $scope;
    }

    public function hasResolver(): bool
    {
        return $this->resolver !== null;
    }

    public function resolve(Model $model): ?string
    {
        if ($this->resolver === null) {
            return null;
        }

        $scope = ($this->resolver)($model);

        return $scope === null || $scope === '' ? null : (string) $scope;
    }

    /** @return class-string<Model>|null */
    public function getSource(): ?string
    {
        return $this->source;
    }

    public function getSourceKey(): string
    {
        return $this->sourceKey;
    }
}
