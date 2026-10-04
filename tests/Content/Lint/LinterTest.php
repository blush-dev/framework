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
use Blush\Content\Lint\FieldSetCheck;
use Blush\Content\Lint\Linter;
use Blush\Content\Lint\LintReport;
use Blush\Content\Lint\VariantCheck;
use Blush\Field\Severity;
use Blush\Field\Violation;
use Blush\Tests\Content\BuildsContentSite;

#[CoversClass(Linter::class)]
#[CoversClass(FieldSetCheck::class)]
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

		$this->assertSame(20, $report->checked);
		$this->assertSame(20, $progress);
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
		$this->assertContains('notice category: "old-posts" has no category entry; a virtual term stands in.', $notices['_posts/2003-04-15.welcome.md']);
		$this->assertContains('notice tag: is not declared by the schema.', $notices['_posts/2008-04-05.spring.md']);
		$this->assertNotContains('notice category: "art" has no category entry; a virtual term stands in.', $notices['_posts/2008-04-05.spring.md']);
	}

	public function testWarnsOfDatesThatArentOnTheCalendar(): void
	{
		$this->standardContent();
		$this->entry('zeros.md', "title: Zeros\npublished: 2019-00-00 00:00:00 -6");
		$this->entry('alias.md', "title: Alias\ndate: 2019-02-30");
		$this->entry('leap.md', "title: Leap\npublished: 2024-02-29\nupdated: 2023-02-29");
		$this->entry('text.md', "title: 2019-00-00");

		$linter   = $this->site()->container()->make(Linter::class);
		$messages = self::messages($linter->lint(), Severity::Notice);

		$this->assertSame(['warning published: "2019-00-00" isn\'t a real date, so it\'s read as 2018-11-30.'], $messages['zeros.md'] ?? null);
		$this->assertContains('warning date: "2019-02-30" isn\'t a real date, so it\'s read as 2019-03-02.', $messages['alias.md'] ?? [], 'An alias is checked under the name it\'s written with.');
		$this->assertSame(['warning updated: "2023-02-29" isn\'t a real date, so it\'s read as 2023-03-01.'], $messages['leap.md'] ?? null, 'A leap day is fine in a leap year.');
		$this->assertArrayNotHasKey('text.md', $messages, 'Only date fields are checked.');
		$this->assertSame('"2019-00-00" isn\'t a real date, so it\'s read as 2018-11-30.', $linter->lintFile('zeros.md')[0]->message ?? null);
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

	public function testReportsOrderPrefixesOutsideCollectionsAndTaxonomies(): void
	{
		$this->standardContent();
		$this->entry('01.services.md', 'title: Services');
		$this->entry('02.work/index.md', 'title: Work');
		$this->entry('02.work/03.design.md', 'title: Design');
		$this->entry('profiles/01.sam.md', 'title: Sam');
		$this->entry('topics/04.music.md', 'title: Music');
		$this->entry('__drafts/2023-08-01.the-last-one.md', 'title: The Last One');
		$this->entry('_05.secret.md', 'title: Secret');

		$linter = $this->site()->container()->make(Linter::class);
		$errors = self::messages($linter->lint(), Severity::Error);

		$this->assertSame(['error file: has an order prefix, which only collections and taxonomies use; pages don\'t. Rename it services.md.'], $errors['01.services.md'] ?? null);
		$this->assertStringEndsWith('Rename it work/index.md.', $errors['02.work/index.md'][0] ?? '');
		$this->assertStringEndsWith('Rename it work/design.md.', $errors['02.work/03.design.md'][0] ?? '', 'Its folder too (D-409).');
		$this->assertStringContainsString('profiles don\'t. Rename it profiles/sam.md.', $errors['profiles/01.sam.md'][0] ?? '');
		$this->assertArrayNotHasKey('topics/04.music.md', $errors, 'A taxonomy may order its terms.');
		$this->assertArrayNotHasKey('_posts/2003-04-15.welcome.md', $errors, 'So may a collection.');
		$this->assertArrayNotHasKey('__drafts/2023-08-01.the-last-one.md', $errors, 'A hidden folder isn\'t checked.');
		$this->assertArrayNotHasKey('_05.secret.md', $errors, 'Nor a hidden file.');
		$this->assertStringStartsWith('has an order prefix', $linter->lintFile('01.services.md')[0]->message ?? '');
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

	public function testWarnsAboutCreditedAuthorsWithoutEntries(): void
	{
		$this->standardContent();
		$this->entry('_posts/2009-01-01.credits.md', "title: Credits\npublished: 2009-01-01\nauthors: [justintadlock, Sam Smith]\ncategory: missing");

		$messages = self::messages($this->site()->container()->make(Linter::class)->lint(), Severity::Notice);

		$this->assertSame([
			'notice category: "missing" has no category entry; a virtual term stands in.',
			'warning authors: "sam-smith" has no profile entry, so it has no public name or bio; add one.'
		], $messages['_posts/2009-01-01.credits.md'] ?? null, 'Profiles are people (D-351), so a missing one is a warning; a missing term is a notice.');
	}

	public function testNotesFieldSetTargetsThatAttachToNothing(): void
	{
		$this->standardContent();
		$this->writeTemporaryFile('user/data/fields/shop.yaml', "targets: [type:post, type:product]\nfields: [{name: price, type: number}]\n");
		$this->writeTemporaryFile('user/data/fields/nav.yaml', "targets: [menu:primary]\nfields: [{name: badge}]\n");

		$report  = $this->site()->container()->make(Linter::class)->lint();
		$notices = self::messages($report, Severity::Notice);

		$this->assertSame(['notice targets: "shop" names type:product, which the site doesn\'t have, so it isn\'t used there.'], $notices['user/data/fields/shop.yaml'] ?? null);
		$this->assertSame(['notice targets: "nav" names menu:primary, but fields can\'t attach to a "menu" yet.'], $notices['user/data/fields/nav.yaml'] ?? null);
		$this->assertFalse($report->hasErrors());
	}

	public function testReportsASetThatDoesntFitAMediaKind(): void
	{
		$this->standardContent();
		$this->writeTemporaryFile('user/data/fields/strict.yaml', "targets: [media:image]\nfields: [{name: alt}]\n");

		$errors = self::messages($this->site()->container()->make(Linter::class)->lint(), Severity::Error);

		$this->assertStringStartsWith('error targets: media:image can\'t take field set "strict"', $errors['user/data/fields/strict.yaml'][0] ?? '');
	}

	public function testReportsTwoSetsUsingOneSettingName(): void
	{
		$this->standardContent();
		$this->writeTemporaryFile('user/data/fields/brand.yaml', "targets: [settings:general]\nfields: [{name: tagline}]\n");
		$this->writeTemporaryFile('user/data/fields/reading.yaml', "targets: [settings:reading]\nfields: [{name: tagline}]\n");
		$this->writeTemporaryFile('user/data/fields/both.yaml', "targets: [settings:general, settings:search]\nfields: [{name: motto}]\n");

		$errors = self::messages($this->site()->container()->make(Linter::class)->lint(), Severity::Error);

		$this->assertSame(['error targets: Field sets "brand" and "reading" both add a "tagline" setting; settings share one store, so rename one.'], $errors['user/data/fields/reading.yaml'] ?? null);
		$this->assertArrayNotHasKey('user/data/fields/both.yaml', $errors, 'One set on two screens is one setting.');
	}

	public function testNotesASlotItsKindDoesntOffer(): void
	{
		$this->standardContent();
		$this->writeTemporaryFile('user/data/fields/gallery.yaml', "targets: [type:post]\nslot: hero\nfields: [{name: photos, type: list}]\n");

		$report = $this->site()->container()->make(Linter::class)->lint();

		$this->assertContains('notice slot: "gallery" has the slot "hero", which content types don\'t offer, so it\'s in "details".', self::messages($report, Severity::Notice)['user/data/fields/gallery.yaml'] ?? []);
		$this->assertFalse($report->hasErrors());
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
