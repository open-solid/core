<?php

declare(strict_types=1);

namespace OpenSolid\Core\Domain\Error;

final class InvalidStateTransition extends InvariantViolation
{
    /**
     * @param list<\BackedEnum> $allowed
     */
    public static function transition(\BackedEnum $from, \BackedEnum $to, array $allowed): self
    {
        $allowed = array_map(static fn (\BackedEnum $enum): string => $enum->value, $allowed);

        $hint = [] === $allowed
            ? sprintf('State "%s" is terminal and cannot transition to any other state.', $from->value)
            : sprintf('Allowed transition states from "%s": %s.', $from->value, implode(', ', $allowed));

        return self::create(sprintf('The "%s" cannot transition from "%s" to "%s". %s', $from::class, $from->value, $to->value, $hint));
    }
}
