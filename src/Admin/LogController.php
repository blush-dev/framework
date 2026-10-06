<?php

/**
 * Admin log controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Auth\Account;
use Blush\Auth\Capability;
use Blush\Auth\Permissions;
use Blush\Core\Paths;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Log\LogConfig;

/**
 * The site's log, read only, on the Tools screen (D-540), for when
 * something failed on a host with no shell. Both need `site.logs`
 * (D-541), since a log can hold paths and error details.
 *
 * - `GET {path}/api/logs`: the `driver`, the log `file` (relative to the
 *   site, `null` unless the driver writes one), its `size` in bytes, and
 *   its last `ENTRIES` entries, newest first. An entry is one of the
 *   logger's `[time] channel.LEVEL: message` lines, as its `time`,
 *   `channel`, `level`, and `message`, with the lines under it (an
 *   exception and its trace) as its `details`.
 * - `GET {path}/api/logs/download`: the whole file, to save.
 */
final readonly class LogController
{
	/**
	 * The most entries the screen shows.
	 */
	public const int ENTRIES = 50;

	/**
	 * The most bytes read from the end of the file to find them; a trace
	 * can run to a hundred lines.
	 */
	private const int TAIL = 1048576;

	public function __construct(
		private LogConfig $config,
		private Paths $paths,
		private Permissions $permissions
	) {}

	/**
	 * Answers with the log's last lines.
	 */
	public function show(ServerRequestInterface $request): ResponseInterface
	{
		$refusal = $this->refusal($request);

		if ($refusal !== null) {
			return $refusal;
		}

		$path = $this->config->path($this->paths->logs);
		$size = $path !== null && is_file($path) ? (int) filesize($path) : 0;

		return self::json([
			'driver'  => $this->config->driver->value,
			'file'    => $path === null ? null : $this->paths->relative($path),
			'size'    => $size,
			'entries' => $path === null || $size === 0 ? [] : self::entries(self::tail($path, $size))
		]);
	}

	/**
	 * Answers with the whole log file, to save.
	 */
	public function download(ServerRequestInterface $request): ResponseInterface
	{
		$refusal = $this->refusal($request);

		if ($refusal !== null) {
			return $refusal;
		}

		$path = $this->config->path($this->paths->logs);

		if ($path === null || ! is_file($path) || ! is_readable($path)) {
			return self::json(['error' => 'There\'s no log file to download.'], Status::NotFound);
		}

		return Response::file($path, 'text/plain; charset=utf-8', [
			'Content-Disposition' => sprintf('attachment; filename="%s"', basename($path)),
			'Cache-Control'       => 'no-store'
		]);
	}

	/**
	 * Returns a refusal when the account may not read the log, or `null`.
	 */
	private function refusal(ServerRequestInterface $request): ?ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account) {
			return self::json(['error' => 'Sign in first.'], Status::Unauthorized);
		}

		return $this->permissions->can($account, Capability::SiteLogs)
			? null
			: self::json(['error' => 'You aren\'t allowed to read the log.'], Status::Forbidden);
	}

	/**
	 * Returns the last lines of a file, reading only its end.
	 *
	 * @return list<string>
	 */
	private static function tail(string $path, int $size): array
	{
		$handle = fopen($path, 'rb');

		if ($handle === false) {
			return [];
		}

		$start = max(0, $size - self::TAIL);
		fseek($handle, $start);
		$text = (string) stream_get_contents($handle);
		fclose($handle);

		$lines = preg_split('/\R/', rtrim($text, "\r\n")) ?: [];

		// A read that starts mid-file starts mid-line.
		if ($start > 0) {
			array_shift($lines);
		}

		return $lines;
	}

	/**
	 * Groups lines into entries, newest first: each starts at a line the
	 * logger began, and a line before the first of those (the end of an
	 * entry cut off by the read) is dropped.
	 *
	 * @param  list<string> $lines
	 * @return list<array{time: string, channel: string, level: string, message: string, details: string}>
	 */
	private static function entries(array $lines): array
	{
		$entries = [];
		$details = [];

		foreach (array_reverse($lines) as $line) {
			if (preg_match('/^\[([^\]]+)\] ([^ .]+)\.([A-Z]+): ?(.*)$/', $line, $match) !== 1) {
				$details[] = $line;
				continue;
			}

			$entries[] = [
				'time'    => $match[1],
				'channel' => $match[2],
				'level'   => strtolower($match[3]),
				'message' => $match[4],
				'details' => implode("\n", array_reverse($details))
			];
			$details   = [];

			if (count($entries) === self::ENTRIES) {
				break;
			}
		}

		return $entries;
	}

	/**
	 * Returns a JSON answer the browser won't cache.
	 *
	 * @param array<string, mixed> $data
	 */
	private static function json(array $data, Status $status = Status::Ok): ResponseInterface
	{
		return Response::json($data, $status, ['Cache-Control' => 'no-store']);
	}
}
