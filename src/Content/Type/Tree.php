<?php

/**
 * Tree content type.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Type;

use Override;
use Blush\Content\Entry\Position;
use Blush\Storage\Record\Order;
use Blush\Field\Field;

/**
 * A type whose entries nest by folder (D-386). The built-in `page` type is
 * one: every file no other type claims, from the content root down.
 * Others are kept in `_` and their name like every type (D-683), served
 * under their prefix, their name unless they give one (`_doc/install.md`
 * at `/docs/install` with the prefix `docs`), with the folder's `index`
 * as their landing page. Trees have no listing, feed, or routes of their
 * own; the page catch-all serves them. A site replaces the built-in to
 * give pages fields:
 *
 *     new Tree(fields: [new StringField('subtitle')]);
 *
 * and adds a tree of its own beside it:
 *
 *     new Tree('doc', labels: new TypeLabels('Doc'), prefix: 'docs');
 */
final readonly class Tree extends ContentType
{
	/**
	 * The path a tree in a folder is served under, without slashes, or
	 * `null` for its name (D-683). The site's pages have none.
	 */
	public ?string $urlPrefix;

	/**
	 * @param  string          $name        Lowercase letters, digits, and underscores; `page` is the site's pages, at the content root, and any other is kept in `_` and its name.
	 * @param  bool            $public      Whether its entries are public at all.
	 * @param  bool            $sitemap     Whether its entries are in the sitemap.
	 * @param  iterable<Field> $fields      Fields beyond the built-in ones.
	 * @param  bool            $closed      Whether undeclared front matter is an error.
	 * @param  ?TypeLabels     $labels      What people call it; defaults to labels made from the name ("Pages" and "Page").
	 * @param  string          $description What pages are for, in a sentence.
	 * @param  ?string         $icon        An icon name for the admin; defaults to its kind's.
	 * @param  ?string        $byline      The credit relation that's its byline (D-602), or `null` for its only one.
	 * @param  bool            $llms        Whether its entries are listed in `llms.txt` (D-398).
	 * @param  ?FileName       $filename    How new files are named (D-514).
	 * @param  ?string         $prefix      The path a tree in a folder is served under (D-683); its name by default.
	 * @throws InvalidContentType
	 */
	public function __construct(
		string $name = 'page',
		bool $public = true,
		bool $sitemap = true,
		iterable $fields = [],
		bool $closed = false,
		?TypeLabels $labels = null,
		string $description = '',
		?string $icon = null,
		?string $byline = null,
		bool $llms = true,
		?FileName $filename = null,
		?string $prefix = null
	) {
		parent::__construct($name, null, $public, false, new Listing(), false, $sitemap, DateArchives::None, $fields, $closed, $labels, $description, $icon, $byline, $llms, $filename);

		$prefix = trim($prefix ?? '', '/ ');

		if ($prefix !== '' && $this->atRoot()) {
			throw new InvalidContentType(sprintf('Content type "%s" is the site\'s pages, served from the root, so it takes no prefix.', $name));
		}

		if ($prefix !== '' && array_any(explode('/', $prefix), static fn (string $segment): bool => in_array($segment, ['', '.', '..'], true) || str_starts_with($segment, '_'))) {
			throw new InvalidContentType(sprintf('Content type "%s" has an invalid prefix "%s".', $name, $prefix));
		}

		$this->urlPrefix = $prefix === '' ? null : $prefix;
	}

	/**
	 * Returns the path its pages are served under: its prefix, else its
	 * name; the site's pages are served from the root.
	 */
	#[Override]
	public function pagePath(): string
	{
		return $this->atRoot() ? '' : $this->urlPrefix ?? $this->name;
	}

	/**
	 * Returns whether the tree is the site's pages: the type at the
	 * content root, whose root is the site. A tree in a folder has that
	 * folder's `index` as its index page (D-386).
	 */
	public function atRoot(): bool
	{
		return $this->folder === '';
	}

	/**
	 * A tree in a folder can be changed from data; the site's pages
	 * can't.
	 */
	#[Override]
	public function isOverridable(): bool
	{
		return ! $this->atRoot();
	}

	#[Override]
	public function role(): string
	{
		return $this->atRoot() ? 'the site\'s pages' : parent::role();
	}

	/**
	 * Returns its entries' order: by position, then title (D-412).
	 *
	 * @inheritDoc
	 */
	#[Override]
	public function order(): array
	{
		return [Position::FIELD, Order::Asc];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function kind(): TypeKind
	{
		return TypeKind::Tree;
	}

	/**
	 * A tree's folders are its pages' (D-088): `about/biography.md` is
	 * `about/biography`.
	 */
	#[Override]
	public function keysByFolder(): bool
	{
		return true;
	}

	/**
	 * Returns the entry the folder an entry is in belongs to: `about` for
	 * `about/biography`. Top-level entries have none.
	 */
	#[Override]
	public function parentKey(string $key, array $values): ?string
	{
		return str_contains($key, '/') ? dirname($key) : null;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function options(): array
	{
		return ['byline' => $this->byline, 'prefix' => $this->urlPrefix];
	}

	/**
	 * The site's pages are the content root; any other tree is kept in
	 * `_` and its name.
	 */
	#[Override]
	protected static function folderOf(string $name): string
	{
		return $name === BuiltInType::Page->value ? '' : parent::folderOf($name);
	}
}
