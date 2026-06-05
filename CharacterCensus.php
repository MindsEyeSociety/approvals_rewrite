<?php
  include_once("db.inc");
	$pagetitle="Character Census";
  include_once("header.inc");
  include_once("titlebar.php");

	// Total counter of characters displayed
	$total_count = 0;
	$venueDAO = $daoFactory->getVenueDAO();
	$venues = $venueDAO->getVenueOptions();

  print( "<form action=\"$_SERVER[PHP_SELF]\" method=\"Post\">\n" );
  print( "<table class=\"data\">\n" );
  print( "<tr>\n" );
  print( "<th align=\"center\">Choose Venue</th>\n" );
  print( "</tr>\n" );
  print( "<tr>\n" );
  print( "<td align=\"center\">\n" );
  print( "<select name=\"venue_id\" onChange=\"submit();\">\n" );
  print( "<option value=\"99\">All Venues</option>\n" );
	foreach( $venues as $row ) {
		print( "<option value=\"$row[id]\"" );
		if ( isset($_POST["venue_id"]) && $row["id"]==$_POST["venue_id"] ) {
			print (" selected");
		}
		print( ">$row[venue]</option>");
	}
  print( "</select>&nbsp;<input type=\"submit\">\n" );

  print( "</td>\n" );
  print( "</tr>\n" );
  print( "</table>\n" );

  print( "<table class=\"data\">\n" );

	$wherepart = " AND c.active!=0 AND c.char_dead=0";
	if ( isset( $_POST["venue_id"] ) ) {
		if ( $_POST["venue_id"]!=99 ) {
			$wherepart .= sprintf(" AND c.venue_id=%d ",$_POST["venue_id"]);
		}
	}

//	$query="SELECT c.*, c.id as character_id, c.name as character_name, n.venue, ".
	$query="SELECT c.id, c.char_type, c.subtype, c.id as character_id, c.name as character_name, n.venue, ".
	       "  c.vss_id as vss_id, v.name as vss_name, o.id as org_id, ".
				 "  o.org_name as org_name, c.user_id as player_id, ".
				 "  v.storyteller_id as vst_id, o.admin_user_id as ost_id ".
				 "FROM characters c ".
				 "LEFT JOIN vsss v on c.vss_id=v.id ".
				 "LEFT JOIN organizations o on c.vss_id = -1*o.id ".
				 "LEFT JOIN venues n on c.venue_id=n.id ".
				 "WHERE ( v.storyteller_id=$_SESSION[user_id] ";
	if( sizeof( $_SESSION['admin_org_venue_list'] ) > 0 ) {
    foreach( $_SESSION['admin_org_venue_list'] as $venue_id => $org_list ) {
      if( $venue_id == 0 ) {
				$query .= "OR -1*c.vss_id in ('".implode("','",$_SESSION["admin_org_list"])."') ";
			} else {
			  $query .= "OR ( -1*c.vss_id in ('".implode("','",$_SESSION["admin_org_list"])."') AND c.venue_id = $venue_id ) ";
			}
		}
	}
	if( sizeof( $_SESSION['admin_vss_list'] ) > 0 ) {
	  $query .= "OR c.vss_id in ('".implode("','",$_SESSION['admin_vss_list'])."') ";
  }
	$query.= ") $wherepart ".
				 "ORDER BY n.venue, v.name, o.org_name, c.name";
	$res = $db->query($query);
	$last_venue="";
	$last_vss="";
	
	if ( $res->numRows()>0 ) {
	  print( "<tr>\n" );
    print( "<th colspan=\"4\">Characters affiliated with a VSS or Organization</th>" );
	  print( "</tr>\n" );
	}
	while ( $row=$res->nextRow() ) {
	  // Increment total count
		$total_count++;
	  if ( $last_venue!=$row["venue"] ) {
      print( "<th colspan=\"4\">$row[venue]</b></th>" );
  	  print( "</tr>\n" );
			$last_venue=$row["venue"];
		}
	  if ( $last_vss!=$row["vss_id"] ) {
  	  print( "<tr>\n" );
			if ( $row['vss_id'] > 0 ) {
        print( "<th colspan=\"4\">$row[vss_name], ST: ".userInfoPopup($row['vst_id'])."</th>" );
			} else {
        print( "<th colspan=\"4\">$row[org_name], ST: ".userInfoPopup($row['ost_id'])."</th>" );
			}
  	  print( "</tr>\n" );
  	  print( "<tr>\n" );
      print( "<th>Character Name</th>" );
      print( "<th>Sub-Type</th>" );
      print( "<th>Pri/Sec/NPC</th>" );
      print( "<th>Player</th>" );
  	  print( "</tr>\n" );
			$last_vss=$row["vss_id"];
		}
		if( $row['character_name'] == "" ) $row['character_name'] = "<i>unnamed</i>";
		if( $row['char_type'] == "npc" ) $row['char_type'] = "NPC";
	  print( "<tr>\n" );
    print( "<td><a href=\"DisplayCharacter.php?char_id=$row[character_id]&\">$row[character_name]</a></td>" );
    print( "<td>$row[subtype]</td>" );
    print( "<td>$row[char_type]</td>" );
    print( "<td>".userInfoPopup($row['player_id'])."</td>" );
	  print( "</tr>\n" );
	}

	$query="select c.id, c.char_type, c.subtype, c.id as character_id, c.name as character_name, n.venue, ".
	       "'' as vss_name, c.user_id as player_id ".
				 "from characters c left join ".
				 "venues n on c.venue_id=n.id left join ".
				 "users u on c.user_id=u.id ".
				 "WHERE 1=1 AND ( 1=0 ";
	if( sizeof( $_SESSION['admin_org_venue_list'] ) > 0 ) {
		foreach( $_SESSION['admin_org_venue_list'] as $venue_id => $org_list ) {
			if( $venue_id == 0 ) {
				$query .= "OR u.org_id in ('".implode("','",$_SESSION["admin_org_list"])."') ";
			} else {
				$query .= "OR ( u.org_id in ('".implode("','",$_SESSION["admin_org_list"])."') AND c.venue_id = $venue_id ) ";
			}
		}
	}

$query.= ") AND ( c.vss_id=0 OR c.vss_id IS NULL ) ". str_replace("v.", "c.", $wherepart)." ".
				 "ORDER BY n.venue, c.name";

	$res = $db->query($query);

	if ( $res->numRows()>0 ) {
	  print( "<tr>\n" );
    print( "<th colspan=\"4\">Characters not affiliated with a VSS</th>" );
	  print( "</tr>\n" );
	}
	$last_venue="";
	$last_vss="";
	while ( $row=$res->nextRow() ) {
	  // Increment total count
		$total_count++;

		if ( $last_venue!=$row["venue"] ) {
  	  print( "<tr>\n" );
      print( "<th colspan=\"4\">$row[venue]</th>" );
  	  print( "</tr>\n" );
  	  print( "<tr>\n" );
      print( "<td>Character Name</th>" );
      print( "<td>Sub-Type</th>" );
      print( "<td>Pri/Sec/NPC</th>" );
      print( "<td>Player</th>" );
  	  print( "</tr>\n" );
			$last_venue=$row["venue"];
		}
		if( $row['character_name'] == "" ) $row['character_name'] = "<i>unnamed</i>";
		if( $row['char_type'] == "npc" ) $row['char_type'] = "NPC";
	  print( "<tr>\n" );
    print( "<td><a href=\"DisplayCharacter.php?char_id=$row[character_id]&\">$row[character_name]</a></td>" );
    print( "<td>$row[subtype]</td>" );
    print( "<td>$row[char_type]</td>" );
    print( "<td>".userInfoPopup($row['player_id'])."</td>" );
	  print( "</tr>\n" );
	}

  // Display some message if there weren't any records available.
	if( $total_count == 0 ) {
	  print( "<tr>\n" );
    print( "<th colspan=\"4\">No Characters available</th>" );
	  print( "</tr>\n" );

	}

	print( "</table>\n" );
  print( "</form>\n" );


include_once("footerbar.inc");
?>
