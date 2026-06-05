<?php
/*
This page is designed to display user feedback.

It requires a table called:
feedback

This table has the following fields:
id, an autonum
user_id, a number
commentdate, a date
comment, a memo/text
subject, a varchar(255)
active, a yes/no (bit)
*/

  include_once("db.inc");
  $pagetitle="User Feedback History Page";
  include_once("header.inc");
  include_once("titlebar.php");

  if ( $_SESSION["super_user"] && isset($_GET["feedback_id"]) && isset($_GET["new_active_value"]) ) {
    $db->query("update feedback set active=? where id=?", [$_GET["new_active_value"], $_GET["feedback_id"]]);
  }
  
  $query="select f.* ".
       "from feedback f ".
       "order by active desc, commentdate desc";

  $res = $db->query($query);
?>

<table class="data">

<?php  
  while ( $row=$res->nextRow() ) {
 ?>
    <tr>
      <td>
        On <b><?php echo $row["commentdate"]?></b>, <?php echo userInfoPopup($row["user_id"])?> wrote:
      </td>
<?php
  if ( $row["active"] ) {
    echo("<td bgcolor=\"#A0FFA0\">");
    echo("Active");
    if ( $_SESSION["super_user"] ) {
      echo("<font class=\"normalsmall\">(<a href=\"UserFeedbackDisplay.php?feedback_id=" . urlencode($row["id"]) . "&new_active_value=0&\">Toggle</a>)</font>");
    }
    echo("</td>");
  } else {
    echo("<td bgcolor=\"#A0A0A0\">");
    echo("Closed");
    if ( $_SESSION["super_user"] ) {
      echo("<font class=\"normalsmall\">(<a href=\"UserFeedbackDisplay.php?feedback_id=" . urlencode($row["id"]) . "&new_active_value=1&\">Toggle</a>)</font>");
    }
    echo("</td>");
  }
?>
    </tr>
    <tr>
      <td colspan="2">
        <b><?php echo $row["subject"]?></b><br>          
				<?php echo nl2br($row["comment"])?>
      </td>
    </tr>
<?php
  }
?>
  </table>
<div class="normalsmall" align="center"><a href="UserFeedback.php">Enter Feedback</a></div>

<?php 

include_once("footerbar.inc") 

?>