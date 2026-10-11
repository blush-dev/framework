<?php

/**
 * API index controller.
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
use Blush\Api\ApiError;
use Blush\Api\ApiQuery;
use Blush\Api\ApiResponses;

/**
 * Answers the API's own address (`/api/v1`) with where each resource is,
 * any other address under it with a JSON 404, and a browser's CORS
 * preflight for any of them.
 */
final readonly class IndexController
{
	/**
	 * The resources, by name, at their paths under the API.
	 *
	 * @var array<string, string>
	 */
	public const array RESOURCES = [
		'site'      => '/site',
		'types'     => '/types',
		'entries'   => '/entries',
		'relations' => '/relations',
		'media'     => '/media',
		'menus'     => '/menus'
	];

	public function __construct(private ApiResponses $responses)
	{}

	public function index(ServerRequestInterface $request): ResponseInterface
	{
		return $this->responses->answer($request, function () use ($request): array {
			ApiQuery::from($request, []);

			return ['resources' => array_map($this->responses->url(...), self::RESOURCES)];
		});
	}

	public function missing(ServerRequestInterface $request, string $path = ''): ResponseInterface
	{
		return $this->responses->error(ApiError::notFound(sprintf('Nothing is at "%s".', $request->getUri()->getPath())));
	}

	public function preflight(string $path = ''): ResponseInterface
	{
		return $this->responses->preflight();
	}
}
