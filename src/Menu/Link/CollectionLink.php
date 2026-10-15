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
use Blush\Translation\Messages;
use Blush\Translation\Text;

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
		private readonly AppConfig $app,
		Messages $messages
	) {
		parent::__construct($messages);
	}

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
	public function validate(string $key, mixed $value, array $item): ?Text
	{
		return is_string($value) && preg_match('/^[a-z][a-z0-9_]*$/', trim($value)) === 1
			? null
			: $this->messages->t('menu.collection_link.invalid', '"{link}" must be a content type name, such as "post".', link: $key);
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
			throw new UnresolvedLink((string) $this->messages->t('menu.collection_link.missing', 'No public content type "{type}".', type: $name));
		}

		// On a translated page, the language's listing when it has a
		// landing page or entries there (D-463).
		$language = $this->app->languages->forLocale($locale)?->code;
		$landing  = $language === null ? null : $this->content->named($name, '', $language);
		$listed   = $language !== null && ($landing?->isLive() === true || $this->content->query()->type($type->listedType())->language($language)->count() > 0);
		$landing  = $listed ? $landing : $this->content->named($name, '');
		$url      = $this->urls->collection($type, 1, $listed ? $language : null) ?? throw new UnresolvedLink((string) $this->messages->t('menu.collection_link.no_url', 'The "{type}" type has no collection URL.', type: $name));

		return new LinkTarget($url, $landing !== null && $landing->isLive() ? $landing->title : '');
	}
}
