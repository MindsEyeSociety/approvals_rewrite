<?php

class CharacterSheetVersionDAO {
	var $db;

	function __construct( $db ) {
		$this->db = $db;
	}

	/**
	 * Inserts a new version snapshot of a character sheet into the database.
	 *
	 * @param $character_id  The ID of the character this sheet belongs to.
	 * @param $character_sheet  The full character sheet content to archive.
	 * @param $updated_at  The datetime string recording when this version was created.
	 * @return void
	 * @see getVersionsForCharacter
	 * @see getVersion
	 */
	function saveVersion( $character_id, $character_sheet, $updated_at ) {
		$query =
			"INSERT INTO character_sheet_versions (".
			"  character_id,".
			"  character_sheet,".
			"  updated_at".
			") VALUES (".
			"  ?, ?, ?".
			")";
		$this->db->query($query, [$character_id, $character_sheet, $updated_at]);
	}

	/**
	 * Returns a list of version summaries for a character, newest first.
	 *
	 * Each entry contains 'id' and 'updated_at', suitable for populating a
	 * version-history dropdown without loading the full sheet content.
	 *
	 * @param $character_id  The ID of the character whose version history to fetch.
	 * @return array  Array of associative arrays, each with keys 'id' and 'updated_at'.
	 * @see saveVersion
	 * @see getVersion
	 */
	function getVersionsForCharacter( $character_id ) {
		$query =
			"SELECT id, updated_at ".
			"FROM character_sheet_versions ".
			"WHERE character_id=? ".
			"ORDER BY updated_at DESC";
		$this->db->query($query, [$character_id]);
		return $this->db->getAllRows();
	}

	/**
	 * Returns the character sheet content for a single version row.
	 *
	 * Intended for AJAX endpoints that load a historical sheet on demand.
	 *
	 * @param $version_id  The primary-key ID of the version row to retrieve.
	 * @return string|null  The raw character sheet text, or null if the version does not exist.
	 * @see getVersionsForCharacter
	 */
	function getVersion( $version_id ) {
		$query =
			"SELECT character_sheet ".
			"FROM character_sheet_versions ".
			"WHERE id=?";
		$this->db->query($query, [$version_id]);
		if( $row = $this->db->nextRow() ) {
			return $row["character_sheet"];
		} else {
			return null;
		}
	}
}
?>
