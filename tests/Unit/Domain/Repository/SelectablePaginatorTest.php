<?php

declare(strict_types=1);

namespace OpenSolid\Core\Tests\Unit\Domain\Repository;

use Doctrine\Common\Collections\ArrayCollection;
use OpenSolid\Core\Domain\Repository\SelectablePaginator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SelectablePaginatorTest extends TestCase
{
    #[Test]
    public function basicPagination(): void
    {
        $items = $this->createItems(10);
        $paginator = new SelectablePaginator(new ArrayCollection($items), 1, 3);

        $this->assertSame(1, $paginator->getCurrentPage());
        $this->assertSame(3, $paginator->getItemsPerPage());
        $this->assertSame(10, $paginator->getTotalItems());
        $this->assertSame(4, $paginator->getLastPage());
        $this->assertCount(3, $paginator);
    }

    #[Test]
    public function secondPage(): void
    {
        $items = $this->createItems(10);
        $paginator = new SelectablePaginator(new ArrayCollection($items), 2, 3);

        $this->assertSame(2, $paginator->getCurrentPage());
        $this->assertCount(3, $paginator);
    }

    #[Test]
    public function lastPageWithFewerItems(): void
    {
        $items = $this->createItems(10);
        $paginator = new SelectablePaginator(new ArrayCollection($items), 4, 3);

        $this->assertSame(4, $paginator->getCurrentPage());
        $this->assertCount(1, $paginator);
    }

    #[Test]
    public function exactDivision(): void
    {
        $items = $this->createItems(9);
        $paginator = new SelectablePaginator(new ArrayCollection($items), 1, 3);

        $this->assertSame(3, $paginator->getLastPage());
        $this->assertSame(9, $paginator->getTotalItems());
    }

    #[Test]
    public function singleItemPerPage(): void
    {
        $items = $this->createItems(5);
        $paginator = new SelectablePaginator(new ArrayCollection($items), 3, 1);

        $this->assertSame(5, $paginator->getLastPage());
        $this->assertCount(1, $paginator);
    }

    #[Test]
    public function emptyCollection(): void
    {
        $paginator = new SelectablePaginator(new ArrayCollection(), 1, 10);

        $this->assertSame(0, $paginator->getTotalItems());
        $this->assertSame(1, $paginator->getLastPage());
        $this->assertCount(0, $paginator);
    }

    #[Test]
    public function zeroItemsPerPageReturnsLastPageOne(): void
    {
        $items = $this->createItems(5);
        $paginator = new SelectablePaginator(new ArrayCollection($items), 1, 0);

        $this->assertSame(1, $paginator->getLastPage());
    }

    #[Test]
    public function iteratorWithoutMapper(): void
    {
        $items = $this->createItems(5);
        $paginator = new SelectablePaginator(new ArrayCollection($items), 1, 3);

        $result = iterator_to_array($paginator, false);

        $this->assertCount(3, $result);
        $this->assertSame($items[0], $result[0]);
        $this->assertSame($items[1], $result[1]);
        $this->assertSame($items[2], $result[2]);
    }

    #[Test]
    public function iteratorWithMapper(): void
    {
        $items = $this->createItems(3);
        $items[0]->name = 'alice';
        $items[1]->name = 'bob';
        $items[2]->name = 'charlie';

        $mapper = static function (\stdClass $item): \stdClass {
            $mapped = new \stdClass();
            $mapped->upper = strtoupper($item->name);

            return $mapped;
        };

        $paginator = new SelectablePaginator(new ArrayCollection($items), 1, 10, $mapper);

        $result = iterator_to_array($paginator, false);

        $this->assertCount(3, $result);
        $this->assertSame('ALICE', $result[0]->upper);
        $this->assertSame('BOB', $result[1]->upper);
        $this->assertSame('CHARLIE', $result[2]->upper);
    }

    #[Test]
    public function paginationSlicesCorrectItems(): void
    {
        $items = [];
        for ($i = 0; $i < 6; ++$i) {
            $obj = new \stdClass();
            $obj->index = $i;
            $items[] = $obj;
        }

        $page2 = new SelectablePaginator(new ArrayCollection($items), 2, 2);
        /** @var list<\stdClass> $result */
        $result = iterator_to_array($page2, false);

        $this->assertCount(2, $result);
        $this->assertSame(2, $result[0]->index);
        $this->assertSame(3, $result[1]->index);
    }

    #[Test]
    public function allItemsPerPageLargerThanTotal(): void
    {
        $items = $this->createItems(3);
        $paginator = new SelectablePaginator(new ArrayCollection($items), 1, 100);

        $this->assertSame(1, $paginator->getLastPage());
        $this->assertCount(3, $paginator);
        $this->assertSame(3, $paginator->getTotalItems());
    }

    /**
     * @return list<\stdClass>
     */
    private function createItems(int $count): array
    {
        $items = [];
        for ($i = 0; $i < $count; ++$i) {
            $items[] = new \stdClass();
        }

        return $items;
    }
}
