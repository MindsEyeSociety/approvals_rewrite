<?php
$mode=$_POST["mode"];
$ww_number = strtoupper( $_POST['ww_number'] ?? '' );
$errmsgs=array();
if ($mode=="doAdd") {
	if ( count($errmsgs)==0 ) {
    $query=
			"insert into users (".
      "  org_id,".
			"  admin_org_id, ".
			"  assistant, ".
			"  last_login_date,".
			"  password_hint".
      ") values (".
			"  ?, ?, ?, now(), ?)";
    $admin_org = isset($_POST["admin_org_id"]) ? $_POST["admin_org_id"] : 0;
    $assistant = isset($_POST["assistant"]) ? 1 : 0;
    $params = [$_POST["org_id"], $admin_org, $assistant, $_POST["password_hint"]];
    $db->query($query, $params);
    $ThisID=$db->getInsertId();

    if (count($errmsgs)==0) {
      if ( !isset($_SESSION['user_id']) || $_SESSION['user_id']==0 ) {
			    $self = "http://" . $_SERVER['SERVER_NAME'] . $_SERVER['PHP_SELF'];
			    if ($_SERVER['QUERY_STRING'] != '') {
			        $self .= "?" . $_SERVER['QUERY_STRING'];
			    }
			    header("Location: index.php");
          exit();
      } else {
         header("Location: UserDisplay.php?id=" . urlencode($ThisID) . "&mode=edit&");
					exit();
    	}
		}
	}
}    elseif ( $mode=="doEdit" ) {
    $query="UPDATE users SET org_id=? WHERE id=?";
    $db->query($query, [$_POST["org_id"], $_POST["id"]]);

    if( $_POST["admin_org_id"] ) {
      if ( ($_POST["venue_id"] ?? null) || ($_POST["assistant"] ?? null) ) {
        $thisassistant=1;
      } else {
        $thisassistant=0;
      }
      $db->query("INSERT into storytellers ".
                 "(organization_id, venue_id, user_id, assistant) ".
                 "values (?, ?, ?, ?)",
                 [$_POST["admin_org_id"], $_POST["venue_id"], $_POST["id"], $thisassistant]);
      if( !$thisassistant ) {
        $db->query("UPDATE organizations SET admin_user_id = ? WHERE id = ?", [$_POST["id"], $_POST["admin_org_id"]]);
      }
    }  
    // Move the user's apps along with them
    
    $db->query("SELECT a.id FROM applications a LEFT JOIN characters c ON a.character_id = c.id ".
      "WHERE a.user_id = ? AND c.char_type <> 'npc'", [$_POST["id"]]);
    $apps = array();
    while ( $row=$db->nextRow() ) {
      $apps[] = $row["id"];
    }

    if (count($apps) > 0) {
      $placeholders = implode(",", array_fill(0, count($apps), "?"));
      $db->query("UPDATE applications SET org_id = ? WHERE id IN ($placeholders)", array_merge([$_POST["org_id"]], $apps));
    }
    $message="Message=UserModified&";
    
    if ( isset($_SESSION["user_id"]) && isset($_POST["admin_org_id"]) && $_POST["id"]==$_SESSION["user_id"] ) {
      $db->query("select assistant from users where id=?", [$_POST["id"]]);
      $ThisUser_ID=$_POST['id'];
      include_once("session_setup.inc");
    }
    if ( sizeof($errmsgs)==0 ) {
      if ( ($_GET['return'] ?? '') === "UserList" ) {
        header("Location: UserList.php?" . $message);
        exit();
      } else {
        header("Location: UserDisplay.php?id=" . urlencode($_POST["id"]) . "&mode=edit&" . $message);
        exit();
      }
    }
  }
?>