<?php

/**
 * Secrets list command.
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
use Blush\Secret\SecretException;
use Blush\Secret\Secrets;
use Blush\Secret\SecretStatus;
use Blush\Secret\SecretStore;

/**
 * Lists the secrets core and extensions declare (D-715): where each
 * one's value comes from, the environment variable that sets it, and
 * when it was stored. Never a value. Stored secrets nobody declares any
 * more (an extension removed) are listed too.
 */
#[Command('secrets:list', 'List the secrets and where each is set, without their values.')]
final readonly class ListSecrets
{
	public function __construct(
		private Secrets $secrets,
		private SecretStore $store
	) {}

	public function __invoke(Output $output): ExitCode
	{
		try {
			$stored = $this->store->all();
			$rows   = [];

			foreach ($this->secrets->definitions() as $name => $definition) {
				$status = $this->store->status($definition);

				$rows[] = [
					$name,
					$status->value . ($definition->required && ! $status->isSet() ? ' (required)' : ''),
					$definition->env,
					$status === SecretStatus::Environment ? '' : ($stored[$name]->updated ?? '')
				];

				unset($stored[$name]);
			}

			foreach ($stored as $name => $secret) {
				$rows[] = [$name, 'stored (not declared)', '', $secret->updated ?? ''];
			}
		} catch (SecretException $e) {
			$output->error($e->getMessage());

			return ExitCode::Failure;
		}

		if ($rows === []) {
			$output->line('No secrets are declared or stored.');

			return ExitCode::Success;
		}

		$output->table(['Name', 'Status', 'Variable', 'Stored'], $rows);

		return ExitCode::Success;
	}
}
