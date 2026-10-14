<?php

/**
 * Mail attachment.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Mail;

/**
 * A file sent with a message: its contents, the name it's given, and
 * its media type.
 */
final readonly class Attachment
{
	/**
	 * @throws MailException When the name is empty or more than one line.
	 */
	public function __construct(
		public string $contents,
		public string $name,
		public string $type = 'application/octet-stream'
	) {
		if (trim($name) === '' || preg_match('/[\r\n\/\\\\]/', $name) === 1) {
			throw new MailException(sprintf('An attachment needs a file name without line breaks or slashes; "%s" given.', $name));
		}
	}
}
