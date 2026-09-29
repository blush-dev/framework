<?php

/**
 * Taxonomy content type.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Type;

use Override;
use Blush\Content\Schema\Field;
use Blush\Content\Schema\Fields\ReferenceField;

/**
 * A type whose entries are terms that group other entries: tags,
 * categories, series, authors. Entries join a term through the
 * taxonomy's field (its name, unless `field` says otherwise, plus any
 * `aliases`). Its listing page lists the terms, and each term's page
 * lists the entries of `types` (every type when empty) that reference it:
 *
 *     new Taxonomy(
 *         'category',
 *         folder: 'topics',
 *         types: ['post'],
 *         termListing: new Listing(order: Order::Desc)
 *     );
 *
 * A term doesn't need a file; one that's referenced but missing is a
 * virtual term.
 */
final readonly class Taxonomy extends ContentType
{
	/**
	 * The front matter key entries use to reference this taxonomy's terms.
	 */
	public string $field;

	/**
	 * @param  string          $name        Lowercase letters, digits, and underscores.
	 * @param  ?string         $folder      The folder under `user/content`; defaults to the name.
	 * @param  list<string>    $types       The types a term's page lists; empty for every type.
	 * @param  ?string         $field       The term field; defaults to the name.
	 * @param  list<string>    $aliases     Other keys the term field is read from.
	 * @param  TypeUrls|false  $urls        URL settings, or `false` for no routes.
	 * @param  Listing         $listing     How the taxonomy's listing page lists its terms.
	 * @param  Listing         $termListing How a term's page lists entries.
	 * @param  TypeFeed|false  $feed        Feed settings (term feeds too), or `false` for no feeds.
	 * @param  bool            $public      Whether the taxonomy is public at all.
	 * @param  bool            $sitemap     Whether terms are in the sitemap.
	 * @param  iterable<Field> $fields      Fields beyond the built-in ones.
	 * @param  bool            $closed      Whether undeclared front matter is an error.
	 * @param  ?string         $label       For people, for a group of terms; defaults to the singular made plural.
	 * @param  ?string         $singular    For people, for one term; defaults to the name made readable.
	 * @throws InvalidContentType
	 */
	public function __construct(
		string $name,
		?string $folder = null,
		public array $types = [],
		?string $field = null,
		public array $aliases = [],
		TypeUrls|false $urls = new TypeUrls(),
		Listing $listing = new Listing(),
		public Listing $termListing = new Listing(),
		TypeFeed|false $feed = false,
		bool $public = true,
		bool $sitemap = true,
		iterable $fields = [],
		bool $closed = false,
		?string $label = null,
		?string $singular = null
	) {
		parent::__construct($name, $folder, $public, $urls, $listing, $feed, $sitemap, DateArchives::None, $fields, $closed, $label, $singular);

		$this->field = $field ?? $name;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function kind(): TypeKind
	{
		return TypeKind::Taxonomy;
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
	 * Returns a term page's 1.x query arguments, for `Query::fromArray()`,
	 * before the term itself is matched.
	 *
	 * @return array<string, mixed>
	 */
	public function termArguments(): array
	{
		return [...($this->types === [] ? [] : ['type' => $this->types]), ...$this->termListing->arguments()];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function options(): array
	{
		return [
			'types'       => $this->types,
			'field'       => $this->field === $this->name ? null : $this->field,
			'aliases'     => $this->aliases,
			'termListing' => $this->termListing->toArray()
		];
	}
}
