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
 * `{type}.single.paged` of a taxonomy): the term, real or virtual, with
 * the entries that reference it. The entries are those of the
 * taxonomy's `types` (every type when empty), listed by its `termListing`
 * and the term's own `collection` front matter.
 */
final class TermController extends ContentController
{
	/**
	 * @throws NotFound
	 * @throws InvalidQuery
	 */
	public function __invoke(ServerRequestInterface $request, string $type, string $name, int $page = 1): ResponseInterface
	{
		$taxonomy = $this->type($type);
		$term     = $taxonomy instanceof Taxonomy ? $this->visible($this->content->term($taxonomy->name, $name)) : null;

		if ($term === null) {
			throw new NotFound(sprintf('There is no "%s" term "%s".', $type, $name));
		}

		if ($page === 1 && self::isPaged($request)) {
			return self::redirect($request, $this->urls->term($taxonomy, $term->slug) ?? '/');
		}

		$query = $this->query($taxonomy->termArguments(), self::collectionArguments($term))->whereTerm($taxonomy->name, $term->slug);

		return $this->renderer->render(new ContentPage(
			kind: PageKind::Term,
			title: $term->title,
			entry: $term,
			type: $taxonomy,
			entries: $this->paginate($query, $page),
			pageUrl: fn (int $number): ?string => $this->urls->term($taxonomy, $term->slug, $number)
		), $request);
	}
}
