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
use Blush\Content\EntryFields;
use Blush\Content\Parser\DocumentParser;
use Blush\Content\Parser\InvalidDocument;
use Blush\Content\Source\SourceFile;
use Blush\Content\Status;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Visibility;
use Blush\Core\AppConfig;
use Blush\Core\Language;
use Blush\Field\FieldContext;
use Blush\Field\Violation;
use Blush\Support\Slug;
use Blush\Support\Uuid;

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
 * - On a multilingual site (D-455), a file name whose last `.` part
 *   (before the extension) is a language code is a translation in that
 *   language: `about.fr.md`, `2026-10-04.hello.fr.md`, and
 *   `about/index.fr.md` read as `about.md`, `2026-10-04.hello.md`, and
 *   `about/index.md` do, in French. The default language's code is
 *   read too, so `about.en.md` is the English `about` (which
 *   `content:lint` flags beside `about.md`).
 * - `id` (D-477) is the entry's id, a UUID, and no field's: it's read
 *   before the schema, so it's never an undeclared key, and a missing or
 *   malformed one is a violation.
 * - `translation_of` (D-511) is a translation's original's id: one that
 *   isn't a UUID, or on a file without a language suffix, is a
 *   violation. The index links it (`IndexSnapshot::translationOf()`).
 */
final readonly class RecordBuilder
{
	/**
	 * The folder name that marks its entries as drafts.
	 */
	public const string DRAFTS = '_drafts';

	public function __construct(
		private ContentTypes $types,
		private DocumentParser $parser,
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
		$document = $this->parser->parse($contents);
		$data     = $document->frontMatter;
		$id       = $data[EntryFields::ID] ?? null;

		unset($data[EntryFields::ID]);

		$result = $this->types->schema($type->name)->resolve($data, $this->context);
		$values = $result->values;

		$directory = self::directoryOf($file->path);
		[$filename, $language] = $this->language(pathinfo($file->path, PATHINFO_FILENAME));
		$suffixed  = $language !== null;
		$language ??= $this->app->languages->default;
		$landing   = $filename === 'index' && $directory === $type->folder;
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
		[$terms, $labels] = $this->terms($type, $document->frontMatter, $values);

		$record = new IndexRecord(
			path: $file->path,
			type: $type->name,
			slug: $slug,
			key: $landing ? '' : implode('/', [...$segments, $slug]),
			directory: $listedIn,
			locale: match (true) {
				$suffixed                          => $language->locale,
				is_string($values['locale'] ?? null) => $values['locale'],
				default                            => $this->app->locale
			},
			landing: $landing,
			status: $this->status($values, $private),
			visibility: $this->visibility($values, $filename, $private),
			published: $published,
			updated: $updated,
			date: $published === null ? null : DateTimeImmutable::createFromTimestamp($published)->setTimezone($this->context->timezone)->format('YmdHis'),
			title: is_string($values['title'] ?? null) ? $values['title'] : '',
			values: $values,
			extra: $result->extra,
			terms: $terms,
			labels: $labels,
			modified: $file->modified,
			size: $file->size,
			hash: self::hash($contents),
			parent: $landing ? null : $type->parentKey(implode('/', [...$segments, $slug]), $values),
			language: $language->code,
			original: $suffixed ? ltrim("{$directory}/{$filename}." . pathinfo($file->path, PATHINFO_EXTENSION), '/') : null,
			id: is_string($id) && Uuid::isValid($id) ? strtolower($id) : null
		);

		return new ParsedEntry($record, [...self::checkId($id), ...self::checkTranslationOf($values, $suffixed), ...$result->violations], $document->frontMatter);
	}

	/**
	 * Returns the violation for an id that's missing or isn't a UUID.
	 *
	 * @return list<Violation>
	 */
	private static function checkId(mixed $id): array
	{
		return match (true) {
			Uuid::isValid($id)          => [],
			$id === null || $id === '' => [new Violation(EntryFields::ID, 'is missing; every entry needs one. Add it with content:ids --write, or on Site Health in the admin.')],
			default                    => [new Violation(EntryFields::ID, sprintf('"%s" isn\'t a UUID; give the entry a new one with content:ids --write, or on Site Health in the admin.', is_scalar($id) ? (string) $id : get_debug_type($id)))]
		};
	}

	/**
	 * Returns the violation for a `translation_of` that isn't a UUID, or
	 * that's on a file that isn't a translation.
	 *
	 * @param  array<string, mixed> $values
	 * @return list<Violation>
	 */
	private static function checkTranslationOf(array $values, bool $suffixed): array
	{
		$id = $values[EntryFields::TRANSLATION_OF] ?? null;

		return match (true) {
			$id === null        => [],
			! Uuid::isValid($id) => [new Violation(EntryFields::TRANSLATION_OF, sprintf('"%s" isn\'t a UUID; it names the id of the entry this translates.', is_scalar($id) ? (string) $id : get_debug_type($id)))],
			! $suffixed         => [new Violation(EntryFields::TRANSLATION_OF, 'names an original, but this file isn\'t a translation: a translation has its language\'s code before the extension (hello.fr.md).')],
			default             => []
		};
	}

	/**
	 * Splits a file name (without its extension) into the name it has
	 * without a language suffix and the suffix's language, or `null`
	 * when it has none. Only a multilingual site's codes are read.
	 *
	 * @return array{string, ?Language}
	 */
	private function language(string $filename): array
	{
		$languages = $this->app->languages;
		$position  = strrpos($filename, '.');

		if ($position === false || ! $languages->isMultilingual()) {
			return [$filename, null];
		}

		$language = $languages->find(substr($filename, $position + 1));

		return $language === null ? [$filename, null] : [substr($filename, 0, $position), $language];
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
	 * Returns the term slugs each taxonomy's field holds, and the labels
	 * of terms written differently from their slugs. The profiles an
	 * entry credits (D-351) are kept twice: under each people field
	 * (`profile.cooks`), for that field's archives, and together under
	 * the profiles type's name, for a profile's own page.
	 *
	 * @param  array<array-key, mixed> $frontMatter
	 * @param  array<string, mixed>    $values
	 * @return array{array<string, list<string>>, array<string, array<string, string>>}
	 */
	private function terms(ContentType $type, array $frontMatter, array $values): array
	{
		$sources  = [];
		$profiles = $this->types->profiles()?->name;

		foreach ($this->types->taxonomies() as $taxonomy) {
			$sources[] = [$taxonomy->name, $taxonomy->name, $taxonomy->field, $taxonomy->aliases];
		}

		if ($profiles !== null) {
			foreach ($type->people as $people) {
				$sources[] = [$people->termKey($profiles), $profiles, $people->field, $people->aliases];
			}
		}

		$terms  = [];
		$labels = [];

		foreach ($sources as [$key, $labelKey, $field, $aliases]) {
			$value = $values[$field] ?? [];
			$slugs = is_array($value) ? $value : [$value];
			$slugs = array_values(array_map(static fn (mixed $slug): string => (string) $slug, array_filter($slugs, static fn (mixed $slug): bool => is_scalar($slug) && $slug !== '')));

			if ($slugs === []) {
				continue;
			}

			$terms[$key] = $slugs;

			if ($key !== $labelKey) {
				$terms[$labelKey] = array_values(array_unique([...$terms[$labelKey] ?? [], ...$slugs]));
			}

			$raw = array_find(
				[$frontMatter[$field] ?? null, ...array_map(static fn (string $alias): mixed => $frontMatter[$alias] ?? null, $aliases)],
				static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []
			);

			foreach (is_array($raw) ? $raw : [$raw] as $label) {
				if (is_string($label) && ($slug = Slug::from($label)) !== $label && $slug !== '') {
					$labels[$labelKey][$slug] ??= $label;
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
		$relative = $type->folder === '' ? $directory : substr($directory, strlen($type->folder) + 1);

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
