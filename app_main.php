<?php
	include_once("db.inc");

	$pagetitle = "Modern Enigma Society Approvals System";
	include_once("header.inc");
	include_once("titlebar.php");

	$filterDAO = $daoFactory->getFilterDAO();
	$filters = new Filter();
	if ( !array_key_exists( 'reset', $_GET ) ) {
		$prefs_modified = $filters->mergeArray($_GET);

		if ( $prefs_modified ) {
			$filterDAO->deleteByUserID( $_SESSION['user_id'] );
			$filterDAO->create( $filters, $_SESSION['user_id'] );
		} else {
			$filters->mergeArray( $filterDAO->readByUserID( $_SESSION['user_id'] ) );
		}
	} else {
		$filterDAO->deleteByUserID($_SESSION['user_id']);
		$filterDAO->create( $filters, $_SESSION['user_id']);
	}

	if ( !empty( $filters->org_id ) && $filters->suborgs == 1 ) {
		$organizationDAO = $daoFactory->getOrganizationDAO();
		$organization = $organizationDAO->readById($filters->org_id);
		$org_list = $organizationDAO->readSubOrganizationIds($organization);
	} else {
		$org_list = array( $filters->org_id ) ;
	}

	$applicationDAO = $daoFactory->getApplicationDAO();
	$app_ids = $applicationDAO->readApplicationIDsByFilters( $filters, $_SESSION['user_id'], $_SESSION['admin_org_venue_list'], $_SESSION['admin_vss_list'], $_SESSION['last_login_date'], $org_list );

	$vssDAO = $daoFactory->getVSSDao();
	$vsss = $vssDAO->readVSSsById( $_SESSION['admin_vss_list'] );

	// If a venue is selected, only show VSSs matching that venue
	if ( !empty( $filters->venue ) ) {
		$vsss = array_filter( $vsss, function($v) use ($filters) {
			return $v['venue_id'] == $filters->venue;
		} );
	}

	// Fetch the Venues, etc...
	$venueDAO = new VenueDAO( $db );
	$venues = $venueDAO->getVenueOptions();

	$categories = array();
	$result = $db->query( "SELECT distinct category from categories ORDER BY category" );
	while( $row = $result->nextRow() ) {
	  $categories[] = $row['category'];
	}

  $statuses = array(
    'Approved',
    'Denied',
    'Pending Low',
    'Pending Mid',
    'Pending High',
    'Pending Top',
    'Pending Global',
    'Removed'
  );
	$organizationDAO=$daoFactory->getOrganizationDAO();
	$organizations = $organizationDAO->readOrganizationsByIDs( $_SESSION['admin_org_list']);

	if ( isset($_GET['message']) && $_GET["message"]=="appdeleted" ) {
		echo("<div align=center class=normalbold>Application Successfully Deleted</div>");
	}
?>
<div class="table-scroll">
<form action="app_main.php" method="GET">
<table class="data">
<?php
  if ( !empty( $filters->sortorder ) ) {
    echo "<input type='hidden' name='sortorder' value='$filters->sortorder'>\n";
    if ( !empty( $filters->inverted ) ) {
      echo "<input type='hidden' name='inverted' value='1'>\n";
    }
  }
 ?>
  <tr>
    <td align="center">
      <font class="normalbold">Venue:</font>
      <select name="venue">
        <option value="">(Unconstrained)</option>
<?php
  foreach ( $venues as $venue ) {
    $selected = ( $venue["id"] == $filters->venue ) ? " SELECTED" : "";
    echo "<option value=\"{$venue["id"]}\"$selected>{$venue["venue"]}</option>\n";
  }
 ?>
      </select>
    </td>
    <td align="center">
      <font class="normalbold">Category:</font>
      <select name="category">
        <option value="">(Unconstrained)</option>
<?php
  foreach ( $categories as $category ) {
    $selected = ( $category == $filters->category ) ? " SELECTED" : "";
    echo "<option value='$category'$selected>$category</option>\n";
  }
 ?>
      </select>
    </td>
    <td align="center">
      <font class="normalbold">Search for:</font>
      <input type="text" size="35" value="<?php echo stripslashes($filters->search)?>" name="search">
    </td>
  </tr>
  <tr>
    <td align="center">
      <font class="normalbold">Status:</font>
      <select name="status">

<?php
  echo "<option value=\"unconstrained\"";
  if ( is_null( $filters->status )  ) {
    echo " selected";
  }
  echo ">(Unconstrained)</option>\n";
  echo "<option value=\"open\"";
  if ( $filters->status == "open" ) {
    echo " selected";
  }
  echo ">Open</option>\n";
  foreach ( $statuses as $status ) {
    if ( $status == $filters->status ) {
      $selected = " selected=1";
    } else {
      $selected = "";
    }
    echo "<option$selected value=\"$status\">$status</option>\n";
  }
 ?>
      </select>
    </td>
    <td align="center">&nbsp;
    </td>

    <td align="center">
      <font class="normalbold">Modified since my last visit
<?php
  echo "<input type=\"checkbox\" name=\"recentmod\"" .
       ( ( $filters->recentmod == 1) ? " checked" : "" ) . " value=1>\n";
 ?>
      <br>or in last
      <input type="text" name="modinlast" value="<?php echo $filters->modinlast?>" size="3">
      days</font>
    </td>
  </tr>
  <tr>
    <td align="center" colspan=2>
      <div class="normalbold">Affiliation:
      <select name="org_id">
        <option value="">(Unconstrained)</option>
<?php
  foreach ( $organizations as $organization ) {
    $selected = ( $organization["id"] == $filters->org_id ) ? " SELECTED" : "";
    echo "<option value='${organization["id"]}'$selected>";
    if ( !empty( $organization["chapter"] ) ) {
      echo str_repeat("&nbsp;",9)."&#149; ".$organization["chapter"];
      echo "(".$organization["org_name"].")";
    } else if ( !empty( $organization["domain"] ) ) {
      echo str_repeat("&nbsp;",6)."&#149; ".$organization["domain"];
      echo "(".$organization["org_name"].")";
    } else if ( !empty( $organization["region"] ) ) {
      echo str_repeat("&nbsp;",3)."&#149; ".$organization["region"];
      echo "(".$organization["org_name"].")";
    } else if ( !empty( $organization["nation"] ) ) {
      echo "&#149; ".$organization["nation"]."(".$organization["org_name"].")";
    } else if ( !empty( $organization["globe"] ) ) {
			echo "(( ".$organization["globe"]."(".$organization["org_name"].") ))";
		}
    echo "</option>\n";
  }
 ?>
      </select></div>
      <div class="normalbold">Include sub-organizations?
<?php
  echo "<select name=\"suborgs\">";
  echo "<option value=\"1\"". (($filters->suborgs == 1) ? " selected" : "") .">Yes</option>\n";
  echo "<option value=\"0\"". (($filters->suborgs == 0) ? " selected" : "") .">No</option>\n";
  echo "</select>";
 ?>
			</div>
      <div class="normalbold">VSS:
      <select name="vss_id">
        <option value="">(Unconstrained)</option>
<?php
  foreach ( $vsss as $vss ) {
    $selected = ( $vss["id"] == $filters->vss_id ) ? " SELECTED" : "";
    echo "<option value='{$vss["id"]}'$selected>".$vss["name"]."</option>\n";
  }
 ?>
      </select></div>

    </td>
    <td align="center">
      <div class="normalBold">Approval Required:</div>
      <select name='required_approval[]' multiple size='5'>
        <option<?php if( is_array($filters->required_approval) && ( count($filters->required_approval) == 0 || in_array("",$filters->required_approval)) ) { ?> selected="selected"<?php } ?> value="">(Unconstrained)</option>
        <option<?php echo is_array( $filters->required_approval ) && ( in_array("Low", $filters->required_approval) )?" selected=\"selected\"":""?>>Low</option>
        <option<?php echo is_array( $filters->required_approval ) && ( in_array("Mid", $filters->required_approval) )?" selected=\"selected\"":""?>>Mid</option>
        <option<?php echo is_array( $filters->required_approval ) && ( in_array("High", $filters->required_approval) )?" selected=\"selected\"":""?>>High</option>
        <option<?php echo is_array( $filters->required_approval ) && ( in_array("Top", $filters->required_approval) )?" selected=\"selected\"":""?>>Top</option>
        <option<?php echo is_array( $filters->required_approval ) && ( in_array("Global", $filters->required_approval) )?" selected=\"selected\"":""?>>Global</option>
      </select>
    </td>
  </tr>
  <tr>
    <td align="center" colspan="3">
      <a href="app_main.php?reset=1">Reset Filter And Sort</a>
      <input type="submit" value="Find Applications" />
    </td>
  </tr>
</table>
</form>
</div>


<div style="text-align:right; margin: 0 1%;">
<?php
    $recordcount = count( $app_ids );
    echo($recordcount ." Application");
    if( $recordcount!=1 ) {
      echo("s");
    }
    echo(" found");
  ?>
</div>
<div class="table-scroll">
<table class="listing">
  <tr>
<?php
  $to_string = "app_main.php?";
  foreach ( $filters as $key => $value ) {
    if ( is_array( $value ) ) {
      $to_string .= $key . "=" . implode( ",", $value ) . "&";
    } else {
      $to_string.=$key ."=". $value ."&";
    }
  }
  $to_string = preg_replace( "/sortorder=[^&]+&/", "",  $to_string );
  $to_string = preg_replace( "/inverted=1&/", "", $to_string );
  $sortorder = $filters->sortorder;
  $inverted = $filters->inverted;

  echo "<th width=\"20%\" align=\"center\" style=\"white-space:nowrap\"";
  if ( $sortorder == "item" ) {
    echo " class=\"sort\"";
  }
  echo "><a href='{$to_string}sortorder=item&";
  if ( empty( $inverted ) ) { echo "inverted=1&"; }
  echo "'>Item</a></th>\n";

  echo "<th width='20%' valign=\"bottom\" align='center' style=\"white-space:nowrap\"";
  if ( $sortorder == "appnumber" ) {
    echo " class=\"sort\"";
  }
  echo "><a href='{$to_string}sortorder=appnumber&";
  if ( empty( $inverted ) ) { echo "inverted=1&"; }
  echo "'>Number</a></th>\n";

  echo "<th width='10%' valign=\"bottom\" align=\"center\" style=\"white-space:nowrap\"";
  if ( $sortorder == "player" ) {
    echo " class=\"sort\"";
  }
  echo "><a href='{$to_string}sortorder=player&";
  if ( empty( $inverted ) ) { echo "inverted=1&"; }
  echo "'>Player</a></th>\n";

  echo "<th width='10%' valign=\"bottom\" align='center' style=\"white-space:nowrap\"";
  if ( $sortorder == "venue" ) {
    echo " class=\"sort\"";
  }
  echo "><a href='{$to_string}sortorder=venue&";
  if ( empty( $inverted ) ) { echo "inverted=1&"; }
  echo "'>Venue</a></th>\n";

  echo "<th width='10%' valign=\"bottom\" align='center' style=\"white-space:nowrap\"";
  if ( $sortorder == "required_approval" ) {
    echo " class=\"sort\"";
  }
  echo "><a href='{$to_string}sortorder=required_approval&";
  if ( empty( $inverted ) ) { echo "inverted=1&"; }
  echo "'>Required Approval</a></th>\n";

  echo "<th width='10%' valign=\"bottom\" align='center' style=\"white-space:nowrap\"";
  if ( $sortorder == "status" ) {
    echo " class=\"sort\"";
  }
  echo "><a href='{$to_string}sortorder=status&";
  if ( empty( $inverted ) ) { echo "inverted=1&"; }
  echo "'>Status</a></th>\n";

  echo "<th width='10%' valign=\"bottom\" align='center' style=\"white-space:nowrap\"";
  if ( $sortorder == "update_date" ) {
    echo " class=\"sort\"";
  }
  echo "><a href='{$to_string}sortorder=update_date&";
  if ( empty( $inverted ) ) { echo "inverted=1&"; }
  echo "'>Last Updated</a></th>\n";
 ?>
  </tr>
<?php
	$popups = array();
	$counter = 0;
	$applicationDAO = $daoFactory->getApplicationDAO();
	foreach ( $app_ids as $app_id ) {
		$appRowData = $applicationDAO->readApplicationRowDataById( $app_id );
	    $statusage = floor( ( time() - $appRowData->status_change_date ) / 86400 );
	    if ( $statusage > 30 ) {
	      $statusclass="old";
	    } else if ( $statusage > 14 ) {
	      $statusclass="stale";
	    } else {
	      $statusclass="fresh";
	    }
	    $appurl = "AppDetails.php?id=$app_id&";
		if ( !empty( $filters->recentmod ) ) { $appurl .= "showrecentdetails=1&"; }
		if( ++$counter % 2 ) {
			$rowclass="dark";
			echo "<tr class=\"dark\">\n";
		} else {
			$rowclass="";
			echo "<tr>\n";
		}

		$app_detail_popup = $appRowData->character_name . "<br />";
		$app_detail_popup .= $appRowData->character_type;
		$app_detail_popup = addslashes( $app_detail_popup );
		$popups[] = array(
			"selector" => ".application_{$app_id}",
			"title"    => $app_detail_popup
		);
		echo "<td valign=\"top\" class=\"application_{$app_id}\"><a href='$appurl'>".$appRowData->description;
		echo "</a></td>\n";
		echo "<td valign=\"top\" class=\"application_{$app_id}\"><a href=\"$appurl\">".$appRowData->app_number."</a>&nbsp;</td>\n";
		echo "<td valign=\"top\">".userInfoPopup($appRowData->user_id)."</td>\n";
		echo "<td valign=\"top\">".$appRowData->venue."&nbsp;</td>\n";
		echo "<td valign=\"top\">".$appRowData->required_approval."&nbsp;</td>\n";
		echo "<td valign=\"top\" class='$statusclass$rowclass'><b>".$appRowData->status.
			"</b> for <b>$statusage</b> day(s) &nbsp;</td>\n";
		echo "<td valign=\"top\">". strftime( '%D<br>%T', $appRowData->update_date ) . "</td>\n";
		echo "</tr>\n";
  }
  ?>
</table>
</div>

<script type="text/javascript">
	$(function() {
	<?php
		foreach( $popups as $popup ) {
			echo "\t\t\$(\"{$popup['selector']}\").tooltip({showURL:false,delay:0,track:true,bodyHandler:function(){return '{$popup['title']}';}});\n";
		}
	?>
	});
</script>
<?php
	include_once( 'footerbar.inc');
