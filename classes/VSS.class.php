<?php

class VSS {
	var $id;
	var $organization_id;
	var $storyteller_id;
	var $name;
	var $venue_id;
	var $email;

	function __construct( $name = "", $email="", $venue_id = "", $storyteller_id = "", $organization_id = "", $vss = "", $id = "") {
		$this->name = strip_tags($name);
		$this->venue_id = $venue_id;
		$this->storyteller_id = $storyteller_id;
		$this->organization_id = $organization_id;
		$this->vss = strip_tags($vss);
		$this->id = $id;
		$this->email = strip_tags($email);
	}
}
