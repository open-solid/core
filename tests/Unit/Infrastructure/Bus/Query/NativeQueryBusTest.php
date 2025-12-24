<?php

declare(strict_types=1);

namespace OpenSolid\Core\Tests\Unit\Infrastructure\Bus\Query;

use OpenSolid\Bus\Error\NoHandlerForMessage;
use OpenSolid\Bus\MessageBus;
use OpenSolid\Core\Application\Query\Message\Query;
use OpenSolid\Core\Infrastructure\Bus\Query\Error\NoHandlerForQuery;
use OpenSolid\Core\Infrastructure\Bus\Query\NativeQueryBus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class NativeQueryBusTest extends TestCase
{
    #[Test]
    public function asksQuerySuccessfully(): void
    {
        $query = new TestQuery();
        $expectedResult = ['data' => 'result'];

        $messageBus = $this->createMock(MessageBus::class);
        $messageBus->expects($this->once())
            ->method('dispatch')
            ->with($query)
            ->willReturn($expectedResult);

        $bus = new NativeQueryBus($messageBus);
        $result = $bus->ask($query);

        $this->assertSame($expectedResult, $result);
    }

    #[Test]
    public function asksQueryWithObjectResult(): void
    {
        $query = new TestQuery();
        $expectedResult = new \stdClass();
        $expectedResult->name = 'test';

        $messageBus = $this->createMock(MessageBus::class);
        $messageBus->expects($this->once())
            ->method('dispatch')
            ->with($query)
            ->willReturn($expectedResult);

        $bus = new NativeQueryBus($messageBus);
        $result = $bus->ask($query);

        $this->assertSame($expectedResult, $result);
    }

    #[Test]
    public function translatesNoHandlerForMessageToNoHandlerForQuery(): void
    {
        $query = new TestQuery();

        $messageBus = $this->createMock(MessageBus::class);
        $messageBus->expects($this->once())
            ->method('dispatch')
            ->with($query)
            ->willThrowException(new NoHandlerForMessage('No handler'));

        $bus = new NativeQueryBus($messageBus);

        $this->expectException(NoHandlerForQuery::class);
        $this->expectExceptionMessage(sprintf('No handler for query of type "%s".', TestQuery::class));

        $bus->ask($query);
    }

    #[Test]
    public function noHandlerForQueryPreservesPreviousException(): void
    {
        $query = new TestQuery();
        $originalException = new NoHandlerForMessage('No handler');

        $messageBus = $this->createStub(MessageBus::class);
        $messageBus->method('dispatch')
            ->willThrowException($originalException);

        $bus = new NativeQueryBus($messageBus);

        try {
            $bus->ask($query);
            $this->fail('Expected NoHandlerForQuery exception');
        } catch (NoHandlerForQuery $e) {
            $this->assertSame($originalException, $e->getPrevious());
        }
    }

    #[Test]
    public function propagatesOtherExceptions(): void
    {
        $query = new TestQuery();
        $originalException = new \RuntimeException('Unexpected error');

        $messageBus = $this->createStub(MessageBus::class);
        $messageBus->method('dispatch')
            ->willThrowException($originalException);

        $bus = new NativeQueryBus($messageBus);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unexpected error');

        $bus->ask($query);
    }

    #[Test]
    public function asksQueryWithNullResult(): void
    {
        $query = new TestQuery();

        $messageBus = $this->createMock(MessageBus::class);
        $messageBus->expects($this->once())
            ->method('dispatch')
            ->with($query)
            ->willReturn(null);

        $bus = new NativeQueryBus($messageBus);
        $result = $bus->ask($query);

        $this->assertNull($result);
    }
}

final readonly class TestQuery extends Query
{
}
