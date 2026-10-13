<?php

/**
 * Block tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Block;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Core\Application;
use Blush\Field\Fields\BoolField;
use Blush\Field\Fields\EnumField;
use Blush\Field\Fields\NumberField;
use Blush\Field\Fields\TextField;
use Blush\Http\Kernel;
use Blush\Http\Request;
use Blush\Tests\BootsScratchSite;
use Blush\Tests\WritesThemeViews;
use Blush\Embed\Fetcher;
use Blush\Tests\Fixtures\Embed\FixtureFetcher;
use Blush\Tests\Fixtures\Block\Box;
use Blush\Tests\Fixtures\Block\CoreKey;
use Blush\Tests\Fixtures\Block\OwnView;
use Blush\Tests\Fixtures\Block\Orphan;
use Blush\Tests\Fixtures\Block\Salutation;
use Blush\Tests\Fixtures\Block\Stamp;
use Blush\Tests\Fixtures\Block\Tag;
use Blush\Tests\Fixtures\Block\Tone;
use Blush\Tests\Fixtures\Block\Toned;
use Blush\Block\BlockKind;
use Blush\Support\RegistrationException;
use Blush\Theme\ThemeResolver;
use Blush\Block\Block;
use Blush\Block\BlockContent;
use Blush\Block\BlockDefinition;
use Blush\View\RenderableFactory;
use Blush\Block\BlockName;
use Blush\Block\BlockRegistrar;
use Blush\Block\BlockListing;
use Blush\Block\BlockRegistry;
use Blush\Block\BlockType;
use Blush\Block\Callout;
use Blush\Block\Embed;
use Blush\Block\PendingBlock;
use Blush\Block\RegisteredBlocks;
use Blush\View\ViewContext;
use Blush\View\ViewException;
use Blush\View\ViewFactory;
use Blush\View\ViewFinder;
use Blush\View\ViewNotFound;
use Blush\View\Views;

#[CoversClass(Block::class)]
#[CoversClass(BlockContent::class)]
#[CoversClass(BlockDefinition::class)]
#[CoversClass(RenderableFactory::class)]
#[CoversClass(BlockName::class)]
#[CoversClass(BlockRegistrar::class)]
#[CoversClass(BlockRegistry::class)]
#[CoversClass(BlockType::class)]
#[CoversClass(BlockListing::class)]
#[CoversClass(Embed::class)]
#[CoversClass(PendingBlock::class)]
#[CoversClass(RegisteredBlocks::class)]
#[CoversClass(ViewFinder::class)]
#[CoversClass(Views::class)]
final class BlocksTest extends TestCase
{
	use BootsScratchSite;
	use WritesThemeViews;

	private Application $app;

	private function boot(string $environment = 'production'): Views
	{
		$this->app = $this->scratchApplication(['APP_ENV' => $environment]);
		$this->app->boot();

		$container = $this->app->container();
		$container->instance(Fetcher::class, new FixtureFetcher());

		return $container->make(ViewFactory::class)->forChain($container->make(ThemeResolver::class)->active());
	}

	private function view(string $name, string $code): void
	{
		$this->themeView("{$name}.php", $code);
	}

	public function testNamesAreNamespacedAndOnlyCoreOnesAreShort(): void
	{
		$this->assertSame('core/callout', (string) BlockName::parse('callout'));
		$this->assertSame('core/callout', (string) BlockName::parse('core/callout'));
		$this->assertSame('acme/tabs', (string) BlockName::parse('acme/tabs'));
		$this->assertNull(BlockName::parse('tabs'));
		$this->assertNull(BlockName::parse('acme/tabs/more'));
		$this->assertNull(BlockName::parse('../x'));
		$this->assertNull(BlockName::parse('acme/'));

		$this->assertSame(['blocks/callout', 'blocks/core-callout'], new BlockName('core', 'callout')->views());
		$this->assertSame(['blocks/acme-tabs'], new BlockName('acme', 'tabs')->views());
		$this->assertSame('Pricing table', new BlockName('acme', 'pricing-table')->label());

		// A file name takes the longest namespace that fits.
		$this->assertSame('my-plugin/tabs', (string) BlockName::fromFileName('my-plugin-tabs', ['my', 'my-plugin']));
		$this->assertSame('my/tabs', (string) BlockName::fromFileName('my-tabs', ['my', 'my-plugin']));
		$this->assertSame('core/gallery', (string) BlockName::fromFileName('gallery', []));
		$this->assertSame('core/gallery', (string) BlockName::fromFileName('core-gallery', ['core']));
		$this->assertNull(BlockName::fromFileName('tabs', ['my']));
	}

	public function testOnlyRegisteredBlocksExist(): void
	{
		$this->view('blocks/acme-box', '<div <?= $block->attributes(["data-tone" => $block->prop("tone", "plain")]) ?>><?= $block->content() ?></div>');
		$this->view('page', '<?= $template->block("acme/box", tone: "info")->content("<p>Body</p>") ?>');

		$views = $this->boot();

		$this->assertFalse($views->hasBlock('acme/box'), 'A template alone isn\'t a block (D-532).');
		$this->assertTrue($views->hasBlock('callout'));
		$this->assertTrue($views->hasBlock('core/callout'));

		$this->app->container()->make(BlockRegistry::class)->register('acme/box', Box::class);

		$this->assertTrue($views->hasBlock('acme/box'));
		$this->assertFalse($views->hasBlock('box'));
		$this->assertFalse($views->hasBlock('../x'));
		$this->assertSame('<div class="block-box" data-tone="info"><p>Body</p></div>', $views->render('page'));
		$this->assertSame('<div class="block-box extra" id="b" data-tone="plain"></div>', $views->block('acme/box', ['class' => 'extra', 'id' => 'b'], '', new ViewContext()));
	}

	public function testCoreTemplatesMayUseEitherNameAndTheNearestWins(): void
	{
		// A child theme's blush-callout.php beats the framework's
		// callout.php, whichever name it uses.
		$this->view('blocks/core-callout', 'child: <?= $block->content() ?>');

		$views = $this->boot();

		$this->assertSame('child: hi', $views->block('callout', [], 'hi', new ViewContext()));
		$this->assertSame('child: hi', $views->block('core/callout', [], 'hi', new ViewContext()));
	}

	public function testBackedEnumPropsAreCastAndFallBackToTheirDefault(): void
	{
		$this->view('blocks/acme-toned', '<?= e($block->heading) ?>:<?= e($block->tone->value) ?>:<?= $block->level ?>:<?= $block->open ? "open" : "shut" ?>');

		$views = $this->boot();
		$this->app->container()->make(BlockRegistry::class)->register('acme/toned', Toned::class);

		$this->assertSame('Hi:loud:3:open', $views->block('acme/toned', ['heading' => 'Hi', 'tone' => 'loud', 'level' => '3', 'open' => 'true'], '', new ViewContext()));
		$this->assertSame('Hi:quiet:2:shut', $views->block('acme/toned', ['heading' => 'Hi', 'tone' => 'bogus'], '', new ViewContext()));
		$this->assertSame('Hi:loud:2:shut', $views->block('acme/toned', ['heading' => 'Hi', 'tone' => Tone::Loud], '', new ViewContext()));

		$this->expectException(ViewException::class);
		$views->block('acme/toned', ['heading' => ['not', 'a', 'string']], '', new ViewContext());
	}

	public function testDefinitionsReadPropsAndContentFromTheClass(): void
	{
		$definition = new BlockDefinition(new BlockName('acme', 'toned'), Toned::class);
		$props      = $definition->props();

		$this->assertSame(BlockContent::Blocks, $definition->content());

		// Services and private state aren't props.
		$this->assertSame(['heading', 'tone', 'level', 'open'], array_map(static fn ($field): string => $field->name, $props));
		$this->assertInstanceOf(TextField::class, $props[0]);
		$this->assertTrue($props[0]->required);
		$this->assertInstanceOf(EnumField::class, $props[1]);
		$this->assertSame(['quiet', 'loud'], $props[1]->options);
		$this->assertSame('quiet', $props[1]->default);
		$this->assertInstanceOf(NumberField::class, $props[2]);
		$this->assertTrue($props[2]->integer);
		$this->assertSame(2, $props[2]->default);
		$this->assertInstanceOf(BoolField::class, $props[3]);

		$this->assertSame(BlockKind::Container, $definition->kind());
		$this->assertSame([], new BlockDefinition(new BlockName('acme', 'stamp'), Stamp::class)->variants());
	}

	public function testListsEveryBlock(): void
	{
		$this->view('blocks/acme-note', 'note');
		$this->view('blocks/acme-box', 'not registered');
		$this->view('blocks/cards/post', 'a subfolder is not a block');
		$this->view('blocks/callout', 'child callout');
		$this->view('blocks/core-gallery', 'child gallery');
		$this->view('blocks/loose', 'not named for a block');
		$this->view('blocks/callout-warning', 'a variant\'s template');
		$this->writeTemporaryFile('user/lang/en/acme.json', '{"blocks": {"note": {"label": "Note to self", "description": "Holds a note."}}}');

		$views    = $this->boot();
		$registry = $this->app->container()->make(BlockRegistry::class);
		$registry->register('acme/note', Box::class);
		$registry->register('acme/orphan', Orphan::class);

		$blocks = [];

		foreach ($views->blocks() as $block) {
			$blocks[(string) $block->name] = $block;
		}

		$this->assertSame(['acme/note', 'acme/orphan', 'core/abbr', 'core/audio', 'core/badge', 'core/button', 'core/callout', 'core/cite', 'core/dfn', 'core/embed', 'core/figure', 'core/file', 'core/gallery', 'core/grid', 'core/group', 'core/icon', 'core/ins', 'core/kbd', 'core/menu', 'core/meter', 'core/playlist', 'core/progress', 'core/row', 'core/samp', 'core/small', 'core/stack', 'core/time', 'core/toc', 'core/var', 'core/video'], array_keys($blocks));
		$this->assertTrue($blocks['core/callout']->isCore());
		$this->assertFalse($blocks['acme/note']->isCore());
		$this->assertSame(Embed::class, $blocks['core/embed']->className());
		$this->assertSame(Callout::class, $blocks['core/callout']->className());
		$this->assertSame(Box::class, $blocks['acme/note']->className());
		$this->assertCount(1, $blocks['core/callout']->files, 'Only the site\'s: core blocks render themselves (D-382).');
		$this->assertStringEndsWith('extensions/test/site/views/blocks/callout.php', (string) $blocks['core/callout']->file());
		$this->assertStringEndsWith('extensions/test/site/views/blocks/core-gallery.php', (string) $blocks['core/gallery']->file());
		$this->assertSame([], $blocks['core/meter']->files);
		$this->assertTrue($blocks['core/meter']->rendersItself());
		$this->assertFalse($blocks['core/meter']->isMissingTemplate());
		$this->assertFalse($blocks['acme/orphan']->rendersItself(), 'Its render() can return null.');

		// Text comes from the namespace's catalog, or is made from the name.
		$this->assertSame('Callout', $blocks['core/callout']->label);
		$this->assertSame('Note to self', $blocks['acme/note']->label);
		$this->assertSame('Holds a note.', $blocks['acme/note']->description);
		$this->assertNull($blocks['acme/orphan']->label);
		$this->assertSame('Orphan', $blocks['acme/orphan']->displayLabel());
		$this->assertSame(['info', 'tip', 'warning', 'danger'], array_map(static fn ($variant): string => $variant->name, $blocks['core/callout']->variants));
		$this->assertSame('Warning', $views->variantText(new BlockName('core', 'callout'), $blocks['core/callout']->variants[2], 'label'));

		$this->assertTrue($blocks['acme/orphan']->isMissingTemplate());
		$this->assertFalse($blocks['acme/note']->isMissingTemplate());

		// A variant's template is neither a block nor a stray file; a
		// template for a block no one registered is.
		$this->assertSame(['acme-box.php', 'loose.php'], array_map(basename(...), $views->strayBlockFiles()));
	}

	public function testPluginBlocksUseTheirOwnCatalogs(): void
	{
		$this->writeTemporaryFile('extensions/acme/alt/theme.json', '{"name": "acme/alt", "label": "Alt", "namespace": "alt"}');
		$this->writeTemporaryFile('extensions/fixture/hello/plugin.json', '{"name": "fixture/hello", "label": "Hello", "namespace": "hello", "provider": "Blush\\\\Tests\\\\Fixtures\\\\Block\\\\OrphanProvider"}');
		$this->writeTemporaryFile('extensions/fixture/hello/lang/en.json', '{"blocks": {"tabs": {"label": "Tabs"}}, "greeting": "Hello, {name}."}');
		$this->writeTemporaryFile('config/theme.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Theme\\ThemeConfig(active: 'acme/alt');\n");
		$this->writeTemporaryFile('config/plugins.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Plugin\\PluginConfig(enabled: ['fixture/hello']);\n");

		$views = $this->boot();

		$this->assertSame('Tabs', $views->blockText(new BlockName('hello', 'tabs'), 'label'));
		$this->assertNull($views->blockText(new BlockName('other', 'tabs'), 'label'));

		// A plugin's block reads its own catalog, by its vendor/name
		// domain (D-451); a theme's catalog doesn't reword it (D-784).
		$this->app->container()->make(BlockRegistry::class)->register('hello/greeting', Salutation::class);

		$this->assertSame('<p>Hello, Ada.</p>', $views->block('hello/greeting', [], '', new ViewContext()));

		$this->writeTemporaryFile('extensions/acme/alt/lang/en.json', '{"greeting": "Howdy, {name}."}');

		$views = $this->boot();
		$this->app->container()->make(BlockRegistry::class)->register('hello/greeting', Salutation::class);

		$this->assertSame('<p>Hello, Ada.</p>', $views->block('hello/greeting', [], '', new ViewContext()), 'A theme\'s key of the same name isn\'t the plugin\'s text.');

		$this->writeTemporaryFile('user/lang/en/extensions/fixture/hello.json', '{"blocks": {"tabs": {"label": "Site tabs"}}, "greeting": "Howdy, {name}."}');

		$views = $this->boot();
		$this->app->container()->make(BlockRegistry::class)->register('hello/greeting', Salutation::class);

		$this->assertSame('Site tabs', $views->blockText(new BlockName('hello', 'tabs'), 'label'), 'The site\'s override wins.');
		$this->assertSame('<p>Howdy, Ada.</p>', $views->block('hello/greeting', [], '', new ViewContext()));
	}

	public function testTextIsInTheDomainOfTheCodeThatWroteIt(): void
	{
		$this->writeTemporaryFile('extensions/acme/alt/theme.json', '{"name": "acme/alt", "label": "Alt", "namespace": "alt"}');
		$this->writeTemporaryFile('extensions/acme/alt/lang/en.json', '{"greeting": "Theme greeting", "progress": {"label": "Theme progress"}}');
		$this->writeTemporaryFile('extensions/acme/alt/views/page.php', '<?= e($messages->t("greeting")) ?>|<?= e($messages->t("theme.only", "Only {n, plural, one {# thing} other {# things}}", n: 2, context: "A note")) ?>');
		$this->writeTemporaryFile('extensions/fixture/hello/plugin.json', '{"name": "fixture/hello", "label": "Hello", "namespace": "hello", "provider": "Blush\\\\Tests\\\\Fixtures\\\\Block\\\\OrphanProvider"}');
		$this->writeTemporaryFile('extensions/fixture/hello/lang/en.json', '{"greeting": "Plugin greeting", "progress": {"label": "Plugin progress"}}');
		$this->writeTemporaryFile('extensions/fixture/hello/views/greet.php', '<?= e($messages->t("greeting")) ?>|<?= e($messages->t("hello.inline", "Inline {name}", name: "Ada")) ?>');
		$this->writeTemporaryFile('config/theme.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Theme\\ThemeConfig(active: 'acme/alt');\n");
		$this->writeTemporaryFile('config/plugins.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Plugin\\PluginConfig(enabled: ['fixture/hello']);\n");

		$views    = $this->boot();
		$root     = $this->temporaryDirectory();
		$registry = $this->app->container()->make(BlockRegistry::class);
		$registry->register('hello/own', OwnView::class);
		$registry->register('hello/core-key', CoreKey::class);

		// A plugin's own template reads the plugin's catalog, not the
		// theme's (D-784).
		$this->assertSame('Plugin greeting|Inline Ada', $views->block('hello/own', ['file' => "{$root}/extensions/fixture/hello/views/greet.php"], '', new ViewContext()));

		// A plugin's class reads its own catalog before core's.
		$this->assertSame('Plugin progress|Brand new, Ada', $views->block('hello/core-key', [], '', new ViewContext()));

		// A theme's template reads the chain, with its text when no
		// catalog has the key.
		$this->assertSame('Theme greeting|Only 2 things', $views->render('page'));

		$this->assertSame(['acme/alt', 'core'], $views->domainsFor("{$root}/extensions/acme/alt/views/page.php"));
		$this->assertSame(['fixture/hello', 'core'], $views->domainsFor("{$root}/extensions/fixture/hello/views/greet.php"));
	}

	public function testCoreBlocksAreRegisteredWithTheirDefinitions(): void
	{
		$this->boot();

		$registry = $this->app->container()->make(BlockRegistry::class);

		$this->assertSame(['core/abbr', 'core/audio', 'core/badge', 'core/button', 'core/callout', 'core/cite', 'core/dfn', 'core/embed', 'core/figure', 'core/file', 'core/gallery', 'core/grid', 'core/group', 'core/icon', 'core/ins', 'core/kbd', 'core/menu', 'core/meter', 'core/playlist', 'core/progress', 'core/row', 'core/samp', 'core/small', 'core/stack', 'core/time', 'core/toc', 'core/var', 'core/video'], array_keys($registry->all()));
		$embed   = $registry->get('embed');
		$callout = $registry->get('callout');

		$this->assertNotNull($embed);
		$this->assertNotNull($callout);
		$this->assertSame(Embed::class, $embed->class);
		$this->assertSame(BlockContent::Text, $embed->content());
		$this->assertSame(['url', 'title', 'alt', 'label'], array_map(static fn ($field): string => $field->name, $embed->props()));
		$this->assertSame(BlockContent::Blocks, $callout->content());
		$this->assertSame(['info', 'tip', 'warning', 'danger'], array_map(static fn ($variant): string => $variant->name, $callout->variants()));
		$this->assertSame('core', $callout->variants()[0]->registrant);
		$this->assertSame(Callout::class, BlockType::Callout->className());
	}

	/**
	 * A block is registered as a container, a leaf, or inline, and
	 * works only that way (D-531).
	 */
	public function testBlocksAreRegisteredAsOneKind(): void
	{
		$registry = new BlockRegistry();
		new BlockRegistrar($registry)->register();

		$kinds = static fn (string $name): ?BlockKind => $registry->get($name)?->kind();

		$this->assertSame(BlockKind::Container, $kinds('gallery'));
		$this->assertSame(BlockKind::Leaf, $kinds('audio'));
		$this->assertSame(BlockKind::Inline, $kinds('button'));
		$this->assertSame(BlockKind::Inline, $kinds('time'));

		$registry->register('acme/tag', Tag::class);
		$registry->register('acme/note', Box::class);
		$this->assertSame(BlockKind::Inline, $kinds('acme/tag'));
		$this->assertSame(BlockKind::Container, $kinds('acme/note'));

		$odd = [
			'text in a container' => new class () extends Block {
				public const BlockContent CONTENT = BlockContent::Text;

				public const ?BlockKind KIND = BlockKind::Container;

				public function render(): null
				{
					return null;
				}
			},
			'blocks in a leaf' => new class () extends Block {
				public const BlockContent CONTENT = BlockContent::Blocks;

				public const ?BlockKind KIND = BlockKind::Leaf;

				public function render(): null
				{
					return null;
				}
			}
		];

		foreach ($odd as $case => $block) {
			try {
				$registry->register('acme/odd', $block::class);
				$this->fail("{$case} should be refused.");
			} catch (RegistrationException $error) {
				$this->assertStringContainsString('only a block that wraps blocks is a container', $error->getMessage());
			}
		}

		// Every block says how it's written (D-534).
		$kindless = new class () extends Block {
			public function render(): null
			{
				return null;
			}
		};

		$this->expectException(RegistrationException::class);
		$this->expectExceptionMessage('must declare its KIND: BlockKind::Container, Leaf, or Inline.');
		$registry->register('acme/kindless', $kindless::class);
	}

	public function testBlocksWorkOnlyAsTheirKind(): void
	{
		$this->writeTemporaryFile('user/content/index.md', <<<'MD'
			---
			id: d680e8a8-54a7-cbad-6d49-0c445cba2eba
			title: Home
			---
			::button[Leaf button]{url=/a}

			Go :button[Inline button]{url=/b} now, at :time[noon]{datetime=12:00}.

			Inline :callout[callout] and :audio[audio]{src=/a.mp3}.

			::callout[Leaf callout]
			MD);

		$this->boot();

		$html = (string) $this->app->container()->make(Kernel::class)->handle(Request::create('/'))->getBody();

		$this->assertStringNotContainsString('Leaf button</span></a>', $html);
		$this->assertStringContainsString('Inline button</span></a>', $html);
		$this->assertStringContainsString('<time class="block-time" datetime="12:00">noon</time>', $html);
		$this->assertStringContainsString('<p>Inline callout and audio.</p>', $html, 'An inline block of another kind is its text.');
		$this->assertStringNotContainsString('block-callout', $html);
		$this->assertStringContainsString('<p>Leaf callout</p>', $html);
	}

	public function testRegistrationEnforcesNamespaces(): void
	{
		$registry = new BlockRegistry(static fn (string $namespace): bool => $namespace === 'nova');
		new BlockRegistrar($registry)->register();

		// A provider may replace a core block, by either name.
		$registry->register('callout', Stamp::class);
		$this->assertSame(Stamp::class, $registry->get('core/callout')?->class);
		$registry->register('acme/note', Box::class);
		$this->assertSame(Box::class, $registry->get('acme/note')?->class);
		$this->assertSame(['core', 'acme'], $registry->namespaces());

		$registry->registerIf('acme/note', Stamp::class);
		$this->assertSame(Box::class, $registry->get('acme/note')?->class);

		$registry->unregister('acme/note');
		$this->assertFalse($registry->isRegistered('acme/note'));

		$cases = [
			'tabs'           => '"tabs" needs its namespace, such as "vendor/tabs"; only core blocks have short names.',
			'core/tabs'      => '"core/tabs" is in the "core" namespace, which only core blocks use.',
			'acme/tabs/more' => '"acme/tabs/more" is not a valid block name.',
			'nova/badge'     => '"nova/badge" is in a theme\'s namespace, and themes can\'t register blocks (D-532): register it from a plugin, or make it a component the theme\'s templates use.'
		];

		foreach ($cases as $name => $message) {
			try {
				$registry->register($name, Stamp::class);
				$this->fail("{$name} should be refused.");
			} catch (RegistrationException $error) {
				$this->assertSame($message, $error->getMessage());
			}
		}

		$this->expectException(RegistrationException::class);
		// @phpstan-ignore argument.type (verifies the runtime guard)
		$registry->register('acme/x', Tone::class);
	}

	public function testThemesCantRegisterBlocks(): void
	{
		$this->writeTemporaryFile('extensions/acme/nova/theme.json', '{"name": "acme/nova", "label": "Nova", "namespace": "nova"}');

		$this->boot();

		$this->expectException(RegistrationException::class);
		$this->expectExceptionMessage('themes can\'t register blocks (D-532)');
		$this->app->container()->make(BlockRegistry::class)->register('nova/badge', Stamp::class);
	}

	public function testUnknownBlocksThrowWhenATemplateAsksForThem(): void
	{
		$views = $this->boot();

		try {
			$views->block('acme/nope', [], '', new ViewContext());
			$this->fail('An unregistered block should throw.');
		} catch (ViewException $error) {
			$this->assertSame('No block "acme/nope" is registered (D-532).', $error->getMessage());
		}

		try {
			$views->block('nope', [], '', new ViewContext());
			$this->fail('A short third-party name should throw.');
		} catch (ViewException $error) {
			$this->assertSame('"nope" isn\'t a core block, so it needs its namespace, such as "acme/nope" (D-171).', $error->getMessage());
		}

		$this->app->container()->make(BlockRegistry::class)->register('acme/note', Box::class);

		$this->expectException(ViewNotFound::class);
		$this->expectExceptionMessage('No view found for: blocks/acme-note.');
		$views->block('acme/note', [], '', new ViewContext());
	}

	public function testBlocksRenderInEntries(): void
	{
		$this->writeTemporaryFile('config/embed.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Embed\\EmbedConfig(enabled: ['vimeo', 'youtube']);\n");
		$this->writeTemporaryFile('user/content/index.md', <<<'MD'
			---
			id: d680e8a8-54a7-cbad-6d49-0c445cba2eba
			title: Home
			---
			:::callout[Heads up]{variant=warning}
			Back up *first*.
			:::

			:::gallery{columns=9 .wide}
			![](/a.jpg) ![](/b.jpg)
			:::

			:::gallery{columns=2 layout=grid}
			![](/c.jpg)
			:::

			:::gallery{layout=masonry}
			![](/d.jpg)
			:::

			:::figure[A photo]{.wide}
			![A lake](/media/p.jpg)
			:::

			:::figure[Visitors]
			| Month | Visitors |
			| ----- | -------- |
			| May   | 1,204    |
			:::

			::embed[My video]{url="https://youtu.be/dQw4w9WgXcQ" title="Rick"}

			::embed{url="https://example.com/talk"}

			::embed[Our launch]{url="https://vimeo.com/76979871"}

			::embed{url="https://youtu.be/dQw4w9WgXcQ"}

			:::callout{variant=bogus}
			Plain note.
			:::

			::unknown[Kept as text]

			::acme/badge[Site badge]{tone=new}

			::acme/box[A template alone]

			:::badge
			A badge isn't a container.
			:::

			Read the :acme/badge[inline]{tone=tip} notes at https://example.com or 10:30.
			MD);
		$this->view('blocks/acme-badge', '<span class="badge badge--<?= attr($block->prop("tone")) ?>"><?= $block->content() ?></span>');
		$this->view('blocks/acme-box', 'box');

		$this->boot();
		$this->app->container()->make(BlockRegistry::class)->register('acme/badge', Tag::class);

		$html = (string) $this->app->container()->make(Kernel::class)->handle(Request::create('/'))->getBody();

		$this->assertStringContainsString("<aside class=\"block-callout block-callout--warning\" role=\"note\">\n\t\t\t<p class=\"block-callout__title\">Heads up</p>\n\t\t<p>Back up <em>first</em>.</p></aside>", $html);
		$this->assertStringContainsString('<div class="block-gallery block-gallery--flex wide" style="--gallery-columns: 6">', $html);
		$this->assertStringContainsString('<div class="block-gallery block-gallery--grid" style="--gallery-columns: 2">', $html);
		$this->assertStringContainsString('<div class="block-gallery block-gallery--masonry" style="--gallery-columns: 3">', $html);
		$this->assertMatchesRegularExpression('#<figure class="block-figure wide">\s*<img src="http://localhost/media/p.jpg" alt="A lake" />\s*<figcaption>A photo</figcaption>#', $html, 'The figure is the container; its image stands alone.');
		$this->assertMatchesRegularExpression('#<figure class="block-figure">\s*<table>.*?</table>\s*<figcaption>Visitors</figcaption>#s', $html, 'A figure wraps anything, such as a table.');
		$this->assertStringContainsString('<iframe class="block-embed__frame" src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ" title="Rick"', $html);
		$this->assertStringContainsString('<p class="block-embed block-embed--link"><a href="https://example.com/talk">https://example.com/talk</a></p>', $html);

		// Without a title, the frame is named by its caption or provider.
		$this->assertStringContainsString('<iframe class="block-embed__frame" src="https://player.vimeo.com/video/76979871?dnt=1" title="Our launch"', $html);
		$this->assertStringContainsString('<iframe class="block-embed__frame" src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ" title="Embedded content from YouTube"', $html);
		// A variant it doesn't have renders as Default.
		$this->assertStringContainsString("<aside class=\"block-callout\" role=\"note\">\n\t\t<p>Plain note.</p></aside>", $html);
		$this->assertStringContainsString('<p>Kept as text</p>', $html);
		$this->assertStringContainsString('<p>Site badge</p>', $html, 'A block works only as the kind it\'s registered as (D-531).');
		$this->assertStringContainsString('<p>A template alone</p>', $html, 'A block no one registered is unknown (D-532).');
		$this->assertStringNotContainsString('block-badge', $html, 'A block works only in the forms it\'s registered for (D-530).');
		$this->assertStringContainsString('Read the <span class="badge badge--tip">inline</span> notes at', $html);
	}

	public function testBlocksUseTheRequestsTheme(): void
	{
		$this->writeTemporaryFile('extensions/acme/alt/theme.json', '{"name": "acme/alt", "label": "Alt", "namespace": "alt"}');
		$this->writeTemporaryFile('extensions/acme/alt/views/blocks/callout.php', 'alt callout: <?= $block->content() ?>');
		$this->writeTemporaryFile('user/content/index.md', "---\nid: d680e8a8-54a7-cbad-6d49-0c445cba2eba\ntitle: Home\n---\n:::callout\nHi\n:::\n");

		$this->boot('development');

		$kernel = $this->app->container()->make(Kernel::class);

		$this->assertStringContainsString('alt callout: <p>Hi</p>', (string) $kernel->handle(Request::create('/?theme=acme/alt'))->getBody());
	}

	public function testBlocksRenderThemselvesUnlessTheChainHasATemplate(): void
	{
		$views    = $this->boot();
		$registry = $this->app->container()->make(BlockRegistry::class);
		$registry->register('acme/stamp', Stamp::class);

		$this->assertSame('<span class="block-stamp">Paid &amp; done</span>', $views->block('acme/stamp', ['text' => 'Paid & done'], '', new ViewContext()));
		$this->assertStringContainsString('<aside class="block-callout"', $views->block('callout', [], '<p>Hi</p>', new ViewContext()), 'A core block renders the framework\'s template.');

		$this->view('blocks/acme-stamp', 'child stamp: <?= e($block->text) ?>');
		$this->view('blocks/callout', 'child callout');

		$views = $this->boot();
		$this->app->container()->make(BlockRegistry::class)->register('acme/stamp', Stamp::class);

		$this->assertSame('child stamp: Paid', $views->block('acme/stamp', ['text' => 'Paid'], '', new ViewContext()), 'A template in the chain wins.');
		$this->assertSame('child callout', $views->block('callout', [], '', new ViewContext()));
	}
}
