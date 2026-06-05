<?php
include_once("db.inc");
include_once("AppDetails_functions.php");
include_once("classes/EmailService.php");
include_once("classes/ApplicationService.php");
include_once("classes/Application.class.php");

ob_start();
$characterDAO = $daoFactory->getCharacterDAO();
$organizationDAO = $daoFactory->getOrganizationDAO();
$vssDAO = $daoFactory->getVSSDAO();
$userInfoDAO = $daoFactory->getUserInfoDAO();
$applicationDAO = $daoFactory->getApplicationDAO();
$revisionDAO = $daoFactory->getRevisionDAO();
$categoryDAO = $daoFactory->getCategoryDAO();
$venueDAO = $daoFactory->getVenueDAO();
$applicationService = new ApplicationService( $organizationDAO, $characterDAO, $vssDAO, $userInfoDAO );
$emailService = new EmailService( $userInfoDAO, $characterDAO, $applicationService );

/* If the user isn't logged in, send them back home */
if ( !isset( $_SESSION['user_id'] ) ) {
	header("Location: index.php");
	exit();
}

/* The Application id could be either from POST or GET or might be zero on a new app */
$application_id = isset($_GET['id']) ? $_GET['id'] : ( isset($_POST["id"]) ? $_POST["id"]: 0 ) ;
$mode = isset( $_GET['mode'] ) ? $_GET['mode'] :
		( isset( $_POST['mode'] ) ? $_POST['mode'] : "display");
$char_id = isset($_GET['char_id']) ? $_GET['char_id'] : 0;

$finalcomment = false;
$thisuser_id = isset( $_GET['appuser_id'] ) ? $_GET['appuser_id'] : $_SESSION['user_id'];
if( is_array( $_SESSION['admin_org_list'] ) &&
	sizeof( $_SESSION['admin_org_list'] ) > 0 ) {
	$thisadminorglist = "('" . implode( "','", $_SESSION['admin_org_list'] ) . "')";
}

$app_info = $applicationDAO->readByID( $application_id );
$app_info->character = $characterDAO->readByID( $app_info->character_id );
$app_info->venue = $venueDAO->readByID( $app_info->venue_id );
$app_info->organization = $organizationDAO->readByID( $app_info->org_id );
if( $app_info->character != null ) {
	$app_info->character->venue = $venueDAO->readByID( $app_info->character->venue_id);
	if( $app_info->character->vss > 0 ) {
		$app_info->character->vss = $vssDAO->readByID( $app_info->character->vss_id );
	} elseif ( $app_info->character->vss_id < 0 ) {
		$app_info->character->organization = $organizationDAO->readByID( 0 - $app_info->character->vss_id);
	}
}


if ( $app_info->id == "" && $mode != 'add' && $mode != 'doAdd' ) {
	echo("Invalid access attempt - $app_info->id $mode");
	header("Location: app_main.php");
	exit();
}

// In order to view an application the user must either own the application, or
// be a storyteller for the vss the character is on, the organization the vss
// of the character is under, or the organization the user is in.
if( $mode != 'add' && $mode != 'doAdd' ) {
	$securityInfo = $applicationDAO->readSecurityInfoByID( $application_id );
	if( $securityInfo['user_id'] != $_SESSION['user_id'] &&
        !in_array( $securityInfo['user_org_id'], $_SESSION['admin_org_list'] ) &&
        !in_array( $securityInfo['vss_id'], $_SESSION['admin_vss_list'] ) &&
        !in_array( $securityInfo['org_id'], $_SESSION['admin_org_list'] ) ) {
		echo("SECURITY VIOLATION: You are not an ST for this character.  Aborting.");
		exit();
	}
}

if ( isset($_POST['delete'] ) && isset($_POST["id"] ) ) {
	if ( isset($_POST['confirmdelete']) ) {
		$applicationDAO->deleteByID($_POST["id"]);
		header("Location: app_main.php?message=appdeleted&");
		exit();
    } else {
		$errmsg.="DeleteNotConfirmed";
	}
}

if( $mode == 'doAdd' || $mode == 'doEdit' ) {
	$thissubmitvalue = "Enter New Values";

	/*if ( trim( $app_info->description ) == "" ) {
		$errmsg .= "Item Description cannot be blank!\n";
	} else*/ if ( $mode == "doAdd" ) {
		$app_info->user_id =$_POST["user_id"];
		$app_info->character_id = $_POST["character_id"];
		$app_info->character = $characterDAO->readByID( $app_info->character_id );
		$app_info->venue_id = $_POST["application_venue_id"];
		$app_info->category = $_POST["category"];
		$app_info->setStatus($_POST["status"]);
		$app_info->required_approval = $_POST["required_approval"];
		$app_info->description = trim($_POST["description"]);
		$app_info->justification = trim($_POST["justification"]);
		$app_info->character_sheet = trim($_POST["character_sheet"]);
		$app_info->mechanics = trim($_POST["mechanics"]);
		$app_info->id = isset( $_POST['id'] ) ? $_POST['id'] : 0;
		$app_info->organization = $applicationService->determineApplicationOrganization( $app_info );
		$app_info->org_id = $app_info->organization->id;
		$applicationDAO->insert( $app_info );
		$applicationDAO->updateApplicationNumber( $app_info );
		$emailService->sendNewApplicationEmail( $app_info );
		$revisionDAO->insert( new Revision($app_info->id,$_SESSION['user_id'], "Application Entered Into System"));
        header("Location: AppDetails.php?id=$app_info->id&mode=display&message=inputcomplete&");
        exit();
	} else if ( $mode == "doEdit" ) {
        $changed = array();
        if ( $app_info->venue_id != $_POST['application_venue_id'] ) {
        	$app_info->venue_id = $_POST['application_venue_id'];
			$changed[] = "Venue Changed";
        }
        if ( $app_info->category != $_POST['category'] ) {
        	$app_info->category = $_POST['category'];
        	$changed[] = "Category Changed to {$app_info->category}";
        }
        if ( $app_info->status != $_POST['status'] ) {
			$app_info->setStatus($_POST['status']);
        	$changed[] = "Status Changed to {$app_info->status}";
			if ( strtolower($app_info->status) == "denied" ) {
				$finalcomment = true;
			}
        }
        if ( $app_info->description != $_POST['description']) {
			$app_info->description = $_POST['description'];
        	$changed[] = "Description Changed to {$app_info->description}";
        }
        if ( $app_info->character_sheet != $_POST['character_sheet']) {
        	$app_info->character_sheet = $_POST['character_sheet'];
			$changed[] = "Character Sheet Changed";
        }
        if ( $app_info->justification != $_POST["justification"]) {
        	$app_info->justification = $_POST['justification'];
        	$changed[] = "Justification Changed";
        }
        if ( $app_info->required_approval != $_POST['required_approval'] ) {
        	$changed[] = "Required approval changed to {$app_info->required_approval}";
		if ($app_info->status == 'Approved') {
			if ($app_info->required_approval == 'Low') {
				$app_info->setStatus('Pending Mid');
				$changed[] = "Status Changed to {$app_info->status}";
			}
			if ($app_info->required_approval == 'Mid') {
				if ($_POST['required_approval'] != 'Low') {
					$app_info->setStatus('Pending High');
 		       			$changed[] = "Status Changed to {$app_info->status}";
				}
			}
			if ($app_info->required_approval == 'High') {
				if ($_POST['required_approval'] != 'Low' &&
					$_POST['required_approval'] != 'Mid') {
					$app_info->setStatus('Pending Top');
					$changed[] = "Status Changed to {$app_info->status}";
				}
			}
			if ($app_info->required_approval == 'Top') {
				if ($_POST['required_approval'] != 'Low' &&
					$_POST['required_approval'] != 'Mid' &&
					$_POST['required_approval'] != 'High') {
					$app_info->setStatus('Pending Global');
				    $changed[] = "Status Changed to {$app_info->status}";
				}
			}
		}			}
        	$app_info->required_approval = $_POST['required_approval'];
        
		if ( $app_info->mechanics != $_POST['mechanics']) {
			$app_info->mechanics = $_POST['mechanics'];
			$changed[] = "Mechanics Changed";
		}
		if( count( $changed ) > 0 ) {
			$emailService->sendChangesEmail( $app_info, implode( ", ", $changed ) );
		}

		$app_info->update_date = time();
		$applicationDAO->update( $app_info );

		if ( count( $changed ) > 0 ) {
			$revisionDAO->insert( new Revision( $app_info->id, $_SESSION["user_id"], implode( ", ", $changed ) ) );
        }

        if ( $finalcomment ) {
			header("Location: CommentEnterComment.php?finalcomment=1&app_id=$app_info->id&parent_id=0&" );
			exit();
        } else {
			if ( isset( $errmsg ) && $errmsg=="DeleteNotConfirmed" ) {
				$ExtraPart="message=deletenotconfirmed&";
			} else {
				$ExtraPart="message=updatecomplete&";
			}
			header("Location: AppDetails.php?id=$app_info->id&mode=display&$ExtraPart");
			exit();
		}
	}
} else if( $mode == 'add' ) {
	$ThisSubmitValue="Enter New Application";

	$app_info->character_id = $_GET['char_id'];
	$app_info->character = $characterDAO->readByID( $app_info->character_id );

	if( isset( $_GET['apporg_id'] ) && $_GET['apporg_id'] > 0 ) {
		$app_info->org_id = $_GET['apporg_id'];
	} elseif ( is_object( $app_info->character ) && is_numeric($app_info->character->vss_id) && $app_info->character->vss_id > 0 ) {
		$app_info->character->vss = $vssDAO->readByID( $app_info->character->vss_id );
		$app_info->org_id = $app_info->character->vss->organization_id;
	} elseif ( is_object( $app_info->character ) && is_numeric($app_info->character->vss_id) && $app_info->character->vss_id < 0 ) {
		$app_info->org_id = 0-$app_info->character->vss_id;
	} elseif( is_object( $app_info->character ) && is_numeric( $app_info->character->org_id ) && $app_info->character->org_id > 0 ) {
		$app_info->org_id = $app_info->character->org_id;
	} else {
		// Use the Org ID of the logged in user
		$app_info->org_id = $_SESSION['org_id'];
	}
	$app_info->organization = $organizationDAO->readByID( $app_info->org_id );

	if( is_numeric( $app_info->character_id ) && $app_info->character_id != 0 ) {
		$character = $characterDAO->readByID( $app_info->character_id );
	}

	$instructionDAO = $daoFactory->getInstructionDAO();
	$instructions = $instructionDAO->readByOrganization( $app_info->organization );
	$ThisJustificationBlock="";
	foreach ( $instructions as $instruction ) {
		$ThisJustificationBlock.="Instructions from the ";
		if ( $instruction["chapter"]!="" ) {
			$ThisJustificationBlock.="Chapter:" . chr(13) . chr(13);
        } else if ( $instruction["domain"]!="" ) {
			$ThisJustificationBlock.="Domain:" . chr(13) . chr(13);
		} else if ( $instruction["region"]!="" ) {
			$ThisJustificationBlock.="Region:" . chr(13) . chr(13);
		} else if ( $instruction["nation"]!="" ) {
			$ThisJustificationBlock.="Nation:" . chr(13) . chr(13);
		}
        $ThisJustificationBlock.=$instruction["instruction"] . chr(13) . chr(13) . "-----------------" . chr(13) . chr(13);
	}

	if( is_object($character) && isset( $character->vss_id ) ) {
		$vss_instructions = $instructionDAO->instructionsFromVSSID( $character->vss_id );
		if( strlen($vss_instructions) ) {
			$ThisJustificationBlock.="Instructions from your VST\n\n";
			$ThisJustificationBlock.=$vss_instructions . chr(13) . chr(13) . "-----------------" . chr(13) . chr(13);

		}
	}
	if($app_info->character)
		$app_info->user_id = $app_info->character->user_id;
	else
		$app_info->user_id = $_SESSION['user_id'];
	$app_info->venue_id = $character->venue_id ;
	$app_info->required_approval = "High";
	$app_info->status = "Pending Low";
	$app_info->justification = $ThisJustificationBlock;
	$app_info->active = 1;
	$app_info->character_sheet = $character->character_sheet;
	$app_info->creation_date = time();
	$app_info->status_change_date = time();
	$app_info->update_date = time();
	$app_info->venue = $venueDAO->readByID( $app_info->venue_id );
	$character->venue = $venueDAO->readByID( $character->venue_id);
	$app_info->character = $character;

} else if( $mode == 'edit' ) {
	if ( isset( $_POST['approve'] ) ) {
		$ThisStatus="";
		if( $app_info->status == "Pending Low" &&
			( ( $_SESSION['admin_level'] == 'chapter' && !$_SESSION['assistant'] )  ||
				$_SESSION['admin_level'] == 'domain' ||
				$_SESSION['admin_level'] == 'region' ||
				$_SESSION['admin_level'] == 'nation' ||
				$_SESSION['admin_level'] == 'globe' ) ) {
          if( $app_info->required_approval == "Low" &&
              $app_info->user_id != $_SESSION['user_id'] ) {
            $ThisStatus = "Approved";
          } else {
            $ThisStatus = "Pending Mid";
          }

        } else if( $app_info->status == "Pending Mid" &&
            ( ( $_SESSION['admin_level'] == 'domain' &&
                !$_SESSION['assistant'] )  ||
              $_SESSION['admin_level'] == 'region' ||
              $_SESSION['admin_level'] == 'nation' ||
							$_SESSION['admin_level'] == 'globe' ) ) {
          if( $app_info->required_approval == "Mid" &&
              $app_info->user_id != $_SESSION['user_id'] ) {
            $ThisStatus = "Approved";
          } else {
            $ThisStatus = "Pending High";
          }

        } else if( $app_info->status == "Pending High" &&
                   ( ( $_SESSION['admin_level'] == 'region'
//&&!$_SESSION['assistant'] 
		)  ||
                     $_SESSION['admin_level'] == 'nation' ||
										 $_SESSION['admin_level'] == 'globe' ) ) {
          if( $app_info->required_approval == "High" &&
              $app_info->user_id != $_SESSION['user_id'] )  {
            $ThisStatus = "Approved";
          } else {
            $ThisStatus = "Pending Top";
          }

        } else if( $app_info->status == "Pending Top" &&
                   ( $_SESSION['admin_level'] == 'nation' ||
									   $_SESSION['admin_level'] == 'globe' ) /*&&
                   !$_SESSION['assistant']*/ ) {
			          if( $app_info->required_approval == "Top" &&
			              $app_info->user_id != $_SESSION['user_id'] )  {
			            $ThisStatus = "Approved";
			          } else {
			            $ThisStatus = "Pending Global";
					}
        } else if( $app_info->status == "Pending Global" &&
                   ( $_SESSION['admin_level'] == 'globe' ) /*&&
                   !$_SESSION['assistant'] */) {
          $ThisStatus = "Approved";
        }
        if( $ThisStatus != "" ) {
        	$applicationDAO->updateStatus( $app_info->id, $ThisStatus );
        	$revisionDAO->insert( new Revision( $app_info->id, $_SESSION["user_id"], "Status set to $ThisStatus" ) );
        	$emailService->sendChangesEmail( $app_info, $changed );
        }
        header ( "Location: AppDetails.php?id=$app_info->id&mode=display&message=updatecomplete&" );
        exit();
      } else if ( isset($_POST["reinstate"]) ) {
        $applicationDAO->updateStatus( $app_info->id, "Pending Low" );
        $revisionDAO->insert( new Revision( $app_info->id, $_SESSION["user_id"], "Status set to Pending Low" ) );
        $emailService->sendChangesEmail( $app_info, $changed );
        header ( "Location: AppDetails.php?id=$app_info->id&mode=display&message=updatecomplete&" );
        exit();
      } else if ( isset($_POST["remove"]) ) {
        $applicationDAO->updateStatus( $app_info->id, 'Removed' );
        $revisionDAO->insert( new Revision( $app_info->id, $_SESSION["user_id"], "Status Changed to Removed" ) );
		$emailService->sendRemovedEmail( $app_info );
        header ( "Location: AppDetails.php?id=$app_info->id&mode=display&message=updatecomplete&" );
        exit();
      } else if ( isset($_POST["deny"]) ) {
        $applicationDAO->updateStatus( $app_info->id, 'Denied' );
      	$revisionDAO->insert( new Revision( $app_info->id, $_SESSION["user_id"], "Status Changed to Denied" ) );
		$emailService->sendDeniedEmail( $app_info );
        header ( "Location: CommentEnterComment.php?finalcomment=1&app_id=$app_info->id&parent_id=0&" );
        exit();
      } else if( isset( $_POST["publish"] ) ) {
      	$mechanicDAO = $daoFactory->getMechanicDAO();
		$mechanic = new Mechanic(
			$app_info->venue_id,
			$app_info->category,
			$app_info->description,
			$app_info->mechanics
		);
		$mechanicDAO->insert($mechanic);
		header( "Location: MechanicsDetails.php?id={$mechanic->id}&mode=Edit");
		exit();
	}

	$app_info->organization = $organizationDAO->readByID( $app_info->org_id );
	$ThisSubmitValue="Enter New Values";
} else {
	$ThisSubmitValue="Edit Application";
}
$IGNORE_LOGIN = 0;

$pagetitle = "Application Details";
include_once("header.inc");
include_once("titlebar.php");
?>

<?php if ( isset($_GET['message']) && $_GET["message"]=="deletenotconfirmed" ) { ?>
	<div class="msgbox error">Delete Not Confirmed</div>
<?php } ?>

<form action="AppDetails.php" method="post">
<?php if ( $mode=="add" || $mode=="doAdd" ) { ?>
	<input type="hidden" name="mode" value="doAdd">
<?php } else { ?>
  <input type="hidden" name="id" value="<?php echo $app_info->id ?>">
<?php	if ( $mode=="edit" || $mode=="doEdit" ) { ?>
  <input type="hidden" name="mode" value="doEdit">
<?php } else { ?>
  <input type="hidden" name="mode" value="edit">
<?php
	}
}
include_once("AppDetailsTopSection.php");

if( isset($_GET["showdetails"]) || $app_info->id == 0 || $mode == 'edit' ) {
	$smarty->display("AppDetailsCSJustSection.html");
}

if ( $mode=="display" ) {
	$app_id=$app_info->id;
	$app_user_id=$app_info->user_id;
	include_once("CommentShowThreads.php");
	if ( ( $_SESSION['admin_level'] == "region" || $_SESSION['admin_level'] == "nation" ) && $app_info->user_id != $_SESSION['user_id'] ) {
		include_once("AppDetailsRatingsDisplay.php");
	}
}
?>
<table class="data">
  <tr>
  	<th><input type="submit" value="<?php echo $ThisSubmitValue?>"></th>
	</tr>
</table>
<?php
  if ( ( $mode == "edit" || $mode=="doEdit") &&
       ( $_SESSION['super_user'] ||
         ( $_SESSION['admin_level']=="region" &&
           in_array($app_info->org_id,$_SESSION['admin_org_list']) ) ||
         ( $_SESSION['admin_level']=="nation" &&
           in_array($app_info->org_id,$_SESSION['admin_org_list']) )
         )
       ) {
?>
<table class="data">
	<tr>
  	<th align="center" width="50%" valign="top">
    	<input type="submit" value="Permanently Delete Application" name="delete">
    </th>
		<th align="center" width="50%">
    	<input type="checkbox" name="confirmdelete"> Confirm Permanent Deletion of this application. <font color="red">This CANNOT be reversed, and should only be done for test applications.  Non-test applications should be left in the system for archival/historical reference purposes.</font>
		</th>
	</tr>
</table>
<?php
  }
?>
  </form>
<?php
if ( $mode == "display" ) {
	$smarty->assign("revisions",$revisionDAO->readByApplicationID( $app_info->id ));
	$smarty->display("AppDetailsRevisionHistorySection.html");
}

include_once "footerbar.inc";

ob_end_flush();

