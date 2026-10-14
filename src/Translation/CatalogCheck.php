<?php

/**
 * Translation catalog check.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Translation;

use Blush\Data\DataLoader;
use Blush\Data\InvalidData;
use Blush\Field\Severity;
use Blush\Field\Violation;

/**
 * Checks an extension's `lang/` catalogs against what they say they
 * translate (D-452), for `theme:check`, `plugin:check`, and
 * `icon-pack:check`: a catalog that can't be read is an error; `@@locale`
 * that isn't its file's locale, or `@@domain` that isn't the extension's
 * name, is a warning; and a catalog without them is a notice. Named
 * catalogs (D-831), in the folders under `lang/`, are checked the same
 * way, and their `@@catalog` against their folder's name.
 */
final readonly class CatalogCheck
{
	public function __construct(private DataLoader $loader)
	{}

	/**
	 * Checks the catalogs in a folder for a domain.
	 *
	 * @return list<Violation>
	 */
	public function check(string $directory, string $domain): array
	{
		$problems = $this->checkFolder($directory, $domain, null);

		foreach (glob("{$directory}/*", GLOB_ONLYDIR) ?: [] as $folder) {
			if (Catalog::isName(basename($folder))) {
				$problems = [...$problems, ...$this->checkFolder($folder, $domain, basename($folder))];
			}
		}

		return $problems;
	}

	/**
	 * Checks the catalogs in one folder: the default catalog's, or a
	 * named one's.
	 *
	 * @return list<Violation>
	 */
	private function checkFolder(string $directory, string $domain, ?string $name): array
	{
		$where = $name === null ? 'lang' : "lang/{$name}";

		try {
			$catalogs = $this->loader->loadAll($directory);
		} catch (InvalidData $error) {
			return [new Violation($where, $error->getMessage())];
		}

		$problems = [];

		foreach ($catalogs as $locale => $catalog) {
			$file  = "{$where}/{$locale}";
			$says  = $catalog[Catalog::CATALOG] ?? null;

			if ($name !== null && $says !== null && $says !== $name) {
				$problems[] = new Violation($file, sprintf('Its "%s" is %s, but it\'s in the "%s" folder.', Catalog::CATALOG, json_encode($says, JSON_UNESCAPED_SLASHES), $name), Severity::Warning);
			}

			$named = $catalog[Catalog::LOCALE] ?? null;
			$for   = $catalog[Catalog::DOMAIN] ?? null;

			if ($named === null || $for === null || ($name !== null && $says === null)) {
				$problems[] = $name === null
					? new Violation($file, sprintf('Start it with "%s": "%s" and "%s": "%s", which say what it translates.', Catalog::LOCALE, $locale, Catalog::DOMAIN, $domain), Severity::Notice)
					: new Violation($file, sprintf('Start it with "%s": "%s", "%s": "%s", and "%s": "%s", which say what it translates.', Catalog::LOCALE, $locale, Catalog::DOMAIN, $domain, Catalog::CATALOG, $name), Severity::Notice);
			}

			if ($named !== null && (! is_string($named) || self::normalize($named) !== self::normalize((string) $locale))) {
				$problems[] = new Violation($file, sprintf('Its "%s" is %s, but the file is for "%s".', Catalog::LOCALE, json_encode($named, JSON_UNESCAPED_SLASHES), $locale), Severity::Warning);
			}

			if ($for !== null && $for !== $domain) {
				$problems[] = new Violation($file, sprintf('Its "%s" is %s, but it\'s in "%s".', Catalog::DOMAIN, json_encode($for, JSON_UNESCAPED_SLASHES), $domain), Severity::Warning);
			}
		}

		return $problems;
	}

	/**
	 * Returns a locale name for comparing: `fr-ca` and `fr_CA` are one.
	 */
	private static function normalize(string $locale): string
	{
		return strtolower(str_replace('-', '_', $locale));
	}
}
