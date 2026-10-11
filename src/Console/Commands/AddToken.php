<?php

/**
 * Add token command.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console\Commands;

use DateTimeImmutable;
use Blush\Auth\Accounts;
use Blush\Auth\AuthException;
use Blush\Auth\Capabilities;
use Blush\Auth\Tokens;
use Blush\Console\Attributes\Argument;
use Blush\Console\Attributes\Command;
use Blush\Console\Attributes\Option;
use Blush\Console\ExitCode;
use Blush\Console\InvalidInput;
use Blush\Console\Output;
use Blush\Console\Prompt;
use Blush\Console\Verbosity;
use Blush\Core\AppConfig;

/**
 * Makes an API token for an account (D-749), for scripts and local
 * agents, and prints it once: on its own line, so `--quiet` prints
 * nothing else. Like `account:add`, it doesn't ask who's running it:
 * whoever runs it has the site. On a terminal it asks for a name when
 * there's none.
 */
#[Command('token:add', 'Make an API token for an account.')]
final readonly class AddToken
{
	public function __construct(
		private Accounts $accounts,
		private Tokens $tokens,
		private Capabilities $capabilities,
		private AppConfig $app
	) {}

	/**
	 * @throws InvalidInput
	 */
	public function __invoke(
		Output $output,
		Prompt $prompt,
		#[Argument('The account the token acts as.')] string $username,
		#[Option('What the token is for, quoted when it has spaces; asked for when left out.')] ?string $name = null,
		#[Option('How many days it lasts: 30, 90 (the default), or 365, or "never".')] ?string $expires = null,
		#[Option('Capabilities to narrow it to, separated by commas; all the account\'s when left out.')] ?string $capabilities = null
	): ExitCode {
		$account = $this->accounts->find($username);

		if ($account === null) {
			$output->error(sprintf('There\'s no "%s" account.', $username));

			return ExitCode::Failure;
		}

		$days = match ($expires) {
			null    => Tokens::DEFAULT_LIFETIME,
			'never' => null,
			default => ctype_digit($expires) ? (int) $expires : -1
		};

		$limit = $capabilities === null ? null : array_values(array_filter(array_map(trim(...), explode(',', $capabilities)), static fn (string $item): bool => $item !== ''));

		foreach ($limit ?? [] as $capability) {
			if (preg_match(Capabilities::NAME, $capability) !== 1 || (! str_contains($capability, '*') && ! $this->capabilities->has($capability))) {
				$output->error(sprintf('"%s" isn\'t a capability.', $capability));

				return ExitCode::Failure;
			}
		}

		try {
			$name ??= $prompt->ask('Name', null, static fn (string $value): ?string => trim($value) === '' ? 'Say what the token is for.' : null, 'Astro front end');

			[$token, $text] = $this->tokens->make($account, $name, $limit, $days);
		} catch (AuthException $e) {
			$output->error($e->getMessage());

			return ExitCode::Failure;
		}

		$output->success(sprintf('Made "%s" for %s.', $token->name, $account->username));
		$output->line($text, Verbosity::Quiet);
		$output->comment($token->expires === null
			? 'Copy it now: it\'s shown once. It never expires.'
			: sprintf('Copy it now: it\'s shown once. It works until %s.', DateTimeImmutable::createFromTimestamp($token->expires)->setTimezone($this->app->timezone())->format('Y-m-d H:i T')));

		if ($account->suspended) {
			$output->warning(sprintf('%s is suspended, so the token won\'t work until it\'s reinstated.', $account->username));
		}

		return ExitCode::Success;
	}
}
