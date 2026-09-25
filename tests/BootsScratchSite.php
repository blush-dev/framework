<?php

/**
 * Builds a site application for tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests;

use Blush\Core\Application;
use Blush\Core\Bootstrap;
use Blush\Core\Paths;

/**
 * Boots an application for a site in a scratch directory. Tests write the
 * config and user files they need first, with `writeTemporaryFile()`
 * (paths relative to the site root).
 */
trait BootsScratchSite
{
	use TemporaryDirectory;

	/**
	 * Creates the scratch site's application, with its providers
	 * registered but not booted, so a test can register more first.
	 *
	 * @param array<string, string> $environment
	 */
	protected function scratchApplication(array $environment = []): Application
	{
		return new Bootstrap(Paths::fromRoot($this->temporaryDirectory()), $environment)->createApplication();
	}
}
