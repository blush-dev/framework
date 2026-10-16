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
use Blush\Extension\ExtensionSchema;
use Blush\Translation\Messages;
use Blush\Translation\Text;

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
	 * Reads a theme's `tokens.json`. What's wrong is written with
	 * `$messages`, and kept with the exception (`ExtensionException::$text`).
	 *
	 * @throws ExtensionException When it's missing, can't be parsed, sets something it can't, or is of a schema version Blush can't read (D-908).
	 */
	public static function load(string $path, Messages $messages = new Messages()): self
	{
		$file = "{$path}/" . self::FILE;

		if (! is_file($file)) {
			throw new ExtensionException($messages->t('admin.admin_tokens.no_file', 'it has no {file}.', file: self::FILE));
		}

		try {
			$data = json_decode((string) file_get_contents($file), true, 64, JSON_THROW_ON_ERROR);
		} catch (JsonException $e) {
			throw new ExtensionException($messages->t('admin.admin_tokens.unreadable', 'its {file} is invalid: {problem}', file: self::FILE, problem: $e->getMessage()), previous: $e);
		}

		$data = is_array($data) ? array_filter($data, is_string(...), ARRAY_FILTER_USE_KEY) : [];

		return self::fromArray(ExtensionSchema::AdminTokens->upgrade($data, self::FILE, $messages), $path, $messages);
	}

	/**
	 * Returns a warning when a theme's `tokens.json` is written to an
	 * older schema version than the current (D-908), showing it upgraded.
	 */
	public static function outdated(string $path, Messages $messages = new Messages()): ?Text
	{
		$file = "{$path}/" . self::FILE;

		try {
			$data = is_file($file) ? json_decode((string) file_get_contents($file), true, 64, JSON_THROW_ON_ERROR) : null;
		} catch (JsonException) {
			return null;
		}

		return is_array($data) ? ExtensionSchema::AdminTokens->outdated(array_filter($data, is_string(...), ARRAY_FILTER_USE_KEY), self::FILE, $messages) : null;
	}

	/**
	 * Reads tokens from their data, checking any font files against the
	 * theme's folder.
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws ExtensionException
	 */
	public static function fromArray(array $data, string $path, Messages $messages = new Messages()): self
	{
		unset($data['$schema']);

		$faces = [];

		if (is_array($data['typography'] ?? null)) {
			$faces = $data['typography']['fontFace'] ?? [];
			unset($data['typography']['fontFace']);
		}

		if (! is_array($faces) || ! array_is_list($faces)) {
			throw new ExtensionException($messages->t('admin.admin_tokens.faces_not_list', 'its "typography.fontFace" must be a list.'));
		}

		$values = [];

		foreach (self::flatten($data) as $key => $value) {
			$token = AdminToken::at($key) ?? throw new ExtensionException($messages->t('admin.admin_tokens.unknown_token', 'its {file} sets "{token}", which isn\'t a token an admin theme may set.', file: self::FILE, token: $key));

			$values[$token->value] = self::value($token, $value) ?? throw new ExtensionException(self::expected($token, $key, $messages));
		}

		return new self($values, array_map(static fn (mixed $face): FontFace => FontFace::fromArray($face, $path, $messages), $faces));
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
	public static function fromCache(array $data, string $path, Messages $messages = new Messages()): self
	{
		$values = [];

		foreach (is_array($data['values'] ?? null) ? $data['values'] : [] as $token => $value) {
			$token = AdminToken::tryFrom((string) $token);

			if ($token !== null && is_string($value)) {
				$values[$token->value] = $value;
			}
		}

		$faces = is_array($data['faces'] ?? null) ? $data['faces'] : [];

		return new self($values, array_values(array_map(static fn (mixed $face): FontFace => FontFace::fromArray($face, $path, $messages), $faces)));
	}

	/**
	 * Returns the value a token is set to, as CSS writes it, or `null`
	 * when it isn't set. A token left out that takes another's
	 * (`AdminToken::fallback()`) is that one's, as the stylesheet has it.
	 */
	public function get(AdminToken $token): ?string
	{
		$fallback = $token->fallback();

		return $this->values[$token->value] ?? ($fallback === null ? null : $this->get($fallback));
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
	 * order, and the shadows of the style it picks (D-844).
	 */
	public function css(string $selector): string
	{
		$declarations = '';

		foreach (AdminToken::cases() as $token) {
			if (isset($this->values[$token->value])) {
				$declarations .= "\t--{$token->property()}: {$this->values[$token->value]};\n";
			}
		}

		$style = ShadowStyle::tryFrom($this->get(AdminToken::BoxShadowStyle) ?? '') ?? ShadowStyle::Soft;

		foreach ($style->shadows() as $property => $shadow) {
			$declarations .= "\t--{$property}: {$shadow};\n";
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
			TokenType::LetterSpacing => self::measure($token, $value, 'em'),
			TokenType::Keyword       => is_string($value) && in_array($value, $token->keywords() ?? [], true) ? $value : null,
			TokenType::Image         => BackgroundImage::parse($value),
			TokenType::Size          => BackgroundImage::size($value)
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
	private static function expected(AdminToken $token, string $key, Messages $messages): Text
	{
		[$low, $high] = $token->range() ?? [0, 0];

		return match ($token->type()) {
			TokenType::Color         => $messages->t('admin.admin_tokens.expected_color', 'its "{token}" must be a color (hex, rgb(), hsl(), or oklch()) or light-dark() of two.', token: $key),
			TokenType::FontFamily    => $messages->t('admin.admin_tokens.expected_font_family', 'its "{token}" must be a list of font family names, such as ["Karla", "sans-serif"].', token: $key),
			TokenType::FontWeight    => $messages->t('admin.admin_tokens.expected_font_weight', 'its "{token}" must be a weight from {low} to {high}.', token: $key, low: sprintf('%d', $low), high: sprintf('%d', $high)),
			TokenType::Length        => $messages->t('admin.admin_tokens.expected_length', 'its "{token}" must be from {low}px to {high}px, such as "{low}px".', token: $key, low: (string) $low, high: (string) $high),
			TokenType::LetterSpacing => $messages->t('admin.admin_tokens.expected_letter_spacing', 'its "{token}" must be from {low}em to {high}em, such as "-.01em".', token: $key, low: (string) $low, high: (string) $high),
			TokenType::Keyword       => $messages->t('admin.admin_tokens.expected_keyword', 'its "{token}" must be one of {keywords}.', token: $key, keywords: '"' . implode('", "', $token->keywords() ?? []) . '"'),
			TokenType::Image         => $messages->t('admin.admin_tokens.expected_image', 'its "{token}" must be "none" or gradients, written with colors, numbers, and the gradients\' keywords only.', token: $key),
			TokenType::Size          => $messages->t('admin.admin_tokens.expected_size', 'its "{token}" must be "auto", or one or two sizes in pixels or a percent, such as "20px 20px".', token: $key)
		};
	}
}
