<?php

/**
 * Taxonomy term controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Content\Query\InvalidQuery;
use Blush\Content\Type\Taxonomy;
use Blush\Http\NotFound;

/**
 * Serves a taxonomy term's archive (`{type}.single` and
 * `{type}.single.paged` of a taxonomy): the term, which has a file
 * (D-584), with the entries that reference it. The entries are those of the
 * taxonomy's `types` (every type when empty), listed by its `termListing`
 * and the term's own `collection` front matter.
 *
 * A hierarchical taxonomy's `{name}` is the term's path
 * (`web/web-design/css`, D-260): the term is its last slug, and any other
 * path to it, such as a flat 1.x URL or one from before it moved,
 * redirects to its own.
 */
final class TermController extends ContentController
{
	/**
	 * @throws NotFound
	 * @throws InvalidQuery
	 */
	public function __invoke(ServerRequestInterface $request, string $type, string $name, int $page = 1, ?string $language = null): ResponseInterface
	{
		$taxonomy = $this->type($type);
		$term     = $taxonomy instanceof Taxonomy ? $this->visible($this->content->term($taxonomy->name, basename($name), $language)) : null;

		if ($term === null) {
			throw new NotFound(sprintf('There is no "%s" term "%s".', $type, $name));
		}

		// Entries name a term by its original's slug (D-455).
		$slug = $language === null ? $term->slug : $this->urls->originalKey($term);

		if ($page === 1 && self::isPaged($request)) {
			return self::redirect($request, $this->urls->term($taxonomy, $slug, 1, $language) ?? '/');
		}

		$url = $this->urls->term($taxonomy, $slug, $page, $language);

		if ($url !== null && $url !== $request->getUri()->getPath()) {
			return self::redirect($request, $url);
		}

		$query = $this->query($taxonomy->termArguments(), self::collectionArguments($term))->whereTerm($taxonomy->name, $slug)->language($language);

		return $this->renderer->render(new ContentPage(
			kind: PageKind::Term,
			title: $term->title,
			entry: $term,
			type: $taxonomy,
			entries: $this->paginate($query, $page),
			pageUrl: fn (int $number): ?string => $this->urls->term($taxonomy, $slug, $number, $language),
			alternateUrl: function (string $code) use ($taxonomy, $slug, $page, $query): ?string {
				$term = $page === 1 ? $this->visible($this->content->term($taxonomy->name, $slug, $code)) : null;

				return $term?->language === $code || $this->listsPage($query, $page, $code) ? $this->urls->term($taxonomy, $slug, $page, $code) : null;
			}
		), $request);
	}
}
