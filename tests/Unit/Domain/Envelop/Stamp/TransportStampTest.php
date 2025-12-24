<?php

declare(strict_types=1);

namespace OpenSolid\Core\Tests\Unit\Domain\Envelop\Stamp;

use OpenSolid\Core\Domain\Envelop\Stamp\TransportStamp;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TransportStampTest extends TestCase
{
    #[Test]
    public function acceptsSingleTransportName(): void
    {
        $stamp = new TransportStamp('async');

        $this->assertIsArray($stamp->names);
        $this->assertCount(1, $stamp->names);
        $this->assertSame('async', $stamp->names[0]);
    }

    #[Test]
    public function acceptsArrayOfTransportNames(): void
    {
        $stamp = new TransportStamp(['async', 'sync', 'priority']);

        $this->assertSame(['async', 'sync', 'priority'], $stamp->names);
    }
}
