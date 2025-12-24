<?php

declare(strict_types=1);

namespace OpenSolid\Core\Tests\Unit\Domain\Envelop\Attribute;

use OpenSolid\Bus\Envelope\Stamp\Stamp;
use OpenSolid\Core\Domain\Envelop\Attribute\Envelope;
use OpenSolid\Core\Domain\Envelop\Stamp\TransportStamp;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class EnvelopeTest extends TestCase
{
    #[Test]
    public function canBeUsedAsAttribute(): void
    {
        $reflection = new \ReflectionClass(Envelope::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        $this->assertCount(1, $attributes);
        $attribute = $attributes[0]->newInstance();
        $this->assertSame(\Attribute::TARGET_CLASS, $attribute->flags);
    }

    #[Test]
    public function acceptsArrayOfStamps(): void
    {
        $stamp1 = new TransportStamp('async');
        $stamp2 = new TestStamp();

        $envelope = new Envelope([$stamp1, $stamp2]);

        $this->assertArrayHasKey(TransportStamp::class, $envelope->stamps);
        $this->assertArrayHasKey(TestStamp::class, $envelope->stamps);
        $this->assertSame([$stamp1], $envelope->stamps[TransportStamp::class]);
        $this->assertSame([$stamp2], $envelope->stamps[TestStamp::class]);
    }

    #[Test]
    public function groupsMultipleStampsOfSameClass(): void
    {
        $stamp1 = new TransportStamp('async');
        $stamp2 = new TransportStamp('sync');

        $envelope = new Envelope([$stamp1, $stamp2]);

        $this->assertCount(1, $envelope->stamps);
        $this->assertCount(2, $envelope->stamps[TransportStamp::class]);
        $this->assertSame($stamp1, $envelope->stamps[TransportStamp::class][0]);
        $this->assertSame($stamp2, $envelope->stamps[TransportStamp::class][1]);
    }

    #[Test]
    public function canBeRetrievedFromClassAttribute(): void
    {
        $reflection = new \ReflectionClass(TestCommandWithEnvelope::class);
        $attributes = $reflection->getAttributes(Envelope::class);

        $this->assertCount(1, $attributes);

        /** @var Envelope $envelope */
        $envelope = $attributes[0]->newInstance();

        $this->assertArrayHasKey(TransportStamp::class, $envelope->stamps);
    }
}

final readonly class TestStamp extends Stamp
{
}

#[Envelope([new TransportStamp('async')])]
final class TestCommandWithEnvelope
{
}
