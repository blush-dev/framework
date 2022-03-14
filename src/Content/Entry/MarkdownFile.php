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

class MarkdownFile extends File
{
	/**
	 * Sets up the object state.
	 *
	 * @since 1.0.0
	 */
	public function __construct( string $filename )
	{
		parent::__construct( $filename );

		$markdown = App::resolve( 'markdown' )->convert(
			file_get_contents( $filename )
		);

		$this->meta    = $markdown->frontMatter();
		$this->content = $markdown->content();
	}
}
