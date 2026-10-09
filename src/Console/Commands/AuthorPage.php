<?php

/**
 * Offers to create an account's author page.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console\Commands;

use Blush\Auth\Accounts;
use Blush\Auth\AuthException;
use Blush\Console\ExitCode;
use Blush\Console\InvalidInput;
use Blush\Console\Output;
use Blush\Console\Prompt;

/**
 * What `account:add` and `account:author` do once an account is linked
 * to an author (D-259): when the author has no entry, offer to create
 * it, asking for the public name. Declined (the default when the console
 * isn't interactive), the account stays linked and bylines show the slug
 * until someone creates the entry.
 */
final class AuthorPage
{
	/**
	 * @throws InvalidInput When an answer can't be read.
	 */
	public static function offer(Output $output, Prompt $prompt, Accounts $accounts, string $author): ExitCode
	{
		if ($accounts->hasAuthorPage($author)) {
			return ExitCode::Success;
		}

		if (! $prompt->confirm(sprintf('The "%s" author has no page for its name and bio yet. Create it?', $author), $prompt->interactive)) {
			$output->warning(sprintf('Bylines show "%s" until the author has a page; the account can create it from Your profile in the admin.', $author));

			return ExitCode::Success;
		}

		try {
			$page = $accounts->createAuthorPage($author, trim($prompt->ask('Public name:', $author)) ?: $author);
		} catch (AuthException $e) {
			$output->error($e->getMessage());

			return ExitCode::Failure;
		}

		$output->success(sprintf('Created the author page user/content/%s.', $page->path));

		return ExitCode::Success;
	}
}
