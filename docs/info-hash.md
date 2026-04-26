# InfoHash and InfoHashArray

← [Documentation index](README.md)

## Mental model: drop-in for an array

`InfoHash` is an in-place swap for "an array of things keyed by id". You do not give up the usual id → row/object map; you gain a maintained secondary index (one field → ids), iteration, counting, JSON serialization, and SQL-aware loading helpers when you need them. Same slot in your code where you would write `$items[$id] = $row`, but with O(1) lookups by another field, optional column-level merging, and 1-N composition without rewriting collections by hand.

It is not an ORM and not a substitute for SQL. It is a structured collection for already-fetched or composed datasets - perfect for "load these books, then their authors, then their loan counts" without resorting to a single wide JOIN.

## What you get

| Capability | Method | Notes |
|------------|--------|-------|
| Add row by object | `addEntry($obj)` | Reads `id_field_name` and `hash_field_name` from `$obj`. |
| Add row by array | `add_entry_array($arr)` | Cast to object, same fields. |
| Iterate / count / `json_encode()` | `Iterator` / `Countable` / `JsonSerializable` | JSON output is `array_values($this->info)` (a JSON array, not keyed by id). |
| Direct lookup | `$infoHash->info[$id]` | Primary id index. |
| Secondary lookup | `$infoHash->hash[$slug]` | Returns the id. |
| Cached id list | `$infoHash->idArr`, `$infoHash->idList` | `idList` is comma-joined and DB-quoted (handy for `WHERE id IN (...)`). |
| Load from DB | `set_data($query, $class, $conn)` | Runs query, hydrates each row into `$class`, calls `addEntry`. |
| Add columns from another query | `aggregate_data($query, $idFieldLocal)` | Merges extra fields onto existing rows by id. |
| Add 1-N relationship | `add_array_data($query, $idFieldLocal, $propertyName, …)` | Pushes related rows under a property on the parent. |
| LIMIT-aware total count | `getTotalCount()` | Strips `ORDER BY` / `LIMIT`, runs `SELECT COUNT(1)` - for paginated lists. |
| XML serialization | `asXML($rootName, $mode)` | When you need it; modes: `auto`, `info_only`, or pass a value. |

## Constructor

```php
use Ufw\InfoHash;

$books = new InfoHash('book_id', 'slug');
```

Fields default to `id` and `name`. `book_id` becomes the primary key in `$info`; `slug` becomes the secondary key in `$hash`.

## Example: compose, do not JOIN

The anti-JOIN pattern is the main reason to reach for `InfoHash`. A wide JOIN multiplies columns by relationships, then you spend memory and CPU normalizing the result back into objects. `InfoHash` lets you keep each query narrow and compose in PHP:

```php
use Ufw\InfoHash;

$books = new InfoHash('book_id', 'slug');

$books->set_data(
    "SELECT book_id, title, slug, author_id FROM book WHERE published = 1 LIMIT 50",
    \App\Model\Record\Book::class
);

$books->aggregate_data(
    "SELECT book_id, COUNT(*) AS active_loans
     FROM loan WHERE returned_at IS NULL AND book_id IN ($books->idList)
     GROUP BY book_id",
    'book_id'
);

$books->add_array_data(
    "SELECT bt.book_id, t.tag_id, t.name
     FROM book_tag bt JOIN tag t ON t.tag_id = bt.tag_id
     WHERE bt.book_id IN ($books->idList)
     ORDER BY t.name",
    'book_id',
    'tags'
);
```

Three narrow queries instead of one wide JOIN. Each returns exactly what it should:

- `set_data` - books, hydrated into `Record\Book` instances.
- `aggregate_data` - adds an `active_loans` field on each book object (`$book->active_loans = 3`).
- `add_array_data` - pushes a `tags` array onto each book (`$book->tags = [Tag, Tag, ...]`).

Iterate and render:

```php
foreach ($books as $id => $book) {
    echo $book->title;            // from set_data
    echo $book->active_loans;     // from aggregate_data
    foreach ($book->tags as $t) { // from add_array_data
        echo $t->name;
    }
}
```

Total result set memory stays close to "books + their loan counts + their tags" - there is no Cartesian explosion. SQL stays grep-able; each query reads as one intent.

## Pagination - `getTotalCount()`

When the main query is paginated, you usually want both the page rows and the total. `getTotalCount()` peels `ORDER BY` and `LIMIT` off the recorded `data_query`, wraps the rest in `SELECT COUNT(1)`, and runs it on the same connection:

```php
$books->set_data(
    "SELECT * FROM book ORDER BY title LIMIT 20 OFFSET 40",
    \App\Model\Record\Book::class
);

$pageItems = count($books);          // 20 (or fewer)
$grandTotal = $books->getTotalCount(); // total before LIMIT
```

Works correctly with `GROUP BY` (wraps as a subquery first). One `InfoHash` instance, two numbers - no need to write the count query by hand.

## 1-N with sub-array indexing

`add_array_data` accepts optional `datafield` and `indexfield` arguments to control how each child is inserted:

| Form | Result on parent |
|------|------------------|
| `add_array_data($q, 'book_id', 'tags')` | `$book->tags = [tagObj, tagObj, ...]` (numeric list of full rows). |
| `add_array_data($q, 'book_id', 'tags', false, 'tag_id')` | `$book->tags = [42 => tagObj, 17 => tagObj, ...]` (keyed by tag id). |
| `add_array_data($q, 'book_id', 'tag_names', 'name')` | `$book->tag_names = ['Fiction', 'Drama', ...]` (only the `name` column). |
| `add_array_data($q, 'book_id', 'tag_names', 'name', 'tag_id')` | `$book->tag_names = [42 => 'Fiction', 17 => 'Drama']` (id → name map). |

The same routine handles "list of related objects", "keyed sub-collection", "list of scalars", and "id → scalar map" - pick the shape your view actually needs, no extra `foreach` to remap.

## `InfoHashArray` - secondary key is multi-valued

When the secondary index field on each object is itself an array (e.g. each book has many `tag_ids`), use `Ufw\InfoHashArray`. Each value in the array gets its own entry in `$hash` pointing to the parent id. Same `addEntry` / `add_entry_array` API.

```php
use Ufw\InfoHashArray;

$books = new InfoHashArray('book_id', 'tag_ids');
// after loading: $books->hash[42] = book_id of any book tagged 42
```

## When to use it

- You would otherwise write `$byId = []` and a second `$bySlug = []`. Start with `InfoHash` - nothing lost, secondary index gained.
- You are loading a list and then enriching it with counts, related rows, or computed fields. `aggregate_data` + `add_array_data` are exactly that.
- You want pagination without writing a separate count query by hand.
- You explicitly do not want the wide-JOIN-then-normalize pattern of classical ORMs.

## When not to use it

- Pure write paths: `InfoHash` is a read-side composition tool. For inserts/updates use `Db` helpers or your `Record` class directly.
- Streaming over very large result sets: `InfoHash` holds everything in memory. For "process millions of rows" use a query loop without buffering.
- Cross-process state: it is in-memory per request. Cache layers, queues, and shared stores are separate concerns.

---

**Next:** [Forms and validation](forms-and-validation.md) - form objects, validators, filters.
