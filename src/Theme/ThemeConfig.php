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

/**
 * The site's theme settings, from `config/theme.php`:
 *
 *     return new ThemeConfig(active: 'nova');
 *
 * `active` is the slug of a theme in `user/themes`, or `default` for the
 * framework default theme.
 */
final readonly class ThemeConfig implements Config
{
	/**
	 * @throws InvalidConfig
	 */
	public function __construct(public string $active = Themes::DEFAULT)
	{
		if (! Themes::isValidSlug($active)) {
			throw new InvalidConfig(sprintf('ThemeConfig "active" must be a theme slug; "%s" given.', $active));
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
