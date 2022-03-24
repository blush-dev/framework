<?php
/**
 * Message class.
 *
 * This is a built-in messaging system for the framework to display notices,
 * errors, and other necessary information to the user.  It should not be
 * considered a part of the public API at this time and is for internal use only.
 * In the long term, the plan is to scale this out for any type of system
 * messages, including custom ones.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Messenger;

use Blush\Tools\Str;

class Message
{
	/**
	 * Message to output.
	 *
	 * @since 1.0.0
	 */
	protected string $message = '';

	/**
	 * Type of message.  Appended as a class of `.blush-message--{$type}`.
	 * The default supported types are `note`, `success`, `warning`, and
	 * `error`.
	 *
	 * @since 1.0.0
	 */
	protected string $type = 'note';

	/**
	 * Makes a new message.
	 *
	 * @since 1.0.0
	 */
	public function make( string $message, string $type = 'note' ): self
	{
		$message = trim( $message );
		$message = ! Str::startsWith( $message, '<' ) ? "<p>{$message}</p>" : $message;

		$this->message = $message;
		$this->type    = $type;

		return $this;
	}

	/**
	 * Returns the message and styles as HTML.
	 *
	 * @since 1.0.0
	 */
	public function render(): string
	{
		$message = sprintf(
			'<div class="blush-message blush-message--%s">%s</div>',
			e( $this->type ?: 'note' ),
			$this->message
		);

	 	$styles = str_replace( [ "\t", "\n", "\r", "\s\s", "  " ], '', $this->styles() );

		return $message . $styles;
	}

	/**
	 * Displays the message and styles HTML.
	 *
	 * @since 1.0.0
	 */
	public function display(): void
	{
		echo $this->render();
	}

	/**
	 * Alias for `display()`.
	 *
	 * @since 1.0.0
	 */
	public function dump(): void
	{
		echo $this->display();
	}

	/**
	 * Dumps the HTML and dies.
	 *
	 * @since 1.0.0
	 */
	public function dd(): void
	{
		$this->dump();
		die();
	}

	/**
	 * Returns the CSS stylesheet.
	 *
	 * @since 1.0.0
	 */
	protected function styles(): string
	{
		return '<style>
		.blush-message {
			--blush-message-spacing: 2rem;
			--blush-message-color: #484a4c;
			--blush-message-color-accent: #484a4c;
			--blush-message-color-bg: #f4f9ff;
			--blush-message-color-shadow: #e9f3f8;
			--blush-message-color-shadow-text: #e9f3f8;

			clear:         both;
			position:      relative;
			box-sizing:    border-box;
			z-index:       999;

			width:         1024px;
			max-width:     100%;
			box-sizing:    border-box;
			margin:        var( --blush-message-spacing ) auto;
			padding:       var( --blush-message-spacing );

			font-family:   \"Source Code Pro\", Monaco, Consolas, \"Andale Mono WT\", \"Andale Mono\", \"Lucida Console\", \"Lucida Sans Typewriter\", \"DejaVu Sans Mono\", \"Bitstream Vera Sans Mono\", \"Liberation Mono\", \"Nimbus Mono L\", \"Courier New\", Courier, monospace;
			font-size:     18px;
			line-height:   1.75;
			color:         var( --blush-message-color );
			text-shadow:   0 1px var( --blush-message-color-shadow-text );
			background:    var( --blush-message-color-bg );
			box-shadow:    inset 1px 1px 10px var( --blush-message-color-shadow );
			border-radius: 6px;
			border-left:   6px solid var( --blush-message-color-accent );
		}
		.blush-message--note {
			--blush-message-color-accent: #2282bb;
			--blush-message-color: #2282bb;
		}
		.blush-message--success {
			--blush-message-color-accent: #338d00;
		}
		.blush-message--error {
			--blush-message-color-accent: #e23140;
		}
		.blush-message--warning {
			--blush-message-color-accent: #d59401;
		}
		.blush-message > * {
			margin-top: 0;
			margin-bottom: 0;
		}
		.blush-message > * + * {
			margin-top: var( --blush-message-spacing );
			margin-bottom: 0;
		}
		.blush-message :first-child {
			margin-top: 0;
		}
		.blush-message ul {
			list-style-type: circle;
		}
		.blush-message code {
			font: inherit;
			color: #484a4c;
			padding: 0.125em 0.25em;
			background: #e6eef9;
		}
		</style>';
	}
}
