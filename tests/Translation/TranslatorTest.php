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

	private function translator(string $locale = 'en_US'): Translator
	{
		$registry = new DataParserRegistry();
		new DataParserRegistrar($registry)->register();

		$container = new ServiceContainer();
		$container->singleton(YamlParser::class, SymfonyYamlParser::class);

		$root = $this->temporaryDirectory();

		return new Translator(new DataLoader($registry, $container), $locale, ['theme' => ["{$root}/child", "{$root}/parent"]]);
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

	public function testWithDirectoriesReplacesADomain(): void
	{
		$this->writeTemporaryFile('other/en.json', '{"hello": "Other"}');
		$this->writeTemporaryFile('parent/en.json', '{"hello": "Parent"}');

		$translator = $this->translator();
		$other      = $translator->withDirectories('theme', [$this->temporaryDirectory() . '/other']);

		$this->assertSame('Parent', $translator->translate('hello', [], 'theme'));
		$this->assertSame('Other', $other->translate('hello', [], 'theme'));
		$this->assertSame('en_US', $other->locale());
	}

	public function testBrokenCatalogsThrow(): void
	{
		$this->writeTemporaryFile('parent/en.json', '{broken');

		$this->expectException(InvalidData::class);

		$this->translator()->translate('hello', [], 'theme');
	}
}
