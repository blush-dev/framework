<?php

/**
 * Edit refused.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Editing;

use RuntimeException;
use Throwable;
use Blush\Core\BlushException;

/**
 * An edit to an entry or a media file was refused (D-756): why
 * (`refusal`), what to say, the field it's about, if one, and anything
 * more a caller may show (`details`, such as a full relation's target
 * and another with room, D-608).
 */
final class EditRefused extends RuntimeException implements BlushException
{
	/**
	 * @param array<string, mixed> $details
	 */
	public function __construct(
		public readonly Refusal $refusal,
		string $message,
		public readonly ?string $field = null,
		public readonly array $details = [],
		?Throwable $previous = null
	) {
		parent::__construct($message, previous: $previous);
	}

	public static function invalid(string $message, ?string $field = null): self
	{
		return new self(Refusal::Invalid, $message, $field);
	}

	public static function forbidden(string $message, ?string $field = null): self
	{
		return new self(Refusal::Forbidden, $message, $field);
	}

	public static function notFound(string $message): self
	{
		return new self(Refusal::NotFound, $message);
	}

	public static function conflict(string $message, ?Throwable $previous = null): self
	{
		return new self(Refusal::Conflict, $message, previous: $previous);
	}

	/**
	 * @param array<string, mixed> $details
	 */
	public static function unprocessable(string $message, ?string $field = null, array $details = [], ?Throwable $previous = null): self
	{
		return new self(Refusal::Unprocessable, $message, $field, $details, $previous);
	}

	public static function tooLarge(string $message): self
	{
		return new self(Refusal::TooLarge, $message);
	}

	public static function failed(string $message, ?Throwable $previous = null): self
	{
		return new self(Refusal::Failed, $message, previous: $previous);
	}

	public static function revisionRequired(string $message): self
	{
		return new self(Refusal::RevisionRequired, $message);
	}
}
