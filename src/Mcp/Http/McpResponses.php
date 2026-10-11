<?php

/**
 * MCP responses.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Mcp\Http;

use Psr\Http\Message\ResponseInterface;
use Blush\Http\Response;
use Blush\Http\Status;

/**
 * The MCP server's and OAuth's answers (D-751, D-752): JSON, never kept
 * by a cache, and open to apps on other sites (CORS), since a bearer
 * token, never a cookie, is what proves who's asking.
 */
final class McpResponses
{
	/**
	 * The headers every answer carries.
	 *
	 * @return array<string, string>
	 */
	public static function headers(): array
	{
		return [
			'Cache-Control'                 => 'no-store',
			'Access-Control-Allow-Origin'   => '*',
			'Access-Control-Expose-Headers' => 'WWW-Authenticate, Mcp-Session-Id'
		];
	}

	/**
	 * @param array<string, mixed>|object $data
	 * @param array<string, string>       $headers
	 */
	public static function json(array|object $data, Status $status = Status::Ok, array $headers = []): ResponseInterface
	{
		return Response::json($data, $status, [...self::headers(), ...$headers]);
	}

	/**
	 * An answer with nothing in it.
	 *
	 * @param array<string, string> $headers
	 */
	public static function empty(Status $status, array $headers = []): ResponseInterface
	{
		return Response::text('', $status, [...self::headers(), ...$headers]);
	}

	/**
	 * Answers a CORS preflight.
	 */
	public static function preflight(string $methods): ResponseInterface
	{
		return self::empty(Status::NoContent, [
			'Access-Control-Allow-Methods' => $methods,
			'Access-Control-Allow-Headers' => 'Authorization, Content-Type, Accept, MCP-Protocol-Version, Mcp-Session-Id',
			'Access-Control-Max-Age'       => '86400'
		]);
	}
}
