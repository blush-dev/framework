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
use Blush\Content\Index\IndexRecord;
use Blush\Content\Index\IndexSnapshot;
use Blush\Content\Index\RecordBuilder;
use Blush\Content\Parser\InvalidDocument;
use Blush\Content\Query\InvalidQuery;
use Blush\Content\Query\Query;
use Blush\Content\Source\ContentSource;
use Blush\Content\Source\UnreadableSource;
use Blush\Content\Type\Authors;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\Pages;
use Blush\Content\Routing\PageRoutes;
use Blush\Field\Severity;
use Blush\Field\Violation;
use Blush\Routing\RouteTable;
use Blush\Content\Type\Taxonomy;
use Blush\Media\MediaMetadataCheck;

/**
 * Checks every content file, as `content:lint` reports it. It reads the
 * files fresh (not the index) and reports:
 *
 * - errors: files that can't be parsed, front matter values that don't
 *   fit their fields, `collection` query arguments that don't work, and
 *   terms that are their own parent or ancestor;
 * - warnings: two files claiming one entry (`about.md` next to
 *   `about/index.md`), a term's `parent` that has no file, and a page
 *   whose address another route answers (`movie/2024.md` beside a type
 *   with date archives at `/movie/{year}`), and a directive asking for a
 *   variant its component doesn't have under the active theme
 *   (`VariantCheck`, D-266);
 * - notices: undeclared keys and 1.x aliases (D-081), and terms that are
 *   referenced but have no file, which become virtual terms.
 *
 * It also checks the media metadata files under `user/data/media`
 * (`MediaMetadataCheck`, D-293): ones that can't be read or whose values
 * don't fit, and ones whose media file is gone.
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
		private MediaMetadataCheck $media
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
				$violations[$file->path] = [...$parsed->violations, ...$this->checkCollection($parsed->record), ...$this->checkOwnParent($parsed->record), ...$this->variants->check($contents)];
			} catch (InvalidDocument | UnreadableSource $e) {
				$violations[$file->path] = [new Violation(self::FILE, $e->getMessage())];
			}

			if ($progress !== null) {
				$progress($done + 1, count($files));
			}
		}

		$snapshot = IndexSnapshot::build($records, '', 0);

		foreach ($snapshot->conflicts as $key => $ids) {
			[$locale, $type, $entryKey] = explode('/', $key, 3) + ['', '', ''];
			$winner = $snapshot->find($locale, $type, $entryKey);

			foreach ($ids as $id) {
				if ($id !== $winner) {
					$violations[$id][] = new Violation(self::FILE, sprintf('is the same entry as %s, which wins.', $winner), Severity::Warning);
				}
			}
		}

		foreach ($records as $record) {
			foreach ([...$this->missingTerms($snapshot, $record), ...$this->checkParent($snapshot, $record), ...$this->checkPageAddress($record)] as $violation) {
				$violations[$record->id][] = $violation;
			}
		}

		// Media metadata files, by their path from the site root (D-293).
		[$metadata, $described] = $this->media->check();

		return new LintReport(count($files), [...$violations, ...$described], $metadata);
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

			return [...$parsed->violations, ...$this->checkCollection($parsed->record), ...$this->checkOwnParent($parsed->record), ...$this->variants->check($contents)];
		} catch (InvalidDocument | UnreadableSource $e) {
			return [new Violation(self::FILE, $e->getMessage())];
		}
	}

	/**
	 * Checks that a `collection` front matter value is a valid query.
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

		return [];
	}

	/**
	 * Checks that a hierarchical taxonomy's term doesn't name itself as
	 * its parent.
	 *
	 * @return list<Violation>
	 */
	private function checkOwnParent(IndexRecord $record): array
	{
		$type = $this->types->find($record->type);

		return $type instanceof Taxonomy && $type->hierarchical && ($record->values['parent'] ?? null) === $record->key
			? [new Violation('parent', 'names the term itself; a term can\'t be its own parent.')]
			: [];
	}

	/**
	 * Checks that an entry's parent has a file and that following parents
	 * up never comes back to the entry. Pages are left out: a folder
	 * needn't have a page of its own.
	 *
	 * @return list<Violation>
	 */
	private function checkParent(IndexSnapshot $snapshot, IndexRecord $record): array
	{
		$type = $this->types->find($record->type);

		if (! $type instanceof Taxonomy || $record->parent === null) {
			return [];
		}

		$chain = [$record->key];
		$key   = $record->parent;

		while ($key !== null) {
			$id = $snapshot->find($record->locale, $record->type, $key);

			if ($id === null) {
				return $key === $record->parent
					? [new Violation('parent', sprintf('"%s" has no %s entry; the term is shown at the top level.', $key, $record->type), Severity::Warning)]
					: [];
			}

			if (in_array($key, $chain, true)) {
				return $key === $record->key
					? [new Violation('parent', sprintf('makes a loop: %s.', implode(' → ', [...$chain, $key])))]
					: [];
			}

			$chain[] = $key;
			$key     = $snapshot->record($id)?->parent;
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
		if (! $this->types->find($record->type) instanceof Pages || $record->key === '') {
			return [];
		}

		$route = $this->routes->find('GET', "/{$record->key}")?->route;

		return $route !== null && $route->name !== PageRoutes::SINGLE
			? [new Violation(self::FILE, sprintf('is at /%s, but the %s route answers there, so the page can\'t be reached; move the page or change the type\'s prefix.', $record->key, $route->name ?? $route->handlerName()), Severity::Warning)]
			: [];
	}

	/**
	 * Returns notices for terms the entry references that have no file,
	 * and warnings for credited authors without one (D-329).
	 *
	 * @return list<Violation>
	 */
	private function missingTerms(IndexSnapshot $snapshot, IndexRecord $record): array
	{
		$notices = [];

		foreach ($record->terms as $taxonomy => $slugs) {
			$type  = $this->types->find($taxonomy);
			$term  = $type?->termField();
			$field = $term === null ? $taxonomy : $term->name;

			foreach ($slugs as $slug) {
				if ($snapshot->find($record->locale, $taxonomy, $slug) !== null) {
					continue;
				}

				$notices[] = $type instanceof Authors
					? new Violation($field, sprintf('"%s" has no %s entry, so it has no public name or bio; add one.', $slug, $type->labels->item), Severity::Warning)
					: new Violation($field, sprintf('"%s" has no %s entry; a virtual term stands in.', $slug, $taxonomy), Severity::Notice);
			}
		}

		return $notices;
	}
}
