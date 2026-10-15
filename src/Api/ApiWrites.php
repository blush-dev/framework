<?php

/**
 * API writes.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Api;

use Closure;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Activity\Via;
use Blush\Auth\Account;
use Blush\Auth\AuthException;
use Blush\Auth\ContentAction;
use Blush\Auth\Token;
use Blush\Cache\CacheException;
use Blush\Content\Editing\EditRefused;
use Blush\Content\Entries;
use Blush\Content\Entry\Entry;
use Blush\Http\RateLimiter;
use Blush\Http\Status as HttpStatus;
use Blush\Media\MediaException;
use Blush\Support\Uuid;
use Blush\Tool\ToolContext;
use Blush\Tool\ToolContexts;
use Blush\Workflow\Status;

/**
 * What the API's writes share (D-762): a token, always; a write counted
 * against its hourly limit (`ApiConfig::$maxWrites`); the context it runs
 * in, its account's narrowed to the token's capabilities, on the one
 * write path (`EntryEditor`, `MediaEditor`) and logged as the API's; its
 * JSON body; the entry's revision from `If-Match`; and a refusal answered
 * as an `ApiError` by why.
 */
final readonly class ApiWrites
{
	/**
	 * The window writes are counted in, in seconds.
	 */
	private const int WINDOW = 3600;

	public function __construct(
		private ApiConfig $config,
		private ApiResponses $responses,
		private ApiEntries $entries,
		private Entries $content,
		private ToolContexts $contexts,
		private RateLimiter $limiter
	) {}

	/**
	 * Answers a write with what `$write` returns, for the request's token,
	 * once it's counted, or with the error it throws.
	 *
	 * @param Closure(ToolContext): ResponseInterface $write
	 */
	public function run(ServerRequestInterface $request, Closure $write): ResponseInterface
	{
		try {
			return $write($this->context($request));
		} catch (ApiError $error) {
			return $this->responses->error($error);
		} catch (EditRefused $refused) {
			return $this->responses->error(ApiError::refused($refused));
		} catch (MediaException $error) {
			return $this->responses->error(new ApiError('server_error', 'The media library can\'t be read: ' . $error->getMessage(), HttpStatus::InternalServerError));
		}
	}

	/**
	 * Returns the context a request's write runs in, once it's counted.
	 *
	 * @throws ApiError Without a token, or past its limit.
	 */
	private function context(ServerRequestInterface $request): ToolContext
	{
		$token   = $request->getAttribute(Token::class);
		$account = $request->getAttribute(Account::class);

		if (! $token instanceof Token || ! $account instanceof Account) {
			throw ApiError::unauthorized('token_required', 'Changes need an API token, sent in the Authorization header as "Bearer {token}".');
		}

		try {
			$wait = $this->limiter->hit("api|writes|{$token->id}", $this->config->maxWrites, self::WINDOW);
		} catch (CacheException) {
			$wait = null;
		}

		if ($wait !== null) {
			throw ApiError::tooManyWrites($this->config->maxWrites, $wait);
		}

		try {
			return $this->contexts->forToken($account, $token, via: Via::Api);
		} catch (AuthException) {
			throw ApiError::forbidden('The token\'s capabilities can\'t be read.');
		}
	}

	/**
	 * Returns a write's JSON body: an object, by key.
	 *
	 * @return array<string, mixed>
	 * @throws ApiError When it isn't one.
	 */
	public function json(ServerRequestInterface $request): array
	{
		try {
			$data = json_decode((string) $request->getBody(), true, 32, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			throw ApiError::invalidBody('Send the change as a JSON object, with Content-Type: application/json.');
		}

		if (! is_array($data) || ($data !== [] && array_is_list($data))) {
			throw ApiError::invalidBody('Send the change as a JSON object.');
		}

		$values = [];

		foreach ($data as $key => $value) {
			$values[(string) $key] = $value;
		}

		return $values;
	}

	/**
	 * Returns the revision a write sends in `If-Match`, as an entry's
	 * `revision` is, quoted or not.
	 *
	 * @throws ApiError When there's none.
	 */
	public function revision(ServerRequestInterface $request): string
	{
		$value = trim($request->getHeaderLine('If-Match'));

		if (str_starts_with($value, 'W/')) {
			$value = substr($value, 2);
		}

		$value = trim($value, " \t\"");

		return $value === '' || $value === '*' ? throw ApiError::revisionRequired() : $value;
	}

	/**
	 * Returns an entry a write may change, by id: of a public type, and
	 * the token's to edit, or, in the trash, to delete. Any other is
	 * not found, so a write learns nothing a read wouldn't.
	 *
	 * @throws ApiError When there's none.
	 */
	public function entry(ToolContext $context, string $id): Entry
	{
		$entry = Uuid::isValid($id) ? $this->content->find($id) : null;

		$sees = $entry !== null
			&& isset($this->entries->types()[$entry->type->name])
			&& $context->can($entry->status->is(Status::TRASH) ? ContentAction::Delete : ContentAction::Edit, $entry);

		return $sees ? $entry : throw ApiError::notFound(sprintf('No entry has the id "%s" that this token may change.', $id));
	}

	/**
	 * Returns a key's value as text, or `null` when it's left out.
	 *
	 * @param  array<string, mixed> $input
	 * @throws ApiError When it isn't text.
	 */
	public static function text(array $input, string $key): ?string
	{
		if (! array_key_exists($key, $input)) {
			return null;
		}

		return is_string($input[$key]) ? $input[$key] : throw ApiError::invalidBody(sprintf('"%s" is text.', $key), $key);
	}

	/**
	 * Throws when the input has a key a write doesn't take.
	 *
	 * @param  array<string, mixed> $input
	 * @param  list<string>         $keys
	 * @throws ApiError
	 */
	public static function only(array $input, array $keys): void
	{
		foreach (array_keys($input) as $key) {
			if (! in_array($key, $keys, true)) {
				throw ApiError::invalidBody(sprintf('"%s" can\'t be written here; a write takes: %s.', $key, implode(', ', $keys)), $key);
			}
		}
	}
}
