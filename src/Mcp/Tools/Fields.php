<?php

/**
 * Tool fields.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Mcp\Tools;

use Blush\Content\Editing\EditRefused;
use Blush\Content\Editing\Refusal;
use Blush\Mcp\ToolError;

/**
 * What the entry tools share: reading field values from input, and
 * saying an edit's refusal in words the AI can act on.
 */
final class Fields
{
	/**
	 * Returns field values by name.
	 *
	 * @return array<string, mixed>
	 */
	public static function from(mixed $fields): array
	{
		$values = [];

		foreach (is_array($fields) ? $fields : [] as $name => $value) {
			$values[(string) $name] = $value;
		}

		return $values;
	}

	/**
	 * Says why an edit was refused, with the field it's about and what
	 * to do next.
	 */
	public static function refused(EditRefused $refused): ToolError
	{
		$message = $refused->getMessage();

		if ($refused->field !== null) {
			$message = sprintf('%s (field "%s")', $message, $refused->field);
		}

		return new ToolError(match ($refused->refusal) {
			Refusal::Conflict         => "{$message} Read it again with get_entry, then make the change on what's there now.",
			Refusal::RevisionRequired => 'Send the "revision" get_entry answered, so no one else\'s change is lost.',
			default                   => $message
		});
	}
}
