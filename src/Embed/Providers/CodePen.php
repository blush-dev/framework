<?php

/**
 * CodePen provider.
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
 * CodePen (D-633): pen links, framed as the pen's result. CodePen's
 * oEmbed turns away servers, so it isn't asked: the frame is built from
 * the link, and its name is the embed's title or label.
 */
final class CodePen extends EmbedProvider
{
	public function __construct()
	{
		parent::__construct(
			'codepen',
			'CodePen',
			['https://codepen.io/*/pen/*', 'https://codepen.io/*/full/*', 'https://codepen.io/*/embed/*'],
			null
		);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function frame(string $url, ?EmbedData $data): ?string
	{
		[$user, $pen] = self::link($url, ['codepen.io'], '#^/([a-z0-9_-]+)/(?:pen|full|embed)/([a-z0-9]+)/?$#i') ?? [null, null];

		return $user === null ? null : "https://codepen.io/{$user}/embed/{$pen}?default-tab=result";
	}
}
