<?php
  include_once("db.inc");
	$thisModify = $_GET['modify'];
	$db->query("select c.*, v.venue, u.org_id from characters c ".
	  "left join venues v on c.venue_id = v.id " .
	  "left join users u on c.user_id = u.id " .
		"where c.ID=? order by name", [$thisModify]);
	$character = $db->nextRow();
	$thisVenue=$character["venue_id"];

	// Get character DAO for subtypes
	$characterDAO = $daoFactory->getCharacterDAO();
	$venueDAO = $daoFactory->getVenueDAO();

	// Get all venues and their subtypes
	$allVenues = $venueDAO->getVenueOptions();
	$subtypes = array();
	foreach ($allVenues as $venue) {
		$subtypes[$venue['id']] = $characterDAO->getSubtypesForVenue($venue['id']);
	}

	// JSON encode for JavaScript
	$js_subtypes = json_encode($subtypes);
  if ( $character["user_id"]!=($_SESSION['user_id'] ?? null) &&
  	 	 (empty($_SESSION['admin_vss_list']) ||!in_array( $character['vss_id'], $_SESSION['admin_vss_list'] )) &&
  	 	 (empty($_SESSION['admin_org_list']) || !in_array( -$character['vss_id'], $_SESSION['admin_org_list'] )) &&
  		 (empty($_SESSION['admin_org_list']) || !in_array( $character['org_id'], $_SESSION['admin_org_list'] )) ) {
		header("Location: index.php");
	}

	$pagetitle="Modify Character";
	include_once("header.inc");
	include_once("titlebar.php");
 ?>

<form action="ModifyCharacterList4.php" method="post">
<div class="table-scroll">
<table class="data">
	<tr>
		<th align="center">Character</th>
		<th align="center">Venue</th>
		<th align="center">Type</th>
		<th align="center">Active</th>
		<th align="center">Dead/Retired</th>
	</tr>
<?php
	echo("<input type=\"hidden\" name=\"id\" value=\"$character[id]\">\n");
	echo("<tr class=\"dark\"><td valign=\"top\"><input type=\"text\" size=\"27\" name=\"name\" value=\"$character[name]\"></td>\n");
	$db->query("select * from venues order by venue");
	echo("<td valign=\"top\"><select name=\"venue\">");	
	while ( $row=$db->nextRow() ) {
		echo("<option value=\"$row[id]\"");
		if ( $character["venue"]==$row["venue"] ) { echo(" selected"); }
		echo(">{$row["venue"]}</option>");
	}
	echo("</select></td>\n");
	echo("<td valign=\"top\"><div id=\"subtype_container\">");
	if (!empty($subtypes[$thisVenue])) {
		// Show dropdown for venues with subtypes
		echo("<select name=\"SubType\" id=\"subtype_select\">");
		echo("<option value=\"\">-- Select Subtype --</option>");
		foreach ($subtypes[$thisVenue] as $subtype) {
			$selected = ($character["subtype"] == $subtype["subtype"]) ? " selected" : "";
			echo("<option value=\"{$subtype["subtype"]}\"$selected>{$subtype["subtype"]}</option>");
		}
		echo("</select>");
	} else {
		// Show text input for venues without subtypes
		echo("<input type=\"text\" size=\"27\" name=\"SubType\" value=\"$character[subtype]\" id=\"subtype_text\">");
	}
	echo("</div></td>");
	echo("<td valign=\"top\"><input type=\"checkbox\" name=\"Active\"");
	if ( $character["active"] ) { echo(" checked"); }
	echo("></td>\n");
	echo("<td valign=\"top\"><input type=\"checkbox\" name=\"char_dead\"");
	if ( $character["char_dead"] ) { echo(" checked"); }
	echo(">");
	if ( !$character["char_dead"] ) { echo("<div class=normalsmall><i>Checking this box will set all applications for this character to 'Removed'.  Do not do this unless you are sure you want this to happen.</i></div>\n"); }
	echo("</td>\n");
?>
		
	</tr>	
	<tr>
		<th colspan="5" align="center">Character Sheet</th>
	</tr>
	<tr>
		<td colspan="5" align="center">
			<textarea name="character_sheet" rows="10" wrap="virtual"><?php echo($character["character_sheet"]); ?></textarea>
		</td>
	</tr>
	<tr>
		<th colspan="5" align="center">Background</th>
	</tr>
	<tr>
		<td colspan="5" align="center">
			<textarea name="Background" rows="10" wrap="virtual"><?php echo($character["background"]); ?></textarea>
		</td>
	</tr>
	<tr>
		<td colspan="5" align="center">
			<input type="submit" value="Enter New Information">
		</td>
	</tr>
<?php
		 $db->query("select * from applications where character_id='$character[id]' order by status desc, id desc");
?>

	<tr>
		<th colspan="5" align="center">Applications for this character</th>
	</tr>
	<tr>
		<td colspan="5" align="center">
<?php
		 if ( $db->numRows()==0 ) {
		 		echo("<i>No applications have been submitted for this character</i>\n");
		 } else {
?>
			 <table class="data">
			   <tr>
			 		 <th>App Number</th>
					 <th>Item</th>
			 		 <th>Status</th>
				 </tr>
<?php
		 	 while ( $approw = $db->nextRow() ) {
          print( "<tr>\n" );
          print( "<td valign=\"top\">\n" );
          print( "<a href=\"AppDetails.php?id=$approw[id]\">$approw[app_number]</a>\n" );
          print( "</td>\n" );
          print( "<td valign=\"top\">\n" );
          print( "$approw[description]\n" );
          print( "</td>\n" );
          print( "<td>\n" );
          print( "$approw[status]\n" );
          print( "</td>\n" );
          print( "</tr>\n" );
			 }
?>
			 </table>
<?php
		 }
?>
		</td>
	</tr>
<?php		 
	if ( $db->numRows() > 0 && !$_SESSION["super_user"] ) {
?>
	<tr>
		<td colspan="5" align="left">
			&nbsp;<i>This Character cannot be deleted because one or more applications have been submitted for it.</i>
		</td>
	</tr>
<?php
	} else {
?>
	<tr>
		<th colspan="3" align="left">
			<input type="submit" value="Permanently Delete this Character" name="delete">
		</th>
		<th colspan="2" align="right">
				<input type="checkbox" name="ConfirmDelete"> <b>Confirm Permanent Deletion</b>
		</th>
	</tr>
<?php
	}
?>
</table>
</div>
</form>

<script type="text/javascript">
var subtypes = <?php echo $js_subtypes; ?>;

$(document).ready(function() {
    $('select[name="venue"]').change(function() {
        updateSubtypeField();
    });
});

function updateSubtypeField() {
    var venueId = $('select[name="venue"]').val();
    var container = $('#subtype_container');

    if (subtypes[venueId] && subtypes[venueId].length > 0) {
        // Show dropdown
        var selectHtml = '<select name="SubType" id="subtype_select">';
        selectHtml += '<option value="">-- Select Subtype --</option>';
        for (var i = 0; i < subtypes[venueId].length; i++) {
            selectHtml += '<option value="' + subtypes[venueId][i].subtype + '">' + subtypes[venueId][i].subtype + '</option>';
        }
        selectHtml += '</select>';
        container.html(selectHtml);
    } else {
        // Show text input
        container.html('<input type="text" size="27" name="SubType" id="subtype_text">');
    }
}
</script>

<?php include_once("footerbar.inc"); ?>
