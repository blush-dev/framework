<?php

/**
 * Site workflow tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Workflow;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Auth\Accounts;
use Blush\Content\Entries;
use Blush\Content\Entry\Entry;
use Blush\Core\Application;
use Blush\Tests\Content\BuildsContentSite;
use Blush\Workflow\Status;
use Blush\Workflow\StatusRepair;
use Blush\Workflow\Statuses;
use Blush\Workflow\Tone;
use Blush\Workflow\Workflow;
use Blush\Workflow\Workflows;
use Blush\Workflow\WorkflowTables;

#[CoversClass(Statuses::class)]
#[CoversClass(Workflows::class)]
#[CoversClass(WorkflowTables::class)]
#[CoversClass(StatusRepair::class)]
final class SiteWorkflowsTest extends TestCase
{
	use BuildsContentSite;

	private Application $app;

	protected function setUp(): void
	{
		$this->contentConfig(['types' => ['post' => ['kind' => 'collection'], 'note' => ['kind' => 'collection', 'workflow' => 'notes']]]);
		$this->entry('_post/waiting.md', "title: Waiting\nstatus: acme_review");
		$this->entry('_post/lost.md', "title: Lost\nstatus: vanished\ndate: 2026-05-01 09:00:00");
		$this->entry('_post/live.md', "title: Live\ndate: 2026-05-01 09:00:00");

		$this->writeTemporaryFile('user/data/statuses/published.json', '{"label": "Live", "tone": "accent", "live": false}');
		$this->writeTemporaryFile('user/data/statuses/acme_review.json', '{"label": "In Review", "plural": "Reviewing", "tone": "warn"}');
		$this->writeTemporaryFile('user/data/statuses/trash.json', '{"label": "Bin"}');
		$this->writeTemporaryFile('user/data/workflows/standard.json', json_encode([
			'label'       => 'Reviewed',
			'statuses'    => ['draft', 'acme_review', 'published'],
			'initial'     => 'draft',
			'transitions' => [
				['name' => 'submit', 'label' => 'Submit for Review', 'from' => ['draft'], 'to' => 'acme_review'],
				['name' => 'publish', 'label' => 'Publish', 'from' => ['acme_review'], 'to' => 'published', 'capability' => false]
			]
		], JSON_THROW_ON_ERROR));
		$this->writeTemporaryFile('user/data/workflows/notes.json', '{"statuses": ["draft", "nowhere"], "initial": "draft"}');

		$this->app = $this->site();
	}

	private function statuses(): Statuses
	{
		return $this->app->container()->make(Statuses::class);
	}

	private function workflows(): Workflows
	{
		return $this->app->container()->make(Workflows::class);
	}

	private function find(string $path): Entry
	{
		$entry = $this->app->container()->make(Entries::class)->find(self::idFor($path));
		$this->assertNotNull($entry);

		return $entry;
	}

	public function testTheSiteRelabelsAStatusButNeverChangesWhatItDoes(): void
	{
		$published = $this->statuses()->get(Status::PUBLISHED);

		$this->assertSame('Live', (string) $published->label);
		$this->assertSame('Live', (string) $published->plural);
		$this->assertSame(Tone::Accent, $published->tone);
		$this->assertTrue($published->live, 'Only its labels and tone change.');
	}

	public function testTheSiteAddsStatusesOfItsOwnAndLeavesOutCoresOwnNames(): void
	{
		$review = $this->statuses()->find('acme_review');

		$this->assertNotNull($review);
		$this->assertSame('Reviewing', (string) $review->plural);
		$this->assertFalse($review->live);
		$this->assertSame('Trash', (string) $this->statuses()->get(Status::TRASH)->label);
		$this->assertStringContainsString('"trash" status is left out', implode(' ', $this->statuses()->problems()));
	}

	public function testTheSiteReplacesAWorkflowByNameAndLeavesOutOneThatDoesntFit(): void
	{
		$standard = $this->workflows()->for('post');

		$this->assertSame(Workflow::STANDARD, $standard->name);
		$this->assertSame('Reviewed', (string) $standard->label);
		$this->assertSame(['submit', 'publish'], array_map(static fn ($transition): string => $transition->name, $standard->transitions));
		$this->assertSame('Reviewed', (string) $this->workflows()->for('note')->label, 'A workflow left out leaves its types on standard.');
		$this->assertStringContainsString('"notes" workflow is left out', implode(' ', $this->workflows()->problems()));
	}

	public function testRepairMovesEntriesOutsideTheirWorkflowToItsFirstStatus(): void
	{
		$repair = $this->app->container()->make(StatusRepair::class);
		$owner  = $this->app->container()->make(Accounts::class)->create('olive', 'a long enough password', ['owner'], email: 'olive@example.test');
		$lost   = self::idFor('_post/lost.md');

		$this->assertSame([$lost => ['type' => 'post', 'status' => 'vanished', 'initial' => Status::DRAFT]], $repair->report());

		$done = $repair->repair($owner, [$lost]);

		$this->assertSame([$lost], $done['changed']);
		$this->assertSame(Status::DRAFT, $this->find('_post/lost.md')->status->name);
		$this->assertSame([], $repair->report());
	}

	public function testOnlySiteHealthsAccountsRepair(): void
	{
		$repair = $this->app->container()->make(StatusRepair::class);
		$editor = $this->app->container()->make(Accounts::class)->create('eve', 'a long enough password', ['editor'], email: 'eve@example.test');
		$lost   = self::idFor('_post/lost.md');

		$this->assertArrayHasKey($lost, $repair->repair($editor, [$lost])['failed']);
		$this->assertSame('vanished', $this->find('_post/lost.md')->status->name);
	}
}
