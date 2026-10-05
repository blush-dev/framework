<?php

/**
 * Mention resolver.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Markdown;

/**
 * Finds where a mention, `@name`, links (`MarkdownConfig::$mentions`).
 * The content layer binds `Content\ProfileMentions`, which links a
 * published profile's slug to its page. A mention with no address stays
 * the text it was written as.
 */
interface MentionResolver
{
	/**
	 * Returns the URL (or root-relative path) a mention of the name links
	 * to, or `null` when it isn't anyone's.
	 */
	public function url(string $name): ?string;
}
