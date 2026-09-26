<?php

/**
 * Host files.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Export\Host;

use Blush\Core\Framework;

/**
 * Writes the files a kind of static host reads to learn what the export
 * needs (D-140): its redirects, the content types of index files that
 * aren't HTML, how folders map to URLs, and the 404 page. A format
 * returns its files by path in the export, and a notice for anything it
 * can't express (such as a redirect pattern it has no syntax for).
 */
abstract class HostFiles
{
	/**
	 * Returns the files, and notices for what they leave out.
	 */
	abstract public function files(HostContext $context): HostOutput;

	/**
	 * Returns the comment line each file starts with.
	 */
	protected static function banner(): string
	{
		return sprintf('Written by `%s build`; changes are lost on the next build.', Framework::BINARY);
	}
}
