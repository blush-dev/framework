<?php

/**
 * Class domains.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Translation;

use Blush\Plugin\Plugins;
use Blush\Theme\Themes;

/**
 * Which domain a class's text is in (D-784): the extension whose PHP
 * namespace it's in, by the extension's `autoload` prefixes and its
 * provider's namespace (the longest match wins), or core's for a class
 * outside every running plugin and installed theme. This is how a
 * provider's `t()` and a class's `Messages` know their domain without
 * anyone typing it.
 */
final class ClassDomains
{
	/**
	 * Domains by namespace prefix, longest first, once built.
	 *
	 * @var ?array<string, string>
	 */
	private ?array $prefixes = null;

	public function __construct(
		private readonly ?Plugins $plugins = null,
		private readonly ?Themes $themes = null
	) {}

	/**
	 * Returns a class's domain.
	 */
	public function of(string $class): string
	{
		$class = ltrim($class, '\\');

		foreach ($this->prefixes() as $prefix => $domain) {
			if (str_starts_with($class, $prefix)) {
				return $domain;
			}
		}

		return Translator::CORE;
	}

	/**
	 * Returns the extensions' namespace prefixes and their domains,
	 * longest first.
	 *
	 * @return array<string, string>
	 */
	private function prefixes(): array
	{
		if ($this->prefixes !== null) {
			return $this->prefixes;
		}

		$prefixes   = [];
		$extensions = [...($this->plugins?->all() ?? []), ...array_values($this->themes?->all() ?? [])];

		foreach ($extensions as $extension) {
			$provider = $extension->provider ?? '';
			$at       = strrpos($provider, '\\');
			$own      = array_keys($extension->autoload->psr4);

			if ($at !== false) {
				$own[] = substr($provider, 0, $at + 1);
			}

			foreach ($own as $prefix) {
				$prefixes[ltrim($prefix, '\\')] ??= $extension->name;
			}
		}

		uksort($prefixes, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

		return $this->prefixes = $prefixes;
	}
}
