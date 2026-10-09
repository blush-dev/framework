<?php

/**
 * Profile mentions.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content;

use Closure;
use Override;
use Blush\Container\Attributes\Defer;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Type\ContentTypes;
use Blush\Markdown\MentionResolver;

/**
 * Links a mention, `@jane`, to the profile with that slug (D-493): its
 * own page, when it's published and the profiles type has URLs. Anyone
 * else stays text. A body is rendered again when content changes (its
 * cache key holds the content version), so publishing a profile links
 * the mentions of it.
 *
 * The repository is resolved on first use, since it depends (through the
 * entry hydrator) on the Markdown parser that depends on this.
 */
final readonly class ProfileMentions implements MentionResolver
{
	/**
	 * @param Closure(): Entries $content
	 */
	public function __construct(
		private ContentTypes $types,
		private ContentUrls $urls,
		#[Defer(Entries::class)] private Closure $content
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function url(string $name): ?string
	{
		$profiles = $this->types->profiles();

		if ($profiles === null || ! $profiles->public) {
			return null;
		}

		$profile = ($this->content)()->term($profiles->name, strtolower($name));

		return $profile !== null && $profile->isPublished() && $profile->isRoutable() ? $this->urls->profile($profile->slug) : null;
	}
}
