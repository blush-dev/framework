<?php

/**
 * Email control.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Field\Controls;

use Override;
use Blush\Field\ControlSection;
use Blush\Field\Field;
use Blush\Field\Fields\TextField;
use Blush\Field\TextFormat;

/**
 * An email address: a text field whose format is `email`.
 */
final class Email extends TextBox
{
	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function name(): string
	{
		return 'email';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function label(): string
	{
		return 'Email';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function description(): string
	{
		return 'An email address.';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function section(): ControlSection
	{
		return ControlSection::Text;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function type(): string
	{
		return 'text';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function implies(): array
	{
		return ['format' => TextFormat::Email->value];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function fits(Field $field): bool
	{
		return $field instanceof TextField && $field->format === TextFormat::Email;
	}
}
