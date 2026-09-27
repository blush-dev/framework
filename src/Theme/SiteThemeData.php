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
 * The site owner's theme data, `user/data/theme.json` (or `.yaml`,
 * D-022): setting values, which the future admin edits.
 *
 * ```json
 * {
 *     "settings": { "excerpts": false }
 * }
 * ```
 *
 * They apply to whichever theme is active: a setting a theme doesn't
 * declare is ignored.
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
