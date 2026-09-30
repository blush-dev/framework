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
use Blush\Icon\IconCategory;
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
 * picker to draw (as a mask, so no markup from the file runs). A core
 * icon has its `category` (`IconCategory`, D-265); the rest have `null`
 * and a `source` naming the theme, the site, or the extension they come
 * from, as components do.
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
		private ViewFactory $views,
		private Provenance $provenance
	) {}

	public function __invoke(): ResponseInterface
	{
		try {
			$chain = $this->resolver->active();
			$views = $this->views->forChain($chain);
		} catch (ThemeException $error) {
			return Response::json(['error' => $error->getMessage()], Status::InternalServerError, ['Cache-Control' => 'no-store']);
		}

		$keywords   = self::keywords();
		$categories = self::categories();
		$icons      = [];

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
				'category' => $name->isCore() ? ($categories[$name->name] ?? null)?->value : null,
				'source'   => $name->isCore() ? null : $this->provenance->of($name->namespace, $chain),
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

	/**
	 * The core icons' categories, by name. An icon the file leaves out, or
	 * gives an unknown category, has none.
	 *
	 * @return array<string, IconCategory>
	 */
	private static function categories(): array
	{
		$file = Framework::path('resources/icons/blush/categories.json');

		try {
			$data = json_decode((string) @file_get_contents($file), true, 2, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			return [];
		}

		$categories = [];

		foreach (is_array($data) ? $data : [] as $name => $category) {
			$case = is_string($name) && is_string($category) ? IconCategory::tryFrom($category) : null;

			if ($case !== null) {
				$categories[$name] = $case;
			}
		}

		return $categories;
	}
}
