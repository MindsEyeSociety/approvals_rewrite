<?php
/**
 * Runs a real top-level application page/include in an isolated child PHP
 * process against a fully in-memory stub database, and reports back every
 * SQL statement + bound params the page issued.
 *
 * Why a child process rather than an in-process include (as tests/Unit/
 * does for classes): these are legacy procedural scripts that call
 * header()/exit()/die() directly and read superglobals at the top level.
 * Isolating each run in its own process means an exit() call only ends that
 * child -- it can never take down the PHPUnit run -- while a shutdown
 * function inside the child still reliably flushes captured queries even
 * after a fatal error or an explicit exit().
 *
 * The real db.inc, header.inc, DAOFactory, and every DAO class run
 * completely unmodified; only include/Database.class.php and
 * include/settings.inc are shadowed (via -d include_path, which PHP checks
 * before a bare include's calling-script directory) with an in-memory
 * recorder. See stub_includes/include/Database.class.php.
 *
 * @see PageHarnessResult
 */
class PageHarness {

    /**
     * @param string $page Path to the target file, relative to the repo root (e.g. "footerbar.inc", "UserDisplay.php").
     * @param array $get Populates $_GET before the page is included.
     * @param array $post Populates $_POST before the page is included.
     * @param array $session Populates $_SESSION before the page is included (after the harness's own session_start(), so it isn't reset).
     * @param bool $ignoreLogin When true (default), sets a global $IGNORE_LOGIN so header.inc's login gate lets the request through without needing $_SESSION['user_id'] set (which would also trigger the separate, heavier session_setup.inc bootstrap). Set false only when specifically testing login-gate behavior.
     * @param array $responses Canned row sets returned by successive $db->query() calls, in call order (FIFO) -- each entry is a list of associative-array rows for one query; a query beyond the queued responses gets an empty result.
     * @return PageHarnessResult The captured queries, any header() calls, and the page's raw output/exit code.
     * @throws RuntimeException if the child process cannot be started at all (a non-zero exit from the page itself is NOT an error -- that's normal for pages that redirect/die).
     */
    public static function run(
        string $page,
        array $get = [],
        array $post = [],
        array $session = [],
        bool $ignoreLogin = true,
        array $responses = []
    ): PageHarnessResult {
        $repoRoot = dirname( __DIR__, 2 );
        $specFile = tempnam( sys_get_temp_dir(), 'aph_spec_' );
        $outFile  = tempnam( sys_get_temp_dir(), 'aph_out_' );

        file_put_contents( $specFile, json_encode( [
            'page'        => $page,
            'get'         => $get,
            'post'        => $post,
            'session'     => $session,
            'ignoreLogin' => $ignoreLogin,
            'responses'   => $responses,
            'outFile'     => $outFile,
        ] ) );

        $driver = __DIR__ . DIRECTORY_SEPARATOR . 'driver.php';
        $stubPath = __DIR__ . DIRECTORY_SEPARATOR . 'stub_includes';

        $cmd = escapeshellarg( PHP_BINARY )
            . ' -d include_path=' . escapeshellarg( $stubPath )
            . ' -d error_reporting=' . escapeshellarg( (string)( E_ALL & ~E_DEPRECATED ) )
            . ' -d display_errors=1'
            . ' -d session.save_path=' . escapeshellarg( sys_get_temp_dir() )
            . ' ' . escapeshellarg( $driver )
            . ' ' . escapeshellarg( $specFile );

        $descriptors = [ 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ];
        $process = proc_open( $cmd, $descriptors, $pipes, $repoRoot );
        if ( !is_resource( $process ) ) {
            @unlink( $specFile );
            @unlink( $outFile );
            throw new RuntimeException( "PageHarness: failed to start child process for $page" );
        }

        $stdout = stream_get_contents( $pipes[1] );
        $stderr = stream_get_contents( $pipes[2] );
        fclose( $pipes[1] );
        fclose( $pipes[2] );
        $exitCode = proc_close( $process );

        $captured = [];
        if ( is_file( $outFile ) ) {
            $captured = json_decode( file_get_contents( $outFile ), true ) ?? [];
            @unlink( $outFile );
        }
        @unlink( $specFile );

        return new PageHarnessResult(
            $captured['queries'] ?? [],
            $captured['headers'] ?? [],
            $stdout,
            $stderr,
            $exitCode
        );
    }
}

/** Result of one PageHarness::run() call. */
class PageHarnessResult {
    /** @param array $queries Each entry: ['sql' => string, 'params' => array, 'db' => string]. */
    public function __construct(
        public readonly array $queries,
        public readonly array $headers,
        public readonly string $output,
        public readonly string $stderr,
        public readonly int $exitCode
    ) {}

    /** The SQL text of every captured query, in call order. */
    public function sqlStatements(): array {
        return array_column( $this->queries, 'sql' );
    }

    /** True if any captured query's SQL text contains $needle verbatim (e.g. to assert a raw value was NOT interpolated). */
    public function anyQueryContains( string $needle ): bool {
        foreach ( $this->sqlStatements() as $sql ) {
            if ( str_contains( $sql, $needle ) ) {
                return true;
            }
        }
        return false;
    }

    /** True if any captured query's params array contains $needle as one of its bound values. */
    public function anyParamsContain( mixed $needle ): bool {
        foreach ( $this->queries as $q ) {
            if ( in_array( $needle, $q['params'], true ) ) {
                return true;
            }
        }
        return false;
    }
}
