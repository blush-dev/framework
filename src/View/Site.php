<?php

/**
 * Site view model.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View;

use Blush\Core\AppConfig;
use Blush\Core\Framework;

/**
 * What every template knows about the site, as `$site`: its name, home
 * URL, and language.
 */
final readonly class Site
{
	/**
	 * @param string $lang The locale as a BCP 47 tag (`en-US`), for `<html lang>`.
	 */
	public function __construct(
		public string $name,
		public string $url,
		public string $locale,
		public string $lang,
		public string $generator = Framework::NAME . ' ' . Framework::VERSION
	) {}

	/**
	 * Builds the site from the app config.
	 */
	public static function fromConfig(AppConfig $app): self
	{
		return new self(
			name: $app->name,
			url: $app->url,
			locale: $app->locale,
			lang: str_replace('_', '-', $app->locale)
		);
	}
}
