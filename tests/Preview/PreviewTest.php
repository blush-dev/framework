<?php

/**
 * Preview link tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Preview;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ResponseInterface;
use Blush\Clock\FrozenClock;
use Blush\Config\InvalidConfig;
use Blush\Console\Commands\PreviewContent;
use Blush\Console\Console;
use Blush\Console\ExitCode;
use Blush\Console\Testing\CommandTester;
use Blush\Content\Entries;
use Blush\Content\Entry\Entry;
use Blush\Core\Application;
use Blush\Http\Kernel;
use Blush\Http\Request;
use Blush\Preview\PreviewConfig;
use Blush\Preview\PreviewController;
use Blush\Preview\PreviewLink;
use Blush\Preview\PreviewLinks;
use Blush\Preview\PreviewRoutes;
use Blush\Tests\BootsScratchSite;
use Uri\Rfc3986\Uri;

#[CoversClass(PreviewConfig::class)]
#[CoversClass(PreviewLink::class)]
#[CoversClass(PreviewLinks::class)]
#[CoversClass(PreviewController::class)]
#[CoversClass(PreviewRoutes::class)]
#[CoversClass(PreviewContent::class)]
final class PreviewTest extends TestCase
{
	use BootsScratchSite;

	private const string SECRET = 'a-secret-that-is-long-enough-for-signing';

	private Application $app;

	private FrozenClock $clock;

	private function boot(?string $secret = self::SECRET): void
	{
		$this->writeTemporaryFile('user/content/idea.md', "---\ntitle: A Secret Idea\nstatus: draft\nid: 0199b6e2-0000-7000-8000-000000000001\n---\nNot yet.\n");
		$this->writeTemporaryFile('user/content/other.md', "---\ntitle: Another Draft\nstatus: draft\nid: 0199b6e2-0000-7000-8000-000000000002\n---\n");

		$this->start($secret);
	}

	/**
	 * Builds the application, as each request does, at a frozen time.
	 */
	private function start(?string $secret = self::SECRET): void
	{
		$this->app = $this->scratchApplication(['APP_ENV' => 'development', 'APP_URL' => 'https://example.test', ...($secret === null ? [] : ['APP_SECRET' => $secret])]);
		$this->app->boot();

		$this->clock = new FrozenClock('2026-09-29 12:00:00');
		$this->app->container()->instance(ClockInterface::class, $this->clock);
	}

	private function entry(string $name): Entry
	{
		$entry = $this->app->container()->make(Entries::class)->named('page', $name);
		$this->assertNotNull($entry);

		return $entry;
	}

	private function links(): PreviewLinks
	{
		return $this->app->container()->make(PreviewLinks::class);
	}

	private function visit(string $url): ResponseInterface
	{
		return $this->app->container()->make(Kernel::class)->handle(Request::create($url));
	}

	public function testShowsADraftToAnyoneWithTheLink(): void
	{
		$this->boot();

		$link = $this->links()->make($this->entry('idea'));

		$this->assertStringStartsWith('https://example.test/_blush/preview?entry=0199b6e2-0000-7000-8000-000000000001&', $link->url, 'Named by its id, not its file (D-481).');
		$this->assertSame($this->clock->now()->getTimestamp() + 604800, $link->expires);

		$response = $this->visit($link->url);

		$this->assertSame(200, $response->getStatusCode());
		$this->assertStringContainsString('A Secret Idea', (string) $response->getBody());
		$this->assertSame('no-store', $response->getHeaderLine('Cache-Control'));
		$this->assertSame('noindex, nofollow', $response->getHeaderLine('X-Robots-Tag'));
		$this->assertSame('no-referrer', $response->getHeaderLine('Referrer-Policy'));

		$this->assertSame(404, $this->visit('https://example.test/idea')->getStatusCode(), 'The draft stays hidden at its own URL.');
	}

	public function testRefusesChangedAndExpiredLinks(): void
	{
		$this->boot();

		$link  = $this->links()->make($this->entry('idea'), 3600);
		$query = [];
		parse_str(Uri::parse($link->url)?->getQuery() ?? '', $query);

		$this->assertIsString($query['entry'] ?? null);
		$this->assertIsString($query['signature'] ?? null);

		$other  = 'https://example.test/_blush/preview?' . http_build_query(['entry' => $this->entry('other')->id, 'expires' => $query['expires'] ?? '', 'signature' => $query['signature']]);
		$longer = 'https://example.test/_blush/preview?' . http_build_query(['entry' => $query['entry'], 'expires' => '9999999999', 'signature' => $query['signature']]);

		$this->assertSame(403, $this->visit($other)->getStatusCode());
		$this->assertSame(403, $this->visit($longer)->getStatusCode());
		$this->assertSame(403, $this->visit('https://example.test/_blush/preview')->getStatusCode());

		$this->clock->advance('PT1H1S');

		$expired = $this->visit($link->url);

		$this->assertSame(403, $expired->getStatusCode());
		$this->assertStringContainsString('expired', (string) $expired->getBody());
	}

	public function testALinkToARemovedEntryIsNotFound(): void
	{
		$this->boot();

		$link = $this->links()->make($this->entry('idea'));
		unlink($this->temporaryDirectory() . '/user/content/idea.md');

		// The next request reindexes and finds the entry gone.
		$this->start();

		$this->assertSame(404, $this->visit($link->url)->getStatusCode());
	}

	public function testALinkFollowsItsEntryWhenItMoves(): void
	{
		$this->boot();

		$link = $this->links()->make($this->entry('idea'));
		rename($this->temporaryDirectory() . '/user/content/idea.md', $this->temporaryDirectory() . '/user/content/renamed.md');

		$this->start();

		$response = $this->visit($link->url);

		$this->assertSame(200, $response->getStatusCode(), 'The link names the entry by id (D-481).');
		$this->assertStringContainsString('A Secret Idea', (string) $response->getBody());
	}

	public function testIsOffWithoutASecret(): void
	{
		$this->boot(secret: null);

		$this->assertFalse($this->app->container()->make(PreviewConfig::class)->isEnabled());
		$this->assertSame(404, $this->visit('https://example.test/_blush/preview?entry=x&expires=1&signature=x')->getStatusCode());

		$this->expectException(LogicException::class);

		$this->links()->make($this->entry('idea'));
	}

	public function testPrintsALinkFromTheCommandLine(): void
	{
		$this->boot();

		$tester = new CommandTester($this->app->container()->make(Console::class));
		$result = $tester->run('content:preview page idea --hours=2');

		$this->assertSame(ExitCode::Success, $result->exitCode, $result->errors);
		$this->assertStringContainsString('https://example.test/_blush/preview?entry=', $result->output);
		$this->assertStringContainsString('(draft)', $result->output);
		$this->assertSame(ExitCode::Failure, $tester->run('content:preview page missing')->exitCode);
	}

	public function testChecksItsConfig(): void
	{
		$config = new PreviewConfig(self::SECRET, 3600, 'preview');

		$this->assertSame('/preview', $config->path);
		$this->assertEquals($config, PreviewConfig::fromArray($config->toArray()));

		$this->expectException(InvalidConfig::class);

		new PreviewConfig('short');
	}
}
