<?php

declare(strict_types=1);

namespace OpenSolid\Core\Tests\Unit\Infrastructure\Bus\Command;

use OpenSolid\Bus\Error\NoHandlerForMessage;
use OpenSolid\Bus\MessageBus;
use OpenSolid\Core\Application\Command\Message\Command;
use OpenSolid\Core\Infrastructure\Bus\Command\Error\NoHandlerForCommand;
use OpenSolid\Core\Infrastructure\Bus\Command\NativeCommandBus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class NativeCommandBusTest extends TestCase
{
    #[Test]
    public function executesCommandSuccessfully(): void
    {
        $command = new TestCommand();
        $expectedResult = 'command-result';

        $messageBus = $this->createMock(MessageBus::class);
        $messageBus->expects($this->once())
            ->method('dispatch')
            ->with($command)
            ->willReturn($expectedResult);

        $bus = new NativeCommandBus($messageBus);
        $result = $bus->execute($command);

        $this->assertSame($expectedResult, $result);
    }

    #[Test]
    public function executesCommandWithNullResult(): void
    {
        $command = new TestCommand();

        $messageBus = $this->createMock(MessageBus::class);
        $messageBus->expects($this->once())
            ->method('dispatch')
            ->with($command)
            ->willReturn(null);

        $bus = new NativeCommandBus($messageBus);
        $result = $bus->execute($command);

        $this->assertNull($result);
    }

    #[Test]
    public function translatesNoHandlerForMessageToNoHandlerForCommand(): void
    {
        $command = new TestCommand();

        $messageBus = $this->createMock(MessageBus::class);
        $messageBus->expects($this->once())
            ->method('dispatch')
            ->with($command)
            ->willThrowException(new NoHandlerForMessage('No handler'));

        $bus = new NativeCommandBus($messageBus);

        $this->expectException(NoHandlerForCommand::class);
        $this->expectExceptionMessage(sprintf('No handler for command of type "%s".', TestCommand::class));

        $bus->execute($command);
    }

    #[Test]
    public function noHandlerForCommandPreservesPreviousException(): void
    {
        $command = new TestCommand();
        $originalException = new NoHandlerForMessage('No handler');

        $messageBus = $this->createStub(MessageBus::class);
        $messageBus->method('dispatch')
            ->willThrowException($originalException);

        $bus = new NativeCommandBus($messageBus);

        try {
            $bus->execute($command);
            $this->fail('Expected NoHandlerForCommand exception');
        } catch (NoHandlerForCommand $e) {
            $this->assertSame($originalException, $e->getPrevious());
        }
    }

    #[Test]
    public function propagatesOtherExceptions(): void
    {
        $command = new TestCommand();
        $originalException = new \RuntimeException('Unexpected error');

        $messageBus = $this->createStub(MessageBus::class);
        $messageBus->method('dispatch')
            ->willThrowException($originalException);

        $bus = new NativeCommandBus($messageBus);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unexpected error');

        $bus->execute($command);
    }
}

final readonly class TestCommand extends Command
{
}
