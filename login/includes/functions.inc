<?php

function campagestart() {
	$userdata = array();
	if( isset( $_COOKIE['dev_camnumber'] ) ) {
		$userdata['cam_number'] = $_COOKIE['dev_camnumber'];
		$userdata['session_logged_in']=1;
	}
	return $userdata;
};

function get_ip() {
};
