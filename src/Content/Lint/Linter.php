<?php

/**
 * Content linter.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Lint;

use Closure;
use DateMalformedStringException;
use DateTimeImmutable;
use Blush\Content\EntryFields;
use Blush\Content\FlatEntries;
use Blush\Content\Index\IndexRecord;
use Blush\Content\Index\IndexSnapshot;
use Blush\Content\Index\ParsedEntry;
use Blush\Content\Index\RecordBuilder;
use Blush\Content\Parser\FrontMatter;
use Blush\Content\Parser\InvalidDocument;
use Blush\Content\Query\InvalidQuery;
use Blush\Content\Query\Query;
use Blush\Content\Relation\LinkBuilder;
use Blush\Content\Relation\ProblemKind;
use Blush\Content\Relation\Relation;
use Blush\Content\Relation\RelationChecker;
use Blush\Content\Relation\RelationKind;
use Blush\Content\Relation\Relations;
use Blush\Content\Source\ContentSource;
use Blush\Content\Source\UnreadableSource;
use Blush\Content\Status;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\Profiles;
use Blush\Content\Type\Tree;
use Blush\Content\Routing\PageRoutes;
use Blush\Core\AppConfig;
use Blush\Field\Fields\DateField;
use Blush\Field\Severity;
use Blush\Field\Violation;
use Blush\Routing\RouteTable;
use Blush\Media\MediaMetadataCheck;

/**
 * Checks every content file, as `content:lint` reports it. It reads the
 * files fresh (not the index) and reports:
 *
 * - errors: files that can't be parsed, an `id` that's missing, isn't a
 *   UUID, or is another file's too (D-477), front matter values that
 *   don't fit their fields, `collection` query arguments that don't work,
 *   terms that are their own parent or ancestor, and an order prefix
 *   (`01.about.md`) on a tree's or profiles type's file or folder, which
 *   only collections use (D-409; the file still works,
 *   and hidden ones are left alone), and terms and profiles entries
 *   name that have no file, which the site leaves out (D-584;
 *   `content:terms` writes them);
 * - warnings: two files claiming one entry (`about.md` next to
 *   `about/index.md`), a term's `parent` that has no file, and a page
 *   whose address another route answers (`movie/2024.md` beside a type
 *   with date archives at `/movie/{year}`), and a directive asking for a
 *   variant it doesn't have under the active theme
 *   (`VariantCheck`, D-266), and a date that isn't on the calendar
 *   (`2019-00-00`, which is read as 2018-11-30; D-449);
 * - notices: undeclared keys and 1.x aliases (D-081).
 *
 * It also reports files in `user/content` in formats Blush no longer
 * reads (`.html`, `.json`, and the like; `FormatCheck`, D-501), and
 * checks the media metadata files under `user/data/media`
 * (`MediaMetadataCheck`, D-293): ones that can't be read or whose values
 * don't fit, and ones whose media file is gone; and notes field set
 * targets that attach to nothing (`FieldSetCheck`, D-337).
 */
final readonly class Linter
{
	/**
	 * The field name violations about the whole file use.
	 */
	public const string FILE = 'file';

	public function __construct(
		private ContentSource $source,
		private RecordBuilder $builder,
		private ContentTypes $types,
		private RouteTable $routes,
		private VariantCheck $variants,
		private MediaMetadataCheck $media,
		private FieldSetCheck $sets,
		private FormatCheck $formats,
		private AppConfig $app,
		private Relations $relations
	) {}

	/**
	 * Lints the source. `$progress` is called after each file with the
	 * number done and the total.
	 *
	 * @param  ?Closure(int, int): void $progress
	 * @throws UnreadableSource When the source can't be listed.
	 */
	public function lint(?Closure $progress = null): LintReport
	{
		$files      = $this->source->files();
		$records    = [];
		$violations = [];

		foreach ($files as $done => $file) {
			try {
				$contents = $this->source->read($file->path);
				$parsed   = $this->builder->build($file, $contents);

				$records[]               = $parsed->record;
				$violations[$file->path] = [...$parsed->violations, ...$this->checkCollection($parsed->record), ...$this->checkOwnParent($parsed->record), ...$this->checkPrefix($parsed->record), ...$this->checkFlat($parsed->record), ...$this->checkDates($parsed, $contents), ...$this->variants->check($contents)];
			} catch (InvalidDocument | UnreadableSource $e) {
				$violations[$file->path] = [new Violation(self::FILE, $e->getMessage())];
			}

			if ($progress !== null) {
				$progress($done + 1, count($files));
			}
		}

		$snapshot = IndexSnapshot::build($records, '', 0);

		foreach ($snapshot->conflicts as $key => $paths) {
			[$language, $type, $entryKey] = explode('/', $key, 3) + ['', '', ''];
			$winner = $snapshot->find($language, $type, $entryKey);

			foreach ($paths as $path) {
				if ($path === $winner) {
					continue;
				}

				$violations[$path][] = ($snapshot->records[$path]['original'] ?? null) === $winner
					? new Violation(self::FILE, sprintf('has the default language\'s suffix beside %s, which wins; the default language needs none, so remove one.', $winner), Severity::Warning)
					: new Violation(self::FILE, sprintf('is the same entry as %s, which wins.', $winner), Severity::Warning);
			}
		}

		foreach ($snapshot->duplicates as $paths) {
			foreach ($paths as $path) {
				$others = array_values(array_diff($paths, [$path]));

				$violations[$path][] = new Violation(EntryFields::ID, sprintf('is also the id of %s; keep it on one file and give the others new ones with content:ids --keep, or on Site Health in the admin.', implode(', ', $others)));
			}
		}

		foreach ($snapshot->records as $path => $record) {
			[, $problem] = IndexSnapshot::translationOf($record, $snapshot->ids, $snapshot->records);

			if ($problem !== null) {
				$violations[$path][] = new Violation(EntryFields::TRANSLATION_OF, $problem);
			}
		}

		foreach ($records as $record) {
			// With a translation's key and parent in its language (D-457).
			$record = $snapshot->record($record->path) ?? $record;

			foreach ([...$this->missingTerms($snapshot, $record), ...$this->checkParent($snapshot, $record), ...$this->checkPageAddress($record)] as $violation) {
				$violations[$record->path][] = $violation;
			}
		}

		foreach ($this->relationProblems($snapshot) as $path => $problems) {
			foreach ($problems as $violation) {
				$violations[$path][] = $violation;
			}
		}

		// Media metadata files, by their path from the site root (D-293).
		[$metadata, $described] = $this->media->check();

		return new LintReport(count($files), [...$violations, ...$this->formats->check(), ...$described, ...$this->sets->check()], $metadata);
	}

	/**
	 * Checks one file on its own, for an editor that just saved it: its
	 * front matter against its type's schema and its `collection` query.
	 * Checks that need every file (two files claiming one entry, terms
	 * without entries) are `lint()`'s.
	 *
	 * @return list<Violation>
	 */
	public function lintFile(string $path): array
	{
		$file = $this->source->stat($path);

		if ($file === null) {
			return [new Violation(self::FILE, 'doesn\'t exist.')];
		}

		try {
			$contents = $this->source->read($path);
			$parsed   = $this->builder->build($file, $contents);

			return [...$parsed->violations, ...$this->checkCollection($parsed->record), ...$this->checkOwnParent($parsed->record), ...$this->checkPrefix($parsed->record), ...$this->checkFlat($parsed->record), ...$this->checkDates($parsed, $contents), ...$this->variants->check($contents)];
		} catch (InvalidDocument | UnreadableSource $e) {
			return [new Violation(self::FILE, $e->getMessage())];
		}
	}

	/**
	 * Checks that a `collection` front matter value is a valid query,
	 * and warns of 1.x's file order, read as `published` (D-516).
	 *
	 * @return list<Violation>
	 */
	private function checkCollection(IndexRecord $record): array
	{
		$collection = $record->values['collection'] ?? null;

		if (! is_array($collection)) {
			return [];
		}

		try {
			Query::fromArray($collection);
		} catch (InvalidQuery $e) {
			return [new Violation('collection', $e->getMessage())];
		}

		$orderBy = $collection['orderby'] ?? null;

		return in_array($orderBy, Query::FILE_ORDER, true)
			? [new Violation('collection', sprintf('"orderby: %s" is read as "published"; entries are never sorted by file. Write "orderby: published".', $orderBy), Severity::Warning)]
			: [];
	}

	/**
	 * Checks that each date in the front matter, as written, is on the
	 * calendar (D-449). PHP rolls a zero or too-large month or day over
	 * (`2019-00-00` is 2018-11-30, `2019-02-30` is 2019-03-02), so a
	 * placeholder date quietly becomes a real one. YAML hands dates over
	 * already rolled, so the date is read from the key's line in the file
	 * when there is one (JSON front matter keeps its strings as written).
	 *
	 * @return list<Violation>
	 */
	private function checkDates(ParsedEntry $parsed, string $contents): array
	{
		$schema     = $this->types->schema($parsed->record->type);
		$violations = [];

		foreach ($parsed->frontMatter as $key => $value) {
			$key = (string) $key;

			if (! $schema->field($key) instanceof DateField) {
				continue;
			}

			// The key's own line first: YAML hands dates over already rolled.
			$written = FrontMatter::written($contents, $key) ?? (is_string($value) ? $value : '');

			if (
				preg_match('/^\s*(\d{4})-(\d{2})-(\d{2})/', $written, $matches) !== 1
				|| checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])
			) {
				continue;
			}

			// A month or day PHP can't roll over is already the field's error.
			try {
				$read = new DateTimeImmutable("{$matches[1]}-{$matches[2]}-{$matches[3]}")->format('Y-m-d');
			} catch (DateMalformedStringException) {
				continue;
			}

			$violations[] = new Violation($key, sprintf('"%s-%s-%s" isn\'t a real date, so it\'s read as %s.', $matches[1], $matches[2], $matches[3], $read), Severity::Warning);
		}

		return $violations;
	}

	/**
	 * Checks that a tree's or profiles type's file, and the folders
	 * between it and its type's folder, have no order prefix (D-409): the
	 * part of a name before its last `.`. A file may have the prefix its
	 * type's own `filename` pattern gives it (D-514); a folder never. Only collections
	 * order files by name; a page's folder named `01.about` doesn't nest
	 * under `about`, and a prefix orders nothing in a tree. A hidden file,
	 * or one in a hidden folder that isn't a type's (`__drafts/`), is
	 * left alone.
	 *
	 * @return list<Violation>
	 */
	private function checkPrefix(IndexRecord $record): array
	{
		$type = $this->types->find($record->type);

		if (! $type instanceof Tree && ! $type instanceof Profiles) {
			return [];
		}

		// A translation is checked by its name without the suffix (D-455).
		$path     = $record->original ?? $record->path;
		$below    = $type->folder === '' ? $path : substr($path, strlen($type->folder) + 1);
		$segments = explode('/', $below);
		$last     = array_key_last($segments);

		// Hidden files have no address, so a prefix harms nothing there.
		if (array_any($segments, static fn (string $segment): bool => str_starts_with($segment, '_'))) {
			return [];
		}

		$segments[$last] = pathinfo($segments[$last], PATHINFO_FILENAME);

		$renamed = array_map(static function (string $segment): string {
			$position = strrpos($segment, '.');

			return $position === false ? $segment : substr($segment, $position + 1);
		}, $segments);

		// The type's own pattern may give its files a prefix (D-514), never
		// its folders (D-513).
		if ($type->filename !== null && $type->naming()->explains($segments[$last])) {
			$renamed[$last] = $segments[$last];
		}

		if ($renamed === $segments) {
			return [];
		}

		$extension = pathinfo($record->path, PATHINFO_EXTENSION);
		$suffix    = $record->original === null ? '' : ".{$record->language}";
		$suggested = ltrim($type->folder . '/' . implode('/', $renamed) . "{$suffix}.{$extension}", '/');

		return [new Violation(self::FILE, sprintf(
			'has an order prefix, which only collections use; %s don\'t%s. Rename it %s.',
			$type->labels->items,
			$type->filename === null ? '' : sprintf(', beyond their file name pattern (%s) on files', $type->filename->pattern),
			$suggested
		))];
	}

	/**
	 * Checks that a collection's entry is a file directly in its folder
	 * (D-514), or in a `_` folder there: not a folder entry, and not in a
	 * folder of its own below.
	 *
	 * @return list<Violation>
	 */
	private function checkFlat(IndexRecord $record): array
	{
		$type = $this->types->find($record->type);
		$flat = $type === null ? null : FlatEntries::flatPath($type, $record->path, $record->landing);

		if ($flat === null) {
			return [];
		}

		return [new Violation(self::FILE, sprintf(
			'%s; a collection\'s entries are files in its folder. Move it to %s with content:flatten, or on Site Health in the admin.',
			str_starts_with(basename($record->path), 'index.') ? 'is a folder entry' : 'is in a folder below its collection\'s',
			$flat
		))];
	}

	/**
	 * Checks that a hierarchical collection's entry doesn't name itself
	 * as its parent.
	 *
	 * @return list<Violation>
	 */
	private function checkOwnParent(IndexRecord $record): array
	{
		return $this->types->nestsByParent($record->type) && ($record->values['parent'] ?? null) === $record->key
			? [new Violation('parent', 'names the entry itself; an entry can\'t be its own parent.')]
			: [];
	}

	/**
	 * Checks that an entry's parent has a file and that following parents
	 * up never comes back to the entry. Tree types are left out: a folder
	 * needn't have an entry of its own.
	 *
	 * @return list<Violation>
	 */
	private function checkParent(IndexSnapshot $snapshot, IndexRecord $record): array
	{
		if (! $this->types->nestsByParent($record->type) || $record->parent === null) {
			return [];
		}

		$chain = [$record->key];
		$key   = $record->parent;

		while ($key !== null) {
			$path = $snapshot->find($record->language, $record->type, $key);

			if ($path === null) {
				return $key === $record->parent
					? [new Violation('parent', sprintf('"%s" has no %s entry; the entry is shown at the top level.', $key, $record->type), Severity::Warning)]
					: [];
			}

			if (in_array($key, $chain, true)) {
				return $key === $record->key
					? [new Violation('parent', sprintf('makes a loop: %s.', implode(' → ', [...$chain, $key])))]
					: [];
			}

			$chain[] = $key;
			$key     = $snapshot->record($path)?->parent;
		}

		return [];
	}

	/**
	 * Checks that a page's address reaches the page: that no other route
	 * (a type's entries, listings, or archives) answers it first. Type
	 * folders start with `_` by default so this is rare, but a type's URL
	 * prefix can still match a page folder.
	 *
	 * @return list<Violation>
	 */
	private function checkPageAddress(IndexRecord $record): array
	{
		if (! $this->types->find($record->type) instanceof Tree || $record->key === '') {
			return [];
		}

		// Another language's pages are under its prefix (D-455).
		$prefix = $this->app->languages->isOther($record->language) ? "/{$record->language}" : '';
		$single = $prefix === '' ? PageRoutes::SINGLE : "{$record->language}:" . PageRoutes::SINGLE;
		$route  = $this->routes->find('GET', "{$prefix}/{$record->key}")?->route;

		return $route !== null && $route->name !== $single
			? [new Violation(self::FILE, sprintf('is at %s/%s, but the %s route answers there, so the page can\'t be reached; move the page or change the type\'s prefix.', $prefix, $record->key, $route->name ?? $route->handlerName()), Severity::Warning)]
			: [];
	}

	/**
	 * Returns the problems relations find (D-585, D-590), by path, that
	 * no other check reports: a link to the entry itself, a value naming
	 * entries of two types or an id of the wrong type, a plain
	 * reference to nothing (terms and profiles are `missingTerms()`'s), too
	 * many targets, and too many entries naming one target. Parents are
	 * `checkOwnParent()`'s and `checkParent()`'s, and a required field
	 * left empty is the schema's.
	 *
	 * @return array<string, list<Violation>>
	 */
	private function relationProblems(IndexSnapshot $snapshot): array
	{
		$links    = new LinkBuilder()->build($snapshot, $this->relations, $this->types);
		$entries  = [];
		$trashed  = [];
		$found    = [];
		$problems = [...$links->problems];

		foreach ($snapshot->records as $record) {
			if ($record['id'] !== null) {
				$entries[$record['id']] = $record['type'];

				if ($record['status'] === Status::Trash->value) {
					$trashed[] = $record['id'];
				}
			}
		}

		foreach (new RelationChecker()->check($this->relations, $links->graph, $entries, [], $trashed) as $problem) {
			if ($problem->kind === ProblemKind::TooMany || $problem->kind === ProblemKind::InverseLimit) {
				$problems[] = $problem;
			}
		}

		foreach ($problems as $problem) {
			$relation = $this->relations->byKey($problem->key) ?? array_find($this->relations->relations, static fn (Relation $relation): bool => $relation->name === $problem->key);
			$path     = $snapshot->path($problem->source);
			$skip     = $relation?->kind === RelationKind::Parent
				|| ($problem->kind === ProblemKind::Missing && $relation?->kind !== RelationKind::Reference);

			if ($path !== null && $relation !== null && ! $skip) {
				$found[$path][] = new Violation($relation->field, $problem->message);
			}
		}

		return $found;
	}

	/**
	 * Returns errors for the terms the entry references and the profiles
	 * it credits (by the people field that credits them) that have no
	 * file in any language (D-584): entries name terms by the original's
	 * key (D-455), so a translation's entry references a term whose file
	 * is in the default language.
	 *
	 * @return list<Violation>
	 */
	private function missingTerms(IndexSnapshot $snapshot, IndexRecord $record): array
	{
		$errors = [];

		foreach ($record->terms as $key => $slugs) {
			$parts    = explode('.', $key, 2);
			$taxonomy = $parts[0];
			$people   = $parts[1] ?? null;
			$type     = $this->types->find($taxonomy);

			// The profiles type's own key holds every people field's credits together.
			if ($type instanceof Profiles && $people === null) {
				continue;
			}

			$field = $people ?? $this->types->classification($taxonomy)->field ?? $taxonomy;

			foreach ($slugs as $slug) {
				if (! $snapshot->has($taxonomy, $slug)) {
					$errors[] = new Violation($field, sprintf('"%s" has no %s entry, so the site leaves it out; add one, or run content:terms.', $slug, $type->labels->item ?? $taxonomy));
				}
			}
		}

		return $errors;
	}
}
