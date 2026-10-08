<?php

/**
 * Record.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Storage\Record;

use DateTimeInterface;
use Blush\Support\Uuid;

/**
 * What a storage driver keeps (D-643): an id, its values by key, and an
 * optional body. The id is a UUID, identity for every driver (D-606);
 * the values are what a JSON object holds (text, numbers, true and
 * false, null, lists, and maps), and never `id` or `body`, which a
 * query reaches as themselves.
 */
final readonly class Record
{
	/**
	 * Keys a record's values can't use.
	 */
	public const array RESERVED = ['id', 'body'];

	/**
	 * The id, lowercase.
	 */
	public string $id;

	/**
	 * @param  array<string, mixed> $values
	 * @throws InvalidRecord When the id isn't a UUID or a value's key is reserved.
	 */
	public function __construct(
		string $id,
		public array $values = [],
		public ?string $body = null
	) {
		if (! Uuid::isValid($id)) {
			throw new InvalidRecord(sprintf('"%s" isn\'t a record id; ids are UUIDs.', $id));
		}

		foreach (array_keys($values) as $key) {
			if ($key === '' || in_array($key, self::RESERVED, true)) {
				throw new InvalidRecord(sprintf('A record\'s values can\'t use the key "%s".', $key));
			}
		}

		$this->id = strtolower($id);
	}

	/**
	 * Makes a new record with a new id (a version 7 UUID, D-477).
	 *
	 * @param  array<string, mixed> $values
	 * @throws InvalidRecord When a value's key is reserved.
	 */
	public static function create(DateTimeInterface $now, array $values = [], ?string $body = null): self
	{
		return new self(Uuid::v7($now), $values, $body);
	}

	/**
	 * Returns a value by key, or `null` when there's none: `id`, `body`,
	 * a value's key, or keys joined by dots to reach into nested values
	 * (`seo.title`). A key with a dot in it is found as it is first.
	 */
	public function value(string $key): mixed
	{
		if ($key === 'id') {
			return $this->id;
		}

		if ($key === 'body') {
			return $this->body;
		}

		if (array_key_exists($key, $this->values)) {
			return $this->values[$key];
		}

		$value = $this->values;

		foreach (explode('.', $key) as $segment) {
			if (! is_array($value) || ! array_key_exists($segment, $value)) {
				return null;
			}

			$value = $value[$segment];
		}

		return $value;
	}

	/**
	 * Returns the record with a value set.
	 *
	 * @throws InvalidRecord When the key is reserved.
	 */
	#[\NoDiscard]
	public function with(string $key, mixed $value): self
	{
		return new self($this->id, [...$this->values, $key => $value], $this->body);
	}

	/**
	 * Returns the record with other values.
	 *
	 * @param  array<string, mixed> $values
	 * @throws InvalidRecord When a key is reserved.
	 */
	#[\NoDiscard]
	public function withValues(array $values): self
	{
		return new self($this->id, $values, $this->body);
	}

	/**
	 * Returns the record without some values.
	 */
	#[\NoDiscard]
	public function without(string ...$keys): self
	{
		return new self($this->id, array_diff_key($this->values, array_flip($keys)), $this->body);
	}

	/**
	 * Returns the record with another body, or none.
	 */
	#[\NoDiscard]
	public function withBody(?string $body): self
	{
		return new self($this->id, $this->values, $body);
	}
}
