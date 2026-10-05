<?php

/**
 * Preview controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Preview;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Content\ContentRepository;
use Blush\Content\Http\ContentPage;
use Blush\Content\Http\PageKind;
use Blush\Content\Http\PageRenderer;
use Blush\Http\Response;
use Blush\Http\Status;

/**
 * Shows an entry, whatever its status, to anyone with a genuine,
 * unexpired preview link (D-226). The entry renders with the theme as
 * its own page would: a routed type's entry as a single, any other as a
 * page.
 *
 * The answer is never cached or indexed, and sends no referrer, so the
 * link doesn't leak to sites the preview links to. A bad or expired link
 * gets a plain 403 that says so.
 */
final readonly class PreviewController
{
	/**
	 * Headers every preview answer carries.
	 */
	private const array HEADERS = [
		'Cache-Control'   => 'no-store',
		'X-Robots-Tag'    => 'noindex, nofollow',
		'Referrer-Policy' => 'no-referrer'
	];

	public function __construct(
		private PreviewLinks $links,
		private ContentRepository $content,
		private PageRenderer $renderer
	) {}

	public function __invoke(ServerRequestInterface $request): ResponseInterface
	{
		$query     = $request->getQueryParams();
		$path      = $query['entry'] ?? null;
		$expires   = $query['expires'] ?? null;
		$signature = $query['signature'] ?? null;

		$valid = is_string($path) && is_string($expires) && ctype_digit($expires) && is_string($signature)
			&& $this->links->isValid($path, (int) $expires, $signature);

		$entry = $valid ? $this->content->findPath($path) : null;

		if ($entry === null) {
			return Response::text(
				'This preview link has expired or isn\'t valid. Ask for a new one.',
				$valid ? Status::NotFound : Status::Forbidden,
				self::HEADERS
			);
		}

		$response = $this->renderer->render(new ContentPage(
			kind: $entry->type->hasUrls() ? PageKind::Single : PageKind::Page,
			title: $entry->title,
			entry: $entry,
			type: $entry->type
		), $request);

		foreach (self::HEADERS as $name => $value) {
			$response = $response->withHeader($name, $value);
		}

		return $response;
	}
}
