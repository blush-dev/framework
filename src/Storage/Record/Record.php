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
 * What a storage driver keeps (D-643, D-649): an id, its fields by key,
 * and optional content (an entry's Markdown, say). The id is a UUID,
 * identity for every driver (D-606); fields hold what a JSON object
 * holds (text, numbers, true and false, null, lists, and maps), and
 * never `id` or `content`, which a query reaches as themselves.
 *
 * A record read from a store carries its **version** (D-648), which
 * changes whenever it's saved; pass it back to `save()` or `delete()`
 * to refuse the write when someone else changed the record since. The
 * `with*()` copies keep it.
 */
final readonly class Record
{
	/**
	 * Keys a record's fields can't use.
	 */
	public const array RESERVED = ['id', 'content'];

	/**
	 * The id, lowercase.
	 */
	public string $id;

	/**
	 * @param  array<string, mixed> $fields
	 * @param  ?string              $version The store's, when it was read from one.
	 * @throws InvalidRecord When the id isn't a UUID or a field's key is reserved.
	 */
	public function __construct(
		string $id,
		public array $fields = [],
		public ?string $content = null,
		public ?string $version = null
	) {
		if (! Uuid::isValid($id)) {
			throw new InvalidRecord(sprintf('"%s" isn\'t a record id; ids are UUIDs.', $id));
		}

		foreach (['', ...self::RESERVED] as $key) {
			if (array_key_exists($key, $fields)) {
				throw new InvalidRecord(sprintf('A record\'s fields can\'t use the key "%s".', $key));
			}
		}

		$this->id = strtolower($id);
	}

	/**
	 * Makes a new record with a new id (a version 7 UUID, D-477).
	 *
	 * @param  array<string, mixed> $fields
	 * @throws InvalidRecord When a field's key is reserved.
	 */
	public static function create(DateTimeInterface $now, array $fields = [], ?string $content = null): self
	{
		return new self(Uuid::v7($now), $fields, $content);
	}

	/**
	 * Returns a value by key, or `null` when there's none: `id`,
	 * `content`, a field's key, or keys joined by dots to reach into
	 * nested fields (`seo.title`). A key with a dot in it is found as it
	 * is first.
	 */
	public function value(string $key): mixed
	{
		if ($key === 'id') {
			return $this->id;
		}

		if ($key === 'content') {
			return $this->content;
		}

		if (array_key_exists($key, $this->fields)) {
			return $this->fields[$key];
		}

		$value = $this->fields;

		foreach (explode('.', $key) as $segment) {
			if (! is_array($value) || ! array_key_exists($segment, $value)) {
				return null;
			}

			$value = $value[$segment];
		}

		return $value;
	}

	/**
	 * Returns the record with a field set.
	 *
	 * @throws InvalidRecord When the key is reserved.
	 */
	#[\NoDiscard]
	public function with(string $key, mixed $value): self
	{
		return new self($this->id, [...$this->fields, $key => $value], $this->content, $this->version);
	}

	/**
	 * Returns the record with other fields.
	 *
	 * @param  array<string, mixed> $fields
	 * @throws InvalidRecord When a key is reserved.
	 */
	#[\NoDiscard]
	public function withFields(array $fields): self
	{
		return new self($this->id, $fields, $this->content, $this->version);
	}

	/**
	 * Returns the record without some fields.
	 */
	#[\NoDiscard]
	public function without(string ...$keys): self
	{
		return new self($this->id, array_diff_key($this->fields, array_flip($keys)), $this->content, $this->version);
	}

	/**
	 * Returns the record with other content, or none.
	 */
	#[\NoDiscard]
	public function withContent(?string $content): self
	{
		return new self($this->id, $this->fields, $content, $this->version);
	}

	/**
	 * Returns the record as a store read it, at a version.
	 */
	#[\NoDiscard]
	public function withVersion(?string $version): self
	{
		return new self($this->id, $this->fields, $this->content, $version);
	}
}
