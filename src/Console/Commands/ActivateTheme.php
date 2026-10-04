<?php

/**
 * Theme activation command.
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
use Blush\Console\InvalidInput;
use Blush\Console\Output;
use Blush\Core\Bootstrap;
use Blush\Core\CompiledCache;
use Blush\Core\Paths;
use Blush\Extension\ExtensionState;
use Blush\Extension\Requirements;
use Blush\Settings\InvalidSetting;
use Blush\Settings\Setting;
use Blush\Settings\Settings;
use Blush\Settings\SettingsFile;
use Blush\Support\Filesystem;
use Blush\Theme\ThemeException;
use Blush\Theme\Themes;

/**
 * Makes a theme active by writing `config/theme.php`. A missing file is
 * created; an existing one is edited only when its `active` value is a
 * plain string literal, so hand-written config is never mangled. The
 * compiled config (and theme cache) are cleared, since they hold the old
 * value. A theme activated in the admin is saved in
 * `user/data/settings.json` over `config/theme.php` (D-381), so that's
 * cleared too, or the command wouldn't change the theme. A theme whose
 * chain's requirements aren't met is refused, since it wouldn't run
 * (D-431).
 */
#[Command('theme:activate', 'Make a theme the active one.')]
final readonly class ActivateTheme
{
	public function __construct(
		private Themes $themes,
		private ExtensionState $extensions,
		private Paths $paths,
		private Bootstrap $bootstrap,
		private Filesystem $filesystem,
		private SettingsFile $settings
	) {}

	/**
	 * @throws InvalidInput
	 */
	public function __invoke(
		Output $output,
		#[Argument('The theme\'s name (vendor/name).')] string $name
	): ExitCode {
		try {
			$this->themes->chain($name);
		} catch (ThemeException $error) {
			throw new InvalidInput($error->getMessage(), 0, $error);
		}

		// A chain whose requirements aren't met wouldn't run (D-431).
		$unmet = $this->extensions->with(theme: $name)->themes->unmet();

		if ($unmet !== []) {
			throw new InvalidInput(sprintf('The "%s" theme can\'t be activated. %s', $name, Requirements::reason(array_merge(...array_values($unmet)))));
		}

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
				$output->error(sprintf('Couldn\'t find one plain "active" value in %s; set active: \'%s\' there yourself.', $this->paths->relative($file), $name));

				return ExitCode::Failure;
			}

			$this->filesystem->writeAtomic($file, (string) preg_replace($pattern, "\${1}'{$name}'", $source));
		}

		$this->bootstrap->clearCompiled(CompiledCache::Config, CompiledCache::Themes);

		try {
			if ($this->settings->read()->has(Setting::Theme)) {
				$this->settings->update(static fn (Settings $settings): Settings => $settings->without(Setting::Theme));
				$output->comment(sprintf('Cleared the theme activated in the admin (%s).', $this->paths->relative($this->settings->path())));
			}
		} catch (InvalidSetting $error) {
			$output->warning(sprintf('The theme activated in the admin may still win over config/theme.php: %s', $error->getMessage()));
		}

		$output->success(sprintf('Activated the "%s" theme in %s.', $name, $this->paths->relative($file)));

		return ExitCode::Success;
	}
}
