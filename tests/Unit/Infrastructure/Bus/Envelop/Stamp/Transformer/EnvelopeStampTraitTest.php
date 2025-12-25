<?php

declare(strict_types=1);

namespace OpenSolid\Core\Tests\Unit\Infrastructure\Bus\Envelop\Stamp\Transformer;

use OpenSolid\Core\Domain\Envelop\Stamp\TransportStamp;
use OpenSolid\Core\Infrastructure\Bus\Envelop\Stamp\Transformer\EnvelopeStampTrait;
use OpenSolid\Core\Infrastructure\Bus\Envelop\Stamp\Transformer\StampTransformer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

class EnvelopeStampTraitTest extends TestCase
{
    #[Test]
    public function createsSimpleEnvelopeForMessageWithoutAttribute(): void
    {
        $message = new MessageWithoutEnvelope();
        $transformer = $this->createMock(StampTransformer::class);
        $transformer->expects($this->never())->method('transform');

        $handler = new TestEnvelopeHandler($transformer);
        $envelope = $handler->createEnvelopePublic($message);

        $this->assertSame($message, $envelope->getMessage());
        $this->assertEmpty($envelope->all());
    }

    #[Test]
    public function createsEnvelopeWithStampsFromAttribute(): void
    {
        $message = new MessageWithEnvelope();
        $transformedStamp = new TransportNamesStamp(['async']);

        $transformer = $this->createMock(StampTransformer::class);
        $transformer->expects($this->once())
            ->method('transform')
            ->willReturn($transformedStamp);

        $handler = new TestEnvelopeHandler($transformer);
        $envelope = $handler->createEnvelopePublic($message);

        $this->assertSame($message, $envelope->getMessage());
        $stamps = $envelope->all(TransportNamesStamp::class);
        $this->assertCount(1, $stamps);
    }

    #[Test]
    public function createsEnvelopeWithMultipleStamps(): void
    {
        $message = new MessageWithMultipleStamps();
        $transformedStamp1 = new TransportNamesStamp(['async']);
        $transformedStamp2 = new TransportNamesStamp(['sync']);

        $transformer = $this->createMock(StampTransformer::class);
        $transformer->expects($this->exactly(2))
            ->method('transform')
            ->willReturnOnConsecutiveCalls($transformedStamp1, $transformedStamp2);

        $handler = new TestEnvelopeHandler($transformer);
        $envelope = $handler->createEnvelopePublic($message);

        $stamps = $envelope->all(TransportNamesStamp::class);
        $this->assertCount(2, $stamps);
    }

    #[Test]
    public function wrapsExistingEnvelopeWithMessage(): void
    {
        $message = new MessageWithoutEnvelope();
        $transformer = $this->createStub(StampTransformer::class);

        $handler = new TestEnvelopeHandler($transformer);
        $envelope = $handler->createEnvelopePublic($message);

        $this->assertSame($message, $envelope->getMessage());
    }
}

class TestEnvelopeHandler
{
    use EnvelopeStampTrait;

    public function __construct(StampTransformer $stampTransformer)
    {
        $this->stampTransformer = $stampTransformer;
    }

    public function createEnvelopePublic(object $message): Envelope
    {
        return $this->createEnvelope($message);
    }
}

class MessageWithoutEnvelope
{
}

#[TransportStamp('async')]
class MessageWithEnvelope
{
}

#[TransportStamp('async')]
#[TransportStamp('sync')]
class MessageWithMultipleStamps
{
}
