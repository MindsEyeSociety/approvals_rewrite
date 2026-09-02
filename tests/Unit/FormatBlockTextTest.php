<?php
require_once 'include/text_formatting.inc';

/**
 * Unit tests for formatBlockText(), covering the line-break-doubling
 * regression (raw newlines must not also be converted to `<br>`) and the
 * double-`.cs`-wrapper regression (the function must be the sole source of
 * the `.cs` container), plus its existing tab/punctuation-spacing behavior.
 *
 * @see formatBlockText()
 */
final class FormatBlockTextTest extends \PHPUnit\Framework\TestCase {

	/** A single `\n` passes through unconverted; no `<br>` is injected. */
	public function testSingleNewlineProducesNoBrTag(): void {
		$result = formatBlockText("Line one\nLine two");
		$this->assertStringNotContainsString("<br", $result);
		$this->assertStringContainsString("Line one\nLine two", $result);
	}

	/** A `\r\n` passes through unchanged, not duplicated or converted. */
	public function testWindowsLineEndingPassesThroughUnchanged(): void {
		$result = formatBlockText("Line one\r\nLine two");
		$this->assertStringNotContainsString("<br", $result);
		$this->assertStringContainsString("Line one\r\nLine two", $result);
	}

	/** The output is wrapped in exactly one `.cs` div, not nested. */
	public function testOutputIsWrappedInExactlyOneCsDiv(): void {
		$result = formatBlockText("Some text");
		$this->assertStringStartsWith('<div class="cs">', $result);
		$this->assertStringEndsWith('</div>', $result);

		$inner = substr( $result, strlen('<div class="cs">'), -strlen('</div>') );
		$this->assertStringNotContainsString('class="cs"', $inner);
	}

	/**
	 * A literal tab character becomes two non-breaking spaces. (The later
	 * chr(9)-based replacement never fires: this first replacement already
	 * consumes every tab in the string, an existing quirk this test pins
	 * down rather than "fixes".)
	 */
	public function testTabIsConvertedToNonBreakingSpaces(): void {
		$result = formatBlockText("a\tb");
		$this->assertStringContainsString("a&nbsp;&nbsp;b", $result);
	}

	/** A period followed by a non-breaking space (from tab conversion) collapses to a plain space. */
	public function testPeriodBeforeNonBreakingSpaceCollapsesToPlainSpace(): void {
		$result = formatBlockText("End.\tNext");
		// The leading "\t" first becomes "&nbsp;&nbsp;", then ".&nbsp;" collapses to ". ".
		$this->assertStringContainsString("End. &nbsp;Next", $result);
	}

	/** A comma followed by a non-breaking space (from tab conversion) collapses to a plain space. */
	public function testCommaBeforeNonBreakingSpaceCollapsesToPlainSpace(): void {
		$result = formatBlockText("List,\tItem");
		$this->assertStringContainsString("List, &nbsp;Item", $result);
	}
}
