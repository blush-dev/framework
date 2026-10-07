<?php

/**
 * Relation.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Relation;

use Blush\Content\EntryFields;

/**
 * One way entries link to other entries (D-585): a post's categories, a
 * movie's actors, a page's parent. Declared once, on the side that
 * stores it, and read from both sides.
 *
 *     new Relation(
 *         'actors',
 *         RelationKind::Reference,
 *         from: ['movie'],
 *         to: ['person'],
 *         ordered: true,
 *         inverse: new Inverse(label: 'Acted in')
 *     );
 *
 * A relation is a set of records (`Link`: source id, relation, target
 * id, position), which is how a database keeps it (D-486). In a file,
 * every relation but `translation_of` is written in two forms (D-589):
 * its front matter key (`field`, read from `aliases` too) lists slugs,
 * a tree's paths, or ids, in order (a tree's parent is its folder,
 * D-591); and `refs` maps each to its target's id.
 *
 * - `from` names the source types, empty for every type (a taxonomy's
 *   terms may file any entry). `to` names the target types.
 * - `min` and `max` bound how many targets a live entry has (`required`
 *   is a minimum of one); drafts may have fewer (D-585). A relation
 *   that isn't `multiple` has at most one.
 * - `create` lets the admin create a target as it's typed (a tag).
 * - `symmetric` makes each link true from both ends (related posts).
 * - `defaults` are written values filled in when an entry is created,
 *   never applied when it's read (D-587).
 * - An entry never links to itself, and a hierarchical relation refuses
 *   cycles (D-587).
 */
final readonly class Relation
{
	/**
	 * Front matter keys no relation may take: the entry's id, and the
	 * ids of its links.
	 *
	 * @var list<string>
	 */
	public const array RESERVED = [EntryFields::ID, Refs::FIELD];

	/**
	 * The front matter key the relation is written under.
	 */
	public string $field;

	/**
	 * Whether a source may have several targets; never a parent's or a
	 * translation's.
	 */
	public bool $multiple;

	/**
	 * The most targets a source has, or `null` for any number.
	 */
	public ?int $max;

	/**
	 * The targets' side, or `false` when it isn't shown.
	 */
	public Inverse|false $inverse;

	/**
	 * @param  string          $name       Lowercase letters, digits, and underscores; unique on each source type.
	 * @param  RelationKind    $kind       What it's for.
	 * @param  list<string>    $from       The source types; empty for every type.
	 * @param  list<string>    $to         The target types.
	 * @param  ?string         $field      The front matter key; defaults to the name.
	 * @param  list<string>    $aliases    Other keys it's read from.
	 * @param  bool            $multiple   Whether a source may have several targets.
	 * @param  bool            $ordered    Whether the targets' order means something (a lead author).
	 * @param  int             $min        The fewest targets a live entry has.
	 * @param  ?int            $max        The most targets, or `null` for any number.
	 * @param  bool            $create     Whether targets may be created as they're typed.
	 * @param  bool            $symmetric  Whether each link holds from both ends.
	 * @param  Inverse|false|null $inverse The targets' side, `false` when it isn't shown, or `null` for its kind's (a classify relation's targets list what's filed under them).
	 * @param  TranslationRule $translations How a translation's links relate to its original's.
	 * @param  list<string>    $defaults   Written values filled in on create.
	 * @param  string          $label      What people call it ("Actors"); `''` for its name's.
	 * @throws InvalidRelation
	 */
	public function __construct(
		public string $name,
		public RelationKind $kind,
		public array $from = [],
		public array $to = [],
		?string $field = null,
		public array $aliases = [],
		bool $multiple = true,
		public bool $ordered = false,
		public int $min = 0,
		?int $max = null,
		public bool $create = false,
		public bool $symmetric = false,
		Inverse|false|null $inverse = null,
		public TranslationRule $translations = TranslationRule::Fallback,
		public array $defaults = [],
		public string $label = ''
	) {
		$this->field    = $field ?? $name;
		$this->multiple = $multiple && ! $kind->isWithinType();
		$this->inverse  = $inverse ?? new Inverse(archive: $kind === RelationKind::Classify);
		$this->max      = $this->multiple ? $max : 1;

		self::checkName('name', $name);

		foreach ([$this->field, ...$aliases] as $key) {
			self::checkName('front matter key', $key);

			if (in_array($key, self::RESERVED, true)) {
				throw new InvalidRelation(sprintf('Relation "%s" can\'t be written under "%s", which is reserved.', $name, $key));
			}
		}

		if ($to === []) {
			throw new InvalidRelation(sprintf('Relation "%s" names no type it points to.', $name));
		}

		if ($min < 0) {
			throw new InvalidRelation(sprintf('Relation "%s" has a min below 0.', $name));
		}

		if ($this->max !== null && $this->max < max(1, $min)) {
			throw new InvalidRelation(sprintf('Relation "%s" has a max below its min (or below 1).', $name));
		}

		if (count($defaults) > ($this->max ?? PHP_INT_MAX) || in_array('', $defaults, true)) {
			throw new InvalidRelation(sprintf('Relation "%s" has more defaults than it takes, or an empty one.', $name));
		}

		if ($kind->isWithinType() && (count($from) !== 1 || $to !== $from || $symmetric)) {
			throw new InvalidRelation(sprintf('Relation "%s" is a %s, which points from one type to the same type, one target each.', $name, $kind->value));
		}

		// A classify relation is named for the one type it files entries
		// under, so a term's key in the index is its type's name (D-593).
		if ($kind === RelationKind::Classify && $to !== [$name]) {
			throw new InvalidRelation(sprintf('Relation "%s" classifies, so it points to one type and is named after it: "to" must be ["%s"].', $name, $name));
		}

		if ($symmetric && $from !== [] && array_diff($to, $from) !== []) {
			throw new InvalidRelation(sprintf('Relation "%s" is symmetric, so every type it points to must be one it points from.', $name));
		}
	}

	/**
	 * Returns the relation's key on a source type, as the index keys its
	 * links: `movie.actors`.
	 */
	public function key(string $type): string
	{
		return "{$type}.{$this->name}";
	}

	/**
	 * Returns the key the index keeps the relation's written values under
	 * (`IndexRecord::$terms`, D-592), which `Query::whereTerm()` and
	 * `termCounts()` read: a classify relation's name, which is its
	 * type's (`category`), else `{target type}.{name}` (`person.actors`,
	 * `profile.authors`), for a relation to one type. A relation to
	 * several types has none yet.
	 */
	public function termKey(): ?string
	{
		return match (true) {
			$this->kind === RelationKind::Classify => $this->name,
			$this->kind->isWithinType(), count($this->to) !== 1 => null,
			default                                => "{$this->to[0]}.{$this->name}"
		};
	}

	/**
	 * Returns whether entries of a type may hold the relation.
	 */
	public function isFrom(string $type): bool
	{
		return $this->from === [] || in_array($type, $this->from, true);
	}

	/**
	 * Returns whether the relation may point at entries of a type.
	 */
	public function isTo(string $type): bool
	{
		return in_array($type, $this->to, true);
	}

	/**
	 * Returns whether a live entry needs at least one target.
	 */
	public function isRequired(): bool
	{
		return $this->min > 0;
	}

	/**
	 * Returns whether the relation refuses cycles.
	 */
	public function isHierarchical(): bool
	{
		return $this->kind->isHierarchical();
	}

	/**
	 * Returns every front matter key the relation is read from, its own
	 * first.
	 *
	 * @return list<string>
	 */
	public function keys(): array
	{
		return [$this->field, ...$this->aliases];
	}

	/**
	 * Returns the relation's value in front matter: under its key, else
	 * the first alias that has one, or `null`.
	 *
	 * @param array<array-key, mixed> $frontMatter
	 */
	public function valueIn(array $frontMatter): mixed
	{
		return array_find(
			array_map(static fn (string $key): mixed => $frontMatter[$key] ?? null, $this->keys()),
			static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []
		);
	}

	/**
	 * Builds a relation from a definition array: the constructor's
	 * parameter names, with `kind` and `translations` by value, `inverse`
	 * a map or `false`, and `required` short for a `min` of 1.
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws InvalidRelation
	 */
	public static function fromArray(array $data): self
	{
		$string = static fn (string $key, string $default = ''): string => is_string($data[$key] ?? null) ? $data[$key] : $default;
		$list   = static fn (string $key): array => is_array($data[$key] ?? null)
			? array_values(array_filter($data[$key], static fn (mixed $value): bool => is_string($value)))
			: (is_string($data[$key] ?? null) ? [$data[$key]] : []);
		$bool   = static fn (string $key, bool $default): bool => is_bool($data[$key] ?? null) ? $data[$key] : $default;
		$kind   = RelationKind::tryFrom($string('kind', RelationKind::Reference->value))
			?? throw new InvalidRelation(sprintf('Relation "%s" has an unknown kind.', $string('name')));
		$rule   = TranslationRule::tryFrom($string('translations', TranslationRule::Fallback->value))
			?? throw new InvalidRelation(sprintf('Relation "%s" has an unknown translations rule.', $string('name')));
		$min    = is_int($data['min'] ?? null) ? $data['min'] : (($data['required'] ?? false) === true ? 1 : 0);
		$max    = is_int($data['max'] ?? null) ? $data['max'] : null;
		$inverse = $data['inverse'] ?? null;

		return new self(
			name: $string('name'),
			kind: $kind,
			from: $list('from'),
			to: $list('to'),
			field: is_string($data['field'] ?? null) ? $data['field'] : null,
			aliases: $list('aliases'),
			multiple: $bool('multiple', true),
			ordered: $bool('ordered', false),
			min: $min,
			max: $max,
			create: $bool('create', false),
			symmetric: $bool('symmetric', false),
			// A classify relation's terms have pages unless it says otherwise,
			// with or without other inverse options (D-593).
			inverse: is_array($inverse)
				? Inverse::fromArray($kind === RelationKind::Classify && ! array_key_exists('archive', $inverse) ? [...$inverse, 'archive' => true] : $inverse)
				: ($inverse === false ? false : null),
			translations: $rule,
			defaults: $list('defaults'),
			label: $string('label')
		);
	}

	/**
	 * Returns the relation as a definition array that `fromArray()`
	 * accepts, leaving out defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		$data = [
			'name'         => $this->name,
			'kind'         => $this->kind->value,
			'from'         => $this->from,
			'to'           => $this->to,
			'field'        => $this->field === $this->name ? null : $this->field,
			'aliases'      => $this->aliases,
			'multiple'     => $this->multiple ? null : false,
			'ordered'      => $this->ordered ?: null,
			'min'          => $this->min ?: null,
			'max'          => $this->multiple ? $this->max : null,
			'create'       => $this->create ?: null,
			'symmetric'    => $this->symmetric ?: null,
			'inverse'      => $this->inverse === false ? false : ($this->inverse->toArray() ?: null),
			'translations' => $this->translations === TranslationRule::Fallback ? null : $this->translations->value,
			'defaults'     => $this->defaults,
			'label'        => $this->label
		];

		return array_filter($data, static fn (mixed $value): bool => $value !== null && $value !== [] && $value !== '');
	}

	/**
	 * Checks a name or front matter key.
	 *
	 * @throws InvalidRelation
	 */
	private static function checkName(string $what, string $value): void
	{
		if (preg_match('/^[a-z][a-z0-9_]*$/', $value) !== 1) {
			throw new InvalidRelation(sprintf('A relation\'s %s must be lowercase letters, digits, and underscores, starting with a letter; "%s" isn\'t.', $what, $value));
		}
	}
}
