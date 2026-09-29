<?php

/**
 * Pages content type.
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

/**
 * The built-in `page` type: every file no other type claims, from the
 * content root down. Pages have no listing, feed, or routes of their own;
 * the page catch-all serves them at their file paths. A site replaces the
 * built-in to give pages fields:
 *
 *     new Pages(fields: [new StringField('subtitle')]);
 */
final readonly class Pages extends ContentType
{
	/**
	 * @param  string          $name     Lowercase letters, digits, and underscores.
	 * @param  string          $folder   The folder under `user/content`; the content root by default.
	 * @param  bool            $public   Whether pages are public at all.
	 * @param  bool            $sitemap  Whether pages are in the sitemap.
	 * @param  iterable<Field> $fields   Fields beyond the built-in ones.
	 * @param  bool            $closed   Whether undeclared front matter is an error.
	 * @param  ?string         $label    For people, for a group of pages; defaults to "Pages".
	 * @param  ?string         $singular For people, for one page; defaults to "Page".
	 * @throws InvalidContentType
	 */
	public function __construct(
		string $name = 'page',
		string $folder = '',
		bool $public = true,
		bool $sitemap = true,
		iterable $fields = [],
		bool $closed = false,
		?string $label = null,
		?string $singular = null
	) {
		parent::__construct($name, $folder, $public, false, new Listing(), false, $sitemap, DateArchives::None, $fields, $closed, $label, $singular);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function kind(): TypeKind
	{
		return TypeKind::Pages;
	}
}
