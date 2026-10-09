<?php

/**
 * Term menu link.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Menu\Link;

use Override;
use Blush\Content\Entries;
use Blush\Content\Entry\Entry;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Type\ContentTypes;
use Blush\Core\AppConfig;
use Blush\Support\Uuid;

/**
 * Links to a term's archive: `term: category/art` (D-593), with its id
 * in `ref` (D-676), which wins when it finds the term. The term's title
 * is the label.
 */
final class TermLink extends MenuLink implements LinksEntry
{
	public function __construct(
		private readonly Entries $content,
		private readonly ContentTypes $types,
		private readonly ContentUrls $urls,
		private readonly AppConfig $app
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function itemSchema(string $key, array $text): array
	{
		return [
			$key       => [
				'type'        => 'string',
				'pattern'     => '^[a-z][a-z0-9_]*/[^/]+$',
				'description' => 'Links to a term\'s archive, as {type}/{slug}, such as category/art. Its title is the label.'
			],
			self::REF  => self::refSchema()
		];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function keys(): array
	{
		return [self::REF];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function validate(mixed $value, array $item): ?string
	{
		if (! is_string($value) || preg_match('#^[a-z][a-z0-9_]*/[^/]+$#', trim($value)) !== 1) {
			return 'must be a term as {type}/{slug}, such as "category/art".';
		}

		return self::validRef($item) ? null : 'has a "ref" that isn\'t a term\'s id.';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function resolve(string $value, array $item, string $locale): LinkTarget
	{
		$term          = $this->entry($value, $item);
		[$name, $slug] = $term === null ? explode('/', trim($value), 2) : [$term->type->name, $term->slug];

		$taxonomy = $this->types->find($name);

		if ($taxonomy === null || ! $this->types->hasTermPages($name)) {
			throw new UnresolvedLink(sprintf('"%s" isn\'t a type of terms with pages.', $name));
		}

		if ($term === null || ! $term->isPublished() || ! $term->isRoutable()) {
			throw new UnresolvedLink(sprintf('No published term "%s".', $value));
		}

		// On a translated page, its translation when it has one (D-463).
		$language    = $this->app->languages->forLocale($locale)?->code;
		$translation = $language === null ? null : $this->content->term($name, $slug, $language);
		$translated  = $translation !== null && $translation->language === $language && $translation->isPublished() && $translation->isRoutable();
		$term        = $translated ? $translation : $term;

		$url = $this->urls->term($taxonomy, $slug, 1, $translated ? $language : null) ?? throw new UnresolvedLink(sprintf('The term "%s" has no URL.', $value));

		return new LinkTarget($url, $term->title);
	}

	/**
	 * Returns the term, the original when `ref` names a translation, since
	 * a term's translations are found by the original's slug.
	 *
	 * @inheritDoc
	 */
	#[Override]
	public function entry(string $value, array $item): ?Entry
	{
		$ref  = $item[self::REF] ?? null;
		$term = is_string($ref) && Uuid::isValid($ref) ? $this->content->find($ref) : null;

		if ($term !== null) {
			return $term->originalId === null ? $term : $this->content->find($term->originalId) ?? $term;
		}

		[$name, $slug] = [...explode('/', trim($value), 2), ''];

		return $slug === '' ? null : $this->content->term($name, $slug);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function value(Entry $entry): string
	{
		return "{$entry->type->name}/{$entry->slug}";
	}
}
