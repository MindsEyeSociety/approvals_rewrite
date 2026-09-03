<?php
  include_once("db.inc");
	$pagetitle="Modify Character";
	include_once("header.inc");
	include_once("titlebar.php");
	$thisModify = $_GET['modify'];
	$thisTable=$_GET["table"];
	$thisDate = ( $thisTable == "earnedxp" ) ? "earneddate" : "spentdate";
	
	$db->query("select * from $thisTable where id=$thisModify");
	$row = $db->nextRow();
?>

<form action="ModifyCharacterXPList4.php" method="post">
	<input type="hidden" name="character_id" value="<?php echo $row["character_id"]?>">
	<input type="hidden" name="table" value="<?php echo $thisTable?>">
	<input type="hidden" name="id" value="<?php echo $thisModify?>">
<table class="data">
	<?php
	if ( $thisTable=="earnedxp" ) {
	?>
		<tr>
			<th align="center">Event/Source</th>
			<th align="center">Date</th>
			<th align="center">XP Earned</th>
			<th align="center">Notes</th>
		</tr>
		<tr>
			<td>
				<input type="text" size="20" name="eventname" value="<?php echo $row["eventname"]?>">
			</td>
			<td>
				<input type="text" size="10" name="earneddate" value="<?php echo strftime( '%x', strtotime( $row['earneddate'] ) )?>">
			</td>
			<td>
				<input type="text" size="2" name="xpearned" value="<?php echo $row["xpearned"]?>">
			</td>
			<td>
				<input type="text" size="40" name="notes" value="<?php echo $row["notes"]?>">
			</td>
		</tr>
		<tr>
			<th align="center" colspan="4">
				<input type="submit" value="Enter New Values">
			</th>
		</tr>
	<?php
	} else {
	?>
		<tr>
			<th align="center">Item Bought</td>
			<th align="center">Date</td>
			<th align="center">XP Spent</td>
			<th align="center">Notes</td>
		</tr>
		<tr>
			<td>
				<input type="text" size="20" name="itembought" value="<?php echo $row["itembought"]?>">
			</td>
			<td>
				<input type="text" size="10" name="spentdate" value="<?php echo strftime( '%x', strtotime( $row['spentdate'] ) )?>">
			</td>
			<td>
				<input type="text" size="2" name="xpspent" value="<?php echo $row["xpspent"]?>">
			</td>
			<td>
				<input type="text" size="40" name="notes" value="<?php echo $row["notes"]?>">
			</td>
		</tr>
		<tr>
			<th align="center" colspan="4">
				<input type="submit" value="Enter New Values">
			</th>
		</tr>
	<?php
	}
	?>
</table>
</form>

<?php 
include_once("footerbar.inc");
