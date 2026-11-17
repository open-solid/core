<?php

declare(strict_types=1);

namespace OpenSolid\Core\Tests\Unit\Infrastructure\Bus\Event\Serializer;

use OpenSolid\Core\Domain\Event\Message\DomainEvent;
use OpenSolid\Core\Infrastructure\Bus\Event\Serializer\DomainEventNormalizer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Serializer;

class DomainEventNormalizerTest extends TestCase
{
    private function createNormalizer(?NormalizerInterface $inner = null, ?DenormalizerInterface $innerDe = null): DomainEventNormalizer
    {
        $normalizer = new DomainEventNormalizer();
        $normalizer->setNormalizer($inner ?? $this->createStub(NormalizerInterface::class));
        $normalizer->setDenormalizer($innerDe ?? $this->createStub(DenormalizerInterface::class));

        return $normalizer;
    }

    #[Test]
    public function normalizesScalarAndObjectProperties(): void
    {
        $event = new SimpleEvent('aggregate-1');

        $innerNormalizer = $this->createMock(NormalizerInterface::class);
        $innerNormalizer->expects($this->once())
            ->method('normalize')
            ->with($event->occurredOn, 'json', ['ctx' => true, DateTimeNormalizer::FORMAT_KEY => 'Y-m-d\TH:i:s.uP'])
            ->willReturn('2024-01-01T00:00:00.000000+00:00');

        $normalized = $this->createNormalizer($innerNormalizer)->normalize($event, 'json', ['ctx' => true]);

        $this->assertSame($event->id, $normalized['id']);
        $this->assertSame('aggregate-1', $normalized['aggregateId']);
        $this->assertSame('2024-01-01T00:00:00.000000+00:00', $normalized['occurredOn']);
    }

    #[Test]
    public function skipsUninitializedProperties(): void
    {
        $event = $this->eventWithUninitializedOptional();

        $innerNormalizer = $this->createStub(NormalizerInterface::class);
        $innerNormalizer->method('normalize')->willReturn('normalized');

        $normalized = $this->createNormalizer($innerNormalizer)->normalize($event);

        $this->assertArrayNotHasKey('optional', $normalized);
        $this->assertArrayHasKey('id', $normalized);
        $this->assertArrayHasKey('aggregateId', $normalized);
    }

    #[Test]
    public function denormalizesScalarObjectUnionAndNullProperties(): void
    {
        $when = new \DateTimeImmutable('2024-01-01T00:00:00+00:00');

        $innerDenormalizer = $this->createMock(DenormalizerInterface::class);
        $innerDenormalizer->expects($this->exactly(2))
            ->method('denormalize')
            ->willReturnCallback(function (mixed $value, string $type) use ($when): \DateTimeImmutable {
                $this->assertSame(\DateTimeImmutable::class, $type);

                return $when;
            });

        $data = [
            'id' => 'event-id',
            'aggregateId' => 'aggregate-1',
            'occurredOn' => '2024-01-01T00:00:00+00:00',
            'union' => 42,
            'nullable' => null,
            'when' => '2024-01-01T00:00:00+00:00',
        ];

        $event = $this->createNormalizer(innerDe: $innerDenormalizer)->denormalize($data, ComplexEvent::class);

        $this->assertInstanceOf(ComplexEvent::class, $event);
        $this->assertSame('event-id', $event->id);
        $this->assertSame('aggregate-1', $event->aggregateId);
        $this->assertSame($when, $event->occurredOn);
        $this->assertSame(42, $event->union);
        $this->assertNull($event->nullable);
        $this->assertSame($when, $event->when);
    }

    #[Test]
    public function denormalizeSkipsPropertiesMissingFromData(): void
    {
        $innerDenormalizer = $this->createMock(DenormalizerInterface::class);
        $innerDenormalizer->expects($this->never())->method('denormalize');

        $event = $this->createNormalizer(innerDe: $innerDenormalizer)->denormalize(['aggregateId' => 'aggregate-1'], ComplexEvent::class);

        $this->assertInstanceOf(ComplexEvent::class, $event);
        $this->assertSame('aggregate-1', $event->aggregateId);
        $this->assertFalse(new \ReflectionProperty(ComplexEvent::class, 'id')->isInitialized($event));
    }

    #[Test]
    public function denormalizeReturnsBareInstanceWhenDataIsNotArray(): void
    {
        $innerDenormalizer = $this->createMock(DenormalizerInterface::class);
        $innerDenormalizer->expects($this->never())->method('denormalize');

        $event = $this->createNormalizer(innerDe: $innerDenormalizer)->denormalize('not-an-array', ComplexEvent::class);

        $this->assertInstanceOf(ComplexEvent::class, $event);
        $this->assertFalse(new \ReflectionProperty(ComplexEvent::class, 'aggregateId')->isInitialized($event));
    }

    #[Test]
    public function denormalizeRejectsTypesThatAreNotDomainEvents(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('Cannot denormalize "%s"', \stdClass::class));

        $this->createNormalizer()->denormalize([], \stdClass::class);
    }

    #[Test]
    public function supportsNormalizationOnlyForDomainEvents(): void
    {
        $normalizer = $this->createNormalizer();

        $this->assertTrue($normalizer->supportsNormalization(new SimpleEvent('aggregate-1')));
        $this->assertFalse($normalizer->supportsNormalization(new \stdClass()));
        $this->assertFalse($normalizer->supportsNormalization('string'));
    }

    #[Test]
    public function supportsDenormalizationForDomainEventAndSubclasses(): void
    {
        $normalizer = $this->createNormalizer();

        $this->assertTrue($normalizer->supportsDenormalization([], DomainEvent::class));
        $this->assertTrue($normalizer->supportsDenormalization([], SimpleEvent::class));
        $this->assertFalse($normalizer->supportsDenormalization([], \stdClass::class));
        $this->assertFalse($normalizer->supportsDenormalization([], 'string'));
    }

    #[Test]
    public function getSupportedTypesMarksDomainEventAsCacheable(): void
    {
        $normalizer = $this->createNormalizer();

        $this->assertSame([DomainEvent::class => true], $normalizer->getSupportedTypes(null));
        $this->assertSame([DomainEvent::class => true], $normalizer->getSupportedTypes('json'));
    }

    #[Test]
    public function roundTripReconstructsAnEquivalentEvent(): void
    {
        $serializer = $this->createRealSerializer();
        $event = new RoundTripEvent('aggregate-1', 'order-placed', 42, 'note');

        $restored = $serializer->denormalize($serializer->normalize($event), RoundTripEvent::class);

        $this->assertEquals($event, $restored);
        $this->assertSame($event->id, $restored->id);
        $this->assertSame($event->aggregateId, $restored->aggregateId);
        $this->assertEquals($event->occurredOn, $restored->occurredOn);
        $this->assertSame($event->name, $restored->name);
        $this->assertSame($event->amount, $restored->amount);
        $this->assertSame($event->note, $restored->note);
    }

    #[Test]
    public function preservesSubSecondPrecisionOfOccurredOn(): void
    {
        $serializer = $this->createRealSerializer();
        $event = new RoundTripEvent('aggregate-1', 'order-placed', 42, null);

        $normalized = $serializer->normalize($event);

        // occurredOn is the last-write-wins key: microseconds must survive normalization so that
        // same-second, out-of-order events still order deterministically.
        $this->assertIsArray($normalized);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}/', $normalized['occurredOn']);

        $restored = $serializer->denormalize($normalized, RoundTripEvent::class);
        $this->assertSame(
            $event->occurredOn->format('Y-m-d\TH:i:s.u'),
            $restored->occurredOn->format('Y-m-d\TH:i:s.u'),
        );
    }

    #[Test]
    public function normalizationIsIdempotentAcrossRoundTrips(): void
    {
        $serializer = $this->createRealSerializer();
        $event = new RoundTripEvent('aggregate-1', 'order-placed', 42, null);

        $normalized = $serializer->normalize($event);
        $reNormalized = $serializer->normalize($serializer->denormalize($normalized, RoundTripEvent::class));

        $this->assertSame($normalized, $reNormalized);
    }

    #[Test]
    public function repeatedRoundTripsAreStable(): void
    {
        $serializer = $this->createRealSerializer();
        $event = new RoundTripEvent('aggregate-1', 'order-placed', 42, 'note');

        $firstPass = $serializer->denormalize($serializer->normalize($event), RoundTripEvent::class);
        $secondPass = $serializer->denormalize($serializer->normalize($firstPass), RoundTripEvent::class);

        $this->assertEquals($firstPass, $secondPass);
    }

    #[Test]
    public function roundTripPreservesUninitializedProperties(): void
    {
        $serializer = $this->createRealSerializer();
        $event = $this->eventWithUninitializedOptional();

        $restored = $serializer->denormalize($serializer->normalize($event), EventWithOptionalProperty::class);

        $reflection = new \ReflectionProperty(EventWithOptionalProperty::class, 'optional');
        $this->assertFalse($reflection->isInitialized($restored));
        $this->assertSame($event->id, $restored->id);
        $this->assertSame($event->aggregateId, $restored->aggregateId);
    }

    /**
     * Builds an event whose base fields are hydrated but whose "optional" property is deliberately
     * left uninitialized, mirroring how the denormalizer instantiates events without the constructor.
     */
    private function eventWithUninitializedOptional(): EventWithOptionalProperty
    {
        $event = new \ReflectionClass(EventWithOptionalProperty::class)->newInstanceWithoutConstructor();
        $class = new \ReflectionClass($event);
        $class->getProperty('id')->setValue($event, 'event-id');
        $class->getProperty('aggregateId')->setValue($event, 'aggregate-1');
        $class->getProperty('occurredOn')->setValue($event, new \DateTimeImmutable('2024-01-01T00:00:00.000000+00:00'));

        return $event;
    }

    #[Test]
    public function cachesPropertyPlanPerClassWithPrecomputedTypes(): void
    {
        $normalizer = $this->createNormalizer();

        $normalizer->normalize(new SimpleEvent('aggregate-1'));
        $normalizer->denormalize([], ComplexEvent::class);

        $cache = $this->readPropertyPlanCache($normalizer);
        $this->assertIsArray($cache);
        $this->assertArrayHasKey(SimpleEvent::class, $cache);
        $this->assertArrayHasKey(ComplexEvent::class, $cache);

        // The plan precomputes the denormalization target type per property once, up front.
        $types = array_column($cache[SimpleEvent::class], 'type', 'name');
        $this->assertSame(\DateTimeImmutable::class, $types['occurredOn']);
        $this->assertNull($types['id']);
        $this->assertNull($types['aggregateId']);
    }

    #[Test]
    public function reusesTheSamePropertyPlanAcrossCalls(): void
    {
        $normalizer = $this->createNormalizer();

        $normalizer->normalize(new SimpleEvent('aggregate-1'));
        $firstCache = $this->readPropertyPlanCache($normalizer);
        $this->assertIsArray($firstCache);

        // A second call for the same class (via the other direction) must not rebuild the plan.
        $normalizer->denormalize([], SimpleEvent::class);
        $secondCache = $this->readPropertyPlanCache($normalizer);
        $this->assertIsArray($secondCache);

        $this->assertSame($firstCache[SimpleEvent::class], $secondCache[SimpleEvent::class]);
    }

    private function readPropertyPlanCache(DomainEventNormalizer $normalizer): mixed
    {
        return new \ReflectionProperty(DomainEventNormalizer::class, 'propertyPlanCache')->getValue($normalizer);
    }

    private function createRealSerializer(): Serializer
    {
        // A plain DateTimeNormalizer (RFC3339 default) proves DomainEventNormalizer itself injects
        // the microsecond format that keeps occurredOn lossless across the round trip.
        return new Serializer([
            new DomainEventNormalizer(),
            new DateTimeNormalizer(),
        ]);
    }
}

final readonly class SimpleEvent extends DomainEvent
{
}

final readonly class EventWithOptionalProperty extends DomainEvent
{
    public function __construct(string $aggregateId, public string $optional)
    {
        parent::__construct($aggregateId);
    }
}

final readonly class ComplexEvent extends DomainEvent
{
    public function __construct(
        string $aggregateId,
        public int|string $union,
        public ?string $nullable,
        public \DateTimeImmutable $when,
    ) {
        parent::__construct($aggregateId);
    }
}

final readonly class RoundTripEvent extends DomainEvent
{
    public function __construct(
        string $aggregateId,
        public string $name,
        public int $amount,
        public ?string $note,
    ) {
        parent::__construct($aggregateId);
    }
}
