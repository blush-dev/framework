<?php

/**
 * Account author command.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console\Commands;

use Blush\Auth\AccountProfiles;
use Blush\Auth\Accounts;
use Blush\Auth\AuthException;
use Blush\Console\Attributes\Argument;
use Blush\Console\Attributes\Command;
use Blush\Console\ExitCode;
use Blush\Console\InvalidInput;
use Blush\Console\Output;
use Blush\Console\Prompt;

/**
 * Links an account to its profile, the entry it writes as, or unlinks it
 * when no slug is given (D-217). Entries crediting that profile become
 * the account's own, and so does the profile. The link is kept by the
 * profile's id (D-668), so renaming the profile keeps it; a slug with no
 * profile yet makes one, a draft with the public name asked for.
 */
#[Command('account:author', 'Link an admin account to its profile, or unlink it.')]
final readonly class SetAccountAuthor
{
	public function __construct(
		private Accounts $accounts,
		private AccountProfiles $profiles
	) {}

	/**
	 * @throws InvalidInput When an answer can't be read.
	 */
	public function __invoke(
		Output $output,
		Prompt $prompt,
		#[Argument('The account\'s username.')] string $username,
		#[Argument('The profile\'s slug; leave it out to unlink.')] ?string $author = null
	): ExitCode {
		try {
			$account = $this->accounts->find($username) ?? throw new AuthException(sprintf('There\'s no account named "%s".', $username));
			$creates = $this->profiles->wouldCreate($author);
			$title   = $creates ? AddAccount::publicName($prompt, (string) $author) : null;
			$account = $this->profiles->link($account, $author, $title);
		} catch (AuthException $e) {
			$output->error($e->getMessage());

			return ExitCode::Failure;
		}

		if ($author === null) {
			$output->success(sprintf('"%s" isn\'t linked to a profile now.', $username));

			return ExitCode::Success;
		}

		$output->success(sprintf('"%s" writes as the "%s" profile now%s.', $username, $author, $creates ? ', a draft until it\'s published in the admin' : ''));

		return ExitCode::Success;
	}
}
