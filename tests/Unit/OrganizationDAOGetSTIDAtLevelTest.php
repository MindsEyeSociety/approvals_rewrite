<?php
require_once 'classes/OrganizationDAO.php';
require_once 'tests/Unit/support/RecordingDb.php';

/**
 * Unit tests for OrganizationDAO::getOrgIDAtLevel() and getSTIDAtLevel(), used
 * to notify the officer who governs an application's current approval tier
 * (see ApplicationService::getSTForTier()).
 *
 * Both methods resolve the org at a target level ('domain', 'region',
 * 'nation', 'globe') within another org's OWN hierarchy branch by blanking
 * every column deeper than that level and matching on the rest -- the same
 * pattern AppDetailsTopSection.php builds inline. These tests pin:
 * - every dynamic value is bound as a parameter, never spliced into the SQL text,
 * - each level blanks exactly the columns deeper than it,
 * - null is returned (without a warning) when the org has no value at the
 *   requested level, when the org itself doesn't exist, or when no org matches,
 * - getSTIDAtLevel() resolves the matched org's storyteller via getOrgSTID(),
 *   not a raw admin_user_id read.
 *
 * @see OrganizationDAO::getOrgIDAtLevel()
 * @see OrganizationDAO::getSTIDAtLevel()
 * @see ApplicationService::getSTForTier()
 */
final class OrganizationDAOGetSTIDAtLevelTest extends \PHPUnit\Framework\TestCase {

	private const TAINTED = "1' OR '1'='1";

	/** getOrgIDAtLevel() binds the domain/region/nation values as parameters at 'domain' level, blanking only chapter. */
	public function testGetOrgIDAtLevelDomainBindsParamsAndBlanksChapterOnly(): void {
		$db = new RecordingDb( [
			[ [ 'domain' => self::TAINTED, 'region' => 'MidAtl', 'nation' => 'US' ] ],
			[ [ 'id' => 40 ] ],
		] );
		$dao = new OrganizationDAO( $db );

		$result = $dao->getOrgIDAtLevel( 5, 'domain' );

		$this->assertSame( 40, $result );
		$sql = $db->queries[1]['sql'];
		$this->assertStringNotContainsString( self::TAINTED, $sql );
		$this->assertStringContainsString( "chapter=''", $sql );
		$this->assertStringContainsString( 'domain=?', $sql );
		$this->assertStringContainsString( 'region=?', $sql );
		$this->assertStringContainsString( 'nation=?', $sql );
		$this->assertSame( [ self::TAINTED, 'MidAtl', 'US' ], $db->queries[1]['params'] );
	}

	/** getOrgIDAtLevel() at 'region' level blanks domain in addition to chapter, binding only region/nation. */
	public function testGetOrgIDAtLevelRegionBlanksDomainToo(): void {
		$db = new RecordingDb( [
			[ [ 'domain' => 'NoVA', 'region' => self::TAINTED, 'nation' => 'US' ] ],
			[ [ 'id' => 41 ] ],
		] );
		$dao = new OrganizationDAO( $db );

		$result = $dao->getOrgIDAtLevel( 5, 'region' );

		$this->assertSame( 41, $result );
		$sql = $db->queries[1]['sql'];
		$this->assertStringNotContainsString( self::TAINTED, $sql );
		$this->assertStringContainsString( "chapter=''", $sql );
		$this->assertStringContainsString( "domain=''", $sql );
		$this->assertStringContainsString( 'region=?', $sql );
		$this->assertStringContainsString( 'nation=?', $sql );
		$this->assertSame( [ self::TAINTED, 'US' ], $db->queries[1]['params'] );
	}

	/** getOrgIDAtLevel() at 'nation' level blanks domain and region, binding only nation. */
	public function testGetOrgIDAtLevelNationBlanksDomainAndRegion(): void {
		$db = new RecordingDb( [
			[ [ 'domain' => 'NoVA', 'region' => 'MidAtl', 'nation' => self::TAINTED ] ],
			[ [ 'id' => 42 ] ],
		] );
		$dao = new OrganizationDAO( $db );

		$result = $dao->getOrgIDAtLevel( 5, 'nation' );

		$this->assertSame( 42, $result );
		$sql = $db->queries[1]['sql'];
		$this->assertStringNotContainsString( self::TAINTED, $sql );
		$this->assertStringContainsString( "chapter=''", $sql );
		$this->assertStringContainsString( "domain=''", $sql );
		$this->assertStringContainsString( "region=''", $sql );
		$this->assertStringContainsString( 'nation=?', $sql );
		$this->assertSame( [ self::TAINTED ], $db->queries[1]['params'] );
	}

	/** getOrgIDAtLevel() at 'globe' level blanks domain, region, and nation, binding nothing. */
	public function testGetOrgIDAtLevelGlobeBlanksEverythingBelowIt(): void {
		$db = new RecordingDb( [
			[ [ 'domain' => 'NoVA', 'region' => 'MidAtl', 'nation' => 'US' ] ],
			[ [ 'id' => 43 ] ],
		] );
		$dao = new OrganizationDAO( $db );

		$result = $dao->getOrgIDAtLevel( 5, 'globe' );

		$this->assertSame( 43, $result );
		$sql = $db->queries[1]['sql'];
		$this->assertStringContainsString( "chapter=''", $sql );
		$this->assertStringContainsString( "domain=''", $sql );
		$this->assertStringContainsString( "region=''", $sql );
		$this->assertStringContainsString( "nation=''", $sql );
		$this->assertSame( [], $db->queries[1]['params'] );
	}

	/** getOrgIDAtLevel() returns null, with no second query, when the org has no value at the requested level. */
	public function testGetOrgIDAtLevelReturnsNullWhenOrgHasNoValueAtThatLevel(): void {
		$db = new RecordingDb( [
			[ [ 'domain' => '', 'region' => 'MidAtl', 'nation' => 'US' ] ],
		] );
		$dao = new OrganizationDAO( $db );

		$this->assertNull( $dao->getOrgIDAtLevel( 5, 'domain' ) );
		$this->assertCount( 1, $db->queries );
	}

	/** getOrgIDAtLevel() returns null when no org matches at the target level. */
	public function testGetOrgIDAtLevelReturnsNullWhenNoOrgMatches(): void {
		$db = new RecordingDb( [
			[ [ 'domain' => 'NoVA', 'region' => 'MidAtl', 'nation' => 'US' ] ],
			[],
		] );
		$dao = new OrganizationDAO( $db );

		$this->assertNull( $dao->getOrgIDAtLevel( 5, 'domain' ) );
	}

	/** getOrgIDAtLevel() returns null, without a warning, when the org itself doesn't exist. */
	public function testGetOrgIDAtLevelReturnsNullWhenOrgMissing(): void {
		$db = new RecordingDb( [ [] ] );
		$dao = new OrganizationDAO( $db );

		$this->assertNull( $dao->getOrgIDAtLevel( 999, 'domain' ) );
	}

	/** getOrgIDAtLevel() returns null for an unrecognised level, without querying further. */
	public function testGetOrgIDAtLevelReturnsNullForUnrecognisedLevel(): void {
		$db = new RecordingDb( [
			[ [ 'domain' => 'NoVA', 'region' => 'MidAtl', 'nation' => 'US' ] ],
		] );
		$dao = new OrganizationDAO( $db );

		$this->assertNull( $dao->getOrgIDAtLevel( 5, 'chapter' ) );
		$this->assertCount( 1, $db->queries );
	}

	/** getSTIDAtLevel() resolves the matched org's storyteller via getOrgSTID(), not a raw admin_user_id read. */
	public function testGetSTIDAtLevelResolvesStorytellerOfMatchedOrg(): void {
		$db = new RecordingDb( [
			[ [ 'domain' => 'NoVA', 'region' => 'MidAtl', 'nation' => 'US' ] ],
			[ [ 'id' => 40 ] ],
			[ [ 'globe' => '', 'nation' => 'US', 'region' => 'MidAtl', 'domain' => 'NoVA', 'chapter' => '', 'admin_user_id' => 77 ] ],
		] );
		$dao = new OrganizationDAO( $db );

		$this->assertSame( 77, $dao->getSTIDAtLevel( 5, 'domain' ) );
	}

	/**
	 * getSTIDAtLevel() climbs past an org with an empty admin_user_id (35 such
	 * orgs in production) via getOrgSTID()'s own recursion, exactly as every
	 * other officer lookup in this system does.
	 */
	public function testGetSTIDAtLevelClimbsPastEmptyAdminUserID(): void {
		$db = new RecordingDb( [
			[ [ 'domain' => 'NoVA', 'region' => 'MidAtl', 'nation' => 'US' ] ],
			[ [ 'id' => 40 ] ],
			// getOrgSTID(40): empty admin_user_id, so it climbs to the parent org.
			[ [ 'globe' => '', 'nation' => 'US', 'region' => 'MidAtl', 'domain' => 'NoVA', 'chapter' => '', 'admin_user_id' => '' ] ],
			// getParentOrgID(40): domain-level org's parent is the region-level org.
			[ [ 'globe' => '', 'nation' => 'US', 'region' => 'MidAtl', 'domain' => 'NoVA', 'chapter' => '' ] ],
			[ [ 'id' => 41 ] ],
			// getOrgSTID(41): the region-level org has a real storyteller.
			[ [ 'globe' => '', 'nation' => 'US', 'region' => 'MidAtl', 'domain' => '', 'chapter' => '', 'admin_user_id' => 88 ] ],
		] );
		$dao = new OrganizationDAO( $db );

		$this->assertSame( 88, $dao->getSTIDAtLevel( 5, 'domain' ) );
	}

	/** getSTIDAtLevel() returns null when getOrgIDAtLevel() found no org at that level, without querying for a storyteller. */
	public function testGetSTIDAtLevelReturnsNullWhenNoOrgAtThatLevel(): void {
		$db = new RecordingDb( [
			[ [ 'domain' => '', 'region' => 'MidAtl', 'nation' => 'US' ] ],
		] );
		$dao = new OrganizationDAO( $db );

		$this->assertNull( $dao->getSTIDAtLevel( 5, 'domain' ) );
		$this->assertCount( 1, $db->queries );
	}
}
