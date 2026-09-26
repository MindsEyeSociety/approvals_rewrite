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
	 * Sends a message, so tests can intercept delivery by subclassing and
	 * overriding this method instead of calling the global mail() function
	 * directly. Contains no logic of its own.
	 *
	 * @todo The other five send*Email() methods below still call mail()
	 *   inline; migrate each through here as it gains a characterization test.
	 *
	 * @return bool the underlying mail() result
	 * @param $to string
	 * @param $subject string
	 * @param $message string
	 * @param $headers string
	 */
	protected function deliver( $to, $subject, $message, $headers ) {
		return mail( $to, $subject, $message, $headers );
	}

	/**
	 * The role mailbox notified when an officer notification is suppressed.
	 * Reads $SETTINGS['NTA_NOTIFICATION_EMAIL'] directly so tests can stub it
	 * by overriding this method instead of touching globals.
	 *
	 * @return string the configured address, or '' when unset
	 */
	protected function ntaEmail() { global $SETTINGS; return $SETTINGS["NTA_NOTIFICATION_EMAIL"] ?? ''; }

	/**
	 * Master on/off switch for escalating a suppressed officer notification
	 * to the next reachable storyteller. Reads $SETTINGS['ESCALATION_ENABLED']
	 * directly so tests can stub it by overriding this method instead of
	 * touching globals.
	 *
	 * @return bool true when escalation is enabled
	 */
	protected function escalationEnabled() { global $SETTINGS; return !empty( $SETTINGS["ESCALATION_ENABLED"] ); }

	/**
	 * Renders a human-readable label for a storyteller, falling back to a
	 * user-id-based placeholder when their name is missing or is the
	 * "No Name Set" placeholder UserInfoDAO::getUserInfo() uses for a user
	 * with no membership number on file.
	 *
	 * @return string the storyteller's name, or a "(user id N)" placeholder
	 * @param $id mixed the storyteller's user id, for the placeholder form
	 * @param $name string the storyteller's name as looked up, if any
	 */
	private function describeStoryteller( $id, $name ) {
		if( empty( $name ) || $name === 'No Name Set' ) {
			return $id !== null ? "the assigned storyteller (user id $id)" : "the assigned storyteller";
		}
		return $name;
	}

	/**
	 * Explains, in a sentence fragment, why a storyteller could not be
	 * emailed directly -- used in both the escalation and NTA messages.
	 * Never claims "expired" for a status other than 'expired': the
	 * distinction between "expired" and "unknown" exists specifically so an
	 * unverifiable membership is never misreported as a lapsed one.
	 *
	 * @return string a lowercase sentence fragment, e.g. "their MES membership is recorded as expired"
	 * @param $status string one of 'active', 'expired', 'unknown', 'portal_unavailable'
	 * @param $email_ok bool whether the storyteller's email address on file is usable
	 */
	private function describeUnreachableReason( $status, $email_ok ) {
		if( $status === 'expired' ) {
			return 'their MES membership is recorded as expired';
		}
		if( $status === 'unknown' ) {
			return 'their MES membership status could not be verified';
		}
		if( !$email_ok ) {
			return 'no valid email address is on file for them';
		}
		return 'their MES membership status is not active';
	}

	/**
	 * Send an email notifying the Low storyteller of a new application
	 * awaiting their approval. When the Low ST cannot be reached -- their
	 * membership has expired, is unverifiable, or their email on file is
	 * unusable -- climbs the escalation chain (see
	 * ApplicationService::getEscalationSTIDs()) to the first reachable
	 * storyteller and notifies both that storyteller and the NTA, instead of
	 * silently dropping the notification as before.
	 *
	 * A Portal outage ('portal_unavailable') is deliberately never escalated:
	 * it makes every member look inactive, so escalating on it would escalate
	 * every pending application at once. Escalation can also be disabled
	 * entirely via $SETTINGS['ESCALATION_ENABLED']; either way, nothing is
	 * sent and the suppression is logged.
	 *
	 * @return void
	 * @param $application Array
	 */
	function sendNewApplicationEmail( $application ) {
		$user_info = $this->userInfoDAO->getUserInfo($application->user_id);
		$low_st_id = $this->applicationService->getLowST($application);
		$st_info = $this->userInfoDAO->getUserInfo( $low_st_id );
		$character_info = $this->characterDAO->readByID( $application->character_id );
		$character_name = is_object( $character_info ) ? $character_info->name : '';

		$user_name = $user_info['name'] ?? '';
		$user_email = $user_info['email'] ?? '';
		$st_name = $st_info['name'] ?? '';
		$st_email = $st_info['email'] ?? '';

		$message = "<p>Greetings $st_name,</p>\n".
			"<p>An application for {$application->description} has been entered ".
			"by $user_name for $character_name and ".
			"is awaiting your review.</p>\n".
			"<p>Your timely attention to this matter would be appreciated.</p>\n".
			"<p>This is an automated message from the Approval system.\n".
			"Please Log in to the system at ".
			"<a href=\"http://legacy.modernenigmasociety.org/approvals_2017/AppDetails.php?id={$application->id}&\">".
			"http://legacy.modernenigmasociety.org/approvals_2017/AppDetails.php?id={$application->id}&</a> to reply</p>\n";
		$subject = "[Approval System] $user_name has just entered an application";
		$headers =
			"From: Approval System <approvals@legacy.modernenigmasociety.org>\r\n".
			"Reply-To: Approval System <approvals@cammail.modernenigmasociety.org>\r\n".
			"Content-Type: text/html\r\n".
			"CC: $user_email\r\n";

		$status = $this->userInfoDAO->membershipStatus( $low_st_id );
		$email_ok = $this->isValidEmail( $st_email );

		// Only send to the ST directly if they are an active member and have a valid email address
		if( $status === 'active' && $email_ok ) {
			$this->deliver( $st_email, $subject, $message, $headers );
			return;
		}

		if( $status === 'portal_unavailable' ) {
			error_log("EmailService::sendNewApplicationEmail - suppressing notification for application {$application->id}: portal unavailable, low ST " . ($low_st_id ?? 'null'));
			return;
		}

		if( !$this->escalationEnabled() ) {
			error_log("EmailService::sendNewApplicationEmail - escalation disabled, suppressing notification for application {$application->id}, low ST " . ($low_st_id ?? 'null') . " status $status email_ok " . ($email_ok ? 'yes' : 'no'));
			return;
		}

		error_log("EmailService::sendNewApplicationEmail - suppressing direct notification for application {$application->id}: low ST " . ($low_st_id ?? 'null') . " status $status email_ok " . ($email_ok ? 'yes' : 'no'));

		$reason = $this->describeUnreachableReason( $status, $email_ok );
		$st_label = htmlspecialchars( $this->describeStoryteller( $low_st_id, $st_name ) );

		$chain = $this->applicationService->getEscalationSTIDs( $application, $low_st_id );
		$skipped = array();
		$escalated_id = null;
		$escalated_name = '';
		$escalated_email = '';
		foreach( $chain as $candidate_id ) {
			$candidate_status = $this->userInfoDAO->membershipStatus( $candidate_id );
			$candidate_info = $this->userInfoDAO->getUserInfo( $candidate_id );
			$candidate_email = $candidate_info['email'] ?? '';
			if( $candidate_status === 'active' && $this->isValidEmail( $candidate_email ) ) {
				$escalated_id = $candidate_id;
				$escalated_name = $candidate_info['name'] ?? '';
				$escalated_email = $candidate_email;
				break;
			}
			$skipped[] = $candidate_id;
		}

		$app_description = htmlspecialchars( $application->description ?? '' );
		$app_link = "<a href=\"http://legacy.modernenigmasociety.org/approvals_2017/AppDetails.php?id={$application->id}&\">".
			"http://legacy.modernenigmasociety.org/approvals_2017/AppDetails.php?id={$application->id}&</a>";
		$escalation_headers =
			"From: Approval System <approvals@legacy.modernenigmasociety.org>\r\n".
			"Reply-To: Approval System <approvals@cammail.modernenigmasociety.org>\r\n".
			"Content-Type: text/html\r\n";

		if( $escalated_id !== null ) {
			$escalated_label = htmlspecialchars( $this->describeStoryteller( $escalated_id, $escalated_name ) );
			$escalation_message = "<p>Greetings $escalated_label,</p>\n".
				"<p>An application for $app_description has been entered ".
				"by " . htmlspecialchars( $user_name ) . " for " . htmlspecialchars( $character_name ) . " and ".
				"needs review.</p>\n".
				"<p>This would normally have gone to $st_label, but the system could not reach ".
				"them because $reason.</p>\n".
				"<p>Please review the application, or arrange to have the officer's membership ".
				"record corrected.</p>\n".
				"<p>Please log in to the system at $app_link to review.</p>\n".
				"<p>The NTA has also been notified.</p>\n";
			$this->deliver( $escalated_email, "[Approval System] Escalated - a storyteller with a lapsed membership", $escalation_message, $escalation_headers );
			error_log("EmailService::sendNewApplicationEmail - escalated application {$application->id} to user $escalated_id, skipped " . count($skipped) . " (" . implode(',', $skipped) . ")");
		} else {
			error_log("EmailService::sendNewApplicationEmail - no active storyteller found anywhere above low ST " . ($low_st_id ?? 'null') . " for application {$application->id}; chain checked: " . implode(',', $chain));
		}

		$nta_email = $this->ntaEmail();
		if( empty( $nta_email ) || !$this->isValidEmail( $nta_email ) ) {
			error_log("EmailService::sendNewApplicationEmail - NTA_NOTIFICATION_EMAIL missing or invalid, skipping NTA notice for application {$application->id}");
			return;
		}

		if( $escalated_id !== null ) {
			$escalated_label = htmlspecialchars( $this->describeStoryteller( $escalated_id, $escalated_name ) );
			$outcome = "<p>It was escalated to $escalated_label (user id $escalated_id).</p>\n";
			if( !empty( $skipped ) ) {
				$outcome .= "<p>Officers skipped on the way (unreachable): " . htmlspecialchars( implode(', ', $skipped) ) . ".</p>\n";
			}
		} else {
			$outcome = "<p><strong>No active storyteller was found anywhere above them, and nobody has been notified.</strong></p>\n";
		}

		$nta_message = "<p>Greetings,</p>\n".
			"<p>An officer notification was suppressed for application {$application->id} ($app_description), ".
			"applicant " . htmlspecialchars( $user_name ) . ".</p>\n".
			"<p>The assigned storyteller, $st_label (user id " . htmlspecialchars( (string) ($low_st_id ?? '') ) . "), ".
			"could not be reached because $reason.</p>\n".
			$outcome.
			"<p>Review the application at $app_link.</p>\n".
			"<p>This is an automated message from the Approval system.</p>\n";
		$this->deliver( $nta_email, "[Approval System] Suppressed notification - application {$application->id}", $nta_message, $escalation_headers );
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