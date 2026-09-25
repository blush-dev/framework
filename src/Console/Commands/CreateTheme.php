<?php

/**
 * Theme creation command.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console\Commands;

use Blush\Console\Attributes\Argument;
use Blush\Console\Attributes\Command;
use Blush\Console\Attributes\Option;
use Blush\Console\ExitCode;
use Blush\Console\InvalidInput;
use Blush\Console\Output;
use Blush\Core\Framework;
use Blush\Core\Paths;
use Blush\Support\Filesystem;
use Blush\Theme\Themes;

/**
 * Starts a theme in `user/themes/{slug}`: the smallest valid theme, a
 * manifest and a stylesheet (D-021). Everything else falls back to its
 * parent, or to the default theme.
 */
#[Command('theme:new', 'Create a theme in user/themes.')]
final readonly class CreateTheme
{
	public function __construct(
		private Themes $themes,
		private Paths $paths,
		private Filesystem $filesystem
	) {}

	/**
	 * @throws InvalidInput
	 */
	public function __invoke(
		Output $output,
		#[Argument('The theme\'s slug (letters, digits, "-", and "_").')] string $slug,
		#[Option('The parent theme\'s slug.')] ?string $parent = null,
		#[Option('The theme\'s name; defaults to one made from the slug.')] ?string $name = null
	): ExitCode {
		if (! Themes::isValidSlug($slug) || $slug === Themes::DEFAULT) {
			throw new InvalidInput(sprintf('"%s" can\'t be a theme slug; use lowercase letters, digits, "-", and "_" (and not "default").', $slug));
		}

		if ($parent !== null && ! $this->themes->has($parent)) {
			throw new InvalidInput(sprintf('There is no "%s" theme to use as the parent.', $parent));
		}

		$folder = "{$this->paths->themes}/{$slug}";

		if (file_exists($folder)) {
			$output->error(sprintf('%s already exists.', $this->paths->relative($folder)));

			return ExitCode::Failure;
		}

		$name   ??= ucwords(str_replace(['-', '_'], ' ', $slug));
		$manifest = ['name' => $name, 'version' => '1.0.0', ...($parent === null ? [] : ['parent' => $parent]), 'styles' => ['style.css']];

		$this->filesystem->writeAtomic("{$folder}/theme.json", json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n");
		$this->filesystem->writeAtomic("{$folder}/style.css", "/**\n * {$name} theme styles.\n */\n");

		$output->success(sprintf('Created %s. Activate it with: %s theme:activate %s', $this->paths->relative($folder), Framework::BINARY, $slug));

		return ExitCode::Success;
	}
}
