<?php

declare(strict_types=1);

namespace OpenSolid\Core\Tests\Unit\Infrastructure\Bus\Envelop\Stamp\Transformer;

use OpenSolid\Bus\Envelope\Stamp\Stamp;
use OpenSolid\Core\Domain\Envelop\Stamp\TransportStamp;
use OpenSolid\Core\Infrastructure\Bus\Envelop\Stamp\Transformer\DefaultStampTransformer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

class DefaultStampTransformerTest extends TestCase
{
    #[Test]
    public function supportsTransportStamp(): void
    {
        $transformer = new DefaultStampTransformer();
        $stamp = new TransportStamp('async');

        $this->assertTrue($transformer->supports($stamp));
    }

    #[Test]
    public function doesNotSupportOtherStamps(): void
    {
        $transformer = new DefaultStampTransformer();
        $stamp = new UnsupportedStamp();

        $this->assertFalse($transformer->supports($stamp));
    }

    #[Test]
    public function transformsTransportStampToTransportNamesStamp(): void
    {
        $transformer = new DefaultStampTransformer();
        $stamp = new TransportStamp('async');

        $result = $transformer->transform($stamp);

        $this->assertInstanceOf(TransportNamesStamp::class, $result);
    }

    #[Test]
    public function transformsTransportStampWithSingleName(): void
    {
        $transformer = new DefaultStampTransformer();
        $stamp = new TransportStamp('async');

        /** @var TransportNamesStamp $result */
        $result = $transformer->transform($stamp);

        $this->assertSame(['async'], $result->getTransportNames());
    }

    #[Test]
    public function transformsTransportStampWithMultipleNames(): void
    {
        $transformer = new DefaultStampTransformer();
        $stamp = new TransportStamp(['async', 'priority', 'batch']);

        /** @var TransportNamesStamp $result */
        $result = $transformer->transform($stamp);

        $this->assertSame(['async', 'priority', 'batch'], $result->getTransportNames());
    }

    #[Test]
    public function throwsLogicExceptionForUnsupportedStamp(): void
    {
        $transformer = new DefaultStampTransformer();
        $stamp = new UnsupportedStamp();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf(
            'Stamp transformer "%s" does not support "%s".',
            DefaultStampTransformer::class,
            UnsupportedStamp::class
        ));

        $transformer->transform($stamp);
    }
}

final readonly class UnsupportedStamp extends Stamp
{
}
