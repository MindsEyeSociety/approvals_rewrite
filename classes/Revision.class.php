<?php

class Revision{
	var $application_id;
	var $user_id;
	var $revision_date;
	var $revision;

	function __construct( $application_id = 0, $user_id = 0, $revision = "" ) {
		$this->application_id = $application_id;
		$this->user_id = $user_id;
		$this->revision_date = time();
		$this->revision = $revision;
	}
}
