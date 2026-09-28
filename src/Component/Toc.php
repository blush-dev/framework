<?php

/**
 * Table of contents component.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Component;

use Override;

/**
 * A table of contents for the entry it's in (D-175, D-183):
 * `::toc[On this page]`. It lists the entry's headings from level `min`
 * to `max` (2 and 3 by default), nested by level, each linking to its
 * heading. The Markdown layer gives it the outline as `headings`
 * (`CollectOutline`); used from a template, pass `headings` yourself.
 * Nothing renders when there are no headings in range.
 *
 * `$items` is the tree: each item's `text`, `id`, and `children`.
 */
final class Toc extends Component
{
	/**
	 * @inheritDoc
	 */
	public const ComponentContent CONTENT = ComponentContent::Text;

	/**
	 * The headings in range, nested.
	 *
	 * @var list<array{text: string, id: string, children: list<mixed>}>
	 */
	public readonly array $items;

	/**
	 * @param list<array{level: int, text: string, id: string}> $headings
	 */
	public function __construct(
		public readonly int $min = 2,
		public readonly int $max = 3,
		public readonly array $headings = []
	) {
		$low  = max(1, min(6, min($min, $max)));
		$high = max(1, min(6, max($min, $max)));

		$inRange = array_values(array_filter(
			$headings,
			static fn (array $heading): bool => $heading['level'] >= $low && $heading['level'] <= $high
		));

		$index       = 0;
		$this->items = $inRange === [] ? [] : self::branch($inRange, $index, min(array_column($inRange, 'level')));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function shouldRender(): bool
	{
		return $this->items !== [];
	}

	/**
	 * Returns the items at a level, starting at `$index`, with deeper
	 * headings nested under the item before them. A heading deeper than
	 * the level with no item before it (an h3 before any h2) joins this
	 * level, and one that skips a level (an h4 under an h2) is nested with
	 * the level below.
	 *
	 * @param  list<array{level: int, text: string, id: string}> $headings
	 * @return list<array{text: string, id: string, children: list<mixed>}>
	 */
	private static function branch(array $headings, int &$index, int $level): array
	{
		$items = [];

		while ($index < count($headings)) {
			$heading = $headings[$index];

			if ($heading['level'] < $level) {
				break;
			}

			if ($heading['level'] > $level && $items !== []) {
				$last                     = count($items) - 1;
				$items[$last]['children'] = [...$items[$last]['children'], ...self::branch($headings, $index, $heading['level'])];

				continue;
			}

			$items[] = ['text' => $heading['text'], 'id' => $heading['id'], 'children' => []];
			$index++;
		}

		return $items;
	}
}
