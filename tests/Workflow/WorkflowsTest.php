<?php

/**
 * Workflow tests.
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
use Blush\Activity\Actor;
use Blush\Auth\Account;
use Blush\Auth\Accounts;
use Blush\Auth\Capabilities;
use Blush\Auth\RoleEditor;
use Blush\Content\Editing\EditRefused;
use Blush\Content\Editing\EntryEditor;
use Blush\Content\Editing\NewEntry;
use Blush\Content\Editing\Refusal;
use Blush\Content\Entries;
use Blush\Content\Entry\Entry;
use Blush\Core\Application;
use Blush\Event\Listener\ListenerRegistry;
use Blush\Tests\Content\BuildsContentSite;
use Blush\Tests\Fixtures\Workflow\ReviewStatuses;
use Blush\Tests\Fixtures\Workflow\ReviewWorkflows;
use Blush\Workflow\CoreWorkflows;
use Blush\Workflow\InvalidWorkflow;
use Blush\Workflow\StatusChanged;
use Blush\Workflow\StatusChanging;
use Blush\Workflow\StatusSource;
use Blush\Workflow\Transition;
use Blush\Workflow\Transitions;
use Blush\Workflow\Workflow;
use Blush\Workflow\Workflows;
use Blush\Workflow\WorkflowSource;

#[CoversClass(Workflow::class)]
#[CoversClass(Workflows::class)]
#[CoversClass(CoreWorkflows::class)]
#[CoversClass(Transition::class)]
#[CoversClass(Transitions::class)]
#[CoversClass(StatusChanging::class)]
#[CoversClass(StatusChanged::class)]
#[CoversClass(EntryEditor::class)]
final class WorkflowsTest extends TestCase
{
	use BuildsContentSite;

	private Application $app;

	private Account $editor;

	private Account $submitter;

	protected function setUp(): void
	{
		$this->contentConfig(['types' => [
			'post' => ['kind' => 'collection', 'workflow' => 'editorial'],
			'note' => ['kind' => 'collection', 'workflow' => 'nowhere']
		]]);
		$this->entry('_post/rough.md', "title: Rough\nstatus: draft");
		$this->entry('_post/waiting.md', "title: Waiting\nstatus: in_review");
		$this->entry('_post/featured.md', "title: Featured\nstatus: featured\ndate: 2026-05-02 09:00:00");
		$this->entry('_note/jotted.md', "title: Jotted\nstatus: draft");

		$this->app = $this->site();
		$this->app->container()->singleton(ReviewStatuses::class);
		$this->app->container()->tag(ReviewStatuses::class, StatusSource::TAG);
		$this->app->container()->singleton(ReviewWorkflows::class);
		$this->app->container()->tag(ReviewWorkflows::class, WorkflowSource::TAG);

		$this->app->container()->make(RoleEditor::class)->create('submitter', 'Submitter', '', [
			'content.*.create',
			'content.*.edit',
			'content.*.edit.others',
			'content.post.transition.submit'
		]);

		$accounts        = $this->app->container()->make(Accounts::class);
		$this->editor    = $accounts->create('eve', 'a long enough password', ['editor'], email: 'eve@example.test');
		$this->submitter = $accounts->create('sam', 'a long enough password', ['submitter'], email: 'sam@example.test');
	}

	private function workflows(): Workflows
	{
		return $this->app->container()->make(Workflows::class);
	}

	private function editor(): EntryEditor
	{
		return $this->app->container()->make(EntryEditor::class);
	}

	private function post(string $slug): Entry
	{
		$entry = $this->app->container()->make(Entries::class)->find(self::idFor("_post/{$slug}.md"));
		$this->assertNotNull($entry);

		return $entry;
	}

	/**
	 * Returns transitions' names.
	 *
	 * @param  list<Transition> $transitions
	 * @return list<string>
	 */
	private static function names(array $transitions): array
	{
		return array_map(static fn (Transition $transition): string => $transition->name, $transitions);
	}

	/**
	 * Returns the refusal a call throws.
	 */
	private function refused(callable $call): EditRefused
	{
		try {
			$call();
		} catch (EditRefused $refused) {
			return $refused;
		}

		$this->fail('The change wasn\'t refused.');
	}

	public function testResolvesEachTypesWorkflow(): void
	{
		$workflows = $this->workflows();

		$this->assertSame('editorial', $workflows->for('post')->name);
		$this->assertSame(Workflow::STANDARD, $workflows->for('page')->name);
		$this->assertSame(Workflow::STANDARD, $workflows->for('note')->name);
		$this->assertSame(['post'], $workflows->typesUsing('editorial'));
	}

	public function testLeavesOutWorkflowsThatDontFitAndKeepsTheFirstByName(): void
	{
		$workflows = $this->workflows();

		$this->assertNull($workflows->find('broken'));
		$this->assertSame('Standard', (string) $workflows->standard()->label);
		$this->assertCount(1, $workflows->clashes());
		$this->assertSame(Workflow::STANDARD, $workflows->clashes()[0]->name);

		$problems = implode(' ', $workflows->problems());

		$this->assertStringContainsString('"broken" workflow is left out', $problems);
		$this->assertStringContainsString('missing', $problems);
		$this->assertStringContainsString('"note" type names a workflow there isn\'t', $problems);
	}

	public function testOffersTransitionsFromAStatusAndFromInitialOutsideIt(): void
	{
		$editorial = $this->workflows()->find('editorial');
		$this->assertNotNull($editorial);

		$this->assertSame(['publish', 'reject'], self::names($editorial->from('in_review')));
		$this->assertSame(['submit'], self::names($editorial->from('featured')));
		$this->assertSame('publish', $editorial->between('in_review', 'published')?->name);
		$this->assertNull($editorial->between('draft', 'published'));
	}

	public function testRefusesInvalidNames(): void
	{
		$this->expectException(InvalidWorkflow::class);

		new Transition('Submit');
	}

	public function testRegistersTransitionCapabilitiesOfTheirOwn(): void
	{
		$capabilities = $this->app->container()->make(Capabilities::class);

		$this->assertTrue($capabilities->has('content.post.transition.submit'));
		$this->assertTrue($capabilities->has('content.*.transition.reject'));
		$this->assertFalse($capabilities->has('content.post.transition.publish'));
		$this->assertFalse($capabilities->has('content.page.transition.submit'));
	}

	public function testListsTheTransitionsAnAccountMayMake(): void
	{
		$transitions = $this->app->container()->make(Transitions::class);

		$this->assertSame(['submit'], self::names($transitions->allowed($this->submitter, $this->post('rough'), 'draft')));
		$this->assertSame([], self::names($transitions->allowed($this->submitter, $this->post('waiting'), 'in_review')));
		$this->assertSame(['publish', 'reject'], self::names($transitions->allowed($this->editor, $this->post('waiting'), 'in_review')));
		$this->assertSame(['submit', Transitions::RETURN], self::names($transitions->allowed($this->editor, $this->post('featured'), 'featured')));
	}

	public function testMovesAnEntryByItsWorkflowsTransitions(): void
	{
		$submitted = $this->editor()->setStatus(Actor::admin($this->submitter), $this->post('rough'), 'in_review');

		$this->assertSame('in_review', $submitted->status->name);

		$date = $this->editor()->transition(Actor::admin($this->editor), $this->post('rough'), 'publish');

		$this->assertTrue($date->isPublished());
	}

	public function testRefusesAMoveWithNoTransitionOrWithoutItsCapability(): void
	{
		$none = $this->refused(fn () => $this->editor()->setStatus(Actor::admin($this->editor), $this->post('rough'), 'published'));

		$this->assertSame(Refusal::Unprocessable, $none->refusal);
		$this->assertSame('status', $none->field);

		$publish = $this->refused(fn () => $this->editor()->setStatus(Actor::admin($this->submitter), $this->post('waiting'), 'published'));

		$this->assertSame(Refusal::Forbidden, $publish->refusal);

		$reject = $this->refused(fn () => $this->editor()->transition(Actor::admin($this->submitter), $this->post('waiting'), 'reject'));

		$this->assertSame(Refusal::Forbidden, $reject->refusal);
		$this->assertSame('in_review', $this->post('waiting')->status->name);
	}

	public function testStartsNewEntriesAtInitialAndChecksAnotherStatusAsATransition(): void
	{
		$new = $this->editor()->create(Actor::admin($this->submitter), new NewEntry('post', 'Fresh'));

		$this->assertSame('draft', $new->status->name);

		$submitted = $this->editor()->create(Actor::admin($this->submitter), new NewEntry('post', 'Ready', status: 'in_review'));

		$this->assertSame('in_review', $submitted->status->name);

		$refused = $this->refused(fn () => $this->editor()->create(Actor::admin($this->editor), new NewEntry('post', 'Straight', status: 'published')));

		$this->assertSame(Refusal::Unprocessable, $refused->refusal);
	}

	public function testListenersHearChangesAndMayRefuseThem(): void
	{
		$heard     = [];
		$listeners = $this->app->container()->make(ListenerRegistry::class);

		$listeners->listen(StatusChanged::class, static function (StatusChanged $event) use (&$heard): void {
			$heard[] = "{$event->from}>{$event->to}:" . ($event->transition->name ?? '-');
		});

		$this->editor()->setStatus(Actor::admin($this->submitter), $this->post('rough'), 'in_review');
		$this->editor()->trash(Actor::admin($this->editor), $this->post('rough'), $this->app->container()->make(Entries::class)->editable($this->post('rough'))->version);

		$this->assertSame(['draft>in_review:submit', 'in_review>trash:-'], $heard);

		$listeners->listen(StatusChanging::class, static function (StatusChanging $event): void {
			$event->refuse('A reviewer has to sign off first.');
		});

		$refused = $this->refused(fn () => $this->editor()->transition(Actor::admin($this->editor), $this->post('waiting'), 'publish'));

		$this->assertSame('A reviewer has to sign off first.', $refused->getMessage());
		$this->assertSame('in_review', $this->post('waiting')->status->name);
	}
}
