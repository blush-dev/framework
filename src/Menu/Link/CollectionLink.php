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
use Blush\Content\ContentRepository;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Type\ContentTypes;

/**
 * Links to a type's collection: `collection: post`. Its landing page's
 * title is the label, when it has a published one; otherwise the item
 * needs its own `label`.
 */
final class CollectionLink extends MenuLink
{
	public function __construct(
		private readonly ContentRepository $content,
		private readonly ContentTypes $types,
		private readonly ContentUrls $urls
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

		$url     = $this->urls->collection($type) ?? throw new UnresolvedLink(sprintf('The "%s" type has no collection URL.', $name));
		$landing = $this->content->named($name, '', $locale) ?? $this->content->named($name, '');

		return new LinkTarget($url, $landing !== null && $landing->isPublished() ? $landing->title : '');
	}
}
