<?php

/**
 * Secret set command.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console\Commands;

use Blush\Console\Attributes\Argument;
use Blush\Console\Attributes\Command;
use Blush\Console\ExitCode;
use Blush\Console\InvalidInput;
use Blush\Console\Output;
use Blush\Console\Prompt;
use Blush\Secret\InvalidSecret;
use Blush\Secret\SecretException;
use Blush\Secret\Secrets;
use Blush\Secret\SecretStore;

/**
 * Stores a declared secret's value, encrypted (D-715), asked for without
 * showing it, so it's never in the shell's history.
 */
#[Command('secrets:set', 'Store a secret\'s value, encrypted.')]
final readonly class SetSecret
{
	public function __construct(
		private Secrets $secrets,
		private SecretStore $store
	) {}

	/**
	 * @throws InvalidInput When the console isn't interactive.
	 */
	public function __invoke(
		Output $output,
		Prompt $prompt,
		#[Argument('The secret\'s full name, such as "acme/analytics.ga_refresh_token".')] string $name
	): ExitCode {
		try {
			$definition = $this->secrets->find($name) ?? throw new InvalidSecret(sprintf('"%s" isn\'t a declared secret; "secrets:list" lists them.', $name));

			$this->store->set($definition, $prompt->secret(sprintf('Value for %s:', $definition->title())));
		} catch (SecretException $e) {
			$output->error($e->getMessage());

			return ExitCode::Failure;
		}

		$output->success(sprintf('Stored "%s".', $name));

		return ExitCode::Success;
	}
}
