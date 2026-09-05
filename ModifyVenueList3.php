<?php
  include_once("db.inc");
	$pagetitle="Modify Venue";
	include_once("header.inc");
	include_once("titlebar.php");
  $thisModify = $_GET["modify"];
	$db->query("select * from venues where ID=?", [$thisModify]);
  $row = $db->nextRow();
 ?>

<form action="ModifyVenueList4.php" method="post">
<input type="hidden" name="id" value="<?php echo $row["id"]?>">
<table class="data">
	<tr>
		<th align="center">Venue</th>
	</tr>
	<tr class="dark">
		<td>
			<input type="text" size="27" name="venue" value="<?php echo $row["venue"]?>">
		</td>
	</tr>	
	<tr>
		<td align="center">
			<input type="submit" value="Enter New Information">
		</td>
	</tr>
</table>
</form>

<?php include_once("footerbar.inc"); ?>
