<?php

/**
 * Redirect message.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin\Redirects;

use Stringable;

/**
 * A line the Redirects screen shows (D-686): under a form field, in the
 * form's notice, or in a row's problem. Its text is parts, plain, in
 * code type (a path), or strong (a title), so the admin can draw them
 * without reading markup from what was typed. A message may carry
 * the one fix it offers: a label and an action the admin knows.
 *
 * Its text is translated (D-835): a message with parts in code or
 * strong type is one translated sentence, each part held by a slot
 * (`slot()`) among its parameters, and `of()` splits it around them, so
 * a translator can move the part.
 *
 * ```php
 * Message::of(MessageKind::Warn, $messages->t('…', 'Nothing answers {path}.', path: Message::slot(0)), Message::code($path));
 * ```
 *
 * @phpstan-type Part string|array{code: string}|array{strong: string}
 * @phpstan-type Fix array{label: string, action: string, value?: mixed}
 */
final readonly class Message
{
	/**
	 * @param list<Part> $parts
	 * @param ?Fix       $fix
	 */
	public function __construct(
		public MessageKind $kind,
		public array $parts,
		public ?array $fix = null
	) {}

	/**
	 * A message from translated text, each slot in it (`slot()`, by
	 * number) filled with that part, or those parts.
	 *
	 * @param Part|list<Part> ...$parts
	 */
	public static function of(MessageKind $kind, string|Stringable $text, string|array ...$parts): self
	{
		return new self($kind, self::split($text, ...$parts));
	}

	/**
	 * Translated text as parts, each slot in it (`slot()`, by number)
	 * filled with that part, or those parts.
	 *
	 * @param  Part|list<Part> ...$parts
	 * @return list<Part>
	 */
	public static function split(string|Stringable $text, string|array ...$parts): array
	{
		$pieces = preg_split('/\x{E002}(\d+)\x{E003}/u', (string) $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [(string) $text];
		$out    = [];

		foreach ($pieces as $index => $piece) {
			if ($index % 2 === 0) {
				if ($piece !== '') {
					$out[] = $piece;
				}

				continue;
			}

			$part = $parts[(int) $piece] ?? '';

			foreach (is_array($part) && array_is_list($part) ? $part : [$part] as $item) {
				$out[] = self::part($item);
			}
		}

		return $out;
	}

	/**
	 * A slot for a part, by its number among `of()`'s parts: given as a
	 * message's parameter, it holds the part's place in the translation.
	 */
	public static function slot(int $number): string
	{
		return "\u{E002}{$number}\u{E003}";
	}

	/**
	 * A path or address, in code type.
	 *
	 * @return array{code: string}
	 */
	public static function code(string $text): array
	{
		return ['code' => $text];
	}

	/**
	 * A title, in strong type.
	 *
	 * @return array{strong: string}
	 */
	public static function strong(string $text): array
	{
		return ['strong' => $text];
	}

	/**
	 * Returns a copy offering a fix.
	 */
	public function withFix(string|Stringable $label, string $action, mixed $value = null): self
	{
		$label = (string) $label;

		return new self($this->kind, $this->parts, $value === null ? ['label' => $label, 'action' => $action] : ['label' => $label, 'action' => $action, 'value' => $value]);
	}

	/**
	 * The message as the API sends it.
	 *
	 * @return array{kind: string, parts: list<Part>, fix: ?Fix}
	 */
	public function toArray(): array
	{
		return ['kind' => $this->kind->value, 'parts' => $this->parts, 'fix' => $this->fix];
	}

	/**
	 * A part as it was given.
	 *
	 * @return Part
	 */
	private static function part(mixed $part): string|array
	{
		return match (true) {
			is_array($part) && is_string($part['code'] ?? null)   => ['code' => $part['code']],
			is_array($part) && is_string($part['strong'] ?? null) => ['strong' => $part['strong']],
			default                                               => is_string($part) ? $part : ''
		};
	}
}
