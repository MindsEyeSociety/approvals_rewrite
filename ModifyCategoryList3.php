<?php
	include_once("db.inc");
	$pagetitle="Modify Category";
include_once('application.inc');
	$category_id = $_GET['modify'];
	$db->query("SELECT * FROM categories WHERE id=?", [$category_id]);
	$category = $db->nextRow();

	$db->query("SELECT venue_id FROM category_venue WHERE category_id=?", [$category_id]);
	$venues = array();
	while( $row = $db->nextRow() ) {
	  array_push( $venues, $row["venue_id"] );
	}
	$db->query("SELECT * FROM venues ORDER BY venue");
	$numVenues=$db->numRows();
	$venueOptions = $db->getAllRows();

	$smarty->assign( "menus", generateMenus() );
	$smarty->assign( "page_title", $pagetitle );
	$smarty->assign( "venueOptions", $venueOptions );
	$smarty->assign( "numVenues", $numVenues );
	$smarty->assign( "category", $category );
	$smarty->assign( "venues", $venues );

	$smarty->display( "ModifyCategoryList3.html");

?>

<form action="ModifyCategoryList4.php" method="post">
<input type="hidden" name="modify" value="<?php echo($category_id); ?>" />
<table class="data">
	<tr>
	  	<th class="subhead" align="center">Category</th>
	  	<th class="subhead" align="center">Venue</th>
	</tr>

<?php
	echo("<tr class=\"dark\">\n");
	echo("<td><input type=\"text\" size=\"27\" name=\"category\" value=\"".$category["category"]."\"></td>\n");
	echo("<td><select multiple name=\"venue_ids[]\" size=\"$numVenues\">");
	foreach( $venueOptions as $venue ) {
		echo("<option value=\"" . $venue["id"] . "\"");
		if ( in_array( $venue["id"], $venues ) )	{
			echo(" selected=\"selected\"");
		}
		echo(">" . $venue["venue"] . "</option>\n");
	}
	echo("</select></td>");
?>
	</tr>	
	<tr>
		<th colspan="2" align="center">
			<input type="submit" value="Enter New Information">
		</th>
	</tr>
</table>
</form>
<?php 
include_once("footerbar.inc");
?>