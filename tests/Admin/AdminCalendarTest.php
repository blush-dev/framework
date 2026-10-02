<?php

/**
 * Admin calendar API tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Admin;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Admin\CalendarController;

#[CoversClass(CalendarController::class)]
final class AdminCalendarTest extends TestCase
{
	use BootsAdmin;

	/**
	 * Writes dated entries by two authors, an undated draft, and a type's
	 * index page, then boots the admin in Chicago's time.
	 *
	 * @param list<string> $roles
	 */
	private function site(array $roles = ['editor']): void
	{
		$this->writeTemporaryFile('user/data/types/post.yaml', "folder: _posts\n");
		$this->writeTemporaryFile('user/content/_posts/index.md', "---\ntitle: Writing\npublished: 2026-03-02 09:00:00\n---\n");
		$this->writeTemporaryFile('user/content/_posts/later.md', "---\ntitle: Later\npublished: 2026-03-20 14:30:00\nauthors: sam\n---\n");
		$this->writeTemporaryFile('user/content/_posts/earlier.md', "---\ntitle: Earlier\npublished: 2026-03-05 08:00:00\nauthors: jane\n---\n");
		$this->writeTemporaryFile('user/content/_posts/planned.md', "---\ntitle: Planned\nstatus: draft\npublished: 2026-03-11 10:00:00\nauthors: jane\n---\n");
		$this->writeTemporaryFile('user/content/_posts/idea.md', "---\ntitle: Idea\nstatus: draft\nauthors: jane\n---\n");
		// Late on the 31st in Chicago is April in UTC.
		$this->writeTemporaryFile('user/content/_posts/late.md', "---\ntitle: Late\npublished: 2026-04-01T03:00:00Z\nauthors: jane\n---\n");
		$this->writeTemporaryFile('user/data/types/topic.yaml', "kind: taxonomy\nfolder: _topics\n");
		$this->writeTemporaryFile('user/content/_topics/news.md', "---\ntitle: News\npublished: 2026-03-09 12:00:00\n---\n");
		$this->writeTemporaryFile('user/content/soon.md', "---\ntitle: Soon\npublished: 2099-01-01 09:00:00\nauthors: jane\n---\n");

		$this->boot(roles: $roles, environment: ['APP_TIMEZONE' => 'America/Chicago']);
		$this->login();
	}

	/**
	 * @return array<mixed>
	 */
	private function calendar(string $query): array
	{
		$response = $this->send('GET', "/calendar{$query}");

		$this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());

		return self::json($response);
	}

	/**
	 * @param  array<mixed> $calendar
	 * @return list<mixed>
	 */
	private static function titles(array $calendar): array
	{
		return array_column(is_array($calendar['entries'] ?? null) ? $calendar['entries'] : [], 'title');
	}

	public function testPlacesAMonthsDatedEntriesOnTheirDays(): void
	{
		$this->site();

		$march = $this->calendar('?month=2026-03');

		$this->assertSame(['Earlier', 'Planned', 'Later', 'Late'], self::titles($march), 'In date order; no index page, undated draft, or term.');
		$this->assertSame(['2026-03', 'any', null, 4], [$march['month'] ?? null, $march['status'] ?? null, $march['type'] ?? null, $march['total'] ?? null]);

		$entries = is_array($march['entries'] ?? null) ? $march['entries'] : [];
		$later   = $entries[2] ?? null;
		$late    = $entries[3] ?? null;

		$this->assertIsArray($later);
		$this->assertSame(['post', 'published', 20, '14:30', 'post/later'], [$later['type'] ?? null, $later['status'] ?? null, $later['day'] ?? null, $later['time'] ?? null, $later['handle'] ?? null]);
		$this->assertIsArray($late);
		$this->assertSame([31, '22:00'], [$late['day'] ?? null, $late['time'] ?? null], 'Days and times are the site\'s.');
		$this->assertSame('draft', is_array($entries[1] ?? null) ? $entries[1]['status'] ?? null : null);

		$soon = $this->calendar('?month=2099-01');

		$this->assertSame(['Soon'], self::titles($soon));
		$this->assertSame(['scheduled'], array_column(is_array($soon['entries'] ?? null) ? $soon['entries'] : [], 'status'));
		$this->assertSame([], self::titles($this->calendar('?month=2026-02')));
	}

	public function testFiltersByStatusAndType(): void
	{
		$this->site();

		$this->assertSame(['Planned'], self::titles($this->calendar('?month=2026-03&status=draft')));
		$this->assertSame(['Earlier', 'Later', 'Late'], self::titles($this->calendar('?month=2026-03&status=published')));
		$this->assertSame([], self::titles($this->calendar('?month=2026-03&type=page')));
		$this->assertSame(['News'], self::titles($this->calendar('?month=2026-03&type=topic')), 'Terms only when asked for.');
		$this->assertSame(['Soon'], self::titles($this->calendar('?month=2099-01&type=page')));
	}

	public function testShowsOnlyWhatTheAccountMayEdit(): void
	{
		$this->site(['author']);

		$this->assertSame(['Earlier', 'Planned', 'Late'], self::titles($this->calendar('?month=2026-03')));
	}

	public function testDefaultsToTheSitesCurrentMonth(): void
	{
		$this->site();

		$now = new DateTimeImmutable('now', new DateTimeZone('America/Chicago'));
		$calendar = $this->calendar('');

		$this->assertSame([$now->format('Y-m'), $now->format('Y-m-d')], [$calendar['month'] ?? null, $calendar['today'] ?? null]);
	}

	public function testRefusesMalformedRequests(): void
	{
		$this->site();

		foreach (['?month=2026-13', '?month=March', '?status=pending', '?type=nope'] as $query) {
			$this->assertSame(400, $this->send('GET', "/calendar{$query}")->getStatusCode(), $query);
		}

		$this->cookie = null;
		$this->assertSame(401, $this->send('GET', '/calendar')->getStatusCode());
	}
}
