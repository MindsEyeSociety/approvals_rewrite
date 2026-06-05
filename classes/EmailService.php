<?php
/**
 * Email Service
 * @package Services
 */
class EmailService {
	/**
	 * User Info DAO
	 * @var Object
	 */
	var $userInfoDAO;

	/**
	 * Application Service
	 * @var Object
	 */
	var $applicationService;

	/**
	 * Character DAO
	 * @var Object
	 */
	var $characterDAO;

	/**
	 *
	 * @return
	 * @param $userInfoDAO Object
	 * @param $characterDAO Object
	 * @param $applicationService Object
	 */
	function __construct( $userInfoDAO, $characterDAO, $applicationService ) {
		$this->userInfoDAO = $userInfoDAO;
		$this->characterDAO = $characterDAO;
		$this->applicationService = $applicationService;
	}

    /**
     * Simple email validation helper.
     * Returns true if the address is non‑empty and matches a basic pattern.
     */
    private function isValidEmail( $addr ) {
        return (bool) preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $addr);
    }

	/**
	 * Send an email notifying the Low storyteller of a new application
	 * awaiting their approval
	 *
	 * @return void
	 * @param $application Array
	 */
	function sendNewApplicationEmail( $application ) {
		$user_info = $this->userInfoDAO->getUserInfo($application->user_id);
		$low_st_id = $this->applicationService->getLowST($application);
		$st_info = $this->userInfoDAO->getUserInfo( $low_st_id );
		$character_info = $this->characterDAO->readByID( $application->character_id );

		$message = "<p>Greetings $st_info[name],</p>\n".
			"<p>An application for {$application->description} has been entered ".
			"by $user_info[name] for {$character_info->name} and ".
			"is awaiting your review.</p>\n".
			"<p>Your timely attention to this matter would be appreciated.</p>\n".
			"<p>This is an automated message from the Approval system.\n".
			"Please Log in to the system at ".
			"<a href=\"http://legacy.modernenigmasociety.org/approvals_2017/AppDetails.php?id={$application->id}&\">".
			"http://legacy.modernenigmasociety.org/approvals_2017/AppDetails.php?id={$application->id}&</a> to reply</p>\n";
		$subject = "[Approval System] $user_info[name] has just entered an application";
		$headers =
			"From: Approval System <approvals@legacy.modernenigmasociety.org>\r\n".
			"Reply-To: Approval System <approvals@cammail.modernenigmasociety.org>\r\n".
			"Content-Type: text/html\r\n".
			"CC: $user_info[email]\r\n";

		// Only send to the ST if they are an active member and have a valid email address
		if( $this->userInfoDAO->isMemberActive( $low_st_id ) && $this->isValidEmail($st_info['email']) ) {
			mail( $st_info['email'], $subject, $message, $headers );
		}
	}

	function sendChangesEmail ( $application, $changes ) {
        $user_info = $this->userInfoDAO->getUserInfo($application->user_id);
        if ( $user_info['email'] != "" ) {
			$message = "Greetings, $user_info[name]<br><br>\n".
				"This is a quick email to let you know that the following ".
				"changes have just been made on your application for ".
				"{$application->description}".(($application->character)?" for {$application->character->name}":'').":<br> ".
				"$changes<br><br>\n".
				"<a href=\"http://legacy.modernenigmasociety.org/approvals_2017/AppDetails.php?id={$application->id}&\">".
				"http://legacy.modernenigmasociety.org/approvals_2017/AppDetails.php?id={$application->id}&</a><br><br>\n".
				"This is an automated message from the Approval system.  ".
				"Log in to the system to reply to this message.";
			$headers =
				"From: Approval System <approvals@legacy.modernenigmasociety.org>\r\n".
				"Reply-To: Approval System <approvals@legacy.modernenigmasociety.org>\r\n".
				"Content-Type: text/html\r\n";

			// Only send if the applicant is an active member
			if( $this->userInfoDAO->isMemberActive( $application->user_id ) && $this->isValidEmail($user_info['email']) ) {
				mail( $user_info['email'],
					"[Approval System] Your application has been altered",
					$message,
					$headers);
			}
        }
	}

	function sendRemovedEmail ( $application ) {
        $user_info = $this->userInfoDAO->getUserInfo($application->user_id);
        if ( $user_info['email'] != "" ) {
			$message = "Greetings, $user_info[name]<br><br>\n".
				"This is a quick email to let you know that your application for ".
				"{$application->description} ".(($application->character)?" for {$application->character->name} ":'')."has been removed ".
				"from consideration.  If this is unexpected, visit the ".
                "system to obtain additional information.<br>\n ".
                "<a href=\"http://legacy.modernenigmasociety.org/approvals_2017/AppDetails.php?id={$application->id}&\">".
                "http://legacy.modernenigmasociety.org/approvals_2017/AppDetails.php?id={$application->id}&</a><br><br>\n".
                "This is an automated message from the Approval system.  ".
                "Log in to the system to reply to this message.";
			$headers =
				"From: Approval System <approvals@legacy.modernenigmasociety.org>\r\n".
                "Reply-To: Approval System <approvals@legacy.modernenigmasociety.org>\r\n".
                "Content-Type: text/html\r\n" ;

			// Only send if the applicant is an active member
			if( $this->userInfoDAO->isMemberActive( $application->user_id ) && $this->isValidEmail($user_info['email']) ) {
				mail( $user_info['email'], "[Approval System] Your application has been removed", $message, $headers);
			}
        }

	}

	function sendDeniedEmail( $application ) {
        $user_info = $this->userInfoDAO->getUserInfo($application->user_id);
        if ( $user_info['email'] != "" ) {
        	$message = "Greetings, $user_info[name]<br><br>\n".
        		"This is a quick email to let you know that your application for ".
        		"{$application->description}".(($application->character)?" for {$application->character->name}":'')." has been denied.  ".
        		"For details on why the application was denied, visit the ".
        		"approvals system at:<br><br><br>\n".
        		"<a href=\"http://legacy.modernenigmasociety.org/approvals_2017/AppDetails.php?id={$application->id}&\">".
        		"http://legacy.modernenigmasociety.org/approvals_2017/AppDetails.php?id={$application->id}&</a><br><br>\n".
        		"This is an automated message from the Approval system.  ".
        		"Log in to the system to reply to this message.";
			$headers =
				"From: Approval System <approvals@legacy.modernenigmasociety.org>\r\n".
                "Reply-To: Approval System <approvals@legacy.modernenigmasociety.org>\r\n".
                "Content-Type: text/html\r\n" ;

			// Only send if the applicant is an active member
			if( $this->userInfoDAO->isMemberActive( $application->user_id ) && $this->isValidEmail($user_info['email']) ) {
				mail( $user_info['email'], "[Approval System] Your application has been denied", $message, $headers);
			}
        }
    }

    function sendCommentReplyEmail( $application, $parent_comment, $comment ) {
		$user_info = $this->userInfoDAO->getUserInfo($parent_comment->user_id);
		$commenter = $this->userInfoDAO->getUserInfo($comment->user_id);
        if( $user_info != null && $user_info['email'] != "" )
        $message = "Greetings, $user_info[name]<br>\n".
        	"This is a quick email to notify you that $commenter[name] ".
			"has replied to your comment on {$application->description} ".
			(($application->character)?" for {$application->character->name}":'')."<br><br>\n".
 			"Your Comment:<br>\n".
            "<b>Subject:</b> {$parent_comment->subject}<br>".
			"<b>Comment:</b><br><i>" . nl2br($parent_comment->comment) . "</i><br><br>\n".
			"$commenter[name]'s reply:<br>\n".
			"<b>Subject:</b> {$comment->subject}<br>".
            "<b>Comment:</b><br><i>" . nl2br($comment->comment) . "</i><br><br>\n".
            "You can review the comment at ".
            "<a href=\"http://legacy.modernenigmasociety.org/approvals_2017/AppDetails.php?id={$comment->app_id}&showrecentdetails=1&\">".
            "http://legacy.modernenigmasociety.org/approvals_2017/AppDetails.php?id={$comment->app_id}&showrecentdetails=1&</a>".
            "<br><br>";
//            "<br><br>".
//            "<form name=\"response\" method=\"POST\" action=\"http://legacy.modernenigmasociety.org/approvals_2017/CommentEnterComment.php\">\n".
//            "<input type=\"hidden\" value=\"doAdd\" name=\"mode\">\n".
//            "<input type=\"hidden\" value=\"{$comment->app_id}\" name=\"app_id\"\">\n".
//            "<input type=\"hidden\" value=\"{$comment->id}\" name=\"parent_id\">\n".
//            "<input type=\"hidden\" value=\"{$comment->parent_comment_id}\" name=\"root_id\">\n".
//            "<input type=\"hidden\" value=\"\" name=\"constraint_level\">\n".
//            "<input type=\"hidden\" value=\"\" name=\"rating\">\n".
//            "Subject: <input type='text' value=\"{$comment->subject}\" name=\"subject\" width=\"40\"><br>\n".
//            "Text:<br><textarea name=\"comment\" wrap=\"virtual\" cols=\"72\" rows=\"10\"></textarea>\n<br> ".
//            "<input type=\"submit\" value=\"Respond\"></form>";

		// Only send if the parent comment author is an active member
			if( $this->userInfoDAO->isMemberActive( $parent_comment->user_id ) && $this->isValidEmail($user_info['email']) ) {
				mail( $user_info['email'], "[Approval System] Re: '{$comment->subject}' ", $message,
					"From: Approval System <approvals@legacy.modernenigmasociety.org>\r\n".
					"Reply-To: Approval System <approvals@legacy.modernenigmasociety.org>\r\n".
					"Content-Type: text/html\r\n" );
			}
    }

    function sendCommentEmail( $application, $comment ) {
    	$user_info = $this->userInfoDAO->getUserInfo($application->user_id);
        $commenter = $this->userInfoDAO->getUserInfo($comment->user_id);
        if ( $user_info != null && $user_info['email'] != "" ) {
        	$message = "Greetings, $user_info[name]<br>\n".
            	"This is a quick email to let you know that $commenter[name] ".
                "has commented on your  application for {$application->description} " .
                (($application->character)?"for {$application->character->name}":'').".<br><br>\n".
                "The comment, whose subject is:<br>\n".
                "<b>Subject:</b><i>{$comment->subject}</i><br>".
                "<b>Comment:</b><br><i>" . nl2br($comment->comment) . "</i><br><br>\n".
                "You can review the comment at ".
                "<a href=\"http://legacy.modernenigmasociety.org/approvals_2017/AppDetails.php?id={$comment->app_id}&showrecentdetails=1&\">".
                "http://legacy.modernenigmasociety.org/approvals_2017/AppDetails.php?id={$comment->app_id}&showrecentdetails=1&</a>".
                "<br><br>";
//                "<br><br>".
//                "<form name=\"response\" method=\"POST\" action=\"http://legacy.modernenigmasociety.org/approvals_2017/CommentEnterComment.php\">\n".
//                "<input type=\"hidden\" value=\"doAdd\" name=\"mode\">\n".
//                "<input type=\"hidden\" value=\"$comment->app_id\" name=\"app_id\"\">\n".
//                "<input type=\"hidden\" value=\"$comment->id\" name=\"parent_id\">\n".
//                "<input type=\"hidden\" value=\"$comment->parent_comment_id\" name=\"root_id\">\n".
//                "<input type=\"hidden\" value=\"\" name=\"constraint_level\">\n".
//                "<input type=\"hidden\" value=\"\" name=\"rating\">\n".
//                "Subject: <input type='text' value=\"Re: $comment->subject\" name=\"subject\" width=\"40\"><br>\n".
//                "Text:<br><textarea name=\"comment\" wrap=\"virtual\" cols=\"72\" rows=\"10\"></textarea>\n<br> ".
//                "<input type=\"submit\" value=\"Respond\"></form>";

			// Only send if the application owner is an active member
			if( $this->userInfoDAO->isMemberActive( $application->user_id ) && $this->isValidEmail($user_info['email']) ) {
				mail( $user_info['email'], "[Approval System] Comment: '{$comment->subject}'", $message,
					  "From: Approval System <approvals@legacy.modernenigmasociety.org>\r\n".
					  "Reply-To: Approval System <approvals@legacy.modernenigmasociety.org>\r\n".
					  "Content-Type: text/html\r\n" );
			}
        }
	}
}