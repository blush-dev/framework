<?php

/**
 * Activity log tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Activity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Activity\Activity;
use Blush\Activity\ActivityAction;
use Blush\Activity\ActivityFilter;
use Blush\Activity\ActivityLog;
use Blush\Activity\Actor;
use Blush\Activity\Via;
use Blush\Auth\Account;
use Blush\Auth\Accounts;
use Blush\Content\Editing\EditRefused;
use Blush\Content\Editing\EntryEdit;
use Blush\Content\Editing\EntryEditor;
use Blush\Content\Editing\Refusal;
use Blush\Content\Entries;
use Blush\Content\Entry\Entry;
use Blush\Core\Application;
use Blush\Media\MediaEditor;
use Blush\Media\MediaMetadataStore;
use Blush\Tests\Content\BuildsContentSite;
use Blush\Workflow\Status;

#[CoversClass(ActivityLog::class)]
#[CoversClass(Activity::class)]
#[CoversClass(Actor::class)]
#[CoversClass(EntryEditor::class)]
#[CoversClass(MediaEditor::class)]
final class ActivityTest extends TestCase
{
	use BuildsContentSite;

	/**
	 * A one-pixel PNG.
	 */
	private const string PIXEL = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

	private Application $app;

	private Account $jane;

	protected function setUp(): void
	{
		$this->standardContent();
		$this->entry('_post/2021-01-01.mine.md', "title: Mine\npublished: 2021-01-01\nauthor: justintadlock", 'The first body.');
		$this->writeTemporaryFile('config/cache.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Cache\\CacheConfig(enabled: false);\n");

		$this->app  = $this->site();
		$this->jane = $this->app->container()->make(Accounts::class)->create('jane', 'a long enough password', ['editor'], email: 'jane@example.test');
	}

	private function editor(): EntryEditor
	{
		return $this->app->container()->make(EntryEditor::class);
	}

	private function log(): ActivityLog
	{
		return $this->app->container()->make(ActivityLog::class);
	}

	private function mine(): Entry
	{
		$entry = $this->app->container()->make(Entries::class)->find(self::idFor('_post/2021-01-01.mine.md'));
		$this->assertNotNull($entry);

		return $entry;
	}

	private function revision(): string
	{
		return $this->app->container()->make(Entries::class)->editable($this->mine())->version;
	}

	public function testRecordsEachWriteWithWhoAndHow(): void
	{
		$this->editor()->update(Actor::admin($this->jane), $this->mine(), new EntryEdit(body: 'The second body.'), $this->revision());

		[$changes, $total] = $this->log()->list(new ActivityFilter(subject: self::idFor('_post/2021-01-01.mine.md')));

		$this->assertSame(1, $total);
		$this->assertSame(ActivityAction::EntryUpdate, $changes[0]->action);
		$this->assertSame(Via::Admin, $changes[0]->via);
		$this->assertSame($this->jane->id, $changes[0]->account);
		$this->assertSame('Edited the body of “Mine”', (string) $changes[0]->summary);
		$this->assertTrue($changes[0]->canUndo());
		$this->assertSame([], $this->log()->list(new ActivityFilter(via: Via::Mcp))[0]);
	}

	public function testUndoesAnEditOnlyWhileNothingChangedSince(): void
	{
		$this->editor()->update(Actor::admin($this->jane), $this->mine(), new EntryEdit(body: 'The second body.'), $this->revision());

		$edit = $this->log()->latest(self::idFor('_post/2021-01-01.mine.md'));
		$this->assertNotNull($edit);

		$this->clock->advance('PT1M');
		$this->editor()->undo(Actor::admin($this->jane), $edit);

		$this->assertStringContainsString('The first body.', $this->mine()->raw());

		$edit = $this->log()->find($edit->id);
		$this->assertNotNull($edit);
		$this->assertNotNull($edit->undone, 'Marked undone by the change that undid it.');
		$this->assertSame($edit->id, $this->log()->find((string) $edit->undone)?->undoes);

		try {
			$this->editor()->undo(Actor::admin($this->jane), $edit);
			$this->fail('Undone once.');
		} catch (EditRefused $refused) {
			$this->assertSame(Refusal::Unprocessable, $refused->refusal);
		}

		$this->editor()->update(Actor::admin($this->jane), $this->mine(), new EntryEdit(body: 'A third body.'), $this->revision());
		$third = $this->log()->latest(self::idFor('_post/2021-01-01.mine.md'));
		$this->assertNotNull($third);
		$this->editor()->update(Actor::admin($this->jane), $this->mine(), new EntryEdit(set: ['title' => 'Mine, again']), $this->revision());

		$this->expectExceptionMessage('changed since');
		$this->editor()->undo(Actor::admin($this->jane), $third);
	}

	public function testUndoesTrashingWithTheStatusItHad(): void
	{
		$this->editor()->trash(Actor::admin($this->jane), $this->mine(), $this->revision());

		$trashed = $this->log()->latest(self::idFor('_post/2021-01-01.mine.md'));
		$this->assertNotNull($trashed);
		$this->assertSame(ActivityAction::EntryTrash, $trashed->action);

		$this->editor()->undo(Actor::admin($this->jane), $trashed);

		$this->assertSame(Status::PUBLISHED, $this->mine()->status->name, 'Put back as it was, not as a draft.');
	}

	public function testRecordsAndUndoesMediaDetails(): void
	{
		$media    = $this->app->container()->make(MediaEditor::class);
		$uploaded = $media->uploadBytes(Actor::admin($this->jane), 'dot.png', (string) base64_decode(self::PIXEL, true));

		$this->assertNotNull($uploaded->id);

		$this->clock->advance('PT1M');
		$media->update(Actor::admin($this->jane), $uploaded->relative, ['alt' => 'A dot']);

		[$changes] = $this->log()->list(new ActivityFilter(subject: $uploaded->id));
		$this->assertSame([ActivityAction::MediaUpdate, ActivityAction::MediaUpload], array_map(static fn (Activity $activity): ActivityAction => $activity->action, $changes));
		$this->assertSame('Changed alt text on dot.png', (string) $changes[0]->summary);

		$media->undo(Actor::admin($this->jane), $changes[0]);

		$this->assertSame('', $this->app->container()->make(MediaMetadataStore::class)->find($uploaded->file)->alt);
	}

	public function testPrunesOldChangesAndWhatTheyReplaced(): void
	{
		$this->editor()->update(Actor::admin($this->jane), $this->mine(), new EntryEdit(body: 'The second body.'), $this->revision());

		$this->clock->advance('P31D');
		$this->assertSame([0, 1], $this->log()->prune(), 'What it replaced goes after 30 days.');
		$this->assertFalse($this->log()->latest(self::idFor('_post/2021-01-01.mine.md'))?->canUndo() ?? true);

		$this->clock->advance('P60D');
		$this->assertSame([1, 0], $this->log()->prune(), 'The change goes after 90.');
	}
}
