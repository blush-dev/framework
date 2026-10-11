<?php

/**
 * Authorization request.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Mcp\OAuth;

use Blush\Auth\TokenScope;

/**
 * An app's request to connect, checked (D-752): the client, the address
 * to send the person back to, the app's `state`, its PKCE challenge, the
 * scope it asked for (`account` when it named none), and the resource.
 */
final readonly class AuthorizationRequest
{
	public function __construct(
		public OAuthClient $client,
		public string $redirectUri,
		public ?string $state,
		public string $challenge,
		public TokenScope $scope,
		public ?string $resource
	) {}

	/**
	 * Returns the address to send the person back to, with a query.
	 *
	 * @param array<string, string> $query
	 */
	public function redirectTo(array $query): string
	{
		$query = [...$query, ...($this->state === null ? [] : ['state' => $this->state])];

		return $this->redirectUri . (str_contains($this->redirectUri, '?') ? '&' : '?') . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
	}

	/**
	 * An error that may be sent back to the app's address, now that it's
	 * known to be the app's.
	 */
	public function error(string $error, string $description): OAuthError
	{
		return new OAuthError($error, $description);
	}
}
