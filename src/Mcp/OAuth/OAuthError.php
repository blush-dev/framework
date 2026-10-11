<?php

/**
 * OAuth error.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Mcp\OAuth;

use RuntimeException;
use Blush\Core\BlushException;

/**
 * An OAuth request that can't be granted (D-752), with OAuth's error
 * code (`invalid_request`, `invalid_client`, `invalid_grant`,
 * `access_denied`, …) and a description, and whether the client's
 * redirect address can be trusted with it: an error about the client or
 * its address is shown to the person, never sent there.
 */
final class OAuthError extends RuntimeException implements BlushException
{
	public function __construct(
		public readonly string $error,
		string $description,
		public readonly bool $redirectable = true,
		public readonly int $status = 400
	) {
		parent::__construct($description);
	}

	public static function request(string $description): self
	{
		return new self('invalid_request', $description);
	}

	public static function client(string $description): self
	{
		return new self('invalid_client', $description, redirectable: false, status: 401);
	}

	public static function grant(string $description): self
	{
		return new self('invalid_grant', $description);
	}

	public static function scope(string $description): self
	{
		return new self('invalid_scope', $description);
	}

	/**
	 * An error the token and registration endpoints answer as JSON.
	 *
	 * @return array{error: string, error_description: string}
	 */
	public function toArray(): array
	{
		return ['error' => $this->error, 'error_description' => $this->getMessage()];
	}
}
