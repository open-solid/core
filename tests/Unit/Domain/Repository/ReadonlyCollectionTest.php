<?php

declare(strict_types=1);

namespace OpenSolid\Core\Tests\Unit\Domain\Repository;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection as DoctrineCollection;
use Doctrine\Common\Collections\Criteria;
use OpenSolid\Core\Domain\Repository\ReadonlyCollection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ReadonlyCollectionTest extends TestCase
{
    #[Test]
    public function countElements(): void
    {
        $collection = new ReadonlyCollection(new ArrayCollection([new \stdClass(), new \stdClass(), new \stdClass()]));

        $this->assertCount(3, $collection);
    }

    #[Test]
    public function emptyAndNonEmpty(): void
    {
        $this->assertTrue(new ReadonlyCollection(new ArrayCollection())->isEmpty());
        $this->assertFalse(new ReadonlyCollection(new ArrayCollection([new \stdClass()]))->isEmpty());
    }

    #[Test]
    public function contains(): void
    {
        $a = new \stdClass();
        $b = new \stdClass();
        $c = new \stdClass();
        $collection = new ReadonlyCollection(new ArrayCollection([$a, $b]));

        $this->assertTrue($collection->contains($a));
        $this->assertFalse($collection->contains($c));
    }

    #[Test]
    public function containsKey(): void
    {
        $collection = new ReadonlyCollection(new ArrayCollection(['x' => new \stdClass()]));

        $this->assertTrue($collection->containsKey('x'));
        $this->assertFalse($collection->containsKey('y'));
    }

    #[Test]
    public function get(): void
    {
        $obj = new \stdClass();
        $collection = new ReadonlyCollection(new ArrayCollection(['x' => $obj]));

        $this->assertSame($obj, $collection->get('x'));
        $this->assertNull($collection->get('y'));
    }

    #[Test]
    public function getKeysAndGetValues(): void
    {
        $a = new \stdClass();
        $b = new \stdClass();
        $collection = new ReadonlyCollection(new ArrayCollection(['x' => $a, 'y' => $b]));

        $this->assertSame(['x', 'y'], $collection->getKeys());
        $this->assertSame([$a, $b], $collection->getValues());
    }

    #[Test]
    public function toArray(): void
    {
        $a = new \stdClass();
        $b = new \stdClass();
        $data = ['x' => $a, 'y' => $b];
        $collection = new ReadonlyCollection(new ArrayCollection($data));

        $this->assertSame($data, $collection->toArray());
    }

    #[Test]
    public function firstAndLast(): void
    {
        $a = new \stdClass();
        $b = new \stdClass();
        $c = new \stdClass();
        $collection = new ReadonlyCollection(new ArrayCollection([$a, $b, $c]));

        $this->assertSame($a, $collection->first());
        $this->assertSame($c, $collection->last());
    }

    #[Test]
    public function firstAndLastOnEmptyCollection(): void
    {
        $collection = new ReadonlyCollection(new ArrayCollection());

        // @phpstan-ignore method.impossibleType
        $this->assertFalse($collection->first());
        // @phpstan-ignore method.impossibleType
        $this->assertFalse($collection->last());
    }

    #[Test]
    public function keyAndCurrentAndNext(): void
    {
        $a = new \stdClass();
        $b = new \stdClass();
        $collection = new ReadonlyCollection(new ArrayCollection([$a, $b]));

        $collection->first();

        $this->assertSame(0, $collection->key());
        $this->assertSame($a, $collection->current());
        $this->assertSame($b, $collection->next());
        $this->assertSame(1, $collection->key());
    }

    #[Test]
    public function slice(): void
    {
        $a = new \stdClass();
        $b = new \stdClass();
        $c = new \stdClass();
        $d = new \stdClass();
        $collection = new ReadonlyCollection(new ArrayCollection([$a, $b, $c, $d]));

        $this->assertSame([1 => $b, 2 => $c], $collection->slice(1, 2));
    }

    #[Test]
    public function exists(): void
    {
        $a = new \stdClass();
        $a->name = 'alice';
        $b = new \stdClass();
        $b->name = 'bob';
        $collection = new ReadonlyCollection(new ArrayCollection([$a, $b]));

        $this->assertTrue($collection->exists(fn ($k, $v) => $v->name === 'alice'));
        $this->assertFalse($collection->exists(fn ($k, $v) => $v->name === 'nobody'));
    }

    #[Test]
    public function filter(): void
    {
        $a = new \stdClass();
        $a->active = true;
        $b = new \stdClass();
        $b->active = false;
        $c = new \stdClass();
        $c->active = true;
        $collection = new ReadonlyCollection(new ArrayCollection([$a, $b, $c]));

        $filtered = $collection->filter(fn ($v) => $v->active);

        $this->assertInstanceOf(ReadonlyCollection::class, $filtered);
        $this->assertSame([$a, $c], $filtered->getValues());
    }

    #[Test]
    public function map(): void
    {
        $a = new \stdClass();
        $a->name = 'alice';
        $b = new \stdClass();
        $b->name = 'bob';
        $collection = new ReadonlyCollection(new ArrayCollection([$a, $b]));

        $mapped = $collection->map(function ($v) {
            $obj = new \stdClass();
            $obj->upper = strtoupper($v->name);

            return $obj;
        });

        $this->assertInstanceOf(ReadonlyCollection::class, $mapped);
        $this->assertSame('ALICE', $mapped->toArray()[0]->upper);
        $this->assertSame('BOB', $mapped->toArray()[1]->upper);
    }

    #[Test]
    public function partition(): void
    {
        $a = new \stdClass();
        $a->even = false;
        $b = new \stdClass();
        $b->even = true;
        $c = new \stdClass();
        $c->even = false;
        $d = new \stdClass();
        $d->even = true;
        $collection = new ReadonlyCollection(new ArrayCollection([$a, $b, $c, $d]));

        [$truthy, $falsy] = $collection->partition(fn ($k, $v) => $v->even);

        $this->assertSame([$b, $d], $truthy->getValues());
        $this->assertSame([$a, $c], $falsy->getValues());
    }

    #[Test]
    public function forAll(): void
    {
        $a = new \stdClass();
        $a->valid = true;
        $b = new \stdClass();
        $b->valid = true;
        $c = new \stdClass();
        $c->valid = false;
        $collection = new ReadonlyCollection(new ArrayCollection([$a, $b, $c]));

        $this->assertFalse($collection->forAll(fn ($k, $v) => $v->valid));

        $allValid = new ReadonlyCollection(new ArrayCollection([$a, $b]));
        $this->assertTrue($allValid->forAll(fn ($k, $v) => $v->valid));
    }

    #[Test]
    public function indexOf(): void
    {
        $a = new \stdClass();
        $b = new \stdClass();
        $c = new \stdClass();
        $collection = new ReadonlyCollection(new ArrayCollection([$a, $b, $c]));

        $this->assertSame(1, $collection->indexOf($b));
        $this->assertFalse($collection->indexOf(new \stdClass()));
    }

    #[Test]
    public function findFirst(): void
    {
        $a = new \stdClass();
        $a->value = 1;
        $b = new \stdClass();
        $b->value = 2;
        $c = new \stdClass();
        $c->value = 3;
        $collection = new ReadonlyCollection(new ArrayCollection([$a, $b, $c]));

        $this->assertSame($c, $collection->findFirst(fn ($k, $v) => $v->value > 2));
        $this->assertNull($collection->findFirst(fn ($k, $v) => $v->value > 10));
    }

    #[Test]
    public function reduce(): void
    {
        $a = new \stdClass();
        $a->value = 1;
        $b = new \stdClass();
        $b->value = 2;
        $c = new \stdClass();
        $c->value = 3;
        $collection = new ReadonlyCollection(new ArrayCollection([$a, $b, $c]));

        /** @var int $sum */
        $sum = $collection->reduce(fn (int $carry, \stdClass $v) => $carry + $v->value, 0);

        $this->assertSame(6, $sum);
    }

    #[Test]
    public function getIterator(): void
    {
        $a = new \stdClass();
        $b = new \stdClass();
        $collection = new ReadonlyCollection(new ArrayCollection([$a, $b]));

        $values = iterator_to_array($collection);

        $this->assertSame([$a, $b], $values);
    }

    #[Test]
    public function matching(): void
    {
        $collection = new ReadonlyCollection(new ArrayCollection([
            ['name' => 'Alice'],
            ['name' => 'Bob'],
        ]));

        $criteria = Criteria::create()->where(Criteria::expr()->eq('name', 'Alice'));
        $result = $collection->matching($criteria);

        $this->assertInstanceOf(ReadonlyCollection::class, $result);
        $this->assertCount(1, $result);
    }

    #[Test]
    public function matchingThrowsOnNonSelectableCollection(): void
    {
        $inner = $this->createStub(DoctrineCollection::class);
        $collection = new ReadonlyCollection($inner);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Expected a selectable collection');

        $collection->matching(Criteria::create());
    }
}
