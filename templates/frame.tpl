<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN">
<html>
<head>
  <link href="anathema.css" type='text/css' rel='stylesheet'>
  <title>{$title}</title>
  <script src="https://code.jquery.com/jquery-1.7.2.min.js"></script>
  <script src="javascript/jquery.tooltip.js"></script>
</head>

<body>
	{if $content_string == ""}
		{include file=$content}
	{/if}

	{$content_string}
	<br>
	<hr width="25%" align="center" noshade="1" />
	<div align="center" class="normalsmall">
		<a href="UserFeedback.php?pagefeedback=<?=urlencode($pagetitle)?>&">Enter Feedback or Bug Report for this page</a>
	</div>

</body>
</html>
