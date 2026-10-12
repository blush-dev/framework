<?php

/**
 * Class domain tests.
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
use Blush\Core\Application;
use Blush\Core\ServiceProvider;
use Blush\Tests\BootsScratchSite;
use Blush\Tests\Fixtures\Translation\Greeter;
use Blush\Theme\ThemeResolver;
use Blush\Translation\ClassDomains;
use Blush\Translation\Translator;
use Blush\View\EntryTemplate;
use Blush\View\ViewFactory;
use Blush\View\Views;

#[CoversClass(ClassDomains::class)]
#[CoversClass(ServiceProvider::class)]
#[CoversClass(Views::class)]
final class ClassDomainsTest extends TestCase
{
	use BootsScratchSite;

	private Application $app;

	private function boot(): Views
	{
		$this->writeTemporaryFile('extensions/fixture/hello/plugin.json', '{"name": "fixture/hello", "label": "Hello", "namespace": "hello", "provider": "Blush\\\\Tests\\\\Fixtures\\\\Translation\\\\HelloProvider"}');
		$this->writeTemporaryFile('extensions/fixture/hello/lang/en.json', '{"greeting": "Howdy, {name}.", "templates": {"hello": {"label": "Hello, From the Catalog"}}}');
		$this->writeTemporaryFile('extensions/fixture/hello/lang/fr.json', '{"greeting": "Bonjour, {name}."}');
		$this->writeTemporaryFile('config/plugins.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Plugin\\PluginConfig(enabled: ['fixture/hello']);\n");

		$this->app = $this->scratchApplication(['APP_ENV' => 'production']);
		$this->app->boot();

		$container = $this->app->container();

		return $container->make(ViewFactory::class)->forChain($container->make(ThemeResolver::class)->active());
	}

	public function testAClassIsInItsExtensionsDomain(): void
	{
		$this->boot();

		$domains = $this->app->container()->make(ClassDomains::class);

		$this->assertSame('fixture/hello', $domains->of(Greeter::class));
		$this->assertSame('fixture/hello', $domains->of('\\' . Greeter::class));
		$this->assertSame(Translator::CORE, $domains->of(self::class));
	}

	public function testAClassAskingForMessagesGetsItsOwn(): void
	{
		$views   = $this->boot();
		$greeter = $this->app->container()->make(Greeter::class);

		$this->assertSame('fixture/hello', $greeter->messages->domain);
		$this->assertSame('Howdy, Ada.', $views->text($greeter->greeting()));
		$this->assertSame('Bonjour, Ada.', $views->text($greeter->greeting(), 'fr_FR'));
	}

	public function testAProvidersTextIsTranslatedWhereItsShown(): void
	{
		$views     = $this->boot();
		$templates = $views->entryTemplates('post');

		$this->assertSame(['single'], array_map(static fn (EntryTemplate $template): string => $template->name, $templates));
		$this->assertSame('Hello, From the Catalog', $views->text($templates[0]->label));
		$this->assertSame('For 2 types.', $views->text($templates[0]->description), 'Without a catalog entry, its own text.');
	}
}
