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
 * The built-in embed providers (the "Type enum" of D-019).
 */
enum ProviderType: string
{
	case YouTube     = 'youtube';
	case Vimeo       = 'vimeo';
	case Ted         = 'ted';
	case CodePen     = 'codepen';
	case Spotify     = 'spotify';
	case SoundCloud  = 'soundcloud';
	case Flickr      = 'flickr';
	case Twitch      = 'twitch';
	case TikTok      = 'tiktok';
	case X           = 'x';
	case Reddit      = 'reddit';

	/**
	 * Returns the provider's class.
	 *
	 * @return class-string<EmbedProvider>
	 */
	public function className(): string
	{
		return match ($this) {
			self::YouTube     => YouTube::class,
			self::Vimeo       => Vimeo::class,
			self::Ted         => Ted::class,
			self::CodePen     => CodePen::class,
			self::Spotify     => Spotify::class,
			self::SoundCloud  => SoundCloud::class,
			self::Flickr      => Flickr::class,
			self::Twitch      => Twitch::class,
			self::TikTok      => TikTok::class,
			self::X           => X::class,
			self::Reddit      => Reddit::class
		};
	}
}
