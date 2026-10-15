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
use Blush\Field\FieldLibrary;
use Blush\Field\FieldSlot;
use Blush\Field\FieldTargetSource;
use Blush\Translation\Messages;

/**
 * The Settings screens as places fields go on (D-343),
 * `settings:{screen}`. Their settings share one store, keyed by field
 * name.
 */
final readonly class SettingsTargets implements FieldTargetSource
{
	public function __construct(
		private ContentTypes $types,
		private Messages $messages
	) {}

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
		return (string) $this->messages->t('settings.settings_targets.label', 'Settings screens');
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function fieldTargets(): iterable
	{
		foreach (SettingsScreen::cases() as $screen) {
			yield new SettingsTarget($screen, $this->types, $this->messages);
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function slots(): array
	{
		return [new FieldSlot('details', (string) $this->messages->t('settings.settings_targets.details.label', 'Details'), (string) $this->messages->t('settings.settings_targets.details.description', 'Settings of the site\'s own, beside the screen\'s.'))];
	}


	/**
	 * Returns every field extensions register on the Settings screens, by
	 * name, each once, screen by screen: the store `SiteSettings` reads.
	 * The site's own fields reach the screens with their editor (D-858).
	 *
	 * @return array<string, Field>
	 */
	public static function fields(FieldLibrary $library): array
	{
		$fields = [];

		foreach (SettingsScreen::cases() as $screen) {
			foreach ($library->registered->for(SettingsTarget::keyFor($screen)) as $registered) {
				$fields[$registered->field->name] ??= $registered->field;
			}
		}

		return $fields;
	}
}
