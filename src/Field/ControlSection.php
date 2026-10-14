<?php

/**
 * Control picker sections.
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
 * The sections of the admin's control picker (the fields sketch), in
 * order: structure first, since a group is the first thing someone after
 * a shape looks for.
 */
enum ControlSection: string
{
	case Structure = 'structure';
	case Text      = 'text';
	case Choices   = 'choices';
	case Numbers   = 'numbers';
	case Media     = 'media';

	/**
	 * A heading for people.
	 */
	public function label(Messages $messages): Text
	{
		return match ($this) {
			self::Structure => $messages->t('field.control_section.structure', 'Structure'),
			self::Text      => $messages->t('field.control_section.text', 'Text'),
			self::Choices   => $messages->t('field.control_section.choices', 'Choices'),
			self::Numbers   => $messages->t('field.control_section.numbers', 'Numbers, dates, and color'),
			self::Media     => $messages->t('field.control_section.media', 'Media')
		};
	}
}
