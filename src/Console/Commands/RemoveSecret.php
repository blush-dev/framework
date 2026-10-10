<?php

/**
 * Secret remove command.
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
use Blush\Console\Output;
use Blush\Secret\InvalidSecret;
use Blush\Secret\SecretDefinition;
use Blush\Secret\SecretException;
use Blush\Secret\Secrets;
use Blush\Secret\SecretStatus;
use Blush\Secret\SecretStore;

/**
 * Removes a secret's stored value (D-715), declared or left behind by a
 * removed extension. One set in the environment stays set.
 */
#[Command('secrets:remove', 'Remove a secret\'s stored value.')]
final readonly class RemoveSecret
{
	public function __construct(
		private Secrets $secrets,
		private SecretStore $store
	) {}

	public function __invoke(
		Output $output,
		#[Argument('The secret\'s full name, such as "acme/analytics.ga_refresh_token".')] string $name
	): ExitCode {
		try {
			$stored     = $this->store->all()[$name] ?? null;
			$definition = $this->secrets->find($name) ?? ($stored === null ? null : new SecretDefinition($stored->owner, $stored->key));

			if ($definition === null || $stored === null) {
				throw new InvalidSecret(sprintf('"%s" isn\'t stored; "secrets:list" lists the secrets.', $name));
			}

			$this->store->remove($definition);

			if ($this->store->status($definition) === SecretStatus::Environment) {
				$output->comment(sprintf('%s still sets it in the environment.', $definition->env));
			}
		} catch (SecretException $e) {
			$output->error($e->getMessage());

			return ExitCode::Failure;
		}

		$output->success(sprintf('Removed "%s".', $name));

		return ExitCode::Success;
	}
}
