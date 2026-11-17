<?php

declare(strict_types=1);

namespace OpenSolid\Core\Tests\Integration\Symfony\DependencyInjection;

use OpenSolid\Core\Application\Command\Bus\CommandBus;
use OpenSolid\Core\Application\Command\Handler\Attribute\AsCommandHandler;
use OpenSolid\Core\Application\Command\Message\Command;
use OpenSolid\Core\Application\Query\Bus\QueryBus;
use OpenSolid\Core\Application\Query\Handler\Attribute\AsQueryHandler;
use OpenSolid\Core\Application\Query\Message\Query;
use OpenSolid\Core\CoreBundle;
use OpenSolid\Core\Domain\Event\Message\DomainEvent;
use OpenSolid\Core\Infrastructure\Bus\Event\NativeEventBus;
use OpenSolid\Core\Infrastructure\Bus\Event\Subscriber\Attribute\AsDomainEventSubscriber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\Messenger\DependencyInjection\MessengerPass;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Stamp\HandledStamp;

class MessageHandlerConfiguratorTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function strategies(): iterable
    {
        yield 'native' => ['native'];
        yield 'symfony' => ['symfony'];
    }

    #[Test]
    #[DataProvider('strategies')]
    public function unionTypedSubscriberReceivesEveryEventOfItsUnion(string $strategy): void
    {
        $container = $this->buildContainer($strategy, [UnionSubscriber::class, SingleSubscriber::class]);

        $this->publish($container, $strategy, new ListingPublished('l-1'));
        $this->publish($container, $strategy, new ListingSold('l-1'));
        $this->publish($container, $strategy, new ListingExpired('l-1'));

        $this->assertSame(
            [ListingPublished::class, ListingSold::class, ListingExpired::class],
            $this->service($container, UnionSubscriber::class, UnionSubscriber::class)->received,
        );
        $this->assertSame([ListingSold::class], $this->service($container, SingleSubscriber::class, SingleSubscriber::class)->received);
    }

    #[Test]
    #[DataProvider('strategies')]
    public function unionTypedCommandAndQueryHandlersHandleEveryMessageOfTheirUnion(string $strategy): void
    {
        $container = $this->buildContainer($strategy, [UnionCommandHandler::class, UnionQueryHandler::class]);

        $this->assertSame(PublishListing::class, $this->execute($container, $strategy, new PublishListing()));
        $this->assertSame(SellListing::class, $this->execute($container, $strategy, new SellListing()));
        $this->assertSame(FindListing::class, $this->execute($container, $strategy, new FindListing()));
        $this->assertSame(FindListings::class, $this->execute($container, $strategy, new FindListings()));
    }

    #[Test]
    public function singleTypedHandlersAreTaggedAsBefore(): void
    {
        $native = $this->buildContainer('native', [SingleSubscriber::class]);
        $this->assertSame(
            [['class' => ListingSold::class]],
            $native->getDefinition(SingleSubscriber::class)->getTag('domain.event.subscriber'),
        );

        $symfony = $this->buildContainer('symfony', [SingleSubscriber::class]);
        $this->assertSame(
            [['bus' => 'event.bus', 'handles' => ListingSold::class]],
            $symfony->getDefinition(SingleSubscriber::class)->getTag('messenger.message_handler'),
        );
    }

    #[Test]
    #[DataProvider('strategies')]
    public function builtinTypeInUnionFailsTheContainerBuild(string $strategy): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(\sprintf('Invalid type "string" in the union type of parameter "$event" of "%s::__invoke()"', BuiltinUnionSubscriber::class));

        $this->buildContainer($strategy, [BuiltinUnionSubscriber::class]);
    }

    /**
     * @param list<class-string> $handlers
     */
    private function buildContainer(string $strategy, array $handlers): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', sys_get_temp_dir());

        $bundle = new CoreBundle();
        $extension = $bundle->getContainerExtension();
        $this->assertNotNull($extension);
        $container->registerExtension($extension);
        $container->loadFromExtension('opensolid', ['bus' => ['strategy' => $strategy]]);
        $bundle->build($container);

        $container->register('logger', NullLogger::class);

        if ('symfony' === $strategy) {
            $container->register('messenger.middleware.handle_message', HandleMessageMiddleware::class)
                ->setAbstract(true)
                ->setArguments([null]);

            foreach (['command.bus', 'query.bus', 'event.bus'] as $busId) {
                $container->register($busId, MessageBus::class)
                    ->setArguments([[]])
                    ->addTag('messenger.bus')
                    ->setPublic(true);
                $container->setParameter($busId.'.middleware', [['id' => 'handle_message']]);
            }

            $container->addCompilerPass(new MessengerPass());
        } else {
            // The transaction middleware needs a Doctrine registry, which is irrelevant to handler registration.
            $container->addCompilerPass(new class () implements CompilerPassInterface {
                public function process(ContainerBuilder $container): void
                {
                    $container->removeDefinition('cqs.command.middleware.doctrine_transaction');
                }
            });

            foreach (['cqs.command.bus', 'cqs.query.bus', 'domain.event.bus'] as $busId) {
                $container->setAlias('test.'.$busId, $busId)->setPublic(true);
            }
        }

        foreach ($handlers as $handler) {
            $container->register($handler, $handler)
                ->setAutoconfigured(true)
                ->setPublic(true);
        }

        $container->compile();

        return $container;
    }

    private function publish(ContainerBuilder $container, string $strategy, DomainEvent $event): void
    {
        if ('native' === $strategy) {
            $bus = $this->service($container, 'test.domain.event.bus', NativeEventBus::class);
            $bus->publish($event);
            $bus->flush();

            return;
        }

        // The worker of the async transport hands the received envelope to this same bus.
        $this->service($container, 'event.bus', MessageBusInterface::class)->dispatch(new Envelope($event));
    }

    private function execute(ContainerBuilder $container, string $strategy, Command|Query $message): mixed
    {
        if ('native' === $strategy) {
            return $message instanceof Command
                ? $this->service($container, 'test.cqs.command.bus', CommandBus::class)->execute($message)
                : $this->service($container, 'test.cqs.query.bus', QueryBus::class)->ask($message);
        }

        $busId = $message instanceof Command ? 'command.bus' : 'query.bus';
        $handled = $this->service($container, $busId, MessageBusInterface::class)->dispatch($message)->all(HandledStamp::class);
        $this->assertCount(1, $handled);
        $this->assertInstanceOf(HandledStamp::class, $handled[0]);

        return $handled[0]->getResult();
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(ContainerBuilder $container, string $id, string $class): object
    {
        $service = $container->get($id);

        if (!$service instanceof $class) {
            throw new \LogicException(\sprintf('Service "%s" is not an instance of "%s".', $id, $class));
        }

        return $service;
    }
}

final readonly class ListingPublished extends DomainEvent
{
}

final readonly class ListingSold extends DomainEvent
{
}

final readonly class ListingExpired extends DomainEvent
{
}

#[AsDomainEventSubscriber]
final class UnionSubscriber
{
    /** @var list<class-string> */
    public array $received = [];

    public function __invoke(ListingPublished|ListingSold|ListingExpired $event): void
    {
        $this->received[] = $event::class;
    }
}

#[AsDomainEventSubscriber]
final class SingleSubscriber
{
    /** @var list<class-string> */
    public array $received = [];

    public function __invoke(ListingSold $event): void
    {
        $this->received[] = $event::class;
    }
}

#[AsDomainEventSubscriber]
final class BuiltinUnionSubscriber
{
    public function __invoke(ListingSold|string $event): void
    {
    }
}

final readonly class PublishListing extends Command
{
}

final readonly class SellListing extends Command
{
}

#[AsCommandHandler]
final class UnionCommandHandler
{
    public function __invoke(PublishListing|SellListing $command): string
    {
        return $command::class;
    }
}

final readonly class FindListing extends Query
{
}

final readonly class FindListings extends Query
{
}

#[AsQueryHandler]
final class UnionQueryHandler
{
    public function __invoke(FindListing|FindListings $query): string
    {
        return $query::class;
    }
}
