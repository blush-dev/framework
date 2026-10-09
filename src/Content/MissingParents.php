<?php

/**
 * Missing parents.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content;

use Closure;
use Blush\Content\Index\ContentIndex;
use Blush\Content\Index\Indexer;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Writer\EntryChanges;
use Blush\Content\Writer\FilesystemWriter;
use Blush\Content\Writer\WriteException;
use Blush\Core\AppConfig;

/**
 * A tree's page is under its parent page by what its record says
 * (D-656), so a page kept in a folder with no page of its own (a
 * `_regions/` or `_errors/` folder, or `guide/` without a guide page) is
 * at the top of its tree, its address without the folder. `content:parents`
 * and Site Health write each missing parent page, a draft titled by its
 * folder's name, and the pages under it are back at their addresses.
 *
 * A filesystem driver's tool (D-654): only files say where a parent
 * should be. It reads the index, brought up to date first, and looks at
 * files with ids in the default language; a translation follows its
 * original.
 */
final readonly class MissingParents
{
	public function __construct(
		private ContentIndex $index,
		private Indexer $indexer,
		private ContentTypes $types,
		private Entries $content,
		private AppConfig $app
	) {}

	/**
	 * Returns the missing parent pages, with the title each would get and
	 * how many pages are kept under its folder, by type name, then by
	 * key, each in order.
	 *
	 * @return array<string, array<string, array{title: string, pages: int}>>
	 */
	public function report(): array
	{
		$this->indexer->index();

		$snapshot = $this->index->snapshot();
		$language = $this->app->languages->default->code;
		$missing  = [];

		foreach ($snapshot->records as $record) {
			$type = $this->types->find($record['type']);

			if ($type === null || ! $type->keysByFolder() || $record['id'] === null || $record['language'] !== $language || ! str_contains($record['key'], '/')) {
				continue;
			}

			$above = '';

			foreach (array_slice(explode('/', $record['key']), 0, -1) as $segment) {
				$above = ltrim("{$above}/{$segment}", '/');

				// A folder no page can be written at (`__drafts`) is left.
				if (! $snapshot->has($type->name, $above) && FilesystemWriter::isPageKey($above)) {
					$missing[$type->name][$above] ??= ['title' => StoredEntries::titleOf($segment), 'pages' => 0];
					$missing[$type->name][$above]['pages']++;
				}
			}
		}

		foreach ($missing as $name => $keys) {
			ksort($keys, SORT_NATURAL);
			$missing[$name] = $keys;
		}

		ksort($missing);

		return $missing;
	}

	/**
	 * Writes every missing parent page, or only those of the types
	 * `$allowed` passes (by name), such as those an account may create
	 * entries of, and only the `{type}/{key}`s in `$only` when it's given
	 * (one row on Site Health, D-612). A parent's own missing parents are
	 * written with it.
	 *
	 * @param ?Closure(string): bool $allowed
	 * @param ?list<string>          $only
	 */
	public function create(?Closure $allowed = null, ?array $only = null): CreatedEntries
	{
		$created = [];
		$failed  = [];

		foreach ($this->report() as $name => $keys) {
			$type = $this->types->find($name);

			if ($type === null || ($allowed !== null && ! $allowed($name))) {
				continue;
			}

			foreach ($keys as $key => $parent) {
				$key = (string) $key;

				if (($only !== null && ! in_array("{$name}/{$key}", $only, true)) || $this->content->named($name, $key) !== null) {
					continue;
				}

				try {
					$created["{$name}/{$key}"] = $this->content->createAt($type, $key, new EntryChanges(set: [
						'title'  => $parent['title'],
						'status' => Status::Draft->value
					]))->path;
				} catch (WriteException $e) {
					$failed["{$name}/{$key}"] = $e->getMessage();
				}
			}
		}

		return new CreatedEntries($created, $failed);
	}
}
