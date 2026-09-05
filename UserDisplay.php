<?php
include_once("db.inc");

// Centralized session start
$pagetitle = "User Information";
$user_id = isset( $_GET["id"] ) ? $_GET["id"] : $_SESSION["user_id"];
if ( isset( $_GET["mode"] ) ) {
  $mode = $_GET["mode"];
} else if ( isset( $_POST["mode"] ) ) {
  $mode = $_POST["mode"];
} else {
  $mode = "edit";
}
if ( isset( $_GET["return"] ) ) {
  $thisReturn = $_GET["return"];
} else if ( isset( $_POST["return"] ) ) {
  $thisReturn = $_POST["return"];
}

  if ( isset($_GET["action"]) && $_GET["action"]=="DeleteST" ) {
    if ( !isset($_SESSION['user_id']) ) {
      header("Location: index.php");
      exit;
    }
    if ( $_GET['venue_id'] == '' ) {
      $_GET['venue_id'] = 0;
    }
    $db->query(
      "DELETE from storytellers where user_id=? and organization_id=? and venue_id=?",
      [$_GET['id'], $_GET['organization_id'], $_GET['venue_id']]
    );
    header( "Location: UserDisplay.php?id=$_GET[id]" );
    exit;
  }

  if ( $mode == "doAdd" || $mode == "doEdit" ) {
    include_once( "UserDisplayRecordAction.php" );
    $row = array();
    $row["id"] = isset( $_POST["id"] ) ? $_POST["id"] : 0;
    $user_info = $userInfoDAO->getUserInfo($row['id']);
    $row["name"] = $user_info['name'];
    $row["phone_number"] = isset($user_info['phone_number']) ? $user_info['phone_number'] : '';
    $row["email"] = $user_info['email'];
    $row["username"] = "No Longer Used";
    $row["ww_number"] = $user_info['ww_number'];
    $row["org_id"] = $_POST["org_id"];
  } else {
    $user_info = $userInfoDAO->getUserInfo($user_id);
    $row = array();
    $row["id"] = $user_id;
    $user_info = $userInfoDAO->getUserInfo($user_id);
    $row["name"] = $user_info['name'];
    $row["phone_number"] = isset($user_info['phone_number']) ? $user_info['phone_number'] : '';
    $row["email"] = $user_info['email'];
    $row["username"] = "No Longer Used";
    $row["ww_number"] = $user_info['ww_number'];
    $row["org_id"] = $user_info['org_id'];

  }
/*if($user_info && $user_info['org_id'] != $_SESSION['org_id']){
	header('Location: index.php');
	exit;
}*/
  if ( $mode != "add" &&
       $mode != "doAdd" &&
       isset( $_SESSION['user_id'] ) &&
       isset( $row["id"] ) && (
         (
           is_array( $row["org_id"] ) &&
           !in_array( $row["org_id"], $_SESSION["admin_org_list"]
         ) ||
         !is_array( $_SESSION["admin_org_list"] ) ) &&
         $_SESSION['user_id'] != $user_id
       )
     ) {
    header("Location: UserList.php");
  }

  function showField( $fieldname ) {
    global $mode, $_SESSION, $row, $_SESSION;
    $rowvalue = $row[$fieldname];
    echo "$rowvalue\n";
  }

   if ( $mode == "add" || $mode == "doAdd" ) {
    $IGNORE_LOGIN = 1;
  }
  include_once("header.inc");
  include_once("titlebar.php");

  if ( isset( $errmsgs ) ) {
    echo "<div class=\"error\">\n";
    foreach ( $errmsgs as $errmsg ) {
      echo "<div>$errmsg</div>\n";
    }
    echo "</div>\n";
  }
 echo "<form action=\"$_SERVER[PHP_SELF]\" method=\"POST\">\n";
  if ( $mode == "add" || $mode == "doAdd" ) {
    echo "<input type=\"hidden\" name=\"mode\" value=\"doAdd\">\n";
  } else if ( $mode == "edit" || $mode == "doEdit" ) {
    echo "<input type=\"hidden\" name=\"mode\" value=\"doEdit\">\n";
    echo "<input type=\"hidden\" name=\"id\" value=\"$row[id]\">\n";
  }
  echo "<input type=\"hidden\" value=\"\$thisReturn\" name=\"Return\">\n";
  echo "<table class=\"data\">\n";
 ?>
  <tr>
    <td colspan=2>To edit profile information, please visit the <a href=http://portal.modernenigmasociety.org/>Member Portal</a></td>
  </tr>
<?php if(!$_SESSION['org_id']){ ?>  <tr>
    <td colspan=2 style="text-align:center;color:red;font-size:30px">Please set your Organization so your Approvals will be properly received</td>
  </tr><?php } ?>
  <tr>
    <th>Name:</th>
    <th>Phone Number:</th>
  </tr>
  <tr>
    <td>
<?php
  showField("name");
 ?>
    </td>
    <td>
<?php
  showField("phone_number");
 ?>
    </td>
  </tr>
  <tr>
    <th>Email Address:</th>
    <th>Username:</th>
  </tr>
  <tr>
    <td>
<?php
  showField("email");
 ?>
    </td>
    <td>
<?php
  echo "$row[username]\n";
 ?>
    </td>
  </tr>
  <tr>
    <th>Member Number (Remember your country code, and no dashes):</th>
    <th <?php if(!$_SESSION['org_id']) echo "style='color:red'"; ?>>Member Of: (Please be as specific as possible)</th>
  </tr>
  <tr>
    <td>
<?php
  showField("ww_number");
 ?>
    </td>

<?php

  if( is_array( $_SESSION["admin_org_list"] ) ) {
    $db->query("select * from organizations where id in ('" . implode("','",$_SESSION["admin_org_list"]) . "') and active = 1 and (nation!='USA' or chapter='' or chapter is null) order by nation, region, domain, chapter");
  } else {
    $db->query("select * from organizations where 1=0");
  }
  $admin_orgs=array();
  while ($row2=$db->nextRow()) {
    $admin_orgs[] = $row2;
  }

  $db->query("select * from organizations where active = 1 order by nation, region, domain, chapter");
  $orgs=array();
  while ($row2=$db->nextRow()) {
    $orgs[] = $row2;
  }

  $db->query("select org_name from organizations where ID=?", [$row['org_id']]);
  while( $row2=$db->nextRow() ) {
    $ThisOrgName=$row2["org_name"];
  }
?>

    <td>
<?php
  if (
       $mode == "display" || (
         isset($_SESSION['user_id']) && !(
          $_SESSION["super_user"] ||
          (isset($_SESSION["admin_level"]) && $_SESSION["admin_level"] == "region") ||
          (isset($_SESSION["admin_level"]) && $_SESSION["admin_level"] == "nation") ||
          (isset($_SESSION['admin_level']) && $_SESSION['admin_level'] == "globe")
         ) &&
         $row['id']!=$_SESSION['user_id'] &&
         $mode!="add"
       )
     ) {
    echo("$ThisOrgName");
    echo("<input type=\"hidden\" name=\"org_id\" value=\"$row[org_id]\">");
  } else {
    $ThisOrgID=$row["org_id"];
    echo("<select name=\"org_id\">");
    foreach ($orgs as $org) {
      echo("<option value=\"$org[id]\"");
      if ($org["id"]==$ThisOrgID) {
        echo(" selected");
      }
      echo(">");
      if ($org["chapter"]!="") {
        echo("&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&#149;&nbsp;$org[chapter] ($org[org_name])");
      } else if ($org["domain"]!="") {
        echo("&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&#149;&nbsp;$org[domain] ($org[org_name])");
      } else if ($org["region"]!="") {
        echo("&nbsp;&nbsp;&nbsp;&#149;&nbsp;$org[region] ($org[org_name])");
      } else if ($org["nation"]!="") {
        echo("$org[nation] ($org[org_name])");
      } else {
			  echo("(select your organization)");
			}
      echo("</option>");
    }
    echo("</select>");
  }
?>
    </td>
  </tr>
</table>
<?php
if ( $mode!="add" ) {
?>
  <table class="data">
    <tr>
      <th>Storyteller For</th>
      <th>Venue</th>
      <th>Assistant?</th>
    </tr>
<?php
    $query = "SELECT s.id, s.name, v.venue ".
            "FROM vsss s ".
           "LEFT JOIN venues v ON s.venue_id = v.id ".
           "WHERE storyteller_id = ?";
    $db->query( $query, [$row['id']] );
    while( $vss = $db->nextRow() ) {
      print( "<tr>\n" );
      print( "<td>VSS: <a href='VSSDetails.php?id=$vss[id]&'>$vss[name]</a></td>\n");
      print( "<td>$vss[venue]</td>\n");
      print( "<td>&nbsp;</td>\n");
    }
    $db->query("select o.id as organization_id, v.id as venue_id, v.venue, o.org_name as org_name, o.globe, o.nation, o.region, o.domain, o.chapter, s.assistant ".
               "from storytellers s left join ".
               "organizations o on s.organization_id=o.id left join ".
               "venues v on s.venue_id=v.id left join ".
               "users u on s.user_id=u.id ".
               "where s.user_id=?", [$row['id']]);
    while ( $row2=$db->nextRow() ) {
      if ( $row2["globe"]!="" ) {
        print( "<tr>\n" );
        print( "<td>\n" );
        print( "$row2[org_name]" );
        if ( $row2["chapter"]!="" ) {
           print( "($row2[chapter])" );
        } else if ( $row2["domain"]!="" ) {
           print( "($row2[domain])" );
        } else if ( $row2["region"]!="" ) {
           print( "($row2[region])" );
        } else if ( $row2["nation"]!="" ) {
           print( "($row2[nation])" );
        } else if ( $row2["globe"]!="" ) {
           print( "($row2[globe])" );
        }
        if ( $mode=="edit" && in_array($row2["organization_id"],$_SESSION["admin_org_list"]) ) {
            print( "&nbsp; <a href=\"UserDisplay.php?organization_id=$row2[organization_id]&venue_id=$row2[venue_id]&action=DeleteST&id=$row[id]\">Delete</a>" );
        }
        print( "</td>\n" );
        print( "<td>" );
        if ( $row2["venue"]=="" ) {
           print("<i>none</i>");
        } else {
           print("$row2[venue]");
        }
        print( "</td>\n" );
        print( "<td>" );
        if ( $row2["assistant"]=="1" ) {
           print("Yes");
        } else {
           print("No");
        }
        echo("</td></tr>");
      }
    }

    if ( $mode=="edit" || $mode=="doEdit" ) {
      print( "<tr>\n" );
      print( "<td>\n" );
      $thisAdminOrgId = isset($row['admin_org_id']) ? $row['admin_org_id'] : 0;
      if ($thisAdminOrgId) {
          $db->query("select org_name from organizations where ID='$thisAdminOrgId'");
          $row2=$db->nextRow();
          $ThisOrgName = isset($row2['org_name']) ? $row2['org_name'] : '';
      } else {
          $ThisOrgName = '';
          $row2 = array('org_name' => '');
      }

      echo("<select name=\"admin_org_id\">");
      echo("<option value=\"0\">(None)</option>");
      foreach ($admin_orgs as $admin_org) {
        echo("<option value=\"$admin_org[id]\">");
        if ($admin_org["chapter"]!="") {
          echo("&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&#149;&nbsp;$admin_org[chapter] ($admin_org[org_name])");
        } else if ($admin_org["domain"]!="") {
          echo("&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&#149;&nbsp;$admin_org[domain] ($admin_org[org_name])");
        } else if ($admin_org["region"]!="") {
          echo("&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&#149;&nbsp;$admin_org[region] ($admin_org[org_name])");
        } else if ($admin_org["nation"]!="") {
          echo("&nbsp;&nbsp;&nbsp;&#149;&nbsp;$admin_org[nation] ($admin_org[org_name])");
        } else if ($admin_org["globe"]!="") {
          echo("$admin_org[globe] ($admin_org[org_name])");
        }
        echo("</option>");
      }
      echo("</select>");
      print( "</td>\n" );
      print( "<td>" );
      $db->query("select * from venues where active = 1 and lower(venue) not like '%non venue%' order by venue");
      print( "  <select name=\"venue_id\">\n" );
      print( "  <option value=\"\">(None)</option>\n" );
      while ( $row=$db->nextRow() ) {
          print( "<option value=\"$row[id]\">$row[venue]</option>" );
      }
      print( "</select>" );
      print( "</td>\n" );
      print( "<td>" );
       echo("<input type=\"checkbox\" name=\"assistant\">");

      echo("</td></tr></table>");
    }
  } // if mode != "add"
  if ( $mode!="display" ) {
?>
  <table class="data">
    <tr>
      <th><input type="submit" value="Submit Values"></th>
    </tr>

<?php
  }
  if ( isset($_SESSION['user_id']) && $_SESSION['super_user']=="1" ) {
?>
	<tr>
    <th align="center">
      <input type="checkbox" name="delete" value="1">
      Confirm Delete
    </th>
    <th align="center">
      <input type="submit" name="deleteUser" value="Delete User">
    </th>
  </tr>

<?php
  }
?>

</table>
</form>

<?php
  include_once "footerbar.inc";
?>
