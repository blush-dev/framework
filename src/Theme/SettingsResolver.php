<?php

/**
 * Settings resolver.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Theme;

use Blush\Data\InvalidData;
use Blush\Field\FieldContext;
use Blush\Field\FieldFactory;
use Blush\Field\InvalidSchema;
use Blush\Field\Schema;
use Blush\Field\Severity;
use Blush\Field\Violation;

/**
 * Resolves a theme chain's settings (D-022). A manifest's `settings` are
 * field definitions with the same types as content schemas (so the
 * future admin renders both with one form system):
 *
 * ```json
 * "settings": {
 *     "wide": { "type": "bool", "default": false, "label": "Wide layout" },
 *     "layout": { "type": "enum", "options": ["grid", "list"], "default": "list" }
 * }
 * ```
 *
 * Definitions merge down the chain (a child's replaces its ancestor's of
 * the same name), values come from `user/data/theme.json`, and anything
 * missing falls back to its default. Values that don't fit are reported,
 * not fatal: the default is used instead. Values for undeclared settings
 * are ignored.
 */
final class SettingsResolver
{
	/**
	 * Settings resolved so far, by active theme name.
	 *
	 * @var array<string, ThemeSettings>
	 */
	private array $settings = [];

	public function __construct(
		private readonly FieldFactory $fields,
		private readonly FieldContext $context,
		private readonly SiteThemeData $data
	) {}

	/**
	 * Returns a chain's settings.
	 *
	 * @throws ThemeException When a definition is invalid.
	 * @throws InvalidData When the site's theme data can't be read.
	 */
	public function for(ThemeChain $chain): ThemeSettings
	{
		return $this->settings[$chain->active()->name] ??= $this->resolve($chain);
	}

	/**
	 * Resolves a chain's settings.
	 *
	 * @throws ThemeException
	 * @throws InvalidData
	 */
	private function resolve(ThemeChain $chain): ThemeSettings
	{
		$schema = new Schema();
		$names  = [];

		foreach (array_reverse($chain->themes) as $theme) {
			foreach ($theme->settings() as $name => $definition) {
				$names[$name] = true;

				try {
					$schema = $schema->with($this->fields->fromArray([...$definition, 'name' => $name]));
				} catch (InvalidSchema $error) {
					throw new ThemeException(sprintf('The "%s" theme\'s setting "%s" is invalid: %s', $theme->name, $name, $error->getMessage()), 0, $error);
				}
			}
		}

		$values = array_intersect_key($this->data->settings(), $names);
		$first  = $schema->resolve($values, $this->context);
		$failed = array_map(static fn (Violation $violation): string => $violation->field, $first->violations(Severity::Error));

		// A value that doesn't fit falls back to its default.
		$result = $failed === [] ? $first : $schema->resolve(array_diff_key($values, array_flip($failed)), $this->context);

		return new ThemeSettings($schema, $schema->hydrate($result->values, $this->context), $first->violations(Severity::Warning));
	}
}
