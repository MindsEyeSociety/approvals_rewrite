<?php
  include_once("db.inc");
	$pagetitle="ST Instructions";
  include_once("header.inc");
  include_once("titlebar.php");
	
	if( isset( $_GET['vss_id'] ) ) {
  	$query="select v.name, i.instruction from vsss v left join ".
  				 "instructions i on i.vss_id=v.id where v.id=?";
	} elseif ( isset( $_GET['org_id'] ) ) {
  	$query="select o.org_name as name, i.instruction from organizations o left join ".
  				 "instructions i on i.org_id=o.id where o.id=?";
	} else {
	  $query = "";
	}
	if ($query != "") {
		$params = [];
		if (isset($_GET['vss_id'])) {
			$params[] = $_GET['vss_id'];
		} elseif (isset($_GET['org_id'])) {
			$params[] = $_GET['org_id'];
		}
		$db->query($query, $params);
		$row=$db->nextRow();
	} else {
		$row = null;
	}


	if( isset( $_GET["message"] ) ) {
		if ( $_GET["message"]=="ChangesMade" ) {
    	echo("<div class=\"message\" align=\"center\">" .
    		"Instructions Updated Successfully</div><br>\n");
  	}
  }

	if ($row) {
	echo("<div class=\"message\" align=\"center\">\n" .
		"Instructions for all applications within " . $row["name"] . " ");
	echo("</div>\n");
	}
 ?>

<form action="ModifySTInstructions2.php" method="post">
<table class="data">
	<tr>
		<th align="center">Instructions</th>
	</tr>
		<tr>
			<td align="center">
<?php
	if ($row) {
	echo("<textarea name=\"instruction\" wrap=\"virtual\" cols=\"65\" rows=\"10\">" . ($row["instruction"] ?? "") . "</textarea>\n");
	}
	if( isset( $_GET["org_id"] ) ) {
	  echo("<input type=\"hidden\" name=\"org_id\" value=\"" . htmlspecialchars($_GET["org_id"]) . "\">\n");
	} elseif( isset( $_GET["vss_id"] ) ) {
  	echo("<input type=\"hidden\" name=\"vss_id\" value=\"" . htmlspecialchars($_GET["vss_id"]) . "\">\n");
	}
?>
			</td>
		</tr>		
		<tr class="dark">
			<td align="center">
				<input type="submit" value="Enter Changes">
			</td>
		</tr>
</table>
</form>

<?php include_once("footerbar.inc"); ?>