<?php

/**
 * Settings targets.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Settings;

use Override;
use Blush\Content\Type\ContentTypes;
use Blush\Field\Field;
use Blush\Field\FieldSets;
use Blush\Field\FieldSlot;
use Blush\Field\FieldTargetSource;

/**
 * The Settings screens as places field sets attach to (D-343),
 * `settings:{screen}`. Their settings share one store, keyed by field
 * name, so two sets can't use one name on different screens
 * (`conflicts()`).
 */
final readonly class SettingsTargets implements FieldTargetSource
{
	public function __construct(private ContentTypes $types)
	{}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function kind(): string
	{
		return SettingsTarget::KIND;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function label(): string
	{
		return 'Settings screens';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function fieldTargets(): iterable
	{
		foreach (SettingsScreen::cases() as $screen) {
			yield new SettingsTarget($screen, $this->types);
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function slots(): array
	{
		return [new FieldSlot('details', 'Details', 'Settings of the site\'s own, beside the screen\'s.')];
	}

	/**
	 * @inheritDoc
	 *
	 * A set on several screens has its fields on each, which is one
	 * setting; two sets using one name aren't.
	 */
	#[Override]
	public function conflicts(FieldSets $sets): array
	{
		$owners    = [];
		$conflicts = [];

		foreach (SettingsScreen::cases() as $screen) {
			foreach ($sets->for(SettingsTarget::keyFor($screen)) as $set) {
				foreach ($set->schema->fields as $field) {
					foreach ([$field->name, ...$field->aliases] as $key) {
						$owner = $owners[$key] ?? null;

						if ($owner !== null && $owner !== $set->name) {
							$conflicts[$set->name] ??= sprintf('Field sets "%s" and "%s" both add a "%s" setting; settings share one store, so rename one.', $owner, $set->name, $key);
						}

						$owners[$key] ??= $set->name;
					}
				}
			}
		}

		return $conflicts;
	}

	/**
	 * Returns every field sets add to the Settings screens, by name, each
	 * once, screen by screen: the store `SiteSettings` reads.
	 *
	 * @return array<string, Field>
	 */
	public static function fields(FieldSets $sets): array
	{
		$fields = [];

		foreach (SettingsScreen::cases() as $screen) {
			foreach ($sets->for(SettingsTarget::keyFor($screen)) as $set) {
				foreach ($set->schema->fields as $name => $field) {
					$fields[$name] ??= $field;
				}
			}
		}

		return $fields;
	}
}
