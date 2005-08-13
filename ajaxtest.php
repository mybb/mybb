<?php
if($_GET['echo'] == "time")
{
	header('Content-Type: text/xml');
	echo "<result>".date("F j, Y, g:i a")."</result>";
	exit;
}
if($_GET['instantupdate'] == 1)
{
	header('Content-Type: text/xml');
	$handle = fopen("ajaxtest.txt", 'w');
	fwrite($handle, $_GET['query']);
	fclose($handle);
	echo "<success>";
	exit;
}
?>
<html>
<head>
<title>AJAX Intergration Page</title>
<script type="text/javascript" src="jscripts/ajax.js"></script>
<style type="text/css">
table { font-size: 13px; }
h1 { margin-bottom: 7; }
.autocomplete { position: absolute; color: #000; background-color: #fff; border: solid 1px black; visibility: hidden; z-index: 10;}
.autocomplete ul { padding: 0; margin: 0; }
.autocomplete li { display: block; cursor: pointer; padding-left: 5px; padding-right: 5px; }
.autocomplete li.selected { background-color: #efefef; }
</style>
</head>
<body>
<table width="90%" align="center" cellpadding="20">


<tr>
<td width="50%" align="center" valign="top">
<h1>Auto complete test:</h1>
<input autocomplete="off" id="to" value="" type="text"/>
<div id="to-popup" class="autocomplete"><span>blah</span></div>
<script type="text/javascript">autoComplete("to", "private.php?action=getbuddies");
</script>
</td>
<td width="50%" align="center" valign="top">
<h1>Live Fetch from server:</h1>
<input type="button" onclick="javascript:fetchData('ajaxtest.php?echo=time', 'upID');" value="Update Time" />
<br /><br /><span id="upID"></span>
</td>
</tr>


<tr>
<td width="50%" align="center" valign="top" colspan="2">
<?php
$text = implode("", file("ajaxtest.txt"));
if(!is_writeable("ajaxtest.txt"))
{
	$error = "ajaxtest.txt is not writable. Please CHMOD it to 777";
	$text = "";
}
elseif($text == "")
{
	$error = "";
	$text = "Dummy Text";
}
?>
<h1>Editing Text on a page: </h1>
(double click the text below, change it somehow then press enter or deselect it)<br /><br />
<table>
<tr>
<td><?php echo $error; ?><span style="font-size: 13px;" ondblclick="javascript:instantEdit(this, 'ajaxtest.php?instantupdate=1');"><?php echo $text; ?></span></td>
</tr>
</table>
</td>
</tr>


</table>
</body>
</html>