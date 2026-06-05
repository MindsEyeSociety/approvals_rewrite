<?php
class UserInfo {
	var $ww_user;
	var $super_user;
	var $cam_id;
	var $cam_expiry;
	var $firstname;
	var $lastname;
	var $address;
	var $city;
	var $state;
	var $country;
	var $zip;
	var $email;
	var $birthday;
	var $phone;
	var $privacy;
	var $cam_changed;
	var $last_login_date;

	function __construct(
		$ww_user = "",
		$super_user = "",
		$cam_id = "",
		$cam_expiry = "",
		$firstname = "",
		$lastname = "",
		$address = "",
		$city = "",
		$state = "",
		$country = "",
		$zip = "",
		$email = "",
		$birthday = "",
		$phone = "",
		$privacy = "",
		$cam_changed = "",
		$last_login_date = ""
	) {
		$this->ww_user = $ww_user;
		$this->super_user = $super_user;
		$this->cam_id = $cam_id;
		$this->cam_expiry = $cam_expiry;
		$this->firstname = $firstname;
		$this->lastname = $lastname;
		$this->address = $address;
		$this->city = $city;
		$this->state = $state;
		$this->country = $country;
		$this->zip = $zip;
		$this->email = $email;
		$this->birthday = $birthday;
		$this->phone = $phone;
		$this->privacy = $privacy;
		$this->cam_changed = $cam_changed;
		$this->last_login_date = $last_login_date;
	}
}
