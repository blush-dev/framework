<?php

/**
 * Fatal error.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Error;

use ErrorException;
use Blush\Core\BlushException;

/**
 * A fatal error (out of memory, a parse error in an included file, and so on)
 * caught at shutdown. PHP 8.5 records a backtrace for fatal errors
 * (`fatal_error_backtraces`); it's kept in `fatalTrace`, since an exception's
 * own trace can't be set after the fact.
 */
final class FatalError extends ErrorException implements BlushException
{
	/**
	 * @param list<array<string, mixed>> $fatalTrace
	 */
	public function __construct(
		string $message,
		int $severity,
		string $file,
		int $line,
		public readonly array $fatalTrace = []
	) {
		parent::__construct($message, 0, $severity, $file, $line);
	}

	/**
	 * Builds the error from `error_get_last()`.
	 *
	 * @param array<array-key, mixed> $error
	 */
	public static function fromLastError(array $error): self
	{
		$trace = [];

		foreach (is_array($error['trace'] ?? null) ? $error['trace'] : [] as $frame) {
			if (is_array($frame)) {
				$trace[] = array_filter($frame, is_string(...), ARRAY_FILTER_USE_KEY);
			}
		}

		return new self(
			is_string($error['message'] ?? null) ? $error['message'] : 'Fatal error',
			is_int($error['type'] ?? null) ? $error['type'] : E_ERROR,
			is_string($error['file'] ?? null) ? $error['file'] : 'unknown',
			is_int($error['line'] ?? null) ? $error['line'] : 0,
			$trace
		);
	}

	/**
	 * Formats the fatal backtrace like `getTraceAsString()`.
	 */
	public function fatalTraceAsString(): string
	{
		$lines = [];

		foreach ($this->fatalTrace as $index => $frame) {
			$function = is_string($frame['function'] ?? null) ? $frame['function'] : '{main}';
			$class    = is_string($frame['class'] ?? null) ? $frame['class'] : '';
			$type     = is_string($frame['type'] ?? null) ? $frame['type'] : '';
			$file     = is_string($frame['file'] ?? null) ? $frame['file'] : '[internal function]';
			$line     = is_int($frame['line'] ?? null) ? ":{$frame['line']}" : '';

			$lines[] = "#{$index} {$file}{$line}: {$class}{$type}{$function}()";
		}

		return implode("\n", $lines);
	}
}
