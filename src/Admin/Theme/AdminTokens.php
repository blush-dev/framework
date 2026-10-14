<?php

/**
 * Admin tokens.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin\Theme;

use JsonException;
use Blush\Extension\ExtensionException;

/**
 * An admin theme's `tokens.json` (D-827), read and checked: the values it
 * sets, each against its token's type and range, and the fonts it ships.
 * A theme never ships CSS; Blush writes its stylesheet from these, so it
 * can only set the tokens it's given. Anything it leaves out is
 * Neutral's.
 *
 *     {
 *         "color": {
 *             "background": "light-dark(#f6f2ec, #15120e)",
 *             "accent": { "default": "light-dark(#17595e, #72bec2)" }
 *         },
 *         "border": { "radius": { "small": "8px" } },
 *         "typography": {
 *             "fontFamily": { "ui": ["Karla", "sans-serif"] },
 *             "fontFace": [{ "fontFamily": "Karla", "src": "fonts/karla.woff2", "fontWeight": "200 800" }]
 *         }
 *     }
 *
 * A group's `default` may be given as the group itself (`"surface":
 * "#fff"` for `color.surface.default`). A key that isn't a token is
 * refused, as is a value that isn't of its type.
 */
final readonly class AdminTokens
{
	/**
	 * The file a theme's tokens are in.
	 */
	public const string FILE = 'tokens.json';

	/**
	 * @param array<string, string> $values The values it sets, by token, as CSS writes them.
	 * @param list<FontFace>        $faces  The fonts it ships.
	 */
	public function __construct(
		private array $values = [],
		public array $faces = []
	) {}

	/**
	 * Reads a theme's `tokens.json`.
	 *
	 * @throws ExtensionException When it's missing, can't be parsed, or sets something it can't.
	 */
	public static function load(string $path): self
	{
		$file = "{$path}/" . self::FILE;

		if (! is_file($file)) {
			throw new ExtensionException(sprintf('it has no %s.', self::FILE));
		}

		try {
			$data = json_decode((string) file_get_contents($file), true, 64, JSON_THROW_ON_ERROR);
		} catch (JsonException $e) {
			throw new ExtensionException(sprintf('its %s is invalid: %s', self::FILE, $e->getMessage()), previous: $e);
		}

		return self::fromArray(is_array($data) ? $data : [], $path);
	}

	/**
	 * Reads tokens from their data, checking any font files against the
	 * theme's folder.
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws ExtensionException
	 */
	public static function fromArray(array $data, string $path): self
	{
		unset($data['$schema']);

		$faces = [];

		if (is_array($data['typography'] ?? null)) {
			$faces = $data['typography']['fontFace'] ?? [];
			unset($data['typography']['fontFace']);
		}

		if (! is_array($faces) || ! array_is_list($faces)) {
			throw new ExtensionException('its "typography.fontFace" must be a list.');
		}

		$values = [];

		foreach (self::flatten($data) as $key => $value) {
			$token = AdminToken::at($key) ?? throw new ExtensionException(sprintf('its %s sets "%s", which isn\'t a token an admin theme may set.', self::FILE, $key));

			$values[$token->value] = self::value($token, $value) ?? throw new ExtensionException(sprintf('its "%s" %s.', $key, self::expected($token)));
		}

		return new self($values, array_map(static fn (mixed $face): FontFace => FontFace::fromArray($face, $path), $faces));
	}

	/**
	 * Returns what `fromArray()` reads back.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return [
			'values' => $this->values,
			'faces'  => array_map(static fn (FontFace $face): array => $face->toArray(), $this->faces)
		];
	}

	/**
	 * Reads back what `toArray()` gave, as the cache keeps it.
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws ExtensionException
	 */
	public static function fromCache(array $data, string $path): self
	{
		$values = [];

		foreach (is_array($data['values'] ?? null) ? $data['values'] : [] as $token => $value) {
			$token = AdminToken::tryFrom((string) $token);

			if ($token !== null && is_string($value)) {
				$values[$token->value] = $value;
			}
		}

		$faces = is_array($data['faces'] ?? null) ? $data['faces'] : [];

		return new self($values, array_values(array_map(static fn (mixed $face): FontFace => FontFace::fromArray($face, $path), $faces)));
	}

	/**
	 * Returns the value a token is set to, as CSS writes it, or `null`
	 * when it isn't set.
	 */
	public function get(AdminToken $token): ?string
	{
		return $this->values[$token->value] ?? null;
	}

	/**
	 * Returns a color token's value, or `null` when it isn't set.
	 */
	public function color(AdminToken $token): ?ColorValue
	{
		$value = $token->type() === TokenType::Color ? $this->get($token) : null;

		return $value === null ? null : ColorValue::parse($value);
	}

	/**
	 * Whether it sets a token.
	 */
	public function has(AdminToken $token): bool
	{
		return isset($this->values[$token->value]);
	}

	/**
	 * Returns a copy with these tokens' values laid over these: an admin
	 * theme's over Neutral's, for what the theme leaves out.
	 */
	#[\NoDiscard]
	public function with(self $over): self
	{
		return new self([...$this->values, ...$over->values], $over->faces);
	}

	/**
	 * Returns the stylesheet these tokens make: the font faces, then each
	 * value set on the root under a selector of its own, in the tokens'
	 * order.
	 */
	public function css(string $selector): string
	{
		$declarations = '';

		foreach (AdminToken::cases() as $token) {
			if (isset($this->values[$token->value])) {
				$declarations .= "\t--{$token->property()}: {$this->values[$token->value]};\n";
			}
		}

		$faces = array_map(static fn (FontFace $face): string => $face->css() . "\n\n", $this->faces);

		return implode('', $faces) . ($declarations === '' ? '' : "{$selector} {\n{$declarations}}\n");
	}

	/**
	 * Flattens tokens' data into values by path (`color.surface.subtle`),
	 * keeping lists (a family stack) whole.
	 *
	 * @param  array<array-key, mixed> $data
	 * @return array<string, mixed>
	 */
	private static function flatten(array $data, string $prefix = ''): array
	{
		$flat = [];

		foreach ($data as $key => $value) {
			$path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

			// An empty group (`{}`) sets nothing.
			if ($value === []) {
				continue;
			}

			if (is_array($value) && ! array_is_list($value)) {
				$flat = [...$flat, ...self::flatten($value, $path)];
			} else {
				$flat[$path] = $value;
			}
		}

		return $flat;
	}

	/**
	 * Returns a value as CSS writes it, or `null` when it isn't of its
	 * token's type, or is out of its range.
	 */
	private static function value(AdminToken $token, mixed $value): ?string
	{
		return match ($token->type()) {
			TokenType::Color         => is_string($value) ? ColorValue::parse($value)?->css() : null,
			TokenType::FontFamily    => FontFamily::parse($value)?->css(),
			TokenType::FontWeight    => self::weight($token, $value),
			TokenType::Length        => self::measure($token, $value, 'px'),
			TokenType::LetterSpacing => self::measure($token, $value, 'em')
		};
	}

	/**
	 * Returns a length or letter spacing in its unit, within its range, as
	 * CSS writes it, or `null`.
	 */
	private static function measure(AdminToken $token, mixed $value, string $unit): ?string
	{
		if (! is_string($value) || preg_match('/^(-?(?:\d+(?:\.\d+)?|\.\d+))(' . $unit . ')?$/', trim($value), $match) !== 1) {
			return null;
		}

		$number = (float) $match[1];

		// A unit is needed for anything but nothing.
		if (($match[2] ?? '') === '' && $number !== 0.0) {
			return null;
		}

		[$low, $high] = $token->range() ?? [0, 0];

		return $number < $low || $number > $high ? null : ($number === 0.0 ? '0' : $match[1] . $unit);
	}

	/**
	 * Returns a weight within its range, or `null`.
	 */
	private static function weight(AdminToken $token, mixed $value): ?string
	{
		$weight = is_int($value) ? $value : (is_string($value) && preg_match('/^\d{3}$/', $value) === 1 ? (int) $value : null);

		[$low, $high] = $token->range() ?? [0, 0];

		return $weight === null || $weight < $low || $weight > $high ? null : (string) $weight;
	}

	/**
	 * Says what a token's value must be, for messages.
	 */
	private static function expected(AdminToken $token): string
	{
		[$low, $high] = $token->range() ?? [0, 0];

		return match ($token->type()) {
			TokenType::Color         => 'must be a color (hex, rgb(), hsl(), or oklch()) or light-dark() of two',
			TokenType::FontFamily    => 'must be a list of font family names, such as ["Karla", "sans-serif"]',
			TokenType::FontWeight    => sprintf('must be a weight from %d to %d', $low, $high),
			TokenType::Length        => sprintf('must be from %spx to %spx, such as "%spx"', $low, $high, $low),
			TokenType::LetterSpacing => sprintf('must be from %sem to %sem, such as "-.01em"', $low, $high)
		};
	}
}
