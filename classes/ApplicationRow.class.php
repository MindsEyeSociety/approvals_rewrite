<?php
include_once("Organization.class.php");

class ApplicationRow {
	var $app_number;
	var $creation_date;
	var $description;
	var $required_approval;
	var $status_change_date;
	var $update_date;
	var $organization;
	var $character_name;
	var $character_type;
	var $venue;
	var $user_id;
	var $category;
	var $status;
	var $active;
	var $a_org_id;
	var $u_org_id;

	function __construct(
		$app_number,
		$creation_date,
		$description,
		$update_date,
		$required_approval,
		$status_change_date,
		$organzation,
		$character_name,
		$character_type,
		$venue,
		$user_id,
		$category,
		$status,
		$active,
		$a_org_id,
		$u_org_id
	) {
		$this->app_number = $app_number;
		$this->creation_date = $creation_date;
		$this->description = strip_tags($description);
		$this->required_approval = $required_approval;
		$this->status_change_date = $status_change_date;
		$this->update_date = $update_date;
		$this->organzation = $organzation;
		$this->character_name = strip_tags($character_name);
		$this->character_type = strip_tags($character_type);
		$this->venue = $venue;
		$this->user_id = $user_id;
		$this->category = $category;
		$this->status = $status;
		$this->active = $active;
		$this->a_org_id = $a_org_id;
		$this->u_org_id = $u_org_id;
	}
}
