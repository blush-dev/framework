<?php

/**
 * Content type labels.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Type;

use Blush\Field\Definition;
use Blush\Field\InvalidSchema;
use Stringable;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * What people call a content type and its entries (D-278): the names,
 * the names mid-sentence, and the phrases the admin shows most. Each
 * label defaults from the one before it, so a type sets only the ones
 * its name gets wrong: `singular` is the only one the constructor
 * needs, and `fromArray()` makes it from the type's name.
 *
 * The mid-sentence names lowercase the first letter unless the first
 * word is an acronym or has another capital ("FAQ", "McGuffin"); a
 * proper noun ("Jane's notes") sets them.
 *
 * A label may be a message (`$messages->t()`, D-841): it's kept as
 * given and read as text each time, so it's in the current language
 * wherever the type is shown, as a type loaded once a request needs.
 */
final class TypeLabels
{
	/**
	 * The keys, in the order each one's default builds on the ones
	 * before it.
	 */
	public const array KEYS = ['singular', 'plural', 'menu', 'item', 'items', 'newItem', 'editItem', 'searchItems'];

	// phpcs:disable PSR2.Classes.PropertyDeclaration, PHPCompatibility.Syntax.RemovedCurlyBraceArrayAccess -- PHPCS can't parse property hooks yet.
	/**
	 * One entry ("Recipe").
	 */
	public string $singular {
		get => trim((string) $this->texts['singular']);
	}

	/**
	 * A group of entries ("Recipes"), such as the admin's navigation and
	 * list heading.
	 */
	public string $plural {
		get => self::given($this->texts['plural']) ?? self::pluralOf($this->singular);
	}

	/**
	 * The admin's navigation ("Forms" under Literature), where the plural
	 * is too long or repeats what the menu around it says.
	 */
	public string $menu {
		get => self::given($this->texts['menu']) ?? $this->plural;
	}

	/**
	 * One entry mid-sentence ("the first recipe").
	 */
	public string $item {
		get => self::given($this->texts['item']) ?? self::inSentence($this->singular);
	}

	/**
	 * Entries mid-sentence ("3 recipes", "no drafts among the recipes").
	 */
	public string $items {
		get => self::given($this->texts['items']) ?? self::inSentence($this->plural);
	}

	/**
	 * The button and screen that create an entry ("New Recipe"), in
	 * Title Case as the admin's buttons are (D-522).
	 */
	public string $newItem {
		get => self::given($this->texts['newItem']) ?? 'New ' . self::titleCase($this->singular);
	}

	/**
	 * The editor's title ("Edit recipe").
	 */
	public string $editItem {
		get => self::given($this->texts['editItem']) ?? "Edit {$this->item}";
	}

	/**
	 * The list's search field ("Search recipes").
	 */
	public string $searchItems {
		get => self::given($this->texts['searchItems']) ?? "Search {$this->items}";
	}
	// phpcs:enable

	/**
	 * The labels as given, by key; a missing one is made from those
	 * before it.
	 *
	 * @var array{singular: string|Stringable, plural: string|Stringable|null, menu: string|Stringable|null, item: string|Stringable|null, items: string|Stringable|null, newItem: string|Stringable|null, editItem: string|Stringable|null, searchItems: string|Stringable|null}
	 */
	private readonly array $texts;

	/**
	 * @param string|Stringable      $singular    One entry ("Recipe").
	 * @param string|Stringable|null $plural      Defaults to the singular made plural.
	 * @param string|Stringable|null $menu        Defaults to the plural.
	 * @param string|Stringable|null $item        Defaults to the singular mid-sentence.
	 * @param string|Stringable|null $items       Defaults to the plural mid-sentence.
	 * @param string|Stringable|null $newItem     Defaults to "New {Singular}", in Title Case.
	 * @param string|Stringable|null $editItem    Defaults to "Edit {item}".
	 * @param string|Stringable|null $searchItems Defaults to "Search {items}".
	 */
	public function __construct(
		string|Stringable $singular,
		string|Stringable|null $plural = null,
		string|Stringable|null $menu = null,
		string|Stringable|null $item = null,
		string|Stringable|null $items = null,
		string|Stringable|null $newItem = null,
		string|Stringable|null $editItem = null,
		string|Stringable|null $searchItems = null
	) {
		$this->texts = ['singular' => $singular, 'plural' => $plural, 'menu' => $menu, 'item' => $item, 'items' => $items, 'newItem' => $newItem, 'editItem' => $editItem, 'searchItems' => $searchItems];
	}

	/**
	 * Returns the labels a type gets from its name alone: `literary_form`
	 * is "Literary form", "Literary forms", and so on.
	 */
	public static function named(string $name): self
	{
		return new self(ucfirst(str_replace('_', ' ', $name)));
	}

	/**
	 * Builds labels from a map of the keys, making `singular` from the
	 * type's name when it's missing. A label stored as a message
	 * (`toArray()`) is restored as one. Given `$defaults` (core's, for
	 * a built-in type's name, D-842), a missing label is its.
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws InvalidContentType
	 */
	public static function fromArray(array $data, string $name, Messages $messages, ?self $defaults = null): self
	{
		$unknown = array_diff(array_map(strval(...), array_keys($data)), self::KEYS);

		if ($unknown !== []) {
			throw new InvalidContentType((string) $messages->t('content.type_labels.unknown_options', 'Content type "{name}" labels has unknown options: {options}.', name: $name, options: implode(', ', $unknown)));
		}

		$restored = array_filter(array_map($messages->restore(...), $data));

		try {
			$labels = new Definition(array_diff_key($data, $restored), (string) $messages->t('content.type_labels.subject', 'Content type "{name}" labels', name: $name));
			$given  = array_map($labels->nullableString(...), array_combine(self::KEYS, self::KEYS));
		} catch (InvalidSchema $e) {
			throw new InvalidContentType($e->getMessage(), previous: $e);
		}

		$labels   = [...$given, ...$restored];
		foreach ($defaults === null ? [] : $defaults->texts as $key => $default) {
			if (self::given($labels[$key] ?? null) === null) {
				$labels[$key] = $default;
			}
		}

		$singular = $labels['singular'] ?? null;

		unset($labels['singular']);

		return new self($singular === null || self::given($singular) === null ? self::named($name)->singular : $singular, ...$labels);
	}

	/**
	 * Returns the labels as a map that `fromArray()` accepts for a type
	 * with this name, leaving out the ones at their defaults: those made
	 * from the name, or `$defaults` (D-842). A label given as a message
	 * is kept as one (`Text::toArray()`, D-836), so the compiled types
	 * cache translates it as it's read, not in the language it was
	 * written in.
	 *
	 * @return array<string, string|array<string, mixed>>
	 */
	public function toArray(string $name, ?self $defaults = null): array
	{
		$data = [];
		$kept = [];

		foreach (self::KEYS as $key) {
			$given = $this->texts[$key];

			if ($defaults !== null) {
				$default = $defaults->texts[$key];

				if ($given instanceof Text ? ! ($default instanceof Text && $default->key === $given->key) : self::given($given) !== null && self::given($given) !== $defaults->{$key}) {
					$data[$key] = $given instanceof Text ? $given->toArray() : (string) self::given($given);
				}

				continue;
			}

			if ($given instanceof Text) {
				$data[$key] = $given->toArray();
				$kept[$key] = $given;

				continue;
			}

			$default = new self(...[...$kept, 'singular' => $kept['singular'] ?? self::named($name)->singular]);

			if ($this->{$key} !== $default->{$key}) {
				$data[$key] = $this->{$key};
				$kept[$key] = $this->{$key};
			}
		}

		return $data;
	}

	/**
	 * Returns the labels with each message read through `$messages`
	 * (D-842): a type made in code without the site's translator, such
	 * as a plugin's `new Profiles()`, has messages that print in the
	 * language they're written in until the site reads them.
	 */
	public function in(Messages $messages): self
	{
		$read  = static fn (string|Stringable|null $label): string|Stringable|null => $label instanceof Text ? $messages->restore($label->toArray()) : $label;
		$texts = array_map($read, $this->texts);

		unset($texts['singular']);

		return new self($read($this->texts['singular']) ?? $this->texts['singular'], ...$texts);
	}

	/**
	 * Returns every label, by key.
	 *
	 * @return array<string, string>
	 */
	public function all(): array
	{
		return array_combine(self::KEYS, array_map(fn (string $key): string => $this->{$key}, self::KEYS));
	}

	/**
	 * Returns a label someone set, trimmed, or `null` for one that's
	 * missing or blank.
	 */
	private static function given(string|Stringable|null $label): ?string
	{
		$label = trim((string) $label);

		return $label === '' ? null : $label;
	}

	/**
	 * Returns an English plural: "Category" becomes "Categories", "Class"
	 * "Classes", "Post" "Posts". Names that don't follow these rules, or
	 * aren't English, set `plural`.
	 */
	private static function pluralOf(string $singular): string
	{
		return match (true) {
			preg_match('/[^aeiou]y$/i', $singular) === 1     => substr($singular, 0, -1) . 'ies',
			preg_match('/(s|x|z|ch|sh)$/i', $singular) === 1 => "{$singular}es",
			default                                           => "{$singular}s"
		};
	}

	/**
	 * Returns a name in Title Case, as the admin's `titleCase()` makes
	 * it: each word capitalized but the small ones, and capitals kept
	 * ("Literary form" becomes "Literary Form", "Book of the month"
	 * "Book of the Month").
	 */
	private static function titleCase(string $name): string
	{
		$small = ['a', 'an', 'and', 'as', 'at', 'but', 'by', 'for', 'from', 'in', 'into', 'nor', 'of', 'on', 'or', 'the', 'to', 'with'];
		$words = explode(' ', $name);

		foreach ($words as $index => $word) {
			$words[$index] = $index > 0 && in_array(mb_strtolower($word), $small, true) ? mb_strtolower($word) : mb_ucfirst($word);
		}

		return implode(' ', $words);
	}

	/**
	 * Returns a name for use mid-sentence: its first letter lowercase,
	 * unless its first word has another capital ("FAQ", "HTML snippet").
	 */
	private static function inSentence(string $name): string
	{
		return preg_match('/^\S+\p{Lu}/u', $name) === 1 ? $name : mb_lcfirst($name);
	}
}
