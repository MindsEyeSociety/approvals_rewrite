<?php
    include_once("db.inc");
?>
  </main><!-- #content -->
</div><!-- #layout -->
<footer id="site-footer">
<?php
    $build_date = @trim(shell_exec('git -C ' . escapeshellarg(__DIR__) . ' log -1 --format=%cd --date=format:%Y%m%d 2>/dev/null'));
    echo '  <p>Build ' . (empty($build_date) ? 'unknown' : htmlspecialchars($build_date)) . '</p>';
?>
  <p><a href="https://www.modernenigmasociety.org/contact-us/">Feedback / Bug Report</a></p>
<?php
    echo activateUserPopups();

    $query = "SELECT u.username, u.id " .
             "FROM users u " .
             "WHERE u.ww_number='" . $_SESSION['cam_number'] . "'";
    $res = $db->query("$query");
    if ($res->numRows() > 1) {
        while ($user_id = $res->nextRow()) {
            print('<div><a href="index.php?SWAP_ID=' . $user_id['id'] . '">Switch to user: ' . $user_id['username'] . '</a></div>');
        }
    }
?>
</footer>
</body>
</html>
