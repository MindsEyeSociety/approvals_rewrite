<?php
  include_once("db.inc");
	$thisModify = $_GET['modify'];
	$db->query("select c.*, v.venue, u.org_id from characters c ".
	  "left join venues v on c.venue_id = v.id " .
	  "left join users u on c.user_id = u.id " .
		"where c.ID=? order by name", [$thisModify]);
	$character = $db->nextRow();
	$thisVenue=$character["venue_id"];
  if ( $character["user_id"]!=$_SESSION['user_id'] &&
  	 	 !in_array( $character['vss_id'], $_SESSION['admin_vss_list'] ) &&
  	 	 !in_array( -$character['vss_id'], $_SESSION['admin_org_list'] ) &&
  		 !in_array( $character['org_id'], $_SESSION['admin_org_list'] ) ) {
		header("Location: index.php");
	}

	$pagetitle="Modify Character";
	include_once("header.inc");
	include_once("titlebar.php");
 ?>

<form action="ModifyNPCList4.php" method="post">
<table class="data">
	<tr>
		<th align="center">NPC Name</th>
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
		echo(">$row[venue]</option>");
	}
	echo("</select></td>\n");
	echo("<td valign=\"top\"><input type=\"text\" size=\"27\" name=\"SubType\" value=\"$character[subtype]\"></td>");
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
			<textarea name="character_sheet" style="width:98%;" cols="65" rows="10" wrap="virtual"><?php echo($character["character_sheet"]); ?></textarea>
		</td>
	</tr>
	<tr>
		<th colspan="5" align="center">Background</th>
	</tr>
	<tr>
		<td colspan="5" align="center">
			<textarea name="Background" cols="65" rows="10" wrap="virtual"><?php echo($character["background"]); ?></textarea>
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
		<th colspan="5" align="center">Applications for this NPC</th>
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
</form>

<?php include_once("footerbar.inc"); ?>
