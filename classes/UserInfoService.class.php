<?php
class UserInfoService {
	var $userInfoDAO;

	function __construct( $userInfoDAO ) {
		$this->userInfoDAO = $userInfoDAO;
	}

	function getSortedOrganizationsUserList( $organization_ids ,$current_user_id = false) {
		$org_users = $this->userInfoDAO->readIdsByOrganizations( $organization_ids );
		$nameList = Array();
		for( $i=0;$i<count($org_users);$i++) {
			$user_info = $this->userInfoDAO->getUserInfo(intval($org_users[$i]['id']));
			if($current_user_id && $org_users[$i]['id'] == $current_user_id) $current_user_id = false;
			$org_users[$i]['name'] = trim($user_info['name']);
	  		if($org_users[$i]['name'] == 'No Name Set' or $org_users[$i]['name'] == '') continue;
	  		$nameList[] = $org_users[$i];
		}
		if($current_user_id){
			$user_info = $this->userInfoDAO->getUserInfo($current_user_id);
			$nameList[] = Array('id' => $current_user_id, 'name' => trim($user_info['name']));
		}
		usort($nameList, function($a, $b) { return strcmp(strtolower($a['name']), strtolower($b['name'])); });
		return $nameList;
	}
}
?>
