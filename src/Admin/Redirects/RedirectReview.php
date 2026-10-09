<?php

/**
 * Redirect review.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin\Redirects;

use Uri\Rfc3986\Uri;
use Blush\Content\Entries;
use Blush\Content\Entry\Entry;
use Blush\Content\Routing\RedirectRow;
use Blush\Content\Status as EntryStatus;
use Blush\Http\Status;
use Blush\Routing\InvalidRoute;
use Blush\Routing\Redirect;
use Blush\Routing\RoutePattern;

/**
 * Follows addresses through the site's redirects the way the router
 * does, for the Redirects screen (D-686): the site's code first (every
 * redirect source but the `redirects` table), then the table's rows; a
 * path's exact redirect before any pattern, patterns in order, and the
 * first redirect for a pattern winning. Redirects are tried only when
 * nothing answers (`SiteAddresses`).
 *
 * From that it finds what's wrong with a row (`problem()`), where an
 * address ends up (`trace()`), and what the add and edit form says
 * (`check()`). Every answer is worked out on the request that asks.
 *
 * @phpstan-type Target array{kind: 'entry'|'gone'|'path'|'url', entry?: ?Entry, id?: string, path?: string, url?: string}
 * @phpstan-type Hop array{t: 'page', path: string, answer: Entry|string}|array{t: 'redirect', path: string, code: bool, from: string, status: int, target: Target}|array{t: 'away', url: string}|array{t: 'gone', entry: ?Entry, id: string}|array{t: 'missing', path: string}|array{t: 'loop', path: string}
 * @phpstan-type Destination array{entry: string, title: string, url: string}|array{to: string}
 * @phpstan-type Problem array{kind: string, label: string, message: Message, final?: Destination}
 */
final class RedirectReview
{
	/**
	 * How many redirects a trace follows before calling it a loop.
	 */
	private const int HOPS = 8;

	/**
	 * Words for each kind of problem, for the chip on its row.
	 *
	 * @var array<string, string>
	 */
	public const array LABELS = [
		'code'    => 'Overruled',
		'live'    => 'Does nothing',
		'gone'    => 'Leads nowhere',
		'chain'   => 'Chained',
		'missing' => 'Missing page'
	];

	/**
	 * Exact redirects, by path: whether it's the code's, the redirect,
	 * and its row.
	 *
	 * @var array<string, array{code: bool, redirect: Redirect, row: ?RedirectRow, pattern: RoutePattern}>
	 */
	private array $exact = [];

	/**
	 * Pattern redirects, in order, with their anchored regexes.
	 *
	 * @var list<array{code: bool, redirect: Redirect, row: ?RedirectRow, pattern: RoutePattern, regex: string}>
	 */
	private array $patterns = [];

	/**
	 * The code's redirects' keys (an exact path, or a pattern's regex).
	 *
	 * @var array<string, Redirect>
	 */
	private array $codeKeys = [];

	/**
	 * Entries looked up by id.
	 *
	 * @var array<string, ?Entry>
	 */
	private array $found = [];

	/**
	 * @param list<RedirectRow> $rows The `redirects` table's rows.
	 * @param list<Redirect>    $code The other sources' redirects, in order.
	 */
	public function __construct(
		public readonly array $rows,
		public readonly array $code,
		private readonly SiteAddresses $site,
		private readonly Entries $content,
		private readonly string $host
	) {
		foreach ($code as $redirect) {
			$this->add($redirect, null);
		}

		foreach ($rows as $row) {
			$this->add(new Redirect($row->from, $row->to ?? '/', $row->status), $row);
		}
	}

	/**
	 * Returns a review of the rows with one added, or changed from the
	 * row it was (`$was`, its `from`).
	 */
	public function with(RedirectRow $row, ?string $was): self
	{
		$rows = array_values(array_filter($this->rows, static fn (RedirectRow $item): bool => $item->from !== $was && $item->from !== $row->from));

		return new self([$row, ...$rows], $this->code, $this->site, $this->content, $this->host);
	}

	/**
	 * The row from a path, as the table keys them.
	 */
	public function row(string $from): ?RedirectRow
	{
		return array_find($this->rows, static fn (RedirectRow $row): bool => $row->from === $from);
	}

	/**
	 * The row whose key is the same as a path's or pattern's, as the
	 * route table compares them (a trailing slash aside).
	 */
	public function sameAs(string $from, ?string $except = null): ?RedirectRow
	{
		$key = self::key($from);

		return $key === null ? null : array_find($this->rows, static fn (RedirectRow $row): bool => $row->from !== $except && self::key($row->from) === $key);
	}

	/**
	 * The code's redirect with the same key as a path or pattern.
	 */
	public function codeFor(string $from): ?Redirect
	{
		$key = self::key($from);

		return $key === null ? null : $this->codeKeys[$key] ?? null;
	}

	/**
	 * Whether a row handles a path: its exact path, or its pattern
	 * matches.
	 */
	public function handles(RedirectRow $row, string $path): bool
	{
		try {
			$pattern = RoutePattern::parse(SiteAddresses::lookup($row->from));
		} catch (InvalidRoute) {
			return false;
		}

		$path = SiteAddresses::lookup($path);

		return $pattern->isStatic() ? $pattern->path === $path : preg_match('~^' . $pattern->regex() . '$~', $path) === 1;
	}

	/**
	 * Where a row leads, with placeholders filled from `$params`: a live
	 * entry, one that isn't (`gone`), a path, or another site.
	 *
	 * @param  array<string, string> $params
	 * @return Target
	 */
	public function target(RedirectRow $row, array $params = []): array
	{
		if ($row->entry !== null) {
			$entry = $this->entry($row->entry);
			$url   = $entry === null ? null : $this->site->urlOf($entry);

			return $url === null ? ['kind' => 'gone', 'entry' => $entry, 'id' => $row->entry] : ['kind' => 'entry', 'entry' => $entry, 'id' => $row->entry, 'path' => $url];
		}

		return self::targetOf($row->to ?? '/', $params);
	}

	/**
	 * An entry by id, looked up once.
	 */
	public function entry(string $id): ?Entry
	{
		if (! array_key_exists($id, $this->found)) {
			$this->found[$id] = $this->content->find($id);
		}

		return $this->found[$id];
	}

	/**
	 * Follows an address as a visitor would: what answers it, else the
	 * redirect for it, again from where that leads, until a page,
	 * another site, nothing, or a loop.
	 *
	 * @return non-empty-list<Hop>
	 */
	public function trace(string $path, ?RedirectRow $skip = null): array
	{
		$hops = [];
		$seen = [];

		for ($i = 0; $i < self::HOPS; $i++) {
			$path   = SiteAddresses::lookup($path);
			$answer = $this->site->answer($path);

			if ($answer !== null) {
				$hops[] = ['t' => 'page', 'path' => $path, 'answer' => $answer];

				return $hops;
			}

			if (isset($seen[$path])) {
				$hops[] = ['t' => 'loop', 'path' => $path];

				return $hops;
			}

			$seen[$path] = true;
			$match       = $this->match($path, $skip);

			if ($match === null) {
				$hops[] = ['t' => 'missing', 'path' => $path];

				return $hops;
			}

			[$item, $params] = $match;

			$target = $item['row'] === null ? self::targetOf($item['redirect']->to, $params) : $this->target($item['row'], $params);
			$hops[] = ['t' => 'redirect', 'path' => $path, 'code' => $item['code'], 'from' => $item['redirect']->from, 'status' => $item['redirect']->status->value, 'target' => $target];

			if ($target['kind'] === 'url') {
				$hops[] = ['t' => 'away', 'url' => $target['url'] ?? ''];

				return $hops;
			}

			if ($target['kind'] === 'gone') {
				$hops[] = ['t' => 'gone', 'entry' => $target['entry'] ?? null, 'id' => $target['id'] ?? ''];

				return $hops;
			}

			$path = $target['path'] ?? '/';
		}

		$hops[] = ['t' => 'loop', 'path' => $path];

		return $hops;
	}

	/**
	 * What's wrong with a row, if anything: overruled by the code, a
	 * page answering its old address, an entry that isn't live, another
	 * redirect after it, or nothing where it leads. A row with a problem
	 * still works as far as it can.
	 *
	 * @return ?Problem
	 */
	public function problem(RedirectRow $row): ?array
	{
		$static = ! str_contains($row->from, '{');

		if ($this->codeFor($row->from) !== null) {
			return self::problemOf('code', Message::warn('Overruled by a redirect in the site\'s code.')->withFix('Show It', 'code'));
		}

		if ($static) {
			$answer = $this->site->answer($row->from);

			if ($answer !== null) {
				return self::problemOf('live', new Message(MessageKind::Warn, ['Does nothing: ', ...self::answerWords($answer), ' answers this address.'])->withFix('Delete Redirect', 'delete'));
			}
		}

		$target = $this->target($row);

		if ($target['kind'] === 'gone') {
			$entry = $target['entry'] ?? null;

			return self::problemOf('gone', new Message(MessageKind::Warn, ['Leads nowhere: ', ...self::goneWords($entry), '.'])->withFix('Choose Another Page', 'repick'));
		}

		if ($target['kind'] !== 'path' || str_contains($target['path'] ?? '', '{')) {
			return null;
		}

		$path = $target['path'] ?? '/';

		if ($this->site->answer($path) !== null || $this->codeFor($path) !== null) {
			return null;
		}

		if ($this->match(SiteAddresses::lookup($path), $row) === null) {
			return self::problemOf('missing', Message::warn('Leads to a missing page: nothing answers ', Message::code($path), '.')->withFix('Change Where It Goes', 'edit'));
		}

		$hops  = $this->trace($path, $row);
		$final = $hops[array_key_last($hops)];

		if ($final['t'] === 'page' || $final['t'] === 'away') {
			$there = $final['t'] === 'away' ? $final['url'] : $final['path'];
			$fix   = self::destination($final['answer'] ?? null, $there);

			return [...self::problemOf('chain', Message::warn('Leads to another redirect: ', Message::code($path), ' goes on to ', Message::code($there), '.')->withFix('Point Straight There', 'straight', $fix)), 'final' => $fix];
		}

		return self::problemOf('chain', Message::warn('Leads to another redirect, ', Message::code($path), ', which goes nowhere.')->withFix('Change Where It Goes', 'edit'));
	}

	/**
	 * Reads what someone typed or pasted as an address: a path on this
	 * site, a whole address (on this site, its path; on another, kept
	 * whole where `to` may leave the site), or why it isn't one.
	 *
	 * @param  'from'|'to'|'test' $mode
	 * @return array{empty?: true, error?: Message, other?: string, url?: string, path?: string, stripped?: bool, slash?: string}
	 */
	public function read(string $raw, string $mode): array
	{
		$text = trim($raw);

		if ($text === '') {
			return ['empty' => true];
		}

		if (preg_match('/\s/', $text) === 1) {
			return ['error' => Message::bad('An address can\'t contain spaces.')];
		}

		$stripped = false;

		if (preg_match('#^https?://#i', $text) === 1) {
			$uri  = Uri::parse($text);
			$host = $uri?->getHost();

			if ($uri === null || $host === null || $host === '') {
				return ['error' => Message::bad('That isn\'t a whole address. It should look like ', Message::code('https://example.com/page'), '.')];
			}

			if (self::bareHost($host) !== self::bareHost($this->host)) {
				return $mode === 'to'
					? ['url' => $text]
					: ['error' => Message::bad('An old address has to be on this site. This one is on ', Message::strong(self::bareHost($host)), '.'), 'other' => self::bareHost($host)];
			}

			$path     = $uri->getPath();
			$query    = $uri->getQuery();
			$text     = ($path === '' ? '/' : $path) . ($query === null || $query === '' ? '' : "?{$query}");
			$stripped = true;
		} elseif (strcasecmp($text, self::bareHost($this->host)) === 0 || stripos($text, self::bareHost($this->host) . '/') === 0 || strcasecmp($text, $this->host) === 0 || stripos($text, $this->host . '/') === 0) {
			$text     = substr($text, strlen(stripos($text, $this->host) === 0 ? $this->host : self::bareHost($this->host))) ?: '/';
			$stripped = true;
		}

		if (! str_starts_with($text, '/')) {
			return ['error' => Message::bad('Start with ', Message::code('/'), ', as in ', Message::code("/{$text}"), '.'), 'slash' => "/{$text}"];
		}

		if (preg_match('/[?#]/', $text, $found) === 1) {
			if ($mode !== 'test') {
				return ['error' => Message::bad('Leave off the part from ', Message::code($found[0]), ' on. A redirect matches the path only.')];
			}

			$text = strpbrk($text, '?#') === false ? $text : substr($text, 0, strcspn($text, '?#'));
		}

		if (preg_match('/[{}]/', (string) preg_replace('/\{[A-Za-z_][A-Za-z0-9_]*\}/', '', $text)) === 1) {
			return ['error' => Message::bad('A placeholder is a name in braces, like ', Message::code('{name}'), '.')];
		}

		try {
			RoutePattern::parse($text);
		} catch (InvalidRoute $e) {
			return ['error' => Message::bad($e->getMessage())];
		}

		return ['path' => SiteAddresses::lookup($text), 'stripped' => $stripped];
	}

	/**
	 * Checks a redirect as the add and edit form has it: messages for
	 * each field, and the row it would save when nothing stops it. A
	 * path that's a live entry's address becomes the entry, so the row
	 * follows it.
	 *
	 * @param  ?string $was The `from` of the row being changed, if any.
	 * @return array{from: list<Message>, to: list<Message>, status: list<Message>, row: ?RedirectRow}
	 */
	public function check(string $fromText, string $toText, ?string $entryId, int $statusCode, ?string $was): array
	{
		$out    = ['from' => [], 'to' => [], 'status' => []];
		$from   = null;
		$to     = null;
		$entry  = null;
		$status = Status::tryFrom($statusCode);

		if ($status === null || ! in_array($status, [Status::MovedPermanently, Status::Found, Status::SeeOther, Status::TemporaryRedirect, Status::PermanentRedirect], true)) {
			$out['status'][] = Message::bad('Choose a type of redirect.');
		}

		$read = $this->read($fromText, 'from');

		if (isset($read['empty'])) {
			$out['from'][] = Message::bad('Enter the old address, starting with ', Message::code('/'), '.');
		} elseif (isset($read['error'])) {
			$out['from'][] = isset($read['slash']) ? $read['error']->withFix('Add the Slash', 'slash-from', $read['slash']) : $read['error'];
		} else {
			$from = $read['path'] ?? '/';

			if ($read['stripped'] ?? false) {
				$out['from'][] = Message::say('Saved as ', Message::code($from), ', without the site\'s address.');
			}

			$same = $this->sameAs($from, $was);
			$code = $this->codeFor($from);

			if ($same !== null) {
				$out['from'][] = new Message(MessageKind::Bad, ['Another redirect already starts here and goes to ', ...$this->toWords($same), '.'])->withFix('Edit That One', 'edit-other', $same->from);
			} elseif ($code !== null) {
				$out['from'][] = Message::warn('The site\'s code already redirects this address to ', Message::code($code->to), ', so this one would be overruled.');
			} elseif (! str_contains($from, '{')) {
				$answer = $this->site->answer($from);

				if ($answer !== null) {
					$out['from'][] = new Message(MessageKind::Warn, ['Does nothing while ', ...self::answerWords($answer), ' answers this address.']);
				}
			}
		}

		if ($entryId !== null && $entryId !== '') {
			$entry = $this->entry($entryId);

			if ($entry === null) {
				$out['to'][] = Message::bad('That page isn\'t there anymore. Choose another.');
			} elseif ($this->site->urlOf($entry) === null) {
				$out['to'][] = Message::warn(Message::strong(self::titleOf($entry)), ' isn\'t live, so visitors would see Not Found until it is.');
			} else {
				$out['to'][] = Message::say('Follows ', Message::strong(self::titleOf($entry)), ' if its address changes.');
			}
		} else {
			$read = $this->read($toText, 'to');

			if (isset($read['empty'])) {
				$out['to'][] = Message::bad('Choose a page, or enter a path or a whole address.');
			} elseif (isset($read['error'])) {
				$out['to'][] = isset($read['slash'])
					? Message::bad('Pick a page from the list, or start a path with ', Message::code('/'), '.')->withFix('Add the Slash', 'slash-to', $read['slash'])
					: $read['error'];
			} elseif (isset($read['url'])) {
				$to = $read['url'];
				$out['to'][] = Message::say('Leaves the site for ', Message::strong(self::bareHost((string) Uri::parse($to)?->getHost())), '.');
			} else {
				$to = $read['path'] ?? '/';

				if ($read['stripped'] ?? false) {
					$out['to'][] = Message::say('Saved as ', Message::code($to), ', without the site\'s address.');
				}

				$page = str_contains($to, '{') ? null : $this->site->entry($to);

				if ($page !== null && $page->id !== null) {
					$entry = $page;
					$to    = null;
					$out['to'][] = Message::say('That\'s the address of ', Message::strong(self::titleOf($page)), ', so this will point at the page itself and follow it if it moves.');
				}
			}
		}

		if ($from !== null && $to !== null) {
			$missing = array_diff(self::params($to), self::params($from));

			if ($missing !== []) {
				$out['to'][] = Message::bad(Message::code('{' . reset($missing) . '}'), ' isn\'t in the old address, so nothing would fill it.');
			}
		}

		$errors = self::errors($out);

		if ($from !== null && ($to !== null || $entry !== null) && $errors === 0 && $status !== null) {
			$target = $entry !== null ? $this->site->urlOf($entry) : $to;

			if ($target !== null && ! self::isUrl($target) && SiteAddresses::lookup($target) === $from) {
				$out['to'][] = Message::bad('This sends the address to itself.');
			} else {
				$draft = new RedirectRow($from, $entry === null ? $to : null, $entry?->id, $status);

				if (! str_contains($from, '{')) {
					$review = $this->with($draft, $was);
					$hops   = $review->trace($from);
					$last   = $hops[array_key_last($hops)];

					if ($last['t'] === 'loop') {
						$path = array_map(static fn (array $hop): array => Message::code($hop['path']), array_values(array_filter($hops, static fn (array $hop): bool => $hop['t'] === 'redirect')));
						$out['to'][] = new Message(MessageKind::Bad, ['This would loop: ', ...self::joined([...$path, Message::code($from)], ' → '), '. Change where one of them goes.']);
					} elseif ($entry === null && ! self::isUrl((string) $to) && ! str_contains($to, '{') && $this->site->answer($from) === null) {
						$next = $hops[1] ?? null;

						if ($next !== null && $next['t'] === 'redirect') {
							$there = match ($last['t']) {
								'page' => $last['path'],
								'away' => $last['url'],
								default => null
							};

							$fix = self::destination($last['answer'] ?? null, (string) $there);

							$out['to'][] = $there === null
								? Message::warn(Message::code($to), ' redirects again, and that one goes nowhere.')
								: Message::warn(Message::code($to), ' redirects again, to ', Message::code($there), '.')->withFix('Go Straight There', 'straight', $fix);
						} elseif ($next !== null && $next['t'] === 'missing') {
							$out['to'][] = Message::warn('Nothing answers ', Message::code($to), ' yet, so visitors would see Not Found.');
						}
					}
				} elseif ($to !== null && ! self::isUrl($to)) {
					$example = self::example($from);
					$out['to'][] = Message::say('Filled from the old address: ', Message::code($example), ' goes to ', Message::code(self::example($to)), '.');
				}
			}
		}

		$row = null;

		if (self::errors($out) === 0 && $from !== null && ($to !== null || $entry !== null) && $status !== null) {
			$row = new RedirectRow($from, $entry === null ? $to : null, $entry?->id, $status);
		}

		return [...$out, 'row' => $row];
	}

	/**
	 * Words for a title, or "Untitled".
	 */
	public static function titleOf(Entry $entry): string
	{
		return $entry->title === '' ? 'Untitled' : $entry->title;
	}

	/**
	 * How many messages stop a save.
	 *
	 * @param array<string, list<Message>> $fields
	 */
	public static function errors(array $fields): int
	{
		return count(array_filter(array_merge(...array_values($fields)), static fn (Message $message): bool => $message->kind === MessageKind::Bad));
	}

	/**
	 * Whether a target is a whole address on another site.
	 */
	public static function isUrl(string $to): bool
	{
		return preg_match('#^https?://#i', $to) === 1;
	}

	/**
	 * A host without its `www.`.
	 */
	public static function bareHost(string $host): string
	{
		return (string) preg_replace('/^www\./i', '', strtolower($host));
	}

	/**
	 * Words naming where a row leads, for a message.
	 *
	 * @return list<array{code: string}|array{strong: string}>
	 */
	public function toWords(RedirectRow $row): array
	{
		if ($row->entry !== null) {
			$entry = $this->entry($row->entry);

			return [Message::strong($entry === null ? 'a page that was deleted' : self::titleOf($entry))];
		}

		return [Message::code($row->to ?? '')];
	}

	/**
	 * Adds a redirect to the matcher, as the route compiler does: the
	 * first for a path or pattern wins.
	 */
	private function add(Redirect $redirect, ?RedirectRow $row): void
	{
		try {
			$pattern = RoutePattern::parse(SiteAddresses::lookup($redirect->from));
		} catch (InvalidRoute) {
			return;
		}

		$key = $pattern->isStatic() ? $pattern->path : $pattern->regex();

		if ($row === null) {
			$this->codeKeys[$key] ??= $redirect;
		}

		if ($pattern->isStatic()) {
			$this->exact[$key] ??= ['code' => $row === null, 'redirect' => $redirect, 'row' => $row, 'pattern' => $pattern];

			return;
		}

		foreach ($this->patterns as $item) {
			if ($item['pattern']->regex() === $key) {
				return;
			}
		}

		$this->patterns[] = ['code' => $row === null, 'redirect' => $redirect, 'row' => $row, 'pattern' => $pattern, 'regex' => '~^' . $key . '$~'];
	}

	/**
	 * The redirect for a path, and the values its placeholders took,
	 * leaving out one row.
	 *
	 * @return ?array{0: array{code: bool, redirect: Redirect, row: ?RedirectRow, pattern: RoutePattern}, 1: array<string, string>}
	 */
	private function match(string $path, ?RedirectRow $skip = null): ?array
	{
		$exact = $this->exact[$path] ?? null;

		if ($exact !== null && ($skip === null || $exact['row'] !== $skip)) {
			return [$exact, []];
		}

		foreach ($this->patterns as $item) {
			if ($skip !== null && $item['row'] === $skip) {
				continue;
			}

			if (preg_match($item['regex'], $path, $matches) === 1) {
				$params = [];

				foreach ($item['pattern']->params as $position => $name) {
					$params[$name] = $matches[$position + 1] ?? '';
				}

				return [$item, $params];
			}
		}

		return null;
	}

	/**
	 * A path or address with its placeholders filled.
	 *
	 * @param  array<string, string> $params
	 * @return Target
	 */
	private static function targetOf(string $to, array $params): array
	{
		foreach ($params as $name => $value) {
			$to = str_replace('{' . $name . '}', $value, $to);
		}

		return self::isUrl($to) ? ['kind' => 'url', 'url' => $to] : ['kind' => 'path', 'path' => $to];
	}

	/**
	 * Where a chain ends, for its fix: the entry there by id, with its
	 * title and address for the form, or the path or address.
	 *
	 * @return Destination
	 */
	private static function destination(Entry|string|null $answer, string $there): array
	{
		return $answer instanceof Entry && $answer->id !== null
			? ['entry' => $answer->id, 'title' => self::titleOf($answer), 'url' => $there]
			: ['to' => $there];
	}

	/**
	 * A problem by its kind.
	 *
	 * @return Problem
	 */
	private static function problemOf(string $kind, Message $message): array
	{
		return ['kind' => $kind, 'label' => self::LABELS[$kind] ?? $kind, 'message' => $message];
	}

	/**
	 * Words naming what answers an address.
	 *
	 * @return list<string|array{strong: string}>
	 */
	private static function answerWords(Entry|string $answer): array
	{
		return $answer instanceof Entry
			? ['the ' . strtolower($answer->type->labels->singular) . ' ', Message::strong(self::titleOf($answer))]
			: ['the site\'s own page'];
	}

	/**
	 * Words for an entry a row leads to that isn't live.
	 *
	 * @return list<string|array{strong: string}>
	 */
	private static function goneWords(?Entry $entry): array
	{
		if ($entry === null) {
			return ['the page it led to was deleted'];
		}

		return [Message::strong(self::titleOf($entry)), match (true) {
			$entry->status === EntryStatus::Trash     => ' is in the trash',
			$entry->status === EntryStatus::Draft     => ' is a draft',
			$entry->status === EntryStatus::Scheduled => ' isn\'t published yet',
			default                                   => ' isn\'t on the site'
		}];
	}

	/**
	 * A route table key: an exact path, or a pattern's regex.
	 */
	private static function key(string $from): ?string
	{
		try {
			$pattern = RoutePattern::parse(SiteAddresses::lookup($from));
		} catch (InvalidRoute) {
			return null;
		}

		return $pattern->isStatic() ? $pattern->path : $pattern->regex();
	}

	/**
	 * The placeholder names in a path or address.
	 *
	 * @return list<string>
	 */
	private static function params(string $text): array
	{
		preg_match_all('/\{([A-Za-z_][A-Za-z0-9_]*)(?::[^}]*)?\}/', $text, $found);

		return $found[1];
	}

	/**
	 * A path with each placeholder filled with an example.
	 */
	private static function example(string $text): string
	{
		return (string) preg_replace_callback('/\{([A-Za-z_][A-Za-z0-9_]*)(?::[^}]*)?\}/', static fn (array $found): string => match (strtolower($found[1])) {
			'year'  => '2024',
			'month' => '05',
			'day'   => '14',
			'page'  => '2',
			default => 'example'
		}, $text);
	}

	/**
	 * Parts with a separator between each.
	 *
	 * @param  list<array{code: string}> $parts
	 * @return list<string|array{code: string}>
	 */
	private static function joined(array $parts, string $between): array
	{
		$out = [];

		foreach ($parts as $index => $part) {
			if ($index > 0) {
				$out[] = $between;
			}

			$out[] = $part;
		}

		return $out;
	}
}
