<?php
require_once 'classes/EmailService.php';

/**
 * Unit tests for EmailService::sendNewApplicationEmail()'s escalation policy:
 * when the Low ST assigned to an application cannot be reached (their MES
 * membership has expired, is unverifiable, or their email on file is
 * unusable), the notification climbs ApplicationService::getEscalationSTIDs()
 * to the first reachable storyteller and notifies the NTA, instead of the
 * notification being silently dropped as it was before this feature.
 *
 * A Portal outage ('portal_unavailable') is pinned to send nothing at all,
 * since escalating on it would escalate every pending application at once at
 * a 100% false-positive rate. Escalation can also be disabled entirely via
 * escalationEnabled().
 *
 * Uses an anonymous EmailService subclass overriding deliver(), ntaEmail()
 * and escalationEnabled(), so no real mail() call or $SETTINGS global is ever
 * reached.
 *
 * @see EmailService::sendNewApplicationEmail()
 * @see EmailService::deliver()
 */
final class SuppressedNotificationEscalationTest extends \PHPUnit\Framework\TestCase {

	/** @var object stub CharacterDAO shared with the service under test, for shaping character_info per test */
	private $characterDAO;

	/** @var object stub ApplicationService shared with the service under test, for shaping the Low ST/chain per test */
	private $applicationService;

	/**
	 * Builds a recording EmailService wired with stub DAOs, so each test can
	 * shape a membership-status/email/escalation-chain scenario without a
	 * database, and inspect every attempted delivery afterwards.
	 *
	 * @param array<string,string> $statuses user id (as string) => membershipStatus() result
	 * @param array<string,array> $infos user id (as string) => getUserInfo() result; ids absent
	 *   here get UserInfoDAO's real placeholder, array('name' => 'No Name Set', 'email' => '')
	 * @param array $chain the escalation chain getEscalationSTIDs() returns, nearest first
	 */
	private function makeService( array $statuses, array $infos, array $chain, string $ntaEmail = 'nta@example.org', bool $escalationEnabled = true ) {
		$userInfoDAO = new class( $statuses, $infos ) {
			public $statuses;
			public $infos;
			function __construct( $statuses, $infos ) {
				$this->statuses = $statuses;
				$this->infos = $infos;
			}
			function membershipStatus( $id ) {
				return $this->statuses[(string) $id] ?? 'unknown';
			}
			function getUserInfo( $id ) {
				if( is_null( $id ) || !is_numeric( $id ) ) {
					return array();
				}
				return $this->infos[(string) $id] ?? array( 'name' => 'No Name Set', 'email' => '' );
			}
		};
		$characterDAO = new class {
			public $character = null;
			function readByID( $id ) { return $this->character; }
		};
		$applicationService = new class( $chain ) {
			public $chain;
			public $lowStId = null;
			function __construct( $chain ) { $this->chain = $chain; }
			function getLowST( $application ) { return $this->lowStId; }
			function getEscalationSTIDs( $application, $low_st_id ) { return $this->chain; }
		};

		$service = new class( $userInfoDAO, $characterDAO, $applicationService ) extends EmailService {
			public $deliveries = array();
			public $nta_email = '';
			public $escalation_enabled = true;
			protected function deliver( $to, $subject, $message, $headers ) {
				$this->deliveries[] = array( 'to' => $to, 'subject' => $subject, 'message' => $message, 'headers' => $headers );
				return true;
			}
			protected function ntaEmail() { return $this->nta_email; }
			protected function escalationEnabled() { return $this->escalation_enabled; }
		};
		$service->nta_email = $ntaEmail;
		$service->escalation_enabled = $escalationEnabled;

		$this->characterDAO = $characterDAO;
		$this->applicationService = $applicationService;

		return $service;
	}

	/** Builds a plain application row; character_id/description are only used by the message bodies. */
	private function makeApplication( $id = 1, $user_id = 100, $character_id = 5, $description = 'Test App' ) {
		$application = new stdClass;
		$application->id = $id;
		$application->user_id = $user_id;
		$application->character_id = $character_id;
		$application->description = $description;
		return $application;
	}

	/**
	 * An active, reachable Low ST gets exactly one delivery, whose
	 * to/subject/message/headers are byte-identical to the pre-escalation
	 * behaviour -- the no-regression pin for the untouched happy path.
	 */
	public function testActiveOfficerGetsExactlyOneDeliveryIdenticalToToday(): void {
		$service = $this->makeService(
			array( '50' => 'active' ),
			array(
				'50' => array( 'name' => 'Sam ST', 'email' => 'sam@example.org' ),
				'100' => array( 'name' => 'Pat Player', 'email' => 'pat@example.org' ),
			),
			array()
		);
		$this->applicationService->lowStId = 50;
		$this->characterDAO->character = (object) array( 'name' => 'Alice' );

		$service->sendNewApplicationEmail( $this->makeApplication() );

		$this->assertCount( 1, $service->deliveries );
		$delivery = $service->deliveries[0];
		$this->assertSame( 'sam@example.org', $delivery['to'] );
		$this->assertSame( '[Approval System] Pat Player has just entered an application', $delivery['subject'] );
		$this->assertSame(
			"<p>Greetings Sam ST,</p>\n".
			"<p>An application for Test App has been entered ".
			"by Pat Player for Alice and ".
			"is awaiting your review.</p>\n".
			"<p>Your timely attention to this matter would be appreciated.</p>\n".
			"<p>This is an automated message from the Approval system.\n".
			"Please Log in to the system at ".
			"<a href=\"http://legacy.modernenigmasociety.org/approvals_2017/AppDetails.php?id=1&\">".
			"http://legacy.modernenigmasociety.org/approvals_2017/AppDetails.php?id=1&</a> to reply</p>\n",
			$delivery['message']
		);
		$this->assertSame(
			"From: Approval System <approvals@legacy.modernenigmasociety.org>\r\n".
			"Reply-To: Approval System <approvals@cammail.modernenigmasociety.org>\r\n".
			"Content-Type: text/html\r\n".
			"CC: pat@example.org\r\n",
			$delivery['headers']
		);
	}

	/** An expired Low ST triggers two deliveries: the escalated storyteller, then the NTA. */
	public function testExpiredOfficerEscalatesThenNotifiesNta(): void {
		$service = $this->makeService(
			array( '50' => 'expired', '60' => 'active' ),
			array(
				'50' => array( 'name' => 'Lapsed Lenny', 'email' => 'lenny@example.org' ),
				'60' => array( 'name' => 'Nancy Next', 'email' => 'nancy@example.org' ),
				'100' => array( 'name' => 'Pat Player', 'email' => 'pat@example.org' ),
			),
			array( 60 )
		);
		$this->applicationService->lowStId = 50;

		$service->sendNewApplicationEmail( $this->makeApplication() );

		$this->assertCount( 2, $service->deliveries );
		$this->assertSame( 'nancy@example.org', $service->deliveries[0]['to'] );
		$this->assertSame( '[Approval System] Escalated - a storyteller with a lapsed membership', $service->deliveries[0]['subject'] );
		$this->assertStringContainsString( 'Lapsed Lenny', $service->deliveries[0]['message'] );
		$this->assertStringContainsString( 'expired', $service->deliveries[0]['message'] );

		$this->assertSame( 'nta@example.org', $service->deliveries[1]['to'] );
		$this->assertSame( '[Approval System] Suppressed notification - application 1', $service->deliveries[1]['subject'] );
		$this->assertStringContainsString( 'Nancy Next', $service->deliveries[1]['message'] );
	}

	/** Skipped, unreachable officers in the chain receive nothing; the walk continues past them to the first reachable one. */
	public function testMultipleLapsedOfficersAreSkippedUntilReachableOneFound(): void {
		$service = $this->makeService(
			array( '50' => 'unknown', '61' => 'expired', '62' => 'unknown', '63' => 'active' ),
			array(
				'63' => array( 'name' => 'Rae Reachable', 'email' => 'rae@example.org' ),
			),
			array( 61, 62, 63 )
		);
		$this->applicationService->lowStId = 50;

		$service->sendNewApplicationEmail( $this->makeApplication() );

		$this->assertCount( 2, $service->deliveries );
		$this->assertSame( 'rae@example.org', $service->deliveries[0]['to'] );
		$ntaMessage = $service->deliveries[1]['message'];
		$this->assertStringContainsString( '61', $ntaMessage );
		$this->assertStringContainsString( '62', $ntaMessage );
		$this->assertStringContainsString( '63', $ntaMessage );
	}

	/** Neither escalation message discloses the applicant's address anywhere, and neither carries a CC header. */
	public function testEscalationMessagesNeverDiscloseApplicantAddressOrCcHeader(): void {
		$service = $this->makeService(
			array( '50' => 'expired', '60' => 'active' ),
			array(
				'50' => array( 'name' => 'Lapsed Lenny', 'email' => 'lenny@example.org' ),
				'60' => array( 'name' => 'Nancy Next', 'email' => 'nancy@example.org' ),
				'100' => array( 'name' => 'Pat Player', 'email' => 'pat@example.org' ),
			),
			array( 60 )
		);
		$this->applicationService->lowStId = 50;

		$service->sendNewApplicationEmail( $this->makeApplication() );

		$this->assertCount( 2, $service->deliveries );
		foreach( $service->deliveries as $delivery ) {
			$this->assertStringNotContainsString( 'pat@example.org', $delivery['message'] );
			$this->assertStringNotContainsString( 'pat@example.org', $delivery['headers'] );
			$this->assertStringNotContainsString( 'CC:', $delivery['headers'] );
		}
	}

	/** The NTA is notified even when an active storyteller was successfully found and escalated to. */
	public function testNtaNotifiedEvenWhenActiveStorytellerWasFound(): void {
		$service = $this->makeService(
			array( '50' => 'expired', '60' => 'active' ),
			array(
				'50' => array( 'name' => 'Lapsed Lenny', 'email' => 'lenny@example.org' ),
				'60' => array( 'name' => 'Nancy Next', 'email' => 'nancy@example.org' ),
			),
			array( 60 )
		);
		$this->applicationService->lowStId = 50;

		$service->sendNewApplicationEmail( $this->makeApplication() );

		$recipients = array_column( $service->deliveries, 'to' );
		$this->assertContains( 'nta@example.org', $recipients );
	}

	/** When nobody in the chain is reachable, exactly one delivery goes out, to the NTA, saying nobody was notified. */
	public function testNoReachableStorytellerAnywhereNotifiesOnlyNta(): void {
		$service = $this->makeService(
			array( '50' => 'expired', '70' => 'expired', '71' => 'unknown' ),
			array(
				'50' => array( 'name' => 'Lapsed Lenny', 'email' => 'lenny@example.org' ),
			),
			array( 70, 71 )
		);
		$this->applicationService->lowStId = 50;

		$service->sendNewApplicationEmail( $this->makeApplication() );

		$this->assertCount( 1, $service->deliveries );
		$this->assertSame( 'nta@example.org', $service->deliveries[0]['to'] );
		$this->assertStringContainsString( 'No active storyteller was found anywhere above them', $service->deliveries[0]['message'] );
		$this->assertStringContainsString( 'nobody has been notified', $service->deliveries[0]['message'] );
	}

	/** An empty NTA address still delivers the storyteller escalation -- a config gap must never suppress the useful mail. */
	public function testEmptyNtaEmailStillSendsToTheEscalatedStoryteller(): void {
		$service = $this->makeService(
			array( '50' => 'expired', '60' => 'active' ),
			array(
				'50' => array( 'name' => 'Lapsed Lenny', 'email' => 'lenny@example.org' ),
				'60' => array( 'name' => 'Nancy Next', 'email' => 'nancy@example.org' ),
			),
			array( 60 ),
			''
		);
		$this->applicationService->lowStId = 50;

		$service->sendNewApplicationEmail( $this->makeApplication() );

		$this->assertCount( 1, $service->deliveries );
		$this->assertSame( 'nancy@example.org', $service->deliveries[0]['to'] );
	}

	/** An invalid NTA address is never used as a delivery recipient. */
	public function testInvalidNtaEmailNeverUsedAsRecipient(): void {
		$service = $this->makeService(
			array( '50' => 'expired', '60' => 'active' ),
			array(
				'50' => array( 'name' => 'Lapsed Lenny', 'email' => 'lenny@example.org' ),
				'60' => array( 'name' => 'Nancy Next', 'email' => 'nancy@example.org' ),
			),
			array( 60 ),
			'not-an-email'
		);
		$this->applicationService->lowStId = 50;

		$service->sendNewApplicationEmail( $this->makeApplication() );

		$recipients = array_column( $service->deliveries, 'to' );
		$this->assertNotContains( 'not-an-email', $recipients );
	}

	/** A Portal outage sends nothing at all -- escalating here would falsely escalate every pending application. */
	public function testPortalUnavailableSendsZeroDeliveries(): void {
		$service = $this->makeService(
			array( '50' => 'portal_unavailable' ),
			array( '50' => array( 'name' => 'Lapsed Lenny', 'email' => 'lenny@example.org' ) ),
			array( 60 )
		);
		$this->applicationService->lowStId = 50;

		$service->sendNewApplicationEmail( $this->makeApplication() );

		$this->assertCount( 0, $service->deliveries );
	}

	/** With escalation disabled, an unreachable Low ST sends nothing, matching pre-escalation behaviour. */
	public function testEscalationDisabledSendsZeroDeliveries(): void {
		$service = $this->makeService(
			array( '50' => 'expired', '60' => 'active' ),
			array(
				'50' => array( 'name' => 'Lapsed Lenny', 'email' => 'lenny@example.org' ),
				'60' => array( 'name' => 'Nancy Next', 'email' => 'nancy@example.org' ),
			),
			array( 60 ),
			'nta@example.org',
			false
		);
		$this->applicationService->lowStId = 50;

		$service->sendNewApplicationEmail( $this->makeApplication() );

		$this->assertCount( 0, $service->deliveries );
	}

	/** An active officer with an unusable email still escalates -- active membership alone is not reachability. */
	public function testActiveOfficerWithUnusableEmailEscalates(): void {
		$service = $this->makeService(
			array( '50' => 'active', '60' => 'active' ),
			array(
				'50' => array( 'name' => 'Unreachable Uma', 'email' => 'not-an-email' ),
				'60' => array( 'name' => 'Nancy Next', 'email' => 'nancy@example.org' ),
			),
			array( 60 )
		);
		$this->applicationService->lowStId = 50;

		$service->sendNewApplicationEmail( $this->makeApplication() );

		$this->assertCount( 2, $service->deliveries );
		$this->assertSame( 'nancy@example.org', $service->deliveries[0]['to'] );
		$this->assertStringContainsString( 'no valid email address is on file', $service->deliveries[0]['message'] );
	}

	/** A null Low ST id (e.g. a corrupt/missing routing row) escalates cleanly, without warnings, and notifies the NTA. */
	public function testNullLowStIdNotifiesNtaWithNoWarnings(): void {
		$service = $this->makeService(
			array( '80' => 'active' ),
			array( '80' => array( 'name' => 'Olive Officer', 'email' => 'olive@example.org' ) ),
			array( 80 )
		);
		$this->applicationService->lowStId = null;

		$service->sendNewApplicationEmail( $this->makeApplication() );

		$this->assertCount( 2, $service->deliveries );
		$this->assertSame( 'olive@example.org', $service->deliveries[0]['to'] );
		$this->assertStringContainsString( 'the assigned storyteller', $service->deliveries[0]['message'] );
	}

	/** An application whose character has been removed (readByID() returns null) builds every body with no warnings. */
	public function testApplicationWithNoCharacterBuildsWithNoWarnings(): void {
		$service = $this->makeService(
			array( '50' => 'active' ),
			array(
				'50' => array( 'name' => 'Sam ST', 'email' => 'sam@example.org' ),
				'100' => array( 'name' => 'Pat Player', 'email' => 'pat@example.org' ),
			),
			array()
		);
		$this->applicationService->lowStId = 50;
		$this->characterDAO->character = null;

		$service->sendNewApplicationEmail( $this->makeApplication( 1, 100, '' ) );

		$this->assertCount( 1, $service->deliveries );
		$this->assertStringContainsString( 'for  and', $service->deliveries[0]['message'] );
	}

	/**
	 * An active, reachable VST gets exactly one delivery, whose
	 * to/subject/message/headers are byte-identical to the pre-escalation
	 * MoveCharacter2.php mail() call -- the no-regression pin for the
	 * untouched happy path of the VSS join notification.
	 *
	 * @see EmailService::sendVssJoinRequestEmail()
	 */
	public function testVssJoinActiveStorytellerGetsExactlyOneDeliveryIdenticalToToday(): void {
		$service = $this->makeService(
			array( '50' => 'active' ),
			array( '50' => array( 'name' => 'Sam ST', 'email' => 'sam@example.org' ) ),
			array()
		);

		$service->sendVssJoinRequestEmail( 50, array(), 'Pat Player', 'Alice', 'Ghoul' );

		$this->assertCount( 1, $service->deliveries );
		$delivery = $service->deliveries[0];
		$this->assertSame( 'sam@example.org', $delivery['to'] );
		$this->assertSame( '[Approval System] A character has applied to join your VSS', $delivery['subject'] );
		$this->assertSame(
			"Pat Player has applied to add their character, ".
			"Alice (Ghoul) to your Venue Style Sheet.  ".
			"If you accept this character, you will have Low approval over this ".
			"character, and will be able to view the character under the ".
			"Character Census in the Storyteller menu.<br><br>\n" .
			"This is an automated message from the Approval system.<br>".
			"<a href=\"http://legacy.modernenigmasociety.org/approvals_2017/index.php\">Log in</a> to ".
			"the system and choose \"VSS Character List\" from the Storyteller ".
			"menue to accept or reject this character.",
			$delivery['message']
		);
		$this->assertSame(
			"From: Approval System <approvals@legacy.modernenigmasociety.org>\r\n".
			"Reply-To: Approval System <approvals@legacy.modernenigmasociety.org>\r\n".
			"Content-Type: text/html\r\n",
			$delivery['headers']
		);
	}

	/** An unreachable VST escalates to the first reachable storyteller above them, then notifies the NTA. */
	public function testVssJoinUnreachableStorytellerEscalatesThenNotifiesNta(): void {
		$service = $this->makeService(
			array( '50' => 'expired', '60' => 'active' ),
			array(
				'50' => array( 'name' => 'Lapsed Lenny', 'email' => 'lenny@example.org' ),
				'60' => array( 'name' => 'Nancy Next', 'email' => 'nancy@example.org' ),
			),
			array()
		);

		$service->sendVssJoinRequestEmail( 50, array( 60 ), 'Pat Player', 'Alice', 'Ghoul' );

		$this->assertCount( 2, $service->deliveries );
		$this->assertSame( 'nancy@example.org', $service->deliveries[0]['to'] );
		$this->assertSame( '[Approval System] Escalated - a storyteller with a lapsed membership', $service->deliveries[0]['subject'] );
		$this->assertStringContainsString( 'Lapsed Lenny', $service->deliveries[0]['message'] );
		$this->assertStringContainsString( 'expired', $service->deliveries[0]['message'] );
		$this->assertStringContainsString( 'Alice', $service->deliveries[0]['message'] );

		$this->assertSame( 'nta@example.org', $service->deliveries[1]['to'] );
		$this->assertStringContainsString( 'Nancy Next', $service->deliveries[1]['message'] );
	}

	/** A Portal outage sends nothing for the VSS join notification either -- the same no-false-positive rule applies. */
	public function testVssJoinPortalUnavailableSendsZeroDeliveries(): void {
		$service = $this->makeService(
			array( '50' => 'portal_unavailable' ),
			array( '50' => array( 'name' => 'Lapsed Lenny', 'email' => 'lenny@example.org' ) ),
			array()
		);

		$service->sendVssJoinRequestEmail( 50, array( 60 ), 'Pat Player', 'Alice', 'Ghoul' );

		$this->assertCount( 0, $service->deliveries );
	}
}
