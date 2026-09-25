<?php

/**
 * Request factory.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Http;

/**
 * Builds the current request from PHP's globals. `fromGlobals()` is the only
 * code in Blush that reads superglobals; everything downstream works on the
 * `Request` it returns, which is what lets `Kernel::handle()` run anywhere.
 *
 * The URI's scheme, host, and port come from the server as PHP sees it.
 * Forwarded headers from proxies are not trusted here; the `TrustProxies`
 * middleware will apply them when configured.
 */
final class RequestFactory
{
	/**
	 * Builds the request from `$_SERVER`, `$_GET`, `$_POST`, `$_COOKIE`,
	 * `$_FILES`, and `php://input`.
	 */
	public static function fromGlobals(): Request
	{
		$body = fopen('php://input', 'r');

		return self::fromArrays(
			server: $_SERVER,
			query: $_GET,
			post: $_POST,
			cookies: $_COOKIE,
			files: $_FILES,
			body: $body === false ? null : new Stream($body)
		);
	}

	/**
	 * Builds a request from arrays shaped like PHP's globals, so the
	 * mapping can be tested without them.
	 *
	 * @param array<array-key, mixed> $server
	 * @param array<array-key, mixed> $query
	 * @param array<array-key, mixed> $post
	 * @param array<array-key, mixed> $cookies
	 * @param array<array-key, mixed> $files
	 */
	public static function fromArrays(
		array $server,
		array $query = [],
		array $post = [],
		array $cookies = [],
		array $files = [],
		?Stream $body = null
	): Request {
		/** @var array<string, mixed> $server */
		$headers = self::headers($server);
		$method  = self::string($server, 'REQUEST_METHOD') ?: 'GET';

		$protocol = str_replace('HTTP/', '', self::string($server, 'SERVER_PROTOCOL') ?: 'HTTP/1.1');

		/** @var array<string, string> $cookies */
		return new Request(
			method: $method,
			uri: self::uri($server),
			headers: $headers,
			body: $body,
			protocol: $protocol === '' ? '1.1' : $protocol,
			serverParams: $server,
			cookieParams: $cookies,
			queryParams: $query,
			uploadedFiles: self::uploadedFiles($files),
			parsedBody: $method === 'POST' && self::isFormContent($headers) ? $post : null
		);
	}

	/**
	 * Extracts request headers from server variables (`HTTP_*`, plus the
	 * content headers PHP reports without the prefix).
	 *
	 * @param  array<string, mixed> $server
	 * @return array<string, string>
	 */
	private static function headers(array $server): array
	{
		$headers = [];

		foreach ($server as $key => $value) {
			if (! is_string($value) || $value === '') {
				continue;
			}

			$name = match (true) {
				str_starts_with($key, 'HTTP_')                         => substr($key, 5),
				in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true) => $key,
				default                                                => null
			};

			if ($name !== null) {
				$name = ucwords(strtolower(str_replace('_', '-', $name)), '-');

				$headers[$name] = $value;
			}
		}

		return $headers;
	}

	/**
	 * Builds the request URI from server variables.
	 *
	 * @param array<string, mixed> $server
	 */
	private static function uri(array $server): Uri
	{
		$https  = strtolower(self::string($server, 'HTTPS'));
		$scheme = $https !== '' && $https !== 'off' ? 'https' : 'http';

		$host = self::string($server, 'HTTP_HOST') ?: self::string($server, 'SERVER_NAME');
		$port = null;

		// Split a port off the host, leaving bracketed IPv6 hosts intact.
		if (preg_match('/^(\[[^\]]+\]|[^:]+)(?::(\d+))?$/', $host, $match) === 1) {
			$host = $match[1];
			$port = isset($match[2]) ? (int) $match[2] : null;
		}

		$port ??= is_numeric($server['SERVER_PORT'] ?? null) ? (int) $server['SERVER_PORT'] : null;

		$uri = new Uri(self::string($server, 'REQUEST_URI') ?: '/')->withScheme($scheme);

		// An absolute-form request target already carries its host.
		if ($uri->getHost() !== '' || $host === '') {
			return $uri;
		}

		return $uri->withHost($host)->withPort($port);
	}

	/**
	 * Normalizes `$_FILES` into a tree of `UploadedFile` instances,
	 * including the transposed shape PHP uses for `name[]` fields.
	 *
	 * @param  array<array-key, mixed> $files
	 * @return array<array-key, mixed>
	 */
	private static function uploadedFiles(array $files): array
	{
		$normalized = [];

		foreach ($files as $key => $file) {
			if (! is_array($file)) {
				continue;
			}

			$normalized[$key] = isset($file['tmp_name'])
				? self::uploadedFile($file)
				: self::uploadedFiles($file);
		}

		return $normalized;
	}

	/**
	 * Builds one uploaded file, or a tree of them when the `$_FILES` entry
	 * holds arrays.
	 *
	 * @param  array<array-key, mixed> $file
	 * @return UploadedFile|array<array-key, mixed>
	 */
	private static function uploadedFile(array $file): UploadedFile|array
	{
		if (is_array($file['tmp_name'])) {
			$tree = [];

			foreach (array_keys($file['tmp_name']) as $key) {
				$tree[$key] = self::uploadedFile([
					'tmp_name' => $file['tmp_name'][$key],
					'size'     => is_array($file['size'] ?? null) ? $file['size'][$key] ?? null : null,
					'error'    => is_array($file['error'] ?? null) ? $file['error'][$key] ?? UPLOAD_ERR_OK : UPLOAD_ERR_OK,
					'name'     => is_array($file['name'] ?? null) ? $file['name'][$key] ?? null : null,
					'type'     => is_array($file['type'] ?? null) ? $file['type'][$key] ?? null : null
				]);
			}

			return $tree;
		}

		return new UploadedFile(
			file: is_string($file['tmp_name']) ? $file['tmp_name'] : '',
			size: is_numeric($file['size'] ?? null) ? (int) $file['size'] : null,
			error: is_numeric($file['error'] ?? null) ? (int) $file['error'] : UPLOAD_ERR_OK,
			clientFilename: is_string($file['name'] ?? null) ? $file['name'] : null,
			clientMediaType: is_string($file['type'] ?? null) ? $file['type'] : null
		);
	}

	/**
	 * Whether the request body is a form, which PHP parses into `$_POST`.
	 *
	 * @param array<string, string> $headers
	 */
	private static function isFormContent(array $headers): bool
	{
		$type = strtolower(trim(explode(';', $headers['Content-Type'] ?? '')[0]));

		return in_array($type, ['application/x-www-form-urlencoded', 'multipart/form-data'], true);
	}

	/**
	 * Reads a string server variable, or an empty string.
	 *
	 * @param array<string, mixed> $server
	 */
	private static function string(array $server, string $key): string
	{
		$value = $server[$key] ?? '';

		return is_scalar($value) ? (string) $value : '';
	}
}
