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

use Blush\Content\Schema\Definition;
use Blush\Content\Schema\InvalidSchema;

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
 */
final readonly class TypeLabels
{
	/**
	 * The keys, in the order each one's default builds on the ones
	 * before it.
	 */
	public const array KEYS = ['singular', 'plural', 'menu', 'item', 'items', 'newItem', 'editItem', 'searchItems'];

	/**
	 * A group of entries ("Recipes"), such as the admin's navigation and
	 * list heading.
	 */
	public string $plural;

	/**
	 * The admin's navigation ("Forms" under Literature), where the plural
	 * is too long or repeats what the menu around it says.
	 */
	public string $menu;

	/**
	 * One entry mid-sentence ("the first recipe").
	 */
	public string $item;

	/**
	 * Entries mid-sentence ("3 recipes", "no drafts among the recipes").
	 */
	public string $items;

	/**
	 * The button and screen that create an entry ("New recipe").
	 */
	public string $newItem;

	/**
	 * The editor's title ("Edit recipe").
	 */
	public string $editItem;

	/**
	 * The list's search field ("Search recipes").
	 */
	public string $searchItems;

	/**
	 * @param string  $singular    One entry ("Recipe").
	 * @param ?string $plural      Defaults to the singular made plural.
	 * @param ?string $menu        Defaults to the plural.
	 * @param ?string $item        Defaults to the singular mid-sentence.
	 * @param ?string $items       Defaults to the plural mid-sentence.
	 * @param ?string $newItem     Defaults to "New {item}".
	 * @param ?string $editItem    Defaults to "Edit {item}".
	 * @param ?string $searchItems Defaults to "Search {items}".
	 */
	public function __construct(
		public string $singular,
		?string $plural = null,
		?string $menu = null,
		?string $item = null,
		?string $items = null,
		?string $newItem = null,
		?string $editItem = null,
		?string $searchItems = null
	) {
		$this->plural      = self::given($plural) ?? self::pluralOf($singular);
		$this->menu        = self::given($menu) ?? $this->plural;
		$this->item        = self::given($item) ?? self::inSentence($singular);
		$this->items       = self::given($items) ?? self::inSentence($this->plural);
		$this->newItem     = self::given($newItem) ?? "New {$this->item}";
		$this->editItem    = self::given($editItem) ?? "Edit {$this->item}";
		$this->searchItems = self::given($searchItems) ?? "Search {$this->items}";
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
	 * type's name when it's missing.
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws InvalidContentType
	 */
	public static function fromArray(array $data, string $name): self
	{
		$label   = sprintf('Content type "%s" labels', $name);
		$unknown = array_diff(array_map(strval(...), array_keys($data)), self::KEYS);

		if ($unknown !== []) {
			throw new InvalidContentType(sprintf('%s has unknown options: %s.', $label, implode(', ', $unknown)));
		}

		try {
			$labels = new Definition($data, $label);
			$given  = array_map($labels->nullableString(...), array_combine(self::KEYS, self::KEYS));
		} catch (InvalidSchema $e) {
			throw new InvalidContentType($e->getMessage(), previous: $e);
		}

		return new self(...[...$given, 'singular' => self::given($given['singular']) ?? self::named($name)->singular]);
	}

	/**
	 * Returns the labels as a map that `fromArray()` accepts for a type
	 * with this name, leaving out the ones at their defaults.
	 *
	 * @return array<string, string>
	 */
	public function toArray(string $name): array
	{
		$data = [];

		foreach (self::KEYS as $key) {
			$default = new self(...[...$data, 'singular' => $data['singular'] ?? self::named($name)->singular]);

			if ($this->{$key} !== $default->{$key}) {
				$data[$key] = $this->{$key};
			}
		}

		return $data;
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
	private static function given(?string $label): ?string
	{
		$label = trim($label ?? '');

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
	 * Returns a name for use mid-sentence: its first letter lowercase,
	 * unless its first word has another capital ("FAQ", "HTML snippet").
	 */
	private static function inSentence(string $name): string
	{
		return preg_match('/^\S+\p{Lu}/u', $name) === 1 ? $name : mb_lcfirst($name);
	}
}
