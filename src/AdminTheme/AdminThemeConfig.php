<?php

/**
 * Admin theme config.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\AdminTheme;

use Override;
use Blush\Config\Config;
use Blush\Config\ConfigValues;
use Blush\Config\InvalidConfig;
use Blush\Extension\ExtensionName;

/**
 * Which admin themes are on, and the site's default (D-827), as
 * `IconConfig` says which icon packs are on (D-390, D-391): by default the
 * framework's and Composer's are on, and a local one (in `extensions/`)
 * only when `config/admin-themes.php` names it in `enabled`; once the
 * admin saves a list (`saved`), that list is all of what's on. Neutral is
 * always on.
 *
 * `default` is the theme anyone who hasn't chosen one sees, and the
 * sign-in screen's.
 */
final readonly class AdminThemeConfig implements Config
{
	/**
	 * @param list<string>  $enabled Local themes to turn on, by name.
	 * @param ?list<string> $saved   The admin's list of every theme that's on, or `null` when it hasn't saved one.
	 * @param string        $default The site's default theme, by name.
	 * @throws InvalidConfig
	 */
	public function __construct(
		public array $enabled = [],
		public ?array $saved = null,
		public string $default = AdminThemes::BASE
	) {
		if (! ExtensionName::isValid($default)) {
			throw new InvalidConfig(sprintf('AdminThemeConfig "default" must be an admin theme\'s name (vendor/name); "%s" given.', $default));
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function fromArray(array $data): static
	{
		$values = new ConfigValues($data, self::class);
		$values->assertKnownKeys(['enabled', 'saved', 'default']);

		return new static(
			enabled: $values->stringList('enabled'),
			saved: $values->nullableStringList('saved'),
			default: $values->string('default', AdminThemes::BASE)
		);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function toArray(): array
	{
		return ['enabled' => $this->enabled, 'saved' => $this->saved, 'default' => $this->default];
	}
}
