<?php

/**
 * Message extractor.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Translation;

use IntlException;
use MessageFormatter;
use PhpToken;
use Blush\Field\Severity;
use Blush\Field\Violation;
use Blush\Support\Filesystem;

/**
 * Finds the messages an extension's PHP writes (D-784): every `->t()`
 * call with a key and the text beside it, read with PHP's tokenizer, so
 * `lang:extract` can write them to a catalog and `theme:check` and
 * `plugin:check` can say when the catalog is behind.
 *
 * A call is collected when its key and text are each one plain string
 * (`'…'` or `"…"` with nothing interpolated); `context:` is its note for
 * translators. A call without text (a key looked up alone) is left out.
 * Problems, as warnings: a key or text that isn't a plain string, so it
 * can't be collected (write it out, or make it an ICU `select`); a
 * `key:` or `text:` parameter beside the key or text it would replace,
 * which PHP refuses; one key with two texts; and text that isn't a valid
 * ICU message.
 *
 * Templates other engines read (Blade's `.blade.php`) aren't PHP to the
 * tokenizer, so their calls aren't found.
 */
final readonly class MessageExtractor
{
	/**
	 * Folders never read: dependencies, built files, and catalogs.
	 *
	 * @var list<string>
	 */
	private const array SKIPPED = ['vendor', 'node_modules', 'public', 'lang', 'tests'];

	public function __construct(private Filesystem $files = new Filesystem())
	{}

	/**
	 * Reads every PHP file under folders (relative to `$root`, or the
	 * whole of it), returning the messages by key, in the order found,
	 * and the problems.
	 *
	 * @param  list<string> $folders
	 * @return array{array<string, ExtractedMessage>, list<Violation>}
	 */
	public function extract(string $root, array $folders = []): array
	{
		$messages = [];
		$problems = [];

		foreach ($folders === [] ? [''] : $folders as $folder) {
			$base = rtrim($folder === '' ? $root : "{$root}/{$folder}", '/');

			foreach ($this->files->files($base, links: false) as $relative => $file) {
				$path = $folder === '' ? $relative : "{$folder}/{$relative}";

				if (! str_ends_with($relative, '.php') || str_ends_with($relative, '.blade.php') || in_array(explode('/', $path)[0], self::SKIPPED, true)) {
					continue;
				}

				$code = file_get_contents($file->getPathname());

				if ($code === false) {
					continue;
				}

				foreach (self::calls($code) as [$line, $key, $text, $context, $clash]) {
					$where = "{$path}:{$line}";

					if ($clash !== null) {
						$problems[] = new Violation($where, sprintf('It passes "%1$s:" as well as its %1$s, which PHP refuses; rename the parameter.', $clash), Severity::Warning);

						continue;
					}

					if ($text === null) {
						continue;
					}

					if ($key === false || $text === false) {
						$problems[] = new Violation($where, $key === false ? 'Its key isn\'t a plain string, so it can\'t be collected; write it out, or choose with an ICU select.' : 'Its text isn\'t a plain string, so it can\'t be collected.', Severity::Warning);

						continue;
					}

					if (! self::isValid($text)) {
						$problems[] = new Violation($where, sprintf('"%s" isn\'t a valid ICU message: %s', $key, $text), Severity::Warning);

						continue;
					}

					$found = $messages[$key] ?? null;

					if ($found === null) {
						$messages[$key] = new ExtractedMessage($key, $text, is_string($context) ? $context : null, $path, $line);
					} elseif ($found->text !== $text) {
						$problems[] = new Violation($where, sprintf('"%s" has other text at %s; one key is one message.', $key, $found->where()), Severity::Warning);
					}
				}
			}
		}

		return [$messages, $problems];
	}

	/**
	 * Returns whether text is a valid ICU message.
	 */
	private static function isValid(string $text): bool
	{
		try {
			new MessageFormatter(Translator::LAST, $text);

			return true;
		} catch (IntlException) {
			return false;
		}
	}

	/**
	 * Returns a file's `->t()` calls: the line, then the key, text, and
	 * context, each a string, `null` when it isn't given, or `false` when
	 * it isn't a plain string, and the name of a parameter given both by
	 * position and by name (`key` or `text`), else `null`.
	 *
	 * @return list<array{int, string|false, string|false|null, string|false|null, ?string}>
	 */
	private static function calls(string $code): array
	{
		$tokens = array_values(array_filter(PhpToken::tokenize($code), static fn (PhpToken $token): bool => ! $token->isIgnorable()));
		$calls  = [];
		$count  = count($tokens);

		for ($i = 0; $i < $count - 2; $i++) {
			if (! $tokens[$i]->is([T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR]) || $tokens[$i + 1]->text !== 't' || $tokens[$i + 2]->text !== '(') {
				continue;
			}

			$args = self::arguments($tokens, $i + 3);

			$key     = $args[0] ?? $args['key'] ?? null;
			$text    = $args[1] ?? $args['text'] ?? null;
			$context = $args['context'] ?? null;

			$clash = array_find_key(['key' => 0, 'text' => 1], static fn (int $position, string $name): bool => isset($args[$position], $args[$name]));

			$calls[] = [
				$tokens[$i + 1]->line,
				$key === null ? false : (self::literal($key) ?? false),
				$text === null ? null : self::literal($text),
				$context === null ? null : self::literal($context),
				$clash
			];
		}

		return $calls;
	}

	/**
	 * Returns a call's arguments from just after its `(`: positional ones
	 * by index, named ones by name, each as its tokens.
	 *
	 * @param  list<PhpToken> $tokens
	 * @return array<int|string, list<PhpToken>>
	 */
	private static function arguments(array $tokens, int $start): array
	{
		$args    = [];
		$current = [];
		$depth   = 0;
		$index   = 0;
		$count   = count($tokens);

		for ($i = $start; $i < $count; $i++) {
			$text = $tokens[$i]->text;

			if ($depth === 0 && ($text === ',' || $text === ')')) {
				if ($current !== []) {
					$named = count($current) > 2 && $current[0]->is(T_STRING) && $current[1]->text === ':';

					if ($named) {
						$args[$current[0]->text] = array_slice($current, 2);
					} else {
						$args[$index++] = $current;
					}
				}

				$current = [];

				if ($text === ')') {
					break;
				}

				continue;
			}

			$depth += match ($text) {
				'(', '[', '{', '${'   => 1,
				')', ']', '}'         => -1,
				default               => 0
			};

			$current[] = $tokens[$i];
		}

		return $args;
	}

	/**
	 * Returns an argument's string when it's one plain string, else
	 * `false`; `null` (the word) is no text.
	 *
	 * @param list<PhpToken> $tokens
	 */
	private static function literal(array $tokens): string|false|null
	{
		if (count($tokens) === 1 && strtolower($tokens[0]->text) === 'null') {
			return null;
		}

		if (count($tokens) !== 1 || ! $tokens[0]->is(T_CONSTANT_ENCAPSED_STRING)) {
			return false;
		}

		$quoted = $tokens[0]->text;
		$inner  = substr($quoted, 1, -1);

		return $quoted[0] === "'" ? strtr($inner, ['\\\\' => '\\', "\\'" => "'"]) : stripcslashes($inner);
	}
}
