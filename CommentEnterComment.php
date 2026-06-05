<?php
/**
 *
 **/
include_once("db.inc");
include_once("classes/ApplicationService.php");
include_once("classes/EmailService.php");
$applicationDAO = $daoFactory->getApplicationDAO();
$commentDAO = $daoFactory->getCommentDAO();

if ( !isset($_GET["parent_id"]) ) {
	$_GET["parent_id"]="0";
}
if ( !isset($_POST["app_id"]) ) {
	$_POST["app_id"]="0";
}
if ( !isset($_GET["app_id"]) ) {
	$_GET["app_id"]=$_POST["app_id"];
}
if ( !isset($_POST["parent_id"]) ) {
	$_POST["parent_id"]=$_GET["parent_id"];
}
if ( !isset($_POST["mode"]) ) {
	$_POST["mode"]="add";
}

if ( $_POST["mode"] == "doAdd" ) {
	if ( trim($_POST["subject"]) == "" || trim($_POST["comment"] == "") ) {
?>
	<div align="center" class="errmsg">
		Both Subject and Comment must be populated!
	</div>
<?php
	} else {
		$comment = new Comment();
		$comment->app_id = $_GET["app_id"];
		$comment->user_id = $_SESSION["user_id"];
		$comment->root_comment_id = $_POST["root_id"];
		$comment->parent_comment_id = isset( $_POST["parent_id"])?$_POST["parent_id"]:"";
		$comment->subject = $_POST["subject"];
		$comment->comment = $_POST["comment"];
		$comment->comment_date = time();
		$comment->constraint_level = $_POST["constraint_level"];
		$comment->rating = $_POST["rating"];

		$organizationDAO = $daoFactory->getOrganizationDAO();
		$characterDAO = $daoFactory->getCharacterDAO();
		$vssDAO = $daoFactory->getVssDAO();
		$userInfoDAO = $daoFactory->getUserInfoDAO();
		$applicationService = new ApplicationService( $organizationDAO, $characterDAO, $vssDAO, $userInfoDAO );
		$emailService = new EmailService( $userInfoDAO, $characterDAO, $applicationService );

		$commentDAO->insertComment( $comment );
		$application= $applicationDAO->readByID( $comment->app_id );

		if ( $comment->root_comment_id == "0" )  {
			$comment->root_comment_id = $comment->id;
			$commentDAO->update($comment);
		} else {
			$parent_comment = $commentDAO->readByID( $comment->parent_comment_id );
			if( $parent_comment->constraint_level == $comment->constraint_level) {
				$emailService->sendCommentReplyEmail( $application, $parent_comment, $comment );
			}
		}
		$application->update_date = time();
		$applicationDAO->update( $application );
		if ( $comment->constraint_level == "" ) {
			$emailService->sendCommentEmail( $application, $comment );
		}
//		print"<a href=\"AppDetails.php?id=$application->id\">AppDetails.php?id=$application->id</a>";
		header("Location: AppDetails.php?id=$application->id");
		exit();
    }
}

$comment = new Comment();
if( isset( $_POST['parent_id'] ) && $_POST['parent_id'] != 0 ) {
	$parent_comment = $commentDAO->readByID($_POST['parent_id']);
	$comment->app_id = $parent_comment->app_id;
	$comment->root_comment_id = $parent_comment->root_comment_id;
	$comment->parent_comment_id = $parent_comment->id;
	$comment->constraint_level = $parent_comment->constraint_level;

	if( $parent_comment->subject != '' ) {
		if( preg_match( "/^Re\:/", $parent_comment->subject ) ) {
			$comment->subject = $parent_comment->subject;
		} else {
			$comment->subject = "Re: " . $parent_comment->subject;
		}
	}
} else {
	$comment->app_id = $_GET["app_id"];

}
if ( isset($_POST["rating"] ) ) {
	$comment->rating=$_POST["rating"];
} else {
	$comment->rating = $commentDAO->readLastRating( $comment->app_id, $_SESSION["user_id"] );
}

if ( isset($_POST["subject"]) ) {
    $comment->subject = $_POST["subject"];
}

if ( isset($_GET["finalcomment"]) ) {
	$comment->subject = "Denial Justification";
}

$pagetitle="Add a Comment";
include_once("header.inc");
include_once("titlebar.php");
if ( isset($_GET["finalcomment"]) ) {
?>
  <div align="center" class="Subhead">
    Enter a justification for the denial.
  </div>
<?php
  }
 ?>

<div align="center">
<form action="CommentEnterComment.php" method="POST">
<input type="hidden" value="doAdd" name="mode">
<input type="hidden" value="<?php echo $comment->app_id ?>" name="app_id">
<input type="hidden" value="<?php echo $comment->parent_comment_id ?>" name="parent_id">
<input type="hidden" value="<?php echo $comment->root_comment_id ?>" name="root_id">
<table class="data">
<?php
  if( isset($parent_comment) ) {
?>
	<tr>
    <th align="center">Subject</th>
    <th align="center">Name</th>
    <th align="center">Date</th>
    <th align="center">Visibility</th>
    </tr>

    <tr class="dark">
    <td><?php echo str_repeat("&nbsp;", isset($depth) ? $depth * 3 : 0 ) . $parent_comment->subject ?></td>
    <td><?php echo userInfoPopup($parent_comment->user_id) ?></td>
    <td align='center'><?php echo strftime( "%D, %T", $parent_comment->unix_comment_date) ?></td>
<?php if ( $parent_comment->constraint_level != '' ) {?>
      <td align='center'><?php echo ucfirst( $parent_comment->constraint_level )?></td>
<?php } else {?>
      <td align='center'>Unconstrained</td>
<?php }?>
    </tr>
    <tr>
	    <td colspan="5"><?php echo str_replace( "\n", "<br>\n", $parent_comment->comment )?></td>
    </tr>
    </table>
    <table class="dark">
<?php
  }
?>
	<tr>
		<th colspan="5">Your Comment:</th>
	</tr>
	<tr class="dark">
		<td colspan="3">
			<b>Subject:</b> <input type="text" name="subject" value="<?php echo $comment->subject ?>" size="45" maxlength="255" />
		</td>
		<td>
<?php
  if ( ( $_SESSION["admin_level"]=="region" ||
         $_SESSION["admin_level"]=="nation") && !isset($_GET["finalcomment"]) ) {
?>
	Rating:
	    <select name="rating">
		    <option value="5"<?php if ( $comment->rating=="5" ) { echo " selected=\"selected\""; }?>>5 (Strongly Approve)</option>
		    <option value="4"<?php if ( $comment->rating=="4" ) { echo " selected=\"selected\""; }?>>4 (Solidly Approve)</option>
		    <option value="3"<?php if ( $comment->rating=="3" ) { echo " selected=\"selected\""; }?>>3 (Approve)</option>
		    <option value="2"<?php if ( $comment->rating=="2" ) { echo " selected=\"selected\""; }?>>2 (Disapprove)</option>
		    <option value="1"<?php if ( $comment->rating=="1" ) { echo " selected=\"selected\""; }?>>1 (Strongly Disapprove)</option>
		    <option value="0"<?php if ( $comment->rating=="0" ) { echo " selected=\"selected\""; }?>>0 (No vote/Abstain)</option>
	    </select>
<?php } else { ?>
    <input type="hidden" name="rating" value="0" />
<?php } ?>
	</td>
	<td>
		<b>Access Level:</b>
		<select name="constraint_level">
<?php
	$min_reached = 0;
	if ( $comment->constraint_level == "" || isset($_GET["finalcomment"]) ) {
		if ( $_GET["parent_id"]!="" && $_GET["parent_id"]!=0 && isset($_GET["parent_id"]) && $comment->constraint_level=="") {
			$selecttext="selected=\"selected\"";
			$min_reached=1;
		} else {
			$selecttext="";
		}
		print( "<option $selecttext value=\"\">Unconstrained</option>" );
	}
	if ( !isset($_GET["finalcomment"]) && $applicationDAO->readApplicationUserId($comment->app_id) != $_SESSION['user_id'] ) {
		foreach( $_SESSION['constraint_level_list'] as $constraint_level ) {
		if ( $comment->constraint_level == "" || $constraint_level == $comment->constraint_level || $min_reached ) {
			if( !$min_reached ) {
				print ("<option value=\"$constraint_level\" selected=\"selected\">".ucfirst( $constraint_level )."</option>\n");
			} else {
				print ("<option value=\"$constraint_level\">".ucfirst( $constraint_level )."</option>\n");
			}
			$min_reached = 1;
      }
    }
  }
?>
	</select>
	</td>
	</tr>
	<tr class="dark">
	<td colspan="5">
	<textarea name="comment" wrap="virtual" cols="65" rows="10"><?php echo $comment->comment?></textarea>
	</td>
	</tr>
	<tr>
	<td colspan="5" align="center">
	<input type="submit" value="Submit Comment">
	</td>
	</tr>
	</form>
	</table>
  </div>
<?php
  include_once("footerbar.inc");
?>
