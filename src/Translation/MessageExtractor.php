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
 *
 * Scripts (`.ts`, `.js`, and `.vue` files, D-831) are read for `t()`
 * calls the same way (`extractScripts()`): `t('key', 'Text', params)`,
 * as `blush/messages` gives it, with comments skipped. JavaScript has no
 * named arguments, so a script's message has no note for translators.
 * A `messages()` call naming a domain other than the extension's is a
 * warning: its text would be looked for in another's catalog.
 */
final readonly class MessageExtractor
{
	/**
	 * Folders never read: dependencies, built files, and catalogs.
	 *
	 * @var list<string>
	 */
	private const array SKIPPED = ['vendor', 'node_modules', 'public', 'lang', 'tests'];

	/**
	 * The script files read, by extension.
	 *
	 * @var list<string>
	 */
	public const array SCRIPTS = ['ts', 'js', 'mjs', 'vue'];

	/**
	 * A JavaScript string literal: quoted, or a template without `${`.
	 */
	private const string JS_STRING = '\'(?:[^\'\\\\\n]|\\\\.)*\'|"(?:[^"\\\\\n]|\\\\.)*"|`(?:[^`\\\\$]|\\\\.|\$(?!\{))*`';

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

					$this->collect($messages, $problems, $where, $path, $line, $key, $text, $context);
				}
			}
		}

		return [$messages, $problems];
	}

	/**
	 * Reads every script under folders (relative to `$root`), returning
	 * the messages by key, in the order found, and the problems, as
	 * `extract()` does; a `messages()` call naming a domain other than
	 * `$domain` is a problem too.
	 *
	 * @param  list<string> $folders
	 * @return array{array<string, ExtractedMessage>, list<Violation>}
	 */
	public function extractScripts(string $root, array $folders, string $domain): array
	{
		$messages = [];
		$problems = [];

		foreach ($folders as $folder) {
			$base = rtrim("{$root}/{$folder}", '/');

			if (! is_dir($base)) {
				continue;
			}

			foreach ($this->files->files($base, links: false) as $relative => $file) {
				$path      = "{$folder}/{$relative}";
				$extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));

				if (! in_array($extension, self::SCRIPTS, true) || str_ends_with($relative, '.d.ts') || array_intersect(explode('/', $relative), ['node_modules', 'dist']) !== []) {
					continue;
				}

				$code = file_get_contents($file->getPathname());

				if ($code === false) {
					continue;
				}

				$code = $extension === 'vue' ? self::uncommentVue($code) : self::uncommentScript($code);

				foreach (self::domains($code) as [$line, $named]) {
					if ($named !== $domain) {
						$problems[] = new Violation("{$path}:{$line}", sprintf('It reads the "%s" domain\'s messages; an extension\'s scripts read their own, "%s".', $named, $domain), Severity::Warning);
					}
				}

				foreach (self::scriptCalls($code) as [$line, $key, $text]) {
					$this->collect($messages, $problems, "{$path}:{$line}", $path, $line, $key, $text, null);
				}
			}
		}

		return [$messages, $problems];
	}

	/**
	 * Adds a call's message, or the problem with it.
	 *
	 * @param array<string, ExtractedMessage> $messages
	 * @param list<Violation>                 $problems
	 */
	private function collect(array &$messages, array &$problems, string $where, string $path, int $line, string|false $key, string|false|null $text, string|false|null $context): void
	{
		if ($text === null) {
			return;
		}

		if ($key === false || $text === false) {
			$problems[] = new Violation($where, $key === false ? 'Its key isn\'t a plain string, so it can\'t be collected; write it out, or choose with an ICU select.' : 'Its text isn\'t a plain string, so it can\'t be collected.', Severity::Warning);

			return;
		}

		if (! self::isValid($text)) {
			$problems[] = new Violation($where, sprintf('"%s" isn\'t a valid ICU message: %s', $key, $text), Severity::Warning);

			return;
		}

		$found = $messages[$key] ?? null;

		if ($found === null) {
			$messages[$key] = new ExtractedMessage($key, $text, is_string($context) ? $context : null, $path, $line);
		} elseif ($found->text !== $text) {
			$problems[] = new Violation($where, sprintf('"%s" has other text at %s; one key is one message.', $key, $found->where()), Severity::Warning);
		}
	}

	/**
	 * Returns a script's `t()` calls: the line, then the key and text,
	 * each a string, `null` when it isn't given, or `false` when it isn't
	 * a plain string.
	 *
	 * @return list<array{int, string|false, string|false|null}>
	 */
	private static function scriptCalls(string $code): array
	{
		$calls = [];

		preg_match_all('/(?<![\w$.])t\(/', $code, $matches, PREG_OFFSET_CAPTURE);

		foreach ($matches[0] as [$match, $offset]) {
			$args = self::scriptArguments($code, $offset + strlen($match));

			if ($args === []) {
				continue;
			}

			$calls[] = [
				substr_count($code, "\n", 0, $offset) + 1,
				self::scriptLiteral($args[0]) ?? false,
				isset($args[1]) ? self::scriptLiteral($args[1]) : null
			];
		}

		return $calls;
	}

	/**
	 * Returns the domains a script's `messages()` calls name: the line
	 * and the domain, for each one written as a plain string.
	 *
	 * @return list<array{int, string}>
	 */
	private static function domains(string $code): array
	{
		$found = [];

		preg_match_all('/(?<![\w$.])messages\(\s*(' . self::JS_STRING . ')\s*\)/', $code, $matches, PREG_OFFSET_CAPTURE);

		foreach ($matches[1] as [$literal, $offset]) {
			$domain = self::scriptLiteral($literal);

			if (is_string($domain)) {
				$found[] = [substr_count($code, "\n", 0, $offset) + 1, $domain];
			}
		}

		return $found;
	}

	/**
	 * Returns a script call's arguments from just after its `(`, each as
	 * its trimmed source, split at the commas outside strings and
	 * brackets.
	 *
	 * @return list<string>
	 */
	private static function scriptArguments(string $code, int $start): array
	{
		$args    = [];
		$current = '';
		$depth   = 0;
		$length  = strlen($code);

		for ($i = $start; $i < $length; $i++) {
			$char = $code[$i];

			if ($char === '\'' || $char === '"' || $char === '`') {
				// A template with `${}` in it is read whole, to be reported.
				if (preg_match('/\G(?:' . self::JS_STRING . '|`(?:[^`\\\\]|\\\\.)*`)/', $code, $match, 0, $i) !== 1) {
					return [];
				}

				$current .= $match[0];
				$i       += strlen($match[0]) - 1;

				continue;
			}

			if ($depth === 0 && ($char === ',' || $char === ')')) {
				if (trim($current) !== '') {
					$args[] = trim($current);
				}

				if ($char === ')') {
					return $args;
				}

				$current = '';

				continue;
			}

			$depth   += match ($char) {
				'(', '[', '{' => 1,
				')', ']', '}' => -1,
				default       => 0
			};
			$current .= $char;
		}

		return [];
	}

	/**
	 * Returns an argument's string when it's one plain string literal,
	 * else `false`; `null` and `undefined` are no text.
	 */
	private static function scriptLiteral(string $source): string|false|null
	{
		if ($source === 'null' || $source === 'undefined') {
			return null;
		}

		if (preg_match('/^(?:' . self::JS_STRING . ')$/', $source) !== 1) {
			return false;
		}

		return preg_replace_callback(
			'/\\\\(u\{[0-9a-fA-F]+\}|u[0-9a-fA-F]{4}|x[0-9a-fA-F]{2}|\r?\n|.)/s',
			static fn (array $match): string => match (true) {
				str_starts_with($match[1], 'u{')                       => mb_chr((int) hexdec(substr($match[1], 2, -1))) ?: '',
				$match[1][0] === 'u' && strlen($match[1]) === 5         => mb_chr((int) hexdec(substr($match[1], 1))) ?: '',
				$match[1][0] === 'x' && strlen($match[1]) === 3         => mb_chr((int) hexdec(substr($match[1], 1))) ?: '',
				$match[1] === "\n" || $match[1] === "\r\n"             => '',
				default                                                => ['n' => "\n", 't' => "\t", 'r' => "\r", '0' => "\0", 'b' => "\x08", 'f' => "\f", 'v' => "\v"][$match[1]] ?? $match[1]
			},
			substr($source, 1, -1)
		) ?? false;
	}

	/**
	 * Blanks a script's comments, keeping its line breaks, so calls in
	 * them aren't read and the lines of the rest stay where they are.
	 */
	private static function uncommentScript(string $code): string
	{
		return preg_replace_callback(
			'~(' . self::JS_STRING . ')|//[^\n]*|/\*.*?\*/~s',
			static fn (array $match): string => ($match[1] ?? '') !== '' ? $match[0] : preg_replace('/[^\n]/', ' ', $match[0]) ?? '',
			$code
		) ?? $code;
	}

	/**
	 * Blanks a single-file component's comments: its scripts' as a
	 * script's, and the template's `<!-- -->`, whose text (where an
	 * apostrophe is just one) is otherwise left as it is.
	 */
	private static function uncommentVue(string $code): string
	{
		$code = preg_replace_callback('/<!--.*?-->/s', static fn (array $match): string => preg_replace('/[^\n]/', ' ', $match[0]) ?? '', $code) ?? $code;

		return preg_replace_callback(
			'~(<script\b[^>]*>)(.*?)(</script>)~s',
			static fn (array $match): string => $match[1] . self::uncommentScript($match[2]) . $match[3],
			$code
		) ?? $code;
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
