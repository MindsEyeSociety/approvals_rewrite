<?php
include_once("db.inc");
$pagetitle="Character List";
include_once("header.inc");
include_once("titlebar.php");

$query = "SELECT c.id, c.name, c.subtype, c.char_type, c.active, ".
           "c.char_dead, v.venue, o.org_name AS org_name, ".
           "CONCAT(pu.firstName, ' ', pu.lastName) AS ost_name, s.name AS vss_name, u.name AS vst_name, ".
           "COALESCE((SELECT SUM(xpearned) FROM earnedxp WHERE character_id = c.id), 0) AS total_earned, ".
           "COALESCE((SELECT SUM(xpspent) FROM spentxp WHERE character_id = c.id), 0) AS total_spent ".
           "FROM characters c ".
           "LEFT OUTER JOIN vsss s ON c.vss_id=s.id ".
           "LEFT OUTER JOIN organizations o ON (c.vss_id = -1*o.id OR c.org_id = o.id) ".
           "LEFT OUTER JOIN venues v ON c.venue_id = v.id ".
           "LEFT JOIN users u ON s.storyteller_id = u.id ".
           "LEFT JOIN users ou ON o.admin_user_id = ou.id ".
           "LEFT JOIN `mes-portal`.User pu ON ou.ww_number = pu.membershipNumber ".
           "WHERE c.user_id='$_SESSION[user_id]' ";
if( !isset($_GET['showall']) || $_GET['showall'] != 1 ) {
	$query .= "AND c.active ";
}
$query .= "ORDER BY char_dead, active desc, name ";
$db->query($query);

$show_all = isset($_GET["showall"]) && $_GET["showall"] == 1;

if ( isset($_GET["message"]) && $_GET["message"]=="vss_assigned" ) {
	print( "<div align=\"center\" class=\"message\">\n" );
	print( "Character assigned to VSS\n" );
	print( "</div>\n" );
}
?>

<?php if( $show_all ) { ?>
<div><a href="ModifyCharacterList1.php">Hide inactive characters.</a></div>
<?php } else { ?>
<div><a href="ModifyCharacterList1.php?showall=1">Show dead and retired
characters.</a></div>
<?php } ?>

<table class="data list">
	<tr>
		<th>Name</th>
		<th>Venue</th>
		<th>VSS</th>
		<th>Type</th>
		<th>Active</th>
		<th>Dead/Retired?</th>
		<th>Unspent XP</th>
		<th>Modify</th>
	</tr>
	<?php
	$rows = array();
	while( $row = $db->nextRow() ) { $rows[] = $row; }
	$counter = 0;
	foreach( $rows as $row ) {
		$counter++;
		$activeValue = ( $row["active"] ? "YES" : "NO" );
		$char_deadValue = ( $row["char_dead"] ? "YES" : "NO" );
		?>
	<tr <?php if($counter%2 == 0) {?> class="dark" <?php } ?>>
		<td><a href="DisplayCharacter.php?char_id=<?php echo $row["id"]?>&"><?php echo $row["name"]?></a>
		(<?php echo $row["char_type"]?>)</td>
		<td><?php echo $row["venue"]?></td>
		<td><?php	
		if( $row["vss_name"] != "" ) {
			echo(str_replace(" ","&nbsp;","$row[vss_name]&nbsp;($row[vst_name])"));
		} else if($row["org_name"] != "" ) {
	  echo(str_replace(" ","&nbsp;","$row[org_name]&nbsp;($row[ost_name])"));
		} else {
			echo("<i>(none)</i>");
		}	?> <input type="button"
			onclick="window.location='MoveCharacter.php?char_id=<?php echo $row["id"]?>'"
			value="Move Character" /></td>
		<td><?php echo $row["subtype"]?></td>
		<td><?php echo $activeValue?></td>
		<td><?php echo $char_deadValue?></td>
		<td><a
			href="ModifyCharacterXPList1.php?character_id=<?php echo $row["id"]?>"><?php echo $row['total_earned'] - $row['total_spent']?></a></td>
		<td><input type="submit" value="Modify"
			onclick="window.location='ModifyCharacterList3.php?modify=<?php echo $row["id"]?>'"></td>
	</tr>
	<?php
	}
	?>
	<tr>
		<th colspan="8"><a href="AddCharacter.php">Add a new Character</a></th>
	</tr>
</table>

	<?php include_once("footerbar.inc") ?>