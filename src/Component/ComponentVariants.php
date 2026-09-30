<?php

/**
 * Component variants.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Component;

use InvalidArgumentException;
use Blush\Component\Events\ComponentVariantsCollecting;
use Blush\Event\Dispatcher;
use Blush\Theme\ThemeChain;
use Blush\Theme\Themes;

/**
 * Collects each component's variants (D-266): the ones it declared when
 * it registered, then whatever `ComponentVariantsCollecting` listeners
 * add or remove (once per component), then those the chain's themes list
 * in their `theme.json`. A variant from a theme outside the chain is left
 * out, so a theme's variants apply only while it's active.
 *
 * A Markdown image isn't a component, but a theme can offer it variants
 * too (D-268): `theme.json`'s `variants.image`, each a class the image's
 * attributes carry (`{.stretch-wide}`), which the site puts on its
 * figure. Only themes declare them.
 */
final class ComponentVariants
{
	/**
	 * The key a theme's `variants` lists Markdown images' variants under.
	 */
	public const string IMAGE = 'image';

	/**
	 * Collected variants, by component, before the chain is applied.
	 *
	 * @var array<string, list<Variant>>
	 */
	private array $collected = [];

	public function __construct(
		private readonly ComponentRegistry $components,
		private readonly Dispatcher $events,
		private readonly Themes $themes
	) {}

	/**
	 * Returns a component's variants for a chain, Default not included.
	 *
	 * @return list<Variant>
	 */
	public function for(ComponentName $name, ThemeChain $chain): array
	{
		$variants = [];

		foreach ($this->collected($name) as $variant) {
			$variants[$variant->name] = $variant;
		}

		foreach (array_reverse($chain->themes) as $theme) {
			foreach (self::fromManifest($theme->variants(), $name, $theme->slug) as $variant) {
				$variants[$variant->name] = $variant;
			}
		}

		return array_values(array_filter($variants, fn (Variant $variant): bool => ! $this->themes->isOutside($variant->registrant, $chain)));
	}

	/**
	 * Returns Markdown images' variants for a chain, Default not included:
	 * the ones its themes list, the child's winning a name they share.
	 * They're classes, which only a stylesheet gives a look, and the
	 * framework default theme's stylesheet loads only while it's the
	 * active theme, so only then are its variants included.
	 *
	 * @return list<Variant>
	 */
	public function forImages(ThemeChain $chain): array
	{
		$variants = [];
		$themes   = count($chain->themes) > 1 ? array_slice($chain->themes, 0, -1) : $chain->themes;

		foreach (array_reverse($themes) as $theme) {
			foreach ($theme->variants()[self::IMAGE] ?? [] as $item) {
				$variant = self::manifestItem($item, $theme->slug);

				if ($variant !== null) {
					$variants[$variant->name] = $variant;
				}
			}
		}

		return array_values($variants);
	}

	/**
	 * Returns the variant a component uses for a requested name under a
	 * chain, or `null` for Default: when none was asked for, when it's
	 * `default`, or when the component doesn't have it.
	 */
	public function resolve(ComponentName $name, ThemeChain $chain, mixed $requested): ?Variant
	{
		if (! is_string($requested) || trim($requested) === '' || trim($requested) === Variant::DEFAULT) {
			return null;
		}

		return array_find($this->for($name, $chain), static fn (Variant $variant): bool => $variant->name === trim($requested));
	}

	/**
	 * Returns the variants a theme's manifest lists for a component, from
	 * its `variants` map (keys are full names or core short names; each
	 * value a list of names or `{"name", "modifier"}` objects). Invalid
	 * entries are skipped; `theme:check` reports them.
	 *
	 * @param  array<string, list<mixed>> $declared
	 * @return list<Variant>
	 */
	public static function fromManifest(array $declared, ComponentName $name, string $registrant): array
	{
		$variants = [];

		foreach ($declared as $component => $list) {
			if ((string) ComponentName::parse($component) !== (string) $name) {
				continue;
			}

			foreach ($list as $item) {
				$variant = self::manifestItem($item, $registrant);

				if ($variant !== null) {
					$variants[] = $variant;
				}
			}
		}

		return $variants;
	}

	/**
	 * Returns the variant a manifest entry describes, or `null`.
	 */
	public static function manifestItem(mixed $item, string $registrant): ?Variant
	{
		$name     = is_array($item) ? $item['name'] ?? null : $item;
		$modifier = is_array($item) ? $item['modifier'] ?? null : null;

		if (! is_string($name) || ($modifier !== null && ! is_string($modifier))) {
			return null;
		}

		try {
			return new Variant($name, $registrant, $modifier);
		} catch (InvalidArgumentException) {
			return null;
		}
	}

	/**
	 * Returns a component's declared variants and the event's changes,
	 * collected once.
	 *
	 * @return list<Variant>
	 */
	private function collected(ComponentName $name): array
	{
		$key = (string) $name;

		if (! isset($this->collected[$key])) {
			$event = new ComponentVariantsCollecting($name, $this->components->get($key)?->variants() ?? []);

			$this->events->dispatch($event);

			$this->collected[$key] = $event->variants();
		}

		return $this->collected[$key];
	}
}
