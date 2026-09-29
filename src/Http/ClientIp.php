<?php

/**
 * Client IP address.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Http;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Reads the client's IP address from a request: the connection's address
 * (`REMOTE_ADDR`), never a header a client can set. Behind a proxy that's
 * the proxy's address, until trusted proxies are supported.
 */
final class ClientIp
{
	/**
	 * Returns the address, or `''` when the request has none (the CLI or a
	 * test).
	 */
	public static function of(ServerRequestInterface $request): string
	{
		$address = $request->getServerParams()['REMOTE_ADDR'] ?? '';

		return is_string($address) && filter_var($address, FILTER_VALIDATE_IP) !== false ? $address : '';
	}
}
