<?php

/**
 * Asset registry.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Asset;

/**
 * The assets a site can load, by handle (D-569). Core registers its own
 * (`AssetRegistrar`), then plugins, themes, and the site register theirs
 * in their providers, in that order, so a later registration of a handle
 * replaces an earlier one: a theme can restyle core's player, or blank
 * it with an asset that has no files.
 *
 *     $this->container->make(AssetRegistry::class)->register(new Asset('acme/gallery', ...));
 */
final class AssetRegistry
{
	/**
	 * Assets by handle.
	 *
	 * @var array<string, Asset>
	 */
	private array $assets = [];

	/**
	 * Registers an asset, replacing any of the same handle.
	 */
	public function register(Asset $asset): void
	{
		$this->assets[$asset->handle] = $asset;
	}

	/**
	 * Registers an asset unless its handle is taken.
	 */
	public function registerIf(Asset $asset): void
	{
		$this->assets[$asset->handle] ??= $asset;
	}

	/**
	 * Removes an asset, if it's there. Asking for it then loads nothing.
	 */
	public function remove(string $handle): void
	{
		unset($this->assets[$handle]);
	}

	/**
	 * Returns whether a handle is registered.
	 */
	public function has(string $handle): bool
	{
		return isset($this->assets[$handle]);
	}

	/**
	 * Returns an asset by handle, or `null`.
	 */
	public function get(string $handle): ?Asset
	{
		return $this->assets[$handle] ?? null;
	}

	/**
	 * Returns every asset, by handle.
	 *
	 * @return array<string, Asset>
	 */
	public function all(): array
	{
		return $this->assets;
	}

	/**
	 * Returns the assets for some handles in the order they load: each
	 * after what it requires, and each once. Handles no one registered
	 * are skipped, and a loop of requirements is broken where it closes.
	 *
	 * @param  list<string> $handles
	 * @return list<Asset>
	 */
	public function ordered(array $handles): array
	{
		$ordered = [];
		$seen    = [];
		$visit   = function (string $handle) use (&$visit, &$ordered, &$seen): void {
			if (isset($seen[$handle]) || ! isset($this->assets[$handle])) {
				return;
			}

			$seen[$handle] = true;

			foreach ($this->assets[$handle]->requires as $required) {
				$visit($required);
			}

			$ordered[] = $this->assets[$handle];
		};

		foreach ($handles as $handle) {
			$visit($handle);
		}

		return $ordered;
	}
}
