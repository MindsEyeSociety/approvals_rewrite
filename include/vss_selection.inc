<?php

if ( ! function_exists('resolveVssSelection') ) {
	/**
	 * Picks which of the two VSS-selection form fields (a local-org VSS
	 * dropdown vs. a total-org VSS dropdown) should decide the character's
	 * new VSS, since MoveCharacter.php submits both but only one is ever
	 * meant to be honored.
	 *
	 * The local selection takes precedence over the total selection
	 * whenever it was actually chosen; the total selection is used only
	 * when the local one is empty. When neither field carries a real
	 * selection, no VSS was chosen.
	 *
	 * @param $localvss_id The submitted `localvss_id` form value. An empty
	 *                      string means the local dropdown was left
	 *                      untouched (no selection made there).
	 * @param $totalvss_id The submitted `totalvss_id` form value. An empty
	 *                      string means the total dropdown was left
	 *                      untouched (no selection made there).
	 * @return The chosen VSS id (whichever field supplied it), or 0 when
	 *         neither field had a real selection.
	 *
	 * @example
	 *   resolveVssSelection("", "1312");     // => "1312" (total box wins when local is untouched)
	 *   resolveVssSelection("-995", "");     // => "-995" (local box wins when total is untouched)
	 *   resolveVssSelection("", "");         // => 0      (neither chosen)
	 *   resolveVssSelection("-995", "1312"); // => "-995" (local takes precedence when both are set)
	 */
	function resolveVssSelection( $localvss_id, $totalvss_id ) {
		if ( $localvss_id != "" ) {
			return $localvss_id;
		} elseif ( $totalvss_id != "" ) {
			return $totalvss_id;
		}
		return 0;
	}
}
