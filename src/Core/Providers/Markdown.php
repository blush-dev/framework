<?php
/**
 * Markdown service provider.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Core\Providers;

use Blush\Contracts\Markdown\Parser as ParserContract;

use Blush\Core\ServiceProvider;
use Blush\Markdown\{Parser, ImageRenderer, LinkRenderer, ParagraphRenderer};

use League\CommonMark\MarkdownConverter;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\Node\Inline\{Image, Link};
use League\CommonMark\Node\Block\Paragraph;

class Markdown extends ServiceProvider
{
	/**
	 * Register bindings.
	 *
	 * @since 1.0.0
	 */
        public function register() : void
	{
		// Sets up the Markdown converter and environment.
                $this->app->singleton( 'markdown/converter', function( $app ) {

			// Gets the user Markdown config.
                        $markdown = $app->get( 'config.markdown' );

                        // Configure the Environment.
                        $environment = new Environment( $markdown['config'] ?? [] );

			// Loops through user-added extensions and adds them.
                        if ( $markdown->has( 'extensions' ) ) {
                                foreach ( $markdown->get( 'extensions' ) as $extension ) {
                                        $environment->addExtension( new $extension() );
                                }
                        }

			// Add default renderers.
                        $renderers = [
                                Image::class     => ImageRenderer::class,
                                Link::class      => LinkRenderer::class,
                                Paragraph::class => ParagraphRenderer::class
                        ];

                        foreach ( $renderers as $node => $renderer ) {
                                $environment->addRenderer(
                                        $node,
                                        is_string( $renderer )
                                                ? new $renderer()
                                                : $renderer
                                );
                        }

			// Return Markdown converter instance.
                        return new MarkdownConverter( $environment );
                } );

		// Binds a Markdown wrapper class for accessing the converter.
		$this->app->bind( 'markdown', function( $app ) {
			return new Parser( $app->resolve( 'markdown/converter' ) );
		} );
        }
}
