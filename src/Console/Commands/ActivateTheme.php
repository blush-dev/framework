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
use Blush\Support\Filesystem;
use Blush\Theme\ThemeException;
use Blush\Theme\Themes;

/**
 * Makes a theme active by writing `config/theme.php`. A missing file is
 * created; an existing one is edited only when its `active` value is a
 * plain string literal, so hand-written config is never mangled. The
 * compiled config (and theme cache) are cleared, since they hold the old
 * value.
 */
#[Command('theme:activate', 'Make a theme the active one.')]
final readonly class ActivateTheme
{
	public function __construct(
		private Themes $themes,
		private Paths $paths,
		private Bootstrap $bootstrap,
		private Filesystem $filesystem
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

		$output->success(sprintf('Activated the "%s" theme in %s.', $name, $this->paths->relative($file)));

		return ExitCode::Success;
	}
}
