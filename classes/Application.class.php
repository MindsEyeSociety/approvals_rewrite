<?php
include_once("classes/Approval.class.php");


class Application {
	var $id;
	var $app_number;
	var $user_id;
	var $org_id;
	var $character_id;
	var $venue_id;
	var $category;
	var $required_approval;
	var $status;
	var $active;
	var $description;
	var $justification;
	var $character_sheet;
	var $mechanics;
	var $creation_date;
	var $status_change_date;
	var $update_date;
	var $low_approval;
	var $mid_approval;
	var $high_approval;
	var $top_approval;
	var $global_approval;
	var $character;
	var $venue;
	var $organization;

	function __construct(
		$app_number = '',
		$user_id = 0,
		$org_id = 0,
		$character_id = 0,
		$venue_id = 0,
		$category = '',
		$required_approval = '',
		$status = '',
		$active = 0,
		$description = '',
		$justification = '',
		$character_sheet = '',
		$mechanics = '',
		$creation_date = 0,
		$status_change_date = 0,
		$update_date = 0,
		$low_approval = null,
		$mid_approval = null,
		$high_approval = null,
		$top_approval = null,
		$global_approval = null,
		$id = null
	) {
		$this->app_number = $app_number;
		$this->user_id = $user_id;
		$this->org_id = $org_id;
		$this->character_id = $character_id;
		$this->venue_id = $venue_id;
		$this->category = $category;
		$this->required_approval = $required_approval;
		$this->status = $status;
		$this->active = $active;
		$this->description = strip_tags($description);
		$this->justification = strip_tags($justification);
		$this->character_sheet = strip_tags($character_sheet);
		$this->mechanics = strip_tags($mechanics);
		$this->creation_date = $creation_date;
		$this->status_change_date = $status_change_date;
		$this->update_date = $update_date;
		$this->low_approval = $low_approval;
		$this->mid_approval = $mid_approval;
		$this->high_approval = $high_approval;
		$this->top_approval = $top_approval;
		$this->global_approval = $global_approval;
		$this->id = $id;
	}

	function setStatus( $status ) {
		if( $this->status != $status ) {
			$this->status = $status;
			$this->status_change_date = time();
		}
	}

	/*
	var $low_approval_date;
	var $low_approval_user_id;
	var $mid_approval_date;
	var $mid_approval_user_id;
	var $high_approval_date;
	var $high_approval_user_id;
	var $top_approval_date;
	var $top_approval_user_id;
	var $global_approval_date;
	var $global_approval_user_id;
	*/

}
