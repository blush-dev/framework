<?php

/**
 * API error.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Api;

use RuntimeException;
use Blush\Core\BlushException;
use Blush\Http\Status;

/**
 * A request the API can't answer, sent as `{ "error": { "code",
 * "message" } }` with its status (D-737). The code is the contract;
 * the message may change.
 */
final class ApiError extends RuntimeException implements BlushException
{
	public function __construct(
		public readonly string $error,
		string $message,
		public readonly Status $status
	) {
		parent::__construct($message);
	}

	/**
	 * Nothing is at the address, or no record has the id.
	 */
	public static function notFound(string $message): self
	{
		return new self('not_found', $message, Status::NotFound);
	}

	/**
	 * A query parameter is unknown or has a value it can't take.
	 */
	public static function invalidQuery(string $message): self
	{
		return new self('invalid_query', $message, Status::BadRequest);
	}

	/**
	 * Returns the error as it's sent.
	 *
	 * @return array{error: array{code: string, message: string}}
	 */
	public function toArray(): array
	{
		return ['error' => ['code' => $this->error, 'message' => $this->getMessage()]];
	}
}
