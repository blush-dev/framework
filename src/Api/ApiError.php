<?php

/**
 * API error.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Api;

use RuntimeException;
use Blush\Content\Editing\EditRefused;
use Blush\Content\Editing\Refusal;
use Blush\Core\BlushException;
use Blush\Http\Status;

/**
 * A request the API can't answer, sent as `{ "error": { "code",
 * "message" } }` with its status (D-737), and the `field` it's about
 * when a write names one (D-762). The code is the contract; the message
 * may change.
 */
final class ApiError extends RuntimeException implements BlushException
{
	public function __construct(
		public readonly string $error,
		string $message,
		public readonly Status $status,
		public readonly ?string $field = null,
		public readonly ?int $retryAfter = null
	) {
		parent::__construct($message);
	}

	/**
	 * Nothing is at the address, or no record has the id.
	 */
	public static function notFound(string $message): self
	{
		return new self('not_found', $message, Status::NotFound);
	}

	/**
	 * A query parameter is unknown or has a value it can't take.
	 */
	public static function invalidQuery(string $message): self
	{
		return new self('invalid_query', $message, Status::BadRequest);
	}

	/**
	 * A request without a token where one is needed (`token_required`),
	 * or with one that isn't (`invalid_token`): wrong, revoked, expired,
	 * or its account's suspended (D-745, D-750).
	 */
	public static function unauthorized(string $error, string $message): self
	{
		return new self($error, $message, Status::Unauthorized);
	}

	/**
	 * A token sent over plain HTTP, but on the site's own computer
	 * (D-747).
	 */
	public static function httpsRequired(): self
	{
		return new self('https_required', 'Tokens are only accepted over HTTPS.', Status::Forbidden);
	}

	/**
	 * Too many tokens that weren't one from an address (D-750).
	 */
	public static function tooManyAttempts(): self
	{
		return new self('too_many_attempts', 'Too many requests with a token that isn\'t one. Try again later.', Status::TooManyRequests);
	}

	/**
	 * A write's body that isn't a JSON object, or has a key the write
	 * doesn't take (D-762).
	 */
	public static function invalidBody(string $message, ?string $field = null): self
	{
		return new self('invalid_body', $message, Status::BadRequest, $field);
	}

	/**
	 * A write a token can't make, or one without a token (D-762).
	 */
	public static function forbidden(string $message, ?string $field = null): self
	{
		return new self('forbidden', $message, Status::Forbidden, $field);
	}

	/**
	 * A write needing the entry's revision that came without one in
	 * `If-Match` (D-762).
	 */
	public static function revisionRequired(): self
	{
		return new self('revision_required', 'Send the entry\'s "revision" in the If-Match header, so no one else\'s change is lost.', Status::PreconditionRequired);
	}

	/**
	 * More writes from a token in an hour than the site allows (D-762).
	 */
	public static function tooManyWrites(int $max, int $wait): self
	{
		$minutes = (int) ceil($wait / 60);

		return new self('too_many_writes', sprintf('This token has made %d changes this hour, the most the site allows. Try again in %d minute%s.', $max, $minutes, $minutes === 1 ? '' : 's'), Status::TooManyRequests, retryAfter: $wait);
	}

	/**
	 * A write the one write path refused (D-756, D-762), by why: a
	 * revision that's no longer the entry's is `412`, never saved over.
	 */
	public static function refused(EditRefused $refused, ?string $field = null): self
	{
		$field ??= $refused->field;

		return match ($refused->refusal) {
			Refusal::Invalid          => new self('invalid_body', $refused->getMessage(), Status::BadRequest, $field),
			Refusal::Forbidden        => new self('forbidden', $refused->getMessage(), Status::Forbidden, $field),
			Refusal::NotFound         => new self('not_found', $refused->getMessage(), Status::NotFound, $field),
			Refusal::Conflict         => new self('revision_mismatch', 'The entry changed since that revision. Read it again, then make the change on what\'s there now.', Status::PreconditionFailed),
			Refusal::Unprocessable    => new self('unprocessable', $refused->getMessage(), Status::UnprocessableContent, $field),
			Refusal::RevisionRequired => self::revisionRequired(),
			Refusal::TooLarge         => new self('too_large', $refused->getMessage(), Status::ContentTooLarge, $field),
			Refusal::Failed           => new self('server_error', $refused->getMessage(), Status::InternalServerError)
		};
	}

	/**
	 * Returns the error as it's sent.
	 *
	 * @return array{error: array{code: string, message: string, field?: string}}
	 */
	public function toArray(): array
	{
		return ['error' => ['code' => $this->error, 'message' => $this->getMessage(), ...($this->field === null ? [] : ['field' => $this->field])]];
	}
}
