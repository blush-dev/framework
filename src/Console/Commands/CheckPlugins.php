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
use Blush\Console\Outcome;
use Blush\Console\Output;
use Blush\Console\StatusLine;
use Blush\Extension\ExtensionAbandoned;
use Blush\Extension\ExtensionState;
use Blush\Extension\ExtensionText;
use Blush\Extension\Requirements;
use Blush\Extension\VersionConstraint;
use Blush\Field\Severity;
use Blush\Field\Violation;
use Blush\Plugin\BrokenPlugin;
use Blush\Plugin\PluginConfig;
use Blush\Plugin\PluginManifest;
use Blush\Plugin\Plugins;
use Blush\Translation\CatalogCheck;
use Blush\Translation\MessageExtractor;
use Blush\Translation\SourceCatalog;
use Blush\Translation\TranslationException;

/**
 * Checks every installed plugin (or one, by name): that its manifest
 * can be read (D-394) and its `require` are met (D-385), checking one
 * that's off as if it were turned on. A plugin that's turned on but
 * can't run is an error, which fails the command; one that's off and
 * couldn't be turned on is a warning, and so is a version Composer
 * can't read (D-430), an abandoned plugin, which still runs (D-433), and
 * a `lang/` catalog whose `@@locale` or `@@domain` doesn't match (D-452).
 */
#[Command('plugin:check', 'Check plugins\' manifests and requirements.')]
final readonly class CheckPlugins
{
	public function __construct(
		private Plugins $plugins,
		private PluginConfig $config,
		private ExtensionState $extensions,
		private CatalogCheck $catalogs,
		private MessageExtractor $extractor,
		private SourceCatalog $sourceCatalog
	) {}

	public function __invoke(
		Output $output,
		#[Argument('The plugin\'s name (vendor/name); defaults to every plugin.')] ?string $name = null
	): ExitCode {
		$installed = [];

		foreach ($this->plugins->installed() as $plugin) {
			$installed[$plugin->name] = $plugin;
		}

		$plugins = array_filter($installed, static fn (PluginManifest $plugin): bool => $name === null || $plugin->name === $name);
		$broken  = array_filter($this->plugins->broken(), static fn (BrokenPlugin $plugin): bool => $name === null || $plugin->name === $name);

		if ($name !== null && $plugins === [] && $broken === []) {
			$output->error(sprintf('No plugin named "%s" is installed.', $name));

			return ExitCode::Failure;
		}

		$lines = [];

		foreach ($plugins as $plugin) {
			$checked = $this->extensions->check($plugin);

			// Its catalogs against what they say they translate (D-452);
			// notices (a catalog without them) are `theme:check --strict`'s.
			$catalogs = array_filter($this->catalogs->check("{$plugin->path}/lang", $plugin->name), static fn (Violation $violation): bool => $violation->severity !== Severity::Notice);

			// The messages its code and manifest write against lang/en.json
			// (D-784, D-824); a catalog that can't be read is reported above.
			[$messages, $problems] = $this->extractor->extract($plugin->path);
			$messages              = [...ExtensionText::extracted($plugin), ...$messages];

			try {
				$problems = [...$problems, ...$this->sourceCatalog->compare($messages, SourceCatalog::path("{$plugin->path}/lang"))];
			} catch (TranslationException) {
				// Reported by the catalog check.
			}

			$catalogs = [...$catalogs, ...array_filter($problems, static fn (Violation $violation): bool => $violation->severity !== Severity::Notice)];

			foreach ($catalogs as $violation) {
				$lines[] = new StatusLine(Outcome::of($violation->severity), $plugin->name, "{$violation->field} {$violation->message}");
			}

			$abandoned = ExtensionAbandoned::warning($plugin->abandoned);

			// An abandoned plugin still runs, as in Composer (D-433).
			if ($abandoned !== null) {
				$lines[] = new StatusLine(Outcome::Warning, $plugin->name, $abandoned);
			}

			// A version Composer can't read meets only `*` (D-429).
			if (VersionConstraint::normalize($plugin->version) === null) {
				$lines[] = new StatusLine(Outcome::Warning, $plugin->name, sprintf('Its version, "%s", isn\'t one Composer can read, so a plugin that requires it is met only by "*".', $plugin->version));
			} elseif ($abandoned === null && $catalogs === [] && Requirements::met($checked)) {
				$lines[] = new StatusLine(Outcome::Pass, $plugin->name, $plugin->version);
			}

			if (Requirements::met($checked)) {
				continue;
			}

			$on      = $this->config->isEnabled($plugin);
			$lines[] = new StatusLine($on ? Outcome::Failure : Outcome::Warning, $plugin->name, Requirements::reason($checked) . ($on ? ' It\'s turned on, but doesn\'t run.' : ''));
		}

		foreach ($broken as $plugin) {
			$on      = $plugin->name !== '' && $this->config->turnsOn($plugin->name, $plugin->source);
			$lines[] = new StatusLine($on ? Outcome::Failure : Outcome::Warning, $plugin->where, $plugin->reason . ($on ? ' It\'s turned on, but doesn\'t run.' : ''));
		}

		$output->status($lines);
		$output->summary(
			Outcome::worst(...array_map(static fn (StatusLine $line): Outcome => $line->outcome, $lines)),
			Output::count(count($plugins) + count($broken), 'plugin', 'plugins'),
			...Output::problems(self::count($lines, Outcome::Failure), self::count($lines, Outcome::Warning))
		);

		return array_any($lines, static fn (StatusLine $line): bool => $line->outcome === Outcome::Failure) ? ExitCode::Failure : ExitCode::Success;
	}

	/**
	 * Counts the rows with an outcome.
	 *
	 * @param list<StatusLine> $lines
	 */
	private static function count(array $lines, Outcome $outcome): int
	{
		return count(array_filter($lines, static fn (StatusLine $line): bool => $line->outcome === $outcome));
	}
}
