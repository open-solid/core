<?php

declare(strict_types=1);

namespace OpenSolid\Core\Tests\Integration\Bus;

use OpenSolid\Core\Application\Command\Message\Command;
use OpenSolid\Core\Domain\Envelop\Stamp\TransportStamp;
use OpenSolid\Core\Infrastructure\Bus\Command\Error\NoHandlerForCommand;
use OpenSolid\Core\Infrastructure\Bus\Command\SymfonyCommandBus;
use OpenSolid\Core\Infrastructure\Bus\Envelop\Stamp\Transformer\ChainStampTransformer;
use OpenSolid\Core\Infrastructure\Bus\Envelop\Stamp\Transformer\DefaultStampTransformer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\NoHandlerForMessageException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

class SymfonyCommandBusTest extends TestCase
{
    private ChainStampTransformer $stampTransformer;

    protected function setUp(): void
    {
        $this->stampTransformer = new ChainStampTransformer([
            new DefaultStampTransformer(),
        ]);
    }

    #[Test]
    public function executesCommandAndReturnsResult(): void
    {
        $command = new TestIntegrationCommand();
        $expectedResult = 'command-executed';

        $envelope = new Envelope($command, [
            new HandledStamp($expectedResult, 'TestHandler::__invoke'),
        ]);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturn($envelope);

        $bus = new SymfonyCommandBus($messageBus, $this->stampTransformer);
        $result = $bus->execute($command);

        $this->assertSame($expectedResult, $result);
    }

    #[Test]
    public function executesCommandWithStampsFromEnvelopeAttribute(): void
    {
        $command = new TestAsyncCommand();

        $capturedEnvelope = null;
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (Envelope $envelope) use (&$capturedEnvelope) {
                $capturedEnvelope = $envelope;
                return $envelope->with(
                    new HandledStamp('result', 'Handler'),
                );
            });

        $bus = new SymfonyCommandBus($messageBus, $this->stampTransformer);
        $bus->execute($command);

        $this->assertNotNull($capturedEnvelope);
        $stamps = $capturedEnvelope->all(TransportNamesStamp::class);
        $this->assertCount(1, $stamps);
        $this->assertSame(['async'], $stamps[0]->getTransportNames());
    }

    #[Test]
    public function throwsNoHandlerForCommandOnNoHandler(): void
    {
        $command = new TestIntegrationCommand();

        $messageBus = $this->createStub(MessageBusInterface::class);
        $messageBus->method('dispatch')
            ->willThrowException(new NoHandlerForMessageException());

        $bus = new SymfonyCommandBus($messageBus, $this->stampTransformer);

        $this->expectException(NoHandlerForCommand::class);

        $bus->execute($command);
    }

    #[Test]
    public function unwrapsHandlerFailedException(): void
    {
        $command = new TestIntegrationCommand();
        $originalException = new \InvalidArgumentException('Invalid input');
        $handlerFailedException = new HandlerFailedException(
            new Envelope($command),
            [$originalException]
        );

        $messageBus = $this->createStub(MessageBusInterface::class);
        $messageBus->method('dispatch')
            ->willThrowException($handlerFailedException);

        $bus = new SymfonyCommandBus($messageBus, $this->stampTransformer);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid input');

        $bus->execute($command);
    }

    #[Test]
    public function returnsNullForAsyncHandling(): void
    {
        $command = new TestIntegrationCommand();

        $envelope = new Envelope($command, [
            new SentStamp('async', 'AsyncSender'),
        ]);

        $messageBus = $this->createStub(MessageBusInterface::class);
        $messageBus->method('dispatch')
            ->willReturn($envelope);

        $bus = new SymfonyCommandBus($messageBus, $this->stampTransformer);
        $result = $bus->execute($command);

        $this->assertNull($result);
    }
}

final readonly class TestIntegrationCommand extends Command
{
}

#[TransportStamp('async')]
final readonly class TestAsyncCommand extends Command
{
}
