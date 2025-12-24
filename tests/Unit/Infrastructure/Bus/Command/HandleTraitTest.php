<?php

declare(strict_types=1);

namespace OpenSolid\Core\Tests\Unit\Infrastructure\Bus\Command;

use OpenSolid\Core\Infrastructure\Bus\Command\HandleTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\LogicException;
use Symfony\Component\Messenger\Exception\NoHandlerForMessageException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;

class HandleTraitTest extends TestCase
{
    #[Test]
    public function throwsLogicExceptionWhenMessageBusNotInitialized(): void
    {
        $handler = new TestHandlerWithoutBus();

        try {
            $handler->handleMessage(new \stdClass());
            $this->fail('Expected LogicException to be thrown');
        } catch (LogicException $e) {
            $this->assertStringContainsString('You must provide a', $e->getMessage());
            $this->assertStringContainsString('$messageBus', $e->getMessage());
            $this->assertStringContainsString('property', $e->getMessage());
        }
    }

    #[Test]
    public function returnsResultFromSingleHandler(): void
    {
        $message = new \stdClass();
        $expectedResult = 'handler-result';

        $envelope = new Envelope($message, [
            new HandledStamp($expectedResult, 'TestHandler::__invoke'),
        ]);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->once())
            ->method('dispatch')
            ->with($message)
            ->willReturn($envelope);

        $handler = new TestHandler($messageBus);
        $result = $handler->handleMessage($message);

        $this->assertSame($expectedResult, $result);
    }

    #[Test]
    public function throwsLogicExceptionForMultipleHandlers(): void
    {
        $message = new \stdClass();

        $envelope = new Envelope($message, [
            new HandledStamp('result1', 'Handler1::__invoke'),
            new HandledStamp('result2', 'Handler2::__invoke'),
        ]);

        $messageBus = $this->createStub(MessageBusInterface::class);
        $messageBus->method('dispatch')
            ->willReturn($envelope);

        $handler = new TestHandler($messageBus);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/was handled multiple times/');
        $this->expectExceptionMessageMatches('/got 2/');

        $handler->handleMessage($message);
    }

    #[Test]
    public function throwsNoHandlerExceptionWhenZeroHandlers(): void
    {
        $message = new \stdClass();
        $envelope = new Envelope($message);

        $messageBus = $this->createStub(MessageBusInterface::class);
        $messageBus->method('dispatch')
            ->willReturn($envelope);

        $handler = new TestHandler($messageBus);

        $this->expectException(NoHandlerForMessageException::class);
        $this->expectExceptionMessageMatches('/was handled zero times/');

        $handler->handleMessage($message);
    }

    #[Test]
    public function returnsNullForAsyncHandlingWithSentStamp(): void
    {
        $message = new \stdClass();
        $envelope = new Envelope($message, [
            new SentStamp('async', 'AsyncSender'),
        ]);

        $messageBus = $this->createStub(MessageBusInterface::class);
        $messageBus->method('dispatch')
            ->willReturn($envelope);

        $handler = new TestHandlerWithAsyncAllowed($messageBus);
        $result = $handler->handleMessage($message);

        $this->assertNull($result);
    }

    #[Test]
    public function throwsExceptionForAsyncHandlingWhenNotAllowed(): void
    {
        $message = new \stdClass();
        $envelope = new Envelope($message, [
            new SentStamp('async', 'AsyncSender'),
        ]);

        $messageBus = $this->createStub(MessageBusInterface::class);
        $messageBus->method('dispatch')
            ->willReturn($envelope);

        $handler = new TestHandler($messageBus);

        $this->expectException(NoHandlerForMessageException::class);

        $handler->handleMessage($message);
    }

    #[Test]
    public function handlerResultCanBeNull(): void
    {
        $message = new \stdClass();
        $envelope = new Envelope($message, [
            new HandledStamp(null, 'TestHandler::__invoke'),
        ]);

        $messageBus = $this->createStub(MessageBusInterface::class);
        $messageBus->method('dispatch')
            ->willReturn($envelope);

        $handler = new TestHandler($messageBus);
        $result = $handler->handleMessage($message);

        $this->assertNull($result);
    }

    #[Test]
    public function handlerResultCanBeArray(): void
    {
        $message = new \stdClass();
        $expectedResult = ['key' => 'value', 'items' => [1, 2, 3]];
        $envelope = new Envelope($message, [
            new HandledStamp($expectedResult, 'TestHandler::__invoke'),
        ]);

        $messageBus = $this->createStub(MessageBusInterface::class);
        $messageBus->method('dispatch')
            ->willReturn($envelope);

        $handler = new TestHandler($messageBus);
        $result = $handler->handleMessage($message);

        $this->assertSame($expectedResult, $result);
    }
}

class TestHandler
{
    use HandleTrait;

    public function __construct(MessageBusInterface $messageBus)
    {
        $this->messageBus = $messageBus;
    }

    public function handleMessage(object $message): mixed
    {
        return $this->handle($message);
    }
}

class TestHandlerWithAsyncAllowed
{
    use HandleTrait;

    public function __construct(MessageBusInterface $messageBus)
    {
        $this->messageBus = $messageBus;
        $this->allowAsyncHandling = true;
    }

    public function handleMessage(object $message): mixed
    {
        return $this->handle($message);
    }
}

class TestHandlerWithoutBus
{
    use HandleTrait;

    public function handleMessage(object $message): mixed
    {
        return $this->handle($message);
    }
}
