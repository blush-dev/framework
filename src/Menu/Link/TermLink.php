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
use Blush\Content\ContentRepository;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\Taxonomy;
use Blush\Core\AppConfig;

/**
 * Links to a taxonomy term's archive: `term: category/art`. The term's
 * title is the label (a term without a file has one too).
 */
final class TermLink extends MenuLink
{
	public function __construct(
		private readonly ContentRepository $content,
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
			'pattern'     => '^[a-z][a-z0-9_]*/[^/]+$',
			'description' => 'Links to a taxonomy term\'s archive, as {taxonomy}/{slug}, such as category/art. Its title is the label.'
		]];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function validate(mixed $value, array $item): ?string
	{
		return is_string($value) && preg_match('#^[a-z][a-z0-9_]*/[^/]+$#', trim($value)) === 1
			? null
			: 'must be a term as {taxonomy}/{slug}, such as "category/art".';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function resolve(string $value, array $item, string $locale): LinkTarget
	{
		[$name, $slug] = explode('/', trim($value), 2);

		$taxonomy = $this->types->find($name);

		if (! $taxonomy instanceof Taxonomy) {
			throw new UnresolvedLink(sprintf('"%s" isn\'t a taxonomy.', $name));
		}

		$term = $this->content->term($name, $slug);

		if ($term === null || ! $term->isPublished() || ! $term->isRoutable()) {
			throw new UnresolvedLink(sprintf('No published term "%s".', $value));
		}

		// On a translated page, its translation when it has one (D-463).
		$language    = $this->app->languages->forLocale($locale)?->code;
		$translation = $language === null ? null : $this->content->term($name, $slug, $language);
		$translated  = $translation !== null && ! $translation->isVirtual() && $translation->language === $language && $translation->isPublished() && $translation->isRoutable();
		$term        = $translated ? $translation : $term;

		$url = $this->urls->term($taxonomy, $slug, 1, $translated ? $language : null) ?? throw new UnresolvedLink(sprintf('The term "%s" has no URL.', $value));

		return new LinkTarget($url, $term->title);
	}
}
