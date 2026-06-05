<?php
  include_once("db.inc");

  $db->query("select o.globe, o.nation, o.domain, o.region, chapter from storytellers s LEFT JOIN organizations o on s.organization_id = o.id where s.user_id='$_SESSION[user_id]' order by o.nation asc, o.region asc, o.domain asc, chapter asc");
  $ThisNation="XX";
  $ThisRegion="XX";
  $ThisDomain="XX";
  $ThisChapter="XX";
  while( $row=$db->nextRow() ) {
		$ThisGlobe = $row['globe'];
    $ThisNation = $row['nation'];
    if( $ThisRegion == "XX" || 
        ( $ThisRegion != "" && $row['region'] ) ) {
      $ThisRegion = $row['region'];
    }
    if( $ThisDomain == "XX" || 
        ( $ThisDomain != "" && $row['domain'] ) ) {
      $ThisDomain = $row['domain'];
    }
    if( $ThisChapter == "XX" || 
        ( $ThisChapter != "" && $row['chapter'] ) ) {
      $ThisChapter = $row['chapter'];
    }
  }
  if( $_SESSION['super_user'] ) {
	  $ThisGlobe = "";
    $ThisNation = "";
    $ThisRegion = "";
    $ThisDomain = "";
    $ThisChapter = "";
  } 


  $pagetitle = "Organization List";
  include_once("header.inc");
  include_once("titlebar.php");

  if ($ThisChapter == "") {
	  echo("<form action=\"ModifyOrgListAddOrg.php\" method=\"post\">\n");
		echo("<input type=\"hidden\" name=\"globe\" value=\"Camarilla\">\n");
    echo("<table class=\"data\">\n");
    echo("<tr>\n");
    echo("<th align=\"center\">Top Org</th>\n");
    echo("<th align=\"center\">High Org</th>\n");
    echo("<th align=\"center\">Mid Org</th>\n");
    echo("<th align=\"center\">Low Org</th>\n");
    echo("<th align=\"center\">Org Name</th>\n");
    echo("</tr>\n");
    echo("<tr>\n");
    echo("<td>\n");
    if ($ThisNation!="" && !$_SESSION["super_user"]) {
      echo("$ThisNation");
      echo("<input type=\"hidden\" name=\"nation\" value=\"$ThisNation\">\n");
    } else {
      echo("<input type=\"text\" size=\"7\" name=\"nation\" value=\"\">\n");
    }
    echo("</td>\n");
    echo("<td>\n");
  	if ($ThisRegion!="" && !$_SESSION["super_user"]) {
  	  echo("$ThisRegion");
  	  echo("<input type=\"hidden\" name=\"region\" value=\"$ThisRegion\">\n");
  	} else {
  	  echo("<input type=\"text\" size=\"7\" name=\"region\" value=\"\">\n");
  	}
		echo("</td>\n");
		echo("<td>\n");
    if ($ThisDomain!="" && !$_SESSION["super_user"]) {
      echo("$ThisDomain");
      echo("<input type=\"hidden\" name=\"domain\" value=\"$ThisDomain\">\n");
    } else {
      echo("<input type=\"text\" size=\"7\" name=\"domain\" value=\"\">\n");
    }
    echo("</td>\n");
    echo("<td>\n");
    if ($ThisChapter!="" && !$_SESSION["super_user"]) {
      echo("$ThisChapter");
      echo("<input type=\"hidden\" name=\"chapter\" value=\"$ThisChapter\">\n");
    } else {
      echo("<input type=\"text\" size=\"7\" name=\"chapter\" value=\"\">\n");
    }
    echo("</td>\n");
    echo("<td>\n");
    echo("<input type=\"text\" size=\"27\" name=\"orgName\">\n");
    echo("</td>\n");
    echo("</tr>\n");
    echo("<tr>\n");
    echo("<th colspan=\"2\">City</th>\n");
    echo("<th>State</th>\n");
    echo("<th>Country</th>\n");
    echo("<th>Storyteller / Officer Email</th>\n");
    echo("</tr>\n");
    echo("<tr>\n");
    echo("<td colspan=\"2\"><input type=\"text\" size=\"20\" name=\"city\"></td>\n");
    echo("<td><input type=\"text\" size=\"4\" name=\"state\"></td>\n");
    echo("<td><input type=\"text\" size=\"20\" name=\"country\" value=\"\"></td>\n");
    echo("<td>\n");
    
    // Fix: Ensure admin_org_list is treated as an array before implode()
    $adminOrgListArray = is_array($_SESSION['admin_org_list']) ? $_SESSION['admin_org_list'] : [];
    $ThisAdminOrgList="(" . implode(",", $adminOrgListArray) . ")";
    
    // Build a sub-org ID list from the admin's scope (same approach as ModifyOrgList3.php)
    $subQuery = "select id from organizations";
    if ($ThisNation != "") {
        $subQuery .= " where nation='$ThisNation' ";
        if ($ThisRegion != "") {
            $subQuery .= "AND region='$ThisRegion' ";
        }
        if ($ThisDomain != "") {
            $subQuery .= "AND domain='$ThisDomain' ";
        }
        if ($ThisChapter != "") {
            $subQuery .= "AND chapter='$ThisChapter' ";
        }
    }
    $db->query("$subQuery");
    $subOrgList = array();
    while ($row2 = $db->nextRow()) {
        $subOrgList[] = $row2["id"];
    }
    
    // Query users whose org is in the sub-org list (or all users if empty)
    if (!is_array($subOrgList) || count($subOrgList) == 0) {
        $userQuery = "SELECT u.id FROM users u INNER JOIN `mes-portal`.User pu ON u.ww_number = pu.membershipNumber AND pu.membershipExpiration > NOW()";
    } else {
        $userQuery = "SELECT u.id FROM users u INNER JOIN `mes-portal`.User pu ON u.ww_number = pu.membershipNumber AND pu.membershipExpiration > NOW() WHERE u.org_id in ('" .
                     implode("','", $subOrgList) . "')";
    }
    $res = $db->query($userQuery);
    
    echo("<select name=\"admin_user_id\">\n");
    echo("<option value=\"0\">(none)</option>\n");
    
    $nameList = array();
    while ($row3 = $res->nextRow()) {
        $user_info = $userInfoDAO->getUserInfo(intval($row3['id']));
        $row3['name'] = trim($user_info['name']);
        if ($row3['name'] == 'No Name Set' || $row3['name'] == '') continue;
        $nameList[] = array(strtolower($row3['name']), $row3['id'], $row3['name']);
    }
    
    usort($nameList, function($a, $b) { return strcmp($a[0], $b[0]); });
    
    foreach ($nameList as $myName) {
        echo("<option value=\"$myName[1]\">$myName[2]</option>\n");
    }
    
    echo("</select>\n");
    echo("<input type=\"text\" size=\"20\" name=\"email\" value=\"\">\n");
    echo("</td>\n");
    echo("</tr>\n");

    echo("<tr>\n");
    echo("<td colspan=\"7\" align=\"center\">\n");
    echo("<input type=\"submit\" value=\"Enter New Organization Information\">\n");
    echo("</td>\n");
    echo("</tr>\n");
    echo("</table>\n");
    echo("</form>\n");
  }

  // Determine show all state
  $show_all = isset($_GET["showall"]) && $_GET["showall"] == 1;
?>
<?php if( $show_all ) { ?>
<div><a href="ModifyOrgList1.php">Show only active organizations.</a></div>
<?php } else { ?>
<div><a href="ModifyOrgList1.php?showall=1">Show inactive organizations.</a></div>
<?php } ?>

<?php
//  echo("<form action=\"ModifyOrgList2.php\" method=\"post\">\n");
  echo("<table class=\"data\">\n");
  echo("<tr>\n");
  echo("<th align=\"center\">Top Org</th>\n");
  echo("<th align=\"center\">High Org</th>\n");
  echo("<th align=\"center\">Mid Org</th>\n");
  echo("<th align=\"center\">Low Org</th>\n");
  echo("<th align=\"center\">Org Name</th>\n");
  echo("<th align=\"center\">Storyteller</th>\n");
  echo("<th align=\"center\">Location</th>\n");
  echo("<th align=\"center\">Active</th>\n");
  // Show Del/Mod columns if user has any admin permissions
  if (isset($_SESSION['super_user']) || 
      !empty($_SESSION['org_admin_user_list']) || 
      is_array($_SESSION['admin_org_list'])) {
  	echo("<th align=\"center\">Del</th>\n");
	echo("<th align=\"center\">Mod</th>\n");
  }
  echo("</tr>\n");

  $query = "select * from organizations";
  if( !$show_all ) {
      $query .= " WHERE active";
  }
  $query .= " order by active desc, nation, region, domain, chapter";
  $db->query($query);
  $orgRows = $db->getAllRows();

  $numdone=0;
  foreach( $orgRows as $row ) {
    $numdone++;
    if ($numdone % 2 == 0) {
			echo("<tr>\n");
    } else {
  		echo("<tr class=\"dark\">\n");
    }

    echo("<td class=\"normalsmall\">\n");
    if ($row["region"]!="") { 
			echo("<font color=\"#A0A0A0\">\n"); 
		}
    echo("&nbsp;$row[nation]");
    echo("</font>\n");
    echo("</td>\n");
    echo("<td class=\"normalsmall\">\n");
    if ($row["domain"]!="") { echo("<font color=\"#A0A0A0\">\n"); }
    echo("&nbsp;$row[region]");
    echo("</font>\n");
    echo("</td>\n");
    echo("<td class=\"normalsmall\">\n");
    if ($row["chapter"]!="") { echo("<font color=\"#A0A0A0\">\n"); }
    echo("&nbsp;$row[domain]");
    echo("</font>\n");
    echo("</td>\n");
    echo("<td class=\"normalsmall\">\n");
    echo("&nbsp;$row[chapter]");
    echo("</td>\n");
    echo("<td class=\"normalsmall\" align=\"center\">\n");
    echo("&nbsp;$row[org_name]");
    echo("</td>\n");
    echo("<td class=\"normalsmall\" align=\"center\">\n");
    $st_name = "(none)";
    if (!empty($row['admin_user_id']) && $row['admin_user_id'] != 0 && is_numeric($row['admin_user_id'])) {
        $st_info = $userInfoDAO->getUserInfo(intval($row['admin_user_id']));
        $st_name = $st_info['name'];
    }
    echo("&nbsp;" . htmlspecialchars($st_name));
    echo("</td>\n");
    echo("<td class=\"normalsmall\" align=\"right\">\n");
    echo("&nbsp;$row[city], $row[state], $row[country]");
    echo("</td>\n");
    echo("<td class=\"normalsmall\" align=\"center\">\n");
    echo($row['active'] ? "YES" : "NO");
    echo("</td>\n");

    // Check if user has permission to edit/delete this organization
    $orgId = $row["id"];
    $isEditable = (isset($_SESSION['super_user']) || 
                   (isset($_SESSION['org_admin_user_list'][$orgId])));

    // Only show individual Del/Mod cells if admin column headers are displayed
    if (isset($_SESSION['super_user']) || 
        !empty($_SESSION['org_admin_user_list']) || 
        is_array($_SESSION['admin_org_list'])) {
        echo("<td class=\"normalsmall\" align=\"center\">\n");
    	if( $isEditable ) {
  			  $org_name = str_replace("'","'",$row["org_name"]);
    			//echo("<input type=\"checkbox\" name=\"delete[]\" value=\"$row[id]\" onClick=\"return confirm('Are you sure you wish to delete $org_name?')\">\n");
    			echo('<a href="ModifyOrgList2.php?delete[]='.$row['id'].'" onClick="return confirm(\'Are you sure you wish to delete '.$org_name.'?\')"><img width="16" height="16" src="images/trash.png"/></a>'."\n");
    		} else {
    		 	echo("&nbsp;");
    		}
    		echo("</td>\n");
    
        echo("<td class=\"normalsmall\" align=\"center\">\n");
        if( $isEditable ) {
           //echo("<input type=\"radio\" name=\"modify\" value=\"$row[id]\" onClick=\"submit();\">\n");
            	echo('<a href="ModifyOrgList3.php?modify='.$row['id'].'""><img width="16" height="16" src="images/edit.png"/></a>'."\n");
        } else {
           echo("&nbsp;");
        }
      	echo("</td>\n");
    }
 		echo("</tr>\n");	
  }
/*  if($db->numRows()>0 && is_array($_SESSION["admin_org_list"])) {
    echo("<tr>\n");
    echo("<th colspan=\"8\" align=\"center\">\n");
    echo("<input type=\"submit\" value=\"Enter Changes\">\n");
    echo("</th>\n");
    echo("</tr>\n");
  }
*/
?>

</table>
<?php /*</form>*/ ?>
<?php
  include_once("footerbar.inc")
?>