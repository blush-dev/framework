<?php

/**
 * Extension text tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Data\DataLoader;
use Blush\Extension\ExtensionText;
use Blush\Icon\IconPack;
use Blush\Tests\TemporaryDirectory;
use Blush\Theme\ThemeManifest;
use Blush\Translation\CurrentLocale;
use Blush\Translation\Translator;

#[CoversClass(ExtensionText::class)]
final class ExtensionTextTest extends TestCase
{
	use TemporaryDirectory;

	private function theme(): ThemeManifest
	{
		return ThemeManifest::fromArray($this->temporaryDirectory() . '/extensions/acme/paper', [
			'name'        => 'acme/paper',
			'label'       => 'Paper',
			'description' => 'A quiet theme.',
			'menus'       => [
				'primary' => 'Primary',
				'social'  => ['label' => 'Social links', 'depth' => 1],
				'footer'  => ['depth' => 1]
			],
			'settings'    => [
				'wide'   => ['type' => 'bool', 'label' => 'Wide layout', 'description' => 'Fills the window.'],
				'accent' => ['type' => 'text']
			]
		]);
	}

	/**
	 * Text for a translator that knows no extension's catalog, so each
	 * is read from the extension's own folder.
	 */
	private function text(string $locale): ExtensionText
	{
		return new ExtensionText(new Translator(new DataLoader(), 'en_US'), new CurrentLocale($locale));
	}

	public function testAManifestsTextIsTranslatedFromItsOwnCatalog(): void
	{
		$this->writeTemporaryFile('extensions/acme/paper/lang/fr.json', json_encode([
			'@@locale' => 'fr',
			'@@domain' => 'acme/paper',
			'manifest' => [
				'label'    => 'Papier',
				'menus'    => ['primary' => 'Principal'],
				'settings' => ['wide' => ['label' => 'Mise en page large']]
			]
		], JSON_THROW_ON_ERROR));

		$theme = $this->theme();
		$text  = $this->text('fr_FR');

		$this->assertSame('Papier', (string) $text->label($theme));
		$this->assertSame('A quiet theme.', (string) $text->description($theme), 'Without a translation, the manifest\'s text.');
		$this->assertSame('Principal', (string) $text->menu($theme, 'primary', 'Primary'));
		$this->assertSame('Social links', (string) $text->menu($theme, 'social', 'Social links'));
		$this->assertSame('Mise en page large', (string) $text->setting($theme, 'wide', 'label', 'Wide layout'));
		$this->assertSame('Fills the window.', (string) $text->setting($theme, 'wide', 'description', 'Fills the window.'));
		$this->assertSame('Paper', (string) $this->text('en_US')->label($theme), 'In the manifest\'s language, its text.');
		$this->assertSame('"Papier"', json_encode($text->label($theme)), 'It reaches JSON as text.');
	}

	public function testEveryKindIsTranslated(): void
	{
		$path = $this->temporaryDirectory() . '/extensions/acme/marks';
		$pack = new IconPack('acme/marks', 'Marks', 'marks', $path, description: 'Brand marks.');

		$this->writeTemporaryFile('extensions/acme/marks/lang/fr.json', '{"manifest": {"label": "Marques", "description": "Des logos."}}');

		$this->assertSame('Marques', (string) $this->text('fr')->label($pack));
		$this->assertSame('Des logos.', (string) $this->text('fr')->description($pack));
	}

	public function testTheManifestsMessagesAreExtractedWithNotesForTranslators(): void
	{
		$messages = ExtensionText::extracted($this->theme());

		$this->assertSame([
			'manifest.label',
			'manifest.description',
			'manifest.menus.primary',
			'manifest.menus.social',
			'manifest.settings.wide.label',
			'manifest.settings.wide.description'
		], array_keys($messages), 'Empty text is left out.');
		$this->assertSame('Social links', $messages['manifest.menus.social']->text);
		$this->assertSame('The theme\'s name', $messages['manifest.label']->context);
		$this->assertSame('theme.json:1', $messages['manifest.label']->where());

		$pack = new IconPack('acme/marks', 'Marks', 'marks', '/tmp/marks');

		$this->assertSame(['manifest.label'], array_keys(ExtensionText::extracted($pack)));
		$this->assertSame('The icon pack\'s name', ExtensionText::extracted($pack)['manifest.label']->context);
	}
}
