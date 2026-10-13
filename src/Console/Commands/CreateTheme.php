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
use Blush\Console\Outcome;
use Blush\Console\Output;
use Blush\Extension\ExtensionText;
use Blush\Console\Prompt;
use Blush\Console\StatusLine;
use Blush\Core\Framework;
use Blush\Core\Paths;
use Blush\Extension\ExtensionException;
use Blush\Extension\ExtensionName;
use Blush\Extension\ExtensionNamespace;
use Blush\Extension\ExtensionState;
use Blush\Extension\InstalledExtensions;
use Blush\Extension\LocalExtensions;
use Blush\Extension\Requirements;
use Blush\JsonSchema\JsonSchemas;
use Blush\Settings\InvalidSetting;
use Blush\Support\Filesystem;
use Blush\Theme\ThemeActivator;
use Blush\Theme\ThemeException;
use Blush\Theme\ThemeManifest;
use Blush\Theme\Themes;
use Blush\Translation\Catalog;

/**
 * Starts a theme in `extensions/{vendor}/{name}` (D-418): the smallest
 * valid theme, a manifest and a stylesheet (D-021), with a starter
 * `lang/en.json` that names what it translates (D-452). Everything else falls
 * back to its parent, or to the default theme. A child theme gets no
 * stylesheet, since its own would replace its parent's (D-618). The theme is named
 * `vendor/name` (D-378); its namespace defaults to its name, hyphenated
 * (D-424), and its label to one made from the part after the `/`. The name and namespace must be free
 * across every installed extension (`InstalledExtensions`, D-417). The
 * manifest points editors at the framework's `theme.json` schema in
 * `vendor` (D-206).
 */
#[Command('theme:new', 'Create a theme in extensions/.')]
final readonly class CreateTheme
{
	public function __construct(
		private Themes $themes,
		private Paths $paths,
		private Filesystem $filesystem,
		private InstalledExtensions $installed,
		private ExtensionState $extensions,
		private ThemeActivator $activator,
		private ExtensionText $text
	) {}

	/**
	 * @throws InvalidInput
	 * @throws ExtensionException When the installed plugins can't be read.
	 */
	public function __invoke(
		Output $output,
		Prompt $prompt,
		#[Argument('The theme\'s name: vendor/name, such as acme/nova; asked for when it isn\'t given.')] ?string $name = null,
		#[Option('The parent theme\'s name.')] ?string $parent = null,
		#[Option('The theme\'s label; defaults to one made from its name.')] ?string $label = null,
		#[Option('The theme\'s namespace; defaults to its name, hyphenated, such as acme-hello.')] ?string $namespace = null,
		#[Option('What the theme is for.')] ?string $description = null,
		#[Option('Make it the active theme.')] bool $activate = false
	): ExitCode {
		$name ??= $prompt->ask('Name', null, $this->nameProblem(...), 'acme/nova');
		$problem = $this->nameProblem($name);

		if ($problem !== null) {
			throw new InvalidInput($problem);
		}

		$short     = substr((string) strrchr($name, '/'), 1);
		$namespace = $this->namespace($prompt, $name, $namespace);

		if ($parent !== null && ! $this->themes->has($parent)) {
			throw new InvalidInput(sprintf('There is no "%s" theme to use as the parent.', $parent));
		}

		$label       ??= $prompt->ask('Label', ucwords(str_replace(['-', '_', '.'], ' ', $short)));
		$parent      ??= $prompt->select('Parent theme', $this->parents(), '') ?: null;
		$description ??= trim($prompt->ask('Description', '', placeholder: 'What the theme is for (optional)'));
		$activate      = $activate || $prompt->confirm('Activate it now?');

		$folder   = LocalExtensions::path($this->paths, $name);
		$schema   = $this->filesystem->relative($folder, sprintf('%s/%s/%s/theme.schema.json', $this->paths->vendor, Framework::PACKAGE, JsonSchemas::DIRECTORY));
		$manifest = [
			'$schema'   => $schema,
			'name'      => $name,
			'label'     => $label,
			...($description === '' ? [] : ['description' => $description]),
			'namespace' => $namespace,
			'version'   => '1.0.0',
			...($parent === null ? ['styles' => ['style.css']] : ['parent' => $parent])
		];
		$files    = ['theme.json' => json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n"];

		if ($parent === null) {
			$files['style.css'] = "/**\n * {$label} theme styles.\n */\n";
		}

		$files['lang/en.json'] = Catalog::starter($name);

		foreach ($files as $file => $contents) {
			$this->filesystem->writeAtomic("{$folder}/{$file}", $contents);
		}

		$output->status(array_map(fn (string $file): StatusLine => new StatusLine(Outcome::Pass, 'wrote', $this->paths->relative("{$folder}/{$file}")), array_keys($files)));
		$output->success(sprintf('Created %s.', $this->paths->relative($folder)));

		if ($activate) {
			return $this->activate($output, $name, $parent);
		}

		$output->next(sprintf('Activate it with `%s theme:activate %s`.', Framework::BINARY, $name));

		return ExitCode::Success;
	}

	/**
	 * Returns why a name can't be the new theme's, or `null` when it can.
	 *
	 * @throws ExtensionException When the installed plugins can't be read.
	 */
	private function nameProblem(string $name): ?string
	{
		if (! ExtensionName::isValid($name) || $name === Themes::DEFAULT) {
			return sprintf('"%s" can\'t be a theme\'s name; use vendor/name in lowercase letters, digits, "-", "_", and "." (and not "%s").', $name, Themes::DEFAULT);
		}

		$folder = LocalExtensions::path($this->paths, $name);

		if (file_exists($folder)) {
			return sprintf('%s already exists.', $this->paths->relative($folder));
		}

		return $this->installed->clash($name, '');
	}

	/**
	 * Returns the theme's namespace: the one given, else its name,
	 * hyphenated, asked for instead when that one can't be used.
	 *
	 * @throws InvalidInput When the namespace can't be used.
	 * @throws ExtensionException When the installed plugins can't be read.
	 */
	private function namespace(Prompt $prompt, string $name, ?string $namespace): string
	{
		$derived = ExtensionNamespace::fromName($name);

		if ($namespace === null && $this->namespaceProblem($derived) !== null && $prompt->interactive) {
			$namespace = $prompt->ask(sprintf('Namespace (%s can\'t be used)', $derived), null, $this->namespaceProblem(...));
		}

		$namespace ??= $derived;
		$problem     = $this->namespaceProblem($namespace);

		if ($problem !== null) {
			throw new InvalidInput($problem);
		}

		return $namespace;
	}

	/**
	 * Returns why a namespace can't be the new theme's, or `null` when it
	 * can.
	 *
	 * @throws ExtensionException When the installed plugins can't be read.
	 */
	private function namespaceProblem(string $namespace): ?string
	{
		if (! ExtensionNamespace::isValid($namespace) || ExtensionNamespace::isReserved($namespace)) {
			return sprintf('"%s" can\'t be a namespace; use lowercase letters, digits, "-", and "_" (and not %s). Pass --namespace.', $namespace, implode(', ', ExtensionNamespace::RESERVED));
		}

		return $this->installed->clash('', $namespace);
	}

	/**
	 * Returns the themes a new one can build on, the default theme first,
	 * the rest by label, after the choice of none.
	 *
	 * @return array<string, string>
	 */
	private function parents(): array
	{
		$labels = array_map(fn (ThemeManifest $theme): string => (string) $this->text->label($theme), $this->themes->all());
		$themes = array_values($this->themes->all());

		usort($themes, static fn (ThemeManifest $a, ThemeManifest $b): int => [$a->name !== Themes::DEFAULT, strtolower($labels[$a->name] ?? '')] <=> [$b->name !== Themes::DEFAULT, strtolower($labels[$b->name] ?? '')]);

		$parents = ['' => 'None: a standalone theme, with its own style.css'];

		foreach ($themes as $theme) {
			$label                 = $labels[$theme->name] ?? '';
			$parents[$theme->name] = sprintf('%s (%s)', $label !== '' ? $label : $theme->name, $theme->name);
		}

		return $parents;
	}

	/**
	 * Makes the new theme the active one, when its parent's chain can run.
	 */
	private function activate(Output $output, string $name, ?string $parent): ExitCode
	{
		$unmet = $parent === null ? [] : $this->extensions->with(theme: $parent)->themes->unmet();

		if ($unmet !== []) {
			$output->warning(sprintf('It can\'t be activated: %s', Requirements::reason(array_merge(...array_values($unmet)))));

			return ExitCode::Failure;
		}

		try {
			$file = $this->activator->activate($name);
		} catch (ThemeException $error) {
			$output->error($error->getMessage(), sprintf('Set `active: \'%s\'` there yourself.', $name));

			return ExitCode::Failure;
		}

		try {
			$this->activator->clearAdminChoice();
		} catch (InvalidSetting $error) {
			$output->warning(sprintf('The theme activated in the admin may still win over config/theme.php: %s', $error->getMessage()));
		}

		$output->success(sprintf('Activated it in %s.', $this->paths->relative($file)));

		return ExitCode::Success;
	}
}
