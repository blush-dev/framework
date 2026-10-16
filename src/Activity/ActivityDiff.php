<?php

/**
 * Activity diff.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Activity;

/**
 * What a change changed (D-902), when it can be told (`state`): the
 * fields whose values differ, before and after, and, for an entry, its
 * body before and after when that differs, and its key when it moved.
 */
final readonly class ActivityDiff
{
	/**
	 * @param list<FieldChange> $fields
	 */
	public function __construct(
		public DiffState $state,
		public array $fields = [],
		public ?string $bodyBefore = null,
		public ?string $bodyAfter = null,
		public ?string $keyBefore = null,
		public ?string $keyAfter = null
	) {}

	/**
	 * A diff with nothing to show, and why.
	 */
	public static function none(DiffState $state): self
	{
		return new self($state);
	}

	/**
	 * Returns it as the admin's API answers it.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return [
			'state'  => $this->state->value,
			'fields' => array_map(static fn (FieldChange $field): array => ['name' => $field->name, 'before' => $field->before, 'after' => $field->after], $this->fields),
			'body'   => $this->bodyBefore === null && $this->bodyAfter === null ? null : ['before' => $this->bodyBefore ?? '', 'after' => $this->bodyAfter ?? ''],
			'moved'  => $this->keyBefore === null || $this->keyBefore === $this->keyAfter ? null : ['from' => $this->keyBefore, 'to' => $this->keyAfter]
		];
	}
}
