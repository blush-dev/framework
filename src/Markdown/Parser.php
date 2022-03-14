<?php
/**
 * Markdown parser.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Markdown;

use Blush\Contracts\Markdown\Parser as ParserContract;
use League\CommonMark\ConverterInterface;
use Symfony\Component\Yaml\Yaml;

class Parser implements ParserContract
{
	/**
	 * Markdown converter.
	 *
	 * @since 1.0.0
	 */
        protected ConverterInterface $converter;

	/**
	 * Stores content.
	 *
	 * @since 1.0.0
	 */
        protected string $content;

	/**
	 * Stores front matter.
	 *
	 * @since 1.0.0
	 */
        protected array $front_matter;

	/**
	 * Sets up object state.
	 *
	 * @since 1.0.0
	 */
        public function __construct( ConverterInterface $converter )
	{
                $this->converter = $converter;
        }

	/**
	 * Converts Markdown to HTML.
	 *
	 * @since 1.0.0
	 */
        public function convert( string $content ) : self
	{
                $this->front_matter = [];

                $regex = '/^---[\r\n|\r|\n](.*?)[\r\n|\r|\n]---/s';

                preg_match( $regex, $content, $match );

                if ( $match ) {
                        $this->front_matter = Yaml::parse( $match[1] );
                        $content = preg_replace( $regex, '', $content, 1 );
                }

                $this->content = $this->converter->convert(
                        $content
                )->getContent();

                return $this;
        }

	/**
	 * Returns Markdown HTML.
	 *
	 * @since 1.0.0
	 */
        public function content() : string
	{
                return $this->content;
        }

	/**
	 * Returns YAML front matter.
	 *
	 * @since 1.0.0
	 */
        public function frontMatter() : array
	{
                return $this->front_matter;
        }
}
