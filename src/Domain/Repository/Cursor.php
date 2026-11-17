<?php

declare(strict_types=1);

namespace OpenSolid\Core\Domain\Repository;

/**
 * Represents a cursor for cursor-based pagination.
 *
 * A cursor contains the parameters needed to fetch the next or previous page of results.
 */
final readonly class Cursor
{
    /**
     * @param array<string, scalar|null> $parameters
     */
    public function __construct(
        private array $parameters,
        private bool $isNext = true,
    ) {
    }

    /**
     * @internal
     *
     * @return array<string, scalar|null>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Returns whether the cursor is for navigating to the next page.
     */
    public function isNext(): bool
    {
        return $this->isNext;
    }

    /**
     * Returns whether the cursor is for navigating to the previous page.
     */
    public function isPrevious(): bool
    {
        return !$this->isNext;
    }

    /**
     * @return array<string, scalar|null>
     */
    public function toArray(): array
    {
        return [...$this->parameters, '_isNext' => $this->isNext];
    }

    /**
     * Encodes the cursor to a URL-safe Base64 JSON string.
     */
    public function encodeToString(): string
    {
        return rtrim(strtr(base64_encode((string) json_encode($this->toArray())), '+/', '-_'), '=');
    }

    /**
     * Decodes a cursor from an encoded string.
     *
     * @throws InvalidCursor if decoding fails or the payload is invalid
     */
    public static function fromEncodedString(mixed $encodedString): self
    {
        if (!is_string($encodedString)) {
            throw new InvalidCursor('(non-string value)');
        }

        $decoded = base64_decode(strtr($encodedString, '-_', '+/'), strict: true);

        if (false === $decoded) {
            throw new InvalidCursor($encodedString);
        }

        try {
            $parameters = json_decode($decoded, associative: true, depth: 2, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidCursor($encodedString, $e);
        }

        if (!is_array($parameters)) {
            throw new InvalidCursor($encodedString);
        }

        $isNext = (bool) ($parameters['_isNext'] ?? true);

        unset($parameters['_isNext']);

        $validated = [];
        foreach ($parameters as $key => $value) {
            if (!is_scalar($value) && null !== $value) {
                throw new InvalidCursor($encodedString);
            }

            $validated[(string) $key] = $value;
        }

        return new self($validated, $isNext);
    }
}
