<?php

/**
 * Entry hydrator.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Entry;

use Closure;
use DateTimeImmutable;
use ReflectionClass;
use Psr\Clock\ClockInterface;
use Blush\Asset\AssetCollector;
use Blush\Content\Record\EntryRecords;
use Blush\Content\Record\EntryTable;
use Blush\Content\Record\EntryTerms;
use Blush\Content\Relation\Refs;
use Blush\Content\Status;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Visibility;
use Blush\Core\AppConfig;
use Blush\Field\FieldContext;
use Blush\Markdown\MarkdownParser;
use Blush\Storage\Record\Record;
use Blush\Storage\Record\RecordStores;

/**
 * Turns `entries` records (D-649) into entries, the same for every
 * driver. Front matter is split by the type's schema into its fields,
 * hydrated (dates become `DateTimeImmutable` in the site timezone), and
 * what it doesn't declare; the status is decided against the clock; and
 * the body's source is a lazy ghost that reads the record's `content`
 * from its store only when it's first used. With a `BodyCache` (bound
 * outside development by the cache layer), a rendering cached under the
 * record's version skips even that.
 */
final class EntryHydrator
{
	/**
	 * Each type's term sources, once asked for.
	 *
	 * @var array<string, list<array{string, string, string, list<string>, bool, string}>>
	 */
	private array $terms = [];

	public function __construct(
		private readonly ContentTypes $types,
		private readonly FieldContext $context,
		private readonly RecordStores $stores,
		private readonly MarkdownParser $markdown,
		private readonly ClockInterface $clock,
		private readonly AppConfig $app,
		private readonly ?BodyCache $cache = null,
		private readonly ?AssetCollector $collector = null
	) {}

	/**
	 * Builds the entry for a record, at its key within its type, kept at
	 * a path (for showing; `''` for a store without files), with the
	 * slugs of the entries its `refs` name, by id, for its terms. Its
	 * Markdown comes from `$content` when given, else from the store; an
	 * entry built for a file without an id (`$identified` false) has
	 * none.
	 *
	 * @param array<string, string> $targets
	 * @param ?Closure(): string    $content
	 */
	public function hydrate(Record $record, string $key, string $path = '', array $targets = [], ?Closure $content = null, bool $identified = true): Entry
	{
		$type     = $this->types->get(EntryRecords::text($record, 'type'));
		$schema   = $this->types->schema($type->name);
		$slug     = EntryRecords::text($record, 'slug');
		$language = EntryRecords::text($record, 'language');
		$front    = [];
		$values   = [];

		// Front matter keys are names, whatever YAML read them as.
		foreach (EntryRecords::front($record) as $name => $value) {
			$front[(string) $name] = $value;

			if ($schema->field((string) $name) !== null) {
				$values[(string) $name] = $value;
			}
		}

		// The fields' times when they're kept as numbers, else the columns'.
		$published = is_int($values['published'] ?? null) ? $values['published'] : EntryTable::timestamp($record->fields['published'] ?? null);
		$updated   = is_int($values['updated'] ?? null) ? $values['updated'] : EntryTable::timestamp($record->fields['updated'] ?? null) ?? $published ?? 0;
		$parent   = EntryRecords::text($record, 'parent_id');
		$original = EntryRecords::text($record, 'original_id');

		return new Entry(
			path: $path,
			type: $type,
			slug: $slug === '' ? 'index' : $slug,
			key: $key,
			title: EntryRecords::text($record, 'title'),
			status: Status::at(EntryRecords::text($record, 'status'), $published, $this->clock->now()->getTimestamp()),
			visibility: Visibility::tryFrom(EntryRecords::text($record, 'visibility')) ?? Visibility::Public,
			published: $published === null ? null : $this->date($published),
			updated: $this->date($updated),
			locale: $this->locale($language, $front),
			fields: $schema->hydrate($values, $this->context),
			extra: array_diff_key($front, $values),
			terms: EntryTerms::of($this->terms[$type->name] ??= EntryTerms::sources($this->types, $type), $values, Refs::fromValue($front[Refs::FIELD] ?? null), $targets),
			landing: $slug === '',
			body: $this->body($record, $language, $content),
			language: $language,
			id: $identified ? $record->id : null,
			version: $record->version,
			parentId: $parent === '' ? null : $parent,
			originalId: $original === '' ? null : $original
		);
	}

	/**
	 * Returns an entry's locale: its language's, in another language;
	 * else its own (`locale` in front matter), or the site's.
	 *
	 * @param array<string, mixed> $front
	 */
	private function locale(string $language, array $front): string
	{
		$other = $this->app->languages->isOther($language) ? $this->app->languages->find($language) : null;

		return $other->locale ?? (is_string($front['locale'] ?? null) ? $front['locale'] : $this->app->locale);
	}

	/**
	 * Returns a record's body, whose source reads its Markdown on first
	 * use.
	 *
	 * @param ?Closure(): string $content
	 */
	private function body(Record $record, string $language, ?Closure $content): Body
	{
		$id      = $record->id;
		$stores  = $this->stores;
		$content ??= static function () use ($stores, $id): string {
			$table = EntryTable::table();

			return $stores->store($table)->find($table, $id)->content ?? '';
		};
		$source  = new ReflectionClass(BodySource::class)->newLazyGhost(static function (BodySource $source) use ($content): void {
			$source->__construct($content());
		});

		return new Body($source, $this->markdown, $this->cache, $record->version ?? '', $this->app->languages->isOther($language) ? $language : '', $this->collector);
	}

	/**
	 * Returns a timestamp as a date in the site timezone.
	 */
	private function date(int $timestamp): DateTimeImmutable
	{
		return DateTimeImmutable::createFromTimestamp($timestamp)->setTimezone($this->context->timezone);
	}
}
