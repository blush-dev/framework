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

use Blush\Data\DataStore;
use Blush\Data\InvalidData;

/**
 * The site owner's theme data, `user/data/theme.json` (D-022): which
 * site menu or region fills a theme location whose name differs (D-199,
 * D-201). A theme's setting values are its own group of settings
 * (`SettingGroups`, D-673), not here.
 *
 * ```json
 * {
 *     "menus": { "main": "primary" },
 *     "regions": { "aside": "sidebar" }
 * }
 * ```
 *
 * They apply to whichever theme is active: a location a theme doesn't
 * have is ignored.
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
		private readonly DataStore $store
	) {}

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
		return $this->data ??= $this->store->load('theme') ?? [];
	}
}
