<?php

/**
 * Workflow writer tests.
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
use Blush\Core\Application;
use Blush\Tests\Content\BuildsContentSite;
use Blush\Workflow\CoreWorkflows;
use Blush\Workflow\InvalidStatus;
use Blush\Workflow\InvalidWorkflow;
use Blush\Workflow\Status;
use Blush\Workflow\Tone;
use Blush\Workflow\Workflow;
use Blush\Workflow\Workflows;
use Blush\Workflow\WorkflowTables;
use Blush\Workflow\WorkflowWriter;

#[CoversClass(WorkflowWriter::class)]
#[CoversClass(Workflow::class)]
final class WorkflowWriterTest extends TestCase
{
	use BuildsContentSite;

	private Application $app;

	protected function setUp(): void
	{
		$this->contentConfig(['types' => ['post' => ['kind' => 'collection', 'workflow' => 'review']]]);

		$this->app = $this->site();
	}

	private function writer(): WorkflowWriter
	{
		return $this->app->container()->make(WorkflowWriter::class);
	}

	private function workflows(): Workflows
	{
		return $this->app->container()->make(Workflows::class);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function review(): array
	{
		return [
			'label'       => 'Review',
			'statuses'    => [Status::DRAFT, 'in_review', Status::PUBLISHED],
			'initial'     => Status::DRAFT,
			'transitions' => [
				['name' => 'submit', 'label' => 'Submit for Review', 'from' => [Status::DRAFT], 'to' => 'in_review'],
				['name' => 'publish', 'label' => 'Publish', 'from' => ['in_review'], 'to' => Status::PUBLISHED, 'capability' => false]
			]
		];
	}

	public function testSavesStatusesOfItsOwnAndRelabelsOthersOnly(): void
	{
		$review = $this->writer()->saveStatus('in_review', ['label' => 'In Review', 'tone' => 'warn', 'live' => false]);

		$this->assertSame('In Review', (string) $review->label);
		$this->assertSame(Tone::Warn, $review->tone);

		$live = $this->writer()->saveStatus(Status::PUBLISHED, ['label' => 'Live', 'live' => false]);

		$this->assertSame('Live', (string) $live->label);
		$this->assertTrue($live->live, 'Only its labels change.');
		$this->assertSame(['label' => 'Live'], $this->app->container()->make(WorkflowTables::class)->statuses()->find(Status::PUBLISHED));

		$this->expectException(InvalidStatus::class);

		$this->writer()->saveStatus(Status::TRASH, ['label' => 'Bin']);
	}

	public function testSavesAWorkflowThatFitsAndRefusesOneThatDoesnt(): void
	{
		$this->writer()->saveStatus('in_review', ['label' => 'In Review']);

		$saved = $this->writer()->saveWorkflow('review', self::review());

		$this->assertSame('review', $this->workflows()->for('post')->name);
		$this->assertSame(['submit', 'publish'], array_map(static fn ($transition): string => $transition->name, $saved->transitions));

		$this->expectException(InvalidWorkflow::class);

		$this->writer()->saveWorkflow('broken', ['statuses' => [Status::DRAFT, 'nowhere'], 'initial' => Status::DRAFT]);
	}

	public function testRefusesToDeleteWhatsInUse(): void
	{
		$this->writer()->saveStatus('in_review', ['label' => 'In Review']);
		$this->writer()->saveWorkflow('review', self::review());

		try {
			$this->writer()->deleteStatus('in_review');
			$this->fail('A status a workflow uses was deleted.');
		} catch (InvalidStatus $e) {
			$this->assertStringContainsString('Review', $e->getMessage());
		}

		try {
			$this->writer()->deleteWorkflow('review');
			$this->fail('A workflow a type uses was deleted.');
		} catch (InvalidWorkflow $e) {
			$this->assertStringContainsString('Type Settings', $e->getMessage());
		}
	}

	public function testCustomizesAndRevertsCoresStandard(): void
	{
		$copy = $this->writer()->customize(Workflow::STANDARD);

		$this->assertSame(['source' => 'site', 'over' => CoreWorkflows::class], $this->workflows()->origin(Workflow::STANDARD));

		$this->writer()->saveWorkflow(Workflow::STANDARD, [...$copy->toArray(), 'label' => 'Ours']);

		$this->assertSame('Ours', (string) $this->workflows()->standard()->label);

		$this->writer()->deleteWorkflow(Workflow::STANDARD);

		$this->assertSame('Standard', (string) $this->workflows()->standard()->label);
		$this->assertSame(CoreWorkflows::class, $this->workflows()->origin(Workflow::STANDARD)['source'] ?? null);
	}
}
