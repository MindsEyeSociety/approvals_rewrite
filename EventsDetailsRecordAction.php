<?php
  include_once("db.inc");

  if ( isset($_POST['delete']) ) {
    if ( isset($_POST['ConfirmDelete']) ) {
       $db->query("DELETE FROM events where id=?", [$_POST["id"]]);
       header("Location: EventsMain.php");
       exit;
    } else {
      header("Location: EventsDetails.php?id=" . urlencode($_POST["id"]) . "&message=DeleteNotConfirmed&");
      exit;
    }
  } else {
   $db->query("select venue_id from vsss where id=?", [$_POST["vss_id"]]);
   $vss_row=$db->nextRow();
    if ( $_POST["mode"]=="Edit" ) {
       $query="UPDATE events SET ".
              "vss_id=?, ".
              "title=?, ".
              "event_date=?, ".
              "event_time=?, ".
              "description=?, ".
              "location=?, ".
              "storyteller_id=?, ".
              "venue_id=?, ".
              "url=?, ".
              "comment=?, ".
              "private_comment=? ".
              "WHERE id=?";
       $event_date = date("Y-m-d H:i:s", strtotime($_POST["event_date"]));
       $params = [
              $_POST["vss_id"],
              $_POST["title"],
              $event_date,
              $_POST["event_time"],
              $_POST["description"],
              $_POST["location"],
              $_SESSION["user_id"],
              $vss_row["venue_id"],
              $_POST["url"],
              $_POST["comment"],
              $_POST["private_comment"],
              $_POST["id"]
       ];

       $db->query($query, $params);
       $this_id=$_POST["id"];
    } else {
       $query="INSERT INTO events (".
               "vss_id, ".
               "title, ".
               "event_date, ".
               "event_time, ".
               "description, ".
               "location, ".
               "storyteller_id, ".
               "venue_id, ".
               "url, ".
               "comment, ".
               "private_comment".
               ") values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
       $event_date = date("Y-m-d H:i:s", strtotime($_POST["event_date"]));
       $params = [
              $_POST["vss_id"],
              $_POST["title"],
              $event_date,
              $_POST["event_time"],
              $_POST["description"],
              $_POST["location"],
              $_SESSION["user_id"],
              $vss_row["venue_id"],
              $_POST["url"],
              $_POST["comment"],
              $_POST["private_comment"]
       ];
       $db->query($query, $params);
       $this_id=$db->getInsertId();
    }
  }
  
  header("Location: EventsDetails.php?id=" . urlencode($this_id) . "&");
 ?>