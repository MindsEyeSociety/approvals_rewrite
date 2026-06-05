<?php

class Approval {
	var $user_id;
	var $application_id;
	var $approval_date;

	function __construct( $user_id, $approval_date, $application_id ) {
		$this->$user_id = $user_id;
		$this->$application_id = $application_id;
		$this->$approval_date = $approval_date;
	}
}
