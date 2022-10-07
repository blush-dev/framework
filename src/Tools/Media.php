<?php
/**
 * Utility class for getting media file information based on a file or URL path.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Tools;

class Media
{
	/**
	 * Directory path for the media file.
	 *
	 * @since 1.0.0
	 */
	protected string $path = '';

	/**
	 * URL path for the media file.
	 *
	 * @since 1.0.0
	 */
	protected string $url = '';

	/**
	 * Width of the media.
	 *
	 * @since 1.0.0
	 */
	protected int $width = 0;

	/**
	 * Height of the media.
	 *
	 * @since 1.0.0
	 */
	protected int $height = 0;

	/**
	 * Size (in bytes) of the media file.
	 *
	 * @since 1.0.0
	 */
	protected int $size = 0;

	/**
	 * Mime type for the media file.
	 *
	 * @since 1.0.0
	 */
	protected string $mime_type = '';

	/**
	 * Sets up the object properties. The `$filepath` is expected to be
	 * relative to the site root.  However, we will attempt to find it
	 * regardless of whether it's a full directory path or URL.
	 *
	 * @since 1.0.0
	 */
	public function __construct( string $filepath )
	{
		// Strip the app directory and URL path if they prepend the file.
		$filepath = Str::afterFirst( $filepath, path() );
		$filepath = Str::afterFirst( $filepath, url() );

		if ( file_exists( path( $filepath ) ) ) {
			$this->path      = path( $filepath );
			$this->url       = url( $filepath );
			$this->size      = filesize( $this->path );
			$this->mime_type = mime_content_type( $this->path );

			if ( $this->hasType( 'image' ) ) {
				$image           = getimagesize( $this->path );
				$this->width     = $image[0];
				$this->height    = $image[1];
			}
		}
	}

	/**
	 * Conditional for checking if the media is considered valid.
	 *
	 * @since 1.0.0
	 */
	public function isValid(): bool
	{
		return $this->path && $this->url && $this->hasAllowedMimeType();
	}

	/**
	 * Conditional for checking if the media is considered valid. Alias for
	 * `isValid()`.
	 *
	 * @since 1.0.0
	 * @deprecated 1.0.0
	 */
	public function valid(): bool
	{
		return $this->isValid();
	}

	/**
	 * Returns the directory path for the media file.
	 *
	 * @since 1.0.0
	 */
	public function path(): string
	{
		return $this->path;
	}

	/**
	 * Returns the URL for the media file.
	 *
	 * @since 1.0.0
	 */
	public function url(): string
	{
		return $this->url;
	}

	/**
	 * Returns the width of the media.
	 */
	public function width(): int
	{
		return $this->width;
	}

	/**
	 * Returns the height of the media.
	 *
	 * @since 1.0.0
	 */
	public function height(): int
	{
		return $this->height;
	}

	/**
	 * Returns the media file size in bytes.
	 *
	 * @since 1.0.0
	 */
	public function size(): int
	{
		return $this->size;
	}

	/**
	 * Returns the mime type.
	 *
	 * @since 1.0.0
	 */
	public function mimeType(): string
	{
		return $this->mime_type;
	}

	/**
	 * Returns the media file type  (e.g., image, audio, video).
	 *
	 * @since 1.0.0
	 */
	public function type(): string
	{
		if ( ! $mime = $this->mimeType() ) {
			return '';
		}

		return Str::beforeFirst( $mime, '/' );
	}

	/**
	 * Returns the media file subtype (e.g., jpeg, mp4, mp3, etc.).
	 *
	 * @since 1.0.0
	 */
	public function subtype(): string
	{
		if ( ! $mime = $this->mimeType() ) {
			return '';
		}

		return Str::afterFirst( $mime, '/' );
	}

	/**
	 * Conditional to check if the media file has a specific type (e.g.,
	 * image, audio, video).
	 *
	 * @since 1.0.0
	 */
	public function hasType( string $type = 'image' ): bool
	{
		return $type === $this->type();
	}

	/**
	 * Conditional check to see if the media has a specific subtype (e.g.,
	 * jpeg, mp4, mp3, etc.).
	 *
	 * @since 1.0.0
	 */
	public function hasSubtype( string $subtype = 'jpeg' ): bool
	{
		return $subtype === $this->subtype();
	}

	/**
	 * Conditional check to see if the media's mime type is allowed.
	 *
	 * @since 1.0.0
	 */
	public function hasAllowedMimeType(): bool
	{
		if ( ! $mime = $this->mimeType() ) {
			return false;
		}

		return in_array( $mime, $this->allowedMimeTypes() );
	}

	/**
	 * Returns an array of allowed mime types.
	 *
	 * @todo  Flesh out full list of image, audio, and video mime types.
	 * @since 1.0.0
	 */
	protected function allowedMimeTypes(): array
	{
		return [
			// Images
			'image/apng',
			'image/avif',
			'image/gif',
			'image/jpeg', // .jpg|.jpeg
			'image/png',
			'image/svg+xml',
			'image/webp',

			// Audio
			'audio/mpeg', // .mp3
			'audio/wav',
			'audio/ogg',

			// Video
			'video/mp4',
			'video/ogg',
			'video/webm'
		];
	}

	/**
	 * If object is used as a string, return the media file's URL. This
	 * should keep consistency with pre-1.0.0 implementations expecting a
	 * URL instead of an object, particularly dealing with entry metadata.
	 *
	 * @since 1.0.0
	 */
	public function __toString(): string
	{
		return $this->url() ?: '';
	}
}
