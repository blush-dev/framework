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
 * in the code is replaced, and what's there otherwise is kept. `core`
 * extracts the framework's own. Problems (a key that isn't a plain
 * string, one key with two texts, text that isn't valid ICU) are
 * warnings; the messages they're in aren't written.
 */
#[Command('lang:extract', 'Write the messages an extension\'s code writes to its catalog.')]
final readonly class ExtractMessages
{
	public function __construct(
		private Themes $themes,
		private Plugins $plugins,
		private MessageExtractor $extractor,
		private SourceCatalog $catalog
	) {}

	public function __invoke(
		Output $output,
		#[Argument('The theme or plugin (vendor/name), or core.')] string $name,
		#[Option('The language the code\'s text is in.')] string $locale = SourceCatalog::LOCALE,
		#[Option('Show what would change without writing.')] bool $dryRun = false
	): ExitCode {
		$found = $this->find($name);

		if ($found === null) {
			$output->error(sprintf('No theme or plugin named "%s" is installed.', $name), 'Name one by its vendor/name, or core for the framework.');

			return ExitCode::Failure;
		}

		[$root, $folders, $lang] = $found;
		[$messages, $problems]  = $this->extractor->extract($root, $folders);
		$file                    = SourceCatalog::path($lang, $locale);

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
	 * it when none), and its `lang/` folder, or `null`.
	 *
	 * @return ?array{string, list<string>, string}
	 */
	private function find(string $name): ?array
	{
		if ($name === Translator::CORE) {
			return [Framework::path(), ['src', 'resources/views', 'resources/blocks'], Framework::path('resources/lang')];
		}

		$theme = $this->themes->find($name);

		if ($theme !== null) {
			return [$theme->path, [], $theme->langPath()];
		}

		$plugin = array_find($this->plugins->installed(), static fn ($plugin): bool => $plugin->name === $name);

		return $plugin === null ? null : [$plugin->path, [], "{$plugin->path}/lang"];
	}
}
