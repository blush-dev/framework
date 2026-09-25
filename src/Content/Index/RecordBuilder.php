<?php

/**
 * Index record builder.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Index;

use DateTimeImmutable;
use Blush\Content\Parser\DocumentParsers;
use Blush\Content\Parser\InvalidDocument;
use Blush\Content\Schema\FieldContext;
use Blush\Content\Source\SourceFile;
use Blush\Content\Status;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Visibility;
use Blush\Core\AppConfig;
use Blush\Support\Slug;

/**
 * Turns one content file into an index record, applying 1.x's file
 * conventions (D-078):
 *
 * - The file's type is the nearest type folder above it (D-083).
 * - Everything before the last `.` of a file name is organizational, so
 *   `2003-04-15.welcome.md` is `welcome`. A `slug` in front matter wins.
 * - `index` directly in a type's folder is that type's landing page.
 *   Anywhere else, `name/index.md` is a bundle: the entry `name`, listed
 *   in the folder above.
 * - A `_`-prefixed file name, or a `_`-prefixed folder between the type's
 *   folder and the file, makes the entry hidden, whatever its front
 *   matter says (1.x's private files and page segments). A `_drafts`
 *   folder makes it a draft.
 * - `published` falls back to `date`; `updated` falls back to
 *   `published`, then to the file's modification time.
 */
final readonly class RecordBuilder
{
	/**
	 * The folder name that marks its entries as drafts.
	 */
	public const string DRAFTS = '_drafts';

	public function __construct(
		private ContentTypes $types,
		private DocumentParsers $parsers,
		private FieldContext $context,
		private AppConfig $app
	) {}

	/**
	 * Parses a file and builds its record.
	 *
	 * @throws InvalidDocument When the file can't be parsed.
	 */
	public function build(SourceFile $file, string $contents): ParsedEntry
	{
		$type     = $this->types->forFile($file->path);
		$document = $this->parsers->parse($file->path, $contents);
		$result   = $this->types->schema($type->name)->resolve($document->frontMatter, $this->context);
		$values   = $result->values;

		$directory = self::directoryOf($file->path);
		$filename  = pathinfo($file->path, PATHINFO_FILENAME);
		$landing   = $filename === 'index' && $directory === $type->path;
		$bundle    = $filename === 'index' && ! $landing;
		$listedIn  = $bundle ? self::directoryOf($directory) : $directory;
		$segments  = self::segmentsBelow($type, $listedIn);
		$private   = $bundle ? [...$segments, basename($directory)] : $segments;

		$slug = match (true) {
			is_string($values['slug'] ?? null) => $values['slug'],
			$landing                           => 'index',
			$bundle                            => self::slugOf(basename($directory)),
			default                            => self::slugOf($filename)
		};

		$published = is_int($values['published'] ?? null) ? $values['published'] : null;
		$updated   = is_int($values['updated'] ?? null) ? $values['updated'] : ($published ?? $file->modified);
		[$terms, $labels] = $this->terms($document->frontMatter, $values);

		$record = new IndexRecord(
			id: $file->path,
			type: $type->name,
			slug: $slug,
			key: $landing ? '' : implode('/', [...$segments, $slug]),
			directory: $listedIn,
			locale: is_string($values['locale'] ?? null) ? $values['locale'] : $this->app->locale,
			landing: $landing,
			status: $this->status($values, $private),
			visibility: $this->visibility($values, $filename, $private),
			published: $published,
			updated: $updated,
			date: $published === null ? null : DateTimeImmutable::createFromTimestamp($published)->setTimezone($this->context->timezone)->format('YmdHis'),
			title: is_string($values['title'] ?? null) ? $values['title'] : '',
			format: $document->format,
			values: $values,
			extra: $result->extra,
			terms: $terms,
			labels: $labels,
			modified: $file->modified,
			size: $file->size,
			hash: self::hash($contents)
		);

		return new ParsedEntry($record, $result->violations);
	}

	/**
	 * Returns the hash the indexer compares to tell a changed file from a
	 * touched one.
	 */
	public static function hash(string $contents): string
	{
		return hash('xxh128', $contents);
	}

	/**
	 * Returns the term slugs each taxonomy's field holds, and the labels of
	 * terms written differently from their slugs.
	 *
	 * @param  array<array-key, mixed> $frontMatter
	 * @param  array<string, mixed>    $values
	 * @return array{array<string, list<string>>, array<string, array<string, string>>}
	 */
	private function terms(array $frontMatter, array $values): array
	{
		$terms  = [];
		$labels = [];

		foreach ($this->types->taxonomies() as $taxonomy) {
			$slugs = $values[$taxonomy->field] ?? [];

			if (! is_array($slugs) || $slugs === []) {
				continue;
			}

			$terms[$taxonomy->name] = array_values(array_map(static fn (mixed $slug): string => (string) $slug, array_filter($slugs, is_scalar(...))));

			$raw = array_find(
				[$frontMatter[$taxonomy->field] ?? null, ...array_map(static fn (string $alias): mixed => $frontMatter[$alias] ?? null, $taxonomy->fieldAliases)],
				static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []
			);

			foreach (is_array($raw) ? $raw : [$raw] as $label) {
				if (is_string($label) && ($slug = Slug::from($label)) !== $label && $slug !== '') {
					$labels[$taxonomy->name][$slug] ??= $label;
				}
			}
		}

		return [$terms, $labels];
	}

	/**
	 * Returns the declared status: front matter, or draft inside a
	 * `_drafts` folder.
	 *
	 * @param array<string, mixed> $values
	 * @param list<string>         $segments
	 */
	private function status(array $values, array $segments): Status
	{
		$declared = is_string($values['status'] ?? null) ? Status::tryFrom($values['status']) : null;

		return $declared ?? (in_array(self::DRAFTS, $segments, true) ? Status::Draft : Status::Published);
	}

	/**
	 * Returns the visibility: hidden for private names, otherwise front
	 * matter's, otherwise public.
	 *
	 * @param array<string, mixed> $values
	 * @param list<string>         $segments
	 */
	private function visibility(array $values, string $filename, array $segments): Visibility
	{
		if (str_starts_with($filename, '_') || array_any($segments, static fn (string $segment): bool => str_starts_with($segment, '_'))) {
			return Visibility::Hidden;
		}

		return is_string($values['visibility'] ?? null)
			? Visibility::tryFrom($values['visibility']) ?? Visibility::Public
			: Visibility::Public;
	}

	/**
	 * Returns the folders between a type's folder and a directory inside
	 * it.
	 *
	 * @return list<string>
	 */
	private static function segmentsBelow(ContentType $type, string $directory): array
	{
		$relative = $type->path === '' ? $directory : substr($directory, strlen($type->path) + 1);

		return $relative === '' ? [] : explode('/', $relative);
	}

	/**
	 * Returns the slug part of a file or folder name: everything after its
	 * last `.`.
	 */
	private static function slugOf(string $name): string
	{
		$position = strrpos($name, '.');

		return $position === false ? $name : substr($name, $position + 1);
	}

	/**
	 * Returns a path's directory, or `''` at the content root.
	 */
	private static function directoryOf(string $path): string
	{
		$directory = dirname($path);

		return $directory === '.' ? '' : $directory;
	}
}
