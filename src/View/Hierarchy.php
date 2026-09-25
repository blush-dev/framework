<?php

/**
 * Template hierarchy.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View;

use Blush\Content\Entry\Entry;
use Blush\Content\Http\ContentPage;
use Blush\Content\Http\PageKind;

/**
 * The view names a page tries, most specific first (see `theming.md`).
 * An entry's `template` front matter (1.x's `view`) always comes first.
 *
 * - **Single:** `single-{type}-{slug}` → `single-{type}` → `single`.
 * - **Collection:** `collection-{type}` → `collection`.
 * - **Term:** `term-{taxonomy}-{slug}` → `term-{taxonomy}` → `term` →
 *   `collection`.
 * - **Date archive:** `archive-date-{type}` → `archive-date` →
 *   `collection`.
 * - **Home:** `home`, then the hierarchy of what it shows.
 * - **Errors:** `error-{status}` → `error`.
 * - **Welcome:** `welcome`.
 *
 * 1.x's view names (`collection-datetime`, `single-home`, …) aren't
 * candidates; a 1.x theme renames its views (D-104).
 */
final readonly class Hierarchy
{
	/**
	 * @param list<string> $names
	 */
	public function __construct(public array $names)
	{}

	/**
	 * Returns a content page's hierarchy.
	 */
	public static function forPage(ContentPage $page): self
	{
		$entry = $page->entry;
		$type  = ($page->type ?? $entry?->type)->name ?? 'page';

		$names = match ($page->kind) {
			PageKind::Welcome    => ['welcome'],
			PageKind::Home       => ['home', ...self::forKind($page->base ?? PageKind::Page, $type, $entry)],
			default              => self::forKind($page->kind, $type, $entry)
		};

		return self::withTemplates($entry, $names);
	}

	/**
	 * Returns an error page's hierarchy.
	 */
	public static function forError(int $status, ?Entry $entry = null): self
	{
		return self::withTemplates($entry, ["error-{$status}", 'error']);
	}

	/**
	 * Returns the names for a kind of page.
	 *
	 * @return list<string>
	 */
	private static function forKind(PageKind $kind, string $type, ?Entry $entry): array
	{
		$slug = $entry?->slug;

		return match ($kind) {
			PageKind::Collection => ["collection-{$type}", 'collection'],
			PageKind::Term       => [...($slug === null ? [] : ["term-{$type}-{$slug}"]), "term-{$type}", 'term', 'collection'],
			PageKind::Date       => ["archive-date-{$type}", 'archive-date', 'collection'],
			default              => [...($slug === null ? [] : ["single-{$type}-{$slug}"]), "single-{$type}", 'single']
		};
	}

	/**
	 * Puts an entry's own templates first and drops duplicates.
	 *
	 * @param list<string> $names
	 */
	private static function withTemplates(?Entry $entry, array $names): self
	{
		return new self(array_values(array_unique([...($entry?->templates() ?? []), ...$names])));
	}
}
