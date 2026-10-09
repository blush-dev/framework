<?php

/**
 * Settings target.
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
use Blush\Field\FieldTarget;
use Blush\Field\Schema;

/**
 * A Settings screen as a place field sets attach to (D-343):
 * `settings:general`, `settings:reading`, or `settings:search`. Its own
 * fields are its built-in settings (`Setting::field()`); a set's settings
 * are saved in the `site` group of saved settings (`user/data/settings/site.json`), which holds
 * any value, so it takes every field.
 */
final readonly class SettingsTarget implements FieldTarget
{
	/**
	 * The kind of target, before the colon.
	 */
	public const string KIND = 'settings';

	public function __construct(
		private SettingsScreen $screen,
		private ContentTypes $types
	) {}

	/**
	 * Returns the target key for a screen.
	 */
	public static function keyFor(SettingsScreen $screen): string
	{
		return self::KIND . ':' . $screen->value;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function key(): string
	{
		return self::keyFor($this->screen);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function label(): string
	{
		return $this->screen->label();
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function accepts(Field $field): bool
	{
		return true;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function schema(): Schema
	{
		return new Schema(array_map(fn (Setting $setting): Field => $setting->field($this->types), $this->screen->settings()));
	}
}
