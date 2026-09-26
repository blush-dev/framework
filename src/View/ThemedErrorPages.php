<?php

/**
 * Themed error pages.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View;

use Override;
use Throwable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Content\ContentRepository;
use Blush\Content\Entry\Entry;
use Blush\Content\Type\ContentTypes;
use Blush\Core\AppConfig;
use Blush\Http\ErrorPages;
use Blush\Http\HttpError;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Theme\ThemeResolver;

/**
 * Renders error responses with the theme: the `error-{status}` →
 * `error` views (see `Hierarchy`), filled from the site's error entry
 * when it has one, `user/content/_errors/{status}.md` (or 1.x's
 * `_error/{status}.md`). The entry gives the title and body; without one,
 * the view falls back to the theme's own messages.
 *
 * With debug on, a server error isn't themed, so the detailed exception
 * page shows instead. Templates get `$status` (the code), `$reason`,
 * `$title`, `$entry`, `$description` (the theme's message for the
 * status), and `$message` (the exception's message, in debug only).
 */
final readonly class ThemedErrorPages implements ErrorPages
{
	/**
	 * The content folders searched for error entries, in order.
	 *
	 * @var list<string>
	 */
	public const array FOLDERS = ['_errors', '_error'];

	public function __construct(
		private ThemeResolver $themes,
		private ViewFactory $views,
		private ContentRepository $content,
		private ContentTypes $types,
		private AppConfig $app
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function render(Throwable $error, Status $status, ServerRequestInterface $request): ?ResponseInterface
	{
		if ($this->app->debug && ! $error instanceof HttpError) {
			return null;
		}

		$views   = $this->views->forChain($this->themes->forRequest($request));
		$entry   = $this->entry($status->value);
		$context = $this->views->context($views, $entry);
		$reason  = $status->reasonPhrase();
		$title   = $entry !== null && $entry->title !== '' ? $entry->title : self::message($views, "error.{$status->value}.title", $reason);

		$context->head->title($title)->meta('robots', 'noindex');
		$context->addClass('is-error', "is-error-{$status->value}");

		$context->share([
			'status'      => $status->value,
			'reason'      => $reason,
			'title'       => $title,
			'entry'       => $entry,
			'description' => self::message($views, "error.{$status->value}.message", self::message($views, 'error.message', '')),
			'message'     => $this->app->debug ? $error->getMessage() : ''
		]);

		$html = $views->render(Hierarchy::forError($status->value, $entry)->names, [], $context);

		return Response::html($html, $status);
	}

	/**
	 * Returns a theme message, or a fallback when the theme has none.
	 */
	private static function message(Views $views, string $key, string $fallback): string
	{
		return $views->translator->has($key, 'theme') ? $views->translator->translate($key, [], 'theme') : $fallback;
	}

	/**
	 * Returns the site's published error entry for a status, or `null`.
	 */
	private function entry(int $status): ?Entry
	{
		foreach (self::FOLDERS as $folder) {
			$type  = $this->types->forFile("{$folder}/{$status}.md");
			$key   = ltrim(substr("{$folder}/{$status}", strlen($type->path)), '/');
			$entry = $this->content->named($type->name, $key);

			if ($entry !== null && $entry->isPublished()) {
				return $entry;
			}
		}

		return null;
	}
}
