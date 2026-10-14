<?php

/**
 * Script messages.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Translation;

use Blush\Extension\ExtensionCatalogs;
use Blush\Field\Severity;
use Blush\Field\Violation;

/**
 * The messages an extension's scripts write (D-831), by the named
 * catalog each folder writes to (`ExtensionCatalogs`): what
 * `lang:extract` writes to `lang/{catalog}/en.json`, and what
 * `theme:check` and `plugin:check` compare those with. Core's are
 * declared as an extension's would be (`CORE`).
 */
final readonly class ScriptMessages
{
	/**
	 * Core's catalogs: the site's scripts and the players, and the admin
	 * app, which shows the players too.
	 *
	 * @var array<string, list<string>>
	 */
	public const array CORE = [
		'site'  => ['resources/site', 'resources/player'],
		'admin' => ['resources/admin', 'resources/player']
	];

	public function __construct(
		private MessageExtractor $extractor,
		private SourceCatalog $catalog
	) {}

	/**
	 * Returns the messages of each catalog an extension's scripts write
	 * to, by catalog name, and the problems: calls that can't be
	 * collected, another domain read, and a folder a manifest names that
	 * doesn't exist.
	 *
	 * @param  array<string, list<string>> $declared Its manifest's `catalogs`, or `[]`.
	 * @return array{array<string, array<string, ExtractedMessage>>, list<Violation>}
	 */
	public function extract(string $root, array $declared, string $domain): array
	{
		$catalogs = [];
		$problems = [];

		foreach (ExtensionCatalogs::resolve($root, $declared) as $name => $folders) {
			foreach ($folders as $folder) {
				if (! is_dir("{$root}/{$folder}")) {
					$problems[] = new Violation('catalogs', sprintf('"%s" names the "%s" folder, which doesn\'t exist.', $name, $folder), Severity::Warning);
				}
			}

			[$messages, $found] = $this->extractor->extractScripts($root, $folders, $domain);
			$catalogs[$name]    = $messages;
			$problems           = [...$problems, ...$found];
		}

		return [$catalogs, self::unique($problems)];
	}

	/**
	 * Returns the problems of an extension's scripts and its named
	 * catalogs: those `extract()` finds, and text a catalog has otherwise
	 * (`SourceCatalog::compare()`).
	 *
	 * @param  array<string, list<string>> $declared
	 * @return list<Violation>
	 * @throws TranslationException When a catalog isn't a JSON object.
	 */
	public function check(string $root, array $declared, string $domain, string $lang): array
	{
		[$catalogs, $problems] = $this->extract($root, $declared, $domain);

		foreach ($catalogs as $name => $messages) {
			$problems = [...$problems, ...$this->catalog->compare($messages, SourceCatalog::path($lang, catalog: $name))];
		}

		return $problems;
	}

	/**
	 * Drops repeats: a folder under two catalogs is read twice.
	 *
	 * @param  list<Violation> $problems
	 * @return list<Violation>
	 */
	private static function unique(array $problems): array
	{
		$unique = [];

		foreach ($problems as $problem) {
			$unique["{$problem->field}|{$problem->message}"] = $problem;
		}

		return array_values($unique);
	}
}
