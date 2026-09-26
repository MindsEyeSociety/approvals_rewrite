<?php
require_once 'classes/ApplicationService.php';

/**
 * Unit tests for ApplicationService::getEscalationSTIDs() and its helpers
 * (resolveApprovalAnchor(), climbFromOrg()), pinning:
 * - the VSS-vs-org anchor asymmetry (a VSS anchor climbs from its OWN org; an
 *   org anchor climbs from its PARENT),
 * - that the climb starts from the same anchor getLowST() resolves to, for
 *   every getLowST() routing branch,
 * - the generic climbFromOrg() termination and dedupe rules (self-parenting
 *   org, mutual cycle, max_rungs backstop, same person on consecutive rungs,
 *   a rung with no storyteller),
 * - that the Low ST and the applicant themself are never reported.
 *
 * @see ApplicationService::getEscalationSTIDs()
 * @see ApplicationService::climbFromOrg()
 * @see ApplicationService::resolveApprovalAnchor()
 */
final class EscalationChainTest extends \PHPUnit\Framework\TestCase {

	private $characterDAO;
	private $organizationDAO;
	private $vssDAO;

	/**
	 * Builds an ApplicationService wired with stub DAOs whose parent/ST maps are
	 * exposed as public properties, so each test can shape its own org tree,
	 * gaps and cycles without a database.
	 */
	private function makeService(): ApplicationService {
		$characterDAO = new class {
			public $vss = 0;
			public $org = 0;
			public $uid = 0;
			public $missing = false;
			function readByID( $id ) {
				if( $this->missing ) return null;
				$o = new stdClass;
				$o->vss_id = $this->vss;
				$o->org_id = $this->org;
				$o->user_id = $this->uid;
				return $o;
			}
		};
		$organizationDAO = new class {
			/** @var array<string,mixed> org_id => parent org id (or null); unmapped ids increment forever. */
			public $parents = array();
			/** @var array<string,mixed> org_id => storyteller id (or null); unmapped ids echo "ORG:$id". */
			public $sts = array();
			public $stCalls = 0;
			function getParentOrgID( $id ) {
				$key = (string) $id;
				if( array_key_exists( $key, $this->parents ) ) {
					return $this->parents[$key];
				}
				return $id + 1;
			}
			function getOrgSTID( $id ) {
				$this->stCalls++;
				$key = (string) $id;
				if( array_key_exists( $key, $this->sts ) ) {
					return $this->sts[$key];
				}
				return "ORG:$id";
			}
		};
		$vssDAO = new class {
			/** @var array<string,mixed> vss_id => org id (or null). */
			public $orgs = array();
			/** @var array<string,mixed> vss_id => storyteller id; unmapped ids echo "VSS:$id". */
			public $sts = array();
			function getVSSOrgID( $id ) {
				$key = (string) $id;
				return $this->orgs[$key] ?? null;
			}
			function getVSSSTID( $id ) {
				$key = (string) $id;
				if( array_key_exists( $key, $this->sts ) ) {
					return $this->sts[$key];
				}
				return "VSS:$id";
			}
		};
		$userInfoDAO = new class {
			function getUserInfo( $id ) { return array( 'org_id' => 777 ); }
		};

		$this->characterDAO = $characterDAO;
		$this->organizationDAO = $organizationDAO;
		$this->vssDAO = $vssDAO;
		return new ApplicationService( $organizationDAO, $characterDAO, $vssDAO, $userInfoDAO );
	}

	/**
	 * The critical asymmetry: a VSS-backed anchor must climb from the VSS's own
	 * org, not that org's parent -- applying getParentOrgID to it would skip the
	 * VSS's own org's storyteller.
	 */
	public function testVssBackedApplicationClimbsFromTheVssOwnOrgNotItsParent(): void {
		$service = $this->makeService();
		$this->characterDAO->vss = 7;
		$this->vssDAO->orgs['7'] = 40;
		$this->organizationDAO->parents = array( '40' => 140, '140' => null );
		$this->organizationDAO->sts = array( '40' => 'ST-40', '140' => 'ST-140' );

		$application = new stdClass;
		$application->character_id = 1;
		$application->org_id = 0;
		$application->user_id = 999;

		$lowSt = $service->getLowST( $application );

		$chain = $service->getEscalationSTIDs( $application, $lowSt );

		$this->assertSame( array( 'ST-40', 'ST-140' ), $chain );
	}

	/** An empty character_id application climbs from above the parent org getLowST() already resolved. */
	public function testEmptyCharacterIdClimbsAboveTheParentOrg(): void {
		$service = $this->makeService();
		$this->organizationDAO->parents = array( '5' => 105, '105' => 205, '205' => null );
		$this->organizationDAO->sts = array( '105' => 'ST-105', '205' => 'ST-205' );

		$application = new stdClass;
		$application->character_id = "";
		$application->org_id = 5;
		$application->user_id = 999;

		$lowSt = $service->getLowST( $application );
		$this->assertSame( 'ST-105', $lowSt );

		$chain = $service->getEscalationSTIDs( $application, $lowSt );

		$this->assertSame( array( 'ST-205' ), $chain );
	}

	/** A negative vss_id (org-by-negation) climbs from above that org, not from it. */
	public function testNegativeVssIdClimbsAboveTheNegatedOrg(): void {
		$service = $this->makeService();
		$this->characterDAO->vss = -60;
		$this->organizationDAO->parents = array( '60' => 160, '160' => null );
		$this->organizationDAO->sts = array( '60' => 'ST-60', '160' => 'ST-160' );

		$application = new stdClass;
		$application->character_id = 1;
		$application->org_id = 0;
		$application->user_id = 999;

		$lowSt = $service->getLowST( $application );

		$chain = $service->getEscalationSTIDs( $application, $lowSt );

		$this->assertSame( array( 'ST-160' ), $chain );
	}

	/** A zero vss_id with a character org climbs from above that org, not from it. */
	public function testZeroVssIdClimbsAboveTheCharacterOrg(): void {
		$service = $this->makeService();
		$this->characterDAO->vss = 0;
		$this->characterDAO->org = 30;
		$this->organizationDAO->parents = array( '30' => 130, '130' => null );
		$this->organizationDAO->sts = array( '30' => 'ST-30', '130' => 'ST-130' );

		$application = new stdClass;
		$application->character_id = 1;
		$application->org_id = 0;
		$application->user_id = 999;

		$lowSt = $service->getLowST( $application );

		$chain = $service->getEscalationSTIDs( $application, $lowSt );

		$this->assertSame( array( 'ST-130' ), $chain );
	}

	/** The Low ST is never reported, even when the climb re-encounters the same person. */
	public function testLowStNeverAppearsInChainEvenIfReencountered(): void {
		$service = $this->makeService();
		$this->organizationDAO->parents = array( '1' => 101, '101' => 201, '201' => null );
		$this->organizationDAO->sts = array( '101' => 'ST-SAME', '201' => 'ST-SAME' );

		$application = new stdClass;
		$application->character_id = "";
		$application->org_id = 1;
		$application->user_id = 999;

		$lowSt = $service->getLowST( $application );
		$this->assertSame( 'ST-SAME', $lowSt );

		$chain = $service->getEscalationSTIDs( $application, $lowSt );

		$this->assertSame( array(), $chain );
	}

	/** The applicant is skipped when they hold a rung, but the climb continues above them. */
	public function testApplicantIsSkippedWhenTheyHoldARung(): void {
		$service = $this->makeService();
		$this->organizationDAO->parents = array( '1' => 101, '101' => 201, '201' => 301, '301' => null );
		$this->organizationDAO->sts = array( '101' => 'ST-LOW', '201' => '42', '301' => 'ST-301' );

		$application = new stdClass;
		$application->character_id = "";
		$application->org_id = 1;
		$application->user_id = 42;

		$lowSt = $service->getLowST( $application );

		$chain = $service->getEscalationSTIDs( $application, $lowSt );

		$this->assertSame( array( 'ST-301' ), $chain );
	}

	/** A rung whose getOrgSTID() returns null contributes nothing, but the climb continues above it. */
	public function testRungWithNoStorytellerIsSkippedButClimbContinues(): void {
		$service = $this->makeService();
		$this->organizationDAO->parents = array( '10' => 20, '20' => 30, '30' => null );
		$this->organizationDAO->sts = array( '10' => 'ST-10', '20' => null, '30' => 'ST-30' );

		$chain = $service->climbFromOrg( 10 );

		$this->assertSame( array( 'ST-10', 'ST-30' ), $chain );
	}

	/** One person holding two consecutive rungs is reported only once. */
	public function testConsecutiveRungsHeldByTheSamePersonAreReportedOnce(): void {
		$service = $this->makeService();
		$this->organizationDAO->parents = array( '70' => 80, '80' => 90, '90' => null );
		$this->organizationDAO->sts = array( '70' => 'ST-70', '80' => 'ST-SAME', '90' => 'ST-SAME' );

		$chain = $service->climbFromOrg( 70 );

		$this->assertSame( array( 'ST-70', 'ST-SAME' ), $chain );
	}

	/** A self-parenting org (getParentOrgID(X) === X) terminates the climb after visiting it once. */
	public function testSelfParentingOrgTerminates(): void {
		$service = $this->makeService();
		$this->organizationDAO->parents = array( '5' => 5 );
		$this->organizationDAO->sts = array( '5' => 'ST-5' );

		$chain = $service->climbFromOrg( 5 );

		$this->assertSame( array( 'ST-5' ), $chain );
		$this->assertSame( 1, $this->organizationDAO->stCalls );
	}

	/** A mutual cycle (10<->20) terminates the climb after visiting each org once. */
	public function testMutualCycleTerminates(): void {
		$service = $this->makeService();
		$this->organizationDAO->parents = array( '10' => 20, '20' => 10 );
		$this->organizationDAO->sts = array( '10' => 'ST-10', '20' => 'ST-20' );

		$chain = $service->climbFromOrg( 10 );

		$this->assertSame( array( 'ST-10', 'ST-20' ), $chain );
		$this->assertSame( 2, $this->organizationDAO->stCalls );
	}

	/** With ever-incrementing parents (no cycle, no top), max_rungs is the only thing that stops the climb. */
	public function testMaxRungsBackstopWithEverIncrementingParents(): void {
		$service = $this->makeService();
		// No parent/ST maps configured: the stub's default is parent = id + 1 and
		// storyteller = "ORG:$id", so this org chain never terminates on its own.

		$chain = $service->climbFromOrg( 1, null, 5 );

		$this->assertSame( array( 'ORG:1', 'ORG:2', 'ORG:3', 'ORG:4', 'ORG:5' ), $chain );
		$this->assertSame( 5, $this->organizationDAO->stCalls );
	}

	/** A missing character (readByID returns null) yields a null Low ST, without warning. */
	public function testMissingCharacterYieldsNullLowStWithNoWarning(): void {
		$service = $this->makeService();
		$this->characterDAO->missing = true;

		$application = new stdClass;
		$application->character_id = 1;
		$application->org_id = 0;

		$this->assertNull( $service->getLowST( $application ) );
	}

	/**
	 * A missing character still produces an escalation chain, anchored on the
	 * application's own org. Anchoring on null instead would yield an empty chain
	 * and notify nobody -- reintroducing, for corrupt rows, the exact silent drop
	 * this feature exists to remove.
	 */
	public function testMissingCharacterStillEscalatesFromTheApplicationOrg(): void {
		$service = $this->makeService();
		$this->characterDAO->missing = true;
		$this->organizationDAO->parents = array( '50' => 60, '60' => null );

		$application = new stdClass;
		$application->character_id = 1;
		$application->org_id = 50;
		$application->user_id = 999;

		// Climb starts at parent(50) = 60, which is where the low ST would have been sought.
		$this->assertSame( array( 'ORG:60' ), $service->getEscalationSTIDs( $application, null ) );
	}

	/**
	 * A positive vss_id (MoveCharacter2's sign convention for a real vsss row)
	 * climbs from the VSS's OWN org, not that org's parent -- the same
	 * asymmetry getEscalationSTIDs() applies for a VSS-backed application
	 * anchor, since the VSS's storyteller sits below org level.
	 */
	public function testPositiveVssIdClimbsFromTheVssOwnOrg(): void {
		$service = $this->makeService();
		$this->vssDAO->orgs['7'] = 40;
		$this->organizationDAO->parents = array( '40' => 140, '140' => null );
		$this->organizationDAO->sts = array( '40' => 'ST-40', '140' => 'ST-140' );

		$chain = $service->getEscalationSTIDsForVssSelection( 7 );

		$this->assertSame( array( 'ST-40', 'ST-140' ), $chain );
	}

	/**
	 * A negative vss_id (MoveCharacter2's sign convention for an org, negated)
	 * climbs from above that org's PARENT, not the org itself -- its
	 * storyteller was already the officer being escalated above.
	 */
	public function testNegativeVssIdClimbsFromTheParentOfTheNegatedOrg(): void {
		$service = $this->makeService();
		$this->organizationDAO->parents = array( '60' => 160, '160' => null );
		$this->organizationDAO->sts = array( '60' => 'ST-60', '160' => 'ST-160' );

		$chain = $service->getEscalationSTIDsForVssSelection( -60 );

		$this->assertSame( array( 'ST-160' ), $chain );
	}

	/** A zero vss_id means no VSS was selected, so there is no officer to escalate above. */
	public function testZeroVssIdReturnsEmptyChain(): void {
		$service = $this->makeService();

		$this->assertSame( array(), $service->getEscalationSTIDsForVssSelection( 0 ) );
	}

	/** The excluded storyteller id is still honored when climbing from a positive vss_id. */
	public function testPositiveVssIdExcludesGivenStorytellerId(): void {
		$service = $this->makeService();
		$this->vssDAO->orgs['7'] = 40;
		$this->organizationDAO->parents = array( '40' => 140, '140' => null );
		$this->organizationDAO->sts = array( '40' => 'ST-SAME', '140' => 'ST-SAME' );

		$chain = $service->getEscalationSTIDsForVssSelection( 7, 'ST-SAME' );

		$this->assertSame( array(), $chain );
	}
}
