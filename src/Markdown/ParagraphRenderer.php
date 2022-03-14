<?php
/**
 * Markdown paragraph renderer.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Markdown;

use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

class ParagraphRenderer implements NodeRendererInterface
{
	/**
	 * Renders the element.
	 *
	 * @since 1.0.0
	 */
        public function render( Node $node, ChildNodeRendererInterface $childRenderer )
	{
                $innerHtml = $childRenderer->renderNodes( $node->children() );

                // Don't wrap images with <p> tags.
                if ( 1 === count( $node->children() ) ) {
                        $child = $node->firstChild();
                        if ( $child instanceof Image ) {
                                return $innerHtml;
                        }
                }

                return new HtmlElement(
                        'p',
                        $node->data['attributes'] ?? [],
                        $innerHtml
                );
        }
}
