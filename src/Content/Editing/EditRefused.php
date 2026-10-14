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
use Blush\Translation\Text;

/**
 * An edit to an entry or a media file was refused (D-756): why
 * (`refusal`), what to say, the field it's about, if one, and anything
 * more a caller may show (`details`, such as a full relation's target
 * and another with room, D-608). Its message may be a `Text` (D-836),
 * written with `t()` where it's refused, and is cast in the language
 * current when it's thrown.
 */
final class EditRefused extends RuntimeException implements BlushException
{
	/**
	 * @param array<string, mixed> $details
	 */
	public function __construct(
		public readonly Refusal $refusal,
		string|Text $message,
		public readonly ?string $field = null,
		public readonly array $details = [],
		?Throwable $previous = null
	) {
		parent::__construct((string) $message, previous: $previous);
	}

	public static function invalid(string|Text $message, ?string $field = null): self
	{
		return new self(Refusal::Invalid, $message, $field);
	}

	public static function forbidden(string|Text $message, ?string $field = null): self
	{
		return new self(Refusal::Forbidden, $message, $field);
	}

	public static function notFound(string|Text $message): self
	{
		return new self(Refusal::NotFound, $message);
	}

	public static function conflict(string|Text $message, ?Throwable $previous = null): self
	{
		return new self(Refusal::Conflict, $message, previous: $previous);
	}

	/**
	 * @param array<string, mixed> $details
	 */
	public static function unprocessable(string|Text $message, ?string $field = null, array $details = [], ?Throwable $previous = null): self
	{
		return new self(Refusal::Unprocessable, $message, $field, $details, $previous);
	}

	public static function tooLarge(string|Text $message): self
	{
		return new self(Refusal::TooLarge, $message);
	}

	public static function failed(string|Text $message, ?Throwable $previous = null): self
	{
		return new self(Refusal::Failed, $message, previous: $previous);
	}

	public static function revisionRequired(string|Text $message): self
	{
		return new self(Refusal::RevisionRequired, $message);
	}
}
