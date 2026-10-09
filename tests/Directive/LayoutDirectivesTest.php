<?php

/**
 * Layout directive tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Directive;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Field\Field;
use Blush\Http\Kernel;
use Blush\Http\Request;
use Blush\Tests\BootsScratchSite;
use Blush\Directive\DirectiveContent;
use Blush\Directive\DirectiveRegistry;
use Blush\Directive\Layout\CssLength;
use Blush\Directive\Layout\Grid;
use Blush\Directive\Layout\Group;
use Blush\Directive\Layout\Layout;
use Blush\Directive\Layout\LayoutTag;
use Blush\Directive\Layout\Row;
use Blush\Directive\Layout\RowAlign;
use Blush\Directive\Layout\RowJustify;
use Blush\Directive\Layout\Stack;
use Blush\Directive\Layout\StackAlign;

#[CoversClass(CssLength::class)]
#[CoversClass(Grid::class)]
#[CoversClass(Group::class)]
#[CoversClass(Layout::class)]
#[CoversClass(LayoutTag::class)]
#[CoversClass(Row::class)]
#[CoversClass(RowAlign::class)]
#[CoversClass(RowJustify::class)]
#[CoversClass(Stack::class)]
#[CoversClass(StackAlign::class)]
final class LayoutDirectivesTest extends TestCase
{
	use BootsScratchSite;

	public function testOnlyPlainLengthsAreAccepted(): void
	{
		foreach (['0', '12rem', ' 1.5em ', '.5rem', '240px', '30%', '20ch', '50cqi', '10dvw'] as $length) {
			$this->assertSame(trim($length), CssLength::sanitize($length), $length);
		}

		foreach (['', 'auto', '12', '-1rem', '1rem 2rem', 'calc(1rem + 1px)', 'var(--x)', '1rem;color:red', 'url(x)'] as $value) {
			$this->assertNull(CssLength::sanitize($value), $value);
		}
	}

	public function testGridColumnsWrapAtTheirMinimumWidth(): void
	{
		$space = 'var(--layout-gap, 1.5rem)';

		$this->assertSame(
			"display: grid; gap: {$space}; grid-template-columns: repeat(auto-fill, minmax(max(min(12rem, 100%), calc((100% - (2 - 1) * {$space}) / 2)), 1fr));",
			new Grid()->style
		);
		$this->assertStringContainsString('minmax(max(min(20ch, 100%), calc((100% - (4 - 1) * ', new Grid(columns: 4, min: '20ch')->style);
		$this->assertStringContainsString('repeat(3, minmax(0, 1fr))', new Grid(columns: 3, min: '0')->style);
		$this->assertStringContainsString('grid-template-columns: minmax(0, 1fr);', new Grid(columns: 1)->style);
		$this->assertStringContainsString('(12 - 1)', new Grid(columns: 40)->style);
		$this->assertStringStartsWith('--layout-gap: 2rem; display: grid;', new Grid(gap: '2rem')->style);

		// Anything that isn't a plain length falls back.
		$bad = new Grid(min: 'red; background: url(x)', gap: '1rem; color: red');

		$this->assertStringNotContainsString('red', $bad->style);
		$this->assertStringContainsString('min(12rem, 100%)', $bad->style);
	}

	public function testRowsFlexTheirItems(): void
	{
		$this->assertSame(
			'display: flex; flex-wrap: wrap; gap: var(--layout-gap, 1rem); justify-content: flex-start; align-items: center;',
			new Row()->style
		);
		$this->assertSame(
			'--layout-gap: 0.5rem; display: flex; flex-wrap: nowrap; gap: var(--layout-gap, 1rem); justify-content: space-between; align-items: baseline;',
			new Row(RowJustify::Between, RowAlign::Baseline, wrap: false, gap: '0.5rem')->style
		);
		$this->assertSame('flex-end', RowJustify::End->css());
		$this->assertSame('stretch', RowAlign::Stretch->css());
	}

	public function testStacksSpaceTheirItemsInAColumn(): void
	{
		$this->assertSame(
			'display: flex; flex-direction: column; gap: var(--layout-gap, 1rem); align-items: stretch;',
			new Stack()->style
		);
		$this->assertSame(
			'--layout-gap: 2rem; display: flex; flex-direction: column; gap: var(--layout-gap, 1rem); align-items: flex-start;',
			new Stack(StackAlign::Start, gap: '2rem')->style
		);
		$this->assertStringNotContainsString('red', new Stack(gap: '1rem; color: red')->style);
		$this->assertSame('flex-end', StackAlign::End->css());
		$this->assertSame('center', StackAlign::Center->css());
	}

	public function testTheirPropsComeFromTheirClasses(): void
	{
		$app = $this->scratchApplication();
		$app->boot();

		$registry = $app->container()->make(DirectiveRegistry::class);
		$names    = static fn (string $name): array => array_map(static fn (Field $field): string => $field->name, $registry->get($name)?->props() ?? []);

		$this->assertSame(['tag', 'label'], $names('group'));
		$this->assertSame(['columns', 'min', 'gap', 'tag', 'label'], $names('grid'));
		$this->assertSame(['justify', 'align', 'wrap', 'gap', 'tag', 'label'], $names('row'));
		$this->assertSame(DirectiveContent::Blocks, $registry->get('row')?->content());
		$this->assertSame(['align', 'gap', 'tag', 'label'], $names('stack'));
		$this->assertSame(DirectiveContent::Blocks, $registry->get('stack')?->content());
	}

	public function testTheyRenderFromMarkdownInAnyTheme(): void
	{
		$this->writeTemporaryFile('extensions/acme/bare/theme.json', '{"name": "acme/bare", "label": "Bare", "namespace": "bare"}');
		$this->writeTemporaryFile('user/content/index.md', <<<'MD'
			---
			id: d680e8a8-54a7-cbad-6d49-0c445cba2eba
			title: Home
			---
			::::grid{columns=3 min=10rem gap=2rem .features #features}
			:::group
			One.
			:::

			:::group[Two]{tag=section}
			Two.
			:::
			::::

			:::row{justify=between align=bogus}
			[Back](/one)

			[Next](/two)
			:::

			:::group[Not a section]
			Plain.
			:::

			:::grid[Related]{tag=aside columns=1}
			Aside.
			:::

			:::row{tag=section}
			Unnamed.
			:::

			:::stack[Plans]{tag=aside align=center}
			Stacked.
			:::
			MD);

		$app = $this->scratchApplication(['APP_ENV' => 'development']);
		$app->boot();

		$html = (string) $app->container()->make(Kernel::class)->handle(Request::create('/?theme=acme/bare'))->getBody();

		$this->assertStringContainsString('<div class="directive-grid features" id="features" style="--layout-gap: 2rem; display: grid;', $html);
		$this->assertStringContainsString('minmax(max(min(10rem, 100%), calc((100% - (3 - 1) * var(--layout-gap, 1.5rem)) / 3)), 1fr));">', $html);
		$this->assertStringContainsString("<div class=\"directive-group\">\n<p>One.</p></div>", $html);
		$this->assertStringContainsString("<section class=\"directive-group\" aria-label=\"Two\">\n<p>Two.</p></section>", $html);
		$this->assertStringContainsString('<div class="directive-row" style="display: flex; flex-wrap: wrap; gap: var(--layout-gap, 1rem); justify-content: space-between; align-items: center;">', $html);
		$this->assertStringContainsString("<div class=\"directive-group\">\n<p>Plain.</p></div>", $html);
		$this->assertStringContainsString('<aside class="directive-grid" aria-label="Related" style="display: grid;', $html);
		$this->assertStringContainsString("<p>Aside.</p></aside>", $html);
		$this->assertStringContainsString('<section class="directive-row" style="display: flex;', $html);
		$this->assertStringContainsString("<p>Unnamed.</p></section>", $html);
		$this->assertStringContainsString("<aside class=\"directive-stack\" aria-label=\"Plans\" style=\"display: flex; flex-direction: column; gap: var(--layout-gap, 1rem); align-items: center;\">\n<p>Stacked.</p></aside>", $html);
	}
}
