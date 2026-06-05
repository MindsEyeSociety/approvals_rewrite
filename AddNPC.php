<?php
  include_once("db.inc");
  $pagetitle="Add an NPC";
  include_once("header.inc");
  include_once("titlebar.php");
	include_once("classes/VenueDAO.php");

	if ( isset($_GET["errmsg"]) && $_GET["errmsg"]==1 ) {
    print( "<div align=\"center\" class=\"error\">\n" );
    print( "There is already an NPC with that name\n" );
    print( "</div>\n" );
	}
	if ( isset($_GET["errmsg"]) && $_GET["errmsg"]==2 ) {
    print( "<div align=\"center\" class=\"error\">You must choose a name for the NPC</div>\n" );
	}

	$venueDAO = new VenueDAO( $db );
	$venues = $venueDAO->getVenueOptions();

 ?>
<form action="ModifyNPCListAddNPC.php" method="post">
<?php if ( isset( $_GET['redirect'] ) ) { ?>
	<input type="hidden" name="redirect" value="<?php echo $redirect?>">
<?php } ?>
<table class="data">
		<tr>
			<td>Character Name</td>
			<td><input type="text" size="27" name="name"></td>
		</tr>
		<tr> 
			<td>Venue</td>
			<td>
				<select name="venue" id="venue">
<?php
	foreach( $venues as $venue ) {
		echo("<option value=\"$venue[id]\">$venue[venue]</option>");
	}
?>
				</select>
			</td>
    </tr>
    <tr>
			<td>Subtype <span class="normalsmall">(Clan, Kith, Tribe, etc)</span></td>
			<td><input type="text" size="27" name="SubType"></td>
		</tr>	
		<tr>
			<td>Type</td>
			<td>
				<select name="Type">
<?php
	$db->query("SELECT id, name FROM vsss WHERE storyteller_id = $_SESSION[user_id]");
	while( $row = $db->nextRow() ) {
	  echo("<option value=\"NPC:$row[id]\"");
		if (isset($_GET["npc"])) {
			echo(" selected");
		}
	  echo(">NPC for {$row["name"]} VSS</option>");
	}
	$db->query("SELECT o.id, o.org_name FROM storytellers s ".
	           "LEFT JOIN organizations o ON s.organization_id = o.id ".
						 "WHERE s.user_id = $_SESSION[user_id]");
	while( $row = $db->nextRow() ) {
	  echo("<option value=\"NPC:-{$row["id"]}\"");
		  if (isset($_GET["npc"])) {
			echo(" selected");
		}
	  echo(">NPC for $row[org_name]</option>");
	}
?>
				</select>
			</td>
		</tr>	
		<tr>
			<td>Character Sheet</td>
			<td>
				<textarea name="character_sheet" cols="65" rows="10" wrap="virtual"></textarea>
			</td>
		</tr>
		<tr>
			<td>Background</td>
			<td>
				<textarea name="Background" cols="65" rows="10" wrap="virtual"></textarea>
			</td>
		</tr>
		<tr>
			<th colspan="4" align="center">
				<input type="submit" value="Enter New Information">
			</th>
		</tr>
</table>
</form>

<?php include_once("footerbar.inc") ?>
