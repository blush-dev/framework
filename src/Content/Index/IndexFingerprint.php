<?php

/**
 * Index fingerprint.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Index;

use Blush\Content\Type\ContentTypes;
use Blush\Core\AppConfig;

/**
 * What index records depend on besides the files: the index format, the
 * content types, the timezone, the locale, and the languages (D-455). An index built with a
 * different fingerprint is stale as a whole and is rebuilt in full.
 */
final class IndexFingerprint
{
	private ?string $value = null;

	public function __construct(
		private readonly ContentTypes $types,
		private readonly AppConfig $app
	) {}

	/**
	 * Returns the fingerprint.
	 */
	public function value(): string
	{
		return $this->value ??= hash('xxh128', serialize([
			IndexSnapshot::VERSION,
			$this->types->toArray(),
			$this->app->timezone,
			$this->app->locale,
			$this->app->languages->toArray()
		]));
	}

	/**
	 * Returns whether a snapshot was built with this fingerprint.
	 */
	public function matches(IndexSnapshot $snapshot): bool
	{
		return $snapshot->fingerprint === $this->value();
	}
}
