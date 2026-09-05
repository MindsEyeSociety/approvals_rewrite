<?php
/**
 * Stub replacement for include/Database.class.php, used ONLY by the
 * Integration test harness (tests/Integration/PageHarness.php) via PHP's
 * include_path resolution: a bare `include_once("include/Database.class.php")`
 * checks include_path before the calling script's own directory, so pointing
 * php's -d include_path at this file's parent directory makes every real
 * application file (db.inc, DAOFactory, every DAO class) transparently talk
 * to this in-memory recorder instead of a real MySQL connection.
 *
 * Every query passed to Database::query() is appended to a global capture
 * list (read back by the harness after the driver process exits) instead of
 * being executed, and returns a canned row set popped from a global queue the
 * driver script populates from the test's fixture file. No network I/O, no
 * real settings.inc, no real mysqli extension calls.
 */

class Database {
    public $db_host;
    public $db_user;
    public $db_pass;
    public $db_name;
    public $dbh;
    public $lastResult;

    public function __construct( $host = null, $user = null, $pass = null, $name = null ) {
        $this->db_host = $host;
        $this->db_user = $user;
        $this->db_pass = $pass;
        $this->db_name = $name;
        $this->dbh = null;
    }

    /** Mirrors the real class's escape() shape; no real mysqli handle exists, so this is a plain fallback. */
    function escape( $source ) {
        return addslashes( (string)$source );
    }

    /**
     * Records the query instead of running it, and returns the next canned
     * row set queued for this connection (FIFO), defaulting to an empty
     * result when the test didn't queue one for this call.
     */
    function query( string $query, array $params = [] ) {
        $GLOBALS['__captured_queries'][] = [ 'sql' => $query, 'params' => $params, 'db' => $this->db_name ];
        $rows = array_shift( $GLOBALS['__stub_responses'] ) ?? [];
        $this->lastResult = new StubResultSet( $rows );
        return $this->lastResult;
    }

    function throwError() {
        // Real class echoes debug info then exit()s on a query failure.
        // The stub never fails a query, so this should never be reached.
        exit( 1 );
    }

    function getAllRows() { return $this->lastResult->getAllRows(); }
    function getFieldCount() { return $this->lastResult->getFieldCount(); }
    function getFieldNames() { return $this->lastResult->getFieldNames(); }
    function nextRow() { return $this->lastResult->nextRow(); }
    function numRows() { return $this->lastResult->numRows(); }
    function affectedRows() { return $this->lastResult->affectedRows(); }
    function getField( $field ) { return $this->lastResult->getField( $field ); }
    function getInsertId() { return 1; }
}

/** Mirrors include/ResultSet.class.php's public interface over a plain PHP array of assoc rows -- no mysqli_result involved. */
class StubResultSet {
    private $rows;
    private $currentRow = 0;

    public function __construct( array $rows ) {
        $this->rows = array_values( $rows );
    }

    function getFieldCount() { return count( $this->rows ) > 0 ? count( $this->rows[0] ) : 0; }
    function getFieldNames() { return count( $this->rows ) > 0 ? array_keys( $this->rows[0] ) : []; }

    function nextRow() {
        if ( isset( $this->rows[$this->currentRow] ) ) {
            return $this->rows[$this->currentRow++];
        }
        return array();
    }

    function numRows() { return count( $this->rows ); }
    function affectedRows() { return 0; }
    function getField( $field ) { return $this->rows[$this->currentRow][$field] ?? null; }
    function getAllRows() { return $this->rows; }
}
