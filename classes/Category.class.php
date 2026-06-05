<?php

class Category {
	var $id;
	var $category;

	function __construct( $category = "", $id = "" ) {
		$this->category = $category;
		$this->id = $id;
	}
}
