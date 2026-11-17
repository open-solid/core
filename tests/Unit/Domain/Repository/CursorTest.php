<?php

declare(strict_types=1);

namespace OpenSolid\Core\Tests\Unit\Domain\Repository;

use OpenSolid\Core\Domain\Repository\Cursor;
use OpenSolid\Core\Domain\Repository\InvalidCursor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CursorTest extends TestCase
{
    #[Test]
    public function encodeAndDecode(): void
    {
        $parameters = ['id' => 10, 'name' => 'test'];
        $cursor = new Cursor($parameters, true);

        $decoded = Cursor::fromEncodedString($cursor->encodeToString());

        $this->assertSame($parameters, $decoded->getParameters());
        $this->assertTrue($decoded->isNext());
    }

    #[Test]
    public function encodeAndDecodeWithPreviousCursor(): void
    {
        $cursor = new Cursor(['id' => 10], false);

        $decoded = Cursor::fromEncodedString($cursor->encodeToString());

        $this->assertTrue($decoded->isPrevious());
    }

    #[Test]
    public function encodeProducesUrlSafeString(): void
    {
        $cursor = new Cursor(['id' => 123456789, 'foo' => 'bar/baz+test']);
        $encoded = $cursor->encodeToString();

        $this->assertStringNotContainsString('+', $encoded);
        $this->assertStringNotContainsString('/', $encoded);
        $this->assertStringNotContainsString('=', $encoded);
    }

    #[Test]
    public function fromEncodedStringThrowsForInvalidBase64(): void
    {
        $this->expectException(InvalidCursor::class);

        Cursor::fromEncodedString('not-valid-base64!@#$');
    }

    #[Test]
    public function fromEncodedStringThrowsForInvalidJson(): void
    {
        $this->expectException(InvalidCursor::class);

        Cursor::fromEncodedString($this->encode('not-json'));
    }

    #[Test]
    public function fromEncodedStringChainsPreviousExceptionForInvalidJson(): void
    {
        try {
            Cursor::fromEncodedString($this->encode('not-json'));
            $this->fail('Expected InvalidCursor to be thrown.');
        } catch (InvalidCursor $e) {
            $this->assertInstanceOf(\JsonException::class, $e->getPrevious());
        }
    }

    #[Test]
    public function fromEncodedStringDefaultsToNextWhenIsNextMissing(): void
    {
        $cursor = Cursor::fromEncodedString($this->encode((string) json_encode(['id' => 10])));

        $this->assertTrue($cursor->isNext());
    }

    #[Test]
    public function toArray(): void
    {
        $cursor = new Cursor(['id' => 1], true);

        $this->assertSame(['id' => 1, '_isNext' => true], $cursor->toArray());
    }

    #[Test]
    public function fromEncodedStringThrowsForNonStringInput(): void
    {
        $this->expectException(InvalidCursor::class);

        Cursor::fromEncodedString(['injected' => 'array']);
    }

    #[Test]
    public function fromEncodedStringThrowsForNonScalarParameterValue(): void
    {
        $this->expectException(InvalidCursor::class);

        Cursor::fromEncodedString($this->encode((string) json_encode(['p.id' => [1, 2, 3], '_isNext' => true])));
    }

    #[Test]
    public function fromEncodedStringCastsIsNextToBool(): void
    {
        $cursor = Cursor::fromEncodedString($this->encode((string) json_encode(['id' => 10, '_isNext' => 0])));

        $this->assertFalse($cursor->isNext());
        $this->assertTrue($cursor->isPrevious());
    }

    #[Test]
    public function fromEncodedStringThrowsForNonArrayJson(): void
    {
        $this->expectException(InvalidCursor::class);

        Cursor::fromEncodedString($this->encode((string) json_encode('just-a-string')));
    }

    private function encode(string $payload): string
    {
        return rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    }
}
