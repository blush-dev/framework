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

use Blush\Auth\AccountStore;
use Blush\Auth\Accounts;
use Blush\Auth\AuthException;
use Blush\Console\Attributes\Argument;
use Blush\Console\Attributes\Command;
use Blush\Console\ExitCode;
use Blush\Console\Output;
use Blush\Console\Prompt;

/**
 * Links an account to the author entry it writes as, or unlinks it when
 * no author is given (D-217). Entries crediting that author become the
 * account's own, and so does the author's entry, which it offers to
 * create when there's none (D-259).
 */
#[Command('account:author', 'Link an admin account to an author entry, or unlink it.')]
final readonly class SetAccountAuthor
{
	public function __construct(
		private AccountStore $store,
		private Accounts $accounts
	) {}

	public function __invoke(
		Output $output,
		Prompt $prompt,
		#[Argument('The account\'s username.')] string $username,
		#[Argument('The author entry\'s slug; leave it out to unlink.')] ?string $author = null
	): ExitCode {
		try {
			$account = $this->store->find($username) ?? throw new AuthException(sprintf('There\'s no account named "%s".', $username));
			$account = $this->accounts->setAuthor($account, $author);
		} catch (AuthException $e) {
			$output->error($e->getMessage());

			return ExitCode::Failure;
		}

		if ($author === null) {
			$output->success(sprintf('"%s" isn\'t linked to an author now.', $username));

			return ExitCode::Success;
		}

		$output->success(sprintf('"%s" writes as the "%s" author now.', $username, $author));

		return AuthorPage::offer($output, $prompt, $this->accounts, $author);
	}
}
