<?php

/**
 * Site theme data.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Theme;

use Blush\Core\Paths;
use Blush\Data\DataLoader;
use Blush\Data\InvalidData;

/**
 * The site owner's theme data, `user/data/theme.json` (D-022): setting values, which the future admin edits, and which site
 * menu or region fills a theme location whose name differs (D-199,
 * D-201).
 *
 * ```json
 * {
 *     "settings": { "wide": true },
 *     "menus": { "main": "primary" },
 *     "regions": { "aside": "sidebar" }
 * }
 * ```
 *
 * They apply to whichever theme is active: a setting a theme doesn't
 * declare is ignored, and so is a location it doesn't have.
 */
final class SiteThemeData
{
	/**
	 * The parsed file, once read.
	 *
	 * @var ?array<array-key, mixed>
	 */
	private ?array $data = null;

	public function __construct(
		private readonly Paths $paths,
		private readonly DataLoader $loader
	) {}

	/**
	 * Returns the setting values.
	 *
	 * @return array<array-key, mixed>
	 * @throws InvalidData
	 */
	public function settings(): array
	{
		$settings = $this->data()['settings'] ?? [];

		return is_array($settings) ? $settings : throw new InvalidData('user/data/theme "settings" must be an object.');
	}

	/**
	 * Returns the site menus that fill theme menu locations, by location.
	 *
	 * @return array<string, string>
	 * @throws InvalidData
	 */
	public function menus(): array
	{
		return $this->locations('menus');
	}

	/**
	 * Returns the site regions that fill theme region locations, by
	 * location.
	 *
	 * @return array<string, string>
	 * @throws InvalidData
	 */
	public function regions(): array
	{
		return $this->locations('regions');
	}

	/**
	 * Reads a map of location names to site data names.
	 *
	 * @return array<string, string>
	 * @throws InvalidData
	 */
	private function locations(string $key): array
	{
		$map = $this->data()[$key] ?? [];

		if (! is_array($map) || ($map !== [] && array_is_list($map)) || ! array_all($map, static fn (mixed $name): bool => is_string($name) && $name !== '')) {
			throw new InvalidData(sprintf('user/data/theme "%s" must map location names to names.', $key));
		}

		/** @var array<string, string> $map */
		return $map;
	}

	/**
	 * Returns the parsed file.
	 *
	 * @return array<array-key, mixed>
	 * @throws InvalidData
	 */
	private function data(): array
	{
		return $this->data ??= $this->loader->load($this->paths->data, 'theme') ?? [];
	}
}
