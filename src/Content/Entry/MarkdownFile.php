<?php
/**
 * Gets content entry from Markdown file.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Content\Entry;

use Blush\App;
use Blush\Tools\Str;
use Symfony\Component\Yaml\Yaml;

class MarkdownFile extends File
{
	/**
	 * Whether Markdown has been parsed.
	 *
	 * @since 1.0.0
	 */
	protected bool $markdown_parsed = false;

	/**
	 * Whether YAML has been parsed.
	 *
	 * @since 1.0.0
	 */
	protected bool $yaml_parsed = false;

	/**
	 * Whether this is a "no content" request.
	 *
	 * @since 1.0.0
	 */
	protected bool $nocontent = false;

	/**
	 * Sets up the object state. Child classes need to overwrite this and
	 * pull content and metadata from the file path.
	 *
	 * @since 1.0.0
	 */
	public function __construct( string $filepath, array $options = [] )
	{
		parent::__construct( $filepath );

		$this->nocontent = $options['nocontent'] ?? false;
	}

	/**
	 * Just-in-time Markdown parsing. This should not be called unless
	 * Markdown has yet to be parsed.
	 *
	 * @since 1.0.0
	 */
	protected function parseMarkdown(): void
	{
		$markdown = App::resolve( 'markdown' )->convert(
			file_get_contents( $this->filePath() )
		);

		$this->markdown_parsed = true;
		$this->yaml_parsed     = true;
		$this->content         = $markdown->content();
		$this->meta            = $markdown->frontMatter();
	}

	/**
	 * Just-in-time YAML parsing. This should not be called unless
	 * Markdown or YAML has yet to be parsed.
	 *
	 * @since 1.0.0
	 */
	protected function parseYaml(): void
	{
		$content = file_get_contents(
			$this->filePath(), false, null, 0, 4 * 1024
		);

		$this->yaml_parsed = true;
		$this->meta = $content ? Str::frontMatter( $content ) : [];
	}

	/**
	 * Returns the entry content.
	 *
	 * @since 1.0.0
	 */
	public function content(): string
	{
		if ( ! $this->markdown_parsed ) {
			$this->parseMarkdown();
		}

		return parent::content();
	}

	/**
	 * Returns entry metadata.
	 *
	 * @since  1.0.0
	 */
	public function meta( string $name = '', mixed $default = false ): mixed
	{
		if ( $this->nocontent && ! $this->yaml_parsed ) {
			$this->parseYaml();
		} elseif ( ! $this->markdown_parsed ) {
			$this->parseMarkdown();
		}

		return parent::meta( $name, $default );
	}
}
