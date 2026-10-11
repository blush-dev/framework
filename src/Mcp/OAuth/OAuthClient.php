<?php

/**
 * OAuth client.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Mcp\OAuth;

use Uri\Rfc3986\Uri;

/**
 * An app that may ask a person to connect it (D-752): its record's id,
 * the `client_id` it goes by (one Blush gave it when it registered, or
 * the address of its own metadata document, which proves where it's
 * from), its name, its address (`uri`), where it may be sent back to
 * (`redirectUris`), when it registered, and whether anyone has approved
 * it yet; one nobody approves within a day is removed.
 */
final readonly class OAuthClient
{
	/**
	 * @param list<string> $redirectUris
	 */
	public function __construct(
		public string $id,
		public string $clientId,
		public string $name,
		public array $redirectUris,
		public ?string $uri = null,
		public int $registered = 0,
		public bool $approved = false
	) {}

	/**
	 * Whether it goes by its own metadata document's address, which
	 * proves where it's from.
	 */
	public function isVerified(): bool
	{
		return str_starts_with($this->clientId, 'https://');
	}

	/**
	 * The host it says it's from, or proves it is, for the approval
	 * screen.
	 */
	public function host(): ?string
	{
		$address = $this->isVerified() ? $this->clientId : $this->uri;
		$host    = $address === null ? null : Uri::parse($address)?->getHost();

		return $host === null || $host === '' ? null : strtolower($host);
	}

	/**
	 * Whether it may be sent back to an address: exactly one it named,
	 * or, for one on the person's own computer, the same but for the
	 * port (RFC 8252).
	 */
	public function allowsRedirect(string $uri): bool
	{
		if (in_array($uri, $this->redirectUris, true)) {
			return true;
		}

		$asked = Uri::parse($uri);

		if ($asked === null || $asked->getScheme() !== 'http' || ! in_array($asked->getHost(), ['127.0.0.1', '[::1]', 'localhost'], true)) {
			return false;
		}

		return array_any($this->redirectUris, static function (string $named) use ($asked): bool {
			$parts = Uri::parse($named);

			return $parts !== null
				&& $parts->getScheme() === 'http'
				&& $parts->getHost() === $asked->getHost()
				&& $parts->getPath() === $asked->getPath();
		});
	}

	/**
	 * Returns a copy approved by someone.
	 */
	public function withApproved(): self
	{
		return clone($this, ['approved' => true]);
	}

	/**
	 * Builds a client from its stored array and its record's id.
	 *
	 * @param  array<mixed> $data
	 * @throws OAuthError When the data isn't a client.
	 */
	public static function fromArray(array $data, string $id): self
	{
		$uris = $data['redirectUris'] ?? null;

		if (! is_string($data['clientId'] ?? null) || ! is_string($data['name'] ?? null) || ! is_array($uris)) {
			throw OAuthError::client(sprintf('The OAuth client "%s" is damaged.', $id));
		}

		return new self(
			id: $id,
			clientId: $data['clientId'],
			name: $data['name'],
			redirectUris: array_values(array_filter($uris, is_string(...))),
			uri: is_string($data['uri'] ?? null) ? $data['uri'] : null,
			registered: is_int($data['registered'] ?? null) ? $data['registered'] : 0,
			approved: ($data['approved'] ?? false) === true
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array
	{
		return [
			'clientId'     => $this->clientId,
			'name'         => $this->name,
			'uri'          => $this->uri,
			'redirectUris' => $this->redirectUris,
			'registered'   => $this->registered,
			'approved'     => $this->approved
		];
	}
}
