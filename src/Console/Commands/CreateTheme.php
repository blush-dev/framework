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
use Blush\Extension\ExtensionName;
use Blush\Extension\ExtensionNamespace;
use Blush\JsonSchema\JsonSchemas;
use Blush\Support\Filesystem;
use Blush\Theme\Themes;

/**
 * Starts a theme in `user/themes/{folder}`: the smallest valid theme, a
 * manifest and a stylesheet (D-021). Everything else falls back to its
 * parent, or to the default theme. The theme is named `vendor/name`
 * (D-378); its folder and namespace default to the part after the `/`,
 * and its label to one made from it. The manifest points editors at the
 * framework's `theme.json` schema in `vendor` (D-206).
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
		#[Argument('The theme\'s name: vendor/name, such as acme/nova.')] string $name,
		#[Option('The parent theme\'s name.')] ?string $parent = null,
		#[Option('The theme\'s label; defaults to one made from its name.')] ?string $label = null,
		#[Option('The theme\'s namespace; defaults to the part of its name after the "/".')] ?string $namespace = null
	): ExitCode {
		if (! ExtensionName::isValid($name) || $name === Themes::DEFAULT) {
			throw new InvalidInput(sprintf('"%s" can\'t be a theme\'s name; use vendor/name in lowercase letters, digits, "-", "_", and "." (and not "%s").', $name, Themes::DEFAULT));
		}

		$short       = substr((string) strrchr($name, '/'), 1);
		$namespace ??= $short;

		if (! ExtensionNamespace::isValid($namespace) || ExtensionNamespace::isReserved($namespace)) {
			throw new InvalidInput(sprintf('"%s" can\'t be a namespace; use lowercase letters, digits, "-", and "_" (and not %s). Pass --namespace.', $namespace, implode(', ', ExtensionNamespace::RESERVED)));
		}

		if ($this->themes->byNamespace($namespace) !== null) {
			throw new InvalidInput(sprintf('The "%s" theme already has the namespace "%s"; pass another with --namespace.', $this->themes->byNamespace($namespace)->name, $namespace));
		}

		if ($this->themes->has($name)) {
			throw new InvalidInput(sprintf('A theme named "%s" is already installed.', $name));
		}

		if ($parent !== null && ! $this->themes->has($parent)) {
			throw new InvalidInput(sprintf('There is no "%s" theme to use as the parent.', $parent));
		}

		$folder = "{$this->paths->themes}/{$short}";

		if (file_exists($folder)) {
			$output->error(sprintf('%s already exists.', $this->paths->relative($folder)));

			return ExitCode::Failure;
		}

		$label  ??= ucwords(str_replace(['-', '_', '.'], ' ', $short));
		$schema   = $this->filesystem->relative($folder, sprintf('%s/%s/%s/theme.schema.json', $this->paths->vendor, Framework::PACKAGE, JsonSchemas::DIRECTORY));
		$manifest = ['$schema' => $schema, 'name' => $name, 'label' => $label, 'namespace' => $namespace, 'version' => '1.0.0', ...($parent === null ? [] : ['parent' => $parent]), 'styles' => ['style.css']];

		$this->filesystem->writeAtomic("{$folder}/theme.json", json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n");
		$this->filesystem->writeAtomic("{$folder}/style.css", "/**\n * {$label} theme styles.\n */\n");

		$output->success(sprintf('Created %s. Activate it with: %s theme:activate %s', $this->paths->relative($folder), Framework::BINARY, $name));

		return ExitCode::Success;
	}
}
