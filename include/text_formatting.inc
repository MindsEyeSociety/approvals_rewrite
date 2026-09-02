<?php

if ( ! function_exists('formatBlockText') ) {
	/**
	 * Renders a block of user-entered free text as a single `.cs`-styled
	 * `<div>`, so character sheets, backgrounds, and similar long-form fields
	 * stay readable without resorting to the `<pre>` tag.
	 *
	 * Line breaks are left as raw `\n`/`\r\n` in the output rather than being
	 * converted to `<br>` — the `.cs` CSS class renders with
	 * `white-space: pre-wrap`, which already turns those characters into
	 * visible line breaks in the browser. Injecting `<br>` as well as keeping
	 * the original newline would double every line break.
	 *
	 * The returned string is wrapped in its own `<div class="cs">...</div>`,
	 * so this is the single place that applies the `.cs` treatment — callers
	 * must not additionally wrap the result in their own `.cs` container, or
	 * the padded `.cs` background will be applied twice and bleed across
	 * wrapped lines.
	 *
	 * @param $text Untrusted free text as entered by a user (e.g. a
	 *              character sheet, background, or VSS body). May contain
	 *              tabs, raw newlines, and arbitrary punctuation.
	 * @return The text with tabs converted to non-breaking spaces and minor
	 *         punctuation-spacing cleanup applied, wrapped in a single
	 *         `<div class="cs">...</div>`.
	 *
	 * @example
	 *   formatBlockText("Line one\nLine two.  Trailing.");
	 *   // => "<div class=\"cs\">Line one\nLine two. Trailing.</div>"
	 */
	function formatBlockText( $text ) {
		$formattedText = str_replace("	", "&nbsp;&nbsp;", $text );
		$formattedText = str_replace(".&nbsp;", ". ", $formattedText);
		$formattedText = str_replace(chr(9),"&nbsp;&nbsp;&nbsp;&nbsp; ", $formattedText);
		$formattedText = str_replace(",&nbsp;", ", ", $formattedText);
		return "<div class=\"cs\">".$formattedText."</div>";
	}
}
