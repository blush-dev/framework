<?php

/**
 * Translator tests.
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
use Blush\Container\ServiceContainer;
use Blush\Data\DataLoader;
use Blush\Data\DataParserRegistrar;
use Blush\Data\DataParserRegistry;
use Blush\Data\InvalidData;
use Blush\Data\YamlParser;
use Blush\Data\SymfonyYamlParser;
use Blush\Tests\TemporaryDirectory;
use Blush\Translation\Translator;

#[CoversClass(Translator::class)]
final class TranslatorTest extends TestCase
{
	use TemporaryDirectory;

	private function loader(): DataLoader
	{
		$registry = new DataParserRegistry();
		new DataParserRegistrar($registry)->register();

		$container = new ServiceContainer();
		$container->singleton(YamlParser::class, SymfonyYamlParser::class);

		return new DataLoader($registry, $container);
	}

	private function translator(string $locale = 'en_US'): Translator
	{
		$root = $this->temporaryDirectory();

		return new Translator($this->loader(), $locale, ['theme' => ["{$root}/child", "{$root}/parent"]]);
	}

	public function testChildCatalogsWinKeyByKey(): void
	{
		$this->writeTemporaryFile('parent/en.json', '{"hello": "Hello", "bye": "Goodbye", "nav": {"next": "Next"}}');
		$this->writeTemporaryFile('child/en.yaml', "hello: Howdy\n");

		$translator = $this->translator();

		$this->assertSame('Howdy', $translator->translate('hello', [], 'theme'));
		$this->assertSame('Goodbye', $translator->translate('bye', [], 'theme'));
		$this->assertSame('Next', $translator->translate('nav.next', [], 'theme'));
		$this->assertTrue($translator->has('nav.next', 'theme'));
		$this->assertFalse($translator->has('nav', 'theme'));
	}

	public function testFormatsIcuMessages(): void
	{
		$this->writeTemporaryFile('parent/en.json', '{"minutes": "{n, plural, one {# minute} other {# minutes}} to read", "hi": "Hi, {name}!"}');

		$translator = $this->translator();

		$this->assertSame('1 minute to read', $translator->translate('minutes', ['n' => 1], 'theme'));
		$this->assertSame('5 minutes to read', $translator->translate('minutes', ['n' => 5], 'theme'));
		$this->assertSame('Hi, Ada!', $translator->translate('hi', ['name' => 'Ada'], 'theme'));
	}

	public function testFallsBackThroughLocales(): void
	{
		$this->writeTemporaryFile('parent/fr_CA.json', '{"color": "Couleur (CA)"}');
		$this->writeTemporaryFile('parent/fr.json', '{"color": "Couleur", "gray": "Gris"}');
		$this->writeTemporaryFile('parent/en.json', '{"color": "Color", "gray": "Gray", "only": "English"}');

		$translator = $this->translator();

		$this->assertSame('Couleur (CA)', $translator->translate('color', [], 'theme', 'fr-CA'));
		$this->assertSame('Gris', $translator->translate('gray', [], 'theme', 'fr_CA'));
		$this->assertSame('English', $translator->translate('only', [], 'theme', 'fr_CA'));
		$this->assertSame('Color', $translator->translate('color', [], 'theme'));
		$this->assertSame(['fr_CA', 'fr', 'en_US', 'en'], Translator::fallbacks('fr-ca', 'en_US'));
		$this->assertSame(['de', 'fr_FR', 'fr', 'en'], Translator::fallbacks('de', 'fr_FR'), 'English last, always.');
	}

	public function testFallsBackToEnglishLast(): void
	{
		$this->writeTemporaryFile('parent/en.json', '{"hello": "Hello", "lines": {"one": "One"}}');
		$this->writeTemporaryFile('parent/fr.json', '{"bye": "Au revoir"}');

		$translator = $this->translator('fr_FR');

		$this->assertSame('Au revoir', $translator->translate('bye', [], 'theme'));
		$this->assertSame('Hello', $translator->translate('hello', [], 'theme'), 'A package with only English shows English, not its key.');
		$this->assertSame(['one' => 'One'], $translator->group('lines', [], 'theme'));
	}

	public function testGroupsComeWholeFromOneLocale(): void
	{
		$this->writeTemporaryFile('parent/fr.json', '{"lines": {"cafe": "Au café, {name}."}}');
		$this->writeTemporaryFile('parent/en.json', '{"lines": {"coffee": "Coffee, {name}.", "love": "Love."}, "linesmith": "Not in the group"}');
		$this->writeTemporaryFile('child/en.json', '{"lines": {"mixtape": "A mixtape."}}');

		$translator = $this->translator();

		$this->assertSame(['mixtape' => 'A mixtape.', 'coffee' => 'Coffee, Ada.', 'love' => 'Love.'], $translator->group('lines', ['name' => 'Ada'], 'theme'), 'A child theme adds to the group.');
		$this->assertSame(['cafe' => 'Au café, Ada.'], $translator->group('lines', ['name' => 'Ada'], 'theme', 'fr'), 'No English lines mixed in.');
		$this->assertSame([], $translator->group('missing', [], 'theme'));
	}

	public function testMetadataKeysArentMessages(): void
	{
		$this->writeTemporaryFile('parent/en.json', '{"@@locale": "en", "@@domain": "acme/hello", "hello": "Hello"}');
		$this->writeTemporaryFile('child/en.yaml', "'@@locale': en\nhello: Howdy\n");

		$translator = $this->translator();

		$this->assertSame('Howdy', $translator->translate('hello', [], 'theme'));
		$this->assertFalse($translator->has('@@locale', 'theme'));
		$this->assertFalse($translator->has('@@domain', 'theme'));
	}

	public function testMissingKeysComeBackAsTheKey(): void
	{
		$translator = $this->translator();

		$this->assertSame('missing.key', $translator->translate('missing.key', [], 'theme'));
		$this->assertSame('Hi {name}', $translator->translate('Hi {name}', [], 'nowhere'));
		$this->assertSame('Hi Ada', $translator->translate('Hi {name}', ['name' => 'Ada'], 'nowhere'));
	}

	public function testWithDomainsAddsAndReplacesDomains(): void
	{
		$this->writeTemporaryFile('other/en.json', '{"hello": "Other"}');
		$this->writeTemporaryFile('parent/en.json', '{"hello": "Parent"}');

		$translator = $this->translator();
		$other      = $translator->withDomains(['theme' => [$this->temporaryDirectory() . '/other']], ['other' => 'acme/other']);

		$this->assertSame('Parent', $translator->translate('hello', [], 'theme'));
		$this->assertSame('Other', $other->translate('hello', [], 'theme'));
		$this->assertSame('en_US', $other->locale());
		$this->assertSame('acme/other', $other->domainOf('other'));
		$this->assertSame('app', $other->domainOf('app'), 'A namespace without an extension is its own domain.');
	}

	public function testListsOfDomainsAreSearchedInOrder(): void
	{
		$this->writeTemporaryFile('child/en.json', '{"hello": "Howdy", "lines": {"one": "Child one"}}');
		$this->writeTemporaryFile('parent/en.json', '{"hello": "Hello", "bye": "Bye", "lines": {"one": "Parent one", "two": "Parent two"}}');
		$this->writeTemporaryFile('parent/fr.json', '{"bye": "Au revoir"}');

		$root       = $this->temporaryDirectory();
		$translator = $this->translator()->withDomains(['acme/child' => ["{$root}/child"], 'acme/parent' => ["{$root}/parent"]]);
		$chain      = ['acme/child', 'acme/parent'];

		$this->assertSame('Howdy', $translator->translate('hello', [], $chain));
		$this->assertSame('Bye', $translator->translate('bye', [], $chain));
		$this->assertSame('Au revoir', $translator->translate('bye', [], $chain, 'fr'), 'The locale comes first, then the domains.');
		$this->assertSame(['one' => 'Child one', 'two' => 'Parent two'], $translator->group('lines', [], $chain), 'A child adds to its parent\'s group.');
		$this->assertTrue($translator->has('bye', $chain));
	}

	public function testOverridesInUserLangWin(): void
	{
		$this->writeTemporaryFile('parent/en.json', '{"hello": "Hello", "bye": "Bye", "lines": {"one": "One", "two": "Two"}}');
		$this->writeTemporaryFile('parent/fr.json', '{"hello": "Bonjour"}');
		$this->writeTemporaryFile('blush/en.json', '{"hello": "Framework"}');
		$this->writeTemporaryFile('user/lang/en/extensions/acme/hello.json', '{"@@locale": "en", "@@domain": "acme/hello", "hello": "Hi there", "lines": {"mine": "Mine"}}');
		$this->writeTemporaryFile('user/lang/en/blush.json', '{"hello": "Site framework"}');

		$root       = $this->temporaryDirectory();
		$translator = new Translator($this->loader(), 'en_US', ['acme/hello' => ["{$root}/parent"], 'blush' => ["{$root}/blush"]], "{$root}/user/lang");

		$this->assertSame('Hi there', $translator->translate('hello', [], 'acme/hello'));
		$this->assertSame('Bye', $translator->translate('bye', [], 'acme/hello'), 'Keys the override doesn\'t have come from the package.');
		$this->assertSame('Bonjour', $translator->translate('hello', [], 'acme/hello', 'fr'), 'The package\'s French beats the override\'s English.');
		$this->assertSame(['mine' => 'Mine'], $translator->group('lines', [], 'acme/hello'), 'An override\'s group replaces the package\'s.');
		$this->assertSame('Site framework', $translator->translate('hello', [], 'blush'));
		$this->assertSame("{$root}/user/lang/fr/extensions/acme/hello", $translator->overridePath('acme/hello', 'fr'));
		$this->assertSame("{$root}/user/lang/fr/app", $translator->overridePath('app', 'fr'));
	}

	public function testBrokenCatalogsThrow(): void
	{
		$this->writeTemporaryFile('parent/en.json', '{broken');

		$this->expectException(InvalidData::class);

		$this->translator()->translate('hello', [], 'theme');
	}
}
