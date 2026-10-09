<?php

/**
 * Generated jtcom-sized benchmark site.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Benchmarks\Fixture;

use RuntimeException;
use Blush\Support\Uuid;
use Blush\Tests\Fixtures\Content\JtcomTypes;

/**
 * Generates a site shaped like jtcom (D-044): its seven content types
 * and their relations (from `JtcomTypes`, as data files in
 * `user/data/types` and `user/data/relations`, D-617), 1,184 content
 * files (940 dated posts across 23 years with categories, eras, and an
 * author; 124 topics; 6 eras; 46 pieces of writing with forms, genres,
 * and techniques; pages; and the author's profile), each with an `id`
 * (D-480), and Markdown bodies of a few hundred words. Output is
 * deterministic (a fixed seed, and ids from each file's path), and the
 * site is built once per `VERSION` in the system temp folder, so runs
 * compare like with like.
 */
final class JtcomSizedSite
{
	/**
	 * Bump when the generated content changes.
	 */
	public const int VERSION = 3;

	public const int POSTS = 940;

	public const int TOPICS = 124;

	public const int ERAS = 6;

	public const int WRITING = 46;

	public const int PAGES = 40;

	/**
	 * Words bodies are made from.
	 *
	 * @var list<string>
	 */
	private const array WORDS = [
		'garden', 'story', 'morning', 'theme', 'code', 'plugin', 'river', 'autumn', 'writing', 'light',
		'window', 'chapter', 'coffee', 'design', 'journey', 'season', 'little', 'simple', 'quiet', 'always',
		'about', 'through', 'before', 'another', 'think', 'remember', 'building', 'something', 'people', 'years',
		'the', 'and', 'of', 'to', 'a', 'in', 'that', 'it', 'was', 'for'
	];

	/**
	 * Returns the site's root, generating it first if needed.
	 */
	public static function root(): string
	{
		$root = sys_get_temp_dir() . '/blush-bench-site-v' . self::VERSION;

		if (! is_file("{$root}/.generated")) {
			self::generate($root);
		}

		return $root;
	}

	/**
	 * Returns a published post's slug, for lookups.
	 */
	public static function postSlug(int $number): string
	{
		return "post-{$number}";
	}

	/**
	 * Writes the site.
	 */
	private static function generate(string $root): void
	{
		mt_srand(2026);

		self::write("{$root}/config/app.php", <<<PHP
			<?php

			declare(strict_types=1);

			use Blush\\Core\\AppConfig;
			use Blush\\Core\\Environment;

			return new AppConfig(name: 'Bench', url: 'https://bench.example', environment: Environment::Production, timezone: 'America/Chicago');
			PHP);

		self::write("{$root}/config/content.php", <<<PHP
			<?php

			declare(strict_types=1);

			use Blush\\Content\\ContentConfig;

			return new ContentConfig(home: 'post');
			PHP);

		foreach (['types' => JtcomTypes::definitions(), 'relations' => JtcomTypes::relations()] as $folder => $definitions) {
			foreach ($definitions as $name => $definition) {
				self::write("{$root}/user/data/{$folder}/{$name}.json", json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
			}
		}

		$content = "{$root}/user/content";

		self::entry("{$content}/index.md", ['title' => 'Home'], self::body(2));
		self::entry("{$content}/profiles/j/justintadlock.md", ['title' => 'Justin Tadlock'], self::body(1));
		self::entry("{$content}/_posts/index.md", ['title' => 'Blog'], '');
		self::entry("{$content}/topics/index.md", ['title' => 'Topics', 'collection' => ['number' => -1]], '');
		self::entry("{$content}/eras/index.md", ['title' => 'Eras'], '');
		self::entry("{$content}/writing/index.md", ['title' => 'Writing'], '');

		for ($i = 1; $i <= self::TOPICS; $i++) {
			self::entry("{$content}/topics/topic-{$i}.md", ['title' => "Topic {$i}"], self::body(1));
		}

		for ($i = 1; $i <= self::ERAS; $i++) {
			self::entry(sprintf('%s/eras/%02d.era-%d.md', $content, $i, $i), ['title' => "Era {$i}"], self::body(1));
		}

		$start = strtotime('2003-04-15 17:39:00 America/Chicago');

		for ($i = 1; $i <= self::POSTS; $i++) {
			$published = $start + (int) ($i * 8.8 * 86400) + mt_rand(0, 36000);
			$date      = date('Y-m-d', $published);
			$topics    = array_map(static fn (): string => 'topic-' . mt_rand(1, self::TOPICS), range(1, mt_rand(1, 3)));

			self::entry("{$content}/_posts/{$date}." . self::postSlug($i) . '.md', [
				'title'    => ucfirst(self::words(mt_rand(3, 7))),
				'author'   => 'justintadlock',
				'date'     => date('Y-m-d H:i:s', $published),
				'era'      => 'era-' . min(self::ERAS, intdiv($i * self::ERAS, self::POSTS) + 1),
				'category' => array_values(array_unique($topics)),
				'image'    => '/user/media/' . date('Y/m', $published) . '/image.jpg'
			], self::body(mt_rand(3, 8)));
		}

		foreach (['forms' => 8, 'genres' => 6, 'techniques' => 5] as $taxonomy => $count) {
			self::entry("{$content}/writing/{$taxonomy}/index.md", ['title' => ucfirst($taxonomy)], '');

			for ($i = 1; $i <= $count; $i++) {
				self::entry("{$content}/writing/{$taxonomy}/{$taxonomy}-{$i}.md", ['title' => ucfirst($taxonomy) . " {$i}"], self::body(1));
			}
		}

		for ($i = 1; $i <= self::WRITING; $i++) {
			self::entry(sprintf('%s/writing/%s.writing-%d.md', $content, date('Y-m-d', $start + $i * 86400 * 90), $i), [
				'title'              => ucfirst(self::words(4)),
				'date'               => date('Y-m-d', $start + $i * 86400 * 90),
				'literary_form'      => 'forms-' . mt_rand(1, 8),
				'literary_genre'     => 'genres-' . mt_rand(1, 6),
				'literary_technique' => 'techniques-' . mt_rand(1, 5)
			], self::body(mt_rand(6, 12)));
		}

		for ($i = 1; $i <= self::PAGES; $i++) {
			$folder = ['about', 'archives', 'services', 'playground'][$i % 4];
			$path   = $i <= 4 ? "{$content}/{$folder}/index.md" : "{$content}/{$folder}/page-{$i}.md";

			self::entry($path, ['title' => ucfirst($folder) . " {$i}"], self::body(mt_rand(1, 4)));
		}

		touch("{$root}/.generated");
	}

	/**
	 * Writes a Markdown entry, with an id from its path, last.
	 *
	 * @param array<string, mixed> $frontMatter
	 */
	private static function entry(string $path, array $frontMatter, string $body): void
	{
		$yaml = '';

		foreach ([...$frontMatter, 'id' => Uuid::fromName('bench/' . substr($path, (int) strpos($path, '/user/content/')))] as $key => $value) {
			$yaml .= "{$key}: " . json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
		}

		self::write($path, "---\n{$yaml}---\n\n{$body}");
	}

	/**
	 * Returns a Markdown body of some paragraphs, with a heading, a list,
	 * emphasis, and links.
	 */
	private static function body(int $paragraphs): string
	{
		$blocks = [];

		for ($i = 0; $i < $paragraphs; $i++) {
			$blocks[] = ucfirst(self::words(mt_rand(40, 90))) . ' *' . self::words(3) . '* and [a link](/archives/' . mt_rand(1, 99) . ').';

			if ($i === 1) {
				$blocks[] = '## ' . ucfirst(self::words(3));
				$blocks[] = '- ' . self::words(4) . "\n- " . self::words(5) . "\n- " . self::words(3);
			}
		}

		return implode("\n\n", $blocks) . "\n";
	}

	/**
	 * Returns random words.
	 */
	private static function words(int $count): string
	{
		return implode(' ', array_map(static fn (): string => self::WORDS[mt_rand(0, count(self::WORDS) - 1)], range(1, $count)));
	}

	/**
	 * Writes a file, creating its folder.
	 */
	private static function write(string $path, string $contents): void
	{
		if (! is_dir(dirname($path)) && ! mkdir(dirname($path), 0775, true) && ! is_dir(dirname($path))) {
			throw new RuntimeException(sprintf('Unable to create "%s".', dirname($path)));
		}

		file_put_contents($path, $contents);
	}
}
