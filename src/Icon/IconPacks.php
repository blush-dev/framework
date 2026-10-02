<?php

/**
 * Icon packs.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Icon;

/**
 * The installed icon packs (D-378), as `IconPackDiscovery` (or its
 * cache) found them: valid packs by name, plus the broken ones, by where
 * they were found, with the reason. Every installed pack is on.
 */
final readonly class IconPacks
{
	/**
	 * @param array<string, IconPack> $packs   Valid packs, by name.
	 * @param array<string, string>   $invalid Broken packs, by where they were found, with the reason.
	 */
	public function __construct(
		private array $packs = [],
		private array $invalid = []
	) {}

	/**
	 * Returns every valid pack, by name.
	 *
	 * @return array<string, IconPack>
	 */
	public function all(): array
	{
		return $this->packs;
	}

	/**
	 * Returns a pack, or `null` when it isn't installed.
	 */
	public function find(string $name): ?IconPack
	{
		return $this->packs[$name] ?? null;
	}

	/**
	 * Returns the pack that claims a namespace, or `null`.
	 */
	public function byNamespace(string $namespace): ?IconPack
	{
		return array_find($this->packs, static fn (IconPack $pack): bool => $pack->namespace === $namespace);
	}

	/**
	 * Returns the broken packs, by where they were found, with the reason.
	 *
	 * @return array<string, string>
	 */
	public function invalid(): array
	{
		return $this->invalid;
	}

	/**
	 * Returns a copy without the named packs, each recorded as broken,
	 * by where it was found, with a reason (a namespace another extension
	 * claims, D-378).
	 *
	 * @param array<string, array{string, string}> $reasons Where each was found and why, by pack name.
	 */
	public function without(array $reasons): self
	{
		$packs   = $this->packs;
		$invalid = $this->invalid;

		foreach ($reasons as $name => [$where, $reason]) {
			if (isset($packs[$name])) {
				$invalid[$where] = $reason;
				unset($packs[$name]);
			}
		}

		return new self($packs, $invalid);
	}
}
