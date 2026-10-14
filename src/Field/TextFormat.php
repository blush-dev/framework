<?php

/**
 * Text formats.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Field;

use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * What a text field's value must look like (`TextField`'s `format`), so
 * the type checks what a link, email, or icon control implies (D-769).
 */
enum TextFormat: string
{
	case Url   = 'url';
	case Email = 'email';
	case Icon  = 'icon';

	/**
	 * What a value of the format is, for messages.
	 */
	public function label(Messages $messages): Text
	{
		return match ($this) {
			self::Url   => $messages->t('field.text_format.url', 'a web address, such as https://example.com or /about'),
			self::Email => $messages->t('field.text_format.email', 'an email address'),
			self::Icon  => $messages->t('field.text_format.icon', 'an icon name, with no spaces')
		};
	}
}
