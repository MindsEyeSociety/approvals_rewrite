<?php
	include_once("db.inc");
	$pagetitle="User Listing";
	include_once("header.inc");
	include_once("titlebar.php");
	include_once("services/UserService.class.php");
	include_once("include/pagination.inc");

	$filter = array();

	function getVSTPositionLabel( $vss_id, $vss_name, $vss_venue ) {
		$position = "<div>";
		$position .= "VST&nbsp;for&nbsp;<a href='VSSDetails.php?id=$vss_id&'>$vss_name</a>";
		if( $vss_venue != '' && $vss_venue != 'Non Venue-Specific') {
			$position .= "&nbsp;($vss_venue)";
		}
		$position .= "</div>\n";

		return $position;
	}


	$filter["skip"]   = isset($_GET["skip"])   ? paginationOffset($_GET["skip"]) : 0;
	$filter["sortby"] = isset($_GET["sortby"]) ? $_GET["sortby"] : "Affiliation";
	$filter["search"] = isset($_GET["search"]) ? $_GET["search"] : "";

	$userService = new UserService();
	$total_users = $userService->countMatchingUsers($filter);
	$rows = $userService->fetchMatchingUsers( $filter );

	$user_ids = array();
	for($i=0; $i<count($rows); $i++ ) {
		$user_ids[$rows[$i]["id"]] = $i;
	}
	if( count($user_ids) != 0 ) {
		$query = "SELECT s.user_id, o.nation, o.region, o.domain, o.chapter, o.org_name, v.venue, s.assistant ".
			"FROM storytellers s ".
			"LEFT JOIN organizations o ON o.id = s.organization_id ".
			"LEFT JOIN venues v ON v.id = s.venue_id ".
			"WHERE s.user_id in (". implode(",",array_keys($user_ids)). ")";
		$db->query( $query );
		while( $pdata = $db->nextRow() ) {
			
			$position = isset($rows[$user_ids[$pdata["user_id"]]]["position"]) ? $rows[$user_ids[$pdata["user_id"]]]["position"] : "";
			$position .= "<div>";
			if ( $pdata["assistant"] ) {
				$position .= "Assistant ";
			}
			$position .= "Storyteller for $pdata[org_name]";
			if( $pdata['venue'] != '' ) {
				$position .= "&nbsp;$pdata[venue]";
			}
			$position .= "</div>\n";
			$rows[$user_ids[$pdata["user_id"]]]["position"] = $position;
		}

		$query = "SELECT s.id, s.name, v.venue,s.storyteller_id ".
			"FROM vsss s ".
			"LEFT JOIN venues v ON s.venue_id = v.id ".
			"WHERE storyteller_id in (". implode(",",array_keys($user_ids)). ")";
		$db->query( $query );


		while( $vss = $db->nextRow() ) {
			if ( isset($user_ids[$vss["storyteller_id"]]) ) {
				$row_num = $user_ids[$vss["storyteller_id"]];
				if ( isset($rows[$row_num]) && !isset($rows[$row_num]["position"]) ) {
					$rows[$row_num]["position"] = "";
				}
				$rows[$row_num]["position"] .= getVSTPositionLabel( $vss['id'], $vss['name'], $vss['venue']);
			}
		}
	}

	for($i=0; $i<count($rows); $i++ ) {
		if ( $rows[$i]["super_user"] ) {
			$rows[$i]["position"] .= "<div>Super User</div>\n";
		}
	}

	if (isset($_GET["Message"])) {
		echo("<div class=\"subhead\" align=\"center\">\n\t");
		if ($_GET["Message"]=="UserDeleted") {
			echo("User Deleted Successfully");
		} else if ($_GET["Message"]=="UserModified") {
			echo("User Modified Successfully");
		}
		echo("</div>\n");
	}

 ?>
<form method="GET" action="UserList.php">
	<table class="data filter">
	  <tr>
	    <th align="right">
 <?php
	$page_count=($total_users / 20) + 1; // not sure this is right
	$idx=1;
	$predots = $postdots = 0;
	$basequery = "";
	if( "" != $filter["search"] ) {
		$basequery .= (""==$basequery?"":"&") . "search=". urlencode( $filter["search"]);
	}
	if( "" != $filter["sortby"] ) {
		$basequery .= (""==$basequery?"":"&") . "sortby=". urlencode( $filter["sortby"]);
	}
 	if ( $filter["skip"] > 0 ) {
		echo("<a href=\"UserList.php?$basequery\"><<</a>&nbsp;\n");
		$prevskip=( intval($filter["skip"]/20) - 1 ) * 20 ;
		$prevquery = $basequery . (""==$basequery?"":"&") . "skip=".$prevskip;
		echo("<a href=\"UserList.php?$prevquery\"><</a>\n");
	}
	while ( $idx < $page_count ) {
		$count=($idx-1) * 20;
		if( $count == $filter["skip"] ) {
  		echo("$idx\n");
		} else if ( abs( $count - $filter["skip"] ) <= 100 ) {
			$countquery = $basequery . (""==$basequery?"":"&") . "skip=".$count;
  		echo("<a href=\"UserList.php?$countquery\">$idx</a>\n");
		}
		$idx++;
	}

	if ( $filter["skip"] + 20 < $total_users )	{
		$prevskip=( intval($filter["skip"]/20) + 1 ) * 20 ;
		$prevquery = $basequery . (""==$basequery?"":"&") . "skip=".$prevskip;
		echo("<a href=\"UserList.php?$prevquery\">></a>\n");
		$prevskip=intval($total_users/20) * 20 ;
		$prevquery = $basequery . (""==$basequery?"":"&") . "skip=".$prevskip;
		echo("&nbsp;<a href=\"UserList.php?$prevquery\">>></a>\n");
	}
?>
Search: <input type="text" class="normalmid" name="search" value="<?php echo $filter["search"]?>"></th>
	  </tr>
	</table>
</form>
<table class="data listing">
	<colgroup>
		<col width="20%" />
		<col width="20%" />
		<col width="30%" />
		<col width="10%" />
	</colgroup>
	<tr>
		<th>Name</th>
		<th><a href="UserList.php?sortby=Affiliation&search=<?php echo htmlspecialchars($filter['search'])?>">Affiliation</a></th>
		<th>Position</th>
		<th>&nbsp;</th>
	</tr>
<?php

$counter = 0;
foreach( $rows as $row ) {
	if( ++$counter % 2 == 0 ) {
			echo("<tr class=\"dark\">");
		} else {
			echo("<tr>");
		}
		echo("<td valign=\"top\">$row[name]</td>\n");
		echo("<td valign=\"top\">$row[org_name]</td>\n");
		echo("<td nowrap=1 valign=\"top\">" . ($row["position"] ?? "") . "</td>\n");
		echo("<td valign=\"top\">");
		if ( $_SESSION['super_user'] || in_array( $row["org_id"], $_SESSION['admin_org_list'] ) ) {
			echo("<a href=\"UserDisplay.php?mode=edit&id=$row[id]&Return=UserList.php\">Edit</a>");
		} else {
			echo("<a href=\"UserDisplay.php?mode=display&id=$row[id]\">Profile</a>");
		}
		echo("</td>\n");
		echo("</tr>\n");
	}
?>
</table>
<?php include_once("footerbar.inc"); ?>