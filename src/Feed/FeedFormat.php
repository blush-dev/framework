<?php

/**
 * Feed format.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Feed;

/**
 * The feed formats Blush writes (D-029).
 */
enum FeedFormat: string
{
	case Rss  = 'rss';
	case Atom = 'atom';
	case Json = 'json';

	/**
	 * Returns the route key suffix: RSS is 1.x's plain `.feed`.
	 */
	public function routeSuffix(): string
	{
		return match ($this) {
			self::Rss  => '',
			self::Atom => '.atom',
			self::Json => '.json'
		};
	}

	/**
	 * Returns the media type.
	 */
	public function mediaType(): string
	{
		return match ($this) {
			self::Rss  => 'application/rss+xml',
			self::Atom => 'application/atom+xml',
			self::Json => 'application/feed+json'
		};
	}

	/**
	 * Returns a human-readable name, for `<link title>`.
	 */
	public function label(): string
	{
		return match ($this) {
			self::Rss  => 'RSS',
			self::Atom => 'Atom',
			self::Json => 'JSON Feed'
		};
	}
}
