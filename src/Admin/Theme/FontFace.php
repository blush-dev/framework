<?php

/**
 * Font face.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin\Theme;

use Blush\Extension\ExtensionException;

/**
 * A font an admin theme ships (D-827), as `@font-face` has it: a woff2
 * file inside the theme's folder, its family, weight, style, and the
 * characters it covers. Blush writes the rule, always with
 * `font-display: swap`, and serves the file.
 */
final readonly class FontFace
{
	/**
	 * The largest font file a theme may ship.
	 */
	public const int MAX_BYTES = 512 * 1024;

	/**
	 * Matches a path inside the theme: segments of letters, digits, `.`,
	 * `_`, and `-`, ending in `.woff2`.
	 */
	private const string SRC = '#^(?:[A-Za-z0-9_-][A-Za-z0-9._-]*/)*[A-Za-z0-9_-][A-Za-z0-9._-]*\.woff2$#';

	/**
	 * Matches a `unicode-range`: code points or ranges, comma separated.
	 */
	private const string RANGE = '/^U\+[0-9A-F?]{1,6}(?:-[0-9A-F]{1,6})?(?:, ?U\+[0-9A-F?]{1,6}(?:-[0-9A-F]{1,6})?)*$/i';

	/**
	 * @param string $src    Its file, relative to the theme's folder.
	 * @param string $weight A weight, or a range of two (`200 800`).
	 */
	public function __construct(
		public string $family,
		public string $src,
		public string $weight = '400',
		public string $style = 'normal',
		public string $unicodeRange = ''
	) {}

	/**
	 * Reads a font face from `typography.fontFace`, checking its file is
	 * in the theme's folder.
	 *
	 * @throws ExtensionException
	 */
	public static function fromArray(mixed $data, string $path): self
	{
		if (! is_array($data) || array_is_list($data) && $data !== []) {
			throw new ExtensionException('each of "typography.fontFace" must be an object.');
		}

		$unknown = array_diff(array_map(strval(...), array_keys($data)), ['fontFamily', 'src', 'fontWeight', 'fontStyle', 'unicodeRange']);

		if ($unknown !== []) {
			throw new ExtensionException(sprintf('a "typography.fontFace" has "%s", which isn\'t one of fontFamily, src, fontWeight, fontStyle, and unicodeRange.', implode('", "', $unknown)));
		}

		$family = $data['fontFamily'] ?? null;
		$src    = $data['src'] ?? null;
		$weight = $data['fontWeight'] ?? '400';
		$style  = $data['fontStyle'] ?? 'normal';
		$range  = $data['unicodeRange'] ?? '';

		if (! is_string($family) || FontFamily::name($family) === null) {
			throw new ExtensionException('a "typography.fontFace" needs a "fontFamily": a family name of letters, digits, spaces, hyphens, and underscores.');
		}

		if (! is_string($src) || preg_match(self::SRC, $src) !== 1) {
			throw new ExtensionException(sprintf('the "%s" font face needs a "src": a .woff2 file inside the theme, such as "fonts/%s.woff2".', $family, strtolower(str_replace(' ', '-', $family))));
		}

		$file = "{$path}/{$src}";

		if (! is_file($file)) {
			throw new ExtensionException(sprintf('the "%s" font face\'s file, %s, isn\'t in the theme.', $family, $src));
		}

		if ((int) filesize($file) > self::MAX_BYTES) {
			throw new ExtensionException(sprintf('the "%s" font face\'s file, %s, is over %d KB.', $family, $src, self::MAX_BYTES / 1024));
		}

		$weight = is_int($weight) ? (string) $weight : $weight;

		if (! is_string($weight) || ! self::isWeight($weight)) {
			throw new ExtensionException(sprintf('the "%s" font face\'s "fontWeight" must be a weight from 1 to 1000, or two (such as "200 800").', $family));
		}

		if (! in_array($style, ['normal', 'italic', 'oblique'], true)) {
			throw new ExtensionException(sprintf('the "%s" font face\'s "fontStyle" must be normal, italic, or oblique.', $family));
		}

		if (! is_string($range) || $range !== '' && preg_match(self::RANGE, $range) !== 1) {
			throw new ExtensionException(sprintf('the "%s" font face\'s "unicodeRange" must be code points or ranges, such as "U+0000-00FF, U+0131".', $family));
		}

		return new self($family, $src, preg_replace('/\s+/', ' ', trim($weight)) ?? $weight, $style, strtoupper(str_replace('u+', 'U+', $range)));
	}

	/**
	 * Returns what `fromArray()` reads back.
	 *
	 * @return array<string, string>
	 */
	public function toArray(): array
	{
		return array_filter([
			'fontFamily'   => $this->family,
			'src'          => $this->src,
			'fontWeight'   => $this->weight,
			'fontStyle'    => $this->style,
			'unicodeRange' => $this->unicodeRange
		], static fn (string $value): bool => $value !== '');
	}

	/**
	 * Returns its `@font-face` rule, its file relative to the stylesheet,
	 * which is served beside the theme's files.
	 */
	public function css(): string
	{
		$range = $this->unicodeRange === '' ? '' : "\n\tunicode-range: {$this->unicodeRange};";

		return <<<CSS
			@font-face {
				font-family: "{$this->family}";
				font-style: {$this->style};
				font-weight: {$this->weight};
				font-display: swap;
				src: url("{$this->src}") format("woff2");{$range}
			}
			CSS;
	}

	/**
	 * Whether a weight is one (1 to 1000) or a range of two.
	 */
	private static function isWeight(string $weight): bool
	{
		$parts = preg_split('/\s+/', trim($weight)) ?: [];

		return count($parts) <= 2 && array_all($parts, static fn (string $part): bool => preg_match('/^\d{1,4}$/', $part) === 1 && (int) $part >= 1 && (int) $part <= 1000);
	}
}
