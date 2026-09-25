<?php

/**
 * Collection controller.
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
use Blush\Http\NotFound;

/**
 * Serves a type's collection (`{type}.collection` and
 * `{type}.collection.paged`): the type's landing page (its folder's
 * `index` file) with the entries it collects, queried with the type's
 * `collection` arguments and the landing page's own.
 */
final class CollectionController extends ContentController
{
	/**
	 * @throws NotFound
	 * @throws InvalidQuery
	 */
	public function __invoke(ServerRequestInterface $request, string $type, int $page = 1): ResponseInterface
	{
		$contentType = $this->type($type);

		if ($page === 1 && self::isPaged($request)) {
			return self::redirect($request, $this->urls->collection($contentType) ?? '/');
		}

		return $this->renderer->render($this->collectionPage($contentType, $page, PageKind::Collection), $request);
	}
}
