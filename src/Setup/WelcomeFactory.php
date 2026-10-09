<?php

/**
 * Welcome page notes factory.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Setup;

use Blush\Admin\AdminConfig;
use Blush\Auth\AccountStore;
use Blush\Content\Source\ContentFiles;
use Blush\Content\Source\FilesystemSource;
use Blush\Core\AppConfig;
use Blush\Core\Framework;

/**
 * Gathers the welcome page's notes. Setup problems are left out in
 * production, since the welcome page is public.
 */
final readonly class WelcomeFactory
{
	public function __construct(
		private ContentFiles $contentFiles,
		private AppConfig $app,
		private AdminConfig $admin,
		private AccountStore $accounts,
		private SetupChecks $checks
	) {}

	/**
	 * Returns the notes for the current site.
	 */
	public function make(): Welcome
	{
		$problems = $this->app->environment->isProduction() ? [] : array_values(array_filter(
			$this->checks->all($this->app),
			static fn(CheckResult $result): bool => $result->status !== CheckStatus::Pass
		));

		return new Welcome(
			homepage: $this->contentFiles->kept() ? $this->contentFiles->source()->location('index.' . FilesystemSource::EXTENSION) : null,
			binary: 'bin/' . Framework::BINARY,
			admin: $this->admin->enabled ? $this->admin->path : null,
			accounts: ! $this->accounts->isEmpty(),
			problems: $problems
		);
	}
}
