<?php
include_once("application.inc");

$app_number = ($mode == "add" || $mode == "doAdd" )?"pending":$app_info->app_number;
// Populate info into the drop down menus
$venueDAO = $daoFactory->getVenueDAO();
$userDAO = $daoFactory->getUserInfoDAO();
$venues = $venueDAO->getVenueOptions();
if ( $app_info->character_id != "0" && $app_info->character_id !="" && is_numeric($app_info->character_id) ) {
	$categories = $categoryDAO->readOptions( $app_info->character_id );
} else {
	$categories = $categoryDAO->readOptions();
}

$storytellers = array();
if( is_object( $app_info->character ) ) {
	if( is_object( $app_info->character ) && is_numeric($app_info->character->vss_id) && $app_info->character->vss_id > 0 ) {
		if( !is_object( $app_info->character->vss ) ) {
			$app_info->character->vss = $vssDAO->readByID( $app_info->character->vss_id );
		}
		if( is_object( $app_info->character->vss ) ) {
			$storytellers[] = array(
				'title' => 'VST',
				'id'    => $app_info->character->vss->storyteller_id
			);
			$vss_org_id = $app_info->character->vss->organization_id;
		} else {
			$is_unassigned=true;
			$vss_org_id = $app_info->org_id;
		}
	} elseif ( is_numeric($app_info->character->vss_id) && $app_info->character->vss_id < 0 ) {
		$vss_org_id = 0-$app_info->character->vss_id;
		$organization = $organizationDAO->readByID($vss_org_id);
		$storytellers[] = array(
			'title' => 'ST',
			'id'    => $organization->admin_user_id
		);
	} elseif ( is_object($app_info->character->organization) ) {
		$storytellers[] = array(
			'title' => 'ST',
			'id'    => $app_info->character->organization->admin_user_id
		);
		$vss_org_id = $app_info->character->organization->id;
	} else {
		$is_unassigned=true;
		$vss_org_id = $app_info->org_id;
	}
} else {
	$vss_org_id = $app_info->org_id;
}

$affiliation = $organizationDAO->readByID( $vss_org_id );

if( $affiliation->chapter ) {
	$res = $db->query( sprintf(
		"SELECT admin_user_id ".
		"FROM organizations ".
		"WHERE chapter='%s' AND domain='%s' AND region='%s' AND nation='%s'",
		$db->escape($affiliation->chapter),
		$db->escape($affiliation->domain),
		$db->escape($affiliation->region),
		$db->escape($affiliation->nation)
	));
	if( $row=$res->nextRow() && $row['admin_user_id'] != '') {
		$storytellers[] = array(
  			'title' => 'CST',
  			'id'    => $row['admin_user_id']
		);
	}
}
if( $affiliation->domain ) {
	$res = $db->query( sprintf(
		"SELECT admin_user_id FROM organizations o ".
		"WHERE o.chapter='' and o.domain='%s' and o.region='%s' and o.nation='%s'",
		$db->escape( $affiliation->domain ),
		$db->escape( $affiliation->region ),
		$db->escape( $affiliation->nation )
	));
	if( $row=$res->nextRow() ) {
  		$storytellers[] = array(
  			'title' => 'DST',
  			'id'    => $row['admin_user_id']
  		);
	}
}
if( $affiliation->region ) {
	$res = $db->query( sprintf(
		"SELECT admin_user_id FROM organizations o ".
		"WHERE o.chapter='' and o.domain='' and o.region='%s' and o.nation='%s'",
		$db->escape( $affiliation->region ),
		$db->escape( $affiliation->nation )
	));
	if( $row=$res->nextRow() ) {
 		$storytellers[] = array(
  			'title' => 'RST',
  			'id'    => $row['admin_user_id']
  		);
	}
}
if( $affiliation->nation ) {
	$res = $db->query( sprintf(
		"SELECT admin_user_id FROM organizations o ".
		"WHERE o.chapter='' and o.domain='' and o.region='' and o.nation='%s'",
		$db->escape( $affiliation->nation )
	));
	if( $row=$res->nextRow() ) {
 		$storytellers[] = array(
  			'title' => 'NST',
  			'id'    => $row['admin_user_id']
  		);
	}
}

$thisorg_location = "";
if ( $affiliation->city ) {
	$thisorg_location.=$affiliation->city;
}
if ( $affiliation->state ) {
	if ( $thisorg_location!="" ) {
		$thisorg_location.=", ";
	}
	$thisorg_location.=$affiliation->state;
}
if ( $affiliation->country ) {
	if ( $thisorg_location!="" ) {
		$thisorg_location.=", ";
	}
	$thisorg_location.=$affiliation->country;
}

if ( $_SESSION['admin_level'] == "globe" ) {
	//if ( $_SESSION['admin_assistant']==1 ) {
	//	$thisshowlist=array("Low","Mid","High","Top");
	//} else {
		$thisshowlist=array("Low","Mid","High","Top","Global");
	//}
} else if ( $_SESSION['admin_level']=="nation" ) {
//	if ( $_SESSION['admin_assistant']==1 ) {
//		$thisshowlist=array("Low","Mid","High");
//	} else {
		$thisshowlist=array("Low","Mid","High","Top");
//	}
} else if ( $_SESSION['admin_level']=="region" ) {
//	if ( isset( $_SESSION["admin_assistant"]) && $_SESSION['admin_assistant']==1 ) {
//		$thisshowlist=array("Low","Mid");
//	} else {
		$thisshowlist=array("Low","Mid","High");
//	}
} else if ( $_SESSION['admin_level']=="domain" ) {
	if ( isset( $_SESSION["admin_assistant"]) && $_SESSION['admin_assistant']==1 ) {
		$thisshowlist=array("Low");
	} else {
		$thisshowlist=array("Low","Mid");
	}
} else if ( $_SESSION['admin_level']=="chapter" ) {
	if ( isset( $_SESSION["admin_assistant"]) && $_SESSION['admin_assistant']==1 ) {
		$thisshowlist=array();
	} else {
		$thisshowlist=array("Low");
	}
} else {
	$thisshowlist=array();
}

if ( is_object( $app_info->character ) && strtolower($app_info->character->char_type)=="npc" ) {
	if( $app_info->character->vss_id > 0 ) {
		if( !is_object( $app_info->character->vss ) ) {
			$app_info->character->vss = $vssDAO->readByID( $app_info->character->vss_id );
		}
		if( is_object( $app_info->character->vss ) ) {
			$storyteller = $userDAO->getUserInfo($app_info->character->vss->storyteller_id);
		}
	} elseif ( $app_info->character->vss_id < 0 ) {
		$organization= $organizationDAO->readByID( 0-$character->vss_id );
		$storyteller = $userDAO->getUserInfo( $organization->admin_user_id );
	}
}

$statuslist=array();
if( $mode == "add" ) {
  $statuslist[] = "Pending Low";
} else {
  if ( $_SESSION['user_id']==$app_info->user_id ) {
  	$statuslist[] = $app_info->status;
  	if ( $app_info->status != "Approved" && $app_info->status != "Denied" ) {
  		if ( $_SESSION['admin_level']=="chapter" && $_SESSION['admin_assistant']!=1 ) {
  			$statuslist[] = "Pending Mid";
  		}
  		if ( $app_info->status == "Pending Mid" &&
  		     $_SESSION['admin_level']=="domain" &&
  				 $_SESSION['admin_assistant']!=1 ) {
  			$statuslist[] = "Pending High";
  		}
  		$statuslist[]="Removed";
  	}
  } else if ( ( $_SESSION['admin_level']=="chapter" &&
                $_SESSION['admin_assistant']!=1 ) ||
  					  ( $_SESSION['admin_level']=="domain" &&
                        $_SESSION['admin_assistant']==1) ) {
  	$statuslist= array( "Pending Low","Pending Mid", "Denied","Removed" );
  } else if ( ( $_SESSION['admin_level']=="domain" &&
                $_SESSION['admin_assistant']!=1) ||
  						( $_SESSION['admin_level']=="region" &&
  						  $_SESSION['admin_assistant']==1) ) {
  	$statuslist=array("Pending Low","Pending Mid","Pending High","Denied","Removed");
  } else if ( ( $_SESSION['admin_level']=="region" 
//&&$_SESSION['admin_assistant']!=1
) ||
  						($_SESSION['admin_level'] =="nation" &&
							$_SESSION['admin_assistant']==1) ) {
  	$statuslist=array("Pending Low","Pending Mid","Pending High","Pending Top","Denied","Removed");
  	if ( $_SESSION['admin_assistant']!=1 ) {
  		$statuslist[]="Approved";
  	}
  } else if ( ( $_SESSION['admin_level']=="nation" &&
                $_SESSION['admin_assistant']!=1) ||
							($_SESSION['admin_level'] == "globe" &&
							$_SESSION['admin_assistant']==1)) {
  	$statuslist=array("Pending Low","Pending Mid","Pending High","Pending Top","Pending Global","Denied","Removed");
  	if ( $_SESSION['admin_assistant']!=1 ) {
  		$statuslist[]="Approved";
  	}
} else if ($_SESSION['admin_level']=="globe" &&
			$_SESSION['admin_assistant'] != 1) {
  	$statuslist=array("Pending Low","Pending Mid","Pending High","Pending Top","Pending Global","Denied","Removed","Approved");
  } else {
  	$statuslist=array("Pending Low","Removed");
  }
	if ( !in_array( $app_info->status, $statuslist ) ) {
	  $statuslist[] = $app_info->status;
	}
}

if ( $_SESSION['admin_level'] == "globe" ) {
	//if ( $_SESSION['admin_assistant']==1 ) {
	//	$thisshowlist=array("Low","Mid","High","Top");
	//} else {
		$thisshowlist=array("Low","Mid","High","Top","Global");
	//}
} else if ( $_SESSION['admin_level']=="nation" ) {
	//if ( $_SESSION['admin_assistant']==1 ) {
	//	$thisshowlist=array("Low","Mid","High");
	//} else {
		$thisshowlist=array("Low","Mid","High","Top");
	//}
} else if ( $_SESSION['admin_level']=="region" ) {
//	if ( isset( $_SESSION["admin_assistant"]) && $_SESSION['admin_assistant']==1 ) {
//		$thisshowlist=array("Low","Mid");
//	} else {
		$thisshowlist=array("Low","Mid","High");
//	}
} else if ( $_SESSION['admin_level']=="domain" ) {
	if ( isset( $_SESSION["admin_assistant"]) && $_SESSION['admin_assistant']==1 ) {
		$thisshowlist=array("Low");
	} else {
		$thisshowlist=array("Low","Mid");
	}
} else if ( $_SESSION['admin_level']=="chapter" ) {
	if ( isset( $_SESSION["admin_assistant"]) && $_SESSION['admin_assistant']==1 ) {
		$thisshowlist=array();
	} else {
		$thisshowlist=array("Low");
	}
} else {
	$thisshowlist=array();
}
$thisappstatus=str_replace("Pending ", "", $app_info->status );

$smarty->assign( "app_info",$app_info);
$smarty->assign( "mode", $mode);
$smarty->assign( "venues", $venues);
$smarty->assign( "app_number", $app_number );
if( isset( $storyteller ) ) {
	$smarty->assign( "storyteller", $storyteller );
}
$smarty->assign( "categories", $categories );
$smarty->assign( "thisorg_location", $thisorg_location );
$smarty->assign( "storytellers",$storytellers);
$smarty->assign( "is_unassigned",isset($is_unassigned));
$smarty->assign( "statuslist", $statuslist);
$smarty->assign( "can_approve",
	strstr( $app_info->status, "Pending" ) !== FALSE
	&& in_array( $thisappstatus, $thisshowlist ) );
$smarty->assign( "can_deny",
	strstr( $app_info->status, "Pending" ) !== FALSE
	|| $app_info->status =="Approved" );
$smarty->assign( "can_publish",
	$app_info->status == "Approved" &&
	(
		$_SESSION['admin_level'] == "nation" ||
		$_SESSION['admin_level'] == "globe" ||
		$_SESSION['super_user']
	) );

$smarty->display("AppDetailsTopSection.html");

