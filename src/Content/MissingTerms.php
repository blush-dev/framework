<?php

/**
 * Missing terms.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content;

use Closure;
use Psr\Clock\ClockInterface;
use Blush\Content\Index\ContentIndex;
use Blush\Content\Index\Indexer;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Writer\EntryChanges;
use Blush\Content\Writer\WriteException;
use Blush\Core\AppConfig;

/**
 * Every term and profile is a file (D-584): a slug entries name in a
 * taxonomy or credit as a person, with no file, is left out of the site
 * and is a `content:lint` error. `content:terms` and Site Health write
 * each a published file (D-478), titled as entries first wrote it
 * (`Book Reviews`), or by its slug, which is what the site showed for
 * it before.
 *
 * It reads the index, brought up to date first.
 */
final readonly class MissingTerms
{
	public function __construct(
		private ContentIndex $index,
		private Indexer $indexer,
		private ContentTypes $types,
		private Entries $content,
		private ClockInterface $clock,
		private AppConfig $app
	) {}

	/**
	 * Returns the slugs named with no file, with the title each would
	 * get, by type name, then by slug, each in order.
	 *
	 * @return array<string, array<string, string>>
	 */
	public function report(): array
	{
		$this->indexer->index();

		$snapshot = $this->index->snapshot();
		$missing  = [];

		foreach ($this->types->all() as $type) {
			if (! $this->types->isTermType($type->name)) {
				continue;
			}

			foreach (array_keys($snapshot->terms[$type->name] ?? []) as $slug) {
				$slug = (string) $slug;

				if (! $snapshot->has($type->name, $slug)) {
					$missing[$type->name][$slug] = $snapshot->labels[$type->name][$slug] ?? $slug;
				}
			}

			if (isset($missing[$type->name])) {
				ksort($missing[$type->name], SORT_NATURAL);
			}
		}

		ksort($missing);

		return $missing;
	}

	/**
	 * Returns how many entries name each slug, by type name, then by slug,
	 * as the index has them now (after `report()`).
	 *
	 * @return array<string, array<string, int>>
	 */
	public function namedBy(): array
	{
		return array_map(static fn (array $slugs): array => array_map(count(...), $slugs), $this->index->snapshot()->terms);
	}

	/**
	 * Writes a file for every slug named with no file, or only for the
	 * types `$allowed` passes (by name), such as those an account may
	 * create and publish entries of, and only the `{type}/{slug}`s in
	 * `$only` when it's given (one row on Site Health, D-612).
	 *
	 * @param ?Closure(string): bool $allowed
	 * @param ?list<string>          $only
	 */
	public function create(?Closure $allowed = null, ?array $only = null): CreatedEntries
	{
		$created = [];
		$failed  = [];
		$now     = $this->clock->now()->setTimezone($this->app->timezone());

		foreach ($this->report() as $name => $slugs) {
			$type = $this->types->find($name);

			if ($type === null || ($allowed !== null && ! $allowed($name))) {
				continue;
			}

			foreach ($slugs as $slug => $title) {
				if ($only !== null && ! in_array("{$name}/{$slug}", $only, true)) {
					continue;
				}

				try {
					$created["{$name}/{$slug}"] = $this->content->create($type, $slug, new EntryChanges(set: [
						'title'     => $title,
						'published' => $now->format('Y-m-d H:i:s P')
					]), null, $now)->path;
				} catch (WriteException $e) {
					$failed["{$name}/{$slug}"] = $e->getMessage();
				}
			}
		}

		return new CreatedEntries($created, $failed);
	}
}
