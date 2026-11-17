# Pagination

OpenSolid Core provides two pagination strategies. Both expose a small,
framework-agnostic contract in the `Domain\Repository` layer and keep the
persistence-specific machinery in `Infrastructure`, so application code depends
on an abstraction rather than on Doctrine.

They make different trade-offs:

| Feature                                | Offset `SelectablePaginator` | Cursor `CursorPaginator` |
|----------------------------------------|------------------------------|--------------------------|
| Random access to page N                | Yes                          | No                       |
| Total count                            | Yes                          | Yes (extra query)        |
| Stable under concurrent inserts/deletes| No                           | Yes                      |
| Performance on deep pages              | Degrades (OFFSET scan)       | Constant (index range scan) |
| Requires deterministic ORDER BY        | No                           | Yes                      |

Choose the **offset paginator** when you need random access to an arbitrary page
number (e.g. a numbered pagination bar).

Choose the **cursor paginator** when you need stable, high-performance pagination
on large datasets and simple previous/next navigation is sufficient. A total
count is still available via `getTotalCount()`, at the cost of an extra `COUNT`
query.

## Offset-Based Pagination

The `Paginator` interface is the read contract for an offset/page-number paginator:

```php
use OpenSolid\Core\Domain\Repository\Paginator;

/** @var Paginator<Product> $paginator */
$paginator->getCurrentPage();   // int
$paginator->getItemsPerPage();  // int
$paginator->getLastPage();      // int
$paginator->getTotalItems();    // int
count($paginator);              // items on the current page (Countable)

foreach ($paginator as $product) {
    // current page items (Traversable)
}
```

`SelectablePaginator` is the default implementation. It paginates anything that
implements Doctrine's `Selectable` — a domain `Collection`, an
`InMemoryCollection`, or a `LazyCriteriaCollection` backed by the database:

```php
use OpenSolid\Core\Domain\Repository\SelectablePaginator;
use OpenSolid\Core\Infrastructure\Persistence\Doctrine\ORM\LazyCriteriaCollection;
use Doctrine\Common\Collections\Criteria;

$products = LazyCriteriaCollection::for(
    $entityManager,
    Product::class,
    Criteria::create()->orderBy(['name' => 'ASC']),
);

$paginator = new SelectablePaginator($products, currentPage: 1, itemsPerPage: 15);

echo $paginator->getTotalItems();  // total across all pages
echo $paginator->getLastPage();

foreach ($paginator as $product) {
    echo $product->getName()."\n";
}
```

Only the requested slice is loaded: the paginator applies `setFirstResult()` /
`setMaxResults()` to the criteria, so a `LazyCriteriaCollection` issues a single
windowed query for the page plus a `COUNT` for the total.

### Mapping rows

Pass a `$mapper` closure to transform each entity as it is iterated — handy for
turning entities into read models or DTOs without an extra loop:

```php
$paginator = new SelectablePaginator(
    $products,
    currentPage: 1,
    itemsPerPage: 15,
    mapper: fn (Product $product): ProductView => ProductView::fromProduct($product),
);

foreach ($paginator as $view) {
    // $view is a ProductView
}
```

> Passing `itemsPerPage: 0` disables slicing (returns every item) and reports a
> single last page.

## Cursor-Based Pagination

Unlike offset pagination, cursor pagination uses opaque pointers (cursors)
derived from the last seen row to fetch the next or previous page. This makes it
stable and performant on large datasets — no matter how deep you paginate, the
database uses an index range scan instead of skipping rows.

The contract lives in the domain layer as the `CursorPaginator` interface, and
the Doctrine implementation (which paginates a DQL `Query` or `QueryBuilder`)
lives in `Infrastructure\Persistence\Doctrine\ORM`:

```php
use OpenSolid\Core\Domain\Repository\CursorPaginator;                               // interface
use OpenSolid\Core\Infrastructure\Persistence\Doctrine\ORM\DoctrineCursorPaginator; // implementation
```

> **Cursor pagination requires a deterministic `ORDER BY` clause.** Every column
> combination used for sorting must uniquely identify a position in the result
> set. A common pattern is to sort by a timestamp and then by the primary key as
> a tie-breaker.

### Constructor

```php
new DoctrineCursorPaginator(
    Query|QueryBuilder $query,
    bool $queryProducesDuplicates = true,
)
```

`$queryProducesDuplicates`
- Set to `true` (default) when the query joins a to-many collection. The
  paginator then uses a two-query strategy (ID subquery + `WHERE IN`) to return
  the correct number of root entities despite duplicate rows.
- Set to `false` when only to-one joins are present — this avoids the subquery
  overhead.
- Passing `false` on a query that joins a to-many relation is **not** detected:
  duplicate root entities are produced silently, leading to a corrupt result
  set.

### Basic Usage

The `$cursor` parameter accepts either an encoded string produced by a previous
call to `getNextCursorAsString()` / `getPreviousCursorAsString()`, or a `Cursor`
instance returned by `getNextCursor()` / `getPreviousCursor()`. On the first
request it is `null` or an empty string `''` — both are treated as the first
page. It is typically read from the incoming HTTP query string:

```php
$cursor = $request->query->get('cursor'); // null on the first page
```

```php
use OpenSolid\Core\Infrastructure\Persistence\Doctrine\ORM\DoctrineCursorPaginator;

$dql = 'SELECT p FROM App\Entity\BlogPost p ORDER BY p.createdAt DESC, p.id DESC';
$query = $entityManager->createQuery($dql);

$paginator = (new DoctrineCursorPaginator($query))
    ->paginate(cursor: $cursor, limit: 15);

foreach ($paginator as $post) {
    echo $post->getTitle()."\n";
}

if ($paginator->hasNextPage()) {
    echo $paginator->getNextCursorAsString();     // pass back as ?cursor=...
}

if ($paginator->hasPreviousPage()) {
    echo $paginator->getPreviousCursorAsString();
}
```

### Navigating Pages

Pass the encoded cursor back on subsequent requests to move forward or backward:

```php
// Next page
$paginator->paginate(cursor: $nextCursor, limit: 15);

// Previous page
$paginator->paginate(cursor: $previousCursor, limit: 15);
```

The cursor encodes the location at which the next query should begin fetching
results, along with the navigation direction.

### Returning the domain contract from a repository

Build and run the Doctrine paginator inside infrastructure, then return the
`CursorPaginator` interface so callers stay decoupled from Doctrine:

```php
use OpenSolid\Core\Domain\Repository\CursorPaginator;
use OpenSolid\Core\Infrastructure\Persistence\Doctrine\ORM\DoctrineCursorPaginator;

final class DoctrineBlogPostRepository implements BlogPostRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    /** @return CursorPaginator<BlogPost> */
    public function paginate(?string $cursor, int $limit): CursorPaginator
    {
        $query = $this->em->createQuery(
            'SELECT p FROM App\Entity\BlogPost p ORDER BY p.createdAt DESC, p.id DESC',
        );

        return (new DoctrineCursorPaginator($query, queryProducesDuplicates: false))
            ->paginate($cursor, $limit);
    }
}
```

### Paginating DTO queries (the `NEW` operator)

The paginator works with DQL [`NEW` operator](https://www.doctrine-project.org/projects/doctrine-orm/en/current/reference/dql-doctrine-query-language.html#new-operator-syntax)
queries that hydrate each row into a plain DTO instead of an entity — useful for
read models that never touch the entity graph:

```php
final readonly class BlogPostView
{
    public function __construct(
        public int $id,
        public string $title,
    ) {
    }
}

$dql = 'SELECT NEW App\ReadModel\BlogPostView(p.id, p.title)
        FROM App\Entity\BlogPost p
        ORDER BY p.id ASC';

$paginator = (new DoctrineCursorPaginator($em->createQuery($dql), queryProducesDuplicates: false))
    ->paginate($cursor, 15);

foreach ($paginator->getValues() as $view) {
    // $view is a BlogPostView
}
```

Two things to keep in mind:

- **The DTO's properties must be named after the `ORDER BY` fields.** The cursor's
  pivot value is read straight off the DTO (`$view->id`), while the column *type*
  is resolved from the entity the alias points at (`p`). So `ORDER BY p.id` needs
  a public `$id` on the DTO.
- **`getTotalCount()` requires `setUseOutputWalkers(false)` for `NEW` queries.**
  A `NEW` query selects scalars, so the default COUNT *output walker* cannot
  resolve a root identifier and throws (`Not all identifier properties can be
  found in the ResultSetMapping`). Disabling output walkers switches to the
  tree-walker COUNT strategy, which counts correctly:

  ```php
  $paginator = (new DoctrineCursorPaginator($em->createQuery($dql), queryProducesDuplicates: false))
      ->setUseOutputWalkers(false);

  $paginator->paginate($cursor, 15);

  echo $paginator->getTotalCount();
  ```

### API Reference

The methods below form the `CursorPaginator` interface (plus `paginate()`,
`getQuery()` and `getQueryProducesDuplicates()`, which are specific to the
Doctrine implementation).

`paginate(Cursor|string|null $cursor, int $limit): self`
- Executes the query and stores the results. Fetches `$limit + 1` rows to detect
  whether a further page exists, then trims the extra row. Returns `$this` for
  chaining. Throws `InvalidCursor` if `$cursor` is not a `Cursor`, a string, or
  `null`.

`getNextCursor(): Cursor` / `getPreviousCursor(): Cursor`
- Returns the `Cursor` for the next/previous page. Throws `LogicException` if
  that page does not exist — call `hasNextPage()` / `hasPreviousPage()` first.

`getNextCursorAsString(): string` / `getPreviousCursorAsString(): string`
- Same as above, returning the encoded cursor string.

`hasNextPage(): bool` / `hasPreviousPage(): bool`
- Whether a next/previous page is available.

`hasToPaginate(): bool`
- Whether the result set spans more than one page.

`getValues(): array`
- The raw entity array for the current page.

`getItems(): array`
- An array of `CursorItem` objects, each wrapping an entity and its individual
  `Cursor`. Useful when you need a per-row cursor.

`getCursorForItem(mixed $item, bool $isNext = true): Cursor`
- Builds a `Cursor` pointing at a specific entity. `$isNext = true` means "start
  *after* this item"; `false` means "start *before* this item".

`countPageItems(): int`
- Number of items on the current page. Throws `LogicException` if `paginate()`
  has not been called yet.

`getTotalCount(): int`
- Executes an extra `COUNT` query and returns the total number of matching root
  entities, ignoring the cursor and limit.

### Generated SQL

For `ORDER BY p.createdAt DESC, p.id DESC` and a limit of 15:

**Next page**

```sql
SELECT ...
FROM   blog_post p
WHERE  (p.created_at < :cursor_val_0)
   OR  (p.created_at = :cursor_val_0 AND p.id < :cursor_id_1)
ORDER  BY p.created_at DESC, p.id DESC
LIMIT  16   -- limit + 1
```

**Previous page** (the `ORDER BY` is reversed, then the rows are flipped back):

```sql
SELECT ...
FROM   blog_post p
WHERE  (p.created_at > :cursor_val_0)
   OR  (p.created_at = :cursor_val_0 AND p.id > :cursor_id_1)
ORDER  BY p.created_at ASC, p.id ASC
LIMIT  16
```

### HTML Template Example

```php
<?php
use OpenSolid\Core\Infrastructure\Persistence\Doctrine\ORM\DoctrineCursorPaginator;

$cursor = $_GET['cursor'] ?? null;

$query = $entityManager->createQuery(
    'SELECT p FROM App\Entity\BlogPost p ORDER BY p.createdAt DESC, p.id DESC',
);

/** @var DoctrineCursorPaginator<BlogPost> $paginator */
$paginator = (new DoctrineCursorPaginator($query))->paginate(cursor: $cursor, limit: 15);
?>
<p><?= $paginator->getTotalCount() ?> result(s) in total, <?= $paginator->countPageItems() ?> on this page.</p>

<ul>
    <?php foreach ($paginator as $post): ?>
        <li><?= htmlspecialchars($post->getTitle()) ?></li>
    <?php endforeach ?>
</ul>

<?php if ($paginator->hasToPaginate()): ?>
    <nav>
        <?php if ($paginator->hasPreviousPage()): ?>
            <a href="?cursor=<?= urlencode($paginator->getPreviousCursorAsString()) ?>">Previous</a>
        <?php endif ?>

        <?php if ($paginator->hasNextPage()): ?>
            <a href="?cursor=<?= urlencode($paginator->getNextCursorAsString()) ?>">Next</a>
        <?php endif ?>
    </nav>
<?php endif ?>
```

### Cursor Encoding

A `Cursor` is serialized to a URL-safe string via `Cursor::encodeToString()` and
restored via the static `Cursor::fromEncodedString()`. The format is a JSON
object encoded with URL-safe Base64 (no padding):

```json
{
    "p.createdAt": "2024-01-15T10:30:00+00:00",
    "p.id": 42,
    "_isNext": true
}
```

The `_isNext` flag distinguishes next-page cursors from previous-page cursors.
All other keys are the DQL path expressions (`alias.field`) of the `ORDER BY`
columns, and their values are the database representations of the pivot row's
field values.

If you need a different serialization format (e.g. encryption), build it on top
of a `Cursor` instance: call `$cursor->toArray()` to get the raw data, apply your
own encoding, and reconstruct with `new Cursor($parameters, $isNext)`:

```php
use OpenSolid\Core\Domain\Repository\Cursor;

$payload = $cursor->toArray();          // ['p.createdAt' => ..., 'p.id' => 42, '_isNext' => true]
$cursor = new Cursor(['p.id' => 42], isNext: true);
```

`Cursor::fromEncodedString()` throws `InvalidCursor` when the string is not valid
URL-safe Base64, is not valid JSON, or carries a non-scalar parameter value —
guard untrusted input accordingly:

```php
use OpenSolid\Core\Domain\Repository\Cursor;
use OpenSolid\Core\Domain\Repository\InvalidCursor;

try {
    $cursor = Cursor::fromEncodedString($request->query->get('cursor'));
} catch (InvalidCursor) {
    // fall back to the first page
    $cursor = null;
}
```

`paginate()` performs this decoding for you, so passing the raw query-string
value straight through is fine — it will surface `InvalidCursor` on tampered
input.

### Limitations

- Every `ORDER BY` column must map to an entity field. Raw SQL expressions or
  computed columns in `ORDER BY` are not supported.
- The query must have at least one `ORDER BY` item; the paginator throws a
  `LogicException` otherwise.
