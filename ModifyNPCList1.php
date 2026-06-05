<?php
  include_once("db.inc");
	$pagetitle="Character List";
  include_once("header.inc");
  include_once("titlebar.php");

	$vss_list = implode(",",$_SESSION["admin_vss_list"]);
	if( strlen( $vss_list ) == 0 ) { $vss_list = "0"; }
	$org_list = implode(",",$_SESSION["admin_org_list"]);
	if( strlen( $org_list ) == 0 ) { $org_list = "0"; }
  $db->query("SELECT c.id, c.name, c.subtype, c.char_type, c.active, ".
             "c.char_dead, c.vss_id, v.venue, o.org_name AS org_name, ".
             "ou.id as ost_id, CONCAT(pu.firstName, ' ', pu.lastName) AS ost_name, s.name AS vss_name, ".
						 "u.id AS vst_id, u.name AS vst_name, ".
						 "o.nation, o.region, o.domain, o.chapter, o.globe ".
             "FROM characters c ".
	 				   "LEFT OUTER JOIN vsss s ON c.vss_id=s.id ".
						 "LEFT OUTER JOIN organizations o ON (c.vss_id = -1*o.id OR c.org_id = o.id) ".
						 "LEFT OUTER JOIN organizations o2 ON s.org_id = o2.id ".
	           "LEFT OUTER JOIN venues v ON c.venue_id = v.id ".
						 "LEFT JOIN users u ON s.storyteller_id = u.id ".
						 "LEFT JOIN users ou ON o.admin_user_id = ou.id ".
						 "LEFT JOIN `mes-portal`.User pu ON ou.ww_number = pu.membershipNumber ".
						 "WHERE c.char_type='NPC' AND ( c.vss_id in ( $vss_list ) OR c.org_id in ( $org_list ) ) ".
						 "AND (v.id IS NULL OR v.active = 1) AND (o.id IS NULL OR o.active = 1) ".
             "ORDER BY o2.nation, o.nation, o2.region, o.region, o2.domain, o.domain, o2.chapter, o.chapter, c.vss_id, char_dead, active desc, name " );

	if ( isset($_GET["message"]) && $_GET["message"]=="vss_assigned" ) {
    print( "<div align=\"center\" class=\"message\">\n" );
    print( "Character assigned to VSS\n" );
    print( "</div>\n" );
	}
 ?>

<table class="data">
	<tr>
		<th align="center">Name</th>
		<th align="center">Venue</th>
		<th align="center">Type</th>
		<th align="center">Active</th>
		<th align="center">Dead/Retired?</th>
		<th align="center">Modify</th>
	</tr>
<?php
  $rows = array();
  while( $row = $db->nextRow() ) { $rows[] = $row; }
  $current_org = "XXXXXXXXX";
	$current_vss = "XXXXXXXXX";
	$counter = 0;
	foreach( $rows as $row ) {
	  if( $current_org != $row["org_name"] ) {
		  $current_org = $row["org_name"];
			$current_vss = "";
  		if( $current_org != "" ) {
			  if( $row["ost_name"] ) { $stname = $row["ost_name"]; } else { $stname="<i>No Storyteller</i>"; }
				if( $row["chapter"] ) {
				  $org_level='Chapter';
				} elseif ( $row['domain'] ) {
				  $org_level='Domain';
				} elseif ( $row['region'] ) {
				  $org_level='Region';
				} elseif ( $row['nation'] ) {
				  $org_level='Nation';
				} elseif ( $row['globe'] ) {
				  $org_level='';
				} else {
				  $org_level="Something weird is up";
				}
  		  echo "<tr><th class=\"subhead\" colspan=\"6\" align=\"left\">$current_org&nbsp;$org_level&nbsp;(Storyteller: $stname)</th></tr>\n";
			}
		}
	  if( $current_vss != $row["vss_name"] ) {
		  $current_vss = $row["vss_name"];
		  if( $row["vst_name"] ) { $stname = $row["vst_name"]; } else { $stname="<i>No Storyteller</i>"; }
		  echo "<tr><th class=\"subhead\" colspan=\"6\" align=\"left\"><a href=\"VSSDetails.php?id={$row["vss_id"]}&\" target=\"_new\">$current_vss</a>&nbsp;VSS&nbsp;(VST: $stname)</th></tr>\n";
		}
    $activeValue = ( $row["active"] ? "YES" : "NO" );
    $char_deadValue = ( $row["char_dead"] ? "YES" : "NO" );
		
		if( ++$counter % 2 ) {
			echo("<tr>");
		} else {
			echo("<tr class=\"dark\">");
		}
  	echo("<td>&nbsp;<a href=\"DisplayCharacter.php?char_id={$row["id"]}&\">$row[name]</a> ($row[char_type])</td>\n");
    echo("<td>$row[venue]</td>\n");
   	echo("<td>&nbsp;$row[subtype]</td>\n");	
  	echo("<td align=\"center\">&nbsp;$activeValue</td>\n");	
  	echo("<td align=\"center\">&nbsp;$char_deadValue</td>\n");	
   	echo("<form action=\"ModifyNPCList2.php\" method=\"post\">\n");
    echo("<td align=\"center\"><input type=\"hidden\" name=\"modify\" value=\"$row[id]\">" .
  	     "<input type=\"submit\" value=\"Modify\"></td>\n");	
   	echo("</form>\n");
    echo("</tr>\n");
	}

?>
</table>

<?php include_once("footerbar.inc") ?>
