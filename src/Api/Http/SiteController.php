<?php

/**
 * API site controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Api\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Api\ApiQuery;
use Blush\Api\ApiResponses;
use Blush\Content\Routing\ContentUrls;
use Blush\Core\AppConfig;

/**
 * Answers `/site` (D-736): what a front end's `<head>` and dates need.
 * The site's name, its description, its address, its language as a
 * BCP 47 tag (`en-US`) and its locale (`en_US`), its time zone, and its
 * homepage's address. The site's email address isn't public, so it
 * isn't here.
 */
final readonly class SiteController
{
	public function __construct(
		private ApiResponses $responses,
		private AppConfig $app,
		private ContentUrls $urls
	) {}

	public function __invoke(ServerRequestInterface $request): ResponseInterface
	{
		return $this->responses->answer($request, function () use ($request): array {
			ApiQuery::from($request, []);

			return [
				'name'        => $this->app->name,
				'description' => $this->app->description,
				'url'         => $this->app->origin(),
				'language'    => str_replace('_', '-', $this->app->locale),
				'locale'      => $this->app->locale,
				'timezone'    => $this->app->timezone,
				'home'        => $this->urls->absolute($this->urls->home())
			];
		});
	}
}
