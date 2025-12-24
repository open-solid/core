<?php

declare(strict_types=1);

namespace OpenSolid\Core\Tests\Unit\Domain\Error;

use OpenSolid\Core\Domain\Error\DomainError;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DomainErrorTest extends TestCase
{
    #[Test]
    public function createWithDefaultMessage(): void
    {
        $error = TestDomainError::create();

        $this->assertSame('A domain error occurred.', $error->getMessage());
        $this->assertSame(0, $error->getCode());
        $this->assertNull($error->getPrevious());
        $this->assertSame([], $error->errors);
    }

    #[Test]
    public function createWithCustomMessage(): void
    {
        $error = TestDomainError::create('Custom error message.', 42);

        $this->assertSame('Custom error message.', $error->getMessage());
        $this->assertSame(42, $error->getCode());
    }

    #[Test]
    public function createWithPreviousException(): void
    {
        $previous = new \RuntimeException('Previous error');
        $error = TestDomainError::create('Error with previous.', 0, $previous);

        $this->assertSame('Error with previous.', $error->getMessage());
        $this->assertSame($previous, $error->getPrevious());
    }

    #[Test]
    public function createManyAggregatesErrors(): void
    {
        $error1 = TestDomainError::create('First error.');
        $error2 = TestDomainError::create('Second error.');
        $error3 = TestDomainError::create('Third error.');

        $aggregated = TestDomainError::createMany([$error1, $error2, $error3]);

        $this->assertSame('First error. Second error. Third error.', $aggregated->getMessage());
        $this->assertCount(3, $aggregated->errors);
        $this->assertSame($error1, $aggregated->errors[0]);
        $this->assertSame($error2, $aggregated->errors[1]);
        $this->assertSame($error3, $aggregated->errors[2]);
    }

    #[Test]
    public function createManyWithSingleError(): void
    {
        $error = TestDomainError::create('Only error.');

        $aggregated = TestDomainError::createMany([$error]);

        $this->assertSame('Only error.', $aggregated->getMessage());
        $this->assertCount(1, $aggregated->errors);
    }

    #[Test]
    public function createManyWithEmptyArray(): void
    {
        $aggregated = TestDomainError::createMany([]);

        $this->assertSame('', $aggregated->getMessage());
        $this->assertSame([], $aggregated->errors);
    }
}

/**
 * Concrete implementation for testing the abstract DomainError.
 */
class TestDomainError extends DomainError
{
}
