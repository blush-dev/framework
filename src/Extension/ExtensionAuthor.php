<?php

/**
 * Extension author.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Extension;

/**
 * Someone who made an extension (D-384), as a manifest's `authors` lists
 * them, in the shape of `composer.json`'s `authors`: a `name`, and an
 * optional `email`, `homepage` (an `http` or `https` URL), and `role`.
 *
 * A manifest without `authors` takes them from the `composer.json` in
 * its folder (`fromComposer()`), so a package's authors are written
 * once. A manifest's own list is checked strictly; `composer.json`'s is
 * read leniently, keeping only the entries that fit, since it isn't the
 * extension's manifest.
 */
final readonly class ExtensionAuthor
{
	public function __construct(
		public string $name,
		public string $email = '',
		public string $homepage = '',
		public string $role = ''
	) {}

	/**
	 * Builds an author from an `authors` entry.
	 *
	 * @throws ExtensionException When it isn't an object with a name, or
	 *         a value doesn't fit.
	 */
	public static function fromArray(mixed $data): self
	{
		if (! is_array($data) || (array_is_list($data) && $data !== [])) {
			throw new ExtensionException('Each of "authors" must be an object with a "name".');
		}

		$unknown = array_diff(array_map(strval(...), array_keys($data)), ['name', 'email', 'homepage', 'role']);

		if ($unknown !== []) {
			throw new ExtensionException(sprintf('An author has "name", "email", "homepage", and "role"; "%s" isn\'t one.', reset($unknown)));
		}

		$name = $data['name'] ?? null;

		if (! is_string($name) || trim($name) === '') {
			throw new ExtensionException('Each of "authors" needs a "name".');
		}

		foreach (['email', 'homepage', 'role'] as $key) {
			if (isset($data[$key]) && ! is_string($data[$key])) {
				throw new ExtensionException(sprintf('An author\'s "%s" must be a string.', $key));
			}
		}

		$email    = trim(is_string($data['email'] ?? null) ? $data['email'] : '');
		$homepage = trim(is_string($data['homepage'] ?? null) ? $data['homepage'] : '');

		if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
			throw new ExtensionException(sprintf('"%s" isn\'t an email address.', $email));
		}

		if ($homepage !== '' && (preg_match('#^https?://#i', $homepage) !== 1 || filter_var($homepage, FILTER_VALIDATE_URL) === false)) {
			throw new ExtensionException(sprintf('An author\'s "homepage" must be an http or https URL; "%s" isn\'t.', $homepage));
		}

		return new self(trim($name), $email, $homepage, trim(is_string($data['role'] ?? null) ? $data['role'] : ''));
	}

	/**
	 * Builds a manifest's `authors` list.
	 *
	 * @return list<self>
	 * @throws ExtensionException
	 */
	public static function list(mixed $data): array
	{
		if (! is_array($data) || ! array_is_list($data)) {
			throw new ExtensionException('"authors" must be a list of authors, each an object with a "name".');
		}

		return array_map(self::fromArray(...), $data);
	}

	/**
	 * Returns the authors in a folder's `composer.json`, keeping only the
	 * entries that fit; none when it has no file, or the file isn't
	 * readable JSON.
	 *
	 * @return list<self>
	 */
	public static function fromComposer(string $folder): array
	{
		return self::lenient(ComposerJson::read($folder)['authors'] ?? []);
	}

	/**
	 * Reads a list of authors leniently, as a package's `composer.json`
	 * or Composer's `installed.json` has them, keeping only the entries
	 * that fit; none when it isn't a list.
	 *
	 * @return list<self>
	 */
	public static function lenient(mixed $entries): array
	{
		$authors = [];

		foreach (is_array($entries) && array_is_list($entries) ? $entries : [] as $entry) {
			try {
				$authors[] = self::fromArray($entry);
			} catch (ExtensionException) {
				continue;
			}
		}

		return $authors;
	}

	/**
	 * Returns the author as `composer.json` has it, without empty values.
	 *
	 * @return array{name: string, email?: string, homepage?: string, role?: string}
	 */
	public function toArray(): array
	{
		$author = ['name' => $this->name];

		if ($this->email !== '') {
			$author['email'] = $this->email;
		}

		if ($this->homepage !== '') {
			$author['homepage'] = $this->homepage;
		}

		if ($this->role !== '') {
			$author['role'] = $this->role;
		}

		return $author;
	}
}
