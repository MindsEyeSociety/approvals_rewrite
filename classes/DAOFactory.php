<?php
/**
 * DAOFactory.php
 *
 */
include_once("classes/ApplicationDAO.php");
include_once("classes/CharacterDAO.class.php");
include_once("classes/CommentDAO.php");
include_once("classes/InstructionsDAO.php");
include_once("classes/OrganizationDAO.php");
include_once("classes/UserInfoDAO.php");
include_once("classes/VenueDAO.php");
include_once("classes/VSSDAO.class.php");
include_once("classes/FilterDAO.php");
include_once("classes/MechanicDAO.class.php");
include_once("classes/PlotkitDAO.class.php");
include_once("classes/RevisionDAO.class.php");
include_once("classes/CategoryDAO.class.php");

class DAOFactory {
	var $db;
	var $portal_db;

	var $daoInstances = array();

	function __construct( $db, $portal_db = null ){
		$this->db = $db;
		$this->portal_db = $portal_db;
	}

	function __call( $name, $arguments ) {
		if( substr( $name, 0, 3 ) != "get" ) {
			return;
		}
		$className = substr( $name, 3 );
		return $this->getSimpleDAO($className);
	}

	/**
	 * getInstance implements the Singleton pattern for the DAO Factory
	 */
	public static function getInstance() {
		static $instance;
		global $portal_db;
		global $db;

		if (!isset($instance)) {
			$instance = new DAOFactory( $db, $portal_db );
		}
		return $instance;
	}

	function getSimpleDAO( $name ) {
		$classes = get_declared_classes();
		if( !in_array( $name, $classes ) ) {
			if( file_exists( "./classes/$name.class.php" ) ) {
				include_once( "classes/$name.class.php" );
			} elseif( file_exists( "./classes/$name.php" ) ) {
				include_once( "classes/$name.php" );
			}
		}

		if( !array_key_exists( $name, $this->daoInstances ) ) {
			$this->daoInstances[$name] = new $name( $this->db );
		}
		return $this->daoInstances[$name];
	}

	/**
	 * Returns an Application DAO from the internal list of DAOs
	 * @return
	 */
	function getApplicationDAO() {
		return $this->getSimpleDAO( "ApplicationDAO");
	}

	function getCategoryDAO() {
		return $this->getSimpleDAO( "CategoryDAO");
	}

	function getCharacterDAO() {
		return $this->getSimpleDAO( "CharacterDAO");
	}

	function getCommentDAO() {
		return $this->getSimpleDAO( "CommentDAO");
	}

	function getInstructionDAO() {
		return $this->getSimpleDAO( "InstructionsDAO" );
	}

	function getFilterDAO() {
		return $this->getSimpleDAO( "FilterDAO" );
	}

	function getMechanicDAO() {
		return $this->getSimpleDAO( "MechanicDAO" );
	}

	function getOrganizationDAO() {
		return $this->getSimpleDAO( "OrganizationDAO" );
	}

	function getPlotkitDAO() {
		return $this->getSimpleDAO( "PlotkitDAO" );
	}

	function getRevisionDAO() {
		return $this->getSimpleDAO( "RevisionDAO" );
	}

	/**
	 * Returns a UserInfo DAO from the internal list of DAOs
	 * @return UserInfoDAO A DAO for accessing the User Info table
	 */
	function getUserInfoDAO() {
		if( !array_key_exists( "UserInfoDAO", $this->daoInstances ) ) {
			$this->daoInstances["UserInfoDAO"] = new UserInfoDAO( $this->db, $this->portal_db );
		}
		return $this->daoInstances["UserInfoDAO"];
	}

	function getVenueDAO() {
		return $this->getSimpleDAO( "VenueDAO" );
	}

	function getVSSDAO() {
		return $this->getSimpleDAO( "VSSDAO" );
	}
}