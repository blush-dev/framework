<?php

/**
 * Admin redirects controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin\Redirects;

use DateTimeInterface;
use JsonException;
use Uri\Rfc3986\Uri;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Auth\Account;
use Blush\Auth\AccountProfiles;
use Blush\Auth\Accounts;
use Blush\Auth\Capability;
use Blush\Auth\Permissions;
use Blush\Container\Attributes\Tagged;
use Blush\Content\Entries;
use Blush\Content\Entry\Entry;
use Blush\Content\Routing\DataRedirects;
use Blush\Content\Routing\RedirectRow;
use Blush\Content\Routing\Redirects;
use Blush\Content\Status as EntryStatus;
use Blush\Content\Visibility;
use Blush\Core\AppConfig;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Routing\InvalidRoute;
use Blush\Routing\Redirect;
use Blush\Routing\RedirectSource;
use Blush\Storage\Record\RecordException;

/**
 * The Redirects screen's API (D-686), for accounts with
 * `site.redirects`. Rows are named by their `from` path.
 *
 * - `GET redirects`: a page of rows, sorted by `from`, for a tab (`tab`:
 *   `all`, `permanent`, `temporary`, or `problems`), a search (`search`,
 *   matching a row's paths and its entry's title and address, or, for an
 *   address, the rows that handle it), where they go (`goes`: `here` or
 *   `away`), `page`, and `per`; with each tab's count, the site's code's
 *   redirects (read-only, first), and, when the search is an address,
 *   where it goes (`trace`). Each row carries its problem, if any.
 * - `POST redirects/check`: what the add and edit form says about
 *   `{from, to, entry, status, was}`, field by field.
 * - `POST redirects`: adds a row, or changes the one from `was`; a 422
 *   with the form's messages when something stops it. Answers the row
 *   and, for a change, the row as it was, for Undo.
 * - `POST redirects/delete`: removes the rows `from` names, answering
 *   them as they were, for Undo.
 * - `POST redirects/status`: makes the rows `from` names `status`,
 *   answering the ones that changed as they were.
 * - `POST redirects/restore`: puts rows back as they were kept (`rows`),
 *   after removing the rows `remove` names: Undo.
 *
 * Problems and traces are worked out on each request (`RedirectReview`).
 */
final readonly class RedirectsController
{
	/**
	 * Page sizes the list offers, and its default.
	 */
	private const array PER = [10, 20, 50, 100];

	private const int PER_PAGE = 20;

	/**
	 * @param list<RedirectSource> $sources
	 */
	public function __construct(
		private Redirects $redirects,
		private SiteAddresses $site,
		private Entries $content,
		private Permissions $permissions,
		private Accounts $accounts,
		private AccountProfiles $names,
		private AppConfig $app,
		private ClockInterface $clock,
		#[Tagged(RedirectSource::TAG)] private array $sources = []
	) {}

	public function index(ServerRequestInterface $request): ResponseInterface
	{
		$account = $this->allowed($request);

		if ($account === null) {
			return self::error('You aren\'t allowed to manage redirects.', Status::Forbidden);
		}

		$review = $this->review();

		if ($review === null) {
			return self::error('The redirects can\'t be read. Check user/data/redirects.json.', Status::InternalServerError);
		}

		$query  = $request->getQueryParams();
		$tab    = in_array($query['tab'] ?? null, ['permanent', 'temporary', 'problems'], true) ? (string) $query['tab'] : 'all';
		$goes   = in_array($query['goes'] ?? null, ['here', 'away'], true) ? (string) $query['goes'] : '';
		$search = is_string($query['search'] ?? null) ? trim($query['search']) : '';
		$per    = in_array(self::number($query['per'] ?? null), self::PER, true) ? self::number($query['per'] ?? null) : self::PER_PAGE;
		$page   = max(1, self::number($query['page'] ?? null));

		$rows = $review->rows;

		usort($rows, static fn (RedirectRow $a, RedirectRow $b): int => strnatcasecmp($a->from, $b->from) ?: strcmp($a->from, $b->from));

		$problems = [];

		foreach ($rows as $row) {
			$problems[$row->from] = $review->problem($row);
		}

		$counts = [
			'all'       => count($rows),
			'permanent' => count(array_filter($rows, static fn (RedirectRow $row): bool => $row->isPermanent())),
			'temporary' => count(array_filter($rows, static fn (RedirectRow $row): bool => ! $row->isPermanent())),
			'problems'  => count(array_filter($problems))
		];

		$address = $search !== '' && self::looksLikeAddress($search, $this->host()) ? $review->read($search, 'test') : null;
		$words   = strtolower($search);

		$shown = array_values(array_filter($rows, function (RedirectRow $row) use ($tab, $goes, $words, $address, $problems, $review): bool {
			$away = $row->to !== null && RedirectReview::isUrl($row->to);

			if (($tab === 'permanent' && ! $row->isPermanent()) || ($tab === 'temporary' && $row->isPermanent()) || ($tab === 'problems' && $problems[$row->from] === null)) {
				return false;
			}

			if (($goes === 'here' && $away) || ($goes === 'away' && ! $away)) {
				return false;
			}

			if ($words === '') {
				return true;
			}

			$entry = $row->entry === null ? null : $review->entry($row->entry);
			$text  = strtolower(implode(' ', [$row->from, $row->to ?? '', $entry === null ? '' : $entry->title, $entry === null ? '' : $this->site->urlOf($entry) ?? '']));

			return str_contains($text, $words) || (isset($address['path']) && $review->handles($row, $address['path']));
		}));

		$total = count($shown);
		$pages = max(1, (int) ceil($total / $per));
		$page  = min($page, $pages);

		return self::json([
			'redirects' => array_map(fn (RedirectRow $row): array => $this->describe($row, $review, $problems[$row->from], $account), array_slice($shown, ($page - 1) * $per, $per)),
			'total'     => $total,
			'page'      => $page,
			'pages'     => $pages,
			'per'       => $per,
			'counts'    => $counts,
			'code'      => array_map(static fn (Redirect $redirect): array => $redirect->toArray(), $review->code),
			'trace'     => $address === null ? null : $this->trace($review, $address)
		]);
	}

	public function check(ServerRequestInterface $request): ResponseInterface
	{
		if ($this->allowed($request) === null) {
			return self::error('You aren\'t allowed to manage redirects.', Status::Forbidden);
		}

		$review = $this->review();

		if ($review === null) {
			return self::error('The redirects can\'t be read. Check user/data/redirects.json.', Status::InternalServerError);
		}

		$checked = $this->checked($review, self::input($request));

		return self::json([...$checked, 'row' => $checked['row']?->toArray()]);
	}

	public function save(ServerRequestInterface $request): ResponseInterface
	{
		$account = $this->allowed($request);

		if ($account === null) {
			return self::error('You aren\'t allowed to manage redirects.', Status::Forbidden);
		}

		$review = $this->review();

		if ($review === null) {
			return self::error('The redirects can\'t be read. Check user/data/redirects.json.', Status::InternalServerError);
		}

		$input   = self::input($request);
		$was     = is_string($input['was'] ?? null) && $input['was'] !== '' ? $input['was'] : null;
		$before  = $was === null ? null : $review->row($was);
		$checked = $this->checked($review, $input);
		$draft   = $checked['row'];

		if ($was !== null && $before === null) {
			return self::error('That redirect isn\'t there anymore. Reload the list.', Status::NotFound);
		}

		if ($draft === null) {
			return self::json(['error' => 'Something needs fixing first.', ...$checked, 'row' => null], Status::UnprocessableContent);
		}

		$row = new RedirectRow(
			$draft->from,
			$draft->to,
			$draft->entry,
			$draft->status,
			$before->added ?? $this->clock->now(),
			$before === null ? ($account->id === '' ? null : $account->id) : $before->by,
			$before?->via
		);

		try {
			$this->redirects->write(function () use ($row, $was): void {
				if ($was !== null && $was !== $row->from) {
					$this->redirects->delete($was);
				}

				$this->redirects->save($row);
			});
		} catch (RecordException | InvalidRoute $e) {
			return self::error(sprintf('The redirect couldn\'t be saved: %s', $e->getMessage()), Status::InternalServerError);
		}

		$after = $review->with($row, $was);

		return self::json(['redirect' => $this->describe($row, $after, $after->problem($row), $account), 'was' => $before?->toArray()]);
	}

	public function delete(ServerRequestInterface $request): ResponseInterface
	{
		if ($this->allowed($request) === null) {
			return self::error('You aren\'t allowed to manage redirects.', Status::Forbidden);
		}

		$froms = self::froms(self::input($request));

		if ($froms === null) {
			return self::error('Send "from", a list of the redirects\' old paths.', Status::BadRequest);
		}

		try {
			$deleted = $this->redirects->write(function () use ($froms): array {
				$gone = [];

				foreach ($froms as $from) {
					$row = $this->redirects->find($from);

					if ($row !== null) {
						$this->redirects->delete($from);
						$gone[] = $row->toArray();
					}
				}

				return $gone;
			});
		} catch (RecordException | InvalidRoute $e) {
			return self::error(sprintf('The redirects couldn\'t be deleted: %s', $e->getMessage()), Status::InternalServerError);
		}

		return self::json(['deleted' => $deleted]);
	}

	public function status(ServerRequestInterface $request): ResponseInterface
	{
		if ($this->allowed($request) === null) {
			return self::error('You aren\'t allowed to manage redirects.', Status::Forbidden);
		}

		$input  = self::input($request);
		$froms  = self::froms($input);
		$status = Status::tryFrom(is_int($input['status'] ?? null) ? $input['status'] : 0);

		if ($froms === null || $status === null || ! in_array($status, [Status::MovedPermanently, Status::Found, Status::SeeOther, Status::TemporaryRedirect, Status::PermanentRedirect], true)) {
			return self::error('Send "from", a list of the redirects\' old paths, and a redirect "status".', Status::BadRequest);
		}

		try {
			$changed = $this->redirects->write(function () use ($froms, $status): array {
				$was = [];

				foreach ($froms as $from) {
					$row = $this->redirects->find($from);

					if ($row !== null && $row->status !== $status) {
						$this->redirects->save($row->withStatus($status));
						$was[] = $row->toArray();
					}
				}

				return $was;
			});
		} catch (RecordException | InvalidRoute $e) {
			return self::error(sprintf('The redirects couldn\'t be changed: %s', $e->getMessage()), Status::InternalServerError);
		}

		return self::json(['changed' => $changed]);
	}

	public function restore(ServerRequestInterface $request): ResponseInterface
	{
		if ($this->allowed($request) === null) {
			return self::error('You aren\'t allowed to manage redirects.', Status::Forbidden);
		}

		$input  = self::input($request);
		$list   = $input['rows'] ?? null;
		$remove = self::froms(['from' => $input['remove'] ?? []]);

		if (! is_array($list) || ! array_is_list($list) || $remove === null) {
			return self::error('Send "rows", the redirects as they were kept, and "remove", a list of old paths.', Status::BadRequest);
		}

		try {
			$rows = array_map(static fn (mixed $row): RedirectRow => RedirectRow::fromArray(is_array($row) ? $row : []), $list);
		} catch (InvalidRoute $e) {
			return self::error($e->getMessage(), Status::UnprocessableContent);
		}

		$rows = array_values(array_filter($rows, fn (RedirectRow $row): bool => $this->redirects->isPath($row->from)));

		try {
			$this->redirects->write(function () use ($rows, $remove): void {
				foreach ($remove as $from) {
					$this->redirects->delete($from);
				}

				foreach ($rows as $row) {
					$this->redirects->save($row);
				}
			});
		} catch (RecordException | InvalidRoute $e) {
			return self::error(sprintf('The redirects couldn\'t be put back: %s', $e->getMessage()), Status::InternalServerError);
		}

		return self::json(['restored' => count($rows)]);
	}

	/**
	 * Builds the review of every redirect, or `null` when the table
	 * can't be read.
	 */
	private function review(): ?RedirectReview
	{
		try {
			$rows = $this->redirects->all();
		} catch (RecordException | InvalidRoute) {
			return null;
		}

		$code = [];

		foreach ($this->sources as $source) {
			if ($source instanceof DataRedirects) {
				continue;
			}

			foreach ($source->redirects() as $redirect) {
				$code[] = $redirect;
			}
		}

		return new RedirectReview($rows, $code, $this->site, $this->content, $this->host());
	}

	/**
	 * The form's messages for a request's draft, and the row it would
	 * save.
	 *
	 * @param  array<mixed> $input
	 * @return array{from: list<array<string, mixed>>, to: list<array<string, mixed>>, status: list<array<string, mixed>>, row: ?RedirectRow}
	 */
	private function checked(RedirectReview $review, array $input): array
	{
		$result = $review->check(
			is_string($input['from'] ?? null) ? $input['from'] : '',
			is_string($input['to'] ?? null) ? $input['to'] : '',
			is_string($input['entry'] ?? null) ? $input['entry'] : null,
			is_int($input['status'] ?? null) ? $input['status'] : 301,
			is_string($input['was'] ?? null) && $input['was'] !== '' ? $input['was'] : null
		);

		return ['from' => self::messages($result['from']), 'to' => self::messages($result['to']), 'status' => self::messages($result['status']), 'row' => $result['row']];
	}

	/**
	 * A row as the list shows it.
	 *
	 * @param  ?array{kind: string, label: string, message: Message, final?: array{entry: string, title: string, url: string}|array{to: string}} $problem
	 * @return array<string, mixed>
	 */
	private function describe(RedirectRow $row, RedirectReview $review, ?array $problem, Account $viewer): array
	{
		$entry = null;

		if ($row->entry !== null) {
			$found = $review->entry($row->entry);
			$entry = $found === null
				? ['id' => $row->entry, 'title' => '', 'url' => null, 'type' => null, 'state' => 'deleted']
				: ['id' => $row->entry, 'title' => $found->title, 'url' => $this->site->urlOf($found), 'type' => $found->type->name, 'state' => self::state($found)];
		}

		$by = $row->by === null ? null : $this->accounts->findById($row->by);

		return [
			'from'    => $row->from,
			'to'      => $row->to,
			'entry'   => $entry,
			'status'  => $row->status->value,
			'added'   => $row->added?->format(DateTimeInterface::ATOM),
			'by'      => $row->by === null ? null : ['name' => $by === null ? null : $this->names->displayName($by), 'username' => $by?->username, 'you' => $row->by === $viewer->id],
			'via'     => $row->via?->value,
			'problem' => $problem === null ? null : ['kind' => $problem['kind'], 'label' => $problem['label'], 'message' => $problem['message']->toArray(), 'final' => $problem['final'] ?? null],
			'stored'  => $row->toArray()
		];
	}

	/**
	 * Where an address goes, as the list's first line shows it.
	 *
	 * @param  array{empty?: true, error?: Message, other?: string, url?: string, path?: string, stripped?: bool, slash?: string} $address
	 * @return array<string, mixed>
	 */
	private function trace(RedirectReview $review, array $address): array
	{
		if (! isset($address['path'])) {
			return ['path' => null, 'other' => $address['other'] ?? null, 'hops' => []];
		}

		$hops = array_map(function (array $hop): array {
			return match ($hop['t']) {
				'page'     => ['t' => 'page', 'path' => $hop['path'], 'title' => $hop['answer'] instanceof Entry ? RedirectReview::titleOf($hop['answer']) : null],
				'redirect' => ['t' => 'redirect', 'path' => $hop['path'], 'code' => $hop['code'], 'from' => $hop['from'], 'status' => $hop['status'], 'target' => $this->targetJson($hop['target'])],
				'away'     => ['t' => 'away', 'url' => $hop['url'], 'host' => (string) Uri::parse($hop['url'])?->getHost()],
				'gone'     => ['t' => 'gone', 'title' => $hop['entry'] === null ? null : RedirectReview::titleOf($hop['entry'])],
				'missing'  => ['t' => 'missing', 'path' => $hop['path']],
				default    => ['t' => 'loop', 'path' => $hop['path']]
			};
		}, $review->trace($address['path']));

		$first = $address['path'];

		return [
			'path'      => $first,
			'other'     => null,
			'hops'      => $hops,
			// The site's code, or a page, answering where a row would.
			'overruled' => $review->sameAs($first) !== null && ($hops[0]['t'] === 'page' || ($hops[0]['t'] === 'redirect' && $hops[0]['code'])) ? $hops[0]['t'] : null
		];
	}

	/**
	 * A trace step's target, as the API sends it.
	 *
	 * @param  array{kind: string, entry?: ?Entry, id?: string, path?: string, url?: string} $target
	 * @return array<string, mixed>
	 */
	private function targetJson(array $target): array
	{
		$entry = $target['entry'] ?? null;

		return [
			'kind'  => $target['kind'],
			'title' => $entry === null ? null : RedirectReview::titleOf($entry),
			'path'  => $target['path'] ?? null,
			'url'   => $target['url'] ?? null
		];
	}

	/**
	 * The site's host, from its address.
	 */
	private function host(): string
	{
		return (string) Uri::parse($this->app->url)?->getHost();
	}

	/**
	 * The signed-in account, when it may manage redirects.
	 */
	private function allowed(ServerRequestInterface $request): ?Account
	{
		$account = $request->getAttribute(Account::class);

		return $account instanceof Account && $this->permissions->can($account, Capability::SiteRedirects) ? $account : null;
	}

	/**
	 * Whether a search is an address to follow: a path, a whole address,
	 * or the site's host and a path.
	 */
	private static function looksLikeAddress(string $search, string $host): bool
	{
		return str_starts_with($search, '/')
			|| preg_match('#^https?://#i', $search) === 1
			|| stripos($search, RedirectReview::bareHost($host) . '/') === 0
			|| stripos($search, $host . '/') === 0;
	}

	/**
	 * An entry's state, for a row leading to it.
	 */
	private static function state(Entry $entry): string
	{
		return match (true) {
			$entry->status === EntryStatus::Trash        => 'trash',
			$entry->status === EntryStatus::Draft        => 'draft',
			$entry->status === EntryStatus::Scheduled    => 'scheduled',
			$entry->visibility === Visibility::Hidden    => 'hidden',
			default                                      => 'live'
		};
	}

	/**
	 * The list of old paths a request names, or `null`.
	 *
	 * @param  array<mixed> $input
	 * @return ?list<string>
	 */
	private static function froms(array $input): ?array
	{
		$from = $input['from'] ?? null;

		if (! is_array($from) || ! array_is_list($from)) {
			return null;
		}

		$paths = [];

		foreach ($from as $item) {
			if (! is_string($item)) {
				return null;
			}

			$paths[] = $item;
		}

		return $paths;
	}

	/**
	 * Messages as the API sends them.
	 *
	 * @param  list<Message> $messages
	 * @return list<array<string, mixed>>
	 */
	private static function messages(array $messages): array
	{
		return array_map(static fn (Message $message): array => $message->toArray(), $messages);
	}

	/**
	 * A query value as a whole number, or 0.
	 */
	private static function number(mixed $value): int
	{
		return is_string($value) && ctype_digit($value) ? (int) $value : 0;
	}

	/**
	 * The request's JSON object, or `[]`.
	 *
	 * @return array<mixed>
	 */
	private static function input(ServerRequestInterface $request): array
	{
		try {
			$input = json_decode((string) $request->getBody(), true, 64, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			return [];
		}

		return is_array($input) ? $input : [];
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private static function json(array $data, Status $status = Status::Ok): ResponseInterface
	{
		return Response::json($data, $status, ['Cache-Control' => 'no-store']);
	}

	private static function error(string $message, Status $status): ResponseInterface
	{
		return self::json(['error' => $message], $status);
	}
}
