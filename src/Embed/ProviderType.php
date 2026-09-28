<?php

/**
 * Provider type.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Embed;

use Blush\Embed\Providers\Vimeo;
use Blush\Embed\Providers\YouTube;

/**
 * The built-in embed providers (the "Type enum" of D-019).
 */
enum ProviderType: string
{
	case YouTube = 'youtube';
	case Vimeo   = 'vimeo';

	/**
	 * Returns the provider's class.
	 *
	 * @return class-string<EmbedProvider>
	 */
	public function className(): string
	{
		return match ($this) {
			self::YouTube => YouTube::class,
			self::Vimeo   => Vimeo::class
		};
	}
}
