<?php

/**
 * Embed type.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Embed;

/**
 * The oEmbed response types.
 */
enum EmbedType: string
{
	case Photo = 'photo';
	case Video = 'video';
	case Link  = 'link';
	case Rich  = 'rich';
}
