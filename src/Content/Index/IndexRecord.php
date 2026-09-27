<?php

/**
 * Index record.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Index;

use Blush\Content\Parser\BodyFormat;
use Blush\Content\Source\SourceFile;
use Blush\Content\Status;
use Blush\Content\Visibility;

/**
 * What the index knows about one entry: everything a query filters or
 * sorts on, the normalized front matter, and the source file's stat and
 * hash. Records are stored as arrays (`toArray()`) and stay arrays while
 * queries run; only the entries a query returns become objects.
 *
 * - `id` is the source path, such as `_posts/2003-04-15.welcome.md`.
 * - `slug` is the file name after its last `.` (D-078), a bundle's folder
 *   name for `slug/index.md`, `index` for a landing page, or the `slug`
 *   front matter.
 * - `key` finds the entry within its type: the slug, prefixed by any
 *   folders between the type's folder and the entry (`about/biography` for
 *   a page). A landing page's key is `''`.
 * - `directory` is the folder the entry is listed in; a bundle is listed
 *   in its folder's parent.
 * - `landing` marks a type folder's own `index` file, which is the
 *   collection's page rather than one of its entries.
 * - `status` is the declared status (`published` or `draft`); whether a
 *   published entry is scheduled depends on the time, so it's decided
 *   when the record is read.
 * - `date` is the published time in the site timezone as `YmdHis`, for
 *   date archives.
 * - `terms` maps each taxonomy to the term slugs the entry references,
 *   and `labels` keeps how a term was written when that differs from its
 *   slug (`Book Reviews`), for virtual terms.
 *
 * @phpstan-type RecordArray array{
 *     id: string,
 *     type: string,
 *     slug: string,
 *     key: string,
 *     directory: string,
 *     locale: string,
 *     landing: bool,
 *     status: string,
 *     visibility: string,
 *     published: ?int,
 *     updated: int,
 *     date: ?string,
 *     title: string,
 *     format: string,
 *     values: array<string, mixed>,
 *     extra: array<string, mixed>,
 *     terms: array<string, list<string>>,
 *     labels: array<string, array<string, string>>,
 *     modified: int,
 *     size: int,
 *     hash: string
 * }
 */
final readonly class IndexRecord
{
	/**
	 * @param array<string, mixed>                 $values Normalized front matter, by field name.
	 * @param array<string, mixed>                 $extra  Undeclared front matter (D-081).
	 * @param array<string, list<string>>          $terms  Term slugs by taxonomy.
	 * @param array<string, array<string, string>> $labels Term labels by taxonomy and slug.
	 */
	public function __construct(
		public string $id,
		public string $type,
		public string $slug,
		public string $key,
		public string $directory,
		public string $locale,
		public bool $landing,
		public Status $status,
		public Visibility $visibility,
		public ?int $published,
		public int $updated,
		public ?string $date,
		public string $title,
		public BodyFormat $format,
		public array $values,
		public array $extra,
		public array $terms,
		public array $labels,
		public int $modified,
		public int $size,
		public string $hash
	) {}

	/**
	 * Returns the source file's stat.
	 */
	public function source(): SourceFile
	{
		return new SourceFile($this->id, $this->modified, $this->size);
	}

	/**
	 * Returns the status at a time: a published entry whose date is still
	 * to come is scheduled.
	 */
	public function statusAt(int $now): Status
	{
		return self::effectiveStatus($this->status->value, $this->published, $now);
	}

	/**
	 * Returns the status a record array has at a time.
	 */
	public static function effectiveStatus(string $status, ?int $published, int $now): Status
	{
		if ($status === Status::Draft->value) {
			return Status::Draft;
		}

		return $published !== null && $published > $now ? Status::Scheduled : Status::Published;
	}

	/**
	 * Rebuilds a record from `toArray()`'s output.
	 *
	 * @param RecordArray $data
	 */
	public static function fromArray(array $data): self
	{
		return new self(
			id: $data['id'],
			type: $data['type'],
			slug: $data['slug'],
			key: $data['key'],
			directory: $data['directory'],
			locale: $data['locale'],
			landing: $data['landing'],
			status: Status::tryFrom($data['status']) ?? Status::Published,
			visibility: Visibility::tryFrom($data['visibility']) ?? Visibility::Public,
			published: $data['published'],
			updated: $data['updated'],
			date: $data['date'],
			title: $data['title'],
			format: BodyFormat::tryFrom($data['format']) ?? BodyFormat::Markdown,
			values: $data['values'],
			extra: $data['extra'],
			terms: $data['terms'],
			labels: $data['labels'],
			modified: $data['modified'],
			size: $data['size'],
			hash: $data['hash']
		);
	}

	/**
	 * Returns a copy with another source stat, for a file that was touched
	 * but not changed.
	 */
	public function withSource(SourceFile $file): self
	{
		return clone($this, ['modified' => $file->modified, 'size' => $file->size]);
	}

	/**
	 * Returns the record as an array for the index.
	 *
	 * @return RecordArray
	 */
	public function toArray(): array
	{
		return [
			'id'         => $this->id,
			'type'       => $this->type,
			'slug'       => $this->slug,
			'key'        => $this->key,
			'directory'  => $this->directory,
			'locale'     => $this->locale,
			'landing'    => $this->landing,
			'status'     => $this->status->value,
			'visibility' => $this->visibility->value,
			'published'  => $this->published,
			'updated'    => $this->updated,
			'date'       => $this->date,
			'title'      => $this->title,
			'format'     => $this->format->value,
			'values'     => $this->values,
			'extra'      => $this->extra,
			'terms'      => $this->terms,
			'labels'     => $this->labels,
			'modified'   => $this->modified,
			'size'       => $this->size,
			'hash'       => $this->hash
		];
	}
}
