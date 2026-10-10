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

use Blush\Embed\Providers\CodePen;
use Blush\Embed\Providers\Flickr;
use Blush\Embed\Providers\Reddit;
use Blush\Embed\Providers\SoundCloud;
use Blush\Embed\Providers\Spotify;
use Blush\Embed\Providers\Ted;
use Blush\Embed\Providers\TikTok;
use Blush\Embed\Providers\Twitch;
use Blush\Embed\Providers\Vimeo;
use Blush\Embed\Providers\X;
use Blush\Embed\Providers\YouTube;

/**
 * The built-in embed providers (the "Type enum" of D-019), by label
 * (D-695), as they're registered.
 */
enum ProviderType: string
{
	case CodePen     = 'codepen';
	case Flickr      = 'flickr';
	case Reddit      = 'reddit';
	case SoundCloud  = 'soundcloud';
	case Spotify     = 'spotify';
	case Ted         = 'ted';
	case TikTok      = 'tiktok';
	case Twitch      = 'twitch';
	case Vimeo       = 'vimeo';
	case X           = 'x';
	case YouTube     = 'youtube';

	/**
	 * Returns the provider's class.
	 *
	 * @return class-string<EmbedProvider>
	 */
	public function className(): string
	{
		return match ($this) {
			self::CodePen     => CodePen::class,
			self::Flickr      => Flickr::class,
			self::Reddit      => Reddit::class,
			self::SoundCloud  => SoundCloud::class,
			self::Spotify     => Spotify::class,
			self::Ted         => Ted::class,
			self::TikTok      => TikTok::class,
			self::Twitch      => Twitch::class,
			self::Vimeo       => Vimeo::class,
			self::X           => X::class,
			self::YouTube     => YouTube::class
		};
	}
}
