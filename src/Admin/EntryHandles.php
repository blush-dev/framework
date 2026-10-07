<?php

/**
 * Admin entry handles.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use Blush\Content\ContentRepository;
use Blush\Content\Entry\Entry;
use Blush\Core\AppConfig;

/**
 * An entry's handle is how the admin's addresses name it (D-253): its
 * content type and its key in the index, such as `post/hello-world`, so
 * an editor's address is `{path}/content/post/hello-world` rather than
 * the file's path. Keys are already unique within a type and locale (an
 * entry's own address needs them to be), so no date or suffix is needed.
 * A landing page's empty key is written `index`.
 *
 * An entry has no handle when its handle would find another entry (two
 * files claiming one key, or a page keyed `index` beside a landing page)
 * or when it isn't in the default language. The admin addresses those by
 * their path, as before.
 */
final readonly class EntryHandles
{
	/**
	 * What a landing page's empty key is written as.
	 */
	private const string LANDING = 'index';

	public function __construct(
		private ContentRepository $content,
		private AppConfig $app
	) {}

	/**
	 * Returns an entry's handle, or `null` when it has none.
	 */
	public function of(Entry $entry): ?string
	{
		if ($entry->language !== $this->app->languages->default->code) {
			return null;
		}

		$key = $entry->key === '' ? self::LANDING : $entry->key;

		return $this->find($entry->type->name, $key)?->path === $entry->path ? "{$entry->type->name}/{$key}" : null;
	}

	/**
	 * Returns the entry a type and key name, in the default language.
	 */
	public function find(string $type, string $key): ?Entry
	{
		$key = trim($key, '/');

		return $this->content->named($type, $key) ?? ($key === self::LANDING ? $this->content->named($type, '') : null);
	}
}
