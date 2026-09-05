<?php
/**
 * Minimal stand-in for the real Database class, shared by the SQL-injection
 * regression tests: records every SQL string and bound params array passed
 * to query(), and returns a canned row set (or none) so a test can assert
 * the query text uses ?-placeholders and that a tainted value only ever
 * appears in $params, never spliced into $sql.
 *
 * @see Database in include/Database.class.php, the real class this doubles for.
 */
class RecordingDb {
	/** @var array<int, array{sql: string, params: array}> Every query() call, in order. */
	public array $queries = [];

	private array $responses;
	private array $lastRows = [];
	private int $cursor = 0;

	/** @param array $responses One row-array per expected query() call, in call order (FIFO). A call beyond the queued responses gets no rows. */
	public function __construct( array $responses = [] ) {
		$this->responses = $responses;
	}

	public function query( string $sql, array $params = [] ) {
		$this->queries[] = [ 'sql' => $sql, 'params' => $params ];
		$this->lastRows = array_shift( $this->responses ) ?? [];
		$this->cursor = 0;
		return $this;
	}

	public function escape( $value ) {
		return addslashes( (string)$value );
	}

	public function nextRow() {
		if ( isset( $this->lastRows[ $this->cursor ] ) ) {
			return $this->lastRows[ $this->cursor++ ];
		}
		return array();
	}

	public function numRows(): int {
		return count( $this->lastRows );
	}

	public function getAllRows(): array {
		return $this->lastRows;
	}

	/** The SQL text of every query() call, in order. */
	public function sqlStatements(): array {
		return array_column( $this->queries, 'sql' );
	}
}
