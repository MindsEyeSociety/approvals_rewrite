<?php
class Permissions {
	var $permissions;

	function __construct( $user_id ) {
		$this->permissions = array();
		$this->user_id = $user_id;
	}

	function getUserID() {
		return $this->user_id;
	}

	function setUserID( $user_id ) {
		$this->user_id = $user_id;
	}

	function grantAll() {
		global $db;
		$query = "SELECT id FROM organizations";
		$db->query( $query );
		while( $row = $db->nextRow() ) {
			$this->grantPermissions( $row["id"], 0 );
		}
	}


	function grantPermissions( $org_id, $venue_id = 0 ) {
		if( !is_numeric($venue_id) ) $venue_id = 0;
		if( !array_key_exists( $org_id, $this->permissions ) ) {
			$this->permissions[$org_id] = array();
		}
		if( ! array_key_exists( $venue_id, $this->permissions[$org_id] ) ) {
			$this->permissions[$org_id][$venue_id] = 0;
		}
	}

	function grantFinalPermissions( $org_id, $venue_id ) {
		if( !is_numeric($venue_id) ) $venue_id = 0;
		if( !array_key_exists( $org_id, $this->permissions ) ) {
			$this->permissions[$org_id] = array();
		}
		$this->permissions[$org_id][$venue_id] = 1;
	}

	function getAdminOrgList() {
		$admin_org_list = array_keys($this->permissions);

		return $admin_org_list;
	}

	function getAdminOrgVenueList() {
		$admin_org_venue_list = array();
		foreach( $this->permissions as $org_id => $venue_array ) {
			foreach( $venue_array as $venue_id => $permission) {
				if( !array_key_exists( $venue_id, $admin_org_venue_list ) ) {
					$admin_org_venue_list[$venue_id] = array();
				}
				$admin_org_venue_list[$venue_id][] = $org_id;
			}
		}
		return $admin_org_venue_list;
	}

	function getFinalAuthorityList() {
		$final_authority_list = array();
		foreach( $this->permissions as $org_id => $venue_array ) {
			foreach( $venue_array as $venue_id => $permission ) {
				if( $permission == 1 ) {
					$final_authority_list[] = $org_id;
				}
			}
		}
		return $final_authority_list;
	}

	function getPermissions() {
		return $this->permissions;
	}
}

function getStorytellerOrganizations( $user_id ) {
	global $db;
	$organizations = array();
	$query = "SELECT o.chapter, o.domain, o.region, o.nation, o.globe, ".
		"  s.organization_id, s.venue_id, s.assistant ".
		"FROM storytellers s ".
		"LEFT JOIN organizations o on o.id = s.organization_id ".
		"WHERE s.user_id=%d";
	$query = sprintf($query,$user_id);
	$db->query($query);
	$result = $db->getAllRows();
	return $db->getAllRows();
}

$user_info = $userInfoDAO->getUserInfo( $_SESSION["user_id"] );

$_SESSION['last_login_date'] = $user_info['last_login_date'];
$_SESSION['super_user'] = $user_info['super_user'];
$_SESSION['user_name'] = $user_info['name'];
$_SESSION['email'] = $user_info['email'];

$userInfoDAO->updateLastLoginDate( $_SESSION['user_id'] );

$permissions = new Permissions( $_SESSION["user_id"] );

if ( $_SESSION["super_user"] ) {
	$permissions->grantAll();
}

$final_authority = array();
$admin_level = "";
// Ensure admin_level is always defined in the session to avoid undefined index warnings
$_SESSION['admin_level'] = $admin_level;
  $organizations = getStorytellerOrganizations( $_SESSION["user_id"] );
  $top_final_organization = null;
  $top_organization = null;
  $organizationDAO = $daoFactory->getOrganizationDAO();
  foreach( $organizations as $stquery ) {
  	$organization = new Organization( $stquery['globe'], $stquery['nation'], $stquery['region'], $stquery['domain'], $stquery['chapter'], '', '', '', '', '', '', '', $stquery['organization_id'] );
	$org_level = $organization->getLevel();
	if( $organization->isHigherThan( $top_organization ) ) {
		$top_organization = $organization;
	}
	if( $stquery["assistant"] == 0 && $organization->isHigherThan( $top_final_organization ) ) {
		$top_final_organization = $organization;
	}
	if( $stquery["assistant"] == 0 ) {
		$permissions->grantFinalPermissions( $stquery["organization_id"], $stquery["venue_id"] );
	} else {
		$permissions->grantPermissions( $stquery["organization_id"], $stquery["venue_id"]);
	}

	if( $org_level != null ) {
		if( $org_level->REGION == $org_level->organization_level && !$stquery["assistant"] ) {
			/* RSTs get to see applications from all around the country */
			$nation = new Organization( $stquery['globe'], $stquery['nation'] );
			$child_orgs = $organizationDAO->readSubOrganizationIDs($nation);
		} else {
			$child_orgs = $organizationDAO->readSubOrganizationIDs($organization);
		}
		if( $_SESSION["super_user"] ) {
			$globe = new Organization( $stquery['globe'] );
			$child_orgs = $organizationDAO->readSubOrganizationIDs($globe);
		}
		foreach( $child_orgs as $org_id ) {
			$permissions->grantPermissions( $org_id, $stquery["venue_id"] );
		}
	}
}
$_SESSION['admin_assistant'] = $top_final_organization != null && !$top_final_organization->isHigherThan($top_organization);
if( $top_organization != null ) {
	$_SESSION['obj_admin_level'] = $top_organization->getLevel();
	$_SESSION['admin_level'] = $_SESSION['obj_admin_level']->organization_level ?? '';
	$_SESSION['admin_org_id'] = $top_organization->id;
}
if( $top_final_organization != null ) {
	$_SESSION['obj_max_final_authority_level'] = $top_final_organization->getLevel();
	$_SESSION['max_final_authority_level'] = $_SESSION['obj_max_final_authority_level']->organization_level ?? '';
}
if(isset($_SESSION['admin_level']) && ($_SESSION['admin_level'] == 'nation' || $_SESSION['admin_level'] == 'region')) $_SESSION['admin_assistant'] = 0;
$organizationDAO = $daoFactory->getOrganizationDAO();

$_SESSION['org_id'] = $user_info['org_id'];

$_SESSION['constraint_level_list'] = array();

// Initialize session keys for chapter, domain, region from top_organization
if( $top_organization != null ) {
	$_SESSION['chapter'] = $top_organization->chapter;
	$_SESSION['domain'] = $top_organization->domain;
	$_SESSION['region'] = $top_organization->region;
}

$_SESSION['admin_org_venue_list'] = $permissions->getAdminOrgVenueList();
$_SESSION['admin_final_authority_list'] = $permissions->getFinalAuthorityList();
$_SESSION['admin_org_list'] = $permissions->getAdminOrgList();

// Load organization admin permissions (separate from storytelling permissions)
$orgAdminUserList = array();
if ($_SESSION['user_id']) {
    $adminOrgCheck = $db->query("SELECT id FROM organizations WHERE admin_user_id = '" . intval($_SESSION['user_id']) . "' ORDER BY id");
    while($row = $adminOrgCheck->nextRow()) {
        $orgAdminUserList[$row['id']] = 1;
    }
}
$_SESSION['org_admin_user_list'] = $orgAdminUserList;

$_SESSION['admin_vss_list'] = array();
$_SESSION['admin_final_authority_vss_list'] = array();
$query = "SELECT storyteller_id, id FROM vsss WHERE 1=0 ";
foreach( $_SESSION['admin_org_venue_list'] as $venue_id => $org_list ) {
	if( $venue_id == 0 ) {
		$query .="OR org_id IN ('".implode("','", $org_list)."') ";
	} else {
		$query .= sprintf( "OR ( venue_id=%d ", $venue_id );
		$query .= " AND org_id IN ('".implode("','", $org_list)."')  )";
  }
}
$query .=  sprintf( "OR storyteller_id=%d ", $_SESSION['user_id']);

$db->query($query);
while( $row = $db->nextRow() ) {
	array_push( $_SESSION['admin_vss_list'], $row['id'] );
	if ( $row["storyteller_id"]==$_SESSION['user_id'] ) {
		array_push( $_SESSION['admin_final_authority_vss_list'], $row['id'] );
	}
}

if( count( $_SESSION['admin_vss_list'] ) > 0 && $_SESSION['admin_level'] == "" ) {
	$_SESSION['admin_level'] = 'chapter';
}

if ( $_SESSION['super_user'] ) {
	$_SESSION['admin_level']="globe";
}

switch( $_SESSION['admin_level'] ) {
	case 'nation':
	case 'globe':
		$_SESSION['constraint_level'] = 'top';
		$_SESSION['constraint_level_list'] = array( 'low','mid','high','top' );
		break;
	case 'region':
		$_SESSION['constraint_level'] = 'high';
		$_SESSION['constraint_level_list'] = array( 'low','mid','high' );
		break;
	case 'domain':
		$_SESSION['constraint_level'] = 'mid';
		$_SESSION['constraint_level_list'] = array( 'low','mid' );
		break;
	case 'chapter':
		$_SESSION['constraint_level'] = 'low';
		$_SESSION['constraint_level_list'] = array( 'low' );
		break;
}