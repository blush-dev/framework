<?php

/**
 * Profile controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Content\Query\InvalidQuery;
use Blush\Content\Type\Profiles;
use Blush\Http\NotFound;

/**
 * Serves a profile's own page (the profiles type's `single` and
 * `.single.paged`, D-351), such as `/profiles/jane`: the profile, then
 * every listed entry of any type crediting them, listed as the profiles
 * type's listing (and the profile's own `collection` front matter) says.
 * A profile has a page even before anything credits them; a person
 * credited with no file has none (D-584).
 */
final class ProfileController extends ContentController
{
	/**
	 * @throws NotFound
	 * @throws InvalidQuery
	 */
	public function __invoke(ServerRequestInterface $request, string $type, string $name, int $page = 1): ResponseInterface
	{
		$profiles = $this->type($type);
		$profile  = $profiles instanceof Profiles ? $this->visible($this->content->term($profiles->name, $name)) : null;

		if ($profile === null) {
			throw new NotFound(sprintf('There is no profile "%s".', $name));
		}

		if ($page === 1 && self::isPaged($request)) {
			return self::redirect($request, $this->urls->profile($profile->slug) ?? '/');
		}

		$url = $this->urls->profile($profile->slug, $page);

		if ($url !== null && $url !== $request->getUri()->getPath()) {
			return self::redirect($request, $url);
		}

		$crediting = array_keys($this->types->crediting());
		$query     = $this->query($profiles->listing->arguments(), ['type' => $crediting === [] ? $profiles->name : $crediting], self::collectionArguments($profile))
			->whereTerm($profiles->name, $profile->slug);

		return $this->renderer->render(new ContentPage(
			kind: PageKind::Profile,
			title: $profile->title,
			entry: $profile,
			type: $profiles,
			entries: $this->paginate($query, $page),
			pageUrl: fn (int $number): ?string => $this->urls->profile($profile->slug, $number),
			profile: $profile
		), $request);
	}
}
