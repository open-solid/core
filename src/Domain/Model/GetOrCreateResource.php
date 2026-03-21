<?php

declare(strict_types=1);

namespace OpenSolid\Core\Domain\Model;

/**
 * @template T of object
 */
final readonly class GetOrCreateResource
{
    /**
     * @template TInstance of object
     *
     * @param TInstance $resource
     *
     * @return self<TInstance>
     */
    public static function created(object $resource): self
    {
        return new self($resource, created: true);
    }

    /**
     * @template TInstance of object
     *
     * @param TInstance $resource
     *
     * @return self<TInstance>
     */
    public static function existing(object $resource): self
    {
        return new self($resource, existing: true);
    }

    /**
     * @param T $resource
     */
    private function __construct(
        public object $resource,
        public bool $created = false,
        public bool $existing = false,
    ) {
    }

    /**
     * @template TMapped of object
     *
     * @param \Closure(T): TMapped $mapper
     *
     * @return self<TMapped>
     */
    public function map(\Closure $mapper): self
    {
        return new self($mapper($this->resource), $this->created, $this->existing);
    }
}
