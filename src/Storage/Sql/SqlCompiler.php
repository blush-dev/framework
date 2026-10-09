<?php

/**
 * SQL compiler.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Storage\Sql;

use Closure;
use Blush\Storage\Record\Condition;
use Blush\Storage\Record\ConditionGroup;
use Blush\Storage\Record\Junction;
use Blush\Storage\Record\Operator;
use Blush\Storage\Record\Order;
use Blush\Storage\Record\Related;
use Blush\Storage\Record\Sort;
use Blush\Storage\Record\Subquery;
use Blush\Storage\Record\Table;

/**
 * Turns a record query's conditions and order into SQL (D-606), in a
 * dialect, answering as `ArrayEvaluator` does, which the conformance
 * suite checks (D-648):
 *
 * - **By type:** every comparison tests the value's JSON type first, so
 *   text never equals a number, `true` never equals `1`, and numbers
 *   compare as numbers (`1` equals `1.0`). A missing value and `null`
 *   are both null.
 * - **Lists** (`in`, a subquery's values, `intersects`) are bound as one
 *   JSON value, read with the dialect's `listed()`, whatever their size.
 * - **Bound values** are text, integers (which the store binds as
 *   integers), and floats (cast, since PDO binds them as text).
 * - **Subqueries and related conditions** are worked out first, by the
 *   store (`$values`, `$related`), and bound as lists.
 * - **Order:** a key's nulls last whichever way; then false and true,
 *   numbers, text (without regard to case), and lists and maps (by
 *   their JSON); ties in the order records were added.
 *
 * Every condition it builds is true or false, never `NULL`.
 */
final readonly class SqlCompiler
{
	public function __construct(
		private SqlDialect $dialect
	) {}

	/**
	 * Returns a group of conditions as a condition.
	 *
	 * @param Closure(Subquery): list<bool|int|float|string> $values  A subquery's values.
	 * @param Closure(Related): list<string>                 $related The ids a related condition keeps.
	 */
	public function where(Table $table, ConditionGroup $group, Closure $values, Closure $related): SqlFragment
	{
		$fragments = [];

		foreach ($group->conditions as $condition) {
			$fragments[] = match (true) {
				$condition instanceof ConditionGroup => $this->where($table, $condition, $values, $related),
				$condition instanceof Related        => $this->ids($related($condition)),
				default                              => $this->condition($table, $condition, $values)
			};
		}

		return SqlFragment::join($fragments, $group->junction === Junction::All);
	}

	/**
	 * Returns an `ORDER BY` list for sorts, ending with the order records
	 * were added in.
	 *
	 * @param list<Sort> $sorts
	 */
	public function orderBy(Table $table, array $sorts): string
	{
		$terms = [];

		foreach ($sorts as $sort) {
			[$type, $value] = $this->dialect->read($table, $sort->key);
			$direction      = $sort->order === Order::Desc ? 'DESC' : 'ASC';
			$terms[]        = "({$type} IS NULL OR {$type} IS 'null') ASC";
			$terms[]        = "(CASE WHEN {$type} IS 'true' OR {$type} IS 'false' THEN 0 WHEN {$type} IS 'integer' OR {$type} IS 'real' THEN 1 WHEN {$type} IS 'text' THEN 2 ELSE 3 END) {$direction}";
			$terms[]        = "(CASE WHEN {$type} IS 'text' THEN {$this->dialect->fold($value)} WHEN {$type} IS 'array' OR {$type} IS 'object' THEN {$this->dialect->json($value)} ELSE {$value} END) {$direction}";
		}

		$terms[] = "{$this->dialect->added()} ASC";

		return implode(', ', $terms);
	}

	/**
	 * Returns one condition on a value.
	 *
	 * @param Closure(Subquery): list<bool|int|float|string> $values
	 */
	private function condition(Table $table, Condition $condition, Closure $values): SqlFragment
	{
		[$type, $value] = $this->dialect->read($table, $condition->key);
		$against        = $condition->value instanceof Subquery ? $values($condition->value) : $condition->value;

		return match ($condition->operator) {
			Operator::Equal          => $this->equals($type, $value, $against),
			Operator::NotEqual       => $this->equals($type, $value, $against)->not(),
			Operator::Less           => $this->ordered($type, $value, '<', $against),
			Operator::LessOrEqual    => $this->ordered($type, $value, '<=', $against),
			Operator::Greater        => $this->ordered($type, $value, '>', $against),
			Operator::GreaterOrEqual => $this->ordered($type, $value, '>=', $against),
			Operator::In             => $this->among($type, $value, is_array($against) ? array_values($against) : []),
			Operator::NotIn          => $this->among($type, $value, is_array($against) ? array_values($against) : [])->not(),
			Operator::Between        => is_array($against)
				? SqlFragment::join([$this->ordered($type, $value, '>=', $against[0] ?? null), $this->ordered($type, $value, '<=', $against[1] ?? null)], true)
				: new SqlFragment('0'),
			Operator::Like           => is_string($against) ? $this->like($type, $value, $against) : new SqlFragment('0'),
			Operator::Contains       => $this->listHolds($table, $condition->key, fn (string $elementType, string $element): SqlFragment => $this->equals($elementType, $element, $against)),
			Operator::Intersects     => $this->listHolds($table, $condition->key, fn (string $elementType, string $element): SqlFragment => $this->among($elementType, $element, is_array($against) ? array_values($against) : [])),
			Operator::Null           => self::isNull($type),
			Operator::NotNull        => self::isNull($type)->not()
		};
	}

	/**
	 * A value equal to another, by type.
	 */
	private function equals(string $type, string $value, mixed $against): SqlFragment
	{
		return match (true) {
			$against === null                          => self::isNull($type),
			is_bool($against)                          => new SqlFragment("{$type} IS " . ($against ? "'true'" : "'false'")),
			is_int($against) || is_float($against)     => new SqlFragment("(({$type} IS 'integer' OR {$type} IS 'real') AND {$value} = " . self::number($against) . ')', [$against]),
			is_string($against)                        => new SqlFragment("({$type} IS 'text' AND {$value} = ?)", [$against]),
			default                                    => new SqlFragment('0')
		};
	}

	/**
	 * A value equal to one of a list's, by type: text and numbers each
	 * bound as one list.
	 *
	 * @param list<mixed> $items
	 */
	private function among(string $type, string $value, array $items): SqlFragment
	{
		$texts   = array_values(array_filter($items, is_string(...)));
		$numbers = array_values(array_filter($items, static fn (mixed $item): bool => is_int($item) || is_float($item)));
		$parts   = [];

		if ($texts !== []) {
			$parts[] = new SqlFragment("({$type} IS 'text' AND {$value} IN {$this->dialect->listed()})", [self::encode($texts)]);
		}

		if ($numbers !== []) {
			$parts[] = new SqlFragment("(({$type} IS 'integer' OR {$type} IS 'real') AND {$value} IN {$this->dialect->listed()})", [self::encode($numbers)]);
		}

		foreach ([true, false] as $bool) {
			if (in_array($bool, $items, true)) {
				$parts[] = $this->equals($type, $value, $bool);
			}
		}

		if (in_array(null, $items, true)) {
			$parts[] = self::isNull($type);
		}

		return SqlFragment::join($parts, false);
	}

	/**
	 * A value that compares with a number or text as an operator wants;
	 * never when it's another type.
	 */
	private function ordered(string $type, string $value, string $operator, mixed $against): SqlFragment
	{
		return match (true) {
			is_int($against) || is_float($against) => new SqlFragment("(({$type} IS 'integer' OR {$type} IS 'real') AND {$value} {$operator} " . self::number($against) . ')', [$against]),
			is_string($against)                    => new SqlFragment("({$type} IS 'text' AND {$value} {$operator} ?)", [$against]),
			default                                => new SqlFragment('0')
		};
	}

	/**
	 * Text that matches a `like` pattern: text found anywhere (`%text%`)
	 * is looked for in the folded text, and other patterns go to the
	 * dialect.
	 */
	private function like(string $type, string $value, string $pattern): SqlFragment
	{
		$text = self::anywhere($pattern);

		return match (true) {
			$text === ''   => new SqlFragment("{$type} IS 'text'"),
			$text !== null => new SqlFragment("({$type} IS 'text' AND instr({$this->dialect->fold($value)}, ?) > 0)", [mb_strtolower($text)]),
			default        => new SqlFragment("({$type} IS 'text' AND {$this->dialect->like($value)})", [$pattern])
		};
	}

	/**
	 * A list holding an element that meets a test: a row of the value's
	 * elements keyed by position, which only a list has, read in one go.
	 *
	 * @param Closure(string, string): SqlFragment $test The element's type and value.
	 */
	private function listHolds(Table $table, string $key, Closure $test): SqlFragment
	{
		$element = $test('e.type', 'e.value');

		return new SqlFragment("EXISTS (SELECT 1 FROM {$this->dialect->elements($table, $key)} AS e WHERE typeof(e.key) = 'integer' AND {$element->sql})", $element->params, costly: true);
	}

	/**
	 * Records with one of some ids.
	 *
	 * @param list<string> $ids
	 */
	private function ids(array $ids): SqlFragment
	{
		return $ids === [] ? new SqlFragment('0') : new SqlFragment("id IN {$this->dialect->listed()}", [self::encode($ids)]);
	}

	/**
	 * A value that's null or missing.
	 */
	private static function isNull(string $type): SqlFragment
	{
		return new SqlFragment("({$type} IS NULL OR {$type} IS 'null')");
	}

	/**
	 * The text a `like` pattern finds anywhere (`%text%`, with nothing
	 * else that's a wildcard), unescaped, or `null` for any other
	 * pattern.
	 */
	private static function anywhere(string $pattern): ?string
	{
		if (! str_starts_with($pattern, '%') || ! str_ends_with($pattern, '%') || strlen($pattern) < 2) {
			return null;
		}

		$inner = substr($pattern, 1, -1);
		$text  = '';

		for ($i = 0, $length = strlen($inner); $i < $length; $i++) {
			$character = $inner[$i];

			if ($character === '\\' && $i + 1 < $length) {
				$text .= $inner[++$i];
			} elseif ($character === '%' || $character === '_' || $character === '\\') {
				return null;
			} else {
				$text .= $character;
			}
		}

		return $text;
	}

	/**
	 * Returns a number's placeholder: an integer is bound as one, and a
	 * float, which PDO binds as text, is cast.
	 */
	private static function number(int|float $number): string
	{
		return is_float($number) ? 'CAST(? AS REAL)' : '?';
	}

	/**
	 * Returns a list as JSON, to bind.
	 *
	 * @param list<mixed> $items
	 */
	private static function encode(array $items): string
	{
		return json_encode($items, JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
	}
}
