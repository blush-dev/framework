<?php

/**
 * Menus.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Menu;

use Psr\Log\LoggerInterface;
use Blush\Core\AppConfig;
use Blush\Data\InvalidData;
use Blush\Field\FieldContext;
use Blush\Field\FieldFactory;
use Blush\Field\InvalidSchema;
use Blush\Field\Schema;
use Blush\Field\Severity;
use Blush\Field\Violation;
use Blush\Menu\Link\MenuLinkFactory;
use Blush\Menu\Link\UnresolvedLink;
use Blush\Theme\SiteThemeData;
use Blush\Theme\ThemeChain;
use Blush\Translation\LocaleMap;

/**
 * The site's menus, resolved for a theme's locations (D-199, D-200).
 *
 * A theme declares locations in `theme.json` `menus`; its templates ask
 * for one by name. A location shows the site menu of the same name
 * (`user/data/menus/{name}`), unless `user/data/theme.json` points it at
 * another (`"menus": {"main": "primary"}`). An undeclared location works
 * the same way, with no label, depth limit, or fields.
 *
 * Resolving a menu turns each item's link (`entry`, `term`,
 * `collection`, `route`, `url`, or a kind an extension registered) into
 * a URL and label, picks text in the page's locale (D-202), reads the
 * location's declared fields, and stops at its depth. An item that
 * doesn't resolve (a missing or unpublished entry, an unknown route, a
 * missing label) is left out, with its children, and logged; `check()`
 * reports the same problems for `menu:list` and `theme:check`.
 *
 * Resolved menus are kept per theme, location, and locale for the
 * process; the page cache keeps whole pages.
 */
final class Menus
{
	/**
	 * The keys every item may have, besides its link and the location's
	 * fields.
	 *
	 * @var list<string>
	 */
	public const array ITEM_KEYS = ['label', 'children', 'icon', 'description', 'image', 'badge', 'class', 'rel'];

	/**
	 * Menus resolved so far, by theme, location, and locale.
	 *
	 * @var array<string, ?Menu>
	 */
	private array $resolved = [];

	/**
	 * Each theme's locations, by theme name.
	 *
	 * @var array<string, array<string, MenuLocation>>
	 */
	private array $locations = [];

	/**
	 * Each location's field schema, by theme name and location.
	 *
	 * @var array<string, Schema>
	 */
	private array $schemas = [];

	public function __construct(
		private readonly MenuLoader $files,
		private readonly MenuLinkFactory $links,
		private readonly SiteThemeData $site,
		private readonly FieldFactory $fields,
		private readonly FieldContext $fieldContext,
		private readonly AppConfig $app,
		private readonly LoggerInterface $logger
	) {}

	/**
	 * Returns the menu a location shows, marked for no page, or `null`
	 * when it shows none: the site has no menu for it, or none of the
	 * menu's items resolve. `$locale` is the page's (the site's when
	 * `''`).
	 *
	 * @throws MenuException When the theme's location declaration is invalid.
	 * @throws InvalidData When a menu file or the site's theme data can't be read.
	 */
	public function forLocation(ThemeChain $chain, string $location, string $locale = ''): ?Menu
	{
		$locale = $locale === '' ? $this->app->locale : $locale;
		$key    = "{$chain->active()->name}|{$location}|{$locale}";

		if (array_key_exists($key, $this->resolved)) {
			return $this->resolved[$key];
		}

		$problems = [];
		$menu     = $this->resolve($chain, $location, $locale, $problems);

		foreach ($problems as $problem) {
			$this->logger->warning('Menu {menu}: {problem}', ['menu' => $problem->field, 'problem' => $problem->message]);
		}

		return $this->resolved[$key] = $menu === null || $menu->items === [] ? null : $menu;
	}

	/**
	 * Returns the locations a theme chain declares, by name. A child's
	 * declaration replaces its ancestor's of the same name.
	 *
	 * @return array<string, MenuLocation>
	 * @throws MenuException When a declaration is invalid.
	 */
	public function locations(ThemeChain $chain): array
	{
		$active = $chain->active()->name;

		if (isset($this->locations[$active])) {
			return $this->locations[$active];
		}

		$locations = [];

		foreach (array_reverse($chain->themes) as $theme) {
			foreach ($theme->menus() as $name => $declaration) {
				$locations[$name] = MenuLocation::fromDeclaration($name, $declaration, $theme->name);
			}
		}

		ksort($locations, SORT_STRING);

		return $this->locations[$active] = $locations;
	}

	/**
	 * Returns the name of the site menu a location shows.
	 *
	 * @throws InvalidData
	 */
	public function menuName(string $location): string
	{
		return $this->site->menus()[$location] ?? $location;
	}

	/**
	 * Returns the site's menus, by name.
	 *
	 * @return array<string, MenuFile>
	 * @throws InvalidData
	 */
	public function files(): array
	{
		return $this->files->all();
	}

	/**
	 * Returns the problems with a theme chain's locations and the site's
	 * menus: invalid declarations (errors), menu files with the wrong
	 * shape and items that don't resolve (warnings), and menus no location
	 * shows (notices). A location the site hasn't filled isn't a problem;
	 * it shows nothing.
	 *
	 * @return list<Violation>
	 */
	public function check(ThemeChain $chain): array
	{
		try {
			$locations = $this->locations($chain);
			$files     = $this->files->all();
			$shown     = [];
			$problems  = [];

			foreach ($locations as $name => $location) {
				$menuName = $this->menuName($name);

				if (isset($files[$menuName])) {
					$shown[$menuName] = true;

					$this->resolve($chain, $name, $this->app->locale, $problems);
				}
			}

			foreach ($files as $name => $file) {
				if (! isset($shown[$name])) {
					$problems[] = new Violation("menu {$name}", sprintf('No location of the "%s" theme shows it.', $chain->active()->name), Severity::Notice);

					// Resolve it on its own, to check its items.
					$this->build(new MenuLocation($name), $file, $chain->active()->name, $this->app->locale, $problems);
				}
			}

			return $problems;
		} catch (MenuException | InvalidData $error) {
			return [new Violation('menus', $error->getMessage())];
		}
	}

	/**
	 * Resolves the menu a location shows, collecting problems.
	 *
	 * @param  list<Violation> $problems
	 * @throws MenuException
	 * @throws InvalidData
	 */
	private function resolve(ThemeChain $chain, string $location, string $locale, array &$problems): ?Menu
	{
		$file = $this->files->get($this->menuName($location));

		if ($file === null) {
			return null;
		}

		return $this->build($this->locations($chain)[$location] ?? new MenuLocation($location), $file, $chain->active()->name, $locale, $problems);
	}

	/**
	 * Builds a menu from its file for a location.
	 *
	 * @param  list<Violation> $problems
	 * @throws MenuException
	 */
	private function build(MenuLocation $location, MenuFile $file, string $theme, string $locale, array &$problems): Menu
	{
		$subject = "menu {$file->name}";

		foreach ($file->problems as $problem) {
			$problems[] = new Violation($subject, $problem, Severity::Warning);
		}

		$label = LocaleMap::text($file->label, $locale, $this->app->locale);

		if ($file->label !== null && $label === null) {
			$problems[] = new Violation($subject, '"label" must be text or a map of locales to text.', Severity::Warning);
		}

		$schema = $this->schema($location, $theme);
		$items  = $this->items($file->items, $location, $schema, $locale, 1, '', $subject, $problems);
		$label  = trim($label ?? '');

		return new Menu($location->name, $file->name, $label !== '' ? $label : $location->label, $items);
	}

	/**
	 * Resolves a list of items at a level (1 is the top).
	 *
	 * @param  list<mixed>     $items
	 * @param  list<Violation> $problems
	 * @return list<MenuItem>
	 * @throws MenuException
	 */
	private function items(array $items, MenuLocation $location, Schema $schema, string $locale, int $level, string $trail, string $subject, array &$problems): array
	{
		$resolved = [];

		foreach ($items as $index => $item) {
			$position = ltrim("{$trail}." . ($index + 1), '.');
			$built    = $this->item($item, $location, $schema, $locale, $level, $position, $subject, $problems);

			if ($built !== null) {
				$resolved[] = $built;
			}
		}

		return $resolved;
	}

	/**
	 * Resolves one item, or returns `null` (with a problem) when it's left
	 * out.
	 *
	 * @param  list<Violation> $problems
	 * @throws MenuException
	 */
	private function item(mixed $item, MenuLocation $location, Schema $schema, string $locale, int $level, string $position, string $subject, array &$problems): ?MenuItem
	{
		$problem = static function (string $message) use (&$problems, $subject, $position): null {
			$problems[] = new Violation($subject, "item {$position}: {$message}", Severity::Warning);

			return null;
		};

		if (! is_array($item) || array_is_list($item)) {
			return $problem('must be a map of keys to values.');
		}

		/** @var array<string, mixed> $item */
		$linkKeys = array_values(array_intersect(array_keys($item), $this->links->keys()));

		if (count($linkKeys) > 1) {
			return $problem(sprintf('has more than one link (%s); use one.', implode(', ', $linkKeys)));
		}

		$target  = null;
		$linkKey = $linkKeys[0] ?? null;
		$ownKeys = self::ITEM_KEYS;

		if ($linkKey !== null) {
			$link    = $this->links->make($linkKey);
			$invalid = $link->validate($item[$linkKey], $item);
			$ownKeys = [...$ownKeys, $linkKey, ...$link->keys()];

			if ($invalid !== null) {
				return $problem("\"{$linkKey}\" {$invalid}");
			}

			try {
				$value  = $item[$linkKey];
				$target = $link->resolve(is_scalar($value) ? trim((string) $value) : '', $item, $locale);
			} catch (UnresolvedLink $error) {
				return $problem($error->getMessage());
			}
		}

		$label = LocaleMap::text($item['label'] ?? null, $locale, $this->app->locale);

		if (isset($item['label']) && $label === null) {
			return $problem('"label" must be text or a map of locales to text.');
		}

		$label = trim($label ?? $target->label ?? '');

		if ($label === '') {
			return $problem('needs a "label".');
		}

		$children = $item['children'] ?? [];

		if (! is_array($children) || ! array_is_list($children)) {
			$problem('"children" must be a list.');
			$children = [];
		}

		$children = $location->depth !== null && $level >= $location->depth
			? []
			: $this->items($children, $location, $schema, $locale, $level + 1, $position, $subject, $problems);

		if ($target === null && $children === []) {
			return $problem('has no link and no children.');
		}

		$text = [];

		foreach (['icon', 'description', 'image', 'badge', 'class', 'rel'] as $key) {
			$value      = LocaleMap::text($item[$key] ?? '', $locale, $this->app->locale);
			$text[$key] = trim($value ?? '');

			if ($value === null) {
				$problem("\"{$key}\" must be text.");
			}
		}

		return new MenuItem(
			label: $label,
			url: $target?->url,
			icon: $text['icon'],
			description: $text['description'],
			image: $text['image'],
			badge: $text['badge'],
			class: $text['class'],
			rel: $text['rel'],
			fields: $this->fields(array_diff_key($item, array_flip($ownKeys)), $schema, $locale, $problem),
			children: $children
		);
	}

	/**
	 * Returns an item's values for the location's fields, typed. A key the
	 * location doesn't declare, or a value that doesn't fit, is a problem;
	 * a field that's missing or doesn't fit gets its default.
	 *
	 * @param  array<string, mixed>   $values
	 * @param  callable(string): null $problem
	 * @return array<string, mixed>
	 */
	private function fields(array $values, Schema $schema, string $locale, callable $problem): array
	{
		foreach (array_keys($values) as $key) {
			if (! $schema->has($key)) {
				$problem(sprintf('"%s" isn\'t a menu item key or a field the location declares.', $key));
				unset($values[$key]);
			}
		}

		$values = LocaleMap::resolve($values, $locale, $this->app->locale);
		$result = $schema->resolve($values, $this->fieldContext);
		$failed = [];

		foreach ($result->violations(Severity::Warning) as $violation) {
			$problem("\"{$violation->field}\" {$violation->message}");
			$failed[$violation->field] = true;
		}

		if ($failed !== []) {
			$result = $schema->resolve(array_diff_key($values, $failed), $this->fieldContext);
		}

		return $schema->hydrate($result->values, $this->fieldContext);
	}

	/**
	 * Returns a location's field schema.
	 *
	 * @throws MenuException When a field definition is invalid.
	 */
	private function schema(MenuLocation $location, string $theme): Schema
	{
		if ($location->fields === []) {
			return new Schema();
		}

		$key = "{$theme}|{$location->name}";

		if (isset($this->schemas[$key])) {
			return $this->schemas[$key];
		}

		$fields = [];

		foreach ($location->fields as $name => $definition) {
			$fields[] = [...$definition, 'name' => $name];
		}

		try {
			return $this->schemas[$key] = $this->fields->schema($fields);
		} catch (InvalidSchema $error) {
			throw new MenuException(sprintf('The menu location "%s" has an invalid field: %s', $location->name, $error->getMessage()), 0, $error);
		}
	}
}
