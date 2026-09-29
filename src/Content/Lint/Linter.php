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
use Blush\Content\Schema\Severity;
use Blush\Content\Schema\Violation;
use Blush\Content\Source\ContentSource;
use Blush\Content\Source\UnreadableSource;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\Taxonomy;

/**
 * Checks every content file, as `content:lint` reports it. It reads the
 * files fresh (not the index) and reports:
 *
 * - errors: files that can't be parsed, front matter values that don't
 *   fit their fields, and `collection` query arguments that don't work;
 * - warnings: two files claiming one entry (`about.md` next to
 *   `about/index.md`);
 * - notices: undeclared keys and 1.x aliases (D-081), and terms that are
 *   referenced but have no file, which become virtual terms.
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
		private ContentTypes $types
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
				$parsed = $this->builder->build($file, $this->source->read($file->path));

				$records[]               = $parsed->record;
				$violations[$file->path] = [...$parsed->violations, ...$this->checkCollection($parsed->record)];
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
			foreach ($this->missingTerms($snapshot, $record) as $violation) {
				$violations[$record->id][] = $violation;
			}
		}

		return new LintReport(count($files), $violations);
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
			$parsed = $this->builder->build($file, $this->source->read($path));

			return [...$parsed->violations, ...$this->checkCollection($parsed->record)];
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
	 * Returns notices for terms the entry references that have no file.
	 *
	 * @return list<Violation>
	 */
	private function missingTerms(IndexSnapshot $snapshot, IndexRecord $record): array
	{
		$notices = [];

		foreach ($record->terms as $taxonomy => $slugs) {
			$type  = $this->types->find($taxonomy);
			$field = $type instanceof Taxonomy ? $type->field : $taxonomy;

			foreach ($slugs as $slug) {
				if ($snapshot->find($record->locale, $taxonomy, $slug) === null) {
					$notices[] = new Violation($field, sprintf('"%s" has no %s entry; a virtual term stands in.', $slug, $taxonomy), Severity::Notice);
				}
			}
		}

		return $notices;
	}
}
