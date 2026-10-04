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
 * name, is a warning; and a catalog without them is a notice.
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
		try {
			$catalogs = $this->loader->loadAll($directory);
		} catch (InvalidData $error) {
			return [new Violation('lang', $error->getMessage())];
		}

		$problems = [];

		foreach ($catalogs as $locale => $catalog) {
			$file  = "lang/{$locale}";
			$named = $catalog[Catalog::LOCALE] ?? null;
			$for   = $catalog[Catalog::DOMAIN] ?? null;

			if ($named === null || $for === null) {
				$problems[] = new Violation($file, sprintf('Start it with "%s": "%s" and "%s": "%s", which say what it translates.', Catalog::LOCALE, $locale, Catalog::DOMAIN, $domain), Severity::Notice);
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
