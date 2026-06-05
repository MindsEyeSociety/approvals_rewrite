<?php
  include_once("db.inc");
	$pagetitle="VSS Character List";
  include_once("header.inc");
  include_once("titlebar.php");
	
	if ( count($_SESSION["admin_org_list"])+count($_SESSION["admin_vss_list"]) == 0 ) {
		 echo("<div class=\"subhead\" align=\"center\">You are not an ST</div>");
	} else {
	
$query="select c.char_type as character_type, c.id as character_id, c.name as character_name, ".
			"v.venue, v.active as venue_active, s.name as vss_name, s.id as vss_id, u.id as user_id ".
			"from characters c left join ".
			"venues v on c.venue_id = v.id left join ".
			"vsss s on s.id=c.vss_id left join ".
			"users u on c.user_id=u.id ".
			"where s.storyteller_id='$_SESSION[user_id]' ".
			"and c.approved_in_vss='0' ".
			"and c.active='1' ".
			"and v.active='1' ".
			"order by s.id, c.char_type, c.name";
  $rs = $db->query($query);
  $rows=array();
  while ( $row=$rs->nextRow() ) {
  	$user_info = $userInfoDAO->getUserInfo($row['user_id']);
  	$row['user_name'] = $user_info['name'];
  	$rows[]=$row;
  }
	 
$query="select c.char_type as character_type, c.id as character_id, c.name as character_name, ".
			"v.venue, v.active as venue_active, o.org_name as vss_name, c.vss_id, u.id as user_id ".
			"from storytellers s left join ".
			"characters c on s.organization_id=-c.vss_id left join ".
			"organizations o on s.organization_id=o.id left join ".
			"venues v on c.venue_id=v.id left join ".
			"users u on u.id=c.user_id ".
			"where s.user_id='$_SESSION[user_id]' ".
			"and c.approved_in_vss='1' ".
			"and c.active='1' ".
			"and v.active='1' ".
			"order by o.id, c.char_type, c.name";	
  $rs = $db->query($query);
  while ( $row=$rs->nextRow() ) {
  	$user_info = $userInfoDAO->getUserInfo($row['user_id']);
  	$row['user_name'] = $user_info['name'];
  	$rows[]=$row;
  }
?>

<table class="data">
	<tr>
		<th align="center" colspan="8">
			Characters Pending Approval to Join Your VSS
		</th>
	</tr>
	<?php
		if ( $db->numRows() > 0 ) {
	?>
	<tr>
		<th align="center">VSS Name</th>
		<th align="center">Venue</th>
		<th align="center">Player Name</th>
		<th align="center">Character Name</th>
		<th align="center">Character Type</th>
		<th align="center">Applications</th>
		<th align="center">Accept</th>
		<th align="center">Reject</th>
	</tr>
	<?php 
	  }
	
  $last_vss_id=0;
	$counter = 0;
  foreach ( $rows as $row ) {
		if( ++$counter % 2 == 0 ) {
			echo("<tr class=\"dark\">");
		} else {
			echo("<tr>");
		}
 		echo("<td>&nbsp;");
		if ( $row["vss_id"]!=$last_vss_id ) {
			if ( $row["vss_id"]>0 ) {
				echo("<a href=\"VSSDetails.php?id=$row[vss_id]&\">$row[vss_name]</a>");
			} else {
				echo("$row[vss_name]");
			}
			$thisVenue=$row["venue"];
		} else {
		 $thisVenue="";
		}
		echo("</td>\n");
      	echo("<td>&nbsp;$thisVenue</td>\n");
		echo("<td>&nbsp;$row[user_name]</td>\n");
  		echo("<td align=\"center\">&nbsp;<a href=\"DisplayCharacter.php?char_id=$row[character_id]&\">$row[character_name]</a></td>\n");
	  	echo("<td align=\"center\">&nbsp;$row[character_type]</td>\n");
			$pending_count = 0; $total_count = 0;
		  $db->query("select count(*) as appcount, status from applications where character_id='$row[character_id]' group by status");
			while( $app_count=$db->nextRow() ) {
			  if( $app_count['status'] != 'Removed' && $app_count['status'] != 'Denied' && $app_count['status'] != 'Approved' ) {
				  $pending_count+=$app_count["appcount"];
				}
				$total_count+=$app_count["appcount"];
			}
			if( $total_count == 0 ) {
  	  	echo("<td align=\"center\">&nbsp;<i>(None)</i></td>\n");
			} else {
  	  	echo("<td align=\"center\">&nbsp;<a href=\"DisplayCharacter.php?char_id=$row[character_id]&#applications\">");
				if ($pending_count == 0 ) {
					 echo("<font color=\"#A0A0A0\">");
				} else {
					 echo("<font color=\"#D00000\">");
				}
				echo("$pending_count/$total_count Pending</font></a></td>\n");
			}
	  	echo("<td align=\"center\">&nbsp;<a href=\"ChangeCharacterVSSStatus.php?character_id=$row[character_id]&Action=Accept&\"><font color=\"#008000\">Accept</font> into VSS</a></td>\n");
	  	echo("<td align=\"center\">&nbsp;<a href=\"ChangeCharacterVSSStatus.php?character_id=$row[character_id]&Action=Reject&\"><font color=\"#D00000\">Reject</font> from VSS</a></td>\n");
	  	echo("</tr>\n");
	 	$last_vss_id=$row["vss_id"];
	}
	if ( count( $rows ) == 0 ) {
	?>
  		<tr>
			<td colspan="8">
				&nbsp;<i>(none)</i>
			</td>
		</tr>
	<?php
	}
?>
</table>

<?php
	$query="select c.char_type as character_type, c.id as character_id, c.name as character_name, ".
			"v.venue, v.active as venue_active, s.name as vss_name, s.id as vss_id, COALESCE(u.id,s.storyteller_id) as user_id ".
			"from characters c left join ".
			"venues v on c.venue_id = v.id left join ".
			"vsss s on s.id=c.vss_id left join ".
			"users u on c.user_id=u.id ".
			"where s.storyteller_id='$_SESSION[user_id]' ".
			"and c.approved_in_vss='1' ".
			"and v.active='1' ".
			"order by s.id, c.char_type, c.name";
  $rs = $db->query($query);
  $rows=array();
  while ( $row=$rs->nextRow() ) {
  	$user_info = $userInfoDAO->getUserInfo($row['user_id']);
  	$row['user_name'] = $user_info['name'];
  	$rows[]=$row;
	}

	$query="select c.char_type as character_type, c.id as character_id, c.name as character_name, ".
			"v.venue, v.active as venue_active, o.org_name as vss_name, c.vss_id, COALESCE(u.id,s.user_id) as user_id ".
			"from storytellers s left join ".
			"characters c on s.organization_id=-c.vss_id left join ".
			"organizations o on s.organization_id=o.id left join ".
			"venues v on c.venue_id=v.id left join ".
			"users u on u.id=c.user_id ".
			"where s.user_id='$_SESSION[user_id]' ".
			"and c.approved_in_vss='0' ".
			"and v.active='1' ".
			"order by o.id, c.char_type, c.name";	
  $rs = $db->query($query);
  while ( $row=$rs->nextRow() ) {
  	if (!empty($row['user_id']))
  	{
	  	$user_info = $userInfoDAO->getUserInfo($row['user_id']);
  		$row['user_name'] = $user_info['name'];
  	}
  	$rows[]=$row;
  }
?>
<table class="data">
	<tr>
		<th align="center" colspan="6">
			Characters in your VSS
		</th>
	</tr>
	<tr>
		<th align="center">VSS Name</th>
		<th align="center">Venue</th>
		<th align="center">Player Name</th>
		<th align="center">Character Name</th>
		<th align="center">Character Type</th>
		<th align="center">Pending Apps</th>
	</tr>

<?php
  $last_vss_id=0;

	$counter = 0;	
  foreach( $rows as $row ) {
  	$counter++;
    if( 0 == $counter%2) {
    	echo("<tr class=\"dark\">");
		} else {
				echo("<tr>");
    }
		echo("<td>&nbsp;");
		if ( $row["vss_id"]!=$last_vss_id ) {
			 if ( $row["vss_id"]>0 ) {
			 		 echo("<a href=\"VSSDetails.php?id=$row[vss_id]&\">$row[vss_name]</a>");
			 } else {
			 		 echo("$row[vss_name]");
			 }
		 $thisVenue=$row["venue"];
		} else {
		 $thisVenue="";
		}
		echo("</td>\n");
    echo("<td>&nbsp;$thisVenue</td>\n");
    echo("<td>&nbsp;$row[user_name]</td>\n");
    echo("<td align=\"center\">&nbsp;<a href=\"DisplayCharacter.php?char_id=$row[character_id]&\">$row[character_name]</a></td>\n");
    echo("<td align=\"center\">&nbsp;$row[character_type]</td>\n");
  	$pending_count = 0; $total_count = 0;
    $db->query("select count(*) as appcount, status from applications where character_id='$row[character_id]' group by status");
  	while( $app_count=$db->nextRow() ) {
  	  if( $app_count['status'] != 'Removed' && $app_count['status'] != 'Denied' && $app_count['status'] != 'Approved' ) {
  		  $pending_count+=$app_count["appcount"];
  		}
  		$total_count+=$app_count["appcount"];
  	}
  	if( $total_count == 0 ) {
 	  	echo("<td align=\"center\">&nbsp;<i>(None)</i>\n");
  	} else {
 	  	echo("<td align=\"center\">&nbsp;<a href=\"DisplayCharacter.php?char_id=$row[character_id]&#applications\">");
  		if ($pending_count == 0 ) {
  			 echo("<font color=\"#A0A0A0\">");
  		} else {
  			 echo("<font color=\"#D00000\">");
  		}
  		echo("$pending_count/$total_count Pending</font></a></td>\n");
  	}
   	echo("</tr>\n");
	 	$last_vss_id=$row["vss_id"];
	}

	if ( count($rows) == 0 ) {
	?>
 		<tr>
			<td colspan="8">
				&nbsp;<i>(none)</i>
			</td>
		</tr>
	<?php
	}
	?>
</table>

<?php
	// --- Characters in Inactive Venues ---
	$inactive_rows = array();

	$query="select c.char_type as character_type, c.id as character_id, c.name as character_name, ".
			"v.venue, v.active as venue_active, s.name as vss_name, s.id as vss_id, COALESCE(u.id,s.storyteller_id) as user_id ".
			"from characters c left join ".
			"venues v on c.venue_id = v.id left join ".
			"vsss s on s.id=c.vss_id left join ".
			"users u on c.user_id=u.id ".
			"where s.storyteller_id='$_SESSION[user_id]' ".
			"and c.approved_in_vss='1' ".
			"and v.active='0' ".
			"order by s.id, c.char_type, c.name";
  $rs = $db->query($query);
  while ( $row=$rs->nextRow() ) {
  	$user_info = $userInfoDAO->getUserInfo($row['user_id']);
  	$row['user_name'] = $user_info['name'];
  	$inactive_rows[]=$row;
	}

	$query="select c.char_type as character_type, c.id as character_id, c.name as character_name, ".
			"v.venue, v.active as venue_active, o.org_name as vss_name, c.vss_id, COALESCE(u.id,s.user_id) as user_id ".
			"from storytellers s left join ".
			"characters c on s.organization_id=-c.vss_id left join ".
			"organizations o on s.organization_id=o.id left join ".
			"venues v on c.venue_id=v.id left join ".
			"users u on u.id=c.user_id ".
			"where s.user_id='$_SESSION[user_id]' ".
			"and c.approved_in_vss='0' ".
			"and v.active='0' ".
			"order by o.id, c.char_type, c.name";	
  $rs = $db->query($query);
  while ( $row=$rs->nextRow() ) {
  	if (!empty($row['user_id']))
  	{
	  	$user_info = $userInfoDAO->getUserInfo($row['user_id']);
  		$row['user_name'] = $user_info['name'];
  	}
  	$inactive_rows[]=$row;
  }

	if ( count($inactive_rows) > 0 ) {
?>
<p align="center">
	<a href="#" onclick="toggleInactiveVenues(); return false;" id="inactiveToggleLink">Show characters from inactive venues</a>
</p>

<div id="inactiveVenueCharacters" style="display:none;">
<table class="data">
	<tr>
		<th align="center" colspan="6">
			Characters in Inactive Venues
		</th>
	</tr>
	<tr>
		<th align="center">VSS Name</th>
		<th align="center">Venue</th>
		<th align="center">Player Name</th>
		<th align="center">Character Name</th>
		<th align="center">Character Type</th>
		<th align="center">Pending Apps</th>
	</tr>

<?php
  $last_vss_id=0;
	$counter = 0;	
  foreach( $inactive_rows as $row ) {
  	$counter++;
    if( 0 == $counter%2) {
    	echo("<tr class=\"dark\">");
		} else {
				echo("<tr>");
    }
		echo("<td>&nbsp;");
		if ( $row["vss_id"]!=$last_vss_id ) {
			 if ( $row["vss_id"]>0 ) {
			 		 echo("<a href=\"VSSDetails.php?id=$row[vss_id]&\">$row[vss_name]</a>");
			 } else {
			 		 echo("$row[vss_name]");
			 }
		 $thisVenue=$row["venue"];
		} else {
		 $thisVenue="";
		}
		echo("</td>\n");
		if ( $row["venue_active"] == '0' ) {
    		echo("<td>&nbsp;<i>$thisVenue</i></td>\n");
		} else {
    		echo("<td>&nbsp;$thisVenue</td>\n");
		}
    echo("<td>&nbsp;$row[user_name]</td>\n");
    echo("<td align=\"center\">&nbsp;<a href=\"DisplayCharacter.php?char_id=$row[character_id]&\">$row[character_name]</a></td>\n");
    echo("<td align=\"center\">&nbsp;$row[character_type]</td>\n");
  	$pending_count = 0; $total_count = 0;
    $db->query("select count(*) as appcount, status from applications where character_id='$row[character_id]' group by status");
  	while( $app_count=$db->nextRow() ) {
  	  if( $app_count['status'] != 'Removed' && $app_count['status'] != 'Denied' && $app_count['status'] != 'Approved' ) {
  		  $pending_count+=$app_count["appcount"];
  		}
  		$total_count+=$app_count["appcount"];
  	}
  	if( $total_count == 0 ) {
 	  	echo("<td align=\"center\">&nbsp;<i>(None)</i>\n");
  	} else {
 	  	echo("<td align=\"center\">&nbsp;<a href=\"DisplayCharacter.php?char_id=$row[character_id]&#applications\">");
  		if ($pending_count == 0 ) {
  			 echo("<font color=\"#A0A0A0\">");
  		} else {
  			 echo("<font color=\"#D00000\">");
  		}
  		echo("$pending_count/$total_count Pending</font></a></td>\n");
  	}
   	echo("</tr>\n");
	 	$last_vss_id=$row["vss_id"];
	}
?>
</table>
</div>

<script type="text/javascript">
function toggleInactiveVenues() {
	var div = document.getElementById('inactiveVenueCharacters');
	var link = document.getElementById('inactiveToggleLink');
	if (div.style.display === 'none') {
		div.style.display = '';
		link.innerHTML = 'Hide characters from inactive venues';
	} else {
		div.style.display = 'none';
		link.innerHTML = 'Show characters from inactive venues';
	}
}
</script>

<?php
	}
} // end ST check

include_once("footerbar.inc") 
?>
