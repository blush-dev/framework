<?php

/**
 * Admin entries controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use DateTimeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Auth\Account;
use Blush\Auth\AuthConfig;
use Blush\Auth\Capability;
use Blush\Auth\Permissions;
use Blush\Content\ContentRepository;
use Blush\Content\Entry\Entry;
use Blush\Content\Query\Order;
use Blush\Content\Status;
use Blush\Http\Response;
use Blush\Http\Status as HttpStatus;

/**
 * Answers `GET {path}/api/entries?status={draft|scheduled}` (D-225):
 * the entries with that status that the account may edit, so an author
 * sees their own drafts and an editor everyone's. Drafts come most
 * recently changed first; scheduled entries soonest first.
 */
final readonly class EntriesController
{
	/**
	 * The statuses it lists.
	 */
	public const array STATUSES = [Status::Draft, Status::Scheduled];

	public function __construct(
		private ContentRepository $content,
		private Permissions $permissions,
		private AuthConfig $config
	) {}

	public function __invoke(ServerRequestInterface $request): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);
		$value   = $request->getQueryParams()['status'] ?? '';
		$status  = is_string($value) ? Status::tryFrom($value) : null;

		if ($status === null || ! in_array($status, self::STATUSES, true)) {
			return self::json(['error' => 'Ask for "?status=draft" or "?status=scheduled".'], HttpStatus::BadRequest);
		}

		if (! $account instanceof Account) {
			return self::json(['error' => 'Sign in first.'], HttpStatus::Unauthorized);
		}

		$entries = $this->content->query()->any()->status($status)->orderBy(
			$status === Status::Draft ? 'updated' : 'published',
			$status === Status::Draft ? Order::Desc : Order::Asc
		)->get();

		$list = [];

		foreach ($entries as $entry) {
			if ($this->permissions->can($account, Capability::ContentEdit, $entry)) {
				$list[] = $this->describe($account, $entry);
			}
		}

		return self::json(['status' => $status->value, 'entries' => $list]);
	}

	/**
	 * Returns what the admin shows of an entry.
	 *
	 * @return array<string, mixed>
	 */
	private function describe(Account $account, Entry $entry): array
	{
		return [
			'id'        => $entry->id,
			'title'     => $entry->title,
			'type'      => $entry->type->name,
			'status'    => $entry->status->value,
			'published' => $entry->published?->format(DateTimeInterface::ATOM),
			'updated'   => $entry->updated->format(DateTimeInterface::ATOM),
			'path'      => $entry->source?->path,
			'authors'   => $entry->terms($this->config->authorTaxonomy),
			'own'       => $this->permissions->owns($account, $entry)
		];
	}

	/**
	 * Builds an uncached JSON response.
	 *
	 * @param array<string, mixed> $data
	 */
	private static function json(array $data, HttpStatus $status = HttpStatus::Ok): ResponseInterface
	{
		return Response::json($data, $status, ['Cache-Control' => 'no-store']);
	}
}
