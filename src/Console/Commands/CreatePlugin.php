<?php

/**
 * Plugin creation command.
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
use Blush\Extension\ExtensionException;
use Blush\Extension\ExtensionName;
use Blush\Extension\ExtensionNamespace;
use Blush\Extension\InstalledExtensions;
use Blush\Extension\LocalExtensions;
use Blush\JsonSchema\JsonSchemas;
use Blush\Support\Filesystem;

/**
 * Starts a plugin in `extensions/{vendor}/{name}` (D-418): a manifest and
 * an empty service provider, autoloaded from `src/` (D-041, D-416). The
 * plugin is named `vendor/name` (D-378); its namespace defaults to the
 * part after the `/`, its label to one made from it, and its PHP
 * namespace to both parts in StudlyCase (`acme/hello-world` is
 * `Acme\HelloWorld`). The name and namespace must be free across every
 * installed extension (`InstalledExtensions`). It's created off
 * (D-390). The manifest points editors at the framework's `plugin.json`
 * schema in `vendor` (D-206).
 */
#[Command('plugin:new', 'Create a plugin in extensions/.')]
final readonly class CreatePlugin
{
	public function __construct(
		private Paths $paths,
		private Filesystem $filesystem,
		private InstalledExtensions $installed
	) {}

	/**
	 * @throws InvalidInput
	 * @throws ExtensionException When the installed plugins can't be read.
	 */
	public function __invoke(
		Output $output,
		#[Argument('The plugin\'s name: vendor/name, such as acme/hello.')] string $name,
		#[Option('The plugin\'s label; defaults to one made from its name.')] ?string $label = null,
		#[Option('The plugin\'s namespace; defaults to the part of its name after the "/".')] ?string $namespace = null,
		#[Option('The PHP namespace of its classes; defaults to its name in StudlyCase, such as Acme\\Hello.')] ?string $phpNamespace = null
	): ExitCode {
		if (! ExtensionName::isValid($name)) {
			throw new InvalidInput(sprintf('"%s" can\'t be a plugin\'s name; use vendor/name in lowercase letters, digits, "-", "_", and ".".', $name));
		}

		[$vendor, $short] = explode('/', $name, 2);
		$namespace      ??= $short;

		if (! ExtensionNamespace::isValid($namespace) || ExtensionNamespace::isReserved($namespace)) {
			throw new InvalidInput(sprintf('"%s" can\'t be a namespace; use lowercase letters, digits, "-", and "_" (and not %s). Pass --namespace.', $namespace, implode(', ', ExtensionNamespace::RESERVED)));
		}

		$phpNamespace = trim($phpNamespace ?? self::studly($vendor) . '\\' . self::studly($short), '\\');

		if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)*$/', $phpNamespace) !== 1) {
			throw new InvalidInput(sprintf('"%s" can\'t be a PHP namespace. Pass --php-namespace, such as --php-namespace="Acme\\Hello".', $phpNamespace));
		}

		$clash = $this->installed->clash($name, $namespace);

		if ($clash !== null) {
			throw new InvalidInput($clash);
		}

		$folder = LocalExtensions::path($this->paths, $name);

		if (file_exists($folder)) {
			$output->error(sprintf('%s already exists.', $this->paths->relative($folder)));

			return ExitCode::Failure;
		}

		$label  ??= ucwords(str_replace(['-', '_', '.'], ' ', $short));
		$class    = self::studly($short) . 'ServiceProvider';
		$schema   = $this->filesystem->relative($folder, sprintf('%s/%s/%s/plugin.schema.json', $this->paths->vendor, Framework::PACKAGE, JsonSchemas::DIRECTORY));
		$manifest = [
			'$schema'   => $schema,
			'name'      => $name,
			'label'     => $label,
			'namespace' => $namespace,
			'version'   => '1.0.0',
			'provider'  => "{$phpNamespace}\\{$class}",
			'autoload'  => ['psr-4' => ["{$phpNamespace}\\" => 'src/']],
			'require'   => [Framework::PACKAGE => '^' . (int) Framework::VERSION . '.0']
		];

		$this->filesystem->writeAtomic("{$folder}/plugin.json", json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n");
		$this->filesystem->writeAtomic("{$folder}/src/{$class}.php", self::provider($phpNamespace, $class, $label));

		$output->success(sprintf(
			'Created %s. It\'s off: turn it on in the admin (Config → Plugins) or add "%s" to config/plugins.php\'s enabled list.',
			$this->paths->relative($folder),
			$name
		));

		return ExitCode::Success;
	}

	/**
	 * Returns a name part in StudlyCase: `hello-world` is `HelloWorld`.
	 */
	private static function studly(string $part): string
	{
		return str_replace(' ', '', ucwords(str_replace(['-', '_', '.'], ' ', $part)));
	}

	/**
	 * Returns the service provider's source.
	 */
	private static function provider(string $namespace, string $class, string $label): string
	{
		return <<<PHP
			<?php

			declare(strict_types=1);

			namespace {$namespace};

			use Blush\\Core\\ServiceProvider;

			/**
			 * Connects {$label} to Blush. List its classes in the constants
			 * (SINGLETONS, TAGS, and the rest), or override register() or boot().
			 */
			final class {$class} extends ServiceProvider
			{
			}

			PHP;
	}
}
