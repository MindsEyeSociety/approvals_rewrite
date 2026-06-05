<?php
/*
This page is designed to save public mechanics for applications so that
other users can apply for the same items (such as the "Force" discipline).

It requires a table called:
mechanics

This table has the following fields:
id, an autonum
user_id, a number
commentdate, a date
comment, a memo/text
subject, a varchar(255)
active, a yes/no (bit)
*/

  include_once("db.inc");
  $pagetitle="Custom Mechanics";

  include_once("header.inc");
  include_once("titlebar.php");

  // Grab the first message number from the URL
  $first_message = isset( $_GET['msg'] ) ? $_GET['msg'] : 0 ;

  // Set up some variables for changing things later.
  $msg_display = 20;

	if ( $_SESSION['admin_level']=="nation" || 
       $_SESSION['admin_level']=="globe" ||
       $_SESSION['super_user'] ) {
		 $ThisAdmin=1;
	}
	
	$ThisMode=$_GET['mode'];
	if ( $ThisMode == "" or $ThisAdmin!="1" ) {
		 $ThisMode="Display";
	}
	if ( $ThisMode=="Display" ) {
		 echo("<form action=\"MechanicsDetails.php\" method=\"GET\">\n");
		 echo("<input type=\"hidden\" name=\"id\" value=\"$_GET[id]\">");
		 echo("<input type=\"hidden\" name=\"mode\" value=\"Edit\">");
		 $SubmitValue="Edit Record";
	} else {
		 echo("<form action=\"MechanicsDetailsRecordAction.php\" method=\"POST\">\n");
		 echo("<input type=\"hidden\" name=\"id\" value=\"$_GET[id]\">");
		 echo("<input type=\"hidden\" name=\"mode\" value=\"$ThisMode\">");
		 $SubmitValue="Enter Changes";
	}

	$venueDAO = $daoFactory->getVenueDAO();
	$venues = $venueDAO->getVenueOptions();
	
	$query="SELECT distinct category from categories order by category";
	$categories=$db->query($query);
	
  $query="SELECT m.*, v.venue ".
					"FROM mechanics m left join ".
					"venues v on m.venue_id=v.id ".
					"WHERE m.id='$_GET[id]' ".
					"ORDER BY v.id, m.category , m.title";
  $result = $db->query($query);
  $row=$result->nextRow();
?>

  <table class="data">
			<?php
			if ( $ThisAdmin=="1" ) {
			?>
			<tr>
					<th colspan="3" align="center">
							<input type="submit" value="<?php echo $SubmitValue?>">
					</th>
			</tr>
			<?php
			}
			?>
			<tr>
					<th>Title</th>
					<th>Venue</td>
					<th>Category</td>
			</tr>
			<tr>
					<td>
							<?php
							if ( $ThisMode == "Display" ) {
								 echo($row["title"]);
							} else {
								echo("<input type=\"text\" size=\"27\" name=\"title\" value=\"$row[title]\">");
							}
							?>
					</td>
					<td>
							<?php
							if ( $ThisMode == "Display" ) {
								 echo($row["venue"]);
							} else {
								echo("<select name=\"venue_id\">\n");
								foreach( $venues as $venue ) {
									echo("<option value=\"$venue[id]\"");
									if ( $row["venue_id"]==$venue["id"] ) { echo(" selected"); }
									echo(">$venue[venue]</option>");
								}
								echo("</select>\n");
							}
							?>
					</td>
					<td>
							<?php
							if ( $ThisMode == "Display" ) {
								 echo($row["category"]);
							} else {
								echo("<select name=\"category\">\n");
								while ( $category=$categories->nextRow() ) {
									echo("<option value=\"$category[category]\"");
									if ( $row["category"]==$category["category"] ) { echo(" selected"); }
									echo(">$category[category]</option>");
								}
								echo("</select>\n");
							}
							?>
					</td>
			</tr>
			
			<tr>
					<th colspan="3">
							Description
					</th>
			</tr>

			<tr>
					<td colspan="3">
							<?php
							if ( $ThisMode == "Display" ) {
								 echo(nl2br($row["description"]));
							} else {
								echo("<textarea name=\"description\" cols=\"60\" rows=\"10\" wrap=\"virtual\">$row[description]</textarea>");
							}
							?>&nbsp;
					</td>
			</tr>
			
			<tr>
					<th colspan="3">
							Mechanics
					</th>
			</tr>

			<tr>
					<td colspan="3">
							<?php
							if ( $ThisMode == "Display" ) {
								 echo(nl2br($row["mechanics"]));
							} else {
								echo("<textarea name=\"mechanics\" cols=\"60\" rows=\"10\" wrap=\"virtual\">$row[mechanics]</textarea>");
							}
							?>&nbsp;
					</td>
			</tr>
			
			<tr>
					<th colspan="3">
							Prerequisites
					</th>
			</tr>

			<tr>
					<td colspan="3">
							<?php
							if ( $ThisMode == "Display" ) {
								 echo(nl2br($row["prerequisites"]));
							} else {
								echo("<textarea name=\"prerequisites\" cols=\"60\" rows=\"10\" wrap=\"virtual\">$row[prerequisites]</textarea>");
							}
							?>&nbsp;
					</td>
			</tr>
			
			<tr>
					<th colspan="3">
							Reference Sources
					</th>
			</tr>

			<tr>
					<td colspan="3">
							<?php
							if ( $ThisMode == "Display" ) {
								 echo(nl2br($row["sources"]));
							} else {
								echo("<textarea name=\"sources\" cols=\"60\" rows=\"10\" wrap=\"virtual\">$row[sources]</textarea>");
							}
							?>&nbsp;
					</td>
			</tr>
			<?php
			if ( $ThisAdmin=="1" ) {
			?>
			<tr>
					<th colspan="3" align="center">
							<input type="submit" value="<?php echo $SubmitValue?>">
					</th>
			</tr>
			<?php
			}
			?>
			
  </table>
	<?php
	if ( $ThisAdmin=="1" && $ThisMode=="Edit" ) {
	?>
  <table width="100%" align="center" border="0" cellspacing="1" cellpadding="2">
			<tr>
					<th align="left" width="50%">
							<input name="delete" type="submit" value="Delete Record">
					</th>
					<th align="right" width="50%">
							Confirm Permanent Deletion
							<input type="checkbox" name="ConfirmDelete">
					</th>
			</tr>
  </table>
	<?php
	}
	?>
</td></tr></table>
</form>

<?php
include_once("footerbar.inc")
?>
