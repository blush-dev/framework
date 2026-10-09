<?php

/**
 * Entry menu link.
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
use Blush\Core\AppConfig;
use Blush\Support\Uuid;

/**
 * Links to an entry by type and key: `entry: page/about`, or a type's
 * landing page as `entry: blog/`, with its id in `ref` (D-676), which
 * wins when it finds the entry. The entry's title is the label, and its
 * URL follows slug and permalink changes. The entry in the page's locale
 * is used when there is one, else the site's. Drafts, scheduled, and
 * hidden entries don't resolve, so they stay out of menus until they go
 * live.
 */
final class EntryLink extends MenuLink implements LinksEntry
{
	public function __construct(
		private readonly Entries $content,
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
				'pattern'     => '^[a-z][a-z0-9_]*(/[^/].*|/)?$',
				'description' => 'Links to an entry, as {type}/{key}, such as page/about; {type}/ is its landing page. Its title is the label.'
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
		if (! is_string($value) || preg_match('#^[a-z][a-z0-9_]*(/[^/].*|/)?$#', trim($value)) !== 1) {
			return 'must be an entry as {type}/{key}, such as "page/about".';
		}

		return self::validRef($item) ? null : 'has a "ref" that isn\'t an entry\'s id.';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function resolve(string $value, array $item, string $locale): LinkTarget
	{
		$entry = $this->entry($value, $item);

		if ($entry === null) {
			throw new UnresolvedLink(sprintf('No entry "%s".', $value));
		}

		if (! $entry->isPublished()) {
			throw new UnresolvedLink(sprintf('The entry "%s" isn\'t published.', $value));
		}

		// On a translated page, its translation when it's published (D-463).
		$language    = $this->app->languages->forLocale($locale)?->code;
		$translation = $language === null ? null : $this->content->translation($entry, $language);
		$entry       = $translation !== null && $translation->isPublished() && $translation->isRoutable() ? $translation : $entry;

		$url = $this->urls->entry($entry) ?? throw new UnresolvedLink(sprintf('The entry "%s" has no URL.', $value));

		return new LinkTarget($url, $entry->title);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function entry(string $value, array $item): ?Entry
	{
		$ref = $item[self::REF] ?? null;

		if (is_string($ref) && Uuid::isValid($ref)) {
			$entry = $this->content->find($ref);

			if ($entry !== null) {
				return $entry;
			}
		}

		[$type, $key] = [...explode('/', trim($value), 2), ''];

		return $this->content->named($type, $key);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function value(Entry $entry): string
	{
		return "{$entry->type->name}/{$entry->key}";
	}
}
