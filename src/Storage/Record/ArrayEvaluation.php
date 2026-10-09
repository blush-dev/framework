<?php

/**
 * Array evaluation.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Storage\Record;

use Closure;
use Throwable;
use Blush\Storage\StorageArea;

/**
 * One `ArrayEvaluator` call's state: each table's records, read once,
 * and each subquery's and related condition's answer, worked out once.
 *
 * @phpstan-import-type Row from ArrayEvaluator
 * @phpstan-import-type RefLookup from ArrayEvaluator
 */
final class ArrayEvaluation
{
	/**
	 * Records by table name.
	 *
	 * @var array<string, list<Row>>
	 */
	private array $tables = [];

	/**
	 * Answers by the object asked about.
	 *
	 * @var array<string, array<array-key, mixed>>
	 */
	private array $answers = [];

	/**
	 * @param Closure(Table): list<Row> $read
	 * @param ?RefLookup                $refs
	 */
	public function __construct(
		private readonly Closure $read,
		private readonly ?Closure $refs = null
	) {}

	/**
	 * Returns a relation's refs looked up, when the store keeps them so
	 * (`ArrayEvaluator`'s `RefLookup`): sources by target, or, inverse,
	 * targets by source, lowercase; `null` when it doesn't.
	 *
	 * @return ?array<string, list<string>>
	 */
	public function refs(StorageArea $area, string $relation, bool $inverse): ?array
	{
		return $this->refs === null ? null : ($this->refs)($area, $relation, $inverse);
	}

	/**
	 * A table's records, in the order added.
	 *
	 * @return list<Row>
	 */
	public function records(Table $table): array
	{
		return $this->tables[$table->name] ??= ($this->read)($table);
	}

	/**
	 * Works out an answer about a subquery or a related condition once,
	 * however many alike a query holds (an alternative each, say).
	 *
	 * @template T of array
	 * @param  Closure(): T $answer
	 * @return T
	 */
	public function remember(object $about, Closure $answer): array
	{
		/** @var T */
		return $this->answers[self::key($about)] ??= $answer();
	}

	/**
	 * What an object asks, as a key: the same for two alike.
	 */
	private static function key(object $about): string
	{
		try {
			return hash('xxh128', serialize($about));
		} catch (Throwable) {
			return 'object:' . spl_object_id($about);
		}
	}
}
