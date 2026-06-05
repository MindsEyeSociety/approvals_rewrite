<?php
class UserVO {
	var $id;
	var $username;
	var $ww_number;
	var $password;
	var $name;
	var $email;
	var $phone_number;
	var $org_id;
	var $admin_org_id;
	var $super_user;
	var $assistant;
	var $last_login_date;
	var $password_hint;
	
	function UserVO(
		$id = "",
		$username = "",
		$ww_number = "",
		$password = "",
		$name = "",
		$email = "",
		$phone_number = "",
		$org_id = "",
		$admin_org_id = "",
		$super_user = "",
		$assistant = "",
		$last_login_date = "",
		$password_hint = ""
	) {
		$this->id = $id;
		$this->username = $username;
		$this->ww_number = $ww_number;
		$this->password = $password;
		$this->name = $name;
		$this->email = $email;
		$this->phone_number = $phone_number;
		$this->org_id = $org_id;
		$this->admin_org_id = $admin_org_id;
		$this->super_user = $super_user;
		$this->assistant = $assistant;
		$this->last_login_date = $last_login_date;
		$this->password_hint = $password_hint;
	}
}

?>