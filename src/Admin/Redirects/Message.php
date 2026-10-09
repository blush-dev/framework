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

/**
 * A line the Redirects screen shows (D-686): under a form field, in the
 * form's notice, or in a row's problem. Its text is parts, plain, in
 * code type (a path), or strong (a title), so the admin can draw them
 * without reading markup from what people typed. A message may carry
 * the one fix it offers: a label and an action the admin knows.
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
	 * What stops a save.
	 *
	 * @param Part ...$parts
	 */
	public static function bad(string|array ...$parts): self
	{
		return new self(MessageKind::Bad, array_values($parts));
	}

	/**
	 * What works, but maybe not as meant.
	 *
	 * @param Part ...$parts
	 */
	public static function warn(string|array ...$parts): self
	{
		return new self(MessageKind::Warn, array_values($parts));
	}

	/**
	 * What will happen.
	 *
	 * @param Part ...$parts
	 */
	public static function say(string|array ...$parts): self
	{
		return new self(MessageKind::Say, array_values($parts));
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
	public function withFix(string $label, string $action, mixed $value = null): self
	{
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
}
