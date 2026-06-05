<?php
  include_once("db.inc");
  $pagetitle="Event Listing";
  include_once("header.inc");
  include_once("titlebar.php");


  $vss_query = $db->query("SELECT distinct vss_id FROM characters WHERE user_id=$_SESSION[user_id] AND active=1");
  $watch_query = $db->query("SELECT vss_id FROM vss_subscriptions WHERE user_id=$_SESSION[user_id]");

  $query = "SELECT e.id, e.title, e.event_time, v.id as vss_id, v.name as vss_name, ".
           "o.id as org_id, o.org_name, e.storyteller_id, ".
           "unix_timestamp(e.event_date) as event_date, e.url ".
           "FROM events e ".
					 "LEFT JOIN vsss v on v.id=e.vss_id ".
           "LEFT JOIN organizations o on o.id=-e.vss_id ".
					 "LEFT JOIN users u ON u.org_id=-e.vss_id ".
           "WHERE ( v.storyteller_id = $_SESSION[user_id] ".
					 "OR o.admin_user_id = $_SESSION[user_id] ".
					 "OR u.id = $_SESSION[user_id] ".
					 "OR e.storyteller_id = $_SESSION[user_id] ";
	if( is_array( $_SESSION['admin_org_list'] ) && isset( $_GET["show_all"] ) ) {
	  $auth_list = implode( ",", $_SESSION['admin_org_list'] );
		$query .= "OR -e.vss_id in ($auth_list) ";
	}
  if( is_array( $_SESSION['admin_vss_list'] ) && isset( $_GET["show_all"] ) ) {
    $auth_list = implode( ",", $_SESSION['admin_vss_list'] );
    $query .= "OR e.vss_id in ($auth_list) ";
  }
  if( $vss_query->numRows() > 0 ) {
    $query .= "OR e.vss_id in ( 0 ";
    while( $row = $vss_query->nextRow() ) {
      $query .= "," .$row["vss_id"];
    }
    $query .=" ) ";

  }
  if( $watch_query->numRows() > 0 ) {
    $query .= "OR e.vss_id in ( 0 ";
    while( $row = $watch_query->nextRow() ) {
      $query .= "," .$row["vss_id"];
    }
    $query .=" ) ";

  }
	$query .= " ) ";
	if( !isset( $_GET['show_time_all'] ) ) {
	  $query .= "AND date_sub( now(), interval 7 DAY ) < e.event_date AND date_add( now(), interval 30 DAY ) > e.event_date ";
	}
  $query .= "ORDER BY e.event_date asc";

  $result = $db->query($query);
 ?>

<table class="data">
  <tr>
 <?php
  if ( is_array( $_SESSION['admin_org_list'] ) && count( $_SESSION['admin_org_list'] ) > 0 ) {
		if ( isset($_GET['show_time_all'] ) ) {
			 $extravar2="show_time_all=1&";
		}
    if( isset( $_GET['show_all'] ) ) {
      $to_url = "EventsMain.php?$extravar2";
      $message = "Show only VSSs I participate in";
			$extravar="show_all=1&";
    } else {
      $to_url = "EventsMain.php?show_all=1&$extravar2";
      $message = "Show all VSSs I can view";
    }
?>
			<td class="normalsmall" width="40%">
					<a href="<?php echo $to_url?>"><?php echo $message?></a>
			</td>			
<?php
	} else {
?>
	<td class="normalsmall" width="40%">
		&nbsp;
	</td>	
<?php	
	}
		if ( isset($_GET['show_time_all']) ) {
			 $to_url="EventsMain.php?$extravar";
			 $message="Show only recent/imminent Events";
		} else {
			$to_url="EventsMain.php?show_time_all=1&$extravar";
			$message="Show all events, regardless of time";
		}
?>			
			<td class="normalsmall" align="center" width="20%">
<?php
		if( ( is_array( $_SESSION['admin_final_authority_vss_list'] ) && count( $_SESSION['admin_final_authority_vss_list'] ) > 0 ) || 
	    ( is_array( $_SESSION['admin_final_authority_list'] ) && count( $_SESSION['admin_final_authority_list'] ) > 0 ) ) {
?>
					<a href="EventsDetails.php?mode=Add&">Add New Event</a>
<?php
		 } else {
		 	 echo("&nbsp;");
		 }
?>
			</td>
			<td class="normalsmall" align="right" width="40%">
				<a href="<?php echo $to_url?>"><?php echo $message?></a>	
			</td>
		</tr>
</table>
<table class="data">
  <tr>
    <th align="center">Event</th>
    <th align="center">VSS</th>
    <th align="center">Date</th>
    <th align="center">Storyteller</th>
    <th align="center">Link</th>
  </tr>
<?php
	$counter = 0;
	while( $row = $result->nextRow() ) {
		if( ++$counter % 2 == 0 ) {
			echo("<tr class=\"dark\">");
		} else {
			echo("<tr>");
		}
    echo("<td>&nbsp;<a href='EventsDetails.php?id=$row[id]&'>$row[title]</a></td>\n");
    echo("<td>&nbsp;<a href='VSSDetails.php?id=$row[vss_id]&'>$row[vss_name]</a></td>\n");
    echo("<td>&nbsp;".strftime('%D',$row["event_date"])." $row[event_time]</td>\n");
    echo("<td>&nbsp;".userInfoPopup( $row["storyteller_id"] )."</td>\n");
    echo("<td>&nbsp;");
    if( $row["url"] == "" ) {
      echo("&nbsp;");
    } else {
      echo("<a href='$row[url]'>Link</a>");
    }
    echo("</td>\n");
    echo("</tr>\n");
  }

?>
</table>

<?php include_once("footerbar.inc"); ?>
