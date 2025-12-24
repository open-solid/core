<?php

declare(strict_types=1);

namespace OpenSolid\Core\Tests\Integration\Bus;

use OpenSolid\Core\Application\Query\Message\Query;
use OpenSolid\Core\Infrastructure\Bus\Envelop\Stamp\Transformer\ChainStampTransformer;
use OpenSolid\Core\Infrastructure\Bus\Envelop\Stamp\Transformer\DefaultStampTransformer;
use OpenSolid\Core\Infrastructure\Bus\Query\Error\NoHandlerForQuery;
use OpenSolid\Core\Infrastructure\Bus\Query\SymfonyQueryBus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\Exception\NoHandlerForMessageException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

class SymfonyQueryBusTest extends TestCase
{
    private ChainStampTransformer $stampTransformer;

    protected function setUp(): void
    {
        $this->stampTransformer = new ChainStampTransformer([
            new DefaultStampTransformer(),
        ]);
    }

    #[Test]
    public function asksQueryAndReturnsResult(): void
    {
        $query = new TestIntegrationQuery();
        $expectedResult = ['data' => 'value', 'count' => 42];

        $envelope = new Envelope($query, [
            new HandledStamp($expectedResult, 'TestHandler::__invoke'),
        ]);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturn($envelope);

        $bus = new SymfonyQueryBus($messageBus, $this->stampTransformer);
        $result = $bus->ask($query);

        $this->assertSame($expectedResult, $result);
    }

    #[Test]
    public function asksQueryWithObjectResult(): void
    {
        $query = new TestIntegrationQuery();
        $expectedResult = new \stdClass();
        $expectedResult->name = 'Test';

        $envelope = new Envelope($query, [
            new HandledStamp($expectedResult, 'TestHandler::__invoke'),
        ]);

        $messageBus = $this->createStub(MessageBusInterface::class);
        $messageBus->method('dispatch')
            ->willReturn($envelope);

        $bus = new SymfonyQueryBus($messageBus, $this->stampTransformer);
        $result = $bus->ask($query);

        $this->assertSame($expectedResult, $result);
    }

    #[Test]
    public function throwsNoHandlerForQueryOnNoHandler(): void
    {
        $query = new TestIntegrationQuery();

        $messageBus = $this->createStub(MessageBusInterface::class);
        $messageBus->method('dispatch')
            ->willThrowException(new NoHandlerForMessageException());

        $bus = new SymfonyQueryBus($messageBus, $this->stampTransformer);

        $this->expectException(NoHandlerForQuery::class);

        $bus->ask($query);
    }

    #[Test]
    public function unwrapsHandlerFailedException(): void
    {
        $query = new TestIntegrationQuery();
        $originalException = new \RuntimeException('Handler error');
        $handlerFailedException = new HandlerFailedException(
            new Envelope($query),
            [$originalException]
        );

        $messageBus = $this->createStub(MessageBusInterface::class);
        $messageBus->method('dispatch')
            ->willThrowException($handlerFailedException);

        $bus = new SymfonyQueryBus($messageBus, $this->stampTransformer);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Handler error');

        $bus->ask($query);
    }

    #[Test]
    public function returnsNullResult(): void
    {
        $query = new TestIntegrationQuery();

        $envelope = new Envelope($query, [
            new HandledStamp(null, 'TestHandler::__invoke'),
        ]);

        $messageBus = $this->createStub(MessageBusInterface::class);
        $messageBus->method('dispatch')
            ->willReturn($envelope);

        $bus = new SymfonyQueryBus($messageBus, $this->stampTransformer);
        $result = $bus->ask($query);

        $this->assertNull($result);
    }
}

final readonly class TestIntegrationQuery extends Query
{
}
