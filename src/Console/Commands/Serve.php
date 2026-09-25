<?php

/**
 * Serve command.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console\Commands;

use Blush\Console\Attributes\Command;
use Blush\Console\Attributes\Option;
use Blush\Console\ExitCode;
use Blush\Console\InvalidInput;
use Blush\Console\Output;
use Blush\Console\ProcessRunner;
use Blush\Core\Paths;

/**
 * Runs PHP's built-in web server on the site's public directory, for
 * development only. Requests for files that exist are served directly;
 * everything else goes through the front controller, by way of the
 * framework's `resources/server.php` router script.
 */
#[Command('serve', 'Serve the site with PHP\'s built-in development server.')]
final readonly class Serve
{
	public function __construct(
		private Paths $paths,
		private ProcessRunner $process
	) {}

	/**
	 * @throws InvalidInput
	 */
	public function __invoke(
		Output $output,
		#[Option('The host to listen on.')] string $host = '127.0.0.1',
		#[Option('The port to listen on.', short: 'p')] int $port = 8000
	): ExitCode {
		if ($port < 1 || $port > 65535) {
			throw new InvalidInput(sprintf('The "--port" option must be between 1 and 65535; %d given.', $port));
		}

		if (! is_file("{$this->paths->public}/index.php")) {
			$output->error(sprintf('No front controller found at %s/index.php.', $this->paths->relative($this->paths->public)));

			return ExitCode::Failure;
		}

		$output->success(sprintf('Serving on http://%s:%d', $host, $port));
		$output->comment('Press Ctrl+C to stop.');

		$code = $this->process->run([
			PHP_BINARY,
			'-S',
			"{$host}:{$port}",
			'-t',
			$this->paths->public,
			self::routerScript()
		], $this->paths->public);

		return $code === 0 ? ExitCode::Success : ExitCode::Failure;
	}

	/**
	 * Returns the path to the router script.
	 */
	public static function routerScript(): string
	{
		return dirname(__DIR__, 3) . '/resources/server.php';
	}
}
