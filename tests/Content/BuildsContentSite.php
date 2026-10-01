<?php

/**
 * Builds a scratch site with content for tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Content;

use DateTimeImmutable;
use DateTimeZone;
use Psr\Clock\ClockInterface;
use Blush\Clock\FrozenClock;
use Blush\Content\ContentRepository;
use Blush\Core\Application;
use Blush\Tests\BootsScratchSite;

/**
 * Writes a small, jtcom-shaped site into the scratch directory and boots
 * it with a frozen clock (2026-06-01 12:00 in America/Chicago), so
 * scheduled entries are predictable.
 *
 * The standard content (`standardContent()`) has a `post` type in
 * `_posts` (date archives, prefix `archives`) and a `category` taxonomy
 * in `topics`, like jtcom.
 */
trait BuildsContentSite
{
	use BootsScratchSite;

	private FrozenClock $clock;

	/**
	 * Writes `config/content.php` returning `ContentConfig::fromArray()`
	 * of the given array.
	 *
	 * @param array<string, mixed> $config
	 */
	private function contentConfig(array $config): void
	{
		$source = var_export($config, true);

		$this->writeTemporaryFile('config/content.php', <<<PHP
			<?php

			declare(strict_types=1);

			use Blush\Content\Type\ContentConfig;

			return ContentConfig::fromArray({$source});
			PHP);
	}

	/**
	 * Writes a content file with front matter.
	 */
	private function entry(string $path, string $frontMatter, string $body = ''): void
	{
		$this->writeTemporaryFile("user/content/{$path}", "---\n{$frontMatter}\n---\n{$body}");
	}

	/**
	 * Writes the standard content set.
	 */
	private function standardContent(): void
	{
		$this->contentConfig([
			'types' => [
				'post' => [
					'path'          => '_posts',
					'collection'    => ['order' => 'desc'],
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

		$this->entry('index.md', 'title: Home');
		$this->entry('about/index.md', 'title: About');
		$this->entry('about/biography.md', 'title: Biography');
		$this->entry('_private.md', 'title: Private');
		$this->entry('__drafts/idea.md', 'title: Idea');
		$this->entry('_posts/index.md', 'title: Blog');
		$this->entry('_posts/2003-04-15.welcome.md', "title: Welcome\ndate: 2003-04-15 17:39:00 -5\ncategory: old-posts\nauthor: justintadlock", 'Hello and welcome to my site.');
		$this->entry('_posts/2008-04-05.spring.md', "title: spring\npublished: 2008-04-05 09:00:00\ncategory: [art, Book Reviews]\nauthor: [justintadlock, guest]\ntag: flowers", 'Spring is here.');
		$this->entry('_posts/2008-04-20.rainy.md', "title: Rainy\npublished: 2008-04-20 10:00:00\nvisibility: unlisted\ncategory: art");
		$this->entry('_posts/2026-12-25.future.md', "title: Future\npublished: 2026-12-25 08:00:00");
		$this->entry('_posts/2020-01-01.unfinished.md', "title: Unfinished\npublished: 2020-01-01\nstatus: draft");
		$this->entry('_posts/hello/index.md', "title: Hello Bundle\npublished: 2010-01-01 12:00:00", 'A bundle.');
		$this->entry('topics/index.md', 'title: Topics');
		$this->entry('topics/art.md', 'title: Art');
		$this->entry('authors/justintadlock.md', 'title: Justin Tadlock', 'Writes things.');
		$this->entry('authors/guest.md', 'title: A Guest');
		$this->writeTemporaryFile('user/content/notes.json', '{"title": "Notes", "body": "Some *notes*."}');
		$this->writeTemporaryFile('user/content/_posts/hello/photo.jpg', 'not content');
	}

	/**
	 * Boots the scratch site.
	 */
	private function site(string $environment = 'production'): Application
	{
		$app = $this->scratchApplication(['APP_ENV' => $environment, 'APP_TIMEZONE' => 'America/Chicago']);

		$this->clock = new FrozenClock(new DateTimeImmutable('2026-06-01 12:00:00', new DateTimeZone('America/Chicago')));
		$app->container()->instance(ClockInterface::class, $this->clock);
		$app->boot();

		return $app;
	}

	/**
	 * Returns the repository of a booted site.
	 */
	private function repository(?Application $app = null): ContentRepository
	{
		return ($app ?? $this->site())->container()->make(ContentRepository::class);
	}
}
