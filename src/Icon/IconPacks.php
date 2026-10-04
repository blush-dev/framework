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

use Blush\Extension\Requirement;

/**
 * The installed icon packs (D-378), as `IconPackDiscovery` (or its
 * cache) found them: valid packs by name, plus the broken ones, by where
 * they were found, with the reason. Which are on is `IconConfig`'s
 * (D-390, D-391): by default Composer's packs and the local ones it
 * names, or, once the admin saves a list, only what that list names.
 * Only the ones that are on add their icons.
 */
final readonly class IconPacks
{
	/**
	 * @param array<string, IconPack>          $packs   Valid packs, by name.
	 * @param array<string, string>            $invalid Broken packs, by where they were found, with the reason.
	 * @param IconConfig                       $config  Which are on.
	 * @param array<string, list<Requirement>> $unmet   The packs that are on but can't load, with the requirements each doesn't meet (D-431).
	 */
	public function __construct(
		private array $packs = [],
		private array $invalid = [],
		private IconConfig $config = new IconConfig(),
		private array $unmet = []
	) {}

	/**
	 * Returns a copy with config saying which are on.
	 */
	public function withConfig(IconConfig $config): self
	{
		return new self($this->packs, $this->invalid, $config, $this->unmet);
	}

	/**
	 * Returns a copy knowing which packs that are on can't load, and why
	 * (D-431).
	 *
	 * @param array<string, list<Requirement>> $unmet
	 */
	public function withUnmet(array $unmet): self
	{
		ksort($unmet);

		return new self($this->packs, $this->invalid, $this->config, $unmet);
	}

	/**
	 * Returns the valid packs that load: on, with their requirements
	 * met, by name.
	 *
	 * @return array<string, IconPack>
	 */
	public function enabled(): array
	{
		return array_diff_key($this->on(), $this->unmet);
	}

	/**
	 * Returns the valid packs that are on, by name, whether or not their
	 * requirements are met.
	 *
	 * @return array<string, IconPack>
	 */
	public function on(): array
	{
		return array_filter($this->packs, $this->isEnabled(...), ARRAY_FILTER_USE_KEY);
	}

	/**
	 * The packs that are on but can't load, by name, each with the
	 * requirements it doesn't meet.
	 *
	 * @return array<string, list<Requirement>>
	 */
	public function unmet(): array
	{
		return $this->unmet;
	}

	/**
	 * The config with a pack turned on as well (D-440): added to the
	 * admin's saved list, or, without one, to config's.
	 */
	public function configWith(string $name): IconConfig
	{
		return $this->config->saved !== null
			? new IconConfig($this->config->enabled, [...$this->config->saved, $name])
			: new IconConfig([...$this->config->enabled, $name]);
	}

	/**
	 * Whether a pack is on: installed, and named by the admin's saved
	 * list, or, without one, from Composer or named by config.
	 */
	public function isEnabled(string $name): bool
	{
		$pack = $this->packs[$name] ?? null;

		return match (true) {
			$pack === null                => false,
			$this->config->saved !== null => in_array($name, $this->config->saved, true),
			default                       => $pack->source === IconPackSource::Composer || in_array($name, $this->config->enabled, true)
		};
	}

	/**
	 * Returns every valid pack, on or off, by name.
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

		return new self($packs, $invalid, $this->config, $this->unmet);
	}
}
