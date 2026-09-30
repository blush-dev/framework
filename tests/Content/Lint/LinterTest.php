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
use Blush\Content\Lint\VariantCheck;
use Blush\Content\Schema\Severity;
use Blush\Content\Schema\Violation;
use Blush\Tests\Content\BuildsContentSite;

#[CoversClass(Linter::class)]
#[CoversClass(LintReport::class)]
#[CoversClass(VariantCheck::class)]
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

	public function testChecksTermParents(): void
	{
		$this->contentConfig(['types' => ['topic' => ['kind' => 'taxonomy', 'folder' => 'topics', 'hierarchical' => true]]]);
		$this->entry('topics/web.md', 'title: Web');
		$this->entry('topics/css.md', "title: CSS\nparent: web");
		$this->entry('topics/self.md', "title: Self\nparent: self");
		$this->entry('topics/orphan.md', "title: Orphan\nparent: missing");
		$this->entry('topics/a.md', "title: A\nparent: b");
		$this->entry('topics/b.md', "title: B\nparent: a");

		$linter   = $this->site()->container()->make(Linter::class);
		$messages = self::messages($linter->lint(), Severity::Warning);

		$this->assertSame(['topics/a.md', 'topics/b.md', 'topics/orphan.md', 'topics/self.md'], array_keys($messages));
		$this->assertSame(['error parent: makes a loop: a → b → a.'], $messages['topics/a.md']);
		$this->assertSame(['error parent: makes a loop: b → a → b.'], $messages['topics/b.md']);
		$this->assertSame(['warning parent: "missing" has no topic entry; the term is shown at the top level.'], $messages['topics/orphan.md']);
		$this->assertSame(['error parent: names the term itself; a term can\'t be its own parent.'], $messages['topics/self.md']);
		$this->assertSame('names the term itself; a term can\'t be its own parent.', $linter->lintFile('topics/self.md')[0]->message ?? null);
	}

	public function testWarnsOfPagesAnotherRouteAnswers(): void
	{
		$this->contentConfig(['types' => ['movie' => ['kind' => 'collection', 'dateArchives' => 'year'], 'film' => ['kind' => 'collection', 'urls' => false]]]);
		$this->entry('movie/2024.md', 'title: Shadowed by the year archive');
		$this->entry('movie/about/index.md', 'title: Shadowed by the single route');
		$this->entry('movie/about/team.md', 'title: Under the prefix, but no route answers');
		$this->entry('film/index.md', 'title: No addresses to clash with');
		$this->entry('_movie/jaws.md', 'title: Jaws');

		$messages = self::messages($this->site()->container()->make(Linter::class)->lint(), Severity::Warning);

		$this->assertSame(['movie/2024.md', 'movie/about/index.md'], array_keys($messages));
		$this->assertSame(['warning file: is at /movie/2024, but the movie.collection.year route answers there, so the page can\'t be reached; move the page or change the type\'s prefix.'], $messages['movie/2024.md'] ?? null);
		$this->assertStringContainsString('the movie.single route answers there', $messages['movie/about/index.md'][0] ?? '');
	}

	public function testCleanContentHasNoErrors(): void
	{
		$this->entry('index.md', 'title: Home');

		$report = $this->site()->container()->make(Linter::class)->lint();

		$this->assertFalse($report->hasErrors());
		$this->assertSame([], $report->violations());
		$this->assertSame(1, $report->checked);
	}

	public function testFlagsVariantsAComponentDoesntHave(): void
	{
		$this->entry('notes.md', 'title: Notes', <<<'MD'
			:::callout{variant=warning}
			Fine.
			:::

			:::callout[Heads up]{variant=shiny .wide}
			Not a callout variant.
			:::

			::button[Go]{url=/go variant=primary}

			Press :kbd[Ctrl]{variant=big} and :app/unknown[x]{variant=any}.

			```md
			:::callout{variant=nope}
			```

			:::callout{variant=default}
			Default is always there.
			:::
			MD);

		$violations = $this->site()->container()->make(Linter::class)->lintFile('notes.md');
		$messages   = array_map(static fn (Violation $violation): string => "{$violation->severity->value} {$violation}", $violations);

		$this->assertSame([
			'warning body: line 5: blush/callout has no "shiny" variant under the active theme, so it renders as Default (it has info, tip, warning, danger).',
			'warning body: line 9: blush/button has no "primary" variant under the active theme, so it renders as Default (it has secondary).',
			'warning body: line 11: blush/kbd has no "big" variant under the active theme, so it renders as Default (it has no variants).'
		], $messages);
	}
}
