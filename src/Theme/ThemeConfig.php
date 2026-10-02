<?php

/**
 * Theme config.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Theme;

use Override;
use Blush\Config\Config;
use Blush\Config\ConfigValues;
use Blush\Config\InvalidConfig;
use Blush\Extension\ExtensionName;

/**
 * The site's theme settings, from `config/theme.php`:
 *
 *     return new ThemeConfig(active: 'acme/nova');
 *
 * `active` is an installed theme's name (`vendor/name`, D-378), or
 * `blush/default` for the framework default theme.
 */
final readonly class ThemeConfig implements Config
{
	/**
	 * @throws InvalidConfig
	 */
	public function __construct(public string $active = Themes::DEFAULT)
	{
		if (! ExtensionName::isValid($active)) {
			throw new InvalidConfig(sprintf('ThemeConfig "active" must be a theme\'s name (vendor/name); "%s" given.', $active));
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function fromArray(array $data): static
	{
		$values = new ConfigValues($data, self::class);
		$values->assertKnownKeys(['active']);

		return new static(active: $values->string('active', Themes::DEFAULT));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function toArray(): array
	{
		return ['active' => $this->active];
	}
}
