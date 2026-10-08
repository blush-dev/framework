<?php

/**
 * TED provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Embed\Providers;

use Override;
use Blush\Embed\EmbedData;
use Blush\Embed\EmbedProvider;

/**
 * TED (D-633): talk links, framed from `embed.ted.com`. oEmbed is asked
 * for the size and title.
 */
final class Ted extends EmbedProvider
{
	public function __construct()
	{
		parent::__construct(
			'ted',
			'TED',
			['https://www.ted.com/talks/*', 'https://ted.com/talks/*', 'https://embed.ted.com/talks/*'],
			'https://www.ted.com/services/v1/oembed.json'
		);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function frame(string $url, ?EmbedData $data): ?string
	{
		[$talk] = self::link($url, ['www.ted.com', 'ted.com', 'embed.ted.com'], '#^/talks/([a-z0-9_]+)/?$#i') ?? [null];

		return $talk === null ? null : "https://embed.ted.com/talks/{$talk}";
	}
}
