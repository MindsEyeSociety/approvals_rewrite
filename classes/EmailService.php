<?php
include_once("AppDetails_functions.php");

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
	 * Master on/off switch for sendTierNotificationEmail(), kept deliberately
	 * separate from escalationEnabled(): escalation only concerns what happens
	 * once a notification is already being sent, whereas tier notification is
	 * brand-new outbound mail that never existed before this feature, so it
	 * must ship dark on its own switch. Reads
	 * $SETTINGS['TIER_NOTIFICATION_ENABLED'] directly so tests can stub it by
	 * overriding this method instead of touching globals.
	 *
	 * @return bool true when tier notification is enabled
	 * @see EmailService::sendTierNotificationEmail()
	 */
	protected function tierNotificationEnabled() { global $SETTINGS; return !empty( $SETTINGS["TIER_NOTIFICATION_ENABLED"] ); }

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
	 * Strips any "CC:" header line from a header block before it is reused for
	 * an escalation or NTA notice, so an address the original recipient's copy
	 * was CC'd to (e.g. the applicant) is never disclosed to someone else the
	 * notification gets forwarded to. Every other line is preserved verbatim
	 * and in order.
	 *
	 * @return string the header block with any line beginning "CC:" removed
	 * @param $headers string raw header block, lines terminated with \r\n
	 * @example
	 *   $this->stripCcHeader("From: a\r\nCC: b\r\nContent-Type: text/html\r\n");
	 *   // => "From: a\r\nContent-Type: text/html\r\n"
	 */
	private function stripCcHeader( $headers ) {
		$lines = explode( "\r\n", $headers );
		$kept = array_filter( $lines, function( $line ) {
			return $line !== '' && stripos( $line, 'CC:' ) !== 0;
		} );
		return $kept ? implode( "\r\n", $kept ) . "\r\n" : '';
	}

	/**
	 * The shared suppressed-officer-notification policy: notifies an officer
	 * directly when reachable, otherwise climbs the escalation chain to the
	 * first reachable storyteller above them and separately notifies the NTA,
	 * instead of silently dropping the notification. Used by every caller that
	 * emails a single officer about a pending item awaiting their action (see
	 * sendNewApplicationEmail(), sendVssJoinRequestEmail()), so this policy --
	 * who counts as reachable, how the chain is walked, what the NTA is told,
	 * and what gets logged -- exists in exactly one place.
	 *
	 * Reachability rules:
	 * - active membership + a syntactically valid email -> deliver $subject/
	 *   $message/$headers to the officer directly, unchanged, and stop.
	 * - membershipStatus() === 'portal_unavailable' -> send nothing. A Portal
	 *   outage makes every member look inactive, so escalating on it would
	 *   escalate every pending notification at once.
	 * - escalationEnabled() === false -> send nothing.
	 * - otherwise -> walk $chain in order and deliver an escalation notice to
	 *   the first candidate who is both active and has a valid email, then
	 *   always attempt a separate notice to ntaEmail() (when configured and
	 *   valid) describing what happened, whether or not a candidate was
	 *   reachable.
	 *
	 * @return void
	 * @param $officer_id mixed the officer who should have received the direct notification
	 * @param $chain array ordered candidate storyteller ids above the officer, nearest
	 *   first (see ApplicationService::getEscalationSTIDs() /
	 *   getEscalationSTIDsForVssSelection())
	 * @param $subject string subject line used only for the direct-to-officer send
	 * @param $message string body used only for the direct-to-officer send
	 * @param $headers string headers used for the direct-to-officer send; any "CC:" line
	 *   is stripped (see stripCcHeader()) before reuse for the escalation/NTA sends
	 * @param $context array{what: string, who: string, link: string, ref: string} plain
	 *   strings used to build the escalation/NTA bodies: 'what' describes the item
	 *   needing review (e.g. "An application for Foo"), 'who' is the applicant/player's
	 *   name, 'link' is the HTML anchor to the relevant page, and 'ref' identifies the
	 *   item for the NTA subject line and log messages (e.g. "application 42")
	 * @see EmailService::sendNewApplicationEmail()
	 * @see EmailService::sendVssJoinRequestEmail()
	 */
	private function notifyOfficerOrEscalate( $officer_id, $chain, $subject, $message, $headers, $context ) {
		$officer_info = $this->userInfoDAO->getUserInfo( $officer_id );
		$officer_name = $officer_info['name'] ?? '';
		$officer_email = $officer_info['email'] ?? '';

		$status = $this->userInfoDAO->membershipStatus( $officer_id );
		$email_ok = $this->isValidEmail( $officer_email );

		// Only send to the officer directly if they are an active member and have a valid email address
		if( $status === 'active' && $email_ok ) {
			$this->deliver( $officer_email, $subject, $message, $headers );
			return;
		}

		if( $status === 'portal_unavailable' ) {
			error_log("EmailService::notifyOfficerOrEscalate - suppressing notification for {$context['ref']}: portal unavailable, officer " . ($officer_id ?? 'null'));
			return;
		}

		if( !$this->escalationEnabled() ) {
			error_log("EmailService::notifyOfficerOrEscalate - escalation disabled, suppressing notification for {$context['ref']}, officer " . ($officer_id ?? 'null') . " status $status email_ok " . ($email_ok ? 'yes' : 'no'));
			return;
		}

		error_log("EmailService::notifyOfficerOrEscalate - suppressing direct notification for {$context['ref']}: officer " . ($officer_id ?? 'null') . " status $status email_ok " . ($email_ok ? 'yes' : 'no'));

		$reason = $this->describeUnreachableReason( $status, $email_ok );
		$officer_label = htmlspecialchars( $this->describeStoryteller( $officer_id, $officer_name ) );
		$escalation_headers = $this->stripCcHeader( $headers );

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

		$what = $context['what'];
		$who = htmlspecialchars( $context['who'] );
		$link = $context['link'];

		if( $escalated_id !== null ) {
			$escalated_label = htmlspecialchars( $this->describeStoryteller( $escalated_id, $escalated_name ) );
			$escalation_message = "<p>Greetings $escalated_label,</p>\n".
				"<p>$what has been entered by $who and needs review.</p>\n".
				"<p>This would normally have gone to $officer_label, but the system could not reach ".
				"them because $reason.</p>\n".
				"<p>Please review it, or arrange to have the officer's membership ".
				"record corrected.</p>\n".
				"<p>Please log in to the system at $link to review.</p>\n".
				"<p>The NTA has also been notified.</p>\n";
			$this->deliver( $escalated_email, "[Approval System] Escalated - a storyteller with a lapsed membership", $escalation_message, $escalation_headers );
			error_log("EmailService::notifyOfficerOrEscalate - escalated {$context['ref']} to user $escalated_id, skipped " . count($skipped) . " (" . implode(',', $skipped) . ")");
		} else {
			error_log("EmailService::notifyOfficerOrEscalate - no active storyteller found anywhere above officer " . ($officer_id ?? 'null') . " for {$context['ref']}; chain checked: " . implode(',', $chain));
		}

		$nta_email = $this->ntaEmail();
		if( empty( $nta_email ) || !$this->isValidEmail( $nta_email ) ) {
			error_log("EmailService::notifyOfficerOrEscalate - NTA_NOTIFICATION_EMAIL missing or invalid, skipping NTA notice for {$context['ref']}");
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
			"<p>An officer notification was suppressed for {$context['ref']} ($what), ".
			"submitted by $who.</p>\n".
			"<p>The assigned officer, $officer_label (user id " . htmlspecialchars( (string) ($officer_id ?? '') ) . "), ".
			"could not be reached because $reason.</p>\n".
			$outcome.
			"<p>Review it at $link.</p>\n".
			"<p>This is an automated message from the Approval system.</p>\n";
		$this->deliver( $nta_email, "[Approval System] Suppressed notification - {$context['ref']}", $nta_message, $escalation_headers );
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
	 * @see EmailService::notifyOfficerOrEscalate()
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

		$chain = $this->applicationService->getEscalationSTIDs( $application, $low_st_id );
		$app_link = "<a href=\"http://legacy.modernenigmasociety.org/approvals_2017/AppDetails.php?id={$application->id}&\">".
			"http://legacy.modernenigmasociety.org/approvals_2017/AppDetails.php?id={$application->id}&</a>";
		$context = array(
			'what' => "An application for " . htmlspecialchars( $application->description ?? '' ) . " for " . htmlspecialchars( $character_name ),
			'who' => $user_name,
			'link' => $app_link,
			'ref' => "application {$application->id}",
		);

		$this->notifyOfficerOrEscalate( $low_st_id, $chain, $subject, $message, $headers, $context );
	}

	/**
	 * Send an email notifying a venue storyteller that a character has applied
	 * to join their Venue Style Sheet, awaiting their acceptance or rejection.
	 * When the storyteller cannot be reached -- their membership has expired,
	 * is unverifiable, or their email on file is unusable -- climbs the
	 * escalation chain (see
	 * ApplicationService::getEscalationSTIDsForVssSelection()) to the first
	 * reachable storyteller and notifies both that storyteller and the NTA,
	 * instead of silently dropping the notification as before.
	 *
	 * @return void
	 * @param $storyteller_id mixed the VSS/org storyteller's user id
	 * @param $chain array ordered candidate storyteller ids above them (see
	 *   ApplicationService::getEscalationSTIDsForVssSelection())
	 * @param $player_name string the applying character's player's name (or "NPC")
	 * @param $character_name string the character's name
	 * @param $character_subtype string the character's subtype
	 * @see EmailService::notifyOfficerOrEscalate()
	 * @see ApplicationService::getEscalationSTIDsForVssSelection()
	 */
	function sendVssJoinRequestEmail( $storyteller_id, $chain, $player_name, $character_name, $character_subtype ) {
		$message = "$player_name has applied to add their character, ".
			"$character_name ($character_subtype) to your Venue Style Sheet.  ".
			"If you accept this character, you will have Low approval over this ".
			"character, and will be able to view the character under the ".
			"Character Census in the Storyteller menu.<br><br>\n" .
			"This is an automated message from the Approval system.<br>".
			"<a href=\"http://legacy.modernenigmasociety.org/approvals_2017/index.php\">Log in</a> to ".
			"the system and choose \"VSS Character List\" from the Storyteller ".
			"menue to accept or reject this character.";
		$subject = "[Approval System] A character has applied to join your VSS";
		$headers = "From: Approval System <approvals@legacy.modernenigmasociety.org>\r\n".
			"Reply-To: Approval System <approvals@legacy.modernenigmasociety.org>\r\n".
			"Content-Type: text/html\r\n";

		$link = "<a href=\"http://legacy.modernenigmasociety.org/approvals_2017/index.php\">Log in</a>";
		$context = array(
			'what' => "A request for " . htmlspecialchars( $character_name ) . " (" . htmlspecialchars( $character_subtype ) . ") to join your Venue Style Sheet",
			'who' => $player_name,
			'link' => $link,
			'ref' => "a VSS join request for character " . htmlspecialchars( $character_name ),
		);

		$this->notifyOfficerOrEscalate( $storyteller_id, $chain, $subject, $message, $headers, $context );
	}

	/**
	 * Decides whether a status transition warrants notifying the tier officer
	 * who now must act on it -- the guard AppDetails.php applies at both
	 * ratchet points before calling sendTierNotificationEmail(), so the rule
	 * lives in one place instead of being reimplemented at each call site.
	 *
	 * Notification is warranted only when ALL of:
	 * - $new_status is a "Pending <Tier>" status (never "Approved", "Denied",
	 *   or "Removed" -- those are terminal or already handled elsewhere).
	 * - $new_status actually differs from $old_status -- an application
	 *   already sitting at "Pending Mid" that stays there is not a fresh
	 *   transition, and re-notifying on every unrelated edit would be noise.
	 * - $officer_id could be resolved at all.
	 * - $officer_id is not $actor_id, the user who just made the change --
	 *   storytellers routinely hold multiple offices, and mailing someone
	 *   about their own click is noise, not a notification.
	 *
	 * @return bool true when the tier officer should be notified
	 * @param $new_status string the application's status after the update
	 * @param $old_status string the application's status immediately before this request's changes
	 * @param $actor_id mixed the user who made the change ($_SESSION['user_id'])
	 * @param $officer_id mixed the tier officer who would be notified, or null if none could be resolved
	 * @example EmailService::tierNotificationWarranted( 'Pending Mid', 'Pending Low', 12, 45 ); // true
	 * @example EmailService::tierNotificationWarranted( 'Pending Mid', 'Pending Mid', 12, 45 ); // false -- no transition
	 * @example EmailService::tierNotificationWarranted( 'Approved', 'Pending Global', 12, 45 ); // false -- not a Pending status
	 * @example EmailService::tierNotificationWarranted( 'Pending Mid', 'Pending Low', 45, 45 ); // false -- officer is the actor
	 * @see EmailService::sendTierNotificationEmail()
	 */
	function tierNotificationWarranted( $new_status, $old_status, $actor_id, $officer_id ) {
		if( strpos( (string) $new_status, 'Pending ' ) !== 0 ) {
			return false;
		}
		if( (string) $new_status === (string) $old_status ) {
			return false;
		}
		if( $officer_id === null ) {
			return false;
		}
		if( (string) $officer_id === (string) $actor_id ) {
			return false;
		}
		return true;
	}

	/**
	 * Send an email notifying the officer at a given approval tier that an
	 * application has ratcheted up to their tier and needs their review -- the
	 * notification AppDetails.php never sent before this feature: only the
	 * applicant was told (see sendChangesEmail()), while the officer who now
	 * had to act on it heard nothing and the application simply sat there.
	 *
	 * Gated by its own independent switch, tierNotificationEnabled(): returns
	 * immediately, logging, when it is off. This is deliberately separate from
	 * escalationEnabled(), which only governs what happens once a notification
	 * is already being sent -- see tierNotificationEnabled().
	 *
	 * When the tier officer cannot be reached -- their membership has expired,
	 * is unverifiable, or their email on file is unusable -- climbs $chain to
	 * the first reachable storyteller and notifies both that storyteller and
	 * the NTA, via the same shared policy as sendNewApplicationEmail() and
	 * sendVssJoinRequestEmail() (see notifyOfficerOrEscalate()).
	 *
	 * Deliberately carries no "CC:" header: the applicant already receives
	 * sendChangesEmail() for this same transition, so CCing them here would
	 * double-mail them.
	 *
	 * @return void
	 * @param $application Object the application that ratcheted tiers; reads id and description
	 * @param $tier_rank int the tier rank (1-5, per approvalTierRank()) the application just reached
	 * @param $officer_id mixed the user id of the officer at that tier, or null if none could be resolved
	 * @param $chain array ordered candidate storyteller ids above the tier officer, nearest
	 *   first (see ApplicationService::getEscalationSTIDsForTier())
	 * @param $applicant_name string the applicant's display name
	 * @param $character_name string the character's name, or '' if none
	 * @see EmailService::notifyOfficerOrEscalate()
	 * @see EmailService::tierNotificationEnabled()
	 * @see EmailService::tierNotificationWarranted()
	 * @see ApplicationService::getSTForTier()
	 */
	function sendTierNotificationEmail( $application, $tier_rank, $officer_id, $chain, $applicant_name, $character_name ) {
		if( !$this->tierNotificationEnabled() ) {
			error_log("EmailService::sendTierNotificationEmail - tier notification disabled, suppressing notice for application " . ($application->id ?? 'null'));
			return;
		}

		$tier_name = approvalTierName( $tier_rank );
		$app_id = $application->id ?? '';
		$description = htmlspecialchars( $application->description ?? '' );
		$applicant = htmlspecialchars( $applicant_name ?? '' );
		$character = htmlspecialchars( $character_name ?? '' );

		$app_link = "<a href=\"http://legacy.modernenigmasociety.org/approvals_2017/AppDetails.php?id=$app_id&\">".
			"http://legacy.modernenigmasociety.org/approvals_2017/AppDetails.php?id=$app_id&</a>";

		$message = "<p>Greetings,</p>\n".
			"<p>An application for $description by $applicant for $character has reached ".
			htmlspecialchars( $tier_name )." approval and is awaiting your review.</p>\n".
			"<p>Please log in to the system at $app_link to review.</p>\n".
			"<p>This is an automated message from the Approval system.</p>\n";
		$subject = "[Approval System] An application has reached " . $tier_name . " approval";
		$headers =
			"From: Approval System <approvals@legacy.modernenigmasociety.org>\r\n".
			"Reply-To: Approval System <approvals@cammail.modernenigmasociety.org>\r\n".
			"Content-Type: text/html\r\n";

		$context = array(
			'what' => "An application for $description for $character",
			'who' => $applicant_name,
			'link' => $app_link,
			'ref' => "application $app_id",
		);

		$this->notifyOfficerOrEscalate( $officer_id, $chain, $subject, $message, $headers, $context );
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