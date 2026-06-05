<?php
  include_once("db.inc");
  $pagetitle="Event Details";
  include_once("header.inc");
  include_once("titlebar.php");
  $id = $_GET['id'];
  if( $id != "" ) {
    $db->query("SELECT e.*, e.vss_id as vss_id, v.name as vss_name, ".
		           "o.org_name as org_name, unix_timestamp(e.event_date) as event_date ".
               "FROM events e ".
							 "LEFT JOIN vsss v on e.vss_id=v.id ".
							 "LEFT JOIN organizations o on -e.vss_id=o.id ".
               "WHERE e.id=$id");
    $detailrow=$db->nextRow();
  } else {
    $detailrow=array();
    $detailrow["event_date"] = strtotime("Friday");
  }
  
  $orgs = array();
  $vsss = array();
  $myvss_list = array();
  
  if( is_array( $_SESSION['admin_final_authority_list'] ) ) {
    $org_result=$db->query("SELECT -id as id, org_name as name from organizations where id in (".implode(",",$_SESSION['admin_final_authority_list']).")");
    while( $row = $org_result->nextRow() ) {
      $orgs[] = $row;
      $myvss_list[]=$row["id"];
    }
  }
  
  $vss_result=$db->query("SELECT id, name from vsss where storyteller_id=$_SESSION[user_id]");
  while( $row = $vss_result->nextRow() ) {
    $vsss[] = $row;
    $myvss_list[]=$row["id"];
  }
  
  
  if ( isset($_GET['mode']) ) {
    $mode=$_GET['mode'];
  } else {
    $mode="display";
  }
  if( $mode != "Add" && ( $id == 0 || $id == "" ) ) {
    header("Location: EventsMain.php?message=NoSuchEvent");
    exit();
  }
  if ( $mode!="display" ) {
     echo('<form method="POST" action="EventsDetailsRecordAction.php">');
  } else {
     echo("<form method=\"POST\" action=\"EventsDetails.php?id=$id&mode=Edit&\">");
  }

 ?>

<input type="hidden" name="id" value="<?php echo $id?>">
<input type="hidden" name="mode" value="<?php echo $mode?>">
<table class="data">
  <?php 
  
    if( $mode=="display" ) { $buttonValue = "Edit Event"; } else { $buttonValue="Enter Values"; } 
    if( $mode=="Add" || in_array( $detailrow["vss_id"], $myvss_list ) ) {
  ?>
    
  <tr>
    <th colspan="3" align="center">
        <input type="submit" value="<?php echo $buttonValue?>">
    </th>
  </tr>
   <?php
   }
   ?>
  <tr>
    <th>VSS/Organization</th>
    <th>Event Date</th>
    <th>Event Time</th>
  </tr>
  <tr>
    <td>
        <?php
          if ( $mode=="display" ) {
						 if( $detailrow["vss_id"] > 0 ) {
						 		 echo("&nbsp;" . nl2br($detailrow["vss_name"]));
						 } else {
						 	 	 echo("&nbsp;" . nl2br($detailrow['org_name']));
						 }
          } else {
            echo("<select name=\"vss_id\">");
            foreach( $orgs as $vss_row ) {
              echo("<option value=\"$vss_row[id]\">ORG: $vss_row[name]</option>");
            }
            foreach( $vsss as $vss_row ) {
              echo("<option value=\"$vss_row[id]\">VSS: $vss_row[name]</option>");
            }
            echo("</select>");
          }
        ?>
    </td>
    <td>
        <?php
          if ( $mode=="display" ) {
             echo("&nbsp;" . nl2br(strftime("%D",$detailrow["event_date"]) ));
          } else {
            echo("<input type=\"text\" name=\"event_date\" size=\"17\" value=\"".
                 strftime("%D", $detailrow["event_date"]) . "\">");
          }
        ?>
    </td>
    <td>
        <?php
          if ( $mode=="display" ) {
             echo("&nbsp;" . nl2br($detailrow["event_time"]));
          } else {
            echo("<input type=\"text\" name=\"event_time\" size=\"17\" value=\"$detailrow[event_time]\">");
          }
        ?>
    </td>
  </tr>
  <tr>
    <th colspan="2">Event Title</th>
    <th>Storyteller</th>
  </tr>
  <tr>
    <td colspan="2">
        <?php
          if ( $mode=="display" ) {
             echo("&nbsp;" . nl2br($detailrow["title"]));
          } else {
            echo("<input type=\"text\" name=\"title\" size=\"40\" value=\"$detailrow[title]\">");
          }
        ?>
    </td>
    <td>
        <?php
					if ( $mode=="Add" ) {
						if ($_SESSION["user_id"]==""){
							 echo("You");
						} else {
						  echo(userInfoPopup($_SESSION["user_id"]));
						}
					} else {
						echo(userInfoPopup($detailrow["storyteller_id"]));
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
          if ( $mode=="display" ) {
             echo("&nbsp;" . nl2br($detailrow["description"]));
          } else {
            echo("<textarea rows=\"7\" cols=\"65\" name=\"description\" wrap=\"virtual\">$detailrow[description]</textarea>");
          }
        ?>
    </td>
  </tr>
  <tr>
    <th colspan="3">Location</th>
  </tr>
  <tr>
    <td colspan="3">
        <?php
          if ( $mode=="display" ) {
             echo("&nbsp;" . nl2br($detailrow["location"]));
          } else {
            echo("<textarea rows=\"7\" cols=\"65\" name=\"location\" wrap=\"virtual\">$detailrow[location]</textarea>");
          }
        ?>
    </td>
  </tr>
  <tr><th colspan="3">Associated URL</td></tr>
  <tr>
    <td colspan="3">
        <?php
          if ( $mode=="display" ) {
             if ( $detailrow["url"]=="" ) {
                 echo("<i>(none)</i>");
             } else {
                echo("<a href=\"$detailrow[url]\">$detailrow[url]</a>");
             }
          } else {
            echo("<input type=\"text\" name=\"url\" size=\"57\" value=\"$detailrow[url]\">");
          }
        ?>
    </td>
  </tr>
  <tr><th colspan="3">Comment</th></tr>
  <tr>
    <td colspan="3">
        <?php
          if ( $mode=="display" ) {
             echo("&nbsp;" . nl2br($detailrow["comment"]));
          } else {
            echo("<textarea rows=\"7\" cols=\"65\" name=\"comment\" wrap=\"virtual\">$detailrow[comment]</textarea>");
          }
        ?>
    </td>
  </tr>
  <?php
    if( $mode == "Add" || in_array( $detailrow["vss_id"], $myvss_list ) ) {
  ?>

  <tr><th colspan="3">Private Comment</th></tr>
  <tr>
    <td colspan="3">
        <?php
          if ( $mode=="display" ) {
             echo("&nbsp;" . nl2br($detailrow["private_comment"]));
          } else {
            echo("<textarea rows=\"7\" cols=\"65\" name=\"private_comment\" wrap=\"virtual\">$detailrow[private_comment]</textarea>");
          }
        ?>
    </td>
  </tr>
  <tr>
    <th colspan="3" align="center">
        <input type="submit" value="<?php echo $buttonValue?>">
    </th>
  </tr>
  <?php
}
if( $detailrow["event_date"]>time() && $mode == "Edit") { 
   ?>
    <tr>
      <th>
          <input type="submit" value="Delete Event" name="delete">
      </th>
      <th class="normalbold" align="right">
          Confirm Permanent Deletion <input type="checkbox" name="ConfirmDelete">
      </th>
    </tr>
  <?php } ?>
</table>
</form>

<?php 
if( $mode == "display" ) {
 ?>
<div align="center" class="normalsmall"><a href="EventsMain.php">Back to Event Listing</a></div>   
 <?php
}

include_once("footerbar.inc"); ?>
