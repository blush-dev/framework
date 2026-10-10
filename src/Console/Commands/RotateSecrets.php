<?php

/**
 * Secrets rotate command.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console\Commands;

use Blush\Console\Attributes\Command;
use Blush\Console\ExitCode;
use Blush\Console\Output;
use Blush\Core\Framework;
use Blush\Secret\SecretDefinition;
use Blush\Secret\SecretException;
use Blush\Secret\SecretKeys;
use Blush\Secret\SecretStore;
use Blush\Secret\StoredSecret;

/**
 * Encrypts every stored secret again under the current key (D-715), for
 * after a new `SECRETS_KEY` is set with the old one kept in
 * `SECRETS_KEY_PREVIOUS`. Secrets already under the current key are left
 * as they are; any that won't decrypt are listed, and the command fails.
 */
#[Command('secrets:rotate', 'Encrypt the stored secrets again under the current SECRETS_KEY.')]
final readonly class RotateSecrets
{
	public function __construct(
		private SecretStore $store
	) {}

	public function __invoke(Output $output): ExitCode
	{
		try {
			$result = $this->store->rotate(array_values(array_map(
				static fn (StoredSecret $secret): SecretDefinition => new SecretDefinition($secret->owner, $secret->key),
				$this->store->all()
			)));
		} catch (SecretException $e) {
			$output->error($e->getMessage());

			return ExitCode::Failure;
		}

		foreach ($result['unreadable'] as $name) {
			$output->warning(sprintf('"%s" won\'t decrypt with %s or %s.', $name, SecretKeys::CURRENT, SecretKeys::PREVIOUS), sprintf('Set it again with `%s secrets:set %s`.', Framework::BINARY, $name));
		}

		$count = count($result['rotated']);

		$output->success($count === 0 ? 'Every stored secret is already under the current key.' : sprintf('Encrypted %d stored %s again under the current key.', $count, $count === 1 ? 'secret' : 'secrets'));

		if ($count > 0 && $result['unreadable'] === []) {
			$output->next(sprintf('`%s` can be removed now.', SecretKeys::PREVIOUS));
		}

		return $result['unreadable'] === [] ? ExitCode::Success : ExitCode::Failure;
	}
}
