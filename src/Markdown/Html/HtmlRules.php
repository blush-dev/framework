<?php

/**
 * HTML rules.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Markdown\Html;

/**
 * What raw HTML each level of `HtmlAccess` may add (D-495):
 *
 * - **Refused** for everyone: the tags in `REFUSED` (script, frames,
 *   plugins, forms, styles, and the like), event handler attributes
 *   (`on…`), `srcdoc`, and unsafe addresses (`javascript:`, `vbscript:`,
 *   `file:`, and `data:` that isn't a picture) in any URL attribute or
 *   Markdown link. A page that filters raw HTML (`RawHtml::Filter`)
 *   shows the refused tags as text.
 * - **Allowed** (`html.allowed`): only the tags in `ALLOWED`, each with
 *   the attributes listed for it and the global ones (`GLOBAL`, plus
 *   `aria-*` and `data-*`). No `style`.
 * - **Unfiltered** (`html.unfiltered`): any other tag and attribute.
 *
 * `problems()` names each thing a body has that its writer's level
 * doesn't allow, once per time it's found, so a save can be refused
 * only for what it adds.
 */
final class HtmlRules
{
	/**
	 * Tags nobody may add.
	 *
	 * @var list<string>
	 */
	public const array REFUSED = [
		'applet', 'base', 'button', 'embed', 'form', 'frame', 'frameset',
		'iframe', 'input', 'link', 'math', 'meta', 'noembed', 'noframes',
		'noscript', 'object', 'optgroup', 'option', 'plaintext', 'portal',
		'script', 'select', 'style', 'svg', 'template', 'textarea', 'title',
		'xmp'
	];

	/**
	 * Attributes any allowed tag may have, with `aria-*` and `data-*`.
	 *
	 * @var list<string>
	 */
	public const array GLOBAL = ['class', 'dir', 'hidden', 'id', 'lang', 'role', 'title', 'translate'];

	/**
	 * The allowed list: tags, each with the attributes it may have
	 * beyond the global ones.
	 *
	 * @var array<string, list<string>>
	 */
	public const array ALLOWED = [
		'a'          => ['href', 'hreflang', 'rel', 'target', 'download'],
		'abbr'       => [],
		'audio'      => ['controls', 'loop', 'muted', 'preload', 'src'],
		'b'          => [],
		'bdi'        => [],
		'bdo'        => [],
		'blockquote' => ['cite'],
		'br'         => [],
		'caption'    => [],
		'cite'       => [],
		'code'       => [],
		'col'        => ['span'],
		'colgroup'   => ['span'],
		'data'       => ['value'],
		'dd'         => [],
		'del'        => ['cite', 'datetime'],
		'details'    => ['name', 'open'],
		'dfn'        => [],
		'div'        => [],
		'dl'         => [],
		'dt'         => [],
		'em'         => [],
		'figcaption' => [],
		'figure'     => [],
		'h1'         => [],
		'h2'         => [],
		'h3'         => [],
		'h4'         => [],
		'h5'         => [],
		'h6'         => [],
		'hr'         => [],
		'i'          => [],
		'img'        => ['alt', 'decoding', 'height', 'loading', 'sizes', 'src', 'srcset', 'width'],
		'ins'        => ['cite', 'datetime'],
		'kbd'        => [],
		'li'         => ['value'],
		'mark'       => [],
		'ol'         => ['reversed', 'start', 'type'],
		'p'          => [],
		'picture'    => [],
		'pre'        => [],
		'q'          => ['cite'],
		'rp'         => [],
		'rt'         => [],
		'ruby'       => [],
		's'          => [],
		'samp'       => [],
		'section'    => [],
		'small'      => [],
		'source'     => ['height', 'media', 'sizes', 'src', 'srcset', 'type', 'width'],
		'span'       => [],
		'strong'     => [],
		'sub'        => [],
		'summary'    => [],
		'sup'        => [],
		'table'      => [],
		'tbody'      => [],
		'td'         => ['colspan', 'headers', 'rowspan'],
		'tfoot'      => [],
		'th'         => ['abbr', 'colspan', 'headers', 'rowspan', 'scope'],
		'thead'      => [],
		'time'       => ['datetime'],
		'tr'         => [],
		'track'      => ['default', 'kind', 'label', 'src', 'srclang'],
		'u'          => [],
		'ul'         => [],
		'var'        => [],
		'video'      => ['controls', 'height', 'loop', 'muted', 'playsinline', 'poster', 'preload', 'src', 'width'],
		'wbr'        => []
	];

	/**
	 * Attributes nobody may add, besides event handlers.
	 *
	 * @var list<string>
	 */
	public const array REFUSED_ATTRIBUTES = ['formaction', 'srcdoc'];

	/**
	 * Attributes that hold an address.
	 *
	 * @var list<string>
	 */
	public const array URL_ATTRIBUTES = ['action', 'background', 'cite', 'data', 'href', 'longdesc', 'ping', 'poster', 'src', 'srcset', 'xlink:href'];

	/**
	 * Returns what the markup has that the level doesn't allow, once for
	 * each time it's found: `<iframe>`, `<img onerror>`, or
	 * `javascript: link`.
	 *
	 * @return list<string>
	 */
	public static function problems(RawMarkup $markup, HtmlAccess $access): array
	{
		$problems = [];

		foreach ($markup->html as $html) {
			foreach (HtmlTag::all($html) as $tag) {
				array_push($problems, ...self::tagProblems($tag, $access));
			}
		}

		foreach ($markup->urls as $url) {
			if (self::isUnsafe($url)) {
				$problems[] = self::scheme($url) . ' link';
			}
		}

		return $problems;
	}

	/**
	 * Whether an address could run script, or load something that could:
	 * `javascript:`, `vbscript:`, `file:`, or `data:` that isn't a PNG,
	 * GIF, JPEG, or WebP picture, however it's cased or spaced.
	 */
	public static function isUnsafe(string $url): bool
	{
		$url = strtolower(preg_replace('/[\x00-\x20]+/', '', html_entity_decode($url, ENT_QUOTES | ENT_HTML5)) ?? $url);

		return preg_match('/^(?:javascript|vbscript|file):/', $url) === 1
			|| (str_starts_with($url, 'data:') && preg_match('/^data:image\/(?:png|gif|jpeg|webp)[;,]/', $url) !== 1);
	}

	/**
	 * @return list<string>
	 */
	private static function tagProblems(HtmlTag $tag, HtmlAccess $access): array
	{
		$label = $tag->name === '!' ? '<!…>' : ($tag->name === '?' ? '<?…>' : "<{$tag->name}>");

		if ($access === HtmlAccess::None || in_array($tag->name, self::REFUSED, true)) {
			return [$label];
		}

		$allowed = self::ALLOWED[$tag->name] ?? null;

		if ($access === HtmlAccess::Allowed && $allowed === null) {
			return [$label];
		}

		$problems = [];

		foreach ($tag->attributes as $name => $value) {
			$refused = str_starts_with($name, 'on') || in_array($name, self::REFUSED_ATTRIBUTES, true);
			$listed  = in_array($name, self::GLOBAL, true) || in_array($name, $allowed ?? [], true) || preg_match('/^(?:aria|data)-[a-z0-9_.:-]+$/', $name) === 1;

			if ($refused || ($access === HtmlAccess::Allowed && ! $listed)) {
				$problems[] = "<{$tag->name} {$name}>";
			} elseif (in_array($name, self::URL_ATTRIBUTES, true) && ($unsafe = self::unsafeIn($name, $value)) !== null) {
				$problems[] = self::scheme($unsafe) . " in <{$tag->name} {$name}>";
			}
		}

		return $problems;
	}

	/**
	 * Returns an attribute's unsafe address, or `null`; a `srcset` is a
	 * list.
	 */
	private static function unsafeIn(string $name, string $value): ?string
	{
		$candidates = $name === 'srcset' ? array_map(trim(...), explode(',', $value)) : [$value];

		return array_find($candidates, self::isUnsafe(...));
	}

	/**
	 * Names an unsafe address by its scheme: `javascript:`.
	 */
	private static function scheme(string $url): string
	{
		$url = strtolower(preg_replace('/[\x00-\x20]+/', '', html_entity_decode($url, ENT_QUOTES | ENT_HTML5)) ?? $url);

		return (strstr($url, ':', true) ?: 'unsafe') . ':';
	}
}
