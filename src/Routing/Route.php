<?php

namespace Blush\Routing;

class Route {

	protected $controller;
	protected $uri;
	protected $action;

	protected $paramters = [];
	protected $parameter_names = [];
	protected $wheres = [];

	public function __construct( $methods, $uri, $action ) {

		$this->methods = (array) $methods;
		$this->uri = $uri;

		// parse this.
		$this->action = $action;

	}

	public function uri() {

	}

	public function controller() {

	}

	public function position() {

	}

	public function hasParameters() {
		return ! empty( $this->parameters );
	}

	public function parameters() {
		return $this->parameters;
	}

	public function hasParameter( $name ) {

		if ( $this->hasParameters() ) {
			return array_key_exists( $this->parameters(), $name );
		}

		return false;
	}

	public function parameter( $name ) {

		if ( $this->hasParameter( $name ) ) {
			$parameters = $this->paramemters();
			return $parameters[ $name ];
		}

		return null;
	}

	public function wheres() {
		return $this->wheres;
	}

	public function where( $name, $regex = null ) {

		$wheres = $this->parseWhere( $name, $regex );

		foreach ( $wheres as $name => $regex ) {
			$this->wheres[ $name ] = $regex;
		}
	}

	protected function parseWhere( $name, $regex ) {
		return is_array( $name ) ? $name : [ $name => $expression ];
	}

	public function whereAlpha( $parameters ) {
		$this->addRegexToParameters( $parameters, '[a-zA-Z]+' );
	}

	public function whereAlphaNumeric( $parameters ) {
		$this->addRegexToParameters( $parameters, '[a-zA-Z0-9]+' );
	}

	public function whereNumber( $parameters ) {
		$this->addRegexToParameters( $parameters, '[0-9]+' );
	}

	public function whereYear( $parameters ) {
		$this->addRegexToParameters( $parameters, '[0-9]{4}' );
	}

	private function addRegexToParameters( $parameters, $regex ) {

		$wheres = [];

		foreach ( (array) $parameters as $name ) {
			$wheres[ $name ] = '[a-zA-Z]+';
		}

		$this->where( $wheres );
	}
}
