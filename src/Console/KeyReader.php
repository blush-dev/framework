<?php

/**
 * Key reader.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console;

/**
 * Reads keys one at a time for the prompts drawn on a terminal (D-719).
 * With `$raw`, `start()` puts the terminal in raw mode (no line buffering,
 * echo, or signals, so Ctrl+C is a key) and `stop()` puts its settings
 * back; tests read keys from a stream without it.
 */
final class KeyReader
{
	/**
	 * The terminal's settings before `start()`.
	 */
	private string $saved = '';

	/**
	 * @param resource $input
	 */
	public function __construct(
		private readonly mixed $input,
		private readonly bool $raw = true
	) {}

	/**
	 * Starts reading keys as they're pressed.
	 */
	public function start(): void
	{
		if ($this->raw && $this->saved === '') {
			$this->saved = trim((string) shell_exec('stty -g 2>/dev/null'));
			shell_exec('stty -icanon -echo -isig 2>/dev/null');
		}
	}

	/**
	 * Puts the terminal back as it was.
	 */
	public function stop(): void
	{
		if ($this->saved !== '') {
			shell_exec('stty ' . escapeshellarg($this->saved) . ' 2>/dev/null');
			$this->saved = '';
		}
	}

	/**
	 * Reads the next key, or `null` at the end of input, which Ctrl+D
	 * is too, as it is for a line typed.
	 */
	public function read(): ?Key
	{
		$byte = fread($this->input, 1);

		if ($byte === false || $byte === '' || $byte === "\x04") {
			return null;
		}

		return match (true) {
			$byte === "\033"                 => $this->escape(),
			$byte === "\r" || $byte === "\n" => new Key(KeyType::Enter),
			$byte === ' '                    => new Key(KeyType::Space, ' '),
			$byte === "\x7f" || $byte === "\x08" => new Key(KeyType::Backspace),
			$byte === "\t"                   => new Key(KeyType::Tab),
			$byte === "\x03"                 => new Key(KeyType::Interrupt),
			ord($byte) < 32                  => new Key(KeyType::Other),
			ord($byte) >= 0xC0               => new Key(KeyType::Character, $byte . $this->continuation($byte)),
			default                          => new Key(KeyType::Character, $byte)
		};
	}

	/**
	 * Reads what follows an escape, up to the sequence's last byte, so
	 * keys typed after it are left to read: an arrow, Home, End, or
	 * Delete, or the escape key alone.
	 */
	private function escape(): Key
	{
		stream_set_blocking($this->input, false);

		$sequence = (string) fread($this->input, 1);

		if ($sequence === 'O') {
			$sequence .= (string) fread($this->input, 1);
		} elseif ($sequence === '[') {
			do {
				$byte      = (string) fread($this->input, 1);
				$sequence .= $byte;
			} while ($byte !== '' && ord($byte) < 0x40);
		}

		stream_set_blocking($this->input, true);

		return match ($sequence) {
			'[A', 'OA'             => new Key(KeyType::Up),
			'[B', 'OB'             => new Key(KeyType::Down),
			'[C', 'OC'             => new Key(KeyType::Right),
			'[D', 'OD'             => new Key(KeyType::Left),
			'[H', 'OH', '[1~'      => new Key(KeyType::Home),
			'[F', 'OF', '[4~'      => new Key(KeyType::End),
			'[3~'                  => new Key(KeyType::Delete),
			''                     => new Key(KeyType::Escape),
			default                => new Key(KeyType::Other)
		};
	}

	/**
	 * Reads the rest of a UTF-8 character after its first byte.
	 */
	private function continuation(string $lead): string
	{
		$length = match (true) {
			ord($lead) >= 0xF0 => 3,
			ord($lead) >= 0xE0 => 2,
			default            => 1
		};

		return (string) fread($this->input, $length);
	}
}
