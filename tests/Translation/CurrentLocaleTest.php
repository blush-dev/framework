<?php

/**
 * Current locale tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Translation;

use RuntimeException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Data\DataLoader;
use Blush\Tests\TemporaryDirectory;
use Blush\Translation\CurrentLocale;
use Blush\Translation\Messages;
use Blush\Translation\Text;
use Blush\Translation\Translator;
use Blush\View\Escaper;

#[CoversClass(CurrentLocale::class)]
#[CoversClass(Messages::class)]
#[CoversClass(Text::class)]
#[CoversClass(Translator::class)]
#[CoversClass(Escaper::class)]
final class CurrentLocaleTest extends TestCase
{
	use TemporaryDirectory;

	protected function tearDown(): void
	{
		$this->removeTemporaryDirectory();
	}

	private function messages(CurrentLocale $locale): Messages
	{
		$root = $this->temporaryDirectory();

		$this->writeTemporaryFile('hello/lang/fr.json', '{"greeting": "Bonjour, {name}.", "by": "Par {names}"}');

		return new Messages('acme/hello', new Translator(new DataLoader(), 'en_US', [
			Translator::CORE => ["{$root}/core/lang"],
			'acme/hello'     => ["{$root}/hello/lang"]
		]), $locale);
	}

	public function testTextIsTranslatedWhenItsPrintedInTheLanguageOfThen(): void
	{
		$locale   = new CurrentLocale('en_US');
		$greeting = $this->messages($locale)->t('greeting', 'Hello, {name}.', name: 'Ada');

		$this->assertSame('Hello, Ada.', (string) $greeting, 'The site\'s language, with the text in the code.');
		$this->assertSame('Bonjour, Ada.', $locale->in('fr_FR', static fn (): string => (string) $greeting), 'Made before, printed in the page\'s language (D-816).');
		$this->assertSame('en_US', $locale->get(), 'The language before comes back.');
	}

	public function testALocaleComesBackAfterAnError(): void
	{
		$locale = new CurrentLocale('en_US');

		try {
			$locale->in('fr_FR', static fn () => throw new RuntimeException('Nope'));
		} catch (RuntimeException) {
			// Expected.
		}

		$this->assertSame('en_US', $locale->get());

		$locale->in('de_DE', static fn (): null => null);
		$locale->reset();

		$this->assertSame('en_US', $locale->get());
		$this->assertSame('es_ES', $locale->in('es_ES', $locale->get(...)));
		$this->assertSame('en_US', $locale->in('', $locale->get(...)), 'Empty is the site\'s.');
	}

	public function testAnHtmlParameterPrintsAsHtml(): void
	{
		$locale = new CurrentLocale('en_US');
		$by     = $this->messages($locale)->t('by', 'By {names} & co.', names: raw('<a href="/jane">Jane</a>'));

		$this->assertTrue($by->isHtml());
		$this->assertSame('By <a href="/jane">Jane</a> &amp; co.', Escaper::html($by), 'e() keeps the links and escapes the rest.');
		$this->assertSame('Par <a href="/jane">Jane</a>', $locale->in('fr', static fn (): string => Escaper::html($by)));
		$this->assertSame('By &lt;b&gt;', Escaper::html($this->messages($locale)->t('plain', 'By {name}', name: '<b>')), 'Plain text is escaped.');
	}

	public function testTextWithoutATranslatorIsItsOwnText(): void
	{
		$this->assertSame('3 posts', (string) new Messages()->t('posts', '{count, plural, one {# post} other {# posts}}', count: 3));
		$this->assertSame('missing.key', (string) new Messages()->t('missing.key'));
	}
}
