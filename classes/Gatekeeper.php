<?php

class GateKeeper {

	function __construct() {

	}

	function getInstance() {
		static $instance;
		if( !isset($instance ) ) {
			$instance = new GateKeeper();
		}
		return $instance;
	}

	function mayAddVSS( $organization_id, $keyMaster ) {
		return $keyMaster->isOrgStoryteller( $organization_id );
	}

	function mayViewComment( $commentLevel, $keyMaster ) {

	}

	function maySummonGozer( $keyMaster ) {
		return $keyMaster->super_user;
	}
}
