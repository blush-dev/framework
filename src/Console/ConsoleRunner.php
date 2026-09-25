<?php

/**
 * Console runner.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console;

use Override;
use Throwable;
use Blush\Core\Runner;
use Blush\Error\ErrorHandler;
use Blush\Error\TextRenderer;

/**
 * Runs a command line. A site's `bin/blush` is just:
 *
 *     require dirname(__DIR__) . '/vendor/autoload.php';
 *
 *     exit(new Blush\Console\ConsoleRunner(dirname(__DIR__))->run($argv));
 */
final class ConsoleRunner extends Runner
{
	/**
	 * Where launch errors are written.
	 *
	 * @var resource
	 */
	private readonly mixed $errors;

	/**
	 * @param array<string, string>  $paths
	 * @param ?array<string, string> $environment
	 * @param ?resource              $errors      Where launch errors are written; defaults to stderr.
	 */
	public function __construct(
		string $root,
		array $paths = [],
		?array $environment = null,
		mixed $errors = null
	) {
		parent::__construct($root, $paths, $environment);

		$this->errors = $errors ?? STDERR;
	}
	/**
	 * Runs the command line and returns the exit code. When the
	 * application can't launch (a broken `.env` or config file, say), the
	 * error is printed and the code is `ExitCode::Failure`.
	 *
	 * @param list<string> $argv The arguments, including the script name.
	 */
	public function run(array $argv): int
	{
		try {
			$application = $this->application();
		} catch (Throwable $exception) {
			$this->failedToLaunch($exception);

			return ExitCode::Failure->value;
		}

		return $application->container()
			->make(Console::class)
			->run(array_slice($argv, 1))
			->value;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function earlyErrorHandler(bool $debug): ErrorHandler
	{
		$errors = $this->errors;

		return new ErrorHandler(
			new TextRenderer($debug),
			output: static function (string $text) use ($errors): void {
				fwrite($errors, $text);
			}
		);
	}
}
