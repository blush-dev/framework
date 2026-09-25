<?php

/**
 * Logger.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Log;

use DateTimeInterface;
use Override;
use Stringable;
use Throwable;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

/**
 * The framework's PSR-3 logger. Messages below the minimum level are dropped;
 * the rest are formatted as one line each and handed to a writer:
 *
 *     [2026-09-25T10:00:00+00:00] blush.ERROR: Page "about" failed {"id":"about"}
 *
 * `{placeholder}` tokens in the message are replaced from the context, per
 * PSR-3. Context values that weren't used as placeholders are appended as
 * JSON. An `exception` in the context is appended with its class, message,
 * location, and trace.
 */
final class Logger implements LoggerInterface
{
	use LoggerTrait;

	public function __construct(
		private readonly LogWriter $writer,
		private readonly ClockInterface $clock,
		private readonly Level $threshold = Level::Debug,
		private readonly string $channel = 'blush'
	) {
	}

	/**
	 * @inheritDoc
	 *
	 * @param array<array-key, mixed> $context
	 * @throws InvalidLogLevel When the level is not a PSR-3 level.
	 */
	#[Override]
	public function log(mixed $level, string|Stringable $message, array $context = []): void
	{
		$level = $level instanceof Level ? $level : $this->level($level);

		if (! $level->passes($this->threshold)) {
			return;
		}

		$this->writer->write($level, $this->format($level, (string) $message, $context));
	}

	/**
	 * Converts a PSR-3 level string to a `Level`.
	 *
	 * @throws InvalidLogLevel
	 */
	private function level(mixed $level): Level
	{
		$case = is_string($level) ? Level::tryFrom(strtolower($level)) : null;

		if ($case === null) {
			throw new InvalidLogLevel(sprintf(
				'"%s" is not a PSR-3 log level.',
				is_scalar($level) ? (string) $level : get_debug_type($level)
			));
		}

		return $case;
	}

	/**
	 * Formats one log line.
	 *
	 * @param array<array-key, mixed> $context
	 */
	private function format(Level $level, string $message, array $context): string
	{
		$exception = $context['exception'] ?? null;
		unset($context['exception']);

		$used = [];

		$message = preg_replace_callback(
			'/\{([A-Za-z0-9_.]+)\}/',
			function (array $match) use ($context, &$used): string {
				if (! array_key_exists($match[1], $context)) {
					return $match[0];
				}

				$used[$match[1]] = true;

				return $this->stringify($context[$match[1]]);
			},
			$message
		) ?? $message;

		$line = sprintf(
			'[%s] %s.%s: %s',
			$this->clock->now()->format(DateTimeInterface::ATOM),
			$this->channel,
			strtoupper($level->value),
			$message
		);

		$extra = array_diff_key($context, $used);

		if ($extra !== []) {
			$line .= ' ' . $this->json(array_map($this->normalize(...), $extra));
		}

		if ($exception instanceof Throwable) {
			$line .= "\n" . $this->describe($exception);
		}

		return $line;
	}

	/**
	 * Converts a context value to the string used in its placeholder.
	 */
	private function stringify(mixed $value): string
	{
		return match (true) {
			$value === null => 'null',
			is_bool($value) => $value ? 'true' : 'false',
			$value instanceof Throwable => $value::class . ': ' . $value->getMessage(),
			is_scalar($value), $value instanceof Stringable => (string) $value,
			$value instanceof DateTimeInterface => $value->format(DateTimeInterface::ATOM),
			is_object($value) => '[object ' . $value::class . ']',
			is_array($value) => $this->json(array_map($this->normalize(...), $value)),
			default => '[' . get_debug_type($value) . ']'
		};
	}

	/**
	 * Converts a context value to something JSON can encode faithfully.
	 */
	private function normalize(mixed $value): mixed
	{
		return match (true) {
			$value === null, is_scalar($value) => $value,
			is_array($value) => array_map($this->normalize(...), $value),
			default => $this->stringify($value)
		};
	}

	/**
	 * Encodes a value as compact JSON, never failing.
	 */
	private function json(mixed $value): string
	{
		return json_encode(
			$value,
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
		) ?: '{}';
	}

	/**
	 * Describes an exception and its chain for the log.
	 */
	private function describe(Throwable $exception): string
	{
		$lines = [];

		do {
			$lines[] = sprintf(
				'%s: %s in %s:%d',
				$exception::class,
				$exception->getMessage(),
				$exception->getFile(),
				$exception->getLine()
			);
			$lines[] = $exception->getTraceAsString();

			$exception = $exception->getPrevious();

			if ($exception !== null) {
				$lines[] = 'Caused by:';
			}
		} while ($exception !== null);

		return implode("\n", $lines);
	}
}
