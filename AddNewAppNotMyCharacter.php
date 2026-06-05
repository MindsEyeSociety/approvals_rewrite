<?php
  include_once("db.inc");
	include_once("classes/DAOFactory.php");
	
	$query="SELECT u.id ".
		"FROM users u where org_id in ('".implode( "','", $_SESSION['admin_org_list'] )."') ";
	$db->query($query);
	if ( $db->numRows()==0 ) {
		header("Location: AddNewApp.php?from_vsss=1&");
	} else { 
	 while($row = $db->nextRow())
	 {
	 	$user_info = $userInfoDAO->getUserInfo($row['id']);
	 	$item = array();
	 	$item['id'] = $row['id'];
	 	$item['name'] = $user_info['name'];
	 	$list[] = $item;
	 }
    $pagetitle="Character List";
    include_once("header.inc");
    include_once("titlebar.php");
 ?>
<form action="AddNewApp.php" method="Get">
<table class="data">
	<tr>
		<th align="center">
			For what player is this application?
		</th>
	</tr>
	<tr>
		<td align="center">
			<select name="appuser_id">
<?php
		foreach ( $list as $row ) {
			echo("<option value=\"$row[id]\">$row[name]</option>\n");
		}
?>
			</select>
		</td>
	</tr>
	<tr>
		<th align="center"><input type="submit" value="Submit"></th>
	</tr>
</table>
</form>

<?php } include_once("footerbar.inc") ?>
