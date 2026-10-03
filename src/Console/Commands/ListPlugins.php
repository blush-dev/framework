<?php

/**
 * Plugin list command.
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
use Blush\Plugin\PluginConfig;
use Blush\Plugin\Plugins;

/**
 * Lists the installed plugins, from Composer and `user/plugins`, with
 * whether each is on, off, or turned on but unable to run (its
 * requirements unmet, D-385), and any broken manifests (D-394).
 */
#[Command('plugin:list', 'List the installed plugins.')]
final readonly class ListPlugins
{
	public function __construct(
		private Plugins $plugins,
		private PluginConfig $config
	) {}

	public function __invoke(Output $output): ExitCode
	{
		$installed = $this->plugins->installed();
		$broken    = $this->plugins->broken();

		if ($installed === [] && $broken === []) {
			$output->line('No plugins are installed.');

			return ExitCode::Success;
		}

		$rows = [];

		foreach ($installed as $plugin) {
			$rows[] = [
				$plugin->name,
				$plugin->label,
				$plugin->namespace,
				$plugin->version,
				$plugin->source->value,
				match (true) {
					$this->plugins->has($plugin->name) => 'on',
					$this->config->isEnabled($plugin)  => 'can\'t run',
					default                            => 'off'
				}
			];
		}

		if ($rows !== []) {
			$output->table(['Name', 'Label', 'Namespace', 'Version', 'Source', 'Status'], $rows);
		}

		foreach ($broken as $plugin) {
			$output->warning("{$plugin->where}: {$plugin->reason}");
		}

		if ($this->plugins->unmet() !== []) {
			$output->comment('Run plugin:check to see why a plugin can\'t run.');
		}

		return ExitCode::Success;
	}
}
