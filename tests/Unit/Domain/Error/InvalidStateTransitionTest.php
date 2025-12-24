<?php

declare(strict_types=1);

namespace OpenSolid\Core\Tests\Unit\Domain\Error;

use OpenSolid\Core\Domain\Error\InvalidStateTransition;
use OpenSolid\Core\Domain\Error\InvariantViolation;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class InvalidStateTransitionTest extends TestCase
{
    #[Test]
    public function transitionWithAllowedStates(): void
    {
        $from = TestStatus::Pending;
        $to = TestStatus::Cancelled;
        $allowed = [TestStatus::Approved, TestStatus::Rejected];

        $error = InvalidStateTransition::transition($from, $to, $allowed);

        $expectedMessage = sprintf(
            'The "%s" cannot transition from "pending" to "cancelled". Allowed transition states from "pending": approved, rejected.',
            TestStatus::class
        );

        $this->assertSame($expectedMessage, $error->getMessage());
    }

    #[Test]
    public function transitionWithTerminalState(): void
    {
        $from = TestStatus::Cancelled;
        $to = TestStatus::Pending;
        $allowed = [];

        $error = InvalidStateTransition::transition($from, $to, $allowed);

        $expectedMessage = sprintf(
            'The "%s" cannot transition from "cancelled" to "pending". State "cancelled" is terminal and cannot transition to any other state.',
            TestStatus::class
        );

        $this->assertSame($expectedMessage, $error->getMessage());
    }

    #[Test]
    public function transitionWithSingleAllowedState(): void
    {
        $from = TestStatus::Pending;
        $to = TestStatus::Cancelled;
        $allowed = [TestStatus::Approved];

        $error = InvalidStateTransition::transition($from, $to, $allowed);

        $this->assertStringContainsString('Allowed transition states from "pending": approved.', $error->getMessage());
    }
}

enum TestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
}
