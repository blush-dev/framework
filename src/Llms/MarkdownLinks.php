<?php

/**
 * Markdown links.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Llms;

use Blush\Component\ComponentRegistry;
use Blush\Core\AppConfig;
use Blush\Field\Fields\MediaField;
use Blush\Markdown\CommonMark\Directive\DirectiveAttributes;
use Blush\Media\MediaResolver;

/**
 * Gives a Markdown body's links full URLs (D-396), so a Markdown page
 * still points somewhere once it's read away from the site. The URLs
 * are the ones the HTML page has: a media reference becomes its media
 * URL, then a root-relative URL becomes absolute on the site's origin.
 *
 * Rewritten, as written otherwise: inline link and image destinations
 * (`[text](/about)`, `![alt](/user/media/a.jpg "Title")`, `(<…>)`),
 * reference definitions (`[1]: /about`), and a registered component's
 * media and link props in a directive's attributes
 * (`::audio{src=/media/a.mp3}`), as `ComponentDirectives` resolves them.
 *
 * Left alone: code (fenced blocks, indented blocks, and inline code
 * spans) and HTML (blocks, and attributes in inline HTML), plus full
 * URLs, `//` URLs, `#` fragments, and relative paths that aren't media.
 * Code and HTML blocks are found line by line, leaning toward leaving
 * text alone: an indented line after a blank one counts as code, even
 * in a list, and a line opening with a tag starts an HTML block.
 */
final readonly class MarkdownLinks
{
	/**
	 * A code span, a link destination, or a directive with attributes,
	 * leftmost first, so links inside code spans are passed over.
	 */
	private const string INLINE = '/(`+).+?(?<!`)\1(?!`)'
		. '|(\]\([ \t]*)(<[^>\n]*>|[^\s)]+)'
		. '|((?<![:\w\\\\]):+' . DirectiveAttributes::NAME . '(?:\[[^\]\n]*\])?)\{([^}\n]*)\}/';

	/**
	 * A directive attribute, as `DirectiveAttributes::parse()` reads
	 * them, with its parts kept to rewrite its value.
	 */
	private const string ATTRIBUTE = '/([.#][A-Za-z0-9_:-]+)|([A-Za-z_:][A-Za-z0-9_:.-]*)(?:(\s*=\s*)(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=]+)))?/';

	/**
	 * A reference definition: its label, then its destination.
	 */
	private const string DEFINITION = '/^( {0,3}\[[^\]\n]+\]:[ \t]*)(<[^>\n]*>|\S+)/';

	/**
	 * HTML block openings whose blocks run to a closing marker, not a
	 * blank line: the tag (or comment opening), then the closing marker.
	 *
	 * @var array<string, string>
	 */
	private const array RAW_BLOCKS = [
		'/^ {0,3}<(pre|script|style|textarea)(?:\s|>|$)/i' => '</%s>',
		'/^ {0,3}<!--/'                                    => '-->'
	];

	public function __construct(
		private MediaResolver $media,
		private ComponentRegistry $components,
		private AppConfig $app
	) {}

	/**
	 * Returns a Markdown body with its links' full URLs.
	 */
	public function absolute(string $markdown): string
	{
		$lines  = preg_split('/(?<=\n)/', $markdown) ?: [$markdown];
		$fence  = null;
		$until  = null;
		$html   = false;
		$code   = false;
		$blank  = true;
		$output = '';

		foreach ($lines as $line) {
			$text    = rtrim($line, "\r\n");
			$isBlank = trim($text) === '';
			$skip    = true;
			$indent  = false;

			if ($fence !== null) {
				// To a closing fence of its character, at least as long.
				if (preg_match('/^[ \t]*(?:>[ \t]?)*[ \t]*' . preg_quote($fence[0], '/') . '{' . strlen($fence) . ',}[ \t]*$/', $text) === 1) {
					$fence = null;
				}
			} elseif ($until !== null) {
				if (stripos($text, $until) !== false) {
					$until = null;
				}
			} elseif ($html) {
				$html = ! $isBlank;
			} elseif (preg_match('/^[ \t]*(?:>[ \t]?)*[ \t]*(`{3,}(?=[^`]*$)|~{3,})/', $text, $match) === 1) {
				$fence = $match[1];
			} elseif (($code || $blank) && ! $isBlank && preg_match('/^(?: {4}|\t)/', $text) === 1) {
				$indent = true;
			} elseif (($raw = self::rawBlock($text)) !== null) {
				[$closing, $offset] = $raw;
				$until = stripos($text, $closing, $offset) === false ? $closing : null;
			} elseif (preg_match('/^ {0,3}<\/?[A-Za-z][A-Za-z0-9-]*(?:\s|\/?>|$)/', $text) === 1) {
				$html = true;
			} else {
				$skip = false;
			}

			// Indented code runs on over blank lines.
			$code   = $indent || ($code && $isBlank);
			$blank  = $isBlank;
			$output .= $skip ? $line : $this->line($line);
		}

		return $output;
	}

	/**
	 * Returns the closing marker of the raw HTML block a line opens, and
	 * where the opening ends, or `null`.
	 *
	 * @return ?array{string, int}
	 */
	private static function rawBlock(string $text): ?array
	{
		foreach (self::RAW_BLOCKS as $pattern => $closing) {
			if (preg_match($pattern, $text, $match) === 1) {
				return [sprintf($closing, strtolower($match[1] ?? '')), strlen($match[0])];
			}
		}

		return null;
	}

	/**
	 * Rewrites one line of Markdown text.
	 */
	private function line(string $line): string
	{
		if (preg_match(self::DEFINITION, $line, $match) === 1) {
			return $match[1] . $this->destination($match[2]) . substr($line, strlen($match[0]));
		}

		return preg_replace_callback(self::INLINE, function (array $match): string {
			if ($match[3] !== null) {
				return $match[2] . $this->destination($match[3]);
			}

			if ($match[6] !== null) {
				return $match[4] . '{' . $this->attributes((string) $match[5], $match[6]) . '}';
			}

			return (string) $match[0];
		}, $line, flags: PREG_UNMATCHED_AS_NULL) ?? $line;
	}

	/**
	 * Returns a link destination, `<…>` or bare, with its full URL.
	 */
	private function destination(string $destination): string
	{
		return str_starts_with($destination, '<') && str_ends_with($destination, '>')
			? '<' . $this->url(substr($destination, 1, -1), true) . '>'
			: $this->url($destination, true);
	}

	/**
	 * Returns a directive's attributes with its component's media and
	 * link props given full URLs.
	 */
	private function attributes(string $name, string $attributes): string
	{
		$definition = $this->components->get($name);

		if ($definition === null) {
			return $attributes;
		}

		$media = [];

		foreach ($definition->props() as $field) {
			if ($field instanceof MediaField) {
				$media[] = $field->name;
			}
		}

		$links = $definition->links();

		if ($media === [] && $links === []) {
			return $attributes;
		}

		return preg_replace_callback(self::ATTRIBUTE, function (array $match) use ($media, $links): string {
			$key   = $match[2];
			$value = $match[4] ?? $match[5] ?? $match[6];

			if ($key === null || $match[3] === null || $value === null || (! in_array($key, $media, true) && ! in_array($key, $links, true))) {
				return (string) $match[0];
			}

			$url   = $this->url(trim($value), in_array($key, $media, true));
			$quote = match (true) {
				$match[4] !== null                       => '"',
				$match[5] !== null                       => "'",
				preg_match('/[\s"\'=]/', $url) === 1 => '"',
				default                                  => ''
			};

			return $key . $match[3] . $quote . $url . $quote;
		}, $attributes, flags: PREG_UNMATCHED_AS_NULL) ?? $attributes;
	}

	/**
	 * Returns a URL as the HTML page has it: a media reference as its
	 * media URL, then a root-relative URL as a full one.
	 */
	private function url(string $url, bool $media): string
	{
		$file = $media ? $this->media->resolve($url) : null;
		$url  = $file === null ? $url : $file->url;

		return str_starts_with($url, '/') && ! str_starts_with($url, '//') ? $this->app->absoluteUrl($url) : $url;
	}
}
