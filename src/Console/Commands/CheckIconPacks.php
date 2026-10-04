<?php

/**
 * Icon pack check command.
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
use Blush\Console\Style;
use Blush\Extension\ExtensionAbandoned;
use Blush\Extension\ExtensionState;
use Blush\Extension\Requirements;
use Blush\Extension\VersionConstraint;
use Blush\Icon\IconPack;
use Blush\Icon\IconPacks;

/**
 * Checks every installed icon pack (or one, by name), as `plugin:check`
 * checks plugins (D-431): that its manifest can be read, its `require`
 * is met (checking one that's off as if it were on), and its `version`
 * is one Composer can read. A pack that's on but can't load is an error,
 * which fails the command; one that's off and couldn't be turned on, a
 * broken one, an unreadable version, and an abandoned pack (D-433) are
 * warnings.
 */
#[Command('icon-pack:check', 'Check icon packs\' manifests and requirements.')]
final readonly class CheckIconPacks
{
	public function __construct(
		private IconPacks $packs,
		private ExtensionState $extensions
	) {}

	public function __invoke(
		Output $output,
		#[Argument('The pack\'s name (vendor/name); defaults to every pack.')] ?string $name = null
	): ExitCode {
		$packs  = array_filter($this->packs->all(), static fn (IconPack $pack): bool => $name === null || $pack->name === $name);
		$broken = array_filter($this->packs->invalid(), static fn (string $reason, string $where): bool => $name === null || $where === $name || str_ends_with($where, "/{$name}"), ARRAY_FILTER_USE_BOTH);

		if ($name !== null && $packs === [] && $broken === []) {
			$output->error(sprintf('No icon pack named "%s" is installed.', $name));

			return ExitCode::Failure;
		}

		$errors   = 0;
		$warnings = 0;

		foreach ($packs as $pack) {
			$checked = $this->extensions->check($pack);

			$abandoned = ExtensionAbandoned::warning($pack->abandoned);

			// An abandoned pack still loads, as in Composer (D-433).
			if ($abandoned !== null) {
				$warnings++;

				$output->line(sprintf('%s %s: %s', $output->style('warning', Style::Yellow), $pack->name, $abandoned));
			}

			// A version Composer can't read meets only `*` (D-429, D-430).
			if ($pack->version !== '' && VersionConstraint::normalize($pack->version) === null) {
				$warnings++;

				$output->line(sprintf(
					'%s %s: Its version, "%s", isn\'t one Composer can read, so a requirement of it is met only by "*".',
					$output->style('warning', Style::Yellow),
					$pack->name,
					$pack->version
				));
			} elseif ($abandoned === null && Requirements::met($checked)) {
				$output->line(sprintf('%s %s %s', $output->style('ok     ', Style::Green), $pack->name, $output->style($pack->version, Style::Dim)));
			}

			if (Requirements::met($checked)) {
				continue;
			}

			$on        = $this->packs->isEnabled($pack->name);
			$errors   += $on ? 1 : 0;
			$warnings += $on ? 0 : 1;

			$output->line(sprintf(
				'%s %s: %s%s',
				$output->style($on ? 'error  ' : 'warning', $on ? Style::Red : Style::Yellow),
				$pack->name,
				Requirements::reason($checked),
				$on ? ' It\'s turned on, but its icons don\'t load.' : ''
			));
		}

		foreach ($broken as $where => $reason) {
			$warnings++;

			$output->line(sprintf('%s %s: %s', $output->style('warning', Style::Yellow), $where, $reason));
		}

		$summary = sprintf(
			'Checked %d icon pack(s): %d error(s), %d warning(s).',
			count($packs) + count($broken),
			$errors,
			$warnings
		);

		if ($errors > 0) {
			$output->error($summary);

			return ExitCode::Failure;
		}

		$output->success($summary);

		return ExitCode::Success;
	}
}
