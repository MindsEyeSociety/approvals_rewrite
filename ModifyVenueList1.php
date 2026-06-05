<?php
  include_once("db.inc");
	$pagetitle="Venue Listing";
  include_once("header.inc");
  include_once("titlebar.php");

  $db->query("SELECT * FROM venues ORDER BY venue");
 ?>

<form action="ModifyVenueListAddVenue.php" method="post">
<table class="data add">
	<tr>
		<th style="subhead" colspan="3">Add New Venue</th>
	</tr>
	<tr>
		<td colspan="3"><input type="text" size="27" name="venue" /></td>
	</tr>	
	<tr>
		<th colspan="3" align="center"><input type="submit" value="Enter New Information" /></th>
	</tr>
</table>
</form>

<form action="ModifyVenueList2.php" method="post">
<table class="data listing">
	<tr>
		<th>Venue</th>
		<th>Delete</th>
		<th>Modify</th>
	</tr>
<?php
	$i = 1;
  while( $row = $db->nextRow() ) {
		$i = !$i;
?> 
	<tr<?php if($i){ ?>class="dark"<?php } ?>>
		<td><?php echo $row["venue"]?></td>
<?php if ( $row["venue"] == "Non Venue-Specific" ) { ?>
		<td>&nbsp;</td>
		<td>&nbsp;</td>
<?php } else { ?>
  	<td align="center"><input type="checkbox" name="delete[]" value="<?php echo $row["id"] ?>" /></td>
	  <td align="center"><input type="radio" name="modify" value="<?php echo $row["id"]?>" /></td>
<?php	} ?>
	</tr>
<?php } ?>
	<tr>
		<td colspan="3" align="center"><input type="submit" value="Enter Changes" /></td>
	</tr>
</table>
</form>


<?php include_once("footerbar.inc") ?>
