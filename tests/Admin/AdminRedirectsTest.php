<?php

/**
 * Admin redirects tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Blush\Admin\CountsController;
use Blush\Admin\Redirects\Message;
use Blush\Admin\Redirects\RedirectReview;
use Blush\Admin\Redirects\RedirectsController;
use Blush\Admin\Redirects\SiteAddresses;
use Blush\Content\Routing\DataRedirects;
use Blush\Content\Routing\RedirectRow;
use Blush\Content\Routing\Redirects;
use Blush\Tests\WritesContentConfig;

#[CoversClass(RedirectsController::class)]
#[CoversClass(RedirectReview::class)]
#[CoversClass(SiteAddresses::class)]
#[CoversClass(Message::class)]
#[CoversClass(RedirectRow::class)]
#[CoversClass(Redirects::class)]
#[CoversClass(DataRedirects::class)]
#[CoversClass(CountsController::class)]
final class AdminRedirectsTest extends TestCase
{
	use BootsAdmin;
	use WritesContentConfig;

	private const string ABOUT = '0199b6e2-0000-7000-8000-00000000a001';

	private const string FLAME = '0199b6e2-0000-7000-8000-00000000a002';

	private const string GONE = '0199b6e2-0000-7000-8000-00000000dead';

	protected function tearDown(): void
	{
		$this->removeTemporaryDirectory();
	}

	/**
	 * @param list<string> $roles
	 */
	private function site(array $roles = ['editor']): void
	{
		$this->contentConfig(['types' => ['post' => ['routing' => ['prefix' => 'archives']]]]);
		$this->writeTemporaryFile('user/content/about.md', "---\ntitle: About\nid: " . self::ABOUT . "\n---\n");
		$this->writeTemporaryFile('user/content/_post/2022-03-29.flame.md', "---\ntitle: Rekindling the Flame\ndate: 2022-03-29 12:00:00\nid: " . self::FLAME . "\n---\n");
		$this->writeTemporaryFile('config/routes.php', "<?php\n\ndeclare(strict_types=1);\n\nuse Blush\\Routing\\Redirect;\nuse Blush\\Routing\\RouteConfig;\n\nreturn new RouteConfig(redirects: [new Redirect('/rss', '/about')]);\n");
		$this->writeTemporaryFile('user/data/redirects.json', json_encode([
			['from' => '/old-about', 'to' => '/about'],
			['from' => '/about', 'to' => '/elsewhere'],
			['from' => '/gone', 'entry' => self::GONE],
			['from' => '/chain', 'to' => '/old-about'],
			['from' => '/nowhere', 'to' => '/missing-page'],
			['from' => '/news/{name}', 'to' => '/blog/{name}'],
			['from' => '/blog/{name}', 'entry' => self::FLAME],
			['from' => '/sale', 'to' => 'https://shop.example.com/autumn', 'status' => 302],
			['from' => '/rss', 'to' => '/elsewhere'],
			['from' => '/loop-b', 'to' => '/loop-a']
		], JSON_UNESCAPED_SLASHES) ?: '');

		$this->boot(roles: $roles);
		$this->login();
	}

	/**
	 * Sends a request with the CSRF token.
	 *
	 * @param array<string, mixed> $data
	 */
	private function write(string $method, string $path, array $data = []): ResponseInterface
	{
		$token = self::json($this->send('GET', '/session'))['csrfToken'] ?? '';

		return $this->send($method, $path, json_encode($data) ?: '', ['X-CSRF-Token' => is_string($token) ? $token : '']);
	}

	/**
	 * Returns a value inside a JSON answer by its keys, or `null`.
	 */
	private static function at(mixed $value, string|int ...$keys): mixed
	{
		foreach ($keys as $key) {
			$value = is_array($value) ? ($value[$key] ?? null) : null;
		}

		return $value;
	}

	/**
	 * The list's rows, by old path.
	 *
	 * @param  array<mixed> $answer
	 * @return array<string, array<mixed>>
	 */
	private static function rows(array $answer): array
	{
		$rows = [];

		foreach (is_array($answer['redirects'] ?? null) ? $answer['redirects'] : [] as $row) {
			if (is_array($row) && is_string($row['from'] ?? null)) {
				$rows[$row['from']] = $row;
			}
		}

		return $rows;
	}

	/**
	 * A row's problem's kind, or `null`.
	 *
	 * @param array<mixed> $row
	 */
	private static function problem(array $row): ?string
	{
		$problem = $row['problem'] ?? null;

		return is_array($problem) && is_string($problem['kind'] ?? null) ? $problem['kind'] : null;
	}

	/**
	 * A field's messages' text, plain.
	 *
	 * @param  array<mixed> $answer
	 * @return list<string>
	 */
	private static function said(array $answer, string $field): array
	{
		$lines = [];

		foreach (is_array($answer[$field] ?? null) ? $answer[$field] : [] as $message) {
			$parts = is_array($message) && is_array($message['parts'] ?? null) ? $message['parts'] : [];
			$text  = '';

			foreach ($parts as $part) {
				$text .= is_string($part) ? $part : (is_array($part) ? implode('', array_filter($part, is_string(...))) : '');
			}

			$lines[] = (is_array($message) && is_string($message['kind'] ?? null) ? $message['kind'] : '') . ': ' . $text;
		}

		return $lines;
	}

	/**
	 * The `redirects` table's rows as the file keeps them, by old path.
	 *
	 * @return array<string, array<mixed>>
	 */
	private function stored(): array
	{
		$rows = json_decode((string) file_get_contents($this->temporaryDirectory() . '/user/data/redirects.json'), true);
		$keyed = [];

		foreach (is_array($rows) ? $rows : [] as $row) {
			if (is_array($row) && is_string($row['from'] ?? null)) {
				$keyed[$row['from']] = $row;
			}
		}

		return $keyed;
	}

	public function testListsRowsWithTheirProblems(): void
	{
		$this->site();

		$answer = self::json($this->send('GET', '/redirects?per=50'));
		$rows   = self::rows($answer);

		$this->assertSame(['all' => 10, 'permanent' => 9, 'temporary' => 1, 'problems' => 6], $answer['counts'] ?? null);
		$this->assertSame(['/about', '/blog/{name}', '/chain', '/gone', '/loop-b', '/news/{name}', '/nowhere', '/old-about', '/rss', '/sale'], array_keys($rows), 'Sorted by old path.');
		$this->assertSame(
			['/about' => 'live', '/chain' => 'chain', '/gone' => 'gone', '/loop-b' => 'missing', '/nowhere' => 'missing', '/rss' => 'code'],
			array_filter(array_map(self::problem(...), $rows))
		);
		$this->assertSame(['entry' => self::ABOUT, 'title' => 'About', 'url' => '/about'], self::at($rows, '/chain', 'problem', 'final'), 'A chain can point straight at the page at its end.');
		$this->assertSame(['id' => self::FLAME, 'title' => 'Rekindling the Flame', 'url' => '/archives/flame', 'type' => 'post', 'state' => 'live'], self::at($rows, '/blog/{name}', 'entry'));
		$this->assertSame('deleted', self::at($rows, '/gone', 'entry', 'state'));
		$this->assertSame([['from' => '/rss', 'to' => '/about', 'status' => 301]], $answer['code'] ?? null, 'The site\'s code, read-only.');

		$problems = self::json($this->send('GET', '/redirects?tab=problems'));
		$this->assertSame(6, $problems['total'] ?? null);

		$away = self::json($this->send('GET', '/redirects?goes=away'));
		$this->assertSame(['/sale'], array_keys(self::rows($away)));

		$paged = self::json($this->send('GET', '/redirects?per=10&page=2'));
		$this->assertSame([1, 10], [$paged['pages'] ?? null, $paged['total'] ?? null], 'A page past the end is the last.');

		$this->assertSame(10, self::json($this->send('GET', '/counts'))['redirects'] ?? null);
	}

	public function testSearchIsAlsoATest(): void
	{
		$this->site();

		$answer = self::json($this->send('GET', '/redirects?search=' . rawurlencode('https://example.test/news/flame')));

		$this->assertSame(['/news/{name}'], array_keys(self::rows($answer)), 'The row that handles the address.');
		$this->assertSame('/news/flame', self::at($answer, 'trace', 'path'));
		$this->assertSame(
			[['redirect', '/blog/flame'], ['redirect', '/archives/flame'], ['page', 'Rekindling the Flame']],
			array_map(static fn (mixed $hop): array => [self::at($hop, 't'), self::at($hop, 'target', 'path') ?? self::at($hop, 'title')], (array) self::at($answer, 'trace', 'hops')),
			'Followed as a visitor would, to the entry the second row names.'
		);

		$other = self::json($this->send('GET', '/redirects?search=' . rawurlencode('https://elsewhere.test/x')));
		$this->assertSame('elsewhere.test', self::at($other, 'trace', 'other'));

		$text = self::json($this->send('GET', '/redirects?search=flame'));
		$this->assertSame(['/blog/{name}'], array_keys(self::rows($text)), 'Text matches an entry\'s title and address.');
		$this->assertNull($text['trace'] ?? null);
	}

	public function testChecksTheForm(): void
	{
		$this->site();

		$duplicate = self::json($this->write('POST', '/redirects/check', ['from' => '/old-about/', 'to' => '/x', 'status' => 301]));
		$this->assertSame(['bad: Another redirect already starts here and goes to /about.'], self::said($duplicate, 'from'));
		$this->assertSame(['label' => 'Edit That One', 'action' => 'edit-other', 'value' => '/old-about'], self::at($duplicate, 'from', 0, 'fix'));

		$page = self::json($this->write('POST', '/redirects/check', ['from' => 'https://example.test/team', 'to' => '/about', 'status' => 301]));
		$this->assertSame(['say: Saved as /team, without the site\'s address.'], self::said($page, 'from'));
		$this->assertSame(['say: That\'s the address of About, so this will point at the page itself and follow it if it moves.'], self::said($page, 'to'));
		$this->assertSame(['from' => '/team', 'entry' => self::ABOUT, 'status' => 301], $page['row'] ?? null);

		$loop = self::json($this->write('POST', '/redirects/check', ['from' => '/loop-a', 'to' => '/loop-b', 'status' => 301]));
		$this->assertSame(['bad: This would loop: /loop-a → /loop-b → /loop-a. Change where one of them goes.'], self::said($loop, 'to'));
		$this->assertNull($loop['row'] ?? null);

		$chain = self::json($this->write('POST', '/redirects/check', ['from' => '/team', 'to' => '/chain', 'status' => 301]));
		$this->assertSame(['warn: /chain redirects again, to /about.'], self::said($chain, 'to'));
		$this->assertSame(['entry' => self::ABOUT, 'title' => 'About', 'url' => '/about'], self::at($chain, 'to', 0, 'fix', 'value'));

		$slash = self::json($this->write('POST', '/redirects/check', ['from' => 'team', 'to' => '/news/{slug}', 'status' => 301]));
		$this->assertSame(['label' => 'Add the Slash', 'action' => 'slash-from', 'value' => '/team'], self::at($slash, 'from', 0, 'fix'));

		$filled = self::json($this->write('POST', '/redirects/check', ['from' => '/old/{year}/{name}', 'to' => '/archives/{name}/{slug}', 'status' => 301]));
		$this->assertSame(['bad: {slug} isn\'t in the old address, so nothing would fill it.'], self::said($filled, 'to'));

		$live = self::json($this->write('POST', '/redirects/check', ['from' => '/archives/flame', 'to' => 'https://example.com/', 'status' => 307]));
		$this->assertSame(['warn: Does nothing while the post Rekindling the Flame answers this address.'], self::said($live, 'from'));
		$this->assertSame(['say: Leaves the site for example.com.'], self::said($live, 'to'));
	}

	public function testAddsChangesAndDeletesRows(): void
	{
		$this->site();

		$bad = $this->write('POST', '/redirects', ['from' => '/old-about', 'to' => '/x', 'status' => 301]);
		$this->assertSame(422, $bad->getStatusCode());
		$this->assertArrayNotHasKey('/x', $this->stored());

		$added = self::json($this->write('POST', '/redirects', ['from' => '/team', 'to' => '/about', 'status' => 302]));
		$row   = $this->stored()['/team'] ?? [];

		$this->assertSame([self::ABOUT, null, 302, $this->janeId()], [$row['entry'] ?? null, $row['to'] ?? null, $row['status'] ?? null, $row['by'] ?? null], 'A page\'s address becomes the page, and the row says who added it.');
		$this->assertIsString($row['added'] ?? null);
		$this->assertSame(['name' => 'jane', 'username' => 'jane', 'you' => true], self::at($added, 'redirect', 'by'), 'The account, to name and link to.');
		$this->assertNull($added['was'] ?? null);

		$changed = self::json($this->write('POST', '/redirects', ['was' => '/team', 'from' => '/people', 'to' => 'https://example.com/team', 'status' => 301]));
		$this->assertSame(['/people'], array_values(array_intersect(['/team', '/people'], array_keys($this->stored()))), 'Changing the old path moves the row.');
		$this->assertSame([$row['added'], $this->janeId()], [$this->stored()['/people']['added'] ?? null, $this->stored()['/people']['by'] ?? null], 'It keeps when and by whom it was added.');
		$this->assertSame('/team', self::at($changed, 'was', 'from'));

		$this->assertSame(200, $this->write('POST', '/redirects/restore', ['rows' => [$changed['was'] ?? []], 'remove' => ['/people']])->getStatusCode());
		$this->assertSame(self::ABOUT, $this->stored()['/team']['entry'] ?? null, 'Undo puts it back.');
		$this->assertArrayNotHasKey('/people', $this->stored());

		$retyped = self::json($this->write('POST', '/redirects/status', ['from' => ['/team', '/sale', '/old-about'], 'status' => 301]));
		$this->assertSame(['/team', '/sale'], array_column(is_array($retyped['changed'] ?? null) ? $retyped['changed'] : [], 'from'), 'Only those that changed, as they were.');
		$this->assertSame(301, $this->stored()['/sale']['status'] ?? null);

		$deleted = self::json($this->write('POST', '/redirects/delete', ['from' => ['/team', '/nope']]));
		$this->assertSame(['/team'], array_column(is_array($deleted['deleted'] ?? null) ? $deleted['deleted'] : [], 'from'));
		$this->assertArrayNotHasKey('/team', $this->stored());

		$this->assertSame('/blog/flame', $this->visit('GET', '/news/flame')->getHeaderLine('Location'), 'The site follows the rows.');
		$this->assertSame('/archives/flame', $this->visit('GET', '/blog/flame')->getHeaderLine('Location'));
	}

	public function testNeedsTheCapability(): void
	{
		$this->site(['author']);

		$this->assertSame(403, $this->send('GET', '/redirects')->getStatusCode());
		$this->assertSame(403, $this->write('POST', '/redirects', ['from' => '/a', 'to' => '/b', 'status' => 301])->getStatusCode());
		$this->assertArrayNotHasKey('redirects', self::json($this->send('GET', '/counts')));
	}

	public function testAnEntryRowFollowsItsEntry(): void
	{
		$this->site();

		$redirects = [];

		foreach ($this->app->container()->make(DataRedirects::class)->redirects() as $redirect) {
			$redirects[$redirect->from] = $redirect->to;
		}

		$this->assertSame('/archives/flame', $redirects['/blog/{name}'] ?? null, 'A row naming an entry leads to its address.');
		$this->assertArrayNotHasKey('/gone', $redirects, 'One naming an entry that isn\'t live is left out, so its old address is a 404.');
	}
}
