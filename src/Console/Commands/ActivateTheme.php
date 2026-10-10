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
use Blush\Core\Paths;
use Blush\Extension\ExtensionState;
use Blush\Extension\Requirements;
use Blush\Settings\InvalidSetting;
use Blush\Theme\ThemeActivator;
use Blush\Theme\ThemeException;
use Blush\Theme\Themes;

/**
 * Makes a theme active by writing `config/theme.php`. A missing file is
 * created; an existing one is edited only when its `active` value is a
 * plain string literal, so hand-written config is never mangled. The
 * compiled config (and theme cache) are cleared, since they hold the old
 * value. A theme activated in the admin is saved in
 * the saved settings (`user/data/settings/`) over `config/theme.php` (D-381), so that's
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
		private ThemeActivator $activator
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

		try {
			$file = $this->activator->activate($name);
		} catch (ThemeException $error) {
			$output->error($error->getMessage(), sprintf('Set `active: \'%s\'` there yourself.', $name));

			return ExitCode::Failure;
		}

		try {
			$cleared = $this->activator->clearAdminChoice();

			if ($cleared !== null) {
				$output->comment(sprintf('Cleared the theme activated in the admin (%s).', $cleared));
			}
		} catch (InvalidSetting $error) {
			$output->warning(sprintf('The theme activated in the admin may still win over config/theme.php: %s', $error->getMessage()));
		}

		$output->success(sprintf('Activated the "%s" theme in %s.', $name, $this->paths->relative($file)));

		return ExitCode::Success;
	}
}
