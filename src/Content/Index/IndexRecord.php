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

use Blush\Content\Source\SourceFile;
use Blush\Content\Status;
use Blush\Content\Visibility;

/**
 * What the index knows about one entry: everything a query filters or
 * sorts on, the normalized front matter, and the source file's stat and
 * hash. Records are stored as arrays (`toArray()`) and stay arrays while
 * queries run; only the entries a query returns become objects.
 *
 * - `path` is the source path, such as `_posts/2003-04-15.welcome.md`.
 * - `id` is the entry's id (D-477): the UUID in its `id` front matter,
 *   lowercased, or `null` when it has none or it isn't a UUID (which
 *   `content:lint` reports).
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
 * - `status` is the declared status (`published`, `draft`, or `trash`,
 *   D-484); whether a
 *   published entry is scheduled depends on the time, so it's decided
 *   when the record is read.
 * - `date` is the published time in the site timezone as `YmdHis`, for
 *   date archives.
 * - `terms` maps each taxonomy to the term slugs the entry references,
 *   and `labels` keeps how a term was written when that differs from its
 *   slug (`Book Reviews`), for titling a missing term's file (D-584).
 * - `parent` is the key of the entry's parent in its own type, for the
 *   types that nest: a page's is the key of the folder it's in
 *   (`about` for `about/biography`), and a hierarchical taxonomy's term
 *   names its own in front matter. `null` for the rest.
 * - `language` is the code of the language the entry is written in
 *   (D-455): a translation's file-name suffix (`about.fr.md` is `fr`),
 *   else the site's default language. `locale` is that language's
 *   locale, or for a file without a suffix, its `locale` front matter
 *   or the site's.
 * - `original` is, for a file with a language suffix, the path the file
 *   would have without it (`about.md` for `about.fr.md`), which links a
 *   translation to its siblings; `null` for the rest.
 * - `group` is, for a translation whose `translation_of` names its
 *   original's id (D-511), the original's translation group, which the
 *   index sets when it's built; `null` for the rest, which link by name
 *   (`groupOf()`).
 * - A translation's `key` and `parent` use its language's slugs (D-457):
 *   `about/biography.fr.md` under `about/index.fr.md` (`slug: a-propos`)
 *   is `a-propos/biographie`, with the parent `a-propos`. The index
 *   sets them when it's built, since they depend on other files, and
 *   keeps the ones the file's own path gives in `untranslated`.
 *
 * @phpstan-type RecordArray array{
 *     path: string,
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
 *     values: array<string, mixed>,
 *     extra: array<string, mixed>,
 *     terms: array<string, list<string>>,
 *     labels: array<string, array<string, string>>,
 *     modified: int,
 *     size: int,
 *     hash: string,
 *     parent: ?string,
 *     language: string,
 *     original: ?string,
 *     untranslated: ?array{key: string, parent: ?string},
 *     id: ?string,
 *     group: ?string
 * }
 */
final readonly class IndexRecord
{
	/**
	 * @param array<string, mixed>                 $values Normalized front matter, by field name.
	 * @param array<string, mixed>                 $extra  Undeclared front matter (D-081).
	 * @param array<string, list<string>>          $terms  Term slugs by taxonomy.
	 * @param array<string, array<string, string>> $labels Term labels by taxonomy and slug.
	 * @param ?string                              $parent   The parent's key in the same type.
	 * @param string                               $language The language's code.
	 * @param ?string                              $original The path without a language suffix.
	 * @param ?array{key: string, parent: ?string} $untranslated A translation's key and parent from its path alone, when the index changed them.
	 * @param ?string                              $id           The entry's id (D-477), or `null` without a valid one.
	 * @param ?string                              $group        The translation group its `translation_of` links it to (D-511).
	 */
	public function __construct(
		public string $path,
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
		public array $values,
		public array $extra,
		public array $terms,
		public array $labels,
		public int $modified,
		public int $size,
		public string $hash,
		public ?string $parent = null,
		public string $language = '',
		public ?string $original = null,
		public ?array $untranslated = null,
		public ?string $id = null,
		public ?string $group = null
	) {}

	/**
	 * Returns the source file's stat.
	 */
	public function source(): SourceFile
	{
		return new SourceFile($this->path, $this->modified, $this->size);
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
		return Status::at($status, $published, $now);
	}

	/**
	 * Rebuilds a record from `toArray()`'s output.
	 *
	 * @param RecordArray $data
	 */
	public static function fromArray(array $data): self
	{
		return new self(
			path: $data['path'],
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
			values: $data['values'],
			extra: $data['extra'],
			terms: $data['terms'],
			labels: $data['labels'],
			modified: $data['modified'],
			size: $data['size'],
			hash: $data['hash'],
			parent: $data['parent'],
			language: $data['language'],
			original: $data['original'],
			untranslated: $data['untranslated'],
			id: $data['id'],
			group: $data['group']
		);
	}

	/**
	 * Returns what links the entry with its translations (see
	 * `groupOf()`).
	 */
	public function group(): string
	{
		return self::groupOf($this->toArray());
	}

	/**
	 * Returns what links a record with its translations (D-455, D-460):
	 * the group its `translation_of` links it to (D-511), else its path without a language suffix or extension, with a bundle's
	 * `name/index` read as `name`, so a plain file and a bundle of one
	 * entry link either way (`about.md` with `about/index.fr.md`, and
	 * `about/index.md` with `about.fr.md`). A landing page's `index`
	 * stays, since it isn't the folder's entry.
	 *
	 * @param RecordArray $record
	 */
	public static function groupOf(array $record): string
	{
		if ($record['group'] !== null) {
			return $record['group'];
		}

		$path      = $record['original'] ?? $record['path'];
		$directory = dirname($path);
		$name      = pathinfo($path, PATHINFO_FILENAME);

		if ($name === 'index' && ! $record['landing'] && $directory !== '.') {
			return $directory;
		}

		return $directory === '.' ? $name : "{$directory}/{$name}";
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
			'path'         => $this->path,
			'type'         => $this->type,
			'slug'         => $this->slug,
			'key'          => $this->key,
			'directory'    => $this->directory,
			'locale'       => $this->locale,
			'landing'      => $this->landing,
			'status'       => $this->status->value,
			'visibility'   => $this->visibility->value,
			'published'    => $this->published,
			'updated'      => $this->updated,
			'date'         => $this->date,
			'title'        => $this->title,
			'values'       => $this->values,
			'extra'        => $this->extra,
			'terms'        => $this->terms,
			'labels'       => $this->labels,
			'modified'     => $this->modified,
			'size'         => $this->size,
			'hash'         => $this->hash,
			'parent'       => $this->parent,
			'language'     => $this->language,
			'original'     => $this->original,
			'untranslated' => $this->untranslated,
			'id'           => $this->id,
			'group'        => $this->group
		];
	}
}
