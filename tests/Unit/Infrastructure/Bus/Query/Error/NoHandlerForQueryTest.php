<?php

declare(strict_types=1);

namespace OpenSolid\Core\Tests\Unit\Infrastructure\Bus\Query\Error;

use OpenSolid\Core\Application\Query\Message\Query;
use OpenSolid\Core\Infrastructure\Bus\Query\Error\NoHandlerForQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class NoHandlerForQueryTest extends TestCase
{
    #[Test]
    public function createWithQuery(): void
    {
        $query = new TestQuery();

        $error = NoHandlerForQuery::create($query);

        $this->assertSame(
            sprintf('No handler for query of type "%s".', TestQuery::class),
            $error->getMessage()
        );
        $this->assertSame(0, $error->getCode());
        $this->assertNull($error->getPrevious());
    }

    #[Test]
    public function createWithPreviousException(): void
    {
        $query = new TestQuery();
        $previous = new \RuntimeException('Original error');

        $error = NoHandlerForQuery::create($query, $previous);

        $this->assertSame($previous, $error->getPrevious());
    }

    #[Test]
    public function createWithCustomCode(): void
    {
        $query = new TestQuery();

        $error = NoHandlerForQuery::create($query, null, 404);

        $this->assertSame(404, $error->getCode());
    }

    #[Test]
    public function messageContainsFullyQualifiedClassName(): void
    {
        $query = new TestQuery();

        $error = NoHandlerForQuery::create($query);

        $this->assertStringContainsString(
            'OpenSolid\Core\Tests\Unit\Infrastructure\Bus\Query\Error\TestQuery',
            $error->getMessage()
        );
    }
}

final readonly class TestQuery extends Query
{
}
