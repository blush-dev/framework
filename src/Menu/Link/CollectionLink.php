<?php

/**
 * Collection menu link.
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
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Type\ContentTypes;
use Blush\Core\AppConfig;

/**
 * Links to a type's collection: `collection: post`. Its landing page's
 * title is the label, when it has a published one; otherwise the item
 * needs its own `label`.
 */
final class CollectionLink extends MenuLink
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
		return [$key => [
			'type'        => 'string',
			'pattern'     => '^[a-z][a-z0-9_]*$',
			'description' => 'Links to a content type\'s listing, such as post. Its landing page\'s title is the label, if it has one.'
		]];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function validate(mixed $value, array $item): ?string
	{
		return is_string($value) && preg_match('/^[a-z][a-z0-9_]*$/', trim($value)) === 1
			? null
			: 'must be a content type name, such as "post".';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function resolve(string $value, array $item, string $locale): LinkTarget
	{
		$name = trim($value);
		$type = $this->types->find($name);

		if ($type === null || ! $type->public) {
			throw new UnresolvedLink(sprintf('No public content type "%s".', $name));
		}

		// On a translated page, the language's listing when it has a
		// landing page or entries there (D-463).
		$language = $this->app->languages->forLocale($locale)?->code;
		$landing  = $language === null ? null : $this->content->named($name, '', $language);
		$listed   = $language !== null && ($landing?->isPublished() === true || $this->content->query()->type($type->listedType())->language($language)->count() > 0);
		$landing  = $listed ? $landing : $this->content->named($name, '');
		$url      = $this->urls->collection($type, 1, $listed ? $language : null) ?? throw new UnresolvedLink(sprintf('The "%s" type has no collection URL.', $name));

		return new LinkTarget($url, $landing !== null && $landing->isPublished() ? $landing->title : '');
	}
}
