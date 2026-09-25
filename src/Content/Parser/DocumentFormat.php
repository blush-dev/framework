<?php

/**
 * Document formats.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Parser;

/**
 * The built-in content file formats, keyed by file extension (the "Type
 * enum" of the enum + registry pattern, D-019).
 */
enum DocumentFormat: string
{
	case Md       = 'md';
	case Markdown = 'markdown';
	case Html     = 'html';
	case Json     = 'json';
	case Yaml     = 'yaml';
	case Yml      = 'yml';

	/**
	 * Returns the format's parser class.
	 *
	 * @return class-string<DocumentParser>
	 */
	public function parser(): string
	{
		return match ($this) {
			self::Md,
			self::Markdown => MarkdownDocumentParser::class,
			self::Html     => HtmlDocumentParser::class,
			self::Json,
			self::Yaml,
			self::Yml      => DataDocumentParser::class
		};
	}
}
