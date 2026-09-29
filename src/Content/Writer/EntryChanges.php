<?php

/**
 * Entry changes.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Writer;

/**
 * What to change in an entry's file: front matter values to set (by
 * field name; a field's alias already in the file is kept), keys to
 * remove, and the body, if it changes. Values must be plain data:
 * strings, numbers, booleans, `null`, and lists or maps of them. Dates
 * are strings.
 */
final readonly class EntryChanges
{
	/**
	 * @param array<string, mixed> $set
	 * @param list<string>         $remove
	 * @throws WriteException When a value isn't plain data, or a key is
	 *                        both set and removed.
	 */
	public function __construct(
		public array $set = [],
		public array $remove = [],
		public ?string $body = null
	) {
		foreach ($set as $key => $value) {
			if (! self::isData($value)) {
				throw new WriteException(sprintf('Front matter "%s" must be plain data (text, numbers, true or false, null, or lists and maps of them).', $key));
			}
		}

		$both = array_intersect(array_map(strval(...), array_keys($set)), $remove);

		if ($both !== []) {
			throw new WriteException(sprintf('Front matter "%s" can\'t be both set and removed.', implode('", "', $both)));
		}
	}

	/**
	 * Whether there's nothing to change.
	 */
	public function isEmpty(): bool
	{
		return $this->set === [] && $this->remove === [] && $this->body === null;
	}

	/**
	 * Whether a value is plain data.
	 */
	private static function isData(mixed $value): bool
	{
		return is_scalar($value) || $value === null || (is_array($value) && array_all($value, static fn (mixed $item): bool => self::isData($item)));
	}
}
