<?php

/**
 * Regions.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Region;

use Throwable;
use Psr\Log\LoggerInterface;
use Blush\Core\AppConfig;
use Blush\Data\InvalidData;
use Blush\Field\Severity;
use Blush\Field\Violation;
use Blush\Region\Item\RegionItemFactory;
use Blush\Theme\SiteThemeData;
use Blush\Theme\ThemeChain;
use Blush\View\ViewContext;
use Blush\View\Views;

/**
 * The site's regions, rendered in a theme's locations (D-201).
 *
 * A theme declares locations in `theme.json` `regions`; its templates
 * print one by name. A location shows the site region of the same name
 * (`user/data/regions/{name}`), unless `user/data/theme.json` points it
 * at another (`"regions": {"aside": "sidebar"}`); with no site file, it
 * shows the theme's default items. A site file replaces the defaults,
 * even an empty one.
 *
 * Each item is one kind: a `component` (its other keys are props), a
 * `markdown` text, an `entry`'s body, or a `view` (its other keys are
 * data), or a kind an extension registered. Text may be a locale map
 * (D-202). An item that can't render is left out and logged; `check()`
 * reports item problems for `theme:check`.
 */
final class Regions
{
	/**
	 * Each theme's locations, by theme name.
	 *
	 * @var array<string, array<string, RegionLocation>>
	 */
	private array $locations = [];

	public function __construct(
		private readonly RegionLoader $files,
		private readonly RegionItemFactory $kinds,
		private readonly SiteThemeData $site,
		private readonly AppConfig $app,
		private readonly LoggerInterface $logger
	) {}

	/**
	 * Returns the HTML of the region a location shows, or `''`.
	 *
	 * @throws RegionException When the theme's location declaration is invalid.
	 * @throws InvalidData When a region file or the site's theme data can't be read.
	 */
	public function render(Views $views, ViewContext $context, string $location): string
	{
		$locale = $context->locale === '' ? $this->app->locale : $context->locale;
		$render = new RegionRender($views, $context, $locale, $this->app->locale);
		$html   = '';

		foreach ($this->items($views->chain, $location) as $index => $item) {
			$problem = $this->problem($item);

			if ($problem !== null) {
				$this->log($location, $index, $problem);

				continue;
			}

			/** @var array<string, mixed> $item Checked by `problem()`. */
			$key = $this->key($item);

			try {
				$html .= $this->kinds->make($key)->render($item[$key], $item, $render);
			} catch (Throwable $error) {
				$this->log($location, $index, $error->getMessage());
			}
		}

		return $html;
	}

	/**
	 * Returns whether a location shows any items.
	 *
	 * @throws RegionException
	 * @throws InvalidData
	 */
	public function has(ThemeChain $chain, string $location): bool
	{
		return $this->items($chain, $location) !== [];
	}

	/**
	 * Returns the items a location shows: the site region's, else the
	 * theme's defaults.
	 *
	 * @return list<mixed>
	 * @throws RegionException
	 * @throws InvalidData
	 */
	public function items(ThemeChain $chain, string $location): array
	{
		$file = $this->files->get($this->regionName($location));

		return $file !== null ? $file->items : ($this->locations($chain)[$location]->items ?? []);
	}

	/**
	 * Returns the locations a theme chain declares, by name. A child's
	 * declaration replaces its ancestor's of the same name.
	 *
	 * @return array<string, RegionLocation>
	 * @throws RegionException When a declaration is invalid.
	 */
	public function locations(ThemeChain $chain): array
	{
		$active = $chain->active()->name;

		if (isset($this->locations[$active])) {
			return $this->locations[$active];
		}

		$locations = [];

		foreach (array_reverse($chain->themes) as $theme) {
			foreach ($theme->regions() as $name => $declaration) {
				$locations[$name] = RegionLocation::fromDeclaration($name, $declaration, $theme->name);
			}
		}

		ksort($locations, SORT_STRING);

		return $this->locations[$active] = $locations;
	}

	/**
	 * Returns the name of the site region a location shows.
	 *
	 * @throws InvalidData
	 */
	public function regionName(string $location): string
	{
		return $this->site->regions()[$location] ?? $location;
	}

	/**
	 * Returns the site's regions, by name.
	 *
	 * @return array<string, RegionFile>
	 * @throws InvalidData
	 */
	public function files(): array
	{
		return $this->files->all();
	}

	/**
	 * Returns the problems with a theme chain's locations and the site's
	 * regions: invalid declarations, files and items with the wrong shape
	 * (warnings), and regions no location shows (notices). Items aren't
	 * rendered, so a missing component or view isn't caught here.
	 *
	 * @return list<Violation>
	 */
	public function check(ThemeChain $chain): array
	{
		try {
			$locations = $this->locations($chain);
			$files     = $this->files->all();
		} catch (RegionException | InvalidData $error) {
			return [new Violation('regions', $error->getMessage())];
		}

		$shown    = [];
		$problems = [];

		foreach ($locations as $name => $location) {
			try {
				$shown[$this->regionName($name)] = true;
			} catch (InvalidData $error) {
				return [new Violation('regions', $error->getMessage())];
			}

			foreach ($location->items as $index => $item) {
				$problem = $this->problem($item);

				if ($problem !== null) {
					$problems[] = new Violation("region location {$name}", sprintf('default item %d: %s', $index + 1, $problem), Severity::Warning);
				}
			}
		}

		foreach ($files as $name => $file) {
			foreach ($file->problems as $problem) {
				$problems[] = new Violation("region {$name}", $problem, Severity::Warning);
			}

			foreach ($file->items as $index => $item) {
				$problem = $this->problem($item);

				if ($problem !== null) {
					$problems[] = new Violation("region {$name}", sprintf('item %d: %s', $index + 1, $problem), Severity::Warning);
				}
			}

			if (! isset($shown[$name])) {
				$problems[] = new Violation("region {$name}", sprintf('No location of the "%s" theme shows it.', $chain->active()->name), Severity::Notice);
			}
		}

		return $problems;
	}

	/**
	 * Returns what's wrong with an item's shape, or `null`.
	 */
	private function problem(mixed $item): ?string
	{
		if (! is_array($item) || array_is_list($item)) {
			return 'must be a map of keys to values.';
		}

		$keys = array_values(array_intersect(array_map(strval(...), array_keys($item)), $this->kinds->keys()));

		if (count($keys) !== 1) {
			return sprintf('must have exactly one of %s.', implode(', ', array_map(static fn (string $key): string => "\"{$key}\"", $this->kinds->keys())));
		}

		/** @var array<string, mixed> $item */
		try {
			$invalid = $this->kinds->make($keys[0])->validate($item[$keys[0]], $item);
		} catch (RegionException $error) {
			return $error->getMessage();
		}

		return $invalid === null ? null : "\"{$keys[0]}\" {$invalid}";
	}

	/**
	 * Returns an item's kind key.
	 *
	 * @param array<string, mixed> $item
	 */
	private function key(array $item): string
	{
		return (string) array_first(array_intersect(array_keys($item), $this->kinds->keys()));
	}

	/**
	 * Logs an item that's left out.
	 */
	private function log(string $location, int $index, string $message): void
	{
		$this->logger->warning('Region {region} item {item}: {problem}', ['region' => $location, 'item' => $index + 1, 'problem' => $message]);
	}
}
