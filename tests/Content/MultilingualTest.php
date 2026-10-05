<?php

/**
 * Multilingual content tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Content;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Blush\Config\InvalidConfig;
use Blush\Component\Component;
use Blush\Component\ComponentFactory;
use Blush\Component\ComponentRegistry;
use Blush\Component\Slots;
use Blush\Content\ContentRepository;
use Blush\Content\Entry\Entry;
use Blush\Content\Index\ContentIndex;
use Blush\Content\Index\IndexSnapshot;
use Blush\Content\Index\Indexer;
use Blush\Content\Index\IndexRecord;
use Blush\Content\Index\TranslatedKeys;
use Blush\Content\Index\RecordBuilder;
use Blush\Content\Lint\Linter;
use Blush\Content\LocalizedRepository;
use Blush\Content\Query\EntryCollection;
use Blush\Content\Routing\ContentExportUrls;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Type\ContentTypes;
use Blush\Core\AppConfig;
use Blush\Core\Application;
use Blush\Core\Language;
use Blush\Core\Languages;
use Blush\Export\ExportUrl;
use Blush\Http\Kernel;
use Blush\Http\Request;
use Blush\Menu\Link\CollectionLink;
use Blush\Menu\Link\EntryLink;
use Blush\Menu\Link\TermLink;
use Blush\Menu\Menu;
use Blush\Menu\MenuItem;
use Blush\Menu\Menus;
use Blush\Tests\Fixtures\Content\PostTitles;
use Blush\Theme\ThemeResolver;
use Blush\View\Template;
use Blush\View\ViewContext;
use Blush\View\ViewFactory;

#[CoversClass(Language::class)]
#[CoversClass(Languages::class)]
#[CoversClass(RecordBuilder::class)]
#[CoversClass(IndexSnapshot::class)]
#[CoversClass(TranslatedKeys::class)]
#[CoversClass(IndexRecord::class)]
#[CoversClass(ContentUrls::class)]
#[CoversClass(ContentExportUrls::class)]
#[CoversClass(LocalizedRepository::class)]
#[CoversClass(ComponentFactory::class)]
#[CoversClass(Component::class)]
#[CoversClass(EntryLink::class)]
#[CoversClass(TermLink::class)]
#[CoversClass(CollectionLink::class)]
final class MultilingualTest extends TestCase
{
	use BuildsContentSite;

	private Application $app;

	protected function setUp(): void
	{
		$this->standardContent();
		$this->contentConfig([
			'types' => [
				'post' => [
					'path'          => '_posts',
					'collection'    => ['order' => 'desc', 'orderby' => 'published'],
					'date_archives' => true,
					'routing'       => ['prefix' => 'archives']
				],
				'category' => [
					'path'         => 'topics',
					'taxonomy'     => true,
					'term_collect' => 'post'
				]
			],
			'home' => 'post'
		]);
		$this->languages(['fr' => ['locale' => 'fr_FR', 'label' => 'Français'], 'pt-br' => 'pt_BR']);

		$this->entry('about/index.fr.md', "title: À propos\nslug: a-propos");
		$this->entry('about/biography.fr.md', "title: Biographie\nslug: biographie");
		$this->entry('about/team/jane.md', 'title: Jane');
		$this->entry('about/team/jane.fr.md', 'title: Jeanne');
		$this->entry('_posts/2008-04-05.spring.fr.md', "title: Printemps\npublished: 2008-04-05 09:00:00\nslug: printemps\ncategory: art", 'Le printemps est là.');
		$this->entry('_posts/index.fr.md', 'title: Journal');
		$this->entry('topics/art.fr.md', "title: L'art\nslug: lart");

		$this->app = $this->site();
	}

	/**
	 * Writes `config/app.php` with languages.
	 *
	 * @param array<string, mixed> $languages
	 */
	private function languages(array $languages): void
	{
		$source = var_export($languages, true);

		$this->writeTemporaryFile('config/app.php', <<<PHP
			<?php

			declare(strict_types=1);

			return new Blush\Core\AppConfig(timezone: 'America/Chicago', languages: {$source});
			PHP);
	}

	private function get(string $uri): ResponseInterface
	{
		return $this->app->container()->make(Kernel::class)->handle(Request::create($uri));
	}

	private function content(): ContentRepository
	{
		return $this->app->container()->make(ContentRepository::class);
	}

	public function testLanguagesFromConfig(): void
	{
		$languages = Languages::fromArray('en_US', ['fr' => 'fr_FR', 'pt-br' => ['locale' => 'pt_BR', 'label' => 'Português']]);

		$this->assertSame('en', $languages->default->code);
		$this->assertSame(['en', 'fr', 'pt-br'], array_keys($languages->all()));
		$this->assertSame('Français (France)', $languages->find('fr')?->name());
		$brazil = $languages->find('pt-br');

		$this->assertNotNull($brazil);
		$this->assertSame('Português', $brazil->name());
		$this->assertSame('pt-BR', $brazil->tag());
		$this->assertTrue($languages->isOther('fr'));
		$this->assertFalse($languages->isOther('en'));
		$this->assertFalse(Languages::fromArray('en_US', [])->isMultilingual());

		// A listed language with the site's locale is the default.
		$listed = Languages::fromArray('en_GB', ['uk' => 'en_GB', 'fr' => 'fr_FR']);

		$this->assertSame('uk', $listed->default->code);
		$this->assertSame(['uk', 'fr'], array_keys($listed->toArray()));
		$this->assertSame(['fr' => ['locale' => 'fr_FR', 'label' => '']], AppConfig::fromArray(['languages' => ['fr' => 'fr_FR']])->toArray()['languages']);
	}

	public function testInvalidLanguages(): void
	{
		foreach ([['FR' => 'fr_FR'], ['pt_br' => 'pt_BR'], ['en' => 'en_GB'], ['fr' => ['label' => 'Français']], ['fr' => 'not a locale']] as $languages) {
			try {
				Languages::fromArray('en_US', $languages);
				$this->fail('Expected InvalidConfig for ' . json_encode($languages));
			} catch (InvalidConfig) {
				$this->addToAssertionCount(1);
			}
		}

		$this->expectException(InvalidConfig::class);

		AppConfig::fromArray(['languages' => ['fr_FR']]);
	}

	/**
	 * Returns a booted site's index, built first.
	 */
	private function snapshot(?Application $app = null): IndexSnapshot
	{
		$app ??= $this->app;
		$app->container()->make(ContentRepository::class)->query()->count();

		return $app->container()->make(ContentIndex::class)->snapshot();
	}

	public function testSuffixedFilesAreTranslations(): void
	{
		$snapshot = $this->snapshot();
		$about    = $snapshot->record('about/index.fr.md');
		$spring   = $snapshot->record('_posts/2008-04-05.spring.fr.md');

		$this->assertNotNull($about);
		$this->assertSame(['fr', 'fr_FR', 'a-propos', 'a-propos', 'about/index.md', false], [$about->language, $about->locale, $about->slug, $about->key, $about->original, $about->landing]);
		$this->assertNotNull($spring);
		$this->assertSame(['fr', 'printemps', '_posts/2008-04-05.spring.md'], [$spring->language, $spring->key, $spring->original]);
		$this->assertTrue($snapshot->record('_posts/index.fr.md')?->landing);
		$this->assertSame(['en', null], [$snapshot->record('about/index.md')?->language, $snapshot->record('about/index.md')?->original]);

		$this->assertSame('about/index.fr.md', $snapshot->find('fr', 'page', 'a-propos'));
		$this->assertNull($snapshot->find('en', 'page', 'a-propos'));
		$this->assertEquals(['en' => 'about/index.md', 'fr' => 'about/index.fr.md'], $snapshot->translations('about/index.fr.md'));
		$this->assertEquals(['en' => 'about/biography.md', 'fr' => 'about/biography.fr.md'], $snapshot->translations('about/biography.md'));
		$this->assertSame([], $snapshot->translations('_posts/2003-04-15.welcome.md'));
	}

	public function testPlainFilesAndBundlesLinkEitherWay(): void
	{
		$this->entry('contact/index.md', 'title: Contact');
		$this->entry('contact.fr.md', "title: Nous joindre\nslug: nous-joindre");
		$this->entry('team.md', 'title: Team');
		$this->entry('team/index.fr.md', "title: Équipe\nslug: equipe");
		$this->entry('team/jane.md', 'title: Jane');
		$this->entry('team/jane.fr.md', 'title: Jeanne');

		$app      = $this->site();
		$snapshot = $this->snapshot($app);
		$content  = $app->container()->make(ContentRepository::class);
		$urls     = $app->container()->make(ContentUrls::class);

		$this->assertEquals(['en' => 'contact/index.md', 'fr' => 'contact.fr.md'], $snapshot->translations('contact/index.md'));
		$this->assertEquals(['en' => 'team.md', 'fr' => 'team/index.fr.md'], $snapshot->translations('team/index.fr.md'));

		$contact = $content->named('page', 'contact') ?? $this->fail();

		$this->assertSame('Nous joindre', $content->translation($contact, 'fr')?->title);
		$jane = $content->named('page', 'equipe/jane', 'fr') ?? $this->fail();

		$this->assertSame('/fr/equipe/jane', $urls->entry($jane));
		$this->assertSame('Équipe', $content->parent($jane)?->title);

		// A landing page isn't its folder's entry.
		$this->assertSame('_posts/index', IndexRecord::groupOf($snapshot->records['_posts/index.md']));
		$this->assertSame('about', IndexRecord::groupOf($snapshot->records['about/index.fr.md']));
		$this->assertSame('_posts/2008-04-05.spring', IndexRecord::groupOf($snapshot->records['_posts/2008-04-05.spring.fr.md']));
	}

	public function testTheRepositoryKeepsLanguagesApart(): void
	{
		$content = $this->content();
		$about   = $content->named('page', 'about');

		$this->assertNotNull($about);
		$this->assertSame('About', $about->title);
		$this->assertSame('À propos', $content->named('page', 'a-propos', 'fr')?->title);
		$this->assertNull($content->named('page', 'about', 'fr'));
		$this->assertSame(['en', 'fr'], array_keys($content->translations($about)));
		$this->assertSame('a-propos', $content->translation($about, 'fr')?->key);
		$this->assertNull($content->translation($about, 'pt-br'));

		$titles = static fn (EntryCollection $entries): array => array_map(static fn (Entry $entry): string => $entry->title, $entries->all());

		$this->assertNotContains('Printemps', $titles($content->query()->type('post')->limit(null)->get()));
		$this->assertSame(['Printemps'], $titles($content->query()->type('post')->language('fr')->get()));
		$this->assertContains('Printemps', $titles($content->query()->type('post')->anyLanguage()->limit(null)->get()));
	}

	public function testUrlsArePrefixed(): void
	{
		$content = $this->content();
		$urls    = $this->app->container()->make(ContentUrls::class);
		$art     = $content->named('category', 'art');

		$this->assertSame('/fr/a-propos', $urls->entry($content->named('page', 'a-propos', 'fr') ?? $this->fail()));
		$this->assertSame('/fr/a-propos/biographie', $urls->entry($content->named('page', 'a-propos/biographie', 'fr') ?? $this->fail()));
		$this->assertSame('/fr/a-propos/team/jane', $urls->entry($content->named('page', 'a-propos/team/jane', 'fr') ?? $this->fail()));
		$this->assertSame('/fr/archives/printemps', $urls->entry($content->named('post', 'printemps', 'fr') ?? $this->fail()));
		$this->assertSame('/fr', $urls->entry($content->named('post', '', 'fr') ?? $this->fail()));
		$this->assertSame('/fr', $urls->home('fr'));
		$this->assertNotNull($art);
		$this->assertSame('/fr/page/2', $urls->collection($this->app->container()->make(ContentTypes::class)->get('post'), 2, 'fr'));
		$this->assertSame('/fr/topics/lart', $urls->term($art->type, 'art', 1, 'fr'));
		$this->assertSame('/fr/topics/lart', $urls->entry($content->named('category', 'lart', 'fr') ?? $this->fail()));
		$this->assertSame('/topics/art', $urls->term($art->type, 'art'));
		$this->assertSame('/pt-br/topics/art', $urls->term($art->type, 'art', 1, 'pt-br'));
		$this->assertSame('/fr/archives/2008/04', $urls->date($this->app->container()->make(ContentTypes::class)->get('post'), ['year' => 2008, 'month' => 4], 1, 'fr'));
		$this->assertSame('/about', $urls->entry($content->named('page', 'about') ?? $this->fail()));
	}

	public function testLanguageRoutes(): void
	{
		$page = $this->get('/fr/a-propos');
		$body = (string) $page->getBody();

		$this->assertSame(200, $page->getStatusCode());
		$this->assertStringContainsString('À propos', $body);
		$this->assertStringContainsString('<html lang="fr-FR">', $body);

		$this->assertSame(200, $this->get('/about')->getStatusCode());
		$this->assertStringContainsString('<html lang="en-US">', (string) $this->get('/about')->getBody());
		$this->assertSame(200, $this->get('/fr/a-propos/biographie')->getStatusCode());
		$this->assertSame(200, $this->get('/fr/a-propos/team/jane')->getStatusCode());
		$this->assertSame(404, $this->get('/fr/about/biographie')->getStatusCode());
		$this->assertSame(404, $this->get('/fr/about')->getStatusCode());
		$this->assertSame(404, $this->get('/pt-br/a-propos')->getStatusCode());

		$home = (string) $this->get('/fr')->getBody();

		$this->assertStringContainsString('Printemps', $home);
		$this->assertStringNotContainsString('Welcome', $home);
		$this->assertStringContainsString('<html lang="fr-FR">', $home);
		$this->assertStringNotContainsString('Printemps', (string) $this->get('/')->getBody());

		$this->assertSame(200, $this->get('/fr/archives/printemps')->getStatusCode());
		$this->assertSame(404, $this->get('/fr/archives/spring')->getStatusCode());
		$this->assertSame(404, $this->get('/archives/printemps')->getStatusCode());
		$this->assertStringContainsString('Journal', $home);
		$this->assertStringContainsString('Printemps', (string) $this->get('/fr/archives/2008/04')->getBody());
		$this->assertStringContainsString('>Avril 2008</h1>', (string) $this->get('/fr/archives/2008/04')->getBody());
		$this->assertStringContainsString('>April 2008</h1>', (string) $this->get('/archives/2008/04')->getBody());
		$this->assertSame(404, $this->get('/fr/archives/2003')->getStatusCode());

		$term = $this->get('/fr/topics/lart');

		$this->assertSame(200, $term->getStatusCode());
		$this->assertStringContainsString('Printemps', (string) $term->getBody());
		$this->assertSame('/fr/topics/lart', $this->get('/fr/topics/art')->getHeaderLine('Location'));
		$this->assertStringNotContainsString('Printemps', (string) $this->get('/topics/art')->getBody());
	}

	public function testComponentsFollowThePageLanguage(): void
	{
		$container = $this->app->container();
		$container->make(ComponentRegistry::class)->register('app/post-titles', PostTitles::class);

		$views    = $container->make(ViewFactory::class)->forChain($container->make(ThemeResolver::class)->active());
		$content  = $this->content();
		$french   = $container->make(ViewFactory::class)->context($views, $content->named('page', 'a-propos', 'fr'));
		$english  = $container->make(ViewFactory::class)->context($views, $content->named('page', 'about'));
		$listing  = $container->make(ViewFactory::class)->context($views, null, '/fr', 'fr');

		$this->assertSame(['fr', '', 'fr'], [$french->language, $english->language, $listing->language]);
		$this->assertSame('Printemps | L\'art', $views->component('app/post-titles', [], '', new Slots(), $french));
		$this->assertSame('Printemps | L\'art', $views->component('app/post-titles', [], '', new Slots(), $listing));
		$this->assertStringNotContainsString('Printemps', $views->component('app/post-titles', [], '', new Slots(), $english));
		$this->assertStringEndsWith('| Art', $views->component('app/post-titles', [], '', new Slots(), $english));

		$localized = new LocalizedRepository($content, 'fr');

		$this->assertSame('Printemps', $localized->query()->type('post')->first()?->title);
		$this->assertContains('Printemps', array_map(static fn (Entry $entry): string => $entry->title, $localized->query()->type('post')->anyLanguage()->limit(null)->get()->all()));
		$this->assertNotContains('Printemps', array_map(static fn (Entry $entry): string => $entry->title, $localized->query()->type('post')->language('en')->limit(null)->get()->all()));
		$this->assertSame('À propos', $localized->named('page', 'a-propos')?->title);
		$this->assertSame(['art' => 1], array_filter($localized->termCounts('category')));
	}

	public function testComponentsInMarkdownFollowTheEntrysLanguage(): void
	{
		// The same file in both languages, so only the language tells the
		// cached bodies apart.
		$this->entry('lists.md', 'title: Lists', "::app/post-titles\n");
		$this->entry('lists.fr.md', 'title: Lists', "::app/post-titles\n");

		$app = $this->site();
		$app->container()->make(ComponentRegistry::class)->register('app/post-titles', PostTitles::class);

		$content = $app->container()->make(ContentRepository::class);
		$french  = $content->named('page', 'lists', 'fr')?->body() ?? '';
		$english = $content->named('page', 'lists')?->body() ?? '';

		$this->assertStringContainsString("Printemps | L'art", $french);
		$this->assertStringNotContainsString('Printemps', $english);
		$this->assertStringContainsString('| Art', $english);

		// Rendered again, from the cache.
		$this->assertStringContainsString('Printemps', $app->container()->make(ContentRepository::class)->named('page', 'lists', 'fr')?->body() ?? '');
		$this->assertStringContainsString("Printemps | L'art", (string) $app->container()->make(Kernel::class)->handle(Request::create('/fr/lists'))->getBody());
	}

	public function testComponentTextIsInThePageLanguage(): void
	{
		$this->writeTemporaryFile('user/lang/fr/extensions/blush/default.json', '{"progress": {"label": "Avancement"}}');

		$app       = $this->site();
		$container = $app->container();
		$views     = $container->make(ViewFactory::class)->forChain($container->make(ThemeResolver::class)->active());
		$content   = $container->make(ContentRepository::class);
		$french    = $container->make(ViewFactory::class)->context($views, $content->named('page', 'a-propos', 'fr'));
		$english   = $container->make(ViewFactory::class)->context($views, $content->named('page', 'about'));

		$this->assertStringContainsString('aria-label="Avancement"', $views->component('progress', ['value' => '1', 'max' => '2'], '', new Slots(), $french));
		$this->assertStringContainsString('aria-label="Progress"', $views->component('progress', ['value' => '1', 'max' => '2'], '', new Slots(), $english));
	}

	public function testMenusLinkToTranslations(): void
	{
		$this->writeTemporaryFile('user/data/menus/primary.yaml', <<<'YAML'
			label: { en: Main, fr: Principal }
			items:
			  - entry: page/about
			  - entry: page/about/team/jane
			  - entry: page/notes
			  - term: category/art
			  - term: category/old-posts
			  - collection: post
			YAML);

		$app   = $this->site();
		$menus = $app->container()->make(Menus::class);
		$chain = $app->container()->make(ThemeResolver::class)->active();
		$shape = static fn (?Menu $menu): array => array_map(static fn (MenuItem $item): string => "{$item->label} {$item->url}", $menu->items ?? []);

		$this->assertSame(['About /about', 'Jane /about/team/jane', 'Notes /notes', 'Art /topics/art', 'old-posts /topics/old-posts', 'Blog /'], $shape($menus->forLocation($chain, 'primary')));

		// French: translations, and the originals for what isn't translated.
		$this->assertSame(
			['À propos /fr/a-propos', 'Jeanne /fr/a-propos/team/jane', 'Notes /notes', 'L\'art /fr/topics/lart', 'old-posts /topics/old-posts', 'Journal /fr'],
			$shape($menus->forLocation($chain, 'primary', 'fr_FR'))
		);
		$this->assertSame('Principal', $menus->forLocation($chain, 'primary', 'fr_FR')?->label);

		// Portuguese has nothing translated, so its links are the originals.
		$this->assertSame(['About /about', 'Jane /about/team/jane', 'Notes /notes', 'Art /topics/art', 'old-posts /topics/old-posts', 'Blog /'], $shape($menus->forLocation($chain, 'primary', 'pt_BR')));

		$this->expectException(InvalidConfig::class);

		Languages::fromArray('en_US', ['fr' => 'fr_FR', 'fr-fr' => 'fr_FR']);
	}

	public function testTemplateRoutesFollowThePageLanguage(): void
	{
		$container = $this->app->container();
		$views     = $container->make(ViewFactory::class)->forChain($container->make(ThemeResolver::class)->active());
		$content   = $this->content();
		$french    = new Template($views, $container->make(ViewFactory::class)->context($views, $content->named('page', 'a-propos', 'fr')));
		$english   = new Template($views, $container->make(ViewFactory::class)->context($views, $content->named('page', 'about')));

		$this->assertSame('/fr', $french->route('home'));
		$this->assertSame('/fr/archives/2008/04', $french->route('post.collection.month', ['year' => '2008', 'month' => '04']));
		$this->assertSame($english->route('profile.single', ['name' => 'guest']), $french->route('profile.single', ['name' => 'guest']));
		$this->assertStringStartsNotWith('/fr', $french->route('profile.single', ['name' => 'guest']));
		$this->assertSame('/', $english->route('home'));
		$this->assertSame('/archives/2008/04', $english->route('post.collection.month', ['year' => '2008', 'month' => '04']));
	}

	public function testTermsAreFoundByTheOriginalsSlug(): void
	{
		$content = $this->content();

		$this->assertSame('lart', $content->term('category', 'art', 'fr')?->key);
		$this->assertSame('lart', $content->term('category', 'lart', 'fr')?->key);
		$this->assertSame('art', $content->term('category', 'art')?->key);
		$this->assertTrue($content->term('category', 'art', 'pt-br')?->isVirtual());

		$spring = $content->named('post', 'printemps', 'fr');
		$views  = $this->app->container()->make(ViewFactory::class)->forChain($this->app->container()->make(ThemeResolver::class)->active());

		$this->assertNotNull($spring);
		$this->assertSame(['L\'art'], array_map(static fn (Entry $entry): string => $entry->title, new Template($views, new ViewContext())->terms($spring, 'category')));
	}

	public function testHreflangAlternates(): void
	{
		$about = [
			'<link rel="alternate" href="http://localhost/about" hreflang="en-US">',
			'<link rel="alternate" href="http://localhost/fr/a-propos" hreflang="fr-FR">',
			'<link rel="alternate" href="http://localhost/about" hreflang="x-default">'
		];

		$this->assertSame($about, $this->alternates('/about'));
		$this->assertSame($about, $this->alternates('/fr/a-propos'));

		// Lists: in each language that lists entries for them.
		$home = [
			'<link rel="alternate" href="http://localhost/" hreflang="en-US">',
			'<link rel="alternate" href="http://localhost/fr" hreflang="fr-FR">',
			'<link rel="alternate" href="http://localhost/" hreflang="x-default">'
		];

		$this->assertSame($home, $this->alternates('/'));
		$this->assertSame($home, $this->alternates('/fr'));
		$this->assertSame([
			'<link rel="alternate" href="http://localhost/topics/art" hreflang="en-US">',
			'<link rel="alternate" href="http://localhost/fr/topics/lart" hreflang="fr-FR">',
			'<link rel="alternate" href="http://localhost/topics/art" hreflang="x-default">'
		], $this->alternates('/fr/topics/lart'));
		$this->assertSame([
			'<link rel="alternate" href="http://localhost/archives/2008/04" hreflang="en-US">',
			'<link rel="alternate" href="http://localhost/fr/archives/2008/04" hreflang="fr-FR">',
			'<link rel="alternate" href="http://localhost/archives/2008/04" hreflang="x-default">'
		], $this->alternates('/archives/2008/04'));

		// In one language only, a page has none.
		$this->assertSame([], $this->alternates('/archives/welcome'));
		$this->assertSame([], $this->alternates('/archives/2003'));
		$this->assertSame([], $this->alternates('/topics/old-posts'));

		// A translation that isn't published isn't an alternate.
		$this->entry('about/team/jane.fr.md', "title: Jeanne\nstatus: draft");
		touch($this->temporaryDirectory() . '/user/content/about/team/jane.fr.md', time() + 10);
		$this->app->container()->make(Indexer::class)->index();

		$this->assertSame([], $this->alternates('/about/team/jane'));
	}

	/**
	 * Returns a page's `hreflang` links.
	 *
	 * @return list<string>
	 */
	private function alternates(string $uri): array
	{
		preg_match_all('#<link [^>]*hreflang[^>]*>#', (string) $this->get($uri)->getBody(), $matches);

		return $matches[0];
	}

	public function testExportListsEveryLanguage(): void
	{
		$paths = array_map(
			static fn (ExportUrl $url): string => $url->path,
			[...$this->app->container()->make(ContentExportUrls::class)->urls()]
		);

		foreach (['/fr', '/fr/topics/lart', '/fr/archives/2008/04', '/fr/archives/printemps', '/fr/a-propos', '/fr/a-propos/biographie', '/about'] as $path) {
			$this->assertContains($path, $paths);
		}

		$this->assertNotContains('/fr/topics/old-posts', $paths);
	}

	public function testLintFlagsTheDefaultLanguageSuffix(): void
	{
		$this->entry('about/index.en.md', 'title: About again');

		$report = $this->site()->container()->make(Linter::class)->lint();
		$found  = array_map(static fn ($violation): string => $violation->message, $report->files['about/index.en.md'] ?? []);

		$this->assertSame(['has the default language\'s suffix beside about/index.md, which wins; the default language needs none, so remove one.'], $found);
		$this->assertSame([], $report->files['_posts/2008-04-05.spring.fr.md'] ?? []);
	}

	public function testSuffixesMeanNothingOnASiteWithOneLanguage(): void
	{
		$this->languages([]);

		$record = $this->snapshot($this->site())->record('about/team/jane.fr.md');

		$this->assertSame(['en', 'fr', null], [$record?->language, $record?->slug, $record?->original]);
	}

	public function testTranslatedFoldersNameTheirChildren(): void
	{
		$snapshot  = $this->snapshot();
		$biography = $snapshot->record('about/biography.fr.md');

		$this->assertNotNull($biography);
		$this->assertSame(['a-propos/biographie', 'a-propos'], [$biography->key, $biography->parent]);
		$this->assertSame(['key' => 'about/biographie', 'parent' => 'about'], $biography->untranslated);
		$this->assertSame('a-propos/team', $snapshot->record('about/team/jane.fr.md')?->parent);
		$this->assertNull($snapshot->record('about/biography.md')?->untranslated);

		$content = $this->content();
		$about   = $content->named('page', 'a-propos', 'fr');

		$this->assertNotNull($about);
		$this->assertSame(['Biographie'], array_map(static fn (Entry $entry): string => $entry->title, $content->children($about)));
		$this->assertSame('À propos', $content->parent($content->named('page', 'a-propos/biographie', 'fr') ?? $this->fail())?->title);
	}

	public function testReindexingKeepsTranslatedKeysCurrent(): void
	{
		$this->snapshot();
		$this->entry('notes.md', 'title: Notes');
		$this->app->container()->make(Indexer::class)->index();

		$this->assertSame('a-propos/biographie', $this->app->container()->make(ContentIndex::class)->snapshot()->record('about/biography.fr.md')?->key);

		// Only the parent changes; its child's key follows.
		$this->entry('about/index.fr.md', "title: À propos\nslug: qui");
		touch($this->temporaryDirectory() . '/user/content/about/index.fr.md', time() + 10);
		$this->app->container()->make(Indexer::class)->index();

		$snapshot = $this->app->container()->make(ContentIndex::class)->snapshot();

		$this->assertSame(['qui/biographie', 'qui'], [$snapshot->record('about/biography.fr.md')?->key, $snapshot->record('about/biography.fr.md')?->parent]);
	}

	public function testHierarchicalTermsTranslateTheirParents(): void
	{
		$this->contentConfig(['types' => ['topic' => ['path' => 'topics', 'taxonomy' => true, 'hierarchical' => true]]]);
		$this->entry('topics/web.md', 'title: Web');
		$this->entry('topics/web.fr.md', "title: Toile\nslug: toile");
		$this->entry('topics/css.md', "title: CSS\nparent: web");
		$this->entry('topics/css.fr.md', "title: CSS\nparent: web");

		$app      = $this->site();
		$snapshot = $this->snapshot($app);
		$content  = $app->container()->make(ContentRepository::class);
		$css      = $content->named('topic', 'css', 'fr');

		$this->assertSame('toile', $snapshot->record('topics/css.fr.md')?->parent);
		$this->assertSame('web', $snapshot->record('topics/css.md')?->parent);
		$this->assertNotNull($css);
		$this->assertSame('Toile', $content->parent($css)?->title);
		$this->assertSame('/fr/topics/toile/css', $app->container()->make(ContentUrls::class)->entry($css));
	}
}
