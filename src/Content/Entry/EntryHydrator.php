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

use DateTimeImmutable;
use ReflectionClass;
use Psr\Clock\ClockInterface;
use Blush\Content\Index\IndexRecord;
use Blush\Content\Parser\DocumentParsers;
use Blush\Content\Schema\FieldContext;
use Blush\Content\Source\ContentSource;
use Blush\Content\Status;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Visibility;
use Blush\Markdown\MarkdownParser;

/**
 * Turns index records into entries. Front matter is hydrated through the
 * type's schema (dates become `DateTimeImmutable` in the site timezone),
 * the status is decided against the clock, and the body's source is a
 * lazy ghost that reads and parses the file only when it's first used.
 * With a `BodyCache` (bound outside development by the cache layer), a
 * rendering cached under the file's content hash skips even that.
 */
final readonly class EntryHydrator
{
	public function __construct(
		private ContentTypes $types,
		private FieldContext $context,
		private ContentSource $source,
		private DocumentParsers $parsers,
		private MarkdownParser $markdown,
		private ClockInterface $clock,
		private ?BodyCache $cache = null
	) {}

	/**
	 * Builds the entry for a record.
	 */
	public function hydrate(IndexRecord $record): Entry
	{
		return new Entry(
			id: $record->id,
			type: $this->types->get($record->type),
			slug: $record->slug,
			key: $record->key,
			title: $record->title,
			status: $record->statusAt($this->clock->now()->getTimestamp()),
			visibility: $record->visibility,
			published: $record->published === null ? null : $this->date($record->published),
			updated: $this->date($record->updated),
			locale: $record->locale,
			fields: $this->types->schema($record->type)->hydrate($record->values, $this->context),
			extra: $record->extra,
			terms: $record->terms,
			landing: $record->landing,
			source: $record->source(),
			body: $this->body($record)
		);
	}

	/**
	 * Builds a virtual entry for a term that's referenced but has no
	 * file. Its title is the term as first written, and it's as current
	 * as the index.
	 */
	public function virtual(ContentType $type, string $slug, string $title, int $updated, string $locale): Entry
	{
		return new Entry(
			id: "virtual:{$type->name}/{$slug}",
			type: $type,
			slug: $slug,
			key: $slug,
			title: $title,
			status: Status::Published,
			visibility: Visibility::Public,
			published: null,
			updated: $this->date($updated),
			locale: $locale,
			fields: ['title' => $title],
			extra: [],
			terms: [],
			landing: false,
			source: null,
			body: new Body(new BodySource(), $this->markdown)
		);
	}

	/**
	 * Returns a record's body, whose source parses the file on first use.
	 */
	private function body(IndexRecord $record): Body
	{
		$path   = $record->id;
		$source = new ReflectionClass(BodySource::class)->newLazyGhost(function (BodySource $source) use ($path): void {
			$document = $this->parsers->parse($path, $this->source->read($path));

			$source->__construct($document->body, $document->format);
		});

		return new Body($source, $this->markdown, $this->cache, $record->hash);
	}

	/**
	 * Returns a timestamp as a date in the site timezone.
	 */
	private function date(int $timestamp): DateTimeImmutable
	{
		return DateTimeImmutable::createFromTimestamp($timestamp)->setTimezone($this->context->timezone);
	}
}
