<?php

/**
 * Request memo.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Support;

use Closure;

/**
 * What lookups have found so far in this request (D-805 to D-807): one
 * memo every subsystem shares, so the same lookup reached two ways is
 * asked of storage once and comes back as the same object. Records
 * (`RecordStores`), entries (`StoredEntries`), what entries lead to
 * (`EntryGraph`), content URLs (`ContentUrls`), accounts, tokens, OAuth
 * clients, and secrets keep their answers here; a template never needs a
 * variable to save a lookup.
 *
 * Anything that reads storage or builds objects from it remembers its
 * answers here, under a key that names the subsystem and what was asked
 * (`account|…`, `records|…`).
 *
 * It's dropped whenever what it holds might have changed: after every
 * write through a record store or `Entries`, when the content index is
 * stored, when a request comes in, before each job and each pass of a
 * worker, and before each MCP stdio message, so a long-running process
 * never answers from before a change.
 */
final class RequestMemo
{
	/**
	 * Answers by key.
	 *
	 * @var array<string, mixed>
	 */
	private array $values = [];

	/**
	 * Returns the answer kept under a key, or computes and keeps it.
	 *
	 * @template T
	 * @param  Closure(): T $compute
	 * @return T
	 */
	public function remember(string $key, Closure $compute): mixed
	{
		if (array_key_exists($key, $this->values)) {
			/** @var T $value Kept by this method. */
			$value = $this->values[$key];

			return $value;
		}

		return $this->values[$key] = $compute();
	}

	/**
	 * Returns whether an answer is kept under a key.
	 */
	public function has(string $key): bool
	{
		return array_key_exists($key, $this->values);
	}

	/**
	 * Drops every answer.
	 */
	public function forget(): void
	{
		$this->values = [];
	}
}
