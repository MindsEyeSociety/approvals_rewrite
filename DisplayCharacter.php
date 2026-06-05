<?php
  include_once("db.inc");
	require_once("classes/CharacterSheetVersionDAO.class.php");
	$pagetitle="Display Character";
	include_once("header.inc");
	include_once("titlebar.php");
	$thischar_id = $_GET['char_id'];
	$query="select c.*, v.venue, u.org_id, s.name as vss_name, o.org_name ".
				 "from characters c left join ".
				 "venues v on c.venue_id = v.id left join " .
				 "vsss s on c.vss_id = s.id left join ".
				 "organizations o on c.vss_id = -o.id left join ".
				 "users u on c.user_id=u.id ".
		     "where c.id='$thischar_id' AND ".
				 "(".
				 "u.id='$_SESSION[user_id]' ";
  if( sizeof($_SESSION["admin_vss_list"]) > 0 ) {
    $query.="OR c.vss_id in ('" . implode("','",$_SESSION["admin_vss_list"]) . "') ";
	}
	if( sizeof( $_SESSION["admin_org_list"]) > 0 ) {
    $query.="OR -c.vss_id in ('" . implode("','",$_SESSION["admin_org_list"]) . "') ";
  }
  if( sizeof($_SESSION["admin_org_list"]) > 0 ) {
    $query.="OR u.org_id in ('" . implode("','",$_SESSION["admin_org_list"]) . "') ";
  }
  $query.=")";

	$query.=" order by name";
	$db->query($query);
	$character = $db->nextRow();
	foreach($character as $key => $val){
		$character[$key] = strip_tags($val);
	}
	$thisVenue=$character["venue_id"];
	if ( $db->numRows()==0 ) {
		 echo("<div class=\"subhead\" align=\"center\">You do not have access to view this character</div>");
	} else {
	  if( $_SESSION['user_id'] != $character['user_id'] ) {
		  $db->query("INSERT INTO activity_log ( activity_date, character_id, user_id, description ) values ( now(), $thischar_id, $_SESSION[user_id], 'Viewed Character Sheet and Background' )");
		}

		$isStoryteller = !empty($_SESSION['admin_vss_list']) || !empty($_SESSION['admin_org_list']);
		$versions = [];
		if ($isStoryteller) {
			$versionDAO = new CharacterSheetVersionDAO($db);
			$versions = $versionDAO->getVersionsForCharacter($character['id']);
		}

 ?>

<table class="data">
	<tr>
		<th align="center">Character</th>
		<th align="center">Venue</th>
		<th align="center">VSS</th>
		<th align="center">Type</th>
		<th align="center">Active</th>
		<th align="center">Dead/Retired</th>
		<th align="center">XP</th>
	</tr>
<?php
	echo("<tr class=\"dark\"><td>".$character['name']."</td>\n");
	echo("<td>$character[venue] ($character[char_type])</td>\n");
	echo("<td>");
	if( $character["vss_id"] > 0 ) {
	  echo("<a target=\"_blank\" href=\"VSSDetails.php?id=$character[vss_id]&\">$character[vss_name]</a>");
	} elseif( $character["vss_id"] < 0 ) {
	  echo("$character[org_name]");
	} else {
	  echo("<i>(none)</i>");
	}
	if( $character['approved_in_vss'] == 0 && $character['vss_id'] > 0 ) {
	  echo(" <span class=\"normalsmall\" style=\"color:red;\">(Pending)</span> ");
	}
  if ( $character["user_id"]==$_SESSION['user_id'] ||
		 	 in_array( $character['vss_id'], $_SESSION['admin_vss_list'] ) ||
		 	 in_array( -$character['vss_id'], $_SESSION['admin_org_list'] ) ||
			 in_array( $character['org_id'], $_SESSION['admin_org_list'] ) ) {
	  echo(" (<a href=\"MoveCharacter.php?char_id=$character[id]&redirect=DisplayCharacter&\" class=\"normalsmall\">Reassign</a>)");
	}
	echo("</td>\n");
	echo("<td>$character[subtype]</td>");
	echo("<td>");
	if ( $character["active"] ) { echo("Yes"); } else { echo "No"; }
	echo("</td>\n");
	echo("<td>");
	if ( $character["char_dead"] ) { echo("Yes"); } else { echo "No"; }
	echo("</td>\n");
	echo("<td><a href=\"ModifyCharacterXPList1.php?character_id=$character[id]&\">XP List</a></td>");
?>
			
	</tr>	
	<tr>
		<th colspan="7" align="center">Character Sheet</th>
	</tr>
	<tr>
		<td colspan="7" align="left">
			<?php
			if ($isStoryteller && count($versions) > 0) {
				echo '<div class="version-picker">';
				echo '<label for="sheet-version">Sheet version:</label>';
				echo '<select id="sheet-version">';
				echo '<option value="">Current</option>';
				foreach ($versions as $v) {
					$label = date('j M Y, H:i', strtotime($v['updated_at']));
					echo "<option value=\"{$v['id']}\">$label</option>";
				}
				echo '</select>';
				echo '</div>';
			}
			?>
			<?php
				echo("<div class=\"cs\" id=\"sheet-display\">".formatBlockText($character["character_sheet"])."</div>");
			?>
		</td>
	</tr>
	<tr>
		<th colspan="7" align="center">Background</th>
	</tr>
	<tr>
		<td colspan="7" align="left"><div class="cs">
			<?php echo(formatBlockText($character["background"])); ?>
		</div></td>
	</tr>
<?php
		 $db->query("select * from applications where character_id='$character[id]' order by status desc, id desc");
?>
  <tr>
		<th colspan="7" align="center">Applications for this character</th>
	</tr>
	<tr>
		<td colspan="7" align="center">
<?php
	if ( $db->numRows()==0 ) {
		echo("<i>No applications have been submitted for this character</i>\n");
	} else {
?>
			<a name="applications"></a>
			<div class="table-scroll">
			 <table class="data">
			   <tr>
			 		 <th>App Number</th>
			 		 <th>Item</th>
			 		 <th>Status</th>
				 </tr>
<?php
		 	 while ( $approw = $db->nextRow() ) {
          print( "<tr>\n" );
          print( "<td>\n" );
          print( "<a href=\"AppDetails.php?id=$approw[id]\">$approw[app_number]</a>\n" );
          print( "</td>\n" );
          print( "<td>\n" );
          print( "$approw[description]\n" );
          print( "</td>\n" );
          print( "<td>\n" );
          print( "$approw[status]\n" );
          print( "</td>\n" );
          print( "</tr>\n" );
			 }
?>
			 </table>
			</div>
<?php
		 }
?>
		  <div class="normalsmall" align="center"><a href="AppDetails.php?char_id=<?php echo $character["id"]?>&mode=add&appuser_id=<?php echo $character["user_id"]?>&"><i>Add an Application for this Character</i></a></div>
		</td>
	</tr>
<?php if ( $character["user_id"]==$_SESSION['user_id'] ||
				 	 in_array( $character['vss_id'], $_SESSION['admin_vss_list'] ) ||
				 	 in_array( -$character['vss_id'], $_SESSION['admin_org_list'] ) ||
					 in_array( $character['org_id'], $_SESSION['admin_org_list'] ) ) { ?>
</table>
<div class="table-scroll">
<table class="data">
	<tr>
	  <th colspan="3" align="center">Activity Log</th>
	</tr>
<?php
$res = $db->query("SELECT l.user_id, l.description, ".
                  "unix_timestamp( l.activity_date ) as activity_date ".
									"FROM activity_log l ".
									"WHERE l.character_id=$character[id]");
$count = 0;
while( $row = $res->nextRow() ) {
	$count++;
  echo("<tr>\n");
	echo("<td>".strftime( "%D, %T", $row["activity_date"] )."</td>\n" );
	echo("<td>".userInfoPopup($row["user_id"])."</td>\n");
	echo("<td>$row[description]</td>\n");
	echo("</tr>\n");
}
if( $count == 0 ) {
  echo("<tr>\n");
	echo("<td colspan=\"3\"><i>(none)</i></td>\n");
	echo("</tr>\n");
}
if( $character['char_type'] == 'NPC' ) {
  echo ("<form action=\"ModifyNPCList3.php\" method=\"get\">\n");
} else {
  echo ("<form action=\"ModifyCharacterList3.php\" method=\"get\">\n");
}
 ?>
	<tr>
		<th colspan="3" align="center">
			<input type="submit" value="Edit this Character">
      <?php	echo("<input type=\"hidden\" name=\"modify\" value=\"$character[id]\">\n"); ?>
		</td>
	</tr>
<?php } ?>
</table>
</div>
</form>

<?php if ($isStoryteller && count($versions) > 0) { ?>
<script>
(function($) {
  var original = $('#sheet-display').html();
  $('#sheet-version').on('change', function() {
    var id = $(this).val();
    if (!id) {
      $('#sheet-display').html(original);
    } else {
      $.get('GetCharacterSheetVersion.php', {version_id: id})
        .done(function(html) {
          $('#sheet-display').html(html);
        })
        .fail(function(xhr) {
          $('#sheet-display').html('<p style="color:red">Error loading version (HTTP ' + xhr.status + ')</p>');
        });
    }
  });
}(jQuery));
</script>
<?php } ?>

<?php } include_once("footerbar.inc"); ?>
