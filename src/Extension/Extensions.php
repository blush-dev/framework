<?php

/**
 * Extensions.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Extension;

/**
 * The site's enabled extensions, in name order. Bound in the container, so
 * commands like `extension:list` and `doctor` can inspect them.
 */
final readonly class Extensions
{
	/**
	 * Enabled manifests keyed by name.
	 *
	 * @var array<string, ExtensionManifest>
	 */
	private array $manifests;

	/**
	 * @param list<ExtensionManifest> $manifests
	 */
	public function __construct(array $manifests = [])
	{
		$keyed = [];

		foreach ($manifests as $manifest) {
			$keyed[$manifest->name] = $manifest;
		}

		ksort($keyed);

		$this->manifests = $keyed;
	}

	/**
	 * Filters discovered manifests down to the ones config enables.
	 *
	 * @param  list<ExtensionManifest> $discovered
	 * @throws ExtensionException When config enables an extension that isn't installed.
	 */
	public static function enabled(array $discovered, ExtensionConfig $config): self
	{
		$names   = array_map(static fn (ExtensionManifest $manifest): string => $manifest->name, $discovered);
		$missing = array_diff($config->enabled ?? [], $names);

		if ($missing !== []) {
			throw new ExtensionException(sprintf(
				'Config enables extension(s) that are not installed: %s.',
				implode(', ', $missing)
			));
		}

		return new self(array_values(array_filter(
			$discovered,
			static fn (ExtensionManifest $manifest): bool => $config->isEnabled($manifest->name)
		)));
	}

	/**
	 * @return list<ExtensionManifest>
	 */
	public function all(): array
	{
		return array_values($this->manifests);
	}

	/**
	 * Whether the named extension is enabled.
	 */
	public function has(string $name): bool
	{
		return isset($this->manifests[$name]);
	}

	/**
	 * Returns the named extension's manifest, or `null`.
	 */
	public function get(string $name): ?ExtensionManifest
	{
		return $this->manifests[$name] ?? null;
	}

	/**
	 * Returns every enabled extension's provider class, in name order.
	 *
	 * @return list<class-string>
	 */
	public function providers(): array
	{
		return array_map(
			static fn (ExtensionManifest $manifest): string => $manifest->providerClass(),
			$this->all()
		);
	}
}
