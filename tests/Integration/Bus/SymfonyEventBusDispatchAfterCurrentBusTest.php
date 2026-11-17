<?php

declare(strict_types=1);

namespace OpenSolid\Core\Tests\Integration\Bus;

use OpenSolid\Core\Domain\Event\Message\DomainEvent;
use OpenSolid\Core\Infrastructure\Bus\Envelop\Stamp\Transformer\ChainStampTransformer;
use OpenSolid\Core\Infrastructure\Bus\Envelop\Stamp\Transformer\DefaultStampTransformer;
use OpenSolid\Core\Infrastructure\Bus\Event\SymfonyEventBus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\DispatchAfterCurrentBusMiddleware;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

/**
 * Contract test for the after-commit dispatch guarantee.
 *
 * Wires the production topology in-memory: a command bus and an event bus that
 * SHARE one DispatchAfterCurrentBusMiddleware instance, mirroring how Symfony
 * registers that middleware as a single shared service across every bus. A
 * "transaction" middleware sits inside the command bus (as doctrine_transaction
 * does in the real command.bus), and a recording middleware sits at the tail of
 * the event bus standing in for the async transport's send step.
 *
 * The recording middleware is only reached when the deferred flush resumes the
 * queued event-bus stack — after the command's root dispatch finishes. So it
 * observes exactly what a real broker would receive, and the timing the
 * sync:// / in-memory:// test transports otherwise mask.
 */
final class SymfonyEventBusDispatchAfterCurrentBusTest extends TestCase
{
    #[Test]
    public function eventIsSentOnlyAfterTheTransactionCommits(): void
    {
        $recorder = new EventBusRecorder();
        $dispatchAfterCurrentBus = new DispatchAfterCurrentBusMiddleware();

        $eventBus = new SymfonyEventBus(
            new MessageBus([$dispatchAfterCurrentBus, $this->recordingMiddleware($recorder)]),
            new ChainStampTransformer([new DefaultStampTransformer()]),
        );

        $commandBus = new MessageBus([
            $dispatchAfterCurrentBus,
            $this->transactionMiddleware($recorder),
            $this->handlerMiddleware($recorder, $eventBus),
        ]);

        $commandBus->dispatch(new SucceedingCommand());

        self::assertSame(['handle', 'commit', 'send'], $recorder->log);
        self::assertCount(1, $recorder->sent);
    }

    #[Test]
    public function eventIsNeverSentWhenTheTransactionRollsBack(): void
    {
        $recorder = new EventBusRecorder();
        $dispatchAfterCurrentBus = new DispatchAfterCurrentBusMiddleware();

        $eventBus = new SymfonyEventBus(
            new MessageBus([$dispatchAfterCurrentBus, $this->recordingMiddleware($recorder)]),
            new ChainStampTransformer([new DefaultStampTransformer()]),
        );

        $commandBus = new MessageBus([
            $dispatchAfterCurrentBus,
            $this->transactionMiddleware($recorder),
            $this->handlerMiddleware($recorder, $eventBus),
        ]);

        $caught = null;
        try {
            $commandBus->dispatch(new FailingCommand());
        } catch (\RuntimeException $exception) {
            $caught = $exception;
        }

        self::assertNotNull($caught, 'the rolled-back command must surface its exception');
        self::assertSame('handler failed after mutating the aggregate', $caught->getMessage());
        self::assertSame(['handle', 'rollback'], $recorder->log);
        self::assertSame([], $recorder->sent, 'no event may reach the broker when the transaction rolls back');
    }

    /**
     * The nested case, and the reason a middleware reorder is not enough.
     *
     * An outer command dispatches an inner command (the sanctioned ACL pattern:
     * e.g. CreateBooking -> UsePoints), the inner command publishes a domain
     * event, then the outer command fails and rolls back. Because Doctrine nests
     * on the same connection, the inner "commit" is only a savepoint release, not
     * the real commit — so publishing at the inner boundary (what a reorder would
     * do) would leak the inner event before the outer rollback. The stamp defers
     * the send to the OUTERMOST root dispatch, so the inner event is dropped.
     */
    #[Test]
    public function innerEventIsDroppedWhenTheOuterCommandRollsBack(): void
    {
        $recorder = new EventBusRecorder();
        $commandBus = $this->buildNestedCommandBus($recorder, outerFails: true);

        $caught = null;
        try {
            $commandBus->dispatch(new NestedOuterCommand());
        } catch (\RuntimeException $exception) {
            $caught = $exception;
        }

        self::assertNotNull($caught, 'the outer command must surface its exception');
        self::assertSame('outer failed after the nested inner command', $caught->getMessage());
        self::assertNotContains('send', $recorder->log, 'the inner event must not be sent when the outer rolls back');
        self::assertSame([], $recorder->sent);
    }

    #[Test]
    public function nestedInnerEventIsSentOnlyAfterTheOuterCommandCommits(): void
    {
        $recorder = new EventBusRecorder();
        $commandBus = $this->buildNestedCommandBus($recorder, outerFails: false);

        $commandBus->dispatch(new NestedOuterCommand());

        $log = $recorder->log;
        self::assertContains('send', $log);
        self::assertSame('send', end($log), 'the inner event must be sent last — after the outer commit');
        self::assertCount(1, $recorder->sent);
        $sent = $recorder->sent[0];
        self::assertInstanceOf(DomainEvent::class, $sent);
        self::assertSame('aggregate-inner', $sent->aggregateId);
    }

    /**
     * Wires the nested topology: one command bus + event bus sharing a single
     * DispatchAfterCurrentBusMiddleware; a handler that, for the outer command,
     * dispatches the inner command on the same bus (and optionally fails after),
     * and for the inner command publishes the tracked domain event.
     */
    private function buildNestedCommandBus(EventBusRecorder $recorder, bool $outerFails): MessageBusInterface
    {
        $dispatchAfterCurrentBus = new DispatchAfterCurrentBusMiddleware();

        $eventBus = new SymfonyEventBus(
            new MessageBus([$dispatchAfterCurrentBus, $this->recordingMiddleware($recorder)]),
            new ChainStampTransformer([new DefaultStampTransformer()]),
        );

        $handler = new class ($recorder, $eventBus, $outerFails) implements MiddlewareInterface {
            public ?MessageBusInterface $commandBus = null;

            public function __construct(
                private readonly EventBusRecorder $recorder,
                private readonly SymfonyEventBus $eventBus,
                private readonly bool $outerFails,
            ) {
            }

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                $message = $envelope->getMessage();

                if ($message instanceof NestedOuterCommand) {
                    $this->commandBus?->dispatch(new NestedInnerCommand());
                    $this->recorder->log[] = 'outer-handle';

                    if ($this->outerFails) {
                        throw new \RuntimeException('outer failed after the nested inner command');
                    }

                    return $envelope;
                }

                $this->eventBus->publish(new AfterCommitTestEvent('aggregate-inner'));
                $this->recorder->log[] = 'inner-handle';

                return $envelope;
            }
        };

        $commandBus = new MessageBus([
            $dispatchAfterCurrentBus,
            $this->transactionMiddleware($recorder),
            $handler,
        ]);
        $handler->commandBus = $commandBus;

        return $commandBus;
    }

    /**
     * Stands in for the async transport's send step: only reached when the
     * deferred event is actually flushed.
     */
    private function recordingMiddleware(EventBusRecorder $recorder): MiddlewareInterface
    {
        return new class ($recorder) implements MiddlewareInterface {
            public function __construct(private readonly EventBusRecorder $recorder)
            {
            }

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                $this->recorder->log[] = 'send';
                $this->recorder->sent[] = $envelope->getMessage();

                return $envelope;
            }
        };
    }

    /**
     * Mirrors doctrine_transaction: commit when the inner stack returns, roll
     * back (and rethrow) when it throws.
     */
    private function transactionMiddleware(EventBusRecorder $recorder): MiddlewareInterface
    {
        return new class ($recorder) implements MiddlewareInterface {
            public function __construct(private readonly EventBusRecorder $recorder)
            {
            }

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                try {
                    $envelope = $stack->next()->handle($envelope, $stack);
                } catch (\Throwable $exception) {
                    $this->recorder->log[] = 'rollback';

                    throw $exception;
                }

                $this->recorder->log[] = 'commit';

                return $envelope;
            }
        };
    }

    /**
     * Stands in for a command handler: mutates the aggregate (publishes its
     * domain event) and then either succeeds or fails before commit.
     */
    private function handlerMiddleware(EventBusRecorder $recorder, SymfonyEventBus $eventBus): MiddlewareInterface
    {
        return new class ($recorder, $eventBus) implements MiddlewareInterface {
            public function __construct(
                private readonly EventBusRecorder $recorder,
                private readonly SymfonyEventBus $eventBus,
            ) {
            }

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                $this->eventBus->publish(new AfterCommitTestEvent('aggregate-1'));
                $this->recorder->log[] = 'handle';

                if ($envelope->getMessage() instanceof FailingCommand) {
                    throw new \RuntimeException('handler failed after mutating the aggregate');
                }

                return $envelope;
            }
        };
    }
}

final class EventBusRecorder
{
    /** @var list<string> */
    public array $log = [];

    /** @var list<object> */
    public array $sent = [];
}

final readonly class SucceedingCommand
{
}

final readonly class FailingCommand
{
}

final readonly class NestedOuterCommand
{
}

final readonly class NestedInnerCommand
{
}

final readonly class AfterCommitTestEvent extends DomainEvent
{
}
