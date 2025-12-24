<?php

declare(strict_types=1);

namespace OpenSolid\Core\Tests\Unit\Infrastructure\Bus\Envelop\Stamp\Transformer;

use OpenSolid\Bus\Envelope\Stamp\Stamp;
use OpenSolid\Core\Domain\Envelop\Stamp\TransportStamp;
use OpenSolid\Core\Infrastructure\Bus\Envelop\Stamp\Transformer\ChainStampTransformer;
use OpenSolid\Core\Infrastructure\Bus\Envelop\Stamp\Transformer\StampTransformer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

class ChainStampTransformerTest extends TestCase
{
    #[Test]
    public function supportsReturnsTrueWhenAnyTransformerSupports(): void
    {
        $stamp = new TransportStamp('async');

        $transformer1 = $this->createStub(StampTransformer::class);
        $transformer1->method('supports')->willReturn(false);

        $transformer2 = $this->createStub(StampTransformer::class);
        $transformer2->method('supports')->willReturn(true);

        $chain = new ChainStampTransformer([$transformer1, $transformer2]);

        $this->assertTrue($chain->supports($stamp));
    }

    #[Test]
    public function supportsReturnsFalseWhenNoTransformerSupports(): void
    {
        $stamp = new TransportStamp('async');

        $transformer1 = $this->createStub(StampTransformer::class);
        $transformer1->method('supports')->willReturn(false);

        $transformer2 = $this->createStub(StampTransformer::class);
        $transformer2->method('supports')->willReturn(false);

        $chain = new ChainStampTransformer([$transformer1, $transformer2]);

        $this->assertFalse($chain->supports($stamp));
    }

    #[Test]
    public function supportsReturnsFalseWithEmptyTransformers(): void
    {
        $stamp = new TransportStamp('async');
        $chain = new ChainStampTransformer([]);

        $this->assertFalse($chain->supports($stamp));
    }

    #[Test]
    public function transformDelegatesToFirstSupportingTransformer(): void
    {
        $stamp = new TransportStamp('async');
        $expectedResult = new TransportNamesStamp(['async']);

        $transformer1 = $this->createMock(StampTransformer::class);
        $transformer1->method('supports')->with($stamp)->willReturn(false);
        $transformer1->expects($this->never())->method('transform');

        $transformer2 = $this->createMock(StampTransformer::class);
        $transformer2->method('supports')->with($stamp)->willReturn(true);
        $transformer2->expects($this->once())
            ->method('transform')
            ->with($stamp)
            ->willReturn($expectedResult);

        $chain = new ChainStampTransformer([$transformer1, $transformer2]);
        $result = $chain->transform($stamp);

        $this->assertSame($expectedResult, $result);
    }

    #[Test]
    public function transformThrowsLogicExceptionWhenNoTransformerFound(): void
    {
        $stamp = new TransportStamp('async');

        $transformer = $this->createStub(StampTransformer::class);
        $transformer->method('supports')->willReturn(false);

        $chain = new ChainStampTransformer([$transformer]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf('No stamp transformer found for "%s".', TransportStamp::class));

        $chain->transform($stamp);
    }

    #[Test]
    public function transformThrowsLogicExceptionWithEmptyTransformers(): void
    {
        $stamp = new TransportStamp('async');
        $chain = new ChainStampTransformer([]);

        $this->expectException(\LogicException::class);

        $chain->transform($stamp);
    }

    #[Test]
    public function transformUsesFirstMatchingTransformerOnly(): void
    {
        $stamp = new TransportStamp('async');
        $result1 = new TransportNamesStamp(['async']);

        $transformer1 = $this->createStub(StampTransformer::class);
        $transformer1->method('supports')->willReturn(true);
        $transformer1->method('transform')->willReturn($result1);

        $transformer2 = $this->createMock(StampTransformer::class);
        $transformer2->expects($this->never())->method('supports');
        $transformer2->expects($this->never())->method('transform');

        $chain = new ChainStampTransformer([$transformer1, $transformer2]);
        $result = $chain->transform($stamp);

        $this->assertSame($result1, $result);
    }

    #[Test]
    public function acceptsIterableOfTransformers(): void
    {
        $stamp = new TransportStamp('async');
        $expectedResult = new TransportNamesStamp(['async']);

        $transformer = $this->createStub(StampTransformer::class);
        $transformer->method('supports')->willReturn(true);
        $transformer->method('transform')->willReturn($expectedResult);

        $chain = new ChainStampTransformer(new \ArrayIterator([$transformer]));
        $result = $chain->transform($stamp);

        $this->assertSame($expectedResult, $result);
    }
}
