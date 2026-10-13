<?php

/**
 * `lang:extract` command.
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
use Blush\Console\Attributes\Option;
use Blush\Console\ExitCode;
use Blush\Console\Outcome;
use Blush\Console\Output;
use Blush\Console\StatusLine;
use Blush\Core\Framework;
use Blush\Extension\ExtensionManifest;
use Blush\Extension\ExtensionText;
use Blush\Icon\IconPack;
use Blush\Icon\IconPacks;
use Blush\Plugin\PluginManifest;
use Blush\Plugin\Plugins;
use Blush\Theme\Themes;
use Blush\Translation\MessageExtractor;
use Blush\Translation\SourceCatalog;
use Blush\Translation\TranslationException;
use Blush\Translation\Translator;

/**
 * Writes the messages an extension's code writes (`->t()` calls with
 * text beside the key, D-784) to its source catalog, `lang/en.json` (or
 * `--locale`'s), for translators: new keys are added, text that changed
 * in the code is replaced, and what's there otherwise is kept. An
 * extension's manifest text (its label and description, and a theme's
 * menu locations and settings) is written too, under `manifest.` keys
 * (`ExtensionText`, D-824). `core` extracts the framework's own; an icon
 * pack, its manifest's alone. Problems (a key that isn't a plain
 * string, one key with two texts, text that isn't valid ICU) are
 * warnings; the messages they're in aren't written.
 */
#[Command('lang:extract', 'Write the messages an extension\'s code writes to its catalog.')]
final readonly class ExtractMessages
{
	public function __construct(
		private Themes $themes,
		private Plugins $plugins,
		private IconPacks $packs,
		private MessageExtractor $extractor,
		private SourceCatalog $catalog
	) {}

	public function __invoke(
		Output $output,
		#[Argument('The theme, plugin, or icon pack (vendor/name), or core.')] string $name,
		#[Option('The language the code\'s text is in.')] string $locale = SourceCatalog::LOCALE,
		#[Option('Show what would change without writing.')] bool $dryRun = false
	): ExitCode {
		$found = $this->find($name);

		if ($found === null) {
			$output->error(sprintf('No theme, plugin, or icon pack named "%s" is installed.', $name), 'Name one by its vendor/name, or core for the framework.');

			return ExitCode::Failure;
		}

		[$root, $folders, $lang, $manifest] = $found;
		[$messages, $problems]             = $manifest instanceof IconPack ? [[], []] : $this->extractor->extract($root, $folders);
		$messages                          = $manifest === null ? $messages : [...ExtensionText::extracted($manifest), ...$messages];
		$file                              = SourceCatalog::path($lang, $locale);

		try {
			$result = $this->catalog->write($messages, $file, $name, $locale, $dryRun);
		} catch (TranslationException $e) {
			$output->error($e->getMessage());

			return ExitCode::Failure;
		}

		$lines = [];

		foreach ($result['added'] as $key) {
			$lines[] = new StatusLine(Outcome::Pass, $key, 'Added.');
		}

		foreach ($result['changed'] as $key) {
			$lines[] = new StatusLine(Outcome::Pass, $key, 'Its text changed; translators should recheck it.');
		}

		foreach ($result['conflicts'] as $key) {
			$lines[] = new StatusLine(Outcome::Warning, $key, 'A key above or below it holds the other kind (text or a group), so it wasn\'t written.');
		}

		foreach ($problems as $problem) {
			$lines[] = new StatusLine(Outcome::Warning, $problem->field, $problem->message);
		}

		$output->status($lines);
		$output->summary(
			Outcome::worst(Outcome::Pass, ...array_map(static fn (StatusLine $line): Outcome => $line->outcome, $lines)),
			sprintf('%s %s', $dryRun ? 'Would update' : 'Updated', $file),
			Output::count(count($messages), 'message', 'messages'),
			...Output::problems(0, count($result['conflicts']) + count($problems))
		);

		return ExitCode::Success;
	}

	/**
	 * Returns an extension's folder, the folders in it to read (all of
	 * it when none), its `lang/` folder, and its manifest (`null` for
	 * core), or `null`.
	 *
	 * @return ?array{string, list<string>, string, ?ExtensionManifest}
	 */
	private function find(string $name): ?array
	{
		if ($name === Translator::CORE) {
			return [Framework::path(), ['src', 'resources/views', 'resources/blocks'], Framework::path('resources/lang'), null];
		}

		$extension = $this->themes->find($name)
			?? array_find($this->plugins->installed(), static fn (PluginManifest $plugin): bool => $plugin->name === $name)
			?? $this->packs->find($name);

		return $extension === null ? null : [$extension->path, [], "{$extension->path}/lang", $extension];
	}
}
