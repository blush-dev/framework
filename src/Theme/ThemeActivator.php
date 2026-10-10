<?php

/**
 * Theme activator.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Theme;

use Blush\Core\Bootstrap;
use Blush\Core\CompiledCache;
use Blush\Core\Paths;
use Blush\Settings\InvalidSetting;
use Blush\Settings\Setting;
use Blush\Settings\Settings;
use Blush\Settings\SettingsStore;
use Blush\Support\Filesystem;

/**
 * Makes a theme the active one in `config/theme.php`, for
 * `theme:activate` and `theme:new --activate` (D-719): it writes the file
 * when there's none, or changes its one plain `active` value, and clears
 * the compiled config and themes. The theme activated in the admin wins
 * over config, so `clearAdminChoice()` takes it away. Whether the theme
 * can run is the caller's to check.
 */
final readonly class ThemeActivator
{
	public function __construct(
		private Paths $paths,
		private Bootstrap $bootstrap,
		private Filesystem $filesystem,
		private SettingsStore $settings
	) {}

	/**
	 * Writes the active theme to `config/theme.php`, and returns the file.
	 *
	 * @throws ThemeException When the file has no one plain `active` value to change.
	 */
	public function activate(string $name): string
	{
		$file = "{$this->paths->config}/theme.php";

		if (! is_file($file)) {
			$this->filesystem->writeAtomic($file, <<<PHP
				<?php

				declare(strict_types=1);

				use Blush\\Theme\\ThemeConfig;

				return new ThemeConfig(active: '{$name}');

				PHP);
		} else {
			$source  = (string) file_get_contents($file);
			$pattern = '#(\bactive\s*:\s*|[\'"]active[\'"]\s*=>\s*)([\'"])[a-z0-9_./-]*\2#';

			if (preg_match_all($pattern, $source) !== 1) {
				throw new ThemeException(sprintf('Couldn\'t find one plain "active" value in %s.', $this->paths->relative($file)));
			}

			$this->filesystem->writeAtomic($file, (string) preg_replace($pattern, "\${1}'{$name}'", $source));
		}

		$this->bootstrap->clearCompiled(CompiledCache::Config, CompiledCache::Themes);

		return $file;
	}

	/**
	 * Clears the theme activated in the admin, so config's wins, and
	 * returns where it was kept, or `null` when there was none.
	 *
	 * @throws InvalidSetting When the saved settings can't be read or written.
	 */
	public function clearAdminChoice(): ?string
	{
		if (! $this->settings->read()->has(Setting::Theme)) {
			return null;
		}

		$this->settings->update(static fn (Settings $settings): Settings => $settings->without(Setting::Theme));

		return $this->settings->location(Setting::Theme->section());
	}
}
