<?php

namespace Blush\Controllers;

use Suin\RSSWriter\Feed as RSSFeed;
use Suin\RSSWriter\Channel;
use Suin\RSSWriter\Item;

use Blush\App;
use Blush\ContentTypes;
use Blush\Entry\Entries;
use Blush\Entry\Locator;

class Feed {

	protected $params;

	public function __invoke( array $params = [] ) {

		$this->params = $params;

		header('Content-Type: application/rss+xml; charset=utf-8');

		return $this->feed();
	}

	protected function entries() {

		$path    = ContentTypes::get( 'post' )->path();
		$locator = new Locator( $path );

		$args = [
			'number' => 10,
			'order'  => 'desc'
		];

		return new Entries( $locator, $args );
	}

	protected function feed() {

		$feed    = new RSSFeed();
		$channel = new Channel();

		$channel
			->title( 'Justin Tadlock' )
			->description( 'Life &amp; Stuff' )
			->url( e( uri() ) )
			->feedUrl( e( uri( 'feed' ) ) )
			->language( 'en-US' )
			->copyright( 'Copyright ' . date( 'Y' ) . ', Justin Tadlock' )
			->ttl( 60 )
			->appendTo( $feed );

		foreach ( $this->entries()->all() as $entry ) {

			$item = new Item();

			$item
				->title( e( $entry->title() ) )
				->description( $entry->excerpt() )
				->contentEncoded( $entry->content() )
				->url( e( $entry->uri() ) )
				->author( $entry ? e( $entry->author()->title() ) : 'Justin Tadlock' )
				->pubDate( $entry->meta( 'date' ) )
				->preferCdata( true )
				->appendTo( $channel );
		}

		if ( ! empty( $entry ) ) {
			$channel->pubDate( $entry->meta( 'date' ) )->lastBuildDate( $entry->meta( 'date' ) );
		}

		return $feed;
	}
}
