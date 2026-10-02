<?php

/**
 * People field.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Type;

use Blush\Field\Definition;
use Blush\Field\Fields\ReferenceField;
use Blush\Field\InvalidSchema;

/**
 * One way a content type credits people (D-351): a front matter field
 * that names profiles (entries of the site's `Profiles` type), with the
 * type's own words for it. Recipes might credit cooks and photographers,
 * posts their authors, and every one of them points at the same profiles:
 *
 *     new PeopleField('cooks', plural: 'Cooks', singular: 'Cook', required: true)
 *
 * A field with an archive word gets two routes under its type's prefix:
 * `{field}.collection` (`/recipes/cooks`), the people it credits, and
 * `{field}.single` (`/recipes/cooks/jane`), one person's archive, with
 * `.paged` and the feed keys. `false` credits people without archives.
 */
final readonly class PeopleField
{
	/**
	 * The default field: collections credit `authors` (1.x's `author`).
	 */
	public const string AUTHORS = 'authors';

	/**
	 * Field names a people field can't have, since its route keys would
	 * read like the type's own.
	 */
	private const array RESERVED = ['collection', 'single'];

	/**
	 * Other front matter keys the field is read from.
	 *
	 * @var list<string>
	 */
	public array $aliases;

	/**
	 * The people, together ("Cooks").
	 */
	public string $plural;

	/**
	 * One person ("Cook").
	 */
	public string $singular;

	/**
	 * The word the archives sit under, without slashes, or `false` for
	 * none.
	 */
	public string|false $archive;

	/**
	 * @param  string       $field    The front matter key: lowercase letters, digits, and underscores.
	 * @param  list<string> $aliases  Other keys it's read from.
	 * @param  ?string      $plural   Defaults to the field's name, capitalized.
	 * @param  ?string      $singular Defaults to the plural without its "s".
	 * @param  string|false|null $archive The archive word; defaults to the field's name.
	 * @param  bool         $multiple Whether an entry may credit several people.
	 * @param  bool         $required Whether an entry needs one to be published.
	 * @throws InvalidSchema
	 */
	public function __construct(
		public string $field,
		array $aliases = [],
		?string $plural = null,
		?string $singular = null,
		string|false|null $archive = null,
		public bool $multiple = true,
		public bool $required = false
	) {
		if (preg_match('/^[a-z][a-z0-9_]*$/', $field) !== 1 || in_array($field, self::RESERVED, true)) {
			throw new InvalidSchema(sprintf(
				'People field "%s" must start with a lowercase letter, use only lowercase letters, digits, and underscores, and not be "%s".',
				$field,
				implode('" or "', self::RESERVED)
			));
		}

		$archive = $archive === null ? $field : $archive;

		if ($archive !== false && trim($archive, '/ ') === '') {
			throw new InvalidSchema(sprintf('People field "%s" "archive" must be a word, such as "%s", or false.', $field, $field));
		}

		$this->aliases  = array_values(array_unique($aliases));
		$this->plural   = self::given($plural) ?? ucfirst(str_replace('_', ' ', $field));
		$this->singular = self::given($singular) ?? self::singularOf($this->plural);
		$this->archive  = $archive === false ? false : trim($archive, '/ ');
	}

	/**
	 * Returns the field collections credit by default: `authors`, read
	 * from 1.x's `author` too.
	 */
	public static function authors(): self
	{
		return new self(self::AUTHORS, ['author']);
	}

	/**
	 * Builds the fields a type's `people` option names: `true` for the
	 * default `authors` field, `false` for none, or a map of field names
	 * to their settings (`aliases`, `plural`, `singular`, `archive`,
	 * `multiple`, and `required`), where `true` or an empty map takes
	 * every default.
	 *
	 * @return array<string, PeopleField>
	 * @throws InvalidSchema
	 */
	public static function listFrom(mixed $value, string $label): array
	{
		if (is_bool($value)) {
			return $value ? [self::AUTHORS => self::authors()] : [];
		}

		if (! is_array($value)) {
			throw new InvalidSchema(sprintf('%s "people" must be true, false, or a map of fields.', $label));
		}

		$fields = [];

		foreach ($value as $field => $data) {
			if (! is_string($field)) {
				throw new InvalidSchema(sprintf('%s "people" must map field names to their settings.', $label));
			}

			$fields[$field] = self::fromArray($field, $data === true || $data === null ? [] : $data, $label);
		}

		return $fields;
	}

	/**
	 * Builds a field from its settings.
	 *
	 * @throws InvalidSchema
	 */
	public static function fromArray(string $field, mixed $data, string $label): self
	{
		$context = sprintf('%s people field "%s"', $label, $field);

		if (! is_array($data)) {
			throw new InvalidSchema(sprintf('%s must be true or a map.', $context));
		}

		$unknown = array_diff(array_map(strval(...), array_keys($data)), ['aliases', 'plural', 'singular', 'archive', 'multiple', 'required']);

		if ($unknown !== []) {
			throw new InvalidSchema(sprintf('%s has unknown options: %s.', $context, implode(', ', $unknown)));
		}

		$settings = new Definition($data, $context);
		$archive  = $data['archive'] ?? null;

		if ($archive !== null && $archive !== false && ! is_string($archive)) {
			throw new InvalidSchema(sprintf('%s "archive" must be a word or false.', $context));
		}

		$defaults = $field === self::AUTHORS ? self::authors() : null;

		return new self(
			$field,
			$settings->has('aliases') ? $settings->strings('aliases') : ($defaults->aliases ?? []),
			$settings->nullableString('plural'),
			$settings->nullableString('singular'),
			$archive,
			$settings->bool('multiple', true),
			$settings->bool('required')
		);
	}

	/**
	 * Returns the field's settings that differ from its defaults, as
	 * `fromArray()` reads them.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		$default = $this->field === self::AUTHORS ? self::authors() : new self($this->field);

		return array_filter([
			'aliases'  => $this->aliases === $default->aliases ? null : $this->aliases,
			'plural'   => $this->plural === $default->plural ? null : $this->plural,
			'singular' => $this->singular === new self($this->field, plural: $this->plural)->singular ? null : $this->singular,
			'archive'  => $this->archive === $this->field ? null : $this->archive,
			'multiple' => $this->multiple ? null : false,
			'required' => $this->required ?: null
		], static fn (mixed $value): bool => $value !== null);
	}

	/**
	 * Returns whether the field has archives.
	 */
	public function hasArchive(): bool
	{
		return $this->archive !== false;
	}

	/**
	 * Returns the front matter field entries credit people through,
	 * referencing the profiles type.
	 */
	public function referenceField(string $profiles): ReferenceField
	{
		return new ReferenceField($this->field, $profiles, $this->multiple)
			->aliases(...$this->aliases)
			->required($this->required)
			->labeled($this->multiple ? $this->plural : $this->singular);
	}

	/**
	 * Returns the key the index keeps the field's credits under, among an
	 * entry's terms: `profile.cooks`. The profiles type's own name holds
	 * every field's together.
	 */
	public function termKey(string $profiles): string
	{
		return "{$profiles}.{$this->field}";
	}

	/**
	 * Returns the field's route keys, relative to the type: the people
	 * list, then one person's archive, its later pages, and its feeds.
	 *
	 * @return list<string>
	 */
	public function routeKeys(): array
	{
		return array_keys($this->paths());
	}

	/**
	 * Returns the default path of each of the field's route keys, under
	 * its archive word, or none without archives. A person is
	 * `{profile}`.
	 *
	 * @return array<string, string>
	 */
	public function paths(): array
	{
		$word = $this->archive;

		return $word === false ? [] : [
			"{$this->field}.collection"       => $word,
			"{$this->field}.single"           => "{$word}/{profile}",
			"{$this->field}.single.paged"     => "{$word}/{profile}/page/{page}",
			"{$this->field}.single.feed.json" => "{$word}/{profile}/feed/json",
			"{$this->field}.single.feed.atom" => "{$word}/{profile}/feed/atom",
			"{$this->field}.single.feed"      => "{$word}/{profile}/feed"
		];
	}

	/**
	 * Returns the key, under the type's folder, of the page that
	 * introduces the field's list of people: `_cooks`.
	 */
	public function listPage(): string
	{
		return "_{$this->field}";
	}

	/**
	 * Returns the key, under the type's folder, of the page written for
	 * one person's archive: `_cooks/jane`.
	 */
	public function personPage(string $profile): string
	{
		return "_{$this->field}/{$profile}";
	}

	/**
	 * Returns a label someone set, trimmed, or `null`.
	 */
	private static function given(?string $label): ?string
	{
		$label = trim($label ?? '');

		return $label === '' ? null : $label;
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
}
