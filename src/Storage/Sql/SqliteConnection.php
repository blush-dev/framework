<?php

/**
 * SQLite connection.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Storage\Sql;

use PDO;
use PDOException;
use Pdo\Sqlite;
use Throwable;
use Blush\Storage\Record\ArrayEvaluator;
use Blush\Storage\Record\RecordStoreFailure;

/**
 * Opens SQLite databases for the record layer, with what `SqliteDialect`
 * needs registered on each connection: `blush_fold()` (`mb_strtolower()`),
 * `blush_like()` (`ArrayEvaluator`'s `like`), `blush_json()` (a list's
 * or map's JSON as PHP encodes it), and `blush_get()` (a value by a key
 * no JSON path can name).
 *
 * SQLite comes with PHP's `pdo_sqlite`, linked to the host's SQLite, so
 * whether it's there, and has its JSON functions, is asked (`available()`)
 * rather than assumed (D-640).
 */
final class SqliteConnection
{
	/**
	 * Returns whether PHP can open SQLite databases with JSON functions.
	 */
	public static function available(): bool
	{
		if (! class_exists(Sqlite::class)) {
			return false;
		}

		try {
			$statement = new Sqlite('sqlite::memory:')->query("SELECT json_type('{\"a\":[1]}', '$.a')");

			return $statement !== false && $statement->fetchColumn() === 'array';
		} catch (Throwable) {
			return false;
		}
	}

	/**
	 * Opens a database file, made if it doesn't exist, or `:memory:`. A
	 * file written in place keeps a write-ahead log, so reads don't wait
	 * on writes; one swapped in whole (`$wal` off) keeps none beside it.
	 *
	 * @throws RecordStoreFailure When it can't be opened.
	 */
	public static function open(string $path, bool $wal = true): Sqlite
	{
		try {
			$pdo = new Sqlite("sqlite:{$path}", options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
		} catch (PDOException $e) {
			throw new RecordStoreFailure(sprintf('The SQLite database %s couldn\'t be opened: %s', $path, $e->getMessage()), 0, $e);
		}

		$patterns = [];

		$pdo->createFunction('blush_fold', static fn (mixed $text): mixed => is_string($text) ? mb_strtolower($text) : $text, 1, Sqlite::DETERMINISTIC);
		$pdo->createFunction('blush_like', static function (mixed $text, mixed $pattern) use (&$patterns): int {
			if (! is_string($text) || ! is_string($pattern)) {
				return 0;
			}

			$patterns[$pattern] ??= ArrayEvaluator::likePattern($pattern);

			return preg_match($patterns[$pattern], $text) === 1 ? 1 : 0;
		}, 2, Sqlite::DETERMINISTIC);
		$pdo->createFunction('blush_json', static fn (mixed $json): mixed => is_string($json) ? json_encode(json_decode($json, true)) : $json, 1, Sqlite::DETERMINISTIC);
		$pdo->createFunction('blush_get', static function (mixed $fields, mixed $key): ?string {
			$decoded = is_string($fields) ? json_decode($fields, true) : null;
			$value   = is_array($decoded) && is_string($key) ? ArrayEvaluator::value(['fields' => $decoded], $key) : null;

			$json = $value === null ? false : json_encode($value, JSON_PRESERVE_ZERO_FRACTION);

			return $json === false ? null : $json;
		}, 2, Sqlite::DETERMINISTIC);

		if ($path !== ':memory:' && $wal) {
			$pdo->exec('PRAGMA journal_mode = WAL');
			$pdo->exec('PRAGMA synchronous = NORMAL');
		}

		return $pdo;
	}
}
