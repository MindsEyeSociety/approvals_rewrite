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
	 * Resolves the user id of the "low storyteller" responsible for approving an application.
	 *
	 * Branches on the application's character (when set) or its org (when not):
	 * - No character_id: the storyteller of the org's parent org.
	 * - character.vss_id < 0: the storyteller of the org identified by -vss_id.
	 * - character.vss_id == 0: the storyteller of the character's own org, or, when the
	 *   character has no org, the storyteller of the owning player's org (looked up via
	 *   UserInfoDAO::getUserInfo, which returns an associative array).
	 * - character.vss_id > 0: the storyteller of that VSS.
	 *
	 * @return mixed the resolved storyteller's user id
	 */
	function getLowST( $application ) {
		if( $application->character_id == "" ) {
			$parent_org_id = $this->organizationDAO->getParentOrgID( $application->org_id );
			$st_id = $this->organizationDAO->getOrgSTID( $parent_org_id );
		} else {
			$character = $this->characterDAO->readByID( $application->character_id );

			if( $character->vss_id < 0 ) {
				$st_id = $this->organizationDAO->getOrgSTID( 0-$character->vss_id );
			} else if( $character->vss_id == 0 ) {
				if( $character->org_id == 0 ) {
					$userinfo = $this->userInfoDAO->getUserInfo( $character->user_id );
					$org_id = $userinfo['org_id'];
				} else {
					$org_id = $character->org_id;
				}
				$st_id = $this->organizationDAO->getOrgSTID( $org_id );
			} else {
				$st_id = $this->vssDAO->getVSSSTID( $character->vss_id );
			}
		}
		return $st_id;
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

	function getApplicationStorytellers( $applocation ) {

	}

}
