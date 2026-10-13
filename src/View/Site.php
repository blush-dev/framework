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

use Locale;
use Blush\Core\AppConfig;
use Blush\Core\Framework;
use Blush\Settings\SiteSettings;

/**
 * What every template knows about the site, as `$site`: its name, home
 * URL, language, the direction its text runs in (D-470), and description
 * (D-398; empty when it has none).
 */
final readonly class Site
{
	/**
	 * @param string $lang The locale as a BCP 47 tag (`en-US`), for `<html lang>`.
	 * @param string $dir  The locale's text direction, `ltr` or `rtl`, for `<html dir>`.
	 */
	public function __construct(
		public string $name,
		public string $url,
		public string $locale,
		public string $lang,
		public string $generator = Framework::NAME . ' ' . Framework::VERSION,
		public string $description = '',
		public string $dir = 'ltr',
		private ?SiteSettings $settings = null
	) {}

	/**
	 * Returns a site setting's value (D-343, D-815): one a field set adds
	 * to the admin's Settings screens, saved in the `site` group of
	 * settings, or its field's default, or `$default` when it has
	 * neither: `$site->setting('logo')`.
	 */
	public function setting(string $name, mixed $default = null): mixed
	{
		return $this->settings === null ? $default : $this->settings->get($name, $default);
	}

	/**
	 * Builds the site from the app config, in a page's locale (a
	 * translation's, D-455) or the site's.
	 */
	public static function fromConfig(AppConfig $app, ?string $locale = null, ?SiteSettings $settings = null): self
	{
		$locale ??= $app->locale;

		return new self(
			name: $app->name,
			url: $app->url,
			locale: $locale,
			lang: str_replace('_', '-', $locale),
			description: $app->description,
			dir: Locale::isRightToLeft($locale) ? 'rtl' : 'ltr',
			settings: $settings
		);
	}
}
