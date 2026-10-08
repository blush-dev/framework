<?php

/**
 * Site Health problem keys.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

/**
 * Each problem a Site Health check finds has a key (D-613), the same
 * from one check to the next, which ignoring keeps and the admin's rows
 * use (`resources/admin/js/health.ts` builds the same keys). A key
 * starts with its area and check:
 *
 * - `{area}:files:{path}:{field}:{message}`: a file's problem.
 * - `{area}:ids:{path}`: a file with no valid id; `{area}:ids-shared:{id}`,
 *   an id files share.
 * - `content:terms:{type}/{slug}`, `content:refs:{path}`,
 *   `content:folders:{path}`, `content:names:{path}`, and
 *   `content:taxonomies:{name}`.
 * - `media:sizes:{key}`: an image whose sizes aren't listed as they are.
 */
final class ProblemKeys
{
	public static function file(string $area, string $path, string $field, string $message): string
	{
		return "{$area}:files:{$path}:{$field}:{$message}";
	}

	public static function id(string $area, string $path): string
	{
		return "{$area}:ids:{$path}";
	}

	public static function sharedId(string $area, string $id): string
	{
		return "{$area}:ids-shared:{$id}";
	}

	public static function of(string $area, string $check, string $item): string
	{
		return "{$area}:{$check}:{$item}";
	}

	/**
	 * Returns the key of every problem in a `ContentHealth` report.
	 *
	 * @param  array<array-key, mixed> $report
	 * @return list<string>
	 */
	public static function all(array $report): array
	{
		$keys = [];

		foreach (self::lists($report, 'files') as $file) {
			foreach (is_array($file['violations'] ?? null) ? $file['violations'] : [] as $violation) {
				if (is_array($violation)) {
					$keys[] = self::file(self::text($file['area'] ?? ''), self::text($file['path'] ?? ''), self::text($violation['field'] ?? ''), self::text($violation['message'] ?? ''));
				}
			}
		}

		foreach (['content' => 'ids', 'media' => 'mediaIds'] as $area => $name) {
			$ids = is_array($report[$name] ?? null) ? $report[$name] : [];

			foreach (is_array($ids['missing'] ?? null) ? $ids['missing'] : [] as $path) {
				$keys[] = self::id($area, self::text($path));
			}

			foreach (is_array($ids['duplicates'] ?? null) ? $ids['duplicates'] : [] as $shared) {
				$keys[] = self::sharedId($area, self::text(is_array($shared) ? ($shared['id'] ?? '') : ''));
			}
		}

		foreach (self::items($report, 'terms') as $item) {
			$keys[] = self::of('content', 'terms', self::text($item['type'] ?? '') . '/' . self::text($item['slug'] ?? ''));
		}

		foreach (['refs' => 'refs', 'folders' => 'folders'] as $name => $check) {
			foreach (self::items($report, $name) as $item) {
				$keys[] = self::of('content', $check, self::text($item['path'] ?? ''));
			}
		}

		foreach (self::lists($report, 'fileNames') as $names) {
			foreach (is_array($names['items'] ?? null) ? $names['items'] : [] as $item) {
				$keys[] = self::of('content', 'names', self::text(is_array($item) ? ($item['path'] ?? '') : ''));
			}
		}

		foreach (is_array($report['taxonomies'] ?? null) ? $report['taxonomies'] : [] as $name) {
			$keys[] = self::of('content', 'taxonomies', self::text($name));
		}

		foreach (self::items($report, 'mediaSizes') as $item) {
			$keys[] = self::of('media', 'sizes', self::text($item['key'] ?? ''));
		}

		return $keys;
	}

	/**
	 * Returns a report's list of arrays under a key.
	 *
	 * @param  array<array-key, mixed> $report
	 * @return list<array<array-key, mixed>>
	 */
	private static function lists(array $report, string $key): array
	{
		return array_values(array_filter(is_array($report[$key] ?? null) ? $report[$key] : [], is_array(...)));
	}

	/**
	 * Returns the `items` of a report's part.
	 *
	 * @param  array<array-key, mixed> $report
	 * @return list<array<array-key, mixed>>
	 */
	private static function items(array $report, string $key): array
	{
		$part = is_array($report[$key] ?? null) ? $report[$key] : [];

		return array_values(array_filter(is_array($part['items'] ?? null) ? $part['items'] : [], is_array(...)));
	}

	private static function text(mixed $value): string
	{
		return is_scalar($value) ? (string) $value : '';
	}
}
