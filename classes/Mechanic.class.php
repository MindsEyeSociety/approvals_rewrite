<?php

class Mechanic {
	var $id;
	var $venue_id;
	var $category;
	var $title;
	var $mechanics;
	function __construct( $venue_id, $category, $title, $mechanic ) {
		$this->venue_id = $venue_id;
		$this->category = $category;
		$this->title = strip_tags($title);
		$this->mechanic = strip_tags($mechanics);
	}
}
