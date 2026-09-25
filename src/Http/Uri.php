<?php

/**
 * URI.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Http;

use Override;
use Stringable;
use Uri\Rfc3986\Uri as Rfc3986Uri;
use Psr\Http\Message\UriInterface;

/**
 * An immutable PSR-7 URI. Parsing is done by PHP 8.5's RFC 3986 parser;
 * this class adds the PSR-7 rules on top: a lowercase scheme and host, no
 * port when it's the scheme's default, and percent-encoding of characters
 * that aren't allowed in a component (existing `%XX` sequences are kept, so
 * nothing is double-encoded).
 */
final readonly class Uri implements UriInterface, Stringable
{
	/**
	 * Default ports, which `getPort()` and the authority leave out.
	 */
	private const array DEFAULT_PORTS = [
		'http'  => 80,
		'https' => 443
	];

	/**
	 * Characters allowed anywhere in a URI (RFC 3986 reserved and
	 * unreserved), beyond `%XX` sequences. Anything else is
	 * percent-encoded before parsing.
	 */
	private const string URI_CHARS = 'A-Za-z0-9\-._~:\/?#\[\]@!$&\'()*+,;=';

	/**
	 * Characters allowed in a path, beyond `%XX` sequences.
	 */
	private const string PATH_CHARS = 'A-Za-z0-9\-._~!$&\'()*+,;=:@\/';

	/**
	 * Characters allowed in a query or fragment, beyond `%XX` sequences.
	 */
	private const string QUERY_CHARS = 'A-Za-z0-9\-._~!$&\'()*+,;=:@\/?';

	/**
	 * Characters allowed in a user name or password, beyond `%XX`
	 * sequences.
	 */
	private const string USER_CHARS = 'A-Za-z0-9\-._~!$&\'()*+,;=';

	private string $scheme;

	private string $userInfo;

	private string $host;

	private ?int $port;

	private string $path;

	private string $query;

	private string $fragment;

	/**
	 * Parses a URI reference. An empty string is an empty URI.
	 *
	 * @throws InvalidMessage When the URI can't be parsed.
	 */
	public function __construct(string $uri = '')
	{
		// Brackets are only valid in an IPv6 host, but browsers send them
		// unencoded in queries (`tag[]=art`), so they're encoded after the
		// authority.
		preg_match('#^(?:[A-Za-z][A-Za-z0-9+.\-]*:)?(?://[^/?\#]*)?#', $uri, $match);

		$authority = $match[0] ?? '';
		$rest      = substr($uri, strlen($authority));

		$parsed = Rfc3986Uri::parse(
			self::encode($authority, self::URI_CHARS) . self::encode($rest, self::QUERY_CHARS . '\#')
		);

		if ($parsed === null) {
			throw new InvalidMessage(sprintf('Unable to parse URI "%s".', $uri));
		}

		$this->scheme   = strtolower($parsed->getRawScheme() ?? '');
		$this->userInfo = $parsed->getRawUserInfo() ?? '';
		$this->host     = strtolower($parsed->getRawHost() ?? '');
		$this->port     = $parsed->getPort();
		$this->path     = $parsed->getRawPath();
		$this->query    = $parsed->getRawQuery() ?? '';
		$this->fragment = $parsed->getRawFragment() ?? '';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function getScheme(): string
	{
		return $this->scheme;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function getAuthority(): string
	{
		if ($this->host === '') {
			return '';
		}

		$authority = $this->userInfo === '' ? $this->host : "{$this->userInfo}@{$this->host}";
		$port      = $this->getPort();

		return $port === null ? $authority : "{$authority}:{$port}";
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function getUserInfo(): string
	{
		return $this->userInfo;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function getHost(): string
	{
		return $this->host;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function getPort(): ?int
	{
		return $this->port !== null && $this->port !== (self::DEFAULT_PORTS[$this->scheme] ?? null)
			? $this->port
			: null;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function getPath(): string
	{
		return $this->path;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function getQuery(): string
	{
		return $this->query;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function getFragment(): string
	{
		return $this->fragment;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	#[\NoDiscard]
	public function withScheme(string $scheme): static
	{
		$scheme = strtolower($scheme);

		if ($scheme !== '' && preg_match('/^[a-z][a-z0-9+.\-]*$/', $scheme) !== 1) {
			throw new InvalidMessage(sprintf('Invalid URI scheme "%s".', $scheme));
		}

		return clone($this, ['scheme' => $scheme]);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	#[\NoDiscard]
	public function withUserInfo(string $user, ?string $password = null): static
	{
		$userInfo = self::encode($user, self::USER_CHARS);

		if ($userInfo !== '' && $password !== null && $password !== '') {
			$userInfo .= ':' . self::encode($password, self::USER_CHARS . ':');
		}

		return clone($this, ['userInfo' => $userInfo]);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	#[\NoDiscard]
	public function withHost(string $host): static
	{
		if (preg_match('/[\s\/?#@]/', $host) === 1) {
			throw new InvalidMessage(sprintf('Invalid URI host "%s".', $host));
		}

		return clone($this, ['host' => strtolower($host)]);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	#[\NoDiscard]
	public function withPort(?int $port): static
	{
		if ($port !== null && ($port < 0 || $port > 65535)) {
			throw new InvalidMessage(sprintf('Invalid URI port %d; it must be between 0 and 65535.', $port));
		}

		return clone($this, ['port' => $port]);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	#[\NoDiscard]
	public function withPath(string $path): static
	{
		return clone($this, ['path' => self::encode($path, self::PATH_CHARS)]);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	#[\NoDiscard]
	public function withQuery(string $query): static
	{
		return clone($this, ['query' => self::encode(ltrim($query, '?'), self::QUERY_CHARS)]);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	#[\NoDiscard]
	public function withFragment(string $fragment): static
	{
		return clone($this, ['fragment' => self::encode(ltrim($fragment, '#'), self::QUERY_CHARS)]);
	}

	/**
	 * Builds the URI string following PSR-7's rules: a rootless path gets
	 * a leading slash when there's an authority, and a path starting with
	 * `//` is reduced to one slash when there isn't.
	 */
	#[Override]
	public function __toString(): string
	{
		$uri       = $this->scheme === '' ? '' : "{$this->scheme}:";
		$authority = $this->getAuthority();
		$path      = $this->path;

		if ($authority !== '') {
			$uri .= "//{$authority}";

			if ($path !== '' && ! str_starts_with($path, '/')) {
				$path = "/{$path}";
			}
		} elseif (str_starts_with($path, '//')) {
			$path = '/' . ltrim($path, '/');
		}

		$uri .= $path;

		if ($this->query !== '') {
			$uri .= "?{$this->query}";
		}

		if ($this->fragment !== '') {
			$uri .= "#{$this->fragment}";
		}

		return $uri;
	}

	/**
	 * Percent-encodes every character outside `$allowed`, along with any
	 * `%` that doesn't start a valid `%XX` sequence, so nothing is
	 * double-encoded.
	 */
	private static function encode(string $value, string $allowed): string
	{
		return preg_replace_callback(
			"/(?:[^{$allowed}%]++|%(?![A-Fa-f0-9]{2}))/",
			static fn (array $match): string => rawurlencode($match[0]),
			$value
		) ?? $value;
	}
}
