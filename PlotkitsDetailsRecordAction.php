<?php
  include_once("db.inc");

  if ( isset($_POST['delete']) ) {
    if ( isset($_POST['ConfirmDelete']) ) {
       $db->query("DELETE FROM plotkits where id=?", [$_POST["id"]]);
       header("Location: PlotkitsMain.php");
       exit;
    } else {
      header("Location: PlotkitsDetails.php?id=" . urlencode($_POST["id"]) . "&message=DeleteNotConfirmed&");
      exit;
    }
  } else {
   $db->query("select venue_id from vsss where id=?", [$_POST["vss_id"]]);
   $vss_row=$db->nextRow();
    if ( $_POST["mode"]=="Edit" ) {
       $query="UPDATE plotkits SET ".
              "vss_id=?, ".
              "title=?, ".
              "start_date=?, ".
              "end_date=?, ".
              "plot_details=?, ".
              "storyteller_id=?, ".
              "venue_id=?, ".
              "url=?, ".
              "comment=?, ".
              "private_comment=? ".
              "WHERE id=?";
       $params = [
              $_POST["vss_id"],
              $_POST["title"],
              date("Y-m-d H:i:s",strtotime($_POST["start_date"])),
              date("Y-m-d H:i:s",strtotime($_POST["end_date"])),
              $_POST["plot_details"],
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
       $query="INSERT INTO plotkits (".
               "vss_id, ".
               "title, ".
               "start_date, ".
               "end_date, ".
               "plot_details, ".
               "storyteller_id, ".
               "venue_id, ".
               "url, ".
               "comment, ".
               "private_comment".
               ") values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
       $params = [
              $_POST["vss_id"],
              $_POST["title"],
              date("Y-m-d H:i:s",strtotime($_POST["start_date"])),
              date("Y-m-d H:i:s",strtotime($_POST["end_date"])),
              $_POST["plot_details"],
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
  
  header("Location: PlotkitsDetails.php?id=" . urlencode($this_id) . "&");
 ?>