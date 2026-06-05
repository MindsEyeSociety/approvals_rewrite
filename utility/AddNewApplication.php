<?php
  include_once("db.inc");
  $pagetitle="Character List";
  include_once("header.inc");
  include_once("titlebar.php");

	$db->query("select id, name, venue from characters where user_id='$_SESSION[user_id]' order by name");
  ?>

<form action="AddNewAppIntermediaryPage.php" method="post">
<table class="data">
	<tr>
		<th align="center">
			For what character is this application?
		</th>
	</tr>
	<tr>
		<td align="center">
			<select name="char_id">
				<option value="New">Not on the list!</option>
<?php
	if ( $_SESSION["admin_org_list"]!="" ) {
		echo("<option value=\"NPC\">An NPC</option>\n");
		echo("<option value=\"NonChar\">This is not a character application</option>\n");
	}
	while ( $row=$db->nextRow() ) {
		echo("<option value=\"{$row["id"]}\">{$row["name"]} ({$row["venue"]})</option>\n");
	}
?>
			</select>
		</td>
	</tr>
</table>
</form>
<?php include_once("footerbar.inc") ?>