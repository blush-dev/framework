<?php

/**
 * Admin icons controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use JsonException;
use Psr\Http\Message\ResponseInterface;
use Blush\Core\Framework;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Icon\IconName;
use Blush\Icon\Icons;
use Blush\Theme\ThemeException;
use Blush\Theme\ThemeResolver;
use Blush\View\ViewFactory;

/**
 * Answers `GET {path}/api/icons` (D-246): the icons the active theme can
 * show, for the editor's icon picker, as `icon:list` lists them. Each has
 * its `name` as the icon component's `name` prop takes it (a core icon's
 * short name, `house`; the rest in full, `jtcom/github`), its translated
 * `label`, the core icons' search `keywords`, and its `svg`, for the
 * picker to draw (as a mask, so no markup from the file runs).
 */
final readonly class IconsController
{
	/**
	 * Larger files aren't sent; the picker shows the name instead.
	 */
	private const int MAX_SVG = 16384;

	public function __construct(
		private Icons $icons,
		private ThemeResolver $resolver,
		private ViewFactory $views
	) {}

	public function __invoke(): ResponseInterface
	{
		try {
			$chain = $this->resolver->active();
			$views = $this->views->forChain($chain);
		} catch (ThemeException $error) {
			return Response::json(['error' => $error->getMessage()], Status::InternalServerError, ['Cache-Control' => 'no-store']);
		}

		$keywords = self::keywords();
		$icons    = [];

		foreach ($this->icons->all($chain) as $key => $file) {
			$name = IconName::parse($key);

			if ($name === null) {
				continue;
			}

			$svg = is_readable($file) && filesize($file) <= self::MAX_SVG ? (string) file_get_contents($file) : '';

			$icons[] = [
				'name'     => $name->isCore() ? $name->name : (string) $name,
				'label'    => $views->iconText($name, 'label') ?? $name->label(),
				'keywords' => $name->isCore() ? $keywords[$name->name] ?? [] : [],
				'svg'      => $svg
			];
		}

		return Response::json(['icons' => $icons], headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * The core icons' keywords (Lucide's tags), by name.
	 *
	 * @return array<string, list<string>>
	 */
	private static function keywords(): array
	{
		$file = Framework::path('resources/icons/blush/tags.json');

		try {
			$tags = json_decode((string) @file_get_contents($file), true, 4, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			return [];
		}

		$keywords = [];

		foreach (is_array($tags) ? $tags : [] as $name => $words) {
			if (is_string($name) && is_array($words)) {
				$keywords[$name] = array_values(array_filter($words, is_string(...)));
			}
		}

		return $keywords;
	}
}
