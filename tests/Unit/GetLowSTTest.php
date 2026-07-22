<?php
require_once 'classes/ApplicationService.php';

/**
 * Unit tests for ApplicationService::getLowST(), covering all four routing
 * branches (org-only application, negative/zero/positive character vss_id)
 * against stub DAOs so no database is required.
 *
 * @see ApplicationService::getLowST()
 */
final class GetLowSTTest extends \PHPUnit\Framework\TestCase {

	/**
	 * Builds an ApplicationService wired with stub DAOs that echo their inputs
	 * back in a recognisable form, so assertions can trace which branch ran.
	 */
	private function makeService(): ApplicationService {
		$characterDAO = new class {
			public $vss = 0;
			public $org = 0;
			public $uid = 0;
			function readByID( $id ) {
				$o = new stdClass;
				$o->vss_id = $this->vss;
				$o->org_id = $this->org;
				$o->user_id = $this->uid;
				return $o;
			}
		};
		$orgDAO = new class {
			function getOrgSTID( $id ) { return "ORG:$id"; }
			function getParentOrgID( $id ) { return $id + 1000; }
		};
		$vssDAO = new class {
			function getVSSSTID( $id ) { return "VSS:$id"; }
		};
		$userDAO = new class {
			function getUserInfo( $id ) { return [ 'org_id' => 777 ]; }
		};

		$this->characterDAO = $characterDAO;
		return new ApplicationService( $orgDAO, $characterDAO, $vssDAO, $userDAO );
	}

	private $characterDAO;

	/** vss_id < 0 resolves via the org identified by -vss_id. */
	public function testNegativeVssIdResolvesOrgByNegation(): void {
		$service = $this->makeService();
		$this->characterDAO->vss = -50;

		$application = new stdClass;
		$application->character_id = 1;
		$application->org_id = 0;

		$this->assertSame( "ORG:50", $service->getLowST( $application ) );
	}

	/** vss_id == 0 with a character org resolves that org's storyteller. */
	public function testZeroVssIdWithCharacterOrgResolvesCharacterOrg(): void {
		$service = $this->makeService();
		$this->characterDAO->vss = 0;
		$this->characterDAO->org = 30;

		$application = new stdClass;
		$application->character_id = 1;
		$application->org_id = 0;

		$this->assertSame( "ORG:30", $service->getLowST( $application ) );
	}

	/** vss_id == 0 with no character org falls back to the owning player's org. */
	public function testZeroVssIdWithNoCharacterOrgFallsBackToPlayerOrg(): void {
		$service = $this->makeService();
		$this->characterDAO->vss = 0;
		$this->characterDAO->org = 0;
		$this->characterDAO->uid = 5;

		$application = new stdClass;
		$application->character_id = 1;
		$application->org_id = 0;

		$this->assertSame( "ORG:777", $service->getLowST( $application ) );
	}

	/** vss_id > 0 resolves the VSS's own storyteller. */
	public function testPositiveVssIdResolvesVssStoryteller(): void {
		$service = $this->makeService();
		$this->characterDAO->vss = 7;

		$application = new stdClass;
		$application->character_id = 1;
		$application->org_id = 0;

		$this->assertSame( "VSS:7", $service->getLowST( $application ) );
	}

	/** An empty character_id skips the character branch and uses the org's parent org. */
	public function testEmptyCharacterIdResolvesParentOrg(): void {
		$service = $this->makeService();

		$application = new stdClass;
		$application->character_id = "";
		$application->org_id = 5;

		$this->assertSame( "ORG:1005", $service->getLowST( $application ) );
	}
}
