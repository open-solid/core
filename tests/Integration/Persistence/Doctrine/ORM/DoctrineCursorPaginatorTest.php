<?php

declare(strict_types=1);

namespace OpenSolid\Core\Tests\Integration\Persistence\Doctrine\ORM;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use OpenSolid\Core\Domain\Repository\Cursor;
use OpenSolid\Core\Domain\Repository\CursorItem;
use OpenSolid\Core\Domain\Repository\InvalidCursor;
use OpenSolid\Core\Infrastructure\Persistence\Doctrine\ORM\DoctrineCursorPaginator;
use OpenSolid\Core\Tests\Integration\Persistence\Doctrine\ORM\Fixtures\Author;
use OpenSolid\Core\Tests\Integration\Persistence\Doctrine\ORM\Fixtures\BlogPost;
use OpenSolid\Core\Tests\Integration\Persistence\Doctrine\ORM\Fixtures\BlogPostView;
use OpenSolid\Core\Tests\Integration\Persistence\Doctrine\ORM\Fixtures\Comment;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DoctrineCursorPaginatorTest extends TestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfig([__DIR__.'/Fixtures'], true);
        $config->enableNativeLazyObjects(true);
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config);
        $this->em = new EntityManager($connection, $config);

        $schemaTool = new SchemaTool($this->em);
        $schemaTool->createSchema($this->em->getMetadataFactory()->getAllMetadata());

        $this->seed();
    }

    #[Test]
    public function firstPageHasNextButNoPrevious(): void
    {
        $paginator = $this->paginator('SELECT p FROM '.BlogPost::class.' p ORDER BY p.id ASC', false)
            ->paginate(null, 2);

        $this->assertSame(2, $paginator->countPageItems());
        $this->assertTrue($paginator->hasNextPage());
        $this->assertFalse($paginator->hasPreviousPage());
        $this->assertTrue($paginator->hasToPaginate());
        $this->assertSame([1, 2], $this->ids($paginator->getValues()));
    }

    #[Test]
    public function forwardNavigationWalksEveryPage(): void
    {
        $dql = 'SELECT p FROM '.BlogPost::class.' p ORDER BY p.id ASC';

        $page1 = $this->paginator($dql, false)->paginate(null, 2);
        $this->assertSame([1, 2], $this->ids($page1->getValues()));

        $page2 = $this->paginator($dql, false)->paginate($page1->getNextCursorAsString(), 2);
        $this->assertSame([3, 4], $this->ids($page2->getValues()));
        $this->assertTrue($page2->hasNextPage());
        $this->assertTrue($page2->hasPreviousPage());

        $page3 = $this->paginator($dql, false)->paginate($page2->getNextCursorAsString(), 2);
        $this->assertSame([5], $this->ids($page3->getValues()));
        $this->assertFalse($page3->hasNextPage());
        $this->assertTrue($page3->hasPreviousPage());
    }

    #[Test]
    public function backwardNavigationReturnsPreviousPageInOriginalOrder(): void
    {
        $dql = 'SELECT p FROM '.BlogPost::class.' p ORDER BY p.id ASC';

        $page2 = $this->paginator($dql, false)->paginate(
            new Cursor(['p.id' => 2], true)->encodeToString(),
            2,
        );
        $this->assertSame([3, 4], $this->ids($page2->getValues()));

        $page1 = $this->paginator($dql, false)->paginate($page2->getPreviousCursorAsString(), 2);
        $this->assertSame([1, 2], $this->ids($page1->getValues()));
    }

    #[Test]
    public function singlePageDoesNotPaginate(): void
    {
        $paginator = $this->paginator('SELECT p FROM '.BlogPost::class.' p ORDER BY p.id ASC', false)
            ->paginate(null, 10);

        $this->assertSame(5, $paginator->countPageItems());
        $this->assertFalse($paginator->hasNextPage());
        $this->assertFalse($paginator->hasPreviousPage());
        $this->assertFalse($paginator->hasToPaginate());
    }

    #[Test]
    public function emptyResultSet(): void
    {
        $paginator = $this->paginator("SELECT p FROM ".BlogPost::class." p WHERE p.title = 'missing' ORDER BY p.id ASC", false)
            ->paginate(null, 10);

        $this->assertSame(0, $paginator->countPageItems());
        $this->assertFalse($paginator->hasNextPage());
        $this->assertFalse($paginator->hasPreviousPage());
        $this->assertSame([], $paginator->getItems());
        $this->assertSame([], $paginator->getValues());
    }

    #[Test]
    public function getTotalCountIgnoresCursorAndLimit(): void
    {
        $paginator = $this->paginator('SELECT p FROM '.BlogPost::class.' p ORDER BY p.id ASC', false)
            ->paginate(null, 2);

        $this->assertSame(5, $paginator->getTotalCount());
    }

    #[Test]
    public function getItemsWrapsEntitiesWithCursors(): void
    {
        $paginator = $this->paginator('SELECT p FROM '.BlogPost::class.' p ORDER BY p.id ASC', false)
            ->paginate(null, 2);

        $items = $paginator->getItems();

        $this->assertCount(2, $items);
        $this->assertInstanceOf(CursorItem::class, $items[0]);
        $this->assertInstanceOf(BlogPost::class, $items[0]->getValue());
        $this->assertInstanceOf(Cursor::class, $items[0]->getCursor());
        $this->assertSame(1, $items[0]->getValue()->id);
    }

    #[Test]
    public function duplicatesPathReturnsDistinctRootEntities(): void
    {
        // LEFT JOIN to a to-many association produces duplicate root rows; the default
        // queryProducesDuplicates=true strategy must still return distinct BlogPosts.
        $dql = 'SELECT p FROM '.BlogPost::class.' p LEFT JOIN p.comments c ORDER BY p.id ASC';

        $paginator = $this->paginator($dql, true)->paginate(null, 2);

        $this->assertSame([1, 2], $this->ids($paginator->getValues()));
        $this->assertTrue($paginator->hasNextPage());
        $this->assertSame(5, $paginator->getTotalCount());
    }

    #[Test]
    public function orderingByToOneAssociationUnwrapsToIdentifier(): void
    {
        $paginator = $this->paginator('SELECT p FROM '.BlogPost::class.' p ORDER BY p.author ASC, p.id ASC', false)
            ->paginate(null, 1);

        $this->assertTrue($paginator->hasNextPage());

        $cursor = $paginator->getNextCursor();
        $this->assertArrayHasKey('p.author', $cursor->getParameters());
        $this->assertSame(10, $cursor->getParameters()['p.author']);
    }

    #[Test]
    public function paginateThrowsInvalidCursorForNonStringNonCursorValue(): void
    {
        $paginator = $this->paginator('SELECT p FROM '.BlogPost::class.' p ORDER BY p.id ASC', false);

        $this->expectException(InvalidCursor::class);
        $paginator->paginate(['injected' => 'array'], 10);
    }

    #[Test]
    public function countPageItemsThrowsBeforePaginate(): void
    {
        $paginator = $this->paginator('SELECT p FROM '.BlogPost::class.' p ORDER BY p.id ASC', false);

        $this->expectException(\LogicException::class);
        $paginator->countPageItems();
    }

    #[Test]
    public function paginatesDtoQueryBuiltWithNewOperator(): void
    {
        // The DQL `NEW` operator hydrates each row into a plain DTO instead of an
        // entity. The ORDER BY still references the entity alias (p.id), so the
        // cursor reads the pivot value straight off the DTO's public property.
        $dql = 'SELECT NEW '.BlogPostView::class.'(p.id, p.title) FROM '.BlogPost::class.' p ORDER BY p.id ASC';

        // A `NEW` query selects scalars, so the COUNT output walker cannot resolve a
        // root identifier; disabling output walkers makes getTotalCount() use the
        // tree-walker strategy instead.
        $page1 = $this->dtoPaginator($dql)->setUseOutputWalkers(false);
        $page1->paginate(null, 2);

        $values = $page1->getValues();
        $this->assertSame([1, 2], array_map(static fn (BlogPostView $view): int => $view->id, $values));
        $this->assertSame('Post 1', $values[0]->title);
        $this->assertTrue($page1->hasNextPage());
        $this->assertSame(5, $page1->getTotalCount());

        $page2 = $this->dtoPaginator($dql)->paginate($page1->getNextCursorAsString(), 2);

        $this->assertSame([3, 4], array_map(static fn (BlogPostView $view): int => $view->id, $page2->getValues()));
        $this->assertTrue($page2->hasPreviousPage());
    }

    #[Test]
    public function dtoQueryWrapsViewsWithCursors(): void
    {
        $dql = 'SELECT NEW '.BlogPostView::class.'(p.id, p.title) FROM '.BlogPost::class.' p ORDER BY p.id ASC';

        $items = $this->dtoPaginator($dql)->paginate(null, 2)->getItems();

        $this->assertCount(2, $items);
        $this->assertInstanceOf(CursorItem::class, $items[0]);
        $this->assertInstanceOf(BlogPostView::class, $items[0]->getValue());
        $this->assertSame(['p.id' => 1], $items[0]->getCursor()->getParameters());
    }

    /**
     * @param array<int, BlogPost> $posts
     *
     * @return list<int>
     */
    private function ids(array $posts): array
    {
        return array_values(array_map(static fn (BlogPost $post): int => $post->id, $posts));
    }

    /**
     * @return DoctrineCursorPaginator<BlogPost>
     */
    private function paginator(string $dql, bool $queryProducesDuplicates): DoctrineCursorPaginator
    {
        /** @var DoctrineCursorPaginator<BlogPost> $paginator */
        $paginator = new DoctrineCursorPaginator($this->em->createQuery($dql), $queryProducesDuplicates);

        return $paginator;
    }

    /**
     * @return DoctrineCursorPaginator<BlogPostView>
     */
    private function dtoPaginator(string $dql): DoctrineCursorPaginator
    {
        /** @var DoctrineCursorPaginator<BlogPostView> $paginator */
        $paginator = new DoctrineCursorPaginator($this->em->createQuery($dql), queryProducesDuplicates: false);

        return $paginator;
    }

    private function seed(): void
    {
        $alice = new Author(10, 'Alice');
        $bob = new Author(20, 'Bob');
        $this->em->persist($alice);
        $this->em->persist($bob);

        $commentId = 1;
        for ($i = 1; $i <= 5; ++$i) {
            $post = new BlogPost($i, 'Post '.$i, 0 === $i % 2 ? $bob : $alice);
            $this->em->persist($post);

            // Give each post a couple of comments to exercise the duplicate-row path.
            for ($c = 0; $c < 2; ++$c) {
                $this->em->persist(new Comment($commentId++, $post, 'Comment on post '.$i));
            }
        }

        $this->em->flush();
        $this->em->clear();
    }
}
