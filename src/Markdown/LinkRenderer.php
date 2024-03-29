<?php
/**
 * Markdown link renderer.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Markdown;

use Blush\Tools\Str;
use League\CommonMark\Extension\CommonMark\Node\Inline\{Image, Link};
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\{ChildNodeRendererInterface, NodeRendererInterface};
use League\CommonMark\Util\HtmlElement;

class LinkRenderer implements NodeRendererInterface
{
	/**
	 * Renders the element.
	 *
	 * @since 1.0.0
	 */
        public function render( Node $node, ChildNodeRendererInterface $childRenderer )
	{
                $url = $node->getUrl();

                if ( Str::startsWith( $url, '/' ) ) {
			$url = Str::appendUri( url( $url ) );
                }

                $attr = $node->data['attributes'] ?? [];

                $attr['href'] = e( $url );

                if ( $title = $node->getTitle() ) {
                        $attr['title'] = e( $title );
                }

		if ( 1 === count( $node->children() ) && $node->firstChild() instanceof Image ) {
			return ( new ImageRenderer( $node ) )->render( $node->firstChild(), $childRenderer );
		}


                $innerHtml = $childRenderer->renderNodes( $node->children() );

                return new HtmlElement( 'a', $attr, $innerHtml );
        }
}
