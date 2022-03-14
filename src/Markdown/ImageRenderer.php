<?php
/**
 * Markdown image renderer.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Markdown;

use Blush\Tools\Str;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Node\Node;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

class ImageRenderer implements NodeRendererInterface
{
	/**
	 * Renders the element.
	 *
	 * @since 1.0.0
	 */
        public function render( Node $node, ChildNodeRendererInterface $childRenderer )
	{
                $url        = $node->getUrl();
		$alt        = '';
		$figcaption = '';

                if ( Str::startsWith( $url, '/' ) ) {
			$url = Str::appendUri( uri(), $url );
                }

                if ( 1 === count( $node->children() ) && $node->firstChild() instanceof Text ) {
                	$alt = $childRenderer->renderNodes( $node->children() );
                }

                $image = new HtmlElement( 'img', [
                        'src' => e( $url ),
			'alt' => e( $alt ),
                ] );

                if ( $node->getTitle() ) {
                        $figcaption = new HtmlElement(
                                'figcaption',
                                [],
                                $node->getTitle()
                        );
                }

                return new HtmlElement(
                        'figure',
                        $node->data['attributes'] ?? [],
                        "{$image}\n{$figcaption}"
                );
        }
}
