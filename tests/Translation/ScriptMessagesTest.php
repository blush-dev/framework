<?php

/**
 * Script messages tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Translation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Data\DataLoader;
use Blush\Extension\ExtensionCatalogs;
use Blush\Extension\ExtensionException;
use Blush\Field\Violation;
use Blush\Tests\TemporaryDirectory;
use Blush\Translation\Catalog;
use Blush\Translation\CatalogCheck;
use Blush\Translation\ExtractedMessage;
use Blush\Translation\MessageExtractor;
use Blush\Translation\ScriptMessages;
use Blush\Translation\SourceCatalog;
use Blush\Translation\Translator;

#[CoversClass(ScriptMessages::class)]
#[CoversClass(MessageExtractor::class)]
#[CoversClass(ExtensionCatalogs::class)]
#[CoversClass(SourceCatalog::class)]
#[CoversClass(CatalogCheck::class)]
#[CoversClass(Translator::class)]
final class ScriptMessagesTest extends TestCase
{
	use TemporaryDirectory;

	private function scripts(): ScriptMessages
	{
		return new ScriptMessages(new MessageExtractor(), new SourceCatalog());
	}

	private function extension(): string
	{
		$this->writeTemporaryFile('acme/resources/site/gallery.ts', <<<'TS'
			import { messages } from 'blush/messages';

			const t = messages('acme/gallery');

			// t('commented.out', 'Never read');
			/* t('block.comment', 'Never read') */
			button.label = t('gallery.next', 'Next');
			count.textContent = t(
				'gallery.count',
				'{count, plural, one {# photo} other {# photos}}',
				{ count: photos.length }
			);
			t('gallery.quote', "It's \"here\"é");
			t('keyed.only');
			t(`gallery.${kind}`, 'Kind');
			t('gallery.next', 'Following');
			const other = messages('acme/other');
			TS);
		$this->writeTemporaryFile('acme/resources/site/types.d.ts', "t('types.key', 'Never read');\n");
		$this->writeTemporaryFile('acme/resources/admin/Screen.vue', <<<'VUE'
			<script setup lang="ts">
			import { messages } from 'blush/messages';
			// t('vue.comment', 'Never read')
			const t = messages('acme/gallery');
			const title = t('screen.title', 'Gallery');
			</script>

			<template>
				<!-- {{ t('html.comment', 'Never read') }} -->
				<p>Don't break: {{ t('screen.empty', 'No photos yet.') }}</p>
				<button :aria-label="t('screen.add', 'Add a Photo')">+</button>
			</template>
			VUE);
		$this->writeTemporaryFile('acme/resources/not a catalog/x.ts', "t('odd.key', 'Odd');\n");

		return $this->temporaryDirectory() . '/acme';
	}

	public function testReadsScriptsByTheirFoldersCatalog(): void
	{
		[$catalogs, $problems] = $this->scripts()->extract($this->extension(), [], 'acme/gallery');

		$this->assertSame(['admin', 'site'], array_keys($catalogs), 'Each folder under resources/ named as a catalog is one.');
		$this->assertEqualsCanonicalizing(['gallery.next', 'gallery.count', 'gallery.quote'], array_keys($catalogs['site']));
		$this->assertSame('{count, plural, one {# photo} other {# photos}}', $catalogs['site']['gallery.count']->text);
		$this->assertSame('It\'s "here"é', $catalogs['site']['gallery.quote']->text);
		$this->assertSame('resources/site/gallery.ts:7', $catalogs['site']['gallery.next']->where());
		$this->assertSame('resources/site/gallery.ts:8', $catalogs['site']['gallery.count']->where());
		$this->assertEqualsCanonicalizing(['screen.title', 'screen.empty', 'screen.add'], array_keys($catalogs['admin']));
		$this->assertSame('resources/admin/Screen.vue:10', $catalogs['admin']['screen.empty']->where());

		$messages = array_map(static fn (Violation $violation): string => (string) $violation, $problems);

		$this->assertEqualsCanonicalizing([
			'resources/site/gallery.ts:17: It reads the "acme/other" domain\'s messages; an extension\'s scripts read their own, "acme/gallery".',
			'resources/site/gallery.ts:15: Its key isn\'t a plain string, so it can\'t be collected; write it out, or choose with an ICU select.',
			'resources/site/gallery.ts:16: "gallery.next" has other text at resources/site/gallery.ts:7; one key is one message.'
		], $messages);
	}

	public function testAManifestNamesItsCatalogsFolders(): void
	{
		$root = $this->extension();

		[$catalogs, $problems] = $this->scripts()->extract($root, ['ui' => ['resources/site', 'resources/admin'], 'gone' => ['resources/gone']], 'acme/gallery');

		$this->assertSame(['ui', 'gone'], array_keys($catalogs));
		$this->assertContains('screen.title', array_keys($catalogs['ui']));
		$this->assertContains('gallery.next', array_keys($catalogs['ui']));
		$this->assertContains('catalogs: "gone" names the "resources/gone" folder, which doesn\'t exist.', array_map(static fn (Violation $violation): string => (string) $violation, $problems));
	}

	public function testManifestCatalogsAreChecked(): void
	{
		$this->assertSame(['site' => ['resources/site']], ExtensionCatalogs::fromManifest(['site' => ['/resources/site/']]));
		$this->assertSame([], ExtensionCatalogs::fromManifest(null));

		$this->expectException(ExtensionException::class);

		ExtensionCatalogs::fromManifest(['site' => ['../outside']]);
	}

	public function testNamedCatalogsAreWrittenAndCompared(): void
	{
		$root    = $this->extension();
		$catalog = new SourceCatalog();
		$file    = SourceCatalog::path("{$root}/lang", catalog: 'site');

		[$catalogs] = $this->scripts()->extract($root, [], 'acme/gallery');

		$catalog->write($catalogs['site'], $file, 'acme/gallery', named: 'site');

		$written = json_decode((string) file_get_contents($file), true);

		$this->assertIsArray($written);
		$this->assertSame(['en', 'acme/gallery', 'site'], [$written[Catalog::LOCALE], $written[Catalog::DOMAIN], $written[Catalog::CATALOG]]);
		$this->assertFalse(array_any($this->scripts()->check($root, [], 'acme/gallery', "{$root}/lang"), static fn (Violation $violation): bool => str_contains((string) $violation->message, 'reads otherwise')));

		$this->writeTemporaryFile('acme/lang/site/en.json', (string) json_encode([...$written, 'gallery' => ['next' => 'Onward']]));

		$problems = array_map(static fn (Violation $violation): string => (string) $violation->message, $this->scripts()->check($root, [], 'acme/gallery', "{$root}/lang"));

		$this->assertContains('"gallery.next" reads otherwise in lang/site/en.json; run lang:extract.', $problems);
	}

	public function testNamedCatalogsSayWhichTheyAre(): void
	{
		$this->writeTemporaryFile('lang/en.json', '{"@@locale": "en", "@@domain": "acme/gallery"}');
		$this->writeTemporaryFile('lang/site/en.json', '{"@@locale": "en", "@@domain": "acme/gallery", "@@catalog": "admin"}');
		$this->writeTemporaryFile('lang/admin/fr.json', '{"@@locale": "fr", "@@domain": "acme/gallery"}');

		$problems = array_map(
			static fn (Violation $violation): string => (string) $violation,
			new CatalogCheck(new DataLoader())->check($this->temporaryDirectory() . '/lang', 'acme/gallery')
		);

		$this->assertEqualsCanonicalizing([
			'lang/site/en: Its "@@catalog" is "admin", but it\'s in the "site" folder.',
			'lang/admin/fr: Start it with "@@locale": "fr", "@@domain": "acme/gallery", and "@@catalog": "admin", which say what it translates.'
		], $problems);
	}

	public function testANamedCatalogResolvesAsAWhole(): void
	{
		$root = $this->temporaryDirectory();

		$this->writeTemporaryFile('acme/lang/site/en.json', '{"@@catalog": "site", "next": "Next", "back": "Back", "only": "English", "@next": {"context": "A button"}}');
		$this->writeTemporaryFile('acme/lang/site/fr.json', '{"next": "Suivant", "back": "Retour"}');
		$this->writeTemporaryFile('acme/lang/site/fr_CA.json', '{"next": "Prochain"}');
		$this->writeTemporaryFile('acme/lang/fr.json', '{"php": "Seulement PHP"}');
		$this->writeTemporaryFile('user/lang/fr/site/extensions/acme/gallery.json', '{"back": "Revenir"}');
		$this->writeTemporaryFile('user/lang/de/site/extensions/acme/gallery.json', '{"back": "Zurück"}');

		$translator = new Translator(new DataLoader(), 'en_US', ['acme/gallery' => ["{$root}/acme/lang"]], "{$root}/user/lang");

		$this->assertSame(['next' => 'Prochain', 'back' => 'Revenir', 'only' => 'English'], $translator->catalog('acme/gallery', 'site', 'fr_CA'), 'The most specific locale wins, the site\'s override within one, and notes and metadata are left out.');
		$this->assertSame([], $translator->catalog('acme/gallery', 'admin', 'fr'));
		$this->assertSame(['de', 'en', 'fr', 'fr_CA'], $translator->catalogLocales('acme/gallery', 'site'));
	}
}
