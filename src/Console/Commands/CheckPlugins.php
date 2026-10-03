<?php

/**
 * Plugin check command.
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
use Blush\Plugin\BrokenPlugin;
use Blush\Plugin\PluginConfig;
use Blush\Plugin\PluginManifest;
use Blush\Plugin\PluginRequirements;
use Blush\Plugin\Plugins;

/**
 * Checks every installed plugin (or one, by name): that its manifest
 * can be read (D-394) and its `requires` are met (D-385), checking one
 * that's off as if it were turned on. A plugin that's turned on but
 * can't run is an error, which fails the command; one that's off and
 * couldn't be turned on is a warning.
 */
#[Command('plugin:check', 'Check plugins\' manifests and requirements.')]
final readonly class CheckPlugins
{
	public function __construct(
		private Plugins $plugins,
		private PluginConfig $config
	) {}

	public function __invoke(
		Output $output,
		#[Argument('The plugin\'s name (vendor/name); defaults to every plugin.')] ?string $name = null
	): ExitCode {
		$installed = [];
		$running   = [];
		$blocked   = [];

		foreach ($this->plugins->installed() as $plugin) {
			$installed[$plugin->name] = $plugin;

			if ($this->plugins->has($plugin->name)) {
				$running[$plugin->name] = true;
			} elseif ($this->config->isEnabled($plugin)) {
				$blocked[$plugin->name] = true;
			}
		}

		$plugins = array_filter($installed, static fn (PluginManifest $plugin): bool => $name === null || $plugin->name === $name);
		$broken  = array_filter($this->plugins->broken(), static fn (BrokenPlugin $plugin): bool => $name === null || $plugin->name === $name);

		if ($name !== null && $plugins === [] && $broken === []) {
			$output->error(sprintf('No plugin named "%s" is installed.', $name));

			return ExitCode::Failure;
		}

		$requirements = new PluginRequirements();
		$errors       = 0;
		$warnings     = 0;

		foreach ($plugins as $plugin) {
			$checked = $requirements->check($plugin, $installed, $running, $blocked);

			if (PluginRequirements::met($checked)) {
				$output->line(sprintf('%s %s %s', $output->style('ok     ', Style::Green), $plugin->name, $output->style($plugin->version, Style::Dim)));

				continue;
			}

			$on        = $this->config->isEnabled($plugin);
			$errors   += $on ? 1 : 0;
			$warnings += $on ? 0 : 1;

			$output->line(sprintf(
				'%s %s: %s%s',
				$output->style($on ? 'error  ' : 'warning', $on ? Style::Red : Style::Yellow),
				$plugin->name,
				PluginRequirements::reason($checked),
				$on ? ' It\'s turned on, but doesn\'t run.' : ''
			));
		}

		foreach ($broken as $plugin) {
			$on        = $plugin->name !== '' && $this->config->turnsOn($plugin->name, $plugin->source);
			$errors   += $on ? 1 : 0;
			$warnings += $on ? 0 : 1;

			$output->line(sprintf(
				'%s %s: %s%s',
				$output->style($on ? 'error  ' : 'warning', $on ? Style::Red : Style::Yellow),
				$plugin->where,
				$plugin->reason,
				$on ? ' It\'s turned on, but doesn\'t run.' : ''
			));
		}

		$summary = sprintf(
			'Checked %d plugin(s): %d error(s), %d warning(s).',
			count($plugins) + count($broken),
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
