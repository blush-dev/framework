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
use Blush\Content\Type\Profiles;
use Blush\Content\Type\TypeKind;

/**
 * The view names a page tries, most specific first (see `theming.md`).
 * An entry's `template` front matter (1.x's `view`) always comes first.
 *
 * - **Single:** `single-{type}-{slug}` → `single-{type}` →
 *   (`single-terms`) → `single-{kind}` → `single`.
 * - **Collection:** `collection-{type}` → (`collection-terms`) →
 *   `collection-{kind}` → `collection`.
 *
 * `{kind}` is the type's kind: `collection`, `tree`, or `profiles`
 * (D-561), so a theme can draw every tree, say, without knowing a site's
 * type names. A type of terms (one a classify relation files entries
 * under, D-593) tries `-terms` first, as 2.x's taxonomies tried
 * `collection-taxonomy` (D-147).
 * - **Term:** `term-{taxonomy}-{slug}` → `term-{taxonomy}` → `term` →
 *   `collection`.
 * - **Date archive:** `archive-date-{type}` → `archive-date` →
 *   `collection`.
 * - **Related list** (what a type's relation with an archive word links
 *   to, D-596): `related-list-{type}-{relation}` → `related-list-{relation}`
 *   → `related-list` → `collection`.
 * - **Related** (a target's archive under it): `related-{type}-{relation}`
 *   → `related-{relation}` → `related` → `term` (`profile` for a person's
 *   archive under a credit, D-602) → `collection`.
 * - **Profile** (a profile's own page): `profile-{slug}` → `profile` →
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
	public static function forPage(ContentPage $page, bool $terms = false): self
	{
		$entry    = $page->entry;
		$type     = $page->type ?? $entry?->type;
		$name     = $type->name ?? 'page';
		$kind     = $type?->kind()->value ?? TypeKind::Tree->value;

		$names = match ($page->kind) {
			PageKind::Welcome    => ['welcome'],
			PageKind::Home       => ['home', ...self::forKind($page->base ?? PageKind::Page, $name, $kind, $entry, $terms)],
			PageKind::RelatedList => ["related-list-{$name}-{$page->relation?->name}", "related-list-{$page->relation?->name}", 'related-list', 'collection'],
			PageKind::Related    => ["related-{$name}-{$page->relation?->name}", "related-{$page->relation?->name}", 'related', $page->target?->type instanceof Profiles ? 'profile' : 'term', 'collection'],
			PageKind::Profile    => [...($entry === null ? [] : ["profile-{$entry->slug}"]), 'profile', 'collection'],
			default              => self::forKind($page->kind, $name, $kind, $entry, $terms)
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
	 * Returns the names for a kind of page, with the type's name, the
	 * type's kind (`$typeKind`, D-561), and whether its entries are terms.
	 *
	 * @return list<string>
	 */
	private static function forKind(PageKind $kind, string $type, string $typeKind, ?Entry $entry, bool $terms = false): array
	{
		$slug = $entry?->slug;
		$both = static fn (string $prefix): array => $terms ? ["{$prefix}-terms", "{$prefix}-{$typeKind}"] : ["{$prefix}-{$typeKind}"];

		return match ($kind) {
			PageKind::Collection => ["collection-{$type}", ...$both('collection'), 'collection'],
			PageKind::Term       => [...($slug === null ? [] : ["term-{$type}-{$slug}"]), "term-{$type}", 'term', 'collection'],
			PageKind::Date       => ["archive-date-{$type}", 'archive-date', 'collection'],
			default              => [...($slug === null ? [] : ["single-{$type}-{$slug}"]), "single-{$type}", ...$both('single'), 'single']
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
