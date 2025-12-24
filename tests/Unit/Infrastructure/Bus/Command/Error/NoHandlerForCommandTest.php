<?php

declare(strict_types=1);

namespace OpenSolid\Core\Tests\Unit\Infrastructure\Bus\Command\Error;

use OpenSolid\Core\Application\Command\Message\Command;
use OpenSolid\Core\Infrastructure\Bus\Command\Error\NoHandlerForCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class NoHandlerForCommandTest extends TestCase
{
    #[Test]
    public function createWithCommand(): void
    {
        $command = new TestCommand();

        $error = NoHandlerForCommand::create($command);

        $this->assertSame(
            sprintf('No handler for command of type "%s".', TestCommand::class),
            $error->getMessage()
        );
        $this->assertSame(0, $error->getCode());
        $this->assertNull($error->getPrevious());
    }

    #[Test]
    public function createWithPreviousException(): void
    {
        $command = new TestCommand();
        $previous = new \RuntimeException('Original error');

        $error = NoHandlerForCommand::create($command, $previous);

        $this->assertSame($previous, $error->getPrevious());
    }

    #[Test]
    public function createWithCustomCode(): void
    {
        $command = new TestCommand();

        $error = NoHandlerForCommand::create($command, null, 500);

        $this->assertSame(500, $error->getCode());
    }

    #[Test]
    public function messageContainsFullyQualifiedClassName(): void
    {
        $command = new TestCommand();

        $error = NoHandlerForCommand::create($command);

        $this->assertStringContainsString(
            'OpenSolid\Core\Tests\Unit\Infrastructure\Bus\Command\Error\TestCommand',
            $error->getMessage()
        );
    }
}

final readonly class TestCommand extends Command
{
}
