<?php

/**
 * Asset registrar.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Asset;

/**
 * Registers core's assets (D-569), without replacing any a plugin,
 * theme, or the site registered first:
 *
 * - `blush/player`: the audio and video players (D-553, D-554), which
 *   `::audio` and `::video` ask for (D-573).
 */
final readonly class AssetRegistrar
{
	/**
	 * The audio and video players' handle.
	 */
	public const string PLAYER = 'blush/player';

	public function __construct(private AssetRegistry $registry)
	{}

	/**
	 * Registers core's assets.
	 */
	public function register(): void
	{
		$this->registry->registerIf(new Asset(
			self::PLAYER,
			styles: [new Style('css/player.css', AssetUrls::CORE)],
			scripts: [new Script('js/player.js', AssetUrls::CORE, ['type' => 'module'])]
		));
	}
}
