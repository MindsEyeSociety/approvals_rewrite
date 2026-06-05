<?php
class CharacterService {
	var $characterDAO;
	var $playerDAO;
	
	function __construct( $characterDAO, $playerDAO ) {
		$this->characterDAO = $characterDAO;
		$this->playerDAO = $playerDAO;
	}

	function getCharacterOrgID( $character_info ) {
		if( $character_info["org_id"] == 0 ) {
			return getPlayerOrgID( $character_info["user_id"] );
		}
		return $character_info["org_id"];
	}


}

?>
