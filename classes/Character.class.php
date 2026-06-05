<?php
class Character {
	var $id;
	var $name;
	var $venue_id;
	var $subtype;
	var $user_id;
	var $char_type;
	var $org_id;
	var $vss_id;
	var $background;
	var $character_sheet;
	var $active;
	var $char_dead;
	var $last_updated;
	var $approved_in_vss;
	var $venue;
	var $organization;
	var $vss;

	function __construct(
		$id = "",
		$name = "",
		$venue_id = "",
		$subtype = "",
		$user_id = "",
		$char_type = "",
		$org_id = "",
		$vss_id = "",
		$background = "",
		$character_sheet = "",
		$active = "",
		$char_dead = "",
		$last_updated = "",
		$approved_in_vss = ""
	) {
		$this->id = $id;
		$this->name = strip_tags($name);
		$this->venue_id = $venue_id;
		$this->subtype = $subtype;
		$this->user_id = $user_id;
		$this->char_type = strip_tags($char_type);
		$this->org_id = $org_id;
		$this->vss_id = $vss_id;
		$this->background = strip_tags($background);
		$this->character_sheet = strip_tags($character_sheet);
		$this->active = $active;
		$this->char_dead = $char_dead;
		$this->last_updated = $last_updated;
		$this->approved_in_vss = $approved_in_vss;
	}
}
