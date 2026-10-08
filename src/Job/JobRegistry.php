<?php

/**
 * Job registry.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Job;

use Override;
use Blush\Support\Registry;
use Blush\Support\RegistrationException;

/**
 * Job classes by key (D-621). A key is `vendor/name`, as extensions are
 * named (D-378), in lowercase letters, digits, and hyphens, so a stored
 * job names its work without naming a class.
 *
 * @extends Registry<Job>
 */
final class JobRegistry extends Registry
{
	/**
	 * What a key looks like.
	 */
	public const string KEY = '/^[a-z0-9][a-z0-9-]*\/[a-z0-9][a-z0-9-]*$/';

	/**
	 * @inheritDoc
	 */
	protected const string CONTRACT = Job::class;

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function register(string $key, string $className): void
	{
		if (preg_match(self::KEY, $key) !== 1) {
			throw new RegistrationException(sprintf('A job\'s key is "vendor/name" in lowercase letters, digits, and hyphens; "%s" given.', $key));
		}

		parent::register($key, $className);
	}
}
