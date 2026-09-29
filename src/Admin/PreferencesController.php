<?php

/**
 * Admin preferences controller.
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
use Blush\Auth\ColorScheme;
use Blush\Http\Response;
use Blush\Http\Status;

/**
 * Answers `PATCH {path}/api/preferences` (D-235): changes the signed-in
 * account's own preferences, which any account may do, and answers with
 * all of them. Today that's `colorScheme` (`system`, `light`, or
 * `dark`); preferences not sent are left as they are.
 */
final readonly class PreferencesController
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

		if (! is_array($input)) {
			return self::json(['error' => 'Send the preferences to change as a JSON object.'], Status::BadRequest);
		}

		$preferences = $account->preferences;

		if (array_key_exists('colorScheme', $input)) {
			$scheme = is_string($input['colorScheme']) ? ColorScheme::tryFrom($input['colorScheme']) : null;

			if ($scheme === null) {
				return self::json(['error' => '"colorScheme" must be system, light, or dark.'], Status::BadRequest);
			}

			$preferences = $preferences->withColorScheme($scheme);
		}

		$account = $this->accounts->setPreferences($account, $preferences);

		return self::json(['preferences' => $account->preferences->toArray()]);
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private static function json(array $data, Status $status = Status::Ok): ResponseInterface
	{
		return Response::json($data, $status, ['Cache-Control' => 'no-store']);
	}
}
