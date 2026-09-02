<?php
  include_once("db.inc");
	$pagetitle="VSS Details";
  include_once("header.inc");

	$query="SELECT v.*, v.id as id, o.nation, o.region, o.domain, o.chapter, o.org_name, n.venue, u.id as storyteller_id ".
				 " from vsss v".
				 " left join organizations o on v.org_id=o.id".
				 " left join venues n on v.venue_id=n.id".
				 " left join users u on v.storyteller_id=u.id".
		  	 " WHERE v.id='$_GET[id]' ";
  $res = $db->query($query);

 ?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <link href="anathema.css" type='text/css' rel='stylesheet'>
  <title><?php echo $pagetitle?></title>
	<script type="text/javascript">
	<!--

	var Xoffset=-60;
	var Yoffset= 20;
	var LeftBound, CheckWidth, RightBound, nav, old, iex, yyy

	//-->
	</script>
	<script type="text/javascript" src="https://code.jquery.com/jquery-1.7.2.min.js"></script>
	<script type="text/javascript" src="javascript/jquery.tooltip.js"></script>
</head>
<body>

<div style="text-align:center">
	<a href="javascript:close();">Close</a> this window to return to the Approvals System
</div>

<table class="data">
	<tr>
		<th>Domain/Chapter</th>
		<th>Venue</th>
		<th>VSS Name</th>
		<th>Storyteller</th>
	</tr>
<?php
  $last_org_id=0;
  $row = $res->nextRow();
  $user_info = $userInfoDAO->getUserInfo($row['storyteller_id']);
  $row['storyteller_name'] = $user_info['name'];
  $modthisorg=in_array($row["org_id"],$_SESSION["admin_org_list"]);
	if ( $row["org_id"]!=$last_org_id ) {
		 $last_org_id=$row["org_id"];
		 $showorg=1;
	} else {
		$showorg=0;
	}
  if ( $showorg=="1" ) {
		if ( $row["chapter"]!="" ) {
		    echo("<tr><td>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&#149;$row[chapter]&nbsp;($row[org_name])</td>\n");
		} else if ( $row["domain"]!="" ) {
		    echo("<tr><td>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&#149;$row[domain]&nbsp;($row[org_name])</td>\n");
		} else if ( $row["region"]!="" ) {
		    echo("<tr><td>&nbsp;&nbsp;&nbsp;&nbsp;&#149;$row[region]&nbsp;($row[org_name])</td>\n");
		} else if ( $row["nation"]!="" ) {
		    echo("<tr><td>&nbsp;$row[nation]&nbsp;($row[org_name])</td>\n");
		} else {
		    echo("<tr><td>&nbsp;ORPHAN</td>\n");
		}
  } else {
  	echo("<tr><td>&nbsp;</td>\n");
  }
	echo("<td>&nbsp;$row[venue]</td>\n");
	echo("<td>&nbsp;$row[name]</td>\n");
	echo("<td>&nbsp;$row[storyteller_name]</td>\n");
	echo("</tr></table>\n");

	if( $_SESSION['admin_level'] == 'domain' ||
			$_SESSION['admin_level'] == 'region' ||
			$_SESSION['admin_level'] == 'nation' ||
			$_SESSION['admin_level'] == 'globe' ||
			$_SESSION['super_user'] == 1 ||
			$_SESSION['user_id'] == $row['storyteller_id'] ) {
    print( "<table class=\"data\"><tr>\n" );
		$res = $db->query("SELECT c.*, c.name as character_name, u.id as player_id, u.name as player_name, u.email ".
		           "FROM characters c LEFT JOIN users u ON c.user_id=u.id ".
		           "WHERE vss_id=$row[id] AND c.active!='0' AND c.char_dead='0' ".
							 "ORDER BY char_type, approved_in_vss, c.name" );
    print( "<td class=\"head\" align=\"center\" colspan=\"5\">");
		if ( $res->numRows() > 1 ) {
			print("Venue contains ". $res->numRows(). " characters\n");
		} else if ( $res->numRows() > 0 ) {
			print("Venue contains ". $res->numRows(). " character\n");
    } else {
			print("No characters are affiliated with this VSS\n");
		}
		if ($row["storyteller_id"]==$_SESSION['user_id']) {
			 echo("<div class=\"normalsmall\"><a href=\"ModifyVSSCharacterList1.php\">Accept/Reject Characters</a></div>");
		}
		print("</td>\n" );
    print( "</tr>\n" );
    if ( $res->numRows() > 0 ) {
      print("<tr><th>Character Name</th>\n");
			print("<th>Subtype</th>\n");
			print("<th>Pri/Sec/NPC</th>\n");
			print("<th>Player</th>\n");
			print("<th>In VSS?</th>\n");
			print("</tr>\n");
			$count = 0;
  	  while( $charrow = $res->nextRow() ) {
				$count++;
				if( $count % 2 == 0 ) {
  		  print("<tr class=\"dark\">\n");
				} else {
  		  print("<tr>\n");
				}
  		  print("<td><a href=\"DisplayCharacter.php?char_id=$charrow[id]&\">$charrow[character_name]</a></td>");
  			print("<td>$charrow[subtype]</td>\n");
				if( $charrow["char_type"] == 'npc' ) { $charrow['char_type'] = 'NPC'; }
  			print("<td>$charrow[char_type]</td>\n");
  			print("<td>".userInfoPopup($charrow["player_id"])."</td>\n");
				print("<td align=\"center\">");
      	if( $charrow['approved_in_vss'] == 0 ) {
      	  echo(" <span class=\"normalsmall\" style=\"color:#D00000;\">Pending</span> ");
      	} else {
      	  echo(" <span class=\"normalsmall\" style=\"color:#008000;\">Accepted</span> ");
				}
				print("</td>\n");
  		  print("</tr>\n");
  		}
		}


		print( "</table>\n" );
	}
?>
<table class="data">
	<tr>
		<th align="center">VSS</th>
	</tr>
	<tr>
		<td align="left">
		<?php
				$ThisVSS=$row["vss"];
				$ThisVSS=formatBlockText($ThisVSS );
				echo($ThisVSS);
		?>
	  </td>
	</tr>
</table>
<div style="text-align:center">
	<a href="javascript:close();">Close</a> this window to return to the Approvals System
</div>
</body>
</html>
