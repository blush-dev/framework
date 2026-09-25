<?php

/**
 * Linter tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Content\Lint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Content\Lint\Linter;
use Blush\Content\Lint\LintReport;
use Blush\Content\Schema\Severity;
use Blush\Content\Schema\Violation;
use Blush\Tests\Content\BuildsContentSite;

#[CoversClass(Linter::class)]
#[CoversClass(LintReport::class)]
final class LinterTest extends TestCase
{
	use BuildsContentSite;

	/**
	 * Returns a report's violations as strings, by path.
	 *
	 * @return array<string, list<string>>
	 */
	private static function messages(LintReport $report, Severity $threshold): array
	{
		return array_map(
			static fn (array $violations): array => array_map(
				static fn (Violation $violation): string => "{$violation->severity->value} {$violation}",
				$violations
			),
			$report->violations($threshold)
		);
	}

	public function testReportsProblemsBySeverity(): void
	{
		$this->standardContent();
		$this->entry('about.md', 'title: Old About');
		$this->entry('broken.md', "title: [unclosed\n");
		$this->entry('bad-date.md', "title: Bad\npublished: tomorrow\ncollection:\n  number: lots");

		$progress = 0;
		$report   = $this->site()->container()->make(Linter::class)->lint(static function () use (&$progress): void {
			$progress++;
		});

		$this->assertSame(18, $report->checked);
		$this->assertSame(18, $progress);
		$this->assertTrue($report->hasErrors());
		$this->assertSame(3, $report->count(Severity::Error));
		$this->assertSame(1, $report->count(Severity::Warning));

		$errors = self::messages($report, Severity::Warning);

		$this->assertSame(['about.md', 'bad-date.md', 'broken.md'], array_keys($errors));
		$this->assertSame(['warning file: is the same entry as about/index.md, which wins.'], $errors['about.md']);
		$this->assertStringStartsWith('error published: must be a date', $errors['bad-date.md'][0]);
		$this->assertSame('error collection: Query argument "number" must be a whole number.', $errors['bad-date.md'][1]);
		$this->assertStringStartsWith('error file: Invalid front matter', $errors['broken.md'][0]);

		$notices = self::messages($report, Severity::Notice);

		$this->assertContains('notice date: is read as "published".', $notices['_posts/2003-04-15.welcome.md']);
		$this->assertContains('notice authors: "justintadlock" has no author entry; a virtual term stands in.', $notices['_posts/2003-04-15.welcome.md']);
		$this->assertContains('notice category: "old-posts" has no category entry; a virtual term stands in.', $notices['_posts/2003-04-15.welcome.md']);
		$this->assertContains('notice tag: is not declared by the schema.', $notices['_posts/2008-04-05.spring.md']);
		$this->assertNotContains('notice category: "art" has no category entry; a virtual term stands in.', $notices['_posts/2008-04-05.spring.md']);
	}

	public function testCleanContentHasNoErrors(): void
	{
		$this->entry('index.md', 'title: Home');

		$report = $this->site()->container()->make(Linter::class)->lint();

		$this->assertFalse($report->hasErrors());
		$this->assertSame([], $report->violations());
		$this->assertSame(1, $report->checked);
	}
}
