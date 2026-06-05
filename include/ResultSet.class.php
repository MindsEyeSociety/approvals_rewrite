<?php

class ResultSet {
	var $_rowCount;
	var $_affectedRows;
	var $_currentRow;
	var $_rows;
	var $_fieldCount;
	var $_fieldNames;
	var $_dbh;
	
	/*function __construct( $rh, $db ) {
		$this->_rowCount = 0;
		$this->_fieldNames = array();
		$this->_rows = array();
		$this->_currentRow = 0;
		$this->dbh = $db;

		if( $rh->num_rows == 0 ) {
			#$this->_affectedRows = mysql_affected_rows();
			$this->_affectedRows = $rh->num_rows;
			$this->_fieldCount = 0;
		} else {
                    // Fetch all rows using mysqli (uncommented and using assoc)
                    $this->_rows = [];
                    //while ($row = mysqli_fetch_assoc($rh)) {
                    //    $this->_rows[] = $row;
                    //}
		    $this->_rows = mysqli_fetch_all($rh,MYSQLI_ASSOC);
                    $this->_rowCount = count($this->_rows);  // safer than $rh->num_rows in case of partial fetch

                    $this->_affectedRows = 0;  // for SELECT, affected is 0

		    $this->_fieldCount = $rh->field_count;

		    // Fetch field names (uncommented and fixed)
		    $fields = mysqli_fetch_fields($rh);
		    foreach ($fields as $field) {
		        $this->_fieldNames[] = $field->name;
		    }

		    mysqli_free_result($rh);
		}
	}*/
	function __construct( $rh, $db ) {
	    $this->_rowCount = 0;
	    $this->_fieldNames = array();
	    $this->_rows = array();
	    $this->_currentRow = 0;
	    $this->dbh = $db;

	    // Safety check: $rh must be a result object (not true/false)
	    if (!($rh instanceof mysqli_result)) {
	        $this->_affectedRows = 0;
	        $this->_fieldCount = 0;
	        return;
	    }

	    if ($rh->num_rows == 0) {
	        $this->_affectedRows = 0;
	        $this->_fieldCount = 0;
	    } else {
	        $this->_fieldCount = $rh->field_count;

        // Fetch rows first
	        $this->_rows = mysqli_fetch_all($rh, MYSQLI_ASSOC) ?: [];

	        $this->_rowCount = count($this->_rows);
	        $this->_affectedRows = 0;

        // Fetch field names after rows
	        $fields = mysqli_fetch_fields($rh);
	        foreach ($fields as $field) {
	            $this->_fieldNames[] = $field->name;
	        }

	        mysqli_free_result($rh);
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
