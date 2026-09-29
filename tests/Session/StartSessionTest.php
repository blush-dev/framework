<?php

/**
 * Session tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Session;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Blush\Clock\FrozenClock;
use Blush\Config\InvalidConfig;
use Blush\Core\Paths;
use Blush\Http\Cookie;
use Blush\Http\Request;
use Blush\Http\Response;
use Blush\Http\SameSite;
use Blush\Session\FileSessionStore;
use Blush\Session\Session;
use Blush\Session\SessionConfig;
use Blush\Session\StartSession;
use Blush\Support\Filesystem;
use Blush\Tests\Fixtures\Session\SessionAction;
use Blush\Tests\TemporaryDirectory;

#[CoversClass(Session::class)]
#[CoversClass(SessionConfig::class)]
#[CoversClass(FileSessionStore::class)]
#[CoversClass(StartSession::class)]
#[CoversClass(Cookie::class)]
final class StartSessionTest extends TestCase
{
	use TemporaryDirectory;

	private FrozenClock $clock;

	private FileSessionStore $store;

	protected function setUp(): void
	{
		$this->clock = new FrozenClock('2026-09-29 12:00:00');
		$this->store = new FileSessionStore(Paths::fromRoot($this->temporaryDirectory()), new Filesystem());
	}

	private function send(string $action, ?string $cookie = null, string $url = 'http://example.test/admin'): ResponseInterface
	{
		$request = Request::create($url);
		$request = $cookie === null ? $request : $request->withCookieParams(['blush_session' => $cookie, '__Host-blush_session' => $cookie]);

		return new StartSession($this->store, new SessionConfig(), $this->clock)->process($request, new SessionAction($action));
	}

	/**
	 * Returns the session id a response's cookie sets.
	 */
	private function cookie(ResponseInterface $response): string
	{
		preg_match('/_session=([0-9a-f]{64});/', $response->getHeaderLine('Set-Cookie'), $match);

		return $match[1] ?? '';
	}

	private function sessionFiles(): int
	{
		return count(glob($this->temporaryDirectory() . '/storage/sessions/*.json') ?: []);
	}

	public function testAnUnusedSessionLeavesNothingBehind(): void
	{
		$response = $this->send('read');

		$this->assertSame('0', (string) $response->getBody());
		$this->assertFalse($response->hasHeader('Set-Cookie'));
		$this->assertSame('no-store', $response->getHeaderLine('Cache-Control'));
		$this->assertSame(0, $this->sessionFiles());
	}

	public function testStoringStartsASessionThatLaterRequestsLoad(): void
	{
		$first = $this->send('count');
		$id    = $this->cookie($first);

		$this->assertMatchesRegularExpression('/^blush_session=[0-9a-f]{64}; Path=\/; HttpOnly; SameSite=Strict$/', $first->getHeaderLine('Set-Cookie'));
		$this->assertFileExists($this->temporaryDirectory() . '/storage/sessions/' . hash('sha256', $id) . '.json');

		$second = $this->send('count', $id);

		$this->assertSame('2', (string) $second->getBody());
		$this->assertFalse($second->hasHeader('Set-Cookie'));
	}

	public function testUsesASecureHostCookieOverHttps(): void
	{
		$response = $this->send('count', url: 'https://example.test/admin');

		$this->assertStringStartsWith('__Host-blush_session=', $response->getHeaderLine('Set-Cookie'));
		$this->assertStringContainsString('; Secure;', $response->getHeaderLine('Set-Cookie'));
	}

	public function testDropsIdleAndOldSessions(): void
	{
		$id = $this->cookie($this->send('count'));

		$this->clock->advance('PT1H');
		$this->assertSame('2', (string) $this->send('count', $id)->getBody());

		$this->clock->advance('PT2H1S');
		$idle = $this->send('count', $id);

		$this->assertSame('1', (string) $idle->getBody());
		$this->assertNotSame($id, $this->cookie($idle));
		$this->assertSame(1, $this->sessionFiles());

		$id = $this->cookie($idle);

		for ($hour = 0; $hour < 12; $hour++) {
			$this->clock->advance('PT1H');
			$this->send('count', $id);
		}

		$this->clock->advance('PT1S');
		$this->assertSame('1', (string) $this->send('count', $id)->getBody(), 'A session ends after its lifetime, however busy.');
	}

	public function testRegeneratingChangesTheIdAndDeletesTheOld(): void
	{
		$id       = $this->cookie($this->send('count'));
		$response = $this->send('regenerate', $id);
		$new      = $this->cookie($response);

		$this->assertNotSame('', $new);
		$this->assertNotSame($id, $new);
		$this->assertSame(1, $this->sessionFiles());
		$this->assertSame('2', (string) $this->send('count', $new)->getBody());
	}

	public function testInvalidatingEndsTheSession(): void
	{
		$id       = $this->cookie($this->send('count'));
		$response = $this->send('invalidate', $id);

		$this->assertStringContainsString('blush_session=; Expires=Thu, 01 Jan 1970 00:00:00 GMT; Max-Age=0;', $response->getHeaderLine('Set-Cookie'));
		$this->assertSame(0, $this->sessionFiles());
		$this->assertSame('0', (string) $this->send('read', $id)->getBody());
	}

	public function testClearsACookieThatNamesNoSession(): void
	{
		$response = $this->send('read', 'forged');

		$this->assertStringContainsString('Max-Age=0', $response->getHeaderLine('Set-Cookie'));
	}

	public function testPrunesSessionsLastUsedBeforeATime(): void
	{
		$this->send('count');
		$file = glob($this->temporaryDirectory() . '/storage/sessions/*.json')[0] ?? '';
		touch($file, 1000);

		$this->assertSame(1, $this->store->prune(2000));
		$this->assertSame(0, $this->sessionFiles());
	}

	public function testRejectsValuesThatArentData(): void
	{
		$this->expectException(InvalidArgumentException::class);

		Session::start(0)->set('bad', new \stdClass());
	}

	public function testChecksItsConfig(): void
	{
		$this->assertEquals(new SessionConfig(idle: 600, secure: true), SessionConfig::fromArray(new SessionConfig(idle: 600, secure: true)->toArray()));

		$this->expectException(InvalidConfig::class);

		new SessionConfig(idle: 3600, lifetime: 60);
	}

	public function testCookiesDefaultToTheSafeFlags(): void
	{
		$cookie   = new Cookie('pref', 'a b');
		$response = Response::text('')->withCookie($cookie)->withCookie(new Cookie('other', 'x', expires: 0, sameSite: SameSite::Lax));

		$this->assertSame('pref=a%20b; Path=/; Secure; HttpOnly; SameSite=Strict', $cookie->header());
		$this->assertCount(2, $response->getHeader('Set-Cookie'));
		$this->assertStringContainsString('SameSite=Lax', $response->getHeader('Set-Cookie')[1]);
	}

	public function testHostCookiesMustBeSecureOnTheRoot(): void
	{
		$this->expectException(InvalidArgumentException::class);

		new Cookie('__Host-id', 'x', secure: false);
	}
}
