<?php

namespace Blush\Cache;

use Blush\Proxies\App;
use Blush\Tools\Collection;
use Blush\Tools\Str;

abstract class Cache {

	protected $name;
	protected $path;
	protected $data;

	public function __construct( $name, $path = ''  ) {

		$this->name = Str::afterLast( $name, '/' );
		$this->path = App::resolve( 'path.cache' ) . '/' . Str::beforeLast( $name, '/' );
	}

	//abstract protected function fileType();
	abstract protected function filename();

	protected function path( $file = '' ) {
		$file = trim( $file, '/' );
		return $file ? "{$this->path}/{$file}" : $this->path;
	}

	public function make() {
		if ( ! file_exists( $this->path() ) ) {
			mkdir( $this->path(), 0775, true );
		}
	}

	public function get() {
		return $this->data ?: null;
	}

	// Child classes should write the file contents here. Otherwise, the
	// data is only cached for a single page load.
	public function set( $data ) {
		$this->make();
		$this->data = $data;
	}

	public function delete() {
		if ( file_exists( $this->filename() ) ) {
			unlink( $this->filename() );
		}
	}
}
