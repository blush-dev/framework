<?php

/**
 * Admin appearance controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Auth\Account;
use Blush\Auth\Capability;
use Blush\Auth\Permissions;
use Blush\Core\AppConfig;
use Blush\Core\Paths;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Theme\ThemeConfig;
use Blush\Theme\ThemeException;
use Blush\Theme\ThemeManifest;
use Blush\Theme\ThemeResolver;
use Blush\Theme\Themes;

/**
 * Answers `GET {path}/api/appearance` (D-306), for accounts with
 * `site.settings`: the `active` theme's slug, its `chain` (the theme,
 * its ancestors, then the default theme), whether `config/theme.php`
 * exists (`config`), whether `?theme=` previews work (`preview`, in
 * development only), every installed theme (`slug`, `name`, `version`,
 * `description`, `parent`, `source`, and whether it's `active`), and the
 * `invalid` ones with the reason.
 *
 * The active theme is developer configuration (`config/theme.php`,
 * D-039), so the screen only shows it; `theme:activate` changes it.
 */
final readonly class AppearanceController
{
	public function __construct(
		private Themes $themes,
		private ThemeConfig $config,
		private ThemeResolver $resolver,
		private AppConfig $app,
		private Paths $paths,
		private Permissions $permissions
	) {}

	public function __invoke(ServerRequestInterface $request): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account || ! $this->permissions->can($account, Capability::SiteSettings)) {
			return self::error('You aren\'t allowed to see the site\'s appearance.', Status::Forbidden);
		}

		try {
			$chain = $this->resolver->active();
		} catch (ThemeException $error) {
			return self::error($error->getMessage(), Status::InternalServerError);
		}

		$themes = array_values(array_map(fn (ThemeManifest $theme): array => [
			'slug'        => $theme->slug,
			'name'        => $theme->name,
			'version'     => $theme->version,
			'description' => $theme->description,
			'parent'      => $theme->parent,
			'source'      => $theme->source->value,
			'active'      => $theme->slug === $this->config->active
		], $this->themes->all()));

		// The active theme first, then by name.
		usort($themes, static fn (array $a, array $b): int => [! $a['active'], $a['name']] <=> [! $b['active'], $b['name']]);

		$invalid = [];

		foreach ($this->themes->invalid() as $slug => $reason) {
			$invalid[] = ['slug' => $slug, 'reason' => $reason];
		}

		return Response::json([
			'active'  => $chain->active()->slug,
			'chain'   => $chain->slugs(),
			'config'  => is_file("{$this->paths->config}/theme.php"),
			'preview' => $this->app->environment->isDevelopment(),
			'themes'  => $themes,
			'invalid' => $invalid
		], headers: ['Cache-Control' => 'no-store']);
	}

	private static function error(string $message, Status $status): ResponseInterface
	{
		return Response::json(['error' => $message], $status, ['Cache-Control' => 'no-store']);
	}
}
