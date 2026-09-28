<?php

/**
 * Layout component tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Component;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Content\Schema\Field;
use Blush\Http\Kernel;
use Blush\Http\Request;
use Blush\Tests\BootsScratchSite;
use Blush\Component\ComponentContent;
use Blush\Component\ComponentRegistry;
use Blush\Component\Layout\CssLength;
use Blush\Component\Layout\Grid;
use Blush\Component\Layout\Group;
use Blush\Component\Layout\GroupTag;
use Blush\Component\Layout\Row;
use Blush\Component\Layout\RowAlign;
use Blush\Component\Layout\RowJustify;

#[CoversClass(CssLength::class)]
#[CoversClass(Grid::class)]
#[CoversClass(Group::class)]
#[CoversClass(GroupTag::class)]
#[CoversClass(Row::class)]
#[CoversClass(RowAlign::class)]
#[CoversClass(RowJustify::class)]
final class LayoutComponentsTest extends TestCase
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

	public function testTheirPropsComeFromTheirClasses(): void
	{
		$app = $this->scratchApplication();
		$app->boot();

		$registry = $app->container()->make(ComponentRegistry::class);
		$names    = static fn (string $name): array => array_map(static fn (Field $field): string => $field->name, $registry->get($name)?->props() ?? []);

		$this->assertSame(['tag', 'label'], $names('group'));
		$this->assertSame(['columns', 'min', 'gap'], $names('grid'));
		$this->assertSame(['justify', 'align', 'wrap', 'gap'], $names('row'));
		$this->assertSame(ComponentContent::Blocks, $registry->get('row')?->content());
	}

	public function testTheyRenderFromMarkdownInAnyTheme(): void
	{
		$this->writeTemporaryFile('user/themes/bare/theme.json', '{"name": "Bare"}');
		$this->writeTemporaryFile('user/content/index.md', <<<'MD'
			---
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
			MD);

		$app = $this->scratchApplication(['APP_ENV' => 'development']);
		$app->boot();

		$html = (string) $app->container()->make(Kernel::class)->handle(Request::create('/?theme=bare'))->getBody();

		$this->assertStringContainsString('<div class="component-grid features" id="features" style="--layout-gap: 2rem; display: grid;', $html);
		$this->assertStringContainsString('minmax(max(min(10rem, 100%), calc((100% - (3 - 1) * var(--layout-gap, 1.5rem)) / 3)), 1fr));">', $html);
		$this->assertStringContainsString("<div class=\"component-group\">\n<p>One.</p></div>", $html);
		$this->assertStringContainsString("<section class=\"component-group\" aria-label=\"Two\">\n<p>Two.</p></section>", $html);
		$this->assertStringContainsString('<div class="component-row" style="display: flex; flex-wrap: wrap; gap: var(--layout-gap, 1rem); justify-content: space-between; align-items: center;">', $html);
		$this->assertStringContainsString("<div class=\"component-group\">\n<p>Plain.</p></div>", $html);
	}
}
