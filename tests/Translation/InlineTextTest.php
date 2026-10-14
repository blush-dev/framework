<?php

/**
 * Inline text tests.
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
use Blush\Tests\TemporaryDirectory;
use Blush\Translation\Catalog;
use Blush\Translation\CurrentLocale;
use Blush\Translation\Messages;
use Blush\Translation\Text;
use Blush\Translation\TranslationException;
use Blush\Translation\Translator;

#[CoversClass(Catalog::class)]
#[CoversClass(Messages::class)]
#[CoversClass(Text::class)]
#[CoversClass(Translator::class)]
final class InlineTextTest extends TestCase
{
	use TemporaryDirectory;

	private function translator(string $locale = 'en_US'): Translator
	{
		$root = $this->temporaryDirectory();

		return new Translator(new DataLoader(), $locale, [
			Translator::CORE => ["{$root}/core/lang"],
			'acme/hello'     => ["{$root}/extensions/acme/hello/lang"]
		]);
	}

	public function testTheTextBesideTheKeyIsReadWhenNoCatalogHasIt(): void
	{
		$this->writeTemporaryFile('extensions/acme/hello/lang/fr.json', '{"greeting": "Bonjour, {name}."}');

		$translator = $this->translator();

		$this->assertSame('Hello, Ada.', $translator->translate('greeting', ['name' => 'Ada'], 'acme/hello', null, 'Hello, {name}.'));
		$this->assertSame('Bonjour, Ada.', $translator->translate('greeting', ['name' => 'Ada'], 'acme/hello', 'fr', 'Hello, {name}.'), 'A catalog wins.');
		$this->assertSame('3 posts', $translator->translate('posts', ['count' => 3], 'acme/hello', null, '{count, plural, one {# post} other {# posts}}'));
		$this->assertSame('missing', $translator->translate('missing', [], 'acme/hello'), 'Without text, the key.');
	}

	public function testAMessageIsItsOwnDomainsThenCores(): void
	{
		$this->writeTemporaryFile('core/lang/en.json', '{"progress": {"label": "Progress"}, "shared": "Core\'s"}');
		$this->writeTemporaryFile('extensions/acme/hello/lang/en.json', '{"shared": "Hello\'s"}');

		$translator = $this->translator();

		$this->assertSame('Hello\'s', $translator->text(new Text('shared', 'Shared', 'acme/hello')), 'Its own domain comes before core\'s.');
		$this->assertSame('Progress', $translator->text(new Text('progress.label', null, 'acme/hello')));
		$this->assertSame('Core\'s', $translator->text(new Text('shared')));
		$this->assertSame(['acme/hello', Translator::CORE], Translator::domainsOf('acme/hello'));
		$this->assertSame([Translator::CORE], Translator::domainsOf(Translator::CORE));
	}

	public function testAFilesDomainIsTheExtensionItSitsIn(): void
	{
		$root       = $this->temporaryDirectory();
		$translator = $this->translator();

		$this->assertSame('acme/hello', $translator->domainAt("{$root}/extensions/acme/hello/views/greeting.php"));
		$this->assertSame(Translator::CORE, $translator->domainAt("{$root}/core/views/welcome.php"));
		$this->assertNull($translator->domainAt('/elsewhere/views/page.php'));
	}

	public function testNotesBesideMessagesArentMessages(): void
	{
		$this->writeTemporaryFile('extensions/acme/hello/lang/en.json', '{"actions": {"post": "Post", "@post": {"context": "A button that publishes"}}}');

		$translator = $this->translator();

		$this->assertSame('Post', $translator->translate('actions.post', [], 'acme/hello'));
		$this->assertFalse($translator->has('actions.@post.context', 'acme/hello'));
		$this->assertTrue(Catalog::isAnnotation('@post'));
		$this->assertTrue(Catalog::isAnnotation('@@locale'));
		$this->assertFalse(Catalog::isAnnotation('post'));
	}

	public function testMessagesMakeTextOfTheirDomain(): void
	{
		$text = new Messages('acme/hello')->t('tools.export', 'Export {count} entries', count: 3, context: 'A button');

		$this->assertSame(['tools.export', 'Export {count} entries', 'acme/hello', ['count' => 3], 'A button'], [$text->key, $text->text, $text->domain, $text->params, $text->context]);
		$this->assertSame(Translator::CORE, new Messages()->t('hello')->domain);

		$this->expectException(TranslationException::class);
		new Messages('acme/hello')->t('hello', 'Hello', 3);
	}

	public function testAStoredMessageIsTranslatedWhenItsShown(): void
	{
		$this->writeTemporaryFile('core/lang/fr.json', '{"checked": "{count, plural, one {# fichier vérifié} other {# fichiers vérifiés}} dans {where}.", "media": "la médiathèque"}');

		$translator = $this->translator();
		$english    = new Messages(Translator::CORE, $translator, new CurrentLocale('en_US'));
		$french     = new Messages(Translator::CORE, $translator, new CurrentLocale('fr'));
		$stored     = json_decode((string) json_encode($english->t('checked', '{count, plural, one {# file checked} other {# files checked}} in {where}.', count: 3, where: $english->t('media', 'the library'))->toArray()), true);

		$this->assertSame('3 files checked in the library.', (string) $english->restore($stored));
		$this->assertSame('3 fichiers vérifiés dans la médiathèque.', (string) $french->restore($stored), 'In the reader\'s language, a message among its parameters too.');
		$this->assertSame('Gone, 2.', (string) $french->restore(['key' => 'gone', 'text' => 'Gone, {n}.', 'params' => ['n' => 2]]), 'A key no catalog has shows its text.');
		$this->assertNull($english->restore('A sentence stored before.'));
	}

	public function testAMessageWithHtmlCantBeStored(): void
	{
		$this->expectException(TranslationException::class);
		new Messages()->t('by', 'By {names}', names: raw('<a href="/">Ada</a>'))->toArray();
	}
}
