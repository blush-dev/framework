<?php

/**
 * Expiry tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Content;

use ArrayObject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Activity\Actor;
use Blush\Auth\Account;
use Blush\Auth\Accounts;
use Blush\Cache\ContentVersion;
use Blush\Console\Console;
use Blush\Console\Testing\CommandTester;
use Blush\Content\Editing\EditRefused;
use Blush\Content\Editing\EntryEdit;
use Blush\Content\Editing\EntryEditor;
use Blush\Content\Entries;
use Blush\Content\Entry\Entry;
use Blush\Content\Events\EntriesExpired;
use Blush\Content\Query\Query;
use Blush\Core\Application;
use Blush\Event\Listener\ListenerRegistry;
use Blush\Job\Jobs\GoLiveJob;
use Blush\Workflow\Status;
use Blush\Workflow\Statuses;

#[CoversClass(Statuses::class)]
#[CoversClass(EntryEditor::class)]
#[CoversClass(GoLiveJob::class)]
#[CoversClass(EntriesExpired::class)]
#[CoversClass(ContentVersion::class)]
final class ExpiryTest extends TestCase
{
	use BuildsContentSite;

	private Application $app;

	private Account $editor;

	protected function setUp(): void
	{
		$this->contentConfig(['types' => [
			'post' => ['kind' => 'collection', 'expires' => true],
			'note' => ['kind' => 'collection']
		]]);
		$this->entry('_post/gone.md', "title: Gone\ndate: 2026-05-01 09:00:00\nexpires: 2026-05-20 09:00:00");
		$this->entry('_post/soon.md', "title: Soon\ndate: 2026-05-01 09:00:00\nexpires: 2026-06-10 09:00:00");
		$this->entry('_post/plain.md', "title: Plain\ndate: 2026-05-01 09:00:00");
		$this->entry('_note/kept.md', "title: Kept\ndate: 2026-05-01 09:00:00\nexpires: 2026-05-02 09:00:00");

		$this->app    = $this->site();
		$this->editor = $this->app->container()->make(Accounts::class)->create('eve', 'a long enough password', ['editor'], email: 'eve@example.test');
	}

	private function content(): Entries
	{
		return $this->app->container()->make(Entries::class);
	}

	private function find(string $path): Entry
	{
		$entry = $this->content()->find(self::idFor($path));
		$this->assertNotNull($entry);

		return $entry;
	}

	/**
	 * Returns the titles of a query's entries, sorted.
	 *
	 * @return list<string>
	 */
	private function titles(Query $query): array
	{
		$titles = array_map(static fn (Entry $entry): string => $entry->title, $query->limit(null)->get()->all());
		sort($titles);

		return $titles;
	}

	public function testAnEntryPastItsExpiryIsExpiredAndOffTheSite(): void
	{
		$this->assertSame(Status::EXPIRED, $this->find('_post/gone.md')->status->name);
		$this->assertFalse($this->find('_post/gone.md')->isLive());
		$this->assertSame('2026-05-20', $this->find('_post/gone.md')->expires?->format('Y-m-d'));
		$this->assertSame(Status::PUBLISHED, $this->find('_post/soon.md')->status->name);
		$this->assertSame(Status::PUBLISHED, $this->find('_note/kept.md')->status->name, 'A type without expiry ignores the date.');
		$this->assertNull($this->find('_note/kept.md')->expires);

		$this->assertSame(['Plain', 'Soon'], $this->titles($this->content()->query()->type('post')));
		$this->assertSame(['Gone'], $this->titles($this->content()->query()->type('post')->status(Status::EXPIRED)));
		$this->assertSame(['Gone', 'Plain', 'Soon'], $this->titles($this->content()->query()->type('post')->any()), '"Any status" finds it.');
	}

	public function testTheContentVersionMovesOnAtTheNextExpiry(): void
	{
		$version = $this->app->container()->make(ContentVersion::class);

		$this->assertSame(strtotime('2026-06-10 09:00:00 America/Chicago'), $version->scheduled());
	}

	public function testTheEditorChecksTheDateAndBringsAnEntryBack(): void
	{
		$editor  = $this->app->container()->make(EntryEditor::class);
		$version = fn (Entry $entry): string => $this->content()->editable($entry)->version;

		$early = $this->find('_post/soon.md');

		try {
			$editor->update(Actor::admin($this->editor), $early, new EntryEdit(set: ['expires' => '2026-04-01 09:00:00']), $version($early));
			$this->fail('An expiry before the date was saved.');
		} catch (EditRefused $refused) {
			$this->assertSame('expires', $refused->field);
		}

		$note = $this->find('_note/kept.md');

		try {
			$editor->update(Actor::admin($this->editor), $note, new EntryEdit(set: ['expires' => '2026-07-01 09:00:00']), $version($note));
			$this->fail('A type without expiry took one.');
		} catch (EditRefused $refused) {
			$this->assertSame('expires', $refused->field);
		}

		$gone = $this->find('_post/gone.md');
		$back = $editor->update(Actor::admin($this->editor), $gone, new EntryEdit(remove: ['expires']), $version($gone));

		$this->assertSame(Status::PUBLISHED, $back->status->name, 'Clearing the date brings it back.');
	}

	public function testTheGoLiveJobAnnouncesWhatExpired(): void
	{
		$tester  = new CommandTester($this->app->container()->make(Console::class));
		$expired = new ArrayObject();

		$this->app->container()->make(ListenerRegistry::class)->listen(EntriesExpired::class, static function (EntriesExpired $event) use ($expired): void {
			$expired->append(array_map(static fn (Entry $entry): string => $entry->title, $event->entries));
		});

		$tester->run('schedule:run');

		$this->assertCount(0, $expired, 'The first run only looks.');

		$this->clock->set('2026-06-11 00:00:00 America/Chicago');

		$late = $tester->run('schedule:run -v');

		$this->assertTrue($late->isSuccessful(), $late->output . $late->errors);
		$this->assertSame([['Soon']], $expired->getArrayCopy());
	}
}
