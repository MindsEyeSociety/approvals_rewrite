<?php

class ResultSet {
	var $_rowCount;
	var $_affectedRows;
	var $_currentRow;
	var $_rows;
	var $_fieldCount;
	var $_fieldNames;
	
	function ResultSet( $rh ) {
		$this->_rowCount = 0;
		$this->_fieldNames = array();
		$this->_rows = array();
		$this->_currentRow = 0;
		if( $rh->num_rows == 0 ) {
			#$this->_affectedRows = mysql_affected_rows();
			$this->_affectedRows = $rh->num_rows;
			$this->_fieldCount = 0;
		} else {
			#$this->_fieldCount = mysql_num_fields( $rh );
			$this->_fieldCount = $rh->field_count;
			#$count = mysql_num_fields( $rh );
			$count = $rh->field_count;
	 		for ($i = 0; $i < $count; $i++) {
				$finfo = mysqli_fetch_field($rh);
				$this->_fieldNames[] = $finfo->name;
				#$this->_fieldNames[] = mysql_field_name($rh, $i);
			} 
			#while( $row = mysql_fetch_assoc( $rh ) ) {
			while( $row = mysqli_fetch_row( $rh ) ) {
				$this->_rows[] = $row;
				$this->_rowCount++;
			}
			$this->_affectedRows = 0;
			#mysql_free_result( $rh );
			mysqli_free_result( $rh );
		}
	}
 
	function getFieldCount() {
		return $this->_fieldCount;
	}
	
	function getFieldNames() {
		return $this->_fieldNames;
	}
	
	function nextRow() {
		if( isset( $this->_rows[$this->_currentRow] ) ) {
			$row = $this->_rows[$this->_currentRow];
			$this->_currentRow++;
		} else {
			$row = array();
		}
		return $row;
	}

	function seekRow( $row ) {
		return( $this->_rows[$row] );
	}
	
	function numRows() {
		return $this->_rowCount;
	}

	function affectedRows() {
		return $this->_affectedRows;
	}
	
	function getField( $field ) {
		return( $this->_rows[$this->_currentRow][$field] );
	}
	
	function getAllRows() {
		return $this->_rows;
	}
}

 ?>
