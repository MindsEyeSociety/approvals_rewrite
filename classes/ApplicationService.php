<?php
include_once("classes/OrganizationDAO.php");
include_once("classes/CharacterDAO.class.php");
include_once("classes/VSSDAO.class.php");
include_once("classes/UserInfoDAO.php");

class ApplicationService {

	var $organizationDAO;
	var $characterDAO;
	var $vssDAO;
	var $userInfoDAO;

	function __construct( $organizationDAO, $characterDAO, $vssDAO, $userInfoDAO ) {
		$this->organizationDAO = $organizationDAO;
		$this->characterDAO = $characterDAO;
		$this->vssDAO = $vssDAO;
		$this->userInfoDAO = $userInfoDAO;
	}

	/**
	 * Resolves the anchor that both getLowST() and getEscalationSTIDs() climb from, so
	 * the two never drift apart on which branch applies to a given application.
	 *
	 * Branches on the application's character (when set) or its org (when not):
	 * - No character_id: the storyteller of the org's parent org.
	 * - character.vss_id < 0: the storyteller of the org identified by -vss_id.
	 * - character.vss_id == 0: the storyteller of the character's own org, or, when the
	 *   character has no org, the storyteller of the owning player's org (looked up via
	 *   UserInfoDAO::getUserInfo, which returns an associative array).
	 * - character.vss_id > 0: the storyteller of that VSS.
	 *
	 * The 'vss' anchor deliberately reports the raw vss_id rather than the VSS's org --
	 * resolving that requires a DB lookup (VSSDAO::getVSSOrgID) that only the escalation
	 * climb needs, and getLowST() must not trigger it.
	 *
	 * @param $application The application being routed; reads character_id and org_id,
	 *   and, when a character is set, the character's vss_id/org_id/user_id.
	 * @return array{st_id: mixed, anchor_kind: string, anchor_id: mixed} st_id is the
	 *   resolved storyteller's user id (or null if none could be found); anchor_kind is
	 *   'org' or 'vss', identifying what anchor_id refers to; anchor_id is the org id
	 *   (for 'org') or the raw vss_id (for 'vss').
	 * @example
	 *   $anchor = $service->resolveApprovalAnchor( $application );
	 *   // => array( 'st_id' => 42, 'anchor_kind' => 'vss', 'anchor_id' => 7 )
	 * @see ApplicationService::getLowST()
	 * @see ApplicationService::getEscalationSTIDs()
	 */
	private function resolveApprovalAnchor( $application ) {
		if( $application->character_id == "" ) {
			$parent_org_id = $this->organizationDAO->getParentOrgID( $application->org_id );
			$st_id = $this->organizationDAO->getOrgSTID( $parent_org_id );
			return array( 'st_id' => $st_id, 'anchor_kind' => 'org', 'anchor_id' => $parent_org_id );
		}

		$character = $this->characterDAO->readByID( $application->character_id );
		if( !is_object( $character ) ) {
			// The character row is missing (deleted or corrupt), so no low ST can be
			// resolved. Anchor on the application's own org anyway: escalation can then
			// still reach somebody, which is the whole point. Anchoring on null would
			// produce an empty chain and notify nobody -- the silent drop being fixed here.
			return array( 'st_id' => null, 'anchor_kind' => 'org', 'anchor_id' => $application->org_id );
		}

		if( $character->vss_id < 0 ) {
			$org_id = 0 - $character->vss_id;
			$st_id = $this->organizationDAO->getOrgSTID( $org_id );
			return array( 'st_id' => $st_id, 'anchor_kind' => 'org', 'anchor_id' => $org_id );
		} else if( $character->vss_id == 0 ) {
			if( $character->org_id == 0 ) {
				$userinfo = $this->userInfoDAO->getUserInfo( $character->user_id );
				$org_id = $userinfo['org_id'] ?? null;
			} else {
				$org_id = $character->org_id;
			}
			$st_id = $this->organizationDAO->getOrgSTID( $org_id );
			return array( 'st_id' => $st_id, 'anchor_kind' => 'org', 'anchor_id' => $org_id );
		} else {
			$st_id = $this->vssDAO->getVSSSTID( $character->vss_id );
			return array( 'st_id' => $st_id, 'anchor_kind' => 'vss', 'anchor_id' => $character->vss_id );
		}
	}

	/**
	 * Resolves the user id of the "low storyteller" responsible for approving an application.
	 *
	 * @return mixed the resolved storyteller's user id
	 * @see ApplicationService::resolveApprovalAnchor()
	 */
	function getLowST( $application ) {
		return $this->resolveApprovalAnchor( $application )['st_id'];
	}

	/**
	 * Produces the ordered list of storytellers above an application's Low ST, for use
	 * when the Low ST's MES membership has lapsed and they cannot be emailed about a
	 * pending approval.
	 *
	 * Resolves the same anchor getLowST() would have resolved (see
	 * resolveApprovalAnchor()), then starts the climb one rung above it. A VSS-backed
	 * anchor climbs from the VSS's OWN org, because a VSS's storyteller_id sits below
	 * org level, so the next officer up IS that org's ST. An org-backed anchor climbs
	 * from the org's PARENT, because getLowST() already resolved the org's own ST, so
	 * the climb must start above it. Applying getParentOrgID uniformly to both would
	 * skip the domain ST for every venue-scoped application -- the most common shape in
	 * this system.
	 *
	 * @param $application The application being escalated; also used to omit the
	 *   applicant themself from the chain -- storytellers routinely submit their own
	 *   applications, and escalating to yourself is both a conflict of interest and
	 *   pointless.
	 * @param $low_st_id The Low ST's user id, excluded from the returned chain even if
	 *   the climb would otherwise re-encounter them.
	 * @param $max_rungs Safety cap on how many orgs to climb before giving up.
	 * @return array Ordered list of distinct storyteller user ids above the Low ST,
	 *   nearest first, with no email/membership filtering applied -- the caller decides
	 *   which of these can actually be reached. Empty when there is nowhere left to climb.
	 * @example
	 *   // character with vss_id = 7; VSS 7 is owned by org 40; org 40's parent is org 140
	 *   $service->getEscalationSTIDs( $application, $lowStId );
	 *   // => array( 'ORG:40', 'ORG:140' )
	 * @see ApplicationService::getLowST()
	 * @see ApplicationService::climbFromOrg()
	 * @see ApplicationService::resolveApprovalAnchor()
	 */
	function getEscalationSTIDs( $application, $low_st_id, $max_rungs = 12 ) {
		$anchor = $this->resolveApprovalAnchor( $application );

		if( $anchor['anchor_kind'] == 'vss' ) {
			$org_id = $this->vssDAO->getVSSOrgID( $anchor['anchor_id'] );
		} else {
			$org_id = $this->organizationDAO->getParentOrgID( $anchor['anchor_id'] );
		}

		$chain = $this->climbFromOrg( $org_id, $low_st_id, $max_rungs );

		$applicant_id = (string) $application->user_id;
		return array_values( array_filter( $chain, function( $st_id ) use ( $applicant_id ) {
			return (string) $st_id !== $applicant_id;
		} ) );
	}

	/**
	 * Climbs the org hierarchy from a starting org, collecting the distinct
	 * storytellers found at each rung, nearest first.
	 *
	 * Generic on purpose -- it knows nothing about applications, so any other caller
	 * needing "the chain of officers above this org" can reuse it directly.
	 *
	 * @param $org_id Org id to start climbing from (its own storyteller is included).
	 * @param $exclude_st_id A storyteller id to omit from the result even if
	 *   encountered while climbing (typically the Low ST who triggered the escalation)
	 *   -- null to exclude none.
	 * @param $max_rungs Safety cap on how many orgs to climb before giving up, guarding
	 *   against a corrupt parent-org chain that never terminates.
	 * @return array Ordered list of distinct storyteller user ids, nearest first. The
	 *   climb stops at the top of the org hierarchy, on revisiting an org already
	 *   climbed through (defends against a self- or mutually-parenting org), or at
	 *   $max_rungs, whichever comes first. Never includes $exclude_st_id or an
	 *   empty/null storyteller.
	 * @example
	 *   $service->climbFromOrg( 40 );
	 *   // => array( 'ORG:40', 'ORG:140' ) -- two distinct officers, org 40 then its parent
	 * @see ApplicationService::getEscalationSTIDs()
	 */
	function climbFromOrg( $org_id, $exclude_st_id = null, $max_rungs = 12 ) {
		$seen_st_ids = array();
		if( $exclude_st_id !== null ) {
			$seen_st_ids[] = (string) $exclude_st_id;
		}
		$seen_org_ids = array();
		$chain = array();

		$rungs = 0;
		while( $org_id !== null && $org_id !== '' && $rungs < $max_rungs ) {
			if( in_array( (string) $org_id, $seen_org_ids, true ) ) {
				break;
			}
			$seen_org_ids[] = (string) $org_id;

			$st_id = $this->organizationDAO->getOrgSTID( $org_id );
			if( $st_id !== null && $st_id !== '' && !in_array( (string) $st_id, $seen_st_ids, true ) ) {
				$chain[] = $st_id;
				$seen_st_ids[] = (string) $st_id;
			}

			$org_id = $this->organizationDAO->getParentOrgID( $org_id );
			$rungs++;
		}

		return $chain;
	}

	/**
	 * Produces the ordered list of storytellers above the officer identified by
	 * a sign-encoded VSS selection, for use when that officer's MES membership
	 * has lapsed and they cannot be emailed about a character requesting to
	 * join their venue.
	 *
	 * Mirrors the sign convention MoveCharacter2.php (and getLowST()) use for a
	 * VSS selection: a positive $vss_id identifies a real vsss row whose
	 * storyteller sits BELOW org level, so the climb starts at the VSS's OWN
	 * org -- that org's storyteller has not yet been notified. A negative
	 * $vss_id identifies an org (via -$vss_id) whose storyteller sits AT that
	 * org, so the climb starts at the org's PARENT, one rung above the officer
	 * already resolved. Applying getParentOrgID uniformly to both would skip
	 * the VSS's own org's storyteller for the positive case -- the same
	 * asymmetry getEscalationSTIDs() applies for an application's anchor.
	 *
	 * @param $vss_id The sign-encoded selection: positive is a vsss.id,
	 *   negative is -1 times an organizations.id, 0 means no VSS was selected.
	 * @param $exclude_st_id The officer's own user id, excluded from the
	 *   returned chain even if the climb would otherwise re-encounter them.
	 * @param $max_rungs Safety cap on how many orgs to climb before giving up.
	 * @return array Ordered list of distinct storyteller user ids above the
	 *   officer, nearest first. Empty when $vss_id is 0 or there is nowhere
	 *   left to climb.
	 * @example
	 *   // vss 7 belongs to org 40
	 *   $service->getEscalationSTIDsForVssSelection( 7, $storytellerId );
	 *   // => the chain of storytellers climbing from org 40
	 * @see ApplicationService::getEscalationSTIDs()
	 * @see ApplicationService::climbFromOrg()
	 */
	function getEscalationSTIDsForVssSelection( $vss_id, $exclude_st_id = null, $max_rungs = 12 ) {
		if( $vss_id == 0 ) {
			return array();
		}

		if( $vss_id > 0 ) {
			$org_id = $this->vssDAO->getVSSOrgID( $vss_id );
		} else {
			$org_id = $this->organizationDAO->getParentOrgID( 0 - $vss_id );
		}

		return $this->climbFromOrg( $org_id, $exclude_st_id, $max_rungs );
	}

	/**
	 * Maps a tier rank to the org level whose storyteller governs it, for
	 * getSTForTier() and getEscalationSTIDsForTier() to share -- ranks 2-5
	 * only, since rank 1 (Low) is resolved by getLowST() instead of an org
	 * level lookup.
	 *
	 * @return array<int,string> tier rank => OrganizationDAO::getSTIDAtLevel() level name
	 */
	private static function tierLevels() {
		return array( 2 => 'domain', 3 => 'region', 4 => 'nation', 5 => 'globe' );
	}

	/**
	 * Resolves the org id that anchors a tier-2-and-above officer lookup for an
	 * application: the same org resolveApprovalAnchor() resolves for
	 * getLowST()/getEscalationSTIDs(), except a 'vss' anchor is resolved to its
	 * OWN org (via VSSDAO::getVSSOrgID()) since a VSS's storyteller sits below
	 * org level, whereas an 'org' anchor's id already IS an org id.
	 *
	 * @param $application The application being routed; see resolveApprovalAnchor().
	 * @return mixed The anchor org's id, or null when it cannot be resolved (e.g. a
	 *   VSS with no matching row).
	 * @see ApplicationService::resolveApprovalAnchor()
	 * @see ApplicationService::getSTForTier()
	 * @see ApplicationService::getEscalationSTIDsForTier()
	 */
	private function resolveTierAnchorOrgID( $application ) {
		$anchor = $this->resolveApprovalAnchor( $application );
		if( $anchor['anchor_kind'] == 'vss' ) {
			return $this->vssDAO->getVSSOrgID( $anchor['anchor_id'] );
		}
		return $anchor['anchor_id'];
	}

	/**
	 * Resolves the officer responsible for approving an application at a given
	 * tier, so a ratchet to that tier (see AppDetails.php) can notify them
	 * instead of leaving the application to sit unnoticed -- the same class of
	 * silence already fixed for a suppressed Low ST notification (see
	 * notifyOfficerOrEscalate()), now applied to every tier above Low.
	 *
	 * Maps the tier rank to an org level and resolves that level's storyteller
	 * within the application's anchor org (see resolveTierAnchorOrgID()), the
	 * same org resolveApprovalAnchor() resolves for getLowST():
	 *   1 Low    -> getLowST( $application )        (VST / venue-scoped, unchanged)
	 *   2 Mid    -> the 'domain' officer (DST)
	 *   3 High   -> the 'region' officer (RST)
	 *   4 Top    -> the 'nation' officer (NST)
	 *   5 Global -> the 'globe' officer
	 *
	 * @param $application The application being routed; see resolveApprovalAnchor().
	 * @param $tier_rank The tier rank (1-5, per approvalTierRank()) whose officer is wanted.
	 * @return mixed The resolved officer's user id, or null when no officer can be
	 *   resolved -- an unrecognised tier_rank, an anchor with no resolvable org, or a
	 *   level the org's branch has no value at (see OrganizationDAO::getSTIDAtLevel()).
	 * @example $service->getSTForTier( $application, 2 ); // => the Mid (domain) storyteller's user id
	 * @see ApplicationService::resolveApprovalAnchor()
	 * @see OrganizationDAO::getSTIDAtLevel()
	 */
	function getSTForTier( $application, $tier_rank ) {
		if( $tier_rank == 1 ) {
			return $this->getLowST( $application );
		}

		$levels = self::tierLevels();
		if( !isset( $levels[$tier_rank] ) ) {
			return null;
		}

		$org_id = $this->resolveTierAnchorOrgID( $application );
		if( $org_id === null || $org_id === '' ) {
			return null;
		}

		return $this->organizationDAO->getSTIDAtLevel( $org_id, $levels[$tier_rank] );
	}

	/**
	 * Produces the ordered list of storytellers above a tier officer, for use
	 * when that officer's notification is suppressed and needs to be escalated
	 * (see EmailService::sendTierNotificationEmail()).
	 *
	 * For rank 1 (Low), delegates entirely to the existing getEscalationSTIDs().
	 * For ranks 2-5, climbs from the org AT the target level (see
	 * OrganizationDAO::getOrgIDAtLevel()), excluding $officer_id -- mirroring
	 * the VSS-anchor asymmetry getEscalationSTIDs() applies: starting the climb
	 * AT that org (rather than its parent) is what lets climbFromOrg() exclude
	 * the tier officer's own rung while still picking up every officer above
	 * them, instead of skipping straight past the next rung up.
	 *
	 * @param $application The application being escalated; see getSTForTier().
	 * @param $tier_rank The tier rank (1-5) $officer_id was resolved for.
	 * @param $officer_id The tier officer's user id, excluded from the returned chain.
	 * @param $max_rungs Safety cap on how many orgs to climb before giving up.
	 * @return array Ordered list of distinct storyteller user ids above the tier
	 *   officer, nearest first. Empty when no officer can be resolved at that tier's
	 *   level, or there is nowhere left to climb.
	 * @see ApplicationService::getSTForTier()
	 * @see ApplicationService::climbFromOrg()
	 * @see EmailService::sendTierNotificationEmail()
	 */
	function getEscalationSTIDsForTier( $application, $tier_rank, $officer_id, $max_rungs = 12 ) {
		if( $tier_rank == 1 ) {
			return $this->getEscalationSTIDs( $application, $officer_id, $max_rungs );
		}

		$levels = self::tierLevels();
		if( !isset( $levels[$tier_rank] ) ) {
			return array();
		}

		$org_id = $this->resolveTierAnchorOrgID( $application );
		if( $org_id === null || $org_id === '' ) {
			return array();
		}

		$level_org_id = $this->organizationDAO->getOrgIDAtLevel( $org_id, $levels[$tier_rank] );
		if( $level_org_id === null ) {
			return array();
		}

		return $this->climbFromOrg( $level_org_id, $officer_id, $max_rungs );
	}

	function determineApplicationOrganization( $application ) {
		if ( is_object($application->character) && strtoupper($application->character->char_type)!="NPC" ) {
			$user_info = $this->userInfoDAO->getUserInfo($application->user_id);
			if( $application->character->vss_id > 0 && $application->character->vss_id != '' ) {
				$organization = $this->organizationDAO->readByVSSID( $application->character->vss_id );
			} else {
				$organization = $this->organizationDAO->readByUserID( $application->user_id );
			}
		} elseif( isset( $_GET['appuser_id'] ) )  {
			$user_info = $this->userInfoDAO->getUserInfo($_GET['appuser_id']);
			$organization = $this->organizationDAO->readByUserID( $_GET['appuser_id'] );
		} else {
			$user_info = $this->userInfoDAO->getUserInfo($_SESSION['user_id']);
			$organization = $this->organizationDAO->readByUserID( $_SESSION['user_id'] );
		}
		if($organization) return $organization;


		$organization = null;
		if( $application == null ) return null;
		if( is_object( $application->character ) && $application->character->vss_id != 0 && is_numeric($application->character->vss_id ) ) {
			$organization = $this->organizationDAO->readByVSSID( $application->character->vss_id );
		} elseif ( is_object( $application->character ) ) {
			$organization = $this->organizationDAO->readByUserID( $application->user_id );
		}
		return $organization;
	}

}
