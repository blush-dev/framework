<?php

/**
 * Admin preview link controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use DateTimeImmutable;
use DateTimeInterface;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Auth\Account;
use Blush\Auth\ContentAction;
use Blush\Auth\Permissions;
use Blush\Content\ContentRepository;
use Blush\Preview\PreviewConfig;
use Blush\Preview\PreviewLinks;
use Blush\Http\Response;
use Blush\Http\Status;

/**
 * Answers `POST {path}/api/previews` with `{"entry": path}`: a signed
 * preview link to an entry the account may edit (D-226), as `{url,
 * expires}`. Without a secret, preview links are off: a 503 says so.
 */
final readonly class PreviewLinkController
{
	public function __construct(
		private PreviewLinks $links,
		private PreviewConfig $config,
		private ContentRepository $content,
		private Permissions $permissions
	) {}

	public function __invoke(ServerRequestInterface $request): ResponseInterface
	{
		if (! $this->config->isEnabled()) {
			return self::json(['error' => 'Preview links are off: the site has no APP_SECRET. Run "bin/blush init" to add one.'], Status::ServiceUnavailable);
		}

		try {
			$input = json_decode((string) $request->getBody(), true, 4, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			$input = null;
		}

		$path = is_array($input) ? ($input['entry'] ?? null) : null;

		if (! is_string($path)) {
			return self::json(['error' => 'Send a JSON "entry" path.'], Status::BadRequest);
		}

		$account = $request->getAttribute(Account::class);
		$entry   = $this->content->findPath($path);

		if ($entry === null) {
			return self::json(['error' => sprintf('There\'s no "%s" entry.', $path)], Status::NotFound);
		}

		if (! $account instanceof Account || ! $this->permissions->can($account, ContentAction::Edit, $entry)) {
			return self::json(['error' => 'You aren\'t allowed to preview that entry.'], Status::Forbidden);
		}

		$link = $this->links->make($entry);

		return self::json([
			'url'     => $link->url,
			'expires' => DateTimeImmutable::createFromTimestamp($link->expires)->format(DateTimeInterface::ATOM)
		], Status::Created);
	}

	/**
	 * Builds an uncached JSON response.
	 *
	 * @param array<string, mixed> $data
	 */
	private static function json(array $data, Status $status): ResponseInterface
	{
		return Response::json($data, $status, ['Cache-Control' => 'no-store']);
	}
}
