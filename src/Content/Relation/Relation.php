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
 * - `control` names how the admin picks targets (`RelationControl`),
 *   else its shape's (`control()`, D-599).
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
	 * The name of the credit setup gives a site (D-602), read from 1.x's
	 * `author` too.
	 */
	public const string AUTHORS = 'authors';

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
	 * Whether the order targets are written in means something (a lead
	 * author); always for a credit.
	 */
	public bool $ordered;

	/**
	 * How a translation's links relate to its original's; a credit's add
	 * to them by default.
	 */
	public TranslationRule $translations;

	/**
	 * What one target is called ("Cook"), for a credit beside a name
	 * ("Photographer: Sam"); made from the label when it isn't given.
	 */
	public string $singular;

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
	 * @param  ?RelationControl $control   How the admin picks targets, or `null` for its shape's.
	 * @param  string          $singular   What one target is called ("Cook"); `''` for one made from the label.
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
		bool $ordered = false,
		public int $min = 0,
		?int $max = null,
		public bool $create = false,
		public bool $symmetric = false,
		Inverse|false|null $inverse = null,
		?TranslationRule $translations = null,
		public array $defaults = [],
		public string $label = '',
		public ?RelationControl $control = null,
		string $singular = ''
	) {
		$this->field    = $field ?? $name;
		$this->singular = trim($singular) !== '' ? trim($singular) : self::singularOf($label === '' ? ucfirst(str_replace('_', ' ', $name)) : $label);
		$this->multiple = $multiple && ! $kind->isWithinType();

		// A credit's order names its lead, and a translation adds its own
		// credits (a translator) to its original's (D-587, D-602).
		$this->ordered      = $ordered || $kind === RelationKind::Credit;
		$this->translations = $translations ?? ($kind === RelationKind::Credit ? TranslationRule::Add : TranslationRule::Fallback);
		// A term's and a profile's own pages list what links to them, and
		// a credit has archives under each type by its name (D-602).
		$this->inverse  = $inverse ?? new Inverse(
			page: $kind === RelationKind::Classify || $kind === RelationKind::Credit,
			archive: $kind === RelationKind::Credit ? $name : false
		);
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
	 * Returns the `authors` credit setup and the new-type wizard write
	 * (D-602): from the types given to the profiles type, read from 1.x's
	 * `author` too, with archives under each type's `authors`.
	 *
	 * @param  list<string> $from
	 * @throws InvalidRelation
	 */
	public static function authors(array $from, string $profiles): self
	{
		return new self(self::AUTHORS, RelationKind::Credit, $from, [$profiles], aliases: ['author'], label: 'Authors');
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
	 * Returns how the admin picks the relation's targets (D-599): its own
	 * `control` when its shape can draw it, else its shape's: people for
	 * credits, a select for one target, a tree for a classify relation to
	 * a type that nests (`$nests`), tokens for other terms, and cards for
	 * other entries.
	 */
	public function control(bool $nests = false): RelationControl
	{
		$shape = match (true) {
			$this->kind === RelationKind::Credit   => RelationControl::People,
			! $this->multiple                       => RelationControl::Select,
			$this->kind === RelationKind::Classify => $nests ? RelationControl::Tree : RelationControl::Tokens,
			default                                 => RelationControl::Cards
		};

		$fits = match ($this->control) {
			null                    => false,
			RelationControl::Select => ! $this->multiple,
			RelationControl::Tree   => $nests && $this->multiple,
			default                 => $this->multiple
		};

		return $fits && $this->control !== null ? $this->control : $shape;
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
		$rule   = $string('translations') === '' ? null : TranslationRule::tryFrom($string('translations'))
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
			// A classify relation's terms, and a credit's profiles, have
			// pages unless it says otherwise, with or without other inverse
			// options (D-593, D-602).
			inverse: is_array($inverse)
				? Inverse::fromArray([
					...($kind === RelationKind::Classify || $kind === RelationKind::Credit ? ['page' => true] : []),
					...($kind === RelationKind::Credit ? ['archive' => $string('name')] : []),
					...$inverse
				])
				: ($inverse === false ? false : null),
			translations: $rule,
			defaults: $list('defaults'),
			label: $string('label'),
			singular: $string('singular'),
			control: $string('control') === '' ? null : (RelationControl::tryFrom($string('control')) ?? throw new InvalidRelation(sprintf(
				'Relation "%s" has an unknown control; it can be %s.',
				$string('name'),
				implode(', ', array_map(static fn (RelationControl $control): string => $control->value, RelationControl::cases()))
			)))
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
			'ordered'      => $this->ordered && $this->kind !== RelationKind::Credit ? true : null,
			'min'          => $this->min ?: null,
			'max'          => $this->multiple ? $this->max : null,
			'create'       => $this->create ?: null,
			'symmetric'    => $this->symmetric ?: null,
			'inverse'      => $this->inverse === false ? false : ($this->inverseArray() ?: null),
			'translations' => $this->translations === ($this->kind === RelationKind::Credit ? TranslationRule::Add : TranslationRule::Fallback) ? null : $this->translations->value,
			'defaults'     => $this->defaults,
			'label'        => $this->label,
			'singular'     => $this->singular === self::singularOf($this->label === '' ? ucfirst(str_replace('_', ' ', $this->name)) : $this->label) ? null : $this->singular,
			'control'      => $this->control?->value
		];

		return array_filter($data, static fn (mixed $value): bool => $value !== null && $value !== [] && $value !== '');
	}

	/**
	 * Returns the inverse side as an array, leaving out what its kind
	 * gives by default (a term's or profile's page, a credit's archive
	 * under its name) and writing `false` where its kind's default is on,
	 * so it reads back the same.
	 *
	 * @return array<string, mixed>
	 */
	private function inverseArray(): array
	{
		if ($this->inverse === false) {
			return [];
		}

		$data    = $this->inverse->toArray();
		$page    = $this->kind === RelationKind::Classify || $this->kind === RelationKind::Credit;
		$archive = $this->kind === RelationKind::Credit ? $this->name : false;

		unset($data['page'], $data['archive']);

		if ($this->inverse->page !== $page) {
			$data['page'] = $this->inverse->page;
		}

		if ($this->inverse->archive !== $archive) {
			$data['archive'] = $this->inverse->archive;
		}

		return $data;
	}

	/**
	 * Returns an English singular for a plural label: "Categories" is
	 * "Category", "Cooks" "Cook". Other words set `singular`.
	 */
	private static function singularOf(string $plural): string
	{
		return match (true) {
			preg_match('/[^aeiou]ies$/i', $plural) === 1 => substr($plural, 0, -3) . 'y',
			preg_match('/[^s]s$/i', $plural) === 1       => substr($plural, 0, -1),
			default                                     => $plural
		};
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
