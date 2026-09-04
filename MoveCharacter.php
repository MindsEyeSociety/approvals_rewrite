<?php
	include_once("db.inc");
	$pagetitle="Venue Listing";
	include_once("header.inc");
	include_once("titlebar.php");

	$char_id = isset( $_POST['char_id'] ) ? $_POST['char_id'] : $_GET['char_id'];
	$return = isset($_GET['return'])?$_GET['return']:"";

	$characterDAO = $daoFactory->getCharacterDAO();
	$vssDAO = $daoFactory->getVSSDAO();
	$organizationDAO = $daoFactory->getOrganizationDAO();

	$character = $characterDAO->readByID( $char_id );
	$userOrganization = $organizationDAO->readById( $_SESSION['org_id'] );

	$localvsss = $vssDAO->readLocalVenueVSSs(
		$character->venue_id,
		$userOrganization->globe ?? null,
		$userOrganization->nation ?? null,
		$userOrganization->region ?? null,
		$userOrganization->domain ?? null,
		$userOrganization->chapter ?? null
	);
	for( $i=0; $i < count($localvsss); $i++ ) {
		if (!empty($localvsss[$i]['storyteller_id'])) {
		  	$user_info = $userInfoDAO->getUserInfo($localvsss[$i]['storyteller_id']);
		  	$localvsss[$i]['storyteller_name'] = $user_info['name'];
		}
	}

	$localorgs = $organizationDAO->readLocalOrganizations(
		$userOrganization->globe ?? null,
		$userOrganization->nation ?? null,
		$userOrganization->region ?? null,
		$userOrganization->domain ?? null,
		$userOrganization->chapter ?? null
	);
	for( $i = 0; $i < count( $localorgs); $i++ ) {
		if (!empty($localorgs[$i]['storyteller_id'])) {
		  	$user_info = $userInfoDAO->getUserInfo($localorgs[$i]['storyteller_id']);
		  	$localorgs[$i]['storyteller_name'] = $user_info['name'];
		}
	}

	$totalvsss = $vssDAO->readVSSsByVenueID( $character->venue_id);
	for( $i = 0; $i < count( $totalvsss ); $i++ ) {
		if (!empty($totalvsss[$i]['storyteller_id'])) {
		  	$user_info = $userInfoDAO->getUserInfo($totalvsss[$i]['storyteller_id']);
		  	$totalvsss[$i]['storyteller_name'] = $user_info['name'];
		}
	}

	if ( isset( $_GET["message"]) && $_GET["message"]=="InvalidOrg" ) {
	    print( "<div align=\"center\" class=\"error\">\n" );
	    print( "Invalid Organization Selected - You must select a Chapter or Domain\n" );
	    print( "</div>\n" );
	}
	if ( isset( $_GET["message"]) && $_GET["message"]=="require_vss" ) {
	    print( "<div align=\"center\" class=\"error\">\n" );
	    print( "You must select a Venue Style Sheet for your character\n" );
	    print( "</div>\n" );
	}
 ?>

<div class="normalbold">
	Select a VSS for <?php echo $character->name ?> (Selected Local VSS takes precedence)
</div>
<form action="MoveCharacter2.php" method="post">
<input type="hidden" name="char_id" value="<?php echo $char_id?>">
<input type="hidden" name="redirect" value="<?php echo $_GET['redirect'] ?? ''?>">
<input type="hidden" name="user_id" value="<?php echo $character->user_id?>">
<table class="data">
	<tr>
		<th align="center">Local VSSs</th>
		<th align="center" width="10"><i> - or - </i></th>
		<th align="center">All VSSs</th>
	</tr>
	<tr>
		<td align="center">
			<select name="localvss_id" size="20">
<?php
print( "<option value=\"\">-- Not Selected --</option>" );
foreach( $localorgs as $localrow ) {
  $thisid= -$localrow["id"];
  $thisextratext="";
  if ( $thisid==$character->vss_id and $thisid!="" ) {
    $currenttext=" (current)";
		$character->vss_id = "";
  } else {
  	$currenttext="";
  }
  print( "<option value=\"$thisid\">" );
  if( $localrow["org_name"] != "" ) {
    print( "{$localrow["org_name"]} " );
  } else {
    if ( $localrow["chapter"]!="" ) {
      print( "$localrow[chapter] " );
    } else if ( $localrow["domain"]!="" ) {
      print( "$localrow[domain] " );
    } else if ( $localrow["region"]!="" ) {
      print( "$localrow[region] " );
    } else if ( $localrow["nation"]!="" ) {
      print( "$localrow[nation] " );
    }
  }

  if ( isset($localrow["storyteller_name"]) && $localrow["storyteller_name"]!="" ) {
    print( " ({$localrow['storyteller_name']})\n" );
  }
  print( "$currenttext</option>" );

}

foreach ( $localvsss as $localrow ) {
  $thisid=$localrow["id"];
  $thisextratext="";
  if ( $thisid==$character->vss_id and $thisid!="" ) {
    $currenttext=" (current)";
		$character->vss_id = "";
  } else {
  	$currenttext="";
  }
  print( "<option value=\"$thisid\">" );
  if( $localrow["org_name"] != "" ) {
    print( "$localrow[org_name] " );
  } else {
    if ( $localrow["chapter"]!="" ) {
      print( "$localrow[chapter] " );
    } else if ( $localrow["domain"]!="" ) {
      print( "$localrow[domain] " );
    } else if ( $localrow["region"]!="" ) {
      print( "$localrow[region] " );
    } else if ( $localrow["nation"]!="" ) {
      print( "$localrow[nation] " );
    }
  }

  if ( $localrow["vssname"]!="" ) {
    print( " - $localrow[vssname]" );
  } else {
  	print( " - [UNNAMED]" );
  }
  if ( isset($localrow["storyteller_name"]) && $localrow["storyteller_name"]!="" ) {
    print( " 	 ($localrow[storyteller_name])\n" );
  }
  print( "$currenttext</option>" );

}
?>
		 	</select>
		</td>
		<td align="center" width="10"><i> - or - </i></td>
		<td align="center">
			<select name="totalvss_id" size="20">
<?php
print( "<option value=\"\">-- Not Selected --</option>" );
foreach ( $totalvsss as $totalrow ) {
  $thisid=$totalrow["id"];
  $thisextratext="";
  if ( $thisid==$character->vss_id and $thisid!="" ) {
    $currenttext=" (current)";
  } else {
  	$currenttext="";
  }
  print( "<option value=\"$thisid\">" );
  if( $totalrow["org_name"] != "" ) {
    print( "$totalrow[org_name] " );
  } else {
    if ( $totalrow["chapter"]!="" ) {
      print( "$totalrow[chapter] " );
    } else if ( $totalrow["domain"]!="" ) {
      print( "$totalrow[domain] " );
    } else if ( $totalrow["region"]!="" ) {
      print( "$totalrow[region] " );
    } else if ( $totalrow["nation"]!="" ) {
      print( "$totalrow[nation] " );
    }
  }
  if ( $totalrow["vssname"]!="" ) {
    print( " - $totalrow[vssname]" );
  } else {
	  print( " - [UNNAMED]" );
	}
  if ( isset($totalrow["storyteller_name"]) && $totalrow["storyteller_name"]!="" ) {
    print( " 	 ($totalrow[storyteller_name])\n" );
	}
  print( "$currenttext</option>\n" );
}
?>
		 	</select>
		</td>
	</tr>
	<tr>
		<th colspan="3" align="center"><input type="submit" value="Change VSS"></th>
	</tr>
</table>
</form>
<?php include_once("footerbar.inc") ?>
