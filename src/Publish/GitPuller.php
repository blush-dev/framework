<?php

/**
 * Git puller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Publish;

use Override;

/**
 * Runs `git pull --ff-only` in a folder (the site's `user/`, which may be
 * its own repository, D-039), from `PublishConfig::$remote` and `$branch`
 * when set. It never waits for input: terminal prompts are off and SSH
 * runs in batch mode, so a missing credential fails instead of hanging
 * a webhook request. The command is an argument list, never a shell
 * string.
 */
final readonly class GitPuller implements Puller
{
	public function __construct(private PublishConfig $config)
	{}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function pull(string $directory): PullResult
	{
		if (! function_exists('proc_open')) {
			return new PullResult(false, 'proc_open() is disabled on this host, so git can\'t run.');
		}

		if (! is_dir($directory)) {
			return new PullResult(false, sprintf('"%s" doesn\'t exist.', $directory));
		}

		$command = [$this->config->gitBinary, '-C', $directory, 'pull', '--ff-only'];

		if ($this->config->remote !== null) {
			$command[] = $this->config->remote;

			if ($this->config->branch !== null) {
				$command[] = $this->config->branch;
			}
		}

		$environment = [
			...getenv(),
			'GIT_TERMINAL_PROMPT' => '0',
			'GIT_SSH_COMMAND'     => 'ssh -o BatchMode=yes',
			'LC_ALL'              => 'C'
		];

		// Both streams go to one temp file, which can't fill up and block
		// git the way an unread pipe can.
		$log = tmpfile();

		if ($log === false) {
			return new PullResult(false, 'Unable to create a temp file for git\'s output.');
		}

		$process = @proc_open($command, [1 => $log, 2 => $log], $pipes, $directory, $environment);

		if ($process === false) {
			fclose($log);

			return new PullResult(false, sprintf('Could not start "%s".', $this->config->gitBinary));
		}

		$code = proc_close($process);

		rewind($log);
		$output = (string) stream_get_contents($log);
		fclose($log);

		return new PullResult($code === 0, trim($output));
	}
}
