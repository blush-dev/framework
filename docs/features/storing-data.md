# Storing data

A plugin that keeps its own records, such as albums or sign-ups, keeps
them in a **table**. Tables work the same whatever stores the site's
data, so your code keeps working if a site moves to a database later.

For a few values, such as a plugin's options, use a
[group of settings](settings.md) instead.

## Registering a table

Describe the table, and register it when Blush starts, in your plugin's
[provider's](../plugins/service-providers.md) `register()`:

```php
use Blush\Storage\Record\Table;
use Blush\Storage\Record\TableRegistry;
use Blush\Storage\StorageArea;

public static function albums(): Table
{
	return new Table('gallery/albums', StorageArea::Data, key: 'slug');
}

public function register(): void
{
	$this->container->resolving(TableRegistry::class, static function (object $tables): void {
		$tables->register(self::albums());
	});
}
```

- **The name** is lowercase letters, digits, `_`, and `-`, with `/` to
  group a plugin's tables (`gallery/albums`).
- **The area** is `Data` for site data, `Accounts` for data about
  people, or `Content`.
- **The key** is optional: a value each record has, unique in the table,
  such as a slug or a name, that you can find records by. Key values are
  letters, digits, `.`, `_`, and `-`, starting with a letter or digit.
  A table keyed by a site's paths (`/old-about`) says so with
  `pathKey: true`; on files it's kept as one file, registered with
  `FileLayout::oneFile()`, since its records can't name files.

## Reading and writing records

Ask for `Blush\Storage\Record\RecordStores` in a constructor:

```php
use Blush\Storage\Record\Order;
use Blush\Storage\Record\Record;
use Blush\Storage\Record\RecordQuery;

$table = Gallery::albums();
$store = $stores->store($table);

// Add one. Every record has an id; create() makes a new one.
$store->save($table, Record::create($clock->now(), ['slug' => 'summer', 'title' => 'Summer', 'year' => 2026]));

// Find one by id or by key.
$album = $store->findByKey($table, 'summer');

// Change it: save it again with new fields. Passing the version you
// read refuses the save if someone changed the record since.
$store->save($table, $album->with('title', 'Summer 2026'), $album->version);

// Query.
$recent = $stores->query($table)
	->where('year', '>=', 2024)
	->whereAny(
		static fn (RecordQuery $q): RecordQuery => $q->where('featured', '=', true),
		static fn (RecordQuery $q): RecordQuery => $q->where('tags', 'contains', 'travel')
	)
	->orderBy('year', Order::Desc)
	->paginate(perPage: 20, page: 1)
	->get();

foreach ($recent as $album) {
	echo $album->value('title');
}

$store->delete($table, $album->id);
```

- **A record** is an `id` (a UUID), its **fields** (text, numbers,
  `true`/`false`, `null`, and lists or maps of them; never `id` or
  `content`), and optional **content**, such as Markdown.
  `value('seo.title')` reaches into nested fields.
- **Versions:** every record you read has a `version`, which changes
  when it's saved. Pass it to `save()` or `delete()`, and the write is
  refused with `RecordConflict` if the record changed or was deleted
  since; leave it out to write regardless. `save()` returns the record
  as stored, with its new version.
- **Transactions:** `$store->transaction(fn () => …)` runs several
  writes so none from elsewhere interleave; if it throws, each write is
  put back.

## Queries

- **Conditions:** `=`, `!=`, `<`, `<=`, `>`, `>=`, `between` (two
  values, inclusive), `in` and `not in` (a list), `like` (`%` for any
  run of characters, `_` for one, without regard to case), `contains`
  (a list field holds it), `intersects` (a list field holds any of a
  list), `null`, and `not null`. A missing field is `null`.
- **Subqueries:** `in` and `not in` also take the values one key has in
  the records another query finds, in the same table or another of the
  same area:
  `->where('id', 'not in', new Subquery(new RecordQuery()->where('status', '=', 'hidden'), 'album_id', $photos))`.
- **Comparisons are strict:** the number `2020` and the text `"2020"`
  aren't equal, and `<` compares numbers with numbers and text with text.
  Text matches with `=` are case-sensitive.
- **Order:** `orderBy()` takes several keys; text sorts without regard
  to case, and `null` sorts last either way. Without an order, records
  come in the order they were added.
- **Counting:** `count()`, `countBy('tags')` (how many records have each
  value), and `min()`, `max()`, `sum()`, and `avg()` of a key. The limit
  and page don't apply to them.

## Refs

**Refs** join one record to others through a named relation, in order:
an album's photos, say. Ask for `Blush\Storage\Record\Refs`:

```php
$refs->set($albums, $album->id, 'photos', [$first->id, $second->id]);

// Albums with a given photo, or the photos an album has.
$stores->query($albums)->whereRelated('photos', [$photo->id])->get();
$stores->query($photos)->whereRelated('photos', [$album->id], inverse: true)->get();

// Load each album's photo ids with the albums.
$result = $stores->query($albums)->with('photos')->get();
$result->refs($album->id, 'photos'); // [$first->id, $second->id]
```

`set()` replaces the record's refs for that relation; an empty list
removes them. Refs live in their area's `refs` table, so they work
between any of the area's tables.

## On a flat-file site

A table is a folder of JSON files, one a record, named by its key (or
its id): `gallery/albums` is `user/data/gallery/albums/summer.json`.
Don't edit those files from code; go through the store.

## Testing

For tests, `Blush\Storage\Record\ArrayRecordStore` keeps records in
memory and answers queries the same way.
