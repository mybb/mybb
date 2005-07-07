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
	$handle = fopen("update.txt", 'w');
   fwrite($handle, $_GET['query']);
   fclose($handle);
   echo "<success>";
   exit;
}
?>
<head>
<style>

	.autocomplete {
  position: absolute;
  color: #000;
  background-color: #fff;
  border: solid 1px black;
  visibility: hidden;
  z-index: 10;
}

.autocomplete ul {
  padding: 0;
  margin: 0;
}
 
.autocomplete li {
  display: block;
  cursor: pointer;
  padding-left: 5px;
  padding-right: 5px;
}

.autocomplete li.selected {
 background-color: #efefef;
}
</style>
<script type="text/javascript" src="jscripts/ajax.js"></script>
</head>
<body>
<h1>Auto complete test:</h1><br />
		<input autocomplete="off" id="to" value="" type="text"/>

        <div id="to-popup" class="autocomplete"><span>blah</span></div>
        <script type="text/javascript">
          autoComplete("to", "private.php?action=getbuddies");
        </script><br />
<?php
$text = implode("", file("update.txt")); 
if($text == "")
{
 $text = "Dummy Text";
}
?>
<h1>Editing Text on a page: </h1>(double click the text below, change it somehow then press enter or deselect it. Refresh the page)<br />
		<table><tr><td><span ondblclick="javascript:instantEdit(this, 'testac.php?instantupdate=1');"><?php echo $text; ?></span></td></tr></table>
<br /><br />
<h1>Live Fetch from server</h1>
<input type="button" onclick="javascript:fetchData('testac.php?echo=time', 'upID');" value="Update Time" /><br />
<b><span id="upID"></span></b>