<?php

/**
 * Admin profile controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Auth\Account;
use Blush\Auth\Accounts;
use Blush\Auth\AuthException;
use Blush\Http\Response;
use Blush\Http\Status;

/**
 * Answers `PATCH {path}/api/profile` (D-322): changes the signed-in
 * account's own `name` (`null` or empty takes it away), which any
 * account may do, and answers with its `name` and `displayName`. A name
 * that's too long is a `422`.
 */
final readonly class ProfileController
{
	public function __construct(
		private Accounts $accounts
	) {}

	public function __invoke(ServerRequestInterface $request): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account) {
			return self::json(['error' => 'Sign in first.'], Status::Unauthorized);
		}

		try {
			$input = json_decode((string) $request->getBody(), true, 4, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			$input = null;
		}

		if (! is_array($input) || ! array_key_exists('name', $input) || ($input['name'] !== null && ! is_string($input['name']))) {
			return self::json(['error' => 'Send a JSON "name" (or null).'], Status::BadRequest);
		}

		try {
			$account = $this->accounts->setName($account, $input['name']);
		} catch (AuthException $e) {
			return self::json(['error' => $e->getMessage(), 'field' => 'name'], Status::UnprocessableContent);
		}

		return self::json(['name' => $account->name, 'displayName' => $this->accounts->displayName($account)]);
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private static function json(array $data, Status $status = Status::Ok): ResponseInterface
	{
		return Response::json($data, $status, ['Cache-Control' => 'no-store']);
	}
}
