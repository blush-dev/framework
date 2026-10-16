<?php

/**
 * Console prompts.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console;

use Closure;
use InvalidArgumentException;
use NoDiscard;

/**
 * Asks the user questions. When the console isn't interactive (`-n`, or
 * input isn't a terminal), every prompt returns its default without asking,
 * and a prompt with no default throws `InvalidInput`, so scripted runs fail
 * loudly instead of hanging.
 *
 * On a terminal (with `$keys`), prompts are drawn and redrawn in place as
 * keys are pressed (D-719): text with its default or a placeholder shown
 * dim and errors under it, a Yes/No toggle, a list to pick from with the
 * arrow keys that typing filters, and a list to pick several from with
 * Space. Each leaves one line with its answer, and Ctrl+C throws
 * `PromptCancelled`. Elsewhere they're asked a line at a time.
 */
final readonly class Prompt
{
	/**
	 * The most options a list shows at once.
	 */
	private const int VISIBLE = 7;

	/**
	 * @param resource $input
	 */
	public function __construct(
		private Output $output,
		private mixed $input,
		public bool $interactive = true,
		private ?KeyReader $keys = null
	) {
		if (! is_resource($input)) {
			throw new InvalidArgumentException('Prompt input must be a resource.');
		}
	}

	/**
	 * Builds prompts that read from standard input, interactive only when
	 * it's a terminal, and drawn when output is one too.
	 */
	public static function forTerminal(Output $output): self
	{
		$terminal = stream_isatty(STDIN);
		$drawn    = $terminal && $output->width !== null && PHP_OS_FAMILY !== 'Windows' && function_exists('shell_exec');

		return new self($output, STDIN, $terminal, $drawn ? new KeyReader(STDIN) : null);
	}

	/**
	 * Returns a copy that writes questions to other output.
	 */
	#[NoDiscard]
	public function withOutput(Output $output): self
	{
		return clone($this, ['output' => $output]);
	}

	/**
	 * Returns a copy with interactivity turned on or off.
	 */
	#[NoDiscard]
	public function withInteractive(bool $interactive): self
	{
		return clone($this, ['interactive' => $interactive]);
	}

	/**
	 * Asks a yes/no question.
	 *
	 * @throws InvalidInput When no answer can be read.
	 * @throws PromptCancelled When Ctrl+C is pressed at a drawn prompt.
	 */
	public function confirm(string $question, bool $default = false): bool
	{
		if ($this->interactive && $this->keys !== null) {
			return $this->toggle($question, $default);
		}

		$hint = $default ? 'Y/n' : 'y/N';

		$answer = $this->ask("{$question} [{$hint}]", $default ? 'yes' : 'no', static function (string $answer): ?string {
			return in_array(strtolower($answer), ['y', 'yes', 'n', 'no'], true) ? null : 'Please answer yes or no.';
		});

		return in_array(strtolower($answer), ['y', 'yes'], true);
	}

	/**
	 * Asks for text. An empty answer takes the default, and with a default
	 * of `''` there may be no answer. `$validate` returns an error message
	 * for an unacceptable answer (the question is asked again) or `null`
	 * to accept it; it isn't asked about no answer. On a terminal,
	 * `$placeholder` shows what an answer looks like while there's none
	 * and no default.
	 *
	 * @param  ?Closure(string): ?string $validate
	 * @throws InvalidInput When no answer can be read.
	 * @throws PromptCancelled When Ctrl+C is pressed at a drawn prompt.
	 */
	public function ask(string $question, ?string $default = null, ?Closure $validate = null, string $placeholder = ''): string
	{
		if (! $this->interactive) {
			return $default ?? throw new InvalidInput(sprintf('"%s" needs an answer, but the console is not interactive.', $question));
		}

		if ($this->keys !== null) {
			return $this->text($question, $default, $validate, $placeholder);
		}

		while (true) {
			$suffix = ($default ?? '') === '' || str_contains($question, '[') ? '' : " [{$default}]";

			$this->output->write($this->output->tone($question, Tone::Code) . "{$suffix} ", Verbosity::Quiet);

			$answer = $this->readLine() ?? throw new InvalidInput(sprintf('No answer was given for "%s".', $question));
			$answer = $answer === '' ? ($default ?? '') : $answer;

			$error = match (true) {
				$answer === '' && $default === null => 'An answer is required.',
				$answer === '' || $validate === null => null,
				default                             => $validate($answer)
			};

			if ($error === null) {
				return $answer;
			}

			$this->output->error($error);
		}
	}

	/**
	 * Asks the user to pick one of several choices, by number or by value.
	 *
	 * @param  list<string> $choices
	 * @throws InvalidInput When no answer can be read.
	 * @throws PromptCancelled When Ctrl+C is pressed at a drawn prompt.
	 */
	public function choice(string $question, array $choices, ?string $default = null): string
	{
		return $this->select($question, array_combine($choices, $choices), $default);
	}

	/**
	 * Asks the user to pick one option: values mapped to their labels.
	 * On a terminal it's a list the arrow keys move through and typing
	 * filters; elsewhere, a numbered list answered by number or value.
	 * Returns the value.
	 *
	 * @param  array<string, string> $options
	 * @throws InvalidInput When no answer can be read.
	 * @throws PromptCancelled When Ctrl+C is pressed at a drawn prompt.
	 */
	public function select(string $question, array $options, ?string $default = null): string
	{
		$options = self::options($options);

		if (! $this->interactive) {
			return $default ?? throw new InvalidInput(sprintf('"%s" needs an answer, but the console is not interactive.', $question));
		}

		if ($this->keys !== null) {
			return $this->pick($question, $options, $default);
		}

		$values = array_keys($options);

		foreach (array_values($options) as $index => $label) {
			$this->output->line(sprintf('  [%d] %s', $index + 1, $label), Verbosity::Quiet);
		}

		$answer = $this->ask($question, $default, static function (string $answer) use ($options, $values): ?string {
			return isset($options[$answer]) || isset($values[(int) $answer - 1]) || in_array($answer, $options, true)
				? null
				: 'Please pick one of the listed choices.';
		});

		return match (true) {
			isset($options[$answer])          => $answer,
			in_array($answer, $options, true) => (string) array_search($answer, $options, true),
			default                           => $values[(int) $answer - 1]
		};
	}

	/**
	 * Asks the user to pick one of many options (a timezone): values
	 * mapped to their labels. On a terminal it's a list the arrow keys
	 * move through and typing filters, as `select()` draws it; elsewhere
	 * the options aren't listed, and the answer is a value or a label,
	 * with `$problem` said for any other. Returns the value.
	 *
	 * @param  array<string, string> $options
	 * @throws InvalidInput When no answer can be read.
	 * @throws PromptCancelled When Ctrl+C is pressed at a drawn prompt.
	 */
	public function find(string $question, array $options, ?string $default = null, string $problem = 'That isn\'t one of the choices.'): string
	{
		$options = self::options($options);

		if (! $this->interactive || $this->keys !== null) {
			return $this->select($question, $options, $default);
		}

		$answer = $this->ask($question, $default, static fn (string $answer): ?string => isset($options[$answer]) || in_array($answer, $options, true) ? null : $problem);

		return isset($options[$answer]) ? $answer : (string) array_search($answer, $options, true);
	}

	/**
	 * Asks the user to pick any of several options: values mapped to
	 * their labels. On a terminal, Space picks and Enter is done;
	 * elsewhere, numbers or values separated by commas, and nothing keeps
	 * the defaults. Returns the values picked.
	 *
	 * @param  array<string, string> $options
	 * @param  list<string>          $defaults
	 * @return list<string>
	 * @throws InvalidInput When no answer can be read.
	 * @throws PromptCancelled When Ctrl+C is pressed at a drawn prompt.
	 */
	public function multiselect(string $question, array $options, array $defaults = []): array
	{
		$options = self::options($options);

		if (! $this->interactive) {
			return $defaults;
		}

		if ($this->keys !== null) {
			return $this->check($question, $options, $defaults);
		}

		$values = array_keys($options);

		foreach (array_values($options) as $index => $label) {
			$this->output->line(sprintf('  [%d] %s', $index + 1, $label), Verbosity::Quiet);
		}

		while (true) {
			$this->output->write($this->output->tone($question, Tone::Code) . ($defaults === [] ? '' : ' [' . implode(', ', $defaults) . ']') . ' ', Verbosity::Quiet);

			$answer = $this->readLine() ?? throw new InvalidInput(sprintf('No answer was given for "%s".', $question));

			if (trim($answer) === '') {
				return $defaults;
			}

			$picked = [];

			foreach (array_map(trim(...), explode(',', $answer)) as $part) {
				$value = match (true) {
					isset($options[$part])        => $part,
					isset($values[(int) $part - 1]) && ctype_digit($part) => $values[(int) $part - 1],
					default                       => null
				};

				if ($value === null) {
					$this->output->error(sprintf('"%s" isn\'t one of the listed choices.', $part));

					continue 2;
				}

				$picked[] = $value;
			}

			return array_values(array_unique($picked));
		}
	}

	/**
	 * Asks for a secret without echoing it, when input is a terminal.
	 *
	 * @throws InvalidInput When no answer can be read.
	 * @throws PromptCancelled When Ctrl+C is pressed at a drawn prompt.
	 */
	public function secret(string $question): string
	{
		if (! $this->interactive) {
			throw new InvalidInput(sprintf('"%s" needs an answer, but the console is not interactive.', $question));
		}

		if ($this->keys !== null) {
			return $this->text($question, null, null, '', mask: true);
		}

		$hide = stream_isatty($this->input) && PHP_OS_FAMILY !== 'Windows';

		$this->output->write($this->output->tone($question, Tone::Code) . ' ', Verbosity::Quiet);

		if ($hide) {
			shell_exec('stty -echo');
		}

		try {
			$answer = $this->readLine();
		} finally {
			if ($hide) {
				shell_exec('stty echo');
				$this->output->newLine(1, Verbosity::Quiet);
			}
		}

		return $answer ?? throw new InvalidInput(sprintf('No answer was given for "%s".', $question));
	}

	/**
	 * Asks for a new secret, such as a password, twice without echoing
	 * it, until the two match. `$validate` works as in `ask()`.
	 *
	 * @param  ?Closure(string): ?string $validate
	 * @throws InvalidInput When no answer can be read.
	 * @throws PromptCancelled When Ctrl+C is pressed at a drawn prompt.
	 */
	public function newSecret(string $question, string $repeat, ?Closure $validate = null): string
	{
		while (true) {
			$secret = $this->secret($question);
			$error  = $secret === '' ? 'An answer is required.' : ($validate === null ? null : $validate($secret));

			if ($error !== null) {
				$this->output->error($error);
				continue;
			}

			if (hash_equals($secret, $this->secret($repeat))) {
				return $secret;
			}

			$this->output->error('Those didn\'t match; try again.');
		}
	}

	/**
	 * Draws a text prompt: the answer as it's typed (or bullets for a
	 * secret), its default or placeholder dim while it's empty, and, once
	 * Enter has been pressed, what's wrong with it, checked as it changes.
	 *
	 * @param ?Closure(string): ?string $validate
	 */
	private function text(string $question, ?string $default, ?Closure $validate, string $placeholder, bool $mask = false): string
	{
		$frame = new Frame($this->output);
		$title = $this->title($question);
		$state = new PromptState();

		return $this->reading($frame, $question, function (Key $key) use ($frame, $title, $default, $validate, $mask, $state): ?string {
			if ($key->type === KeyType::Enter) {
				$answer       = $state->text === '' ? ($default ?? '') : $state->text;
				$state->error = match (true) {
					$answer === '' && $default === null && ! $mask => 'An answer is required.',
					$answer === '' || $validate === null          => null,
					default                                       => $validate($answer)
				};
				$state->tried = true;

				if ($state->error === null) {
					$frame->finish($this->answered($title, $mask ? str_repeat('•', min(8, mb_strlen($answer))) : $answer));

					return $answer;
				}

				return null;
			}

			[$state->text, $state->cursor] = self::edit($key, $state->text, $state->cursor);

			if ($state->tried) {
				$state->error = $state->text === '' || $validate === null ? null : $validate($state->text);
			}

			return null;
		}, function () use ($title, $default, $placeholder, $mask, $state): array {
			$shown = $mask ? str_repeat('•', mb_strlen($state->text)) : $state->text;
			$empty = ($default ?? '') === '' ? $placeholder : (string) $default;
			$lines = [$title . '  ' . ($state->text === '' && $empty !== '' ? $this->output->tone($empty, Tone::Muted) : $shown)];

			if ($state->error !== null) {
				$lines[] = '  ' . $this->output->tone($state->error, Tone::Failure);
			}

			return [$lines, [0, mb_strwidth($this->plain($title)) + 2 + mb_strwidth(mb_substr($shown, 0, $state->cursor))]];
		});
	}

	/**
	 * Draws a Yes/No toggle: the arrow keys, Tab, and Space switch it, `y`
	 * and `n` answer it, and Enter takes what's chosen.
	 */
	private function toggle(string $question, bool $default): bool
	{
		$frame = new Frame($this->output);
		$title = $this->title($question);
		$state = new PromptState(yes: $default);

		return $this->reading($frame, $question, function (Key $key) use ($frame, $title, $state): ?bool {
			$answer = match (true) {
				$key->type === KeyType::Enter          => $state->yes,
				in_array($key->char, ['y', 'Y'], true) => true,
				in_array($key->char, ['n', 'N'], true) => false,
				default                                => null
			};

			if ($answer !== null) {
				$frame->finish($this->answered($title, $answer ? 'Yes' : 'No'));

				return $answer;
			}

			if (in_array($key->type, [KeyType::Left, KeyType::Right, KeyType::Up, KeyType::Down, KeyType::Tab, KeyType::Space], true)) {
				$state->yes = ! $state->yes;
			}

			return null;
		}, function () use ($title, $state): array {
			$option = fn (string $label, bool $on): string => $on ? $this->output->tone("● {$label}", Tone::Code) : $this->output->tone("○ {$label}", Tone::Muted);

			return [[$title . '  ' . $option('Yes', $state->yes) . ' / ' . $option('No', ! $state->yes)], null];
		});
	}

	/**
	 * Draws a list to pick one option from.
	 *
	 * @param array<string, string> $options
	 */
	private function pick(string $question, array $options, ?string $default): string
	{
		$frame = new Frame($this->output);
		$title = $this->title($question);
		$state = new PromptState(index: max(0, (int) array_search($default, array_keys($options), true)));

		return $this->reading($frame, $question, function (Key $key) use ($frame, $title, $options, $state): ?string {
			$matches = self::matching($options, $state->text);

			if ($key->type === KeyType::Enter && $matches !== []) {
				$value = (string) array_keys($matches)[min($state->index, count($matches) - 1)];

				$frame->finish($this->answered($title, $options[$value]));

				return $value;
			}

			self::browse($key, $state, count($matches), typing: true);

			return null;
		}, function () use ($title, $options, $state): array {
			$matches      = self::matching($options, $state->text);
			$state->index = min($state->index, max(0, count($matches) - 1));

			return [$this->list($title, $state, $matches, false, '↑↓ to move, Enter to pick'), null];
		});
	}

	/**
	 * Draws a list to pick any number of options from.
	 *
	 * @param  array<string, string> $options
	 * @param  list<string>          $defaults
	 * @return list<string>
	 */
	private function check(string $question, array $options, array $defaults): array
	{
		$frame = new Frame($this->output);
		$title = $this->title($question);
		$state = new PromptState(picked: array_values(array_intersect(array_keys($options), $defaults)));

		return $this->reading($frame, $question, function (Key $key) use ($frame, $title, $options, $state): ?array {
			$matches = self::matching($options, $state->text);

			if ($key->type === KeyType::Enter) {
				$picked = array_values(array_filter(array_keys($options), static fn (string $value): bool => in_array($value, $state->picked, true)));

				$frame->finish($this->answered($title, $picked === [] ? 'None' : implode(', ', array_map(static fn (string $value): string => $options[$value], $picked))));

				return $picked;
			}

			if ($key->type === KeyType::Space) {
				if ($matches !== []) {
					$value         = (string) array_keys($matches)[min($state->index, count($matches) - 1)];
					$state->picked = in_array($value, $state->picked, true) ? array_values(array_diff($state->picked, [$value])) : [...$state->picked, $value];
				}

				return null;
			}

			self::browse($key, $state, count($matches), typing: true);

			return null;
		}, function () use ($title, $options, $state): array {
			$matches      = self::matching($options, $state->text);
			$state->index = min($state->index, max(0, count($matches) - 1));

			return [$this->list($title, $state, $matches, true, '↑↓ to move, Space to pick, Enter when done'), null];
		});
	}

	/**
	 * Reads keys until `$handle` returns an answer, drawing the frame
	 * before each, with the terminal in raw mode throughout. Ctrl+C
	 * leaves the question crossed out and throws `PromptCancelled`.
	 *
	 * @template T
	 * @param  Closure(Key): ?T                                              $handle
	 * @param  Closure(): array{list<string>, ?array{int, int}}              $render
	 * @return T
	 * @throws InvalidInput When input ends before an answer.
	 */
	private function reading(Frame $frame, string $question, Closure $handle, Closure $render): mixed
	{
		$keys = $this->keys ?? throw new InvalidInput('Prompts can\'t be drawn without a terminal.');

		$keys->start();

		try {
			while (true) {
				[$lines, $cursor] = $render();

				$frame->draw($lines, $cursor);

				$key = $keys->read();

				if ($key === null || $key->type === KeyType::Interrupt) {
					$frame->finish($this->output->tone('✗', Tone::Failure) . ' ' . $this->plain($this->title($question), strip: true));

					throw $key === null
						? new InvalidInput(sprintf('No answer was given for "%s".', $question))
						: new PromptCancelled('Cancelled.');
				}

				$answer = $handle($key);

				if ($answer !== null) {
					return $answer;
				}
			}
		} finally {
			$keys->stop();
		}
	}

	/**
	 * Returns a list's lines: the question and the filter typed, the
	 * options in view with the highlighted one marked (and checkboxes
	 * when `$boxes`), and a hint.
	 *
	 * @param  array<string, string> $matches
	 * @return list<string>
	 */
	private function list(string $title, PromptState $state, array $matches, bool $boxes, string $hint): array
	{
		$lines  = [$title . '  ' . ($state->text === '' ? $this->output->tone('Type to filter', Tone::Muted) : $state->text)];
		$top    = max(0, min($state->index - intdiv(self::VISIBLE, 2), count($matches) - self::VISIBLE));
		$width  = max(20, ($this->output->width ?? 80) - 8);
		$values = array_keys($matches);

		foreach (array_slice($values, $top, self::VISIBLE) as $offset => $value) {
			$box   = $boxes ? (in_array($value, $state->picked, true) ? '◼ ' : '◻ ') : '';
			$label = mb_strimwidth($box . $matches[$value], 0, $width, '…');

			$lines[] = $top + $offset === $state->index ? '  ' . $this->output->tone("› {$label}", Tone::Code) : "    {$label}";
		}

		if ($matches === []) {
			$lines[] = '    ' . $this->output->tone('No matches', Tone::Muted);
		}

		$more    = count($matches) - self::VISIBLE;
		$lines[] = '  ' . $this->output->tone($hint . ($more > 0 ? ", {$more} more" : ''), Tone::Muted);

		return $lines;
	}

	/**
	 * Returns a prompt's question, marked as asking.
	 */
	private function title(string $question): string
	{
		return $this->output->tone('?', Tone::Code) . ' ' . $this->output->tone(rtrim($question, ': '), Tone::Heading);
	}

	/**
	 * Returns a prompt's last line: its question, checked, and the answer.
	 */
	private function answered(string $title, string $answer): string
	{
		return $this->output->tone('✓', Tone::Pass) . ' ' . $this->plain($title, strip: true) . '  ' . $this->output->tone($answer, Tone::Muted);
	}

	/**
	 * Returns text without its escape codes, and without the asking mark
	 * when `$strip`.
	 */
	private function plain(string $text, bool $strip = false): string
	{
		$text = (string) preg_replace('/\033\[[\d;]*m/', '', $text);

		return $strip ? substr($text, 2) : $text;
	}

	/**
	 * Applies an editing key to text and the cursor's place in it.
	 *
	 * @return array{string, int}
	 */
	private static function edit(Key $key, string $value, int $cursor): array
	{
		$length = mb_strlen($value);

		return match ($key->type) {
			KeyType::Character, KeyType::Space => [mb_substr($value, 0, $cursor) . $key->char . mb_substr($value, $cursor), $cursor + mb_strlen($key->char)],
			KeyType::Backspace                 => $cursor === 0 ? [$value, 0] : [mb_substr($value, 0, $cursor - 1) . mb_substr($value, $cursor), $cursor - 1],
			KeyType::Delete                    => [mb_substr($value, 0, $cursor) . mb_substr($value, $cursor + 1), $cursor],
			KeyType::Left                      => [$value, max(0, $cursor - 1)],
			KeyType::Right                     => [$value, min($length, $cursor + 1)],
			KeyType::Home                      => [$value, 0],
			KeyType::End                       => [$value, $length],
			default                            => [$value, $cursor]
		};
	}

	/**
	 * Applies a key to a list: the arrows, Tab, Home, and End move through
	 * it, and typing (when `$typing`) changes the filter.
	 */
	private static function browse(Key $key, PromptState $state, int $count, bool $typing): void
	{
		$last = max(0, $count - 1);

		match (true) {
			$key->type === KeyType::Up                                  => $state->index = $state->index === 0 ? $last : $state->index - 1,
			$key->type === KeyType::Down, $key->type === KeyType::Tab   => $state->index = $state->index >= $last ? 0 : $state->index + 1,
			$key->type === KeyType::Home                                => $state->index = 0,
			$key->type === KeyType::End                                 => $state->index = $last,
			$key->type === KeyType::Backspace                           => [$state->text, $state->index] = [mb_substr($state->text, 0, -1), 0],
			$typing && in_array($key->type, [KeyType::Character, KeyType::Space], true) => [$state->text, $state->index] = [$state->text . $key->char, 0],
			default                                                     => null
		};
	}

	/**
	 * Returns the options whose label or value has the filter in it.
	 *
	 * @param  array<string, string> $options
	 * @return array<string, string>
	 */
	private static function matching(array $options, string $filter): array
	{
		return $filter === '' ? $options : array_filter(
			$options,
			static fn (string $label, string|int $value): bool => mb_stripos($label, $filter) !== false || mb_stripos((string) $value, $filter) !== false,
			ARRAY_FILTER_USE_BOTH
		);
	}

	/**
	 * Returns options keyed by their values: a list's values are their
	 * own labels.
	 *
	 * @param  array<array-key, string> $options
	 * @return array<string, string>
	 */
	private static function options(array $options): array
	{
		return array_is_list($options) ? array_combine($options, $options) : array_combine(array_map(strval(...), array_keys($options)), array_values($options));
	}

	/**
	 * Reads one line of input without its line ending, or `null` at the
	 * end of input.
	 */
	private function readLine(): ?string
	{
		$line = fgets($this->input);

		return $line === false ? null : rtrim($line, "\r\n");
	}
}
