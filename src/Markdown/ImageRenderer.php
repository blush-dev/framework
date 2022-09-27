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
use League\CommonMark\Extension\CommonMark\Node\Inline\{Image, Link};
use League\CommonMark\Node\Node;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Renderer\{ChildNodeRendererInterface, NodeRendererInterface};
use League\CommonMark\Util\HtmlElement;

class ImageRenderer implements NodeRendererInterface
{
	public function __construct( protected ?Node $parent = null ) {}

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
		$attr       = [];

                if ( Str::startsWith( $url, '/' ) ) {
			$url = Str::appendUri( url( $url ) );
                }

                if ( 1 === count( $node->children() ) && $node->firstChild() instanceof Text ) {
                	$alt = $childRenderer->renderNodes( $node->children() );
                }

		$parent = $this->parent ?? $node->parent();

		// Get attributes from `<img>` element if they exist.
		if ( ! empty( $node->data['attributes'] ) ) {
			$attr = $node->data['attributes'];

		// If the `<img>` parent is a link, try its attributes.
		} elseif ( $parent instanceof Link ) {
			if ( ! empty( $parent->data['attributes'] ) ) {
				$attr = $parent->data['attributes'];
				$parent->data['attributes'] = [];
			}
		}

                $image = new HtmlElement( 'img', [
                        'src' => e( $url ),
			'alt' => e( $alt ),
                ], '', true );

		if ( $this->parent instanceof Link ) {
			$url = $this->parent->getUrl();

			if ( Str::startsWith( $url, '/' ) ) {
				$url = Str::appendUri( url( $url ) );
			}

			$image = new HtmlElement( 'a', [
				'href' => e( $url )
			], $image );
		}

                if ( $node->getTitle() ) {
                        $figcaption = new HtmlElement(
                                'figcaption',
                                [],
                                $node->getTitle()
                        );
                }

                return new HtmlElement(
                        'figure',
                        $attr,
                        "{$image}\n{$figcaption}"
                );
        }
}
