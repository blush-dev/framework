<?php

/**
 * Authors content type.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Type;

use Override;
use Blush\Field\Field;
use Blush\Field\Fields\ReferenceField;

/**
 * The type whose entries are the people other entries credit (D-329):
 * each one's public name (its title), a bio (its body), and any fields.
 * Entries of the types that support authors (`ContentType::$authors`)
 * credit them through its field (`authors`, or 1.x's `author`):
 *
 *     new Authors(folder: 'authors', field: 'authors', aliases: ['author']);
 *
 * It has no routes, listing, feed, or sitemap of its own; an author's
 * pages are archives under the types that credit them. An author doesn't
 * need a file: one that's credited but missing is a virtual entry named
 * as the credit writes it. A site has at most one, and an admin account
 * may link to one of its entries.
 */
final readonly class Authors extends ContentType
{
	/**
	 * The front matter key entries credit authors through.
	 */
	public string $field;

	/**
	 * @param  string          $name        Lowercase letters, digits, and underscores.
	 * @param  ?string         $folder      The folder under `user/content`; defaults to `_` and the name.
	 * @param  ?string         $field       The field entries credit authors through; defaults to the name.
	 * @param  list<string>    $aliases     Other keys the field is read from.
	 * @param  bool            $public      Whether authors are public at all.
	 * @param  iterable<Field> $fields      Fields beyond the built-in ones.
	 * @param  bool            $closed      Whether undeclared front matter is an error.
	 * @param  ?TypeLabels     $labels      What people call it; defaults to labels made from the name.
	 * @param  string          $description What the type is for, in a sentence.
	 * @param  ?string         $icon        An icon name for the admin; defaults to its kind's.
	 * @throws InvalidContentType
	 */
	public function __construct(
		string $name = 'author',
		?string $folder = null,
		?string $field = null,
		public array $aliases = [],
		bool $public = true,
		iterable $fields = [],
		bool $closed = false,
		?TypeLabels $labels = null,
		string $description = '',
		?string $icon = null
	) {
		parent::__construct($name, $folder, $public, false, new Listing(), false, false, DateArchives::None, $fields, $closed, $labels, $description, $icon, false);

		$this->field = $field ?? $name;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function kind(): TypeKind
	{
		return TypeKind::Authors;
	}

	/**
	 * Authors aren't pages at their folder paths either; their pages are
	 * archives under the types that credit them.
	 */
	#[Override]
	public function servedAsPages(): bool
	{
		return false;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function termField(): ReferenceField
	{
		return new ReferenceField($this->field, $this->name)->aliases(...$this->aliases);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function options(): array
	{
		return [
			'field'   => $this->field === $this->name ? null : $this->field,
			'aliases' => $this->aliases
		];
	}
}
