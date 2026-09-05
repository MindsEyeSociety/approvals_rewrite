<?php
  include_once("db.inc");

  $pagetitle = "Modify Org";
  include_once("header.inc");
  include_once("titlebar.php");

  $ThisModify=$_GET["modify"];

   // Permission check: Super user OR explicitly assigned org admin for THIS organization
   $adminUserIds = isset($_SESSION['org_admin_user_list']) ? array_keys($_SESSION['org_admin_user_list']) : [];
   $isAllowed = (isset($_SESSION['super_user']) || in_array($ThisModify, $adminUserIds));

   if (!$isAllowed) {
     header("Location: ModifyOrgList1.php");
     exit;
   }

  // Use the org being modified to determine the hierarchy context for the form
  $db->query("select * from organizations where id=?", [$ThisModify]);
  $row=$db->nextRow();
  if (!$row) {
    header("Location: ModifyOrgList1.php");
    exit;
  }
  $ThisNation = isset($row["nation"]) ? $row["nation"] : "";
  $ThisRegion = isset($row["region"]) ? $row["region"] : "";
  $ThisDomain = isset($row["domain"]) ? $row["domain"] : "";
  $ThisChapter = isset($row["chapter"]) ? $row["chapter"] : "";

  $db->query("select * from organizations where id=?", [$ThisModify]);

  $row = $db->nextRow();
  if (!$row) {
    echo("<div class=\"error\">Organization ID $ThisModify not found.</div>");
    exit;
  }
  $thisOrgAdminID = isset($row["admin_user_id"]) ? $row["admin_user_id"] : 0;
  $thisOrgActive = isset($row["active"]) ? $row["active"] : 1;
?>

<form action="ModifyOrgList4.php" method="post">
<table class="data">
	<tr>
			<th align="center">Nation</th>
			<th align="center">Region</th>
			<th align="center">Domain</th>
			<th align="center">Chapter</th>
			<th align="center">Name</th>
	</tr>
<?php
	echo("<input type=\"hidden\" name=\"id\" value=\"$row[id]\">\n");
  echo("<tr>\n");
  echo("<td>\n");
  if ($ThisNation!="" && !$_SESSION["super_user"]) {
    echo("$ThisNation");
    echo("<input type=\"hidden\" name=\"nation\" value=\"$ThisNation\">\n");
  } else {
    echo("<input type=\"text\" size=\"7\" name=\"nation\" value=\"$row[nation]\">\n");
    $ThisNation=$row["nation"];
  }
  echo("</td>\n");
  echo("<td>\n");
  if ($ThisRegion!="" && !$_SESSION["super_user"]) {
  	echo("$ThisRegion");
  	echo("<input type=\"hidden\" name=\"region\" value=\"$ThisRegion\">\n");
  } else {
  	echo("<input type=\"text\" size=\"7\" name=\"region\" value=\"$row[region]\">\n");
  	$ThisRegion=$row["region"];
  }
  echo("</td>\n");
  echo("<td>\n");
  if ($ThisDomain!="" && !$_SESSION["super_user"]) {
  	echo("$ThisDomain");
  	echo("<input type=\"hidden\" name=\"domain\" value=\"$ThisDomain\">\n");
  } else {
  	echo("<input type=\"text\" size=\"7\" name=\"domain\" value=\"$row[domain]\">\n");
  	$ThisDomain=$row["domain"];
  }
			echo("</td>\n");
			echo("<td>\n");
				if ($ThisChapter!="" && !$_SESSION["super_user"]) {
					echo("$ThisChapter");
					echo("<input type=\"hidden\" name=\"chapter\" value=\"$ThisChapter\">\n");
				} else {
					echo("<input type=\"text\" size=\"7\" name=\"chapter\" value=\"$row[chapter]\">\n");
					$ThisChapter=$row["chapter"];
				}
			echo("</td>\n");
			echo("<td>\n");
				echo("<input type=\"text\" size=\"27\" name=\"orgName\" value=\"$row[org_name]\">\n");
			echo("</td>\n");
		echo("</tr>\n");
?>

    <tr>
      <th>City</th>
      <th>State</th>
      <th>Country</th>
      <th>Admin User</th>
      <th>Organization Email</th>
    </tr>
    <tr>
<?php
  echo("<td><input type=\"text\" size=\"20\" name=\"city\" value=\"$row[city]\"></td>\n");
  echo("<td><input type=\"text\" size=\"4\" name=\"state\" value=\"$row[state]\"></td>\n");
  echo("<td><input type=\"text\" size=\"20\" name=\"country\" value=\"$row[country]\"></td>\n");
  echo("<td>\n");
	// Admin user can be from any org, so query all active members
	$query = "SELECT u.id FROM users u INNER JOIN `mes-portal`.User pu ON u.ww_number = pu.membershipNumber AND pu.membershipExpiration > NOW()";
	$res = $db->query($query);
	
  echo("<select name=\"admin_user_id\">\n");
  echo("<option value=\"0\">(none)</option>\n");
  
  $nameList = Array();
  while( $row3=$res->nextRow() ) 
  {
  		$user_info = $userInfoDAO->getUserInfo(intval($row3['id']));
  		$row3['name'] = trim($user_info['name']);
  		if($row3['name'] == 'No Name Set' or $row3['name'] == '') continue;
     	$nameList[] = Array(strtolower($row3['name']), $row3['id'], $row3['name']);
  }
  
  usort($nameList, function($a, $b) { return strcmp($a[0], $b[0]); });
  
  foreach ($nameList as $myName)
  {
		echo("<option value=\"$myName[1]\" ");
		if ($myName[1] == $thisOrgAdminID) { echo("selected"); }
			echo(">$myName[2]</option>\n");
  }
  
	echo("</select>\n");
	echo("</td>\n");
  echo("<td>\n");
echo("<input type=\"text\" size=\"20\" name=\"email\" value=\"$row[email]\">");
if($row['email_is_google']) echo "<span style='color: green'> Google Apps</span>";
else echo "<span style='color: red'> Not Google Apps</span>";
	echo("</td>\n");
  echo("</tr>\n");
?>

	<tr>
		<th colspan="2">Active</th>
		<td colspan="3">
			<input type="checkbox" name="active" value="1"<?php if( $thisOrgActive ) echo( " checked"); ?>>
		</td>
	</tr>
	<tr>
		<th colspan="5" align="center">
			<input type="submit" value="Enter New Information">
		</th>
	</tr>
</table>
</form>

<?php include_once( 'footerbar.inc'); ?>
