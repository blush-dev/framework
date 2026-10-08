<?php

/**
 * Data file redirects.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Routing;

use Override;
use Blush\Core\Paths;
use Blush\Data\DataLoader;
use Blush\Data\InvalidData;
use Blush\Routing\InvalidRoute;
use Blush\Routing\Redirect;
use Blush\Routing\RedirectSource;

/**
 * Redirects from `user/data/redirects.json`, the
 * site owner's redirect map. Either a map of paths to targets, where a
 * target may be a map with a `status`:
 *
 *     /old-about: /about
 *     /blog/{slug}: /archives/{slug}
 *     /promo: { to: "https://example.com/sale", status: 302 }
 *
 * or a list of `from`, `to`, and `status` maps. Paths are route patterns,
 * as in `config/routes.php`.
 */
final readonly class DataRedirects implements RedirectSource
{
	/**
	 * The data file's name, without its extension.
	 */
	public const string FILE = 'redirects';

	public function __construct(
		private DataLoader $data,
		private Paths $paths
	) {}

	/**
	 * @inheritDoc
	 * @return list<Redirect>
	 * @throws InvalidRoute
	 */
	#[Override]
	public function redirects(): iterable
	{
		try {
			$data = $this->data->load($this->paths->data, self::FILE) ?? [];
		} catch (InvalidData $e) {
			throw new InvalidRoute(sprintf('user/data/%s is invalid: %s', self::FILE, $e->getMessage()), previous: $e);
		}

		$redirects = [];

		foreach ($data as $key => $value) {
			$entry = match (true) {
				is_string($key) && is_string($value) => ['from' => $key, 'to' => $value],
				is_string($key) && is_array($value)  => ['from' => $key, ...$value],
				is_array($value)                     => $value,
				default                              => null
			};

			if ($entry === null) {
				throw new InvalidRoute(sprintf('user/data/%s must map paths to targets, or list "from" and "to" maps.', self::FILE));
			}

			$redirects[] = Redirect::fromArray($entry);
		}

		return $redirects;
	}
}
