<?php

/**
 * Typed targets.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content;

use Psr\Clock\ClockInterface;
use Blush\Content\Relation\LinkResolver;
use Blush\Content\Relation\Relations;
use Blush\Content\Relation\EntryTargets;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Writer\EntryChanges;
use Blush\Content\Writer\WriteException;
use Blush\Core\AppConfig;
use Blush\Support\Uuid;

/**
 * Targets created as they're typed (D-596): a value an entry is given in
 * a relation with `create` (a tag), naming no entry, is written as one,
 * published and titled as it was typed (`Book Reviews`), as
 * `content:terms` writes them (D-584). The editor asks before it saves,
 * so a save the account can't create the targets for is refused.
 *
 * Only values the save adds count: a value the file already had and
 * names nothing is `content:terms`' to write, not the next save's. A
 * value naming a tree's page by path, or an id, isn't created.
 */
final readonly class TypedTargets
{
	public function __construct(
		private Relations $relations,
		private EntryTargets $targets,
		private ContentTypes $types,
		private Entries $content,
		private ClockInterface $clock,
		private AppConfig $app
	) {}

	/**
	 * Returns the values a save adds that name nothing, in relations
	 * that create their targets, as the title each gets by slug, by the
	 * relation and then the target type's name.
	 *
	 * @param  array<array-key, mixed> $before The front matter before the save.
	 * @param  array<array-key, mixed> $after  The front matter as it would be saved.
	 * @return array<string, array<string, array<string, string>>>
	 */
	public function find(string $type, string $language, array $before, array $after): array
	{
		$targets = $this->targets;
		$found   = [];

		foreach ($this->relations->for($type) as $relation) {
			if (! $relation->create || $relation->kind->isWithinType() || $relation->to === []) {
				continue;
			}

			$had = LinkResolver::values($relation->valueIn($before));

			foreach (self::raw($relation->valueIn($after)) as $typed) {
				$slug = LinkResolver::values($typed)[0] ?? '';

				if ($slug === '' || in_array($slug, $had, true) || str_contains($slug, '/') || Uuid::isValid($slug)) {
					continue;
				}

				if (array_any($relation->to, static fn (string $to): bool => $targets->find($to, $slug, $language) !== null)) {
					continue;
				}

				$found[$relation->name][$relation->to[0]][$slug] ??= trim($typed) === $slug ? $slug : trim($typed);
			}
		}

		return $found;
	}

	/**
	 * Writes each found target, published.
	 *
	 * @param array<string, array<string, array<string, string>>> $found As `find()` returns them.
	 */
	public function create(array $found): CreatedEntries
	{
		$created = [];
		$failed  = [];
		$now     = $this->clock->now()->setTimezone($this->app->timezone());

		foreach ($found as $byType) {
			foreach ($byType as $name => $slugs) {
				$type = $this->types->find($name);

				foreach ($slugs as $slug => $title) {
					if ($type === null) {
						$failed["{$name}/{$slug}"] = sprintf('There\'s no "%s" type.', $name);
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
		}

		return new CreatedEntries($created, $failed);
	}

	/**
	 * Returns a value's items as typed.
	 *
	 * @return list<string>
	 */
	private static function raw(mixed $value): array
	{
		$items = [];

		foreach (is_array($value) ? $value : [$value] as $item) {
			if (is_string($item) || is_int($item)) {
				$items[] = (string) $item;
			}
		}

		return $items;
	}
}
