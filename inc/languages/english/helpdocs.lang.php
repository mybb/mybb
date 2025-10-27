<?php
/**
 * MyBB 1.8 English Language Pack
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 */

// Help Document 1
$l['d1_name'] = "User Registration";
$l['d1_desc'] = "Perks and privileges to user registration.";
$l['d1_document'] = "Some parts of this forum may require you to be logged in and registered. Registration is free and takes a few minutes to complete.
<br /><br />You are encouraged to register; once you register you will be able to post messages, set your own preferences, and maintain a profile.
<br /><br />Some of the features that generally require registration are subscriptions, changing of styles, accessing of your Personal Notepad and emailing forum members.";

// Help Document 2
$l['d2_name'] = "Updating Profile";
$l['d2_desc'] = "Changing your data currently on record.";
$l['d2_document'] = "At some point during your stay, you may decide you need to update some information such as your instant messenger information, your password, or your email address. You may change any of this information from your user control panel. To access this control panel, simply click on the link in the upper left hand corner of most any page entitled \"User CP\". From there, simply choose the appropriate link under the \"Your Profile\" section and change or update any desired items, then proceed to click the submit button located at the bottom of the page for changes to take effect.";

// Help Document 3
$l['d3_name'] = "Use of Cookies on MyBB";
$l['d3_desc'] = "MyBB uses cookies to store certain information about your registration.";
$l['d3_document'] = "MyBB makes use of cookies to store your login information if you are registered, and your last visit if you are not.
<br /><br />Cookies are small text documents stored on your computer; the cookies set by this forum can only be used on this website and pose no security risk.
<br /><br />Cookies on this forum also track the specific topics you have read and when you last read them.
<br /><br />To clear all cookies set by this forum, you can click <a href=\"misc.php?action=clearcookies&amp;my_post_key={1}\">here</a>.";

// Help Document 4
$l['d4_name'] = "Logging In and Out";
$l['d4_desc'] = "How to login and log out.";
$l['d4_document'] = "When you login, you set a cookie on your machine so that you can browse the forums without having to enter in your username and password each time. Logging out clears that cookie to ensure nobody else can browse the forum as you.
<br /><br />To login, simply click the login link at the top right hand corner of the forum. To log out, click the log out link in the same place. In the event you cannot log out, clearing cookies on your machine will take the same effect.";

// Help Document 5
$l['d5_name'] = "Posting a New Thread";
$l['d5_desc'] = "Starting a new thread in a forum.";
$l['d5_document'] = "When you go to a forum you are interested in and you wish to create a new thread (or topic), simply choose the button at the top and bottom of the forums entitled \"New Thread\". Please take note that you may not have permission to post a new thread in every forum as your administrator may have restricted posting in that forum to staff or archived the forum entirely.";

// Help Document 6
$l['d6_name'] = "Posting a Reply";
$l['d6_desc'] = "Replying to a topic within a forum.";
$l['d6_document'] = "During the course of your visit, you may encounter a thread to which you would like to make a reply. To do so, simply click the \"New Reply\" button at the bottom or top of the thread. Please take note that your administrator may have restricted posting to certain individuals in that particular forum.
<br /><br />Additionally, a moderator of a forum may have closed a thread meaning that users cannot reply to it. There is no way for a user to open such a thread without the help of a moderator or administrator.";

// Help Document 7
$l['d7_name'] = "MyCode";
$l['d7_desc'] = "Learn how to use MyCode to enhance your posts.";
$l['d7_document'] = "You can use MyCode, also known as BB Codes to add effects or formatting to your posts. MyCodes are a simplified version of HTML and is used in a similar format to HTML tags which you may already be familiar with.
<br /><br />The table below is a quick guide of the MyCodes available:
<br /><br />
<table class=\"tborder\" cellspacing=\"0\" cellpadding=\"5\" border=\"0\" style=\"width:90%\">
<tbody>
<tr>
<td class=\"tcat\" style=\"width:55%\"><span class=\"smalltext\"><strong>Input</strong></span></td>
<td class=\"tcat\" style=\"width:35%\"><span class=\"smalltext\"><strong>Output</strong></span></td>
<td class=\"tcat\" style=\"width:10%\"><span class=\"smalltext\"><strong>Notes</strong></span></td>
</tr>
<tr>
<td class=\"trow1\"><span style=\"font-weight: bold; color: #ff0000;\">[b]</span>This text is bold<span style=\"font-weight: bold; color: #ff0000;\">[/b]</span></td>
<td class=\"trow1\"><span style=\"font-weight: bold;\" class=\"mycode_b\">This text is bold</span></td>
<td class=\"trow1\"></td>
</tr>
<tr>
<td class=\"trow2\"><span style=\"font-weight: bold; color: #ff0000;\">[i]</span>This text is italicized<span style=\"font-weight: bold; color: #ff0000;\">[/i]</span></td>
<td class=\"trow2\"><span style=\"font-style: italic;\" class=\"mycode_i\">This text is italicized</span></td>
<td class=\"trow2\"></td>
</tr>
<tr>
<td class=\"trow1\"><span style=\"font-weight: bold; color: #ff0000;\">[u]</span>This text is underlined<span style=\"font-weight: bold; color: #ff0000;\">[/u]</span></td>
<td class=\"trow1\"><span style=\"text-decoration: underline;\" class=\"mycode_u\">This text is underlined</span></td>
<td class=\"trow1\"></td>
</tr>
<tr>
<td class=\"trow2\"><span style=\"font-weight: bold; color: #ff0000;\">[s]</span>This text is struck out<span style=\"font-weight: bold; color: #ff0000;\">[/s]</span></td>
<td class=\"trow2\"><span style=\"text-decoration: line-through;\" class=\"mycode_s\">This text is struck out</span></td>
<td class=\"trow2\"></td>
</tr>
<tr>
<td class=\"trow1\"><span style=\"font-weight: bold; color: #ff0000;\">[url]</span>http://www.example.com/<span style=\"font-weight: bold; color: #ff0000;\">[/url]</span></td>
<td class=\"trow1\"><a href=\"http://www.example.com/\" class=\"mycode_url\" rel=\"nofollow\">http://www.example.com/</a></td>
<td class=\"trow1\">URLs will auto-link if proper protocol is included (vaild protocols are http, https, ftp, news, irc, ircs and irc6).</td>
</tr>
<tr>
<td class=\"trow2\"><span style=\"font-weight: bold; color: #ff0000;\">[url=http://www.example.com/]</span>Example.com<span style=\"font-weight: bold; color: #ff0000;\">[/url]</span></td>
<td class=\"trow2\"><a href=\"http://www.example.com/\" class=\"mycode_url\" rel=\"nofollow\">Example.com</a></td>
<td class=\"trow2\"></td>
</tr>
<tr>
<td class=\"trow1\"><span style=\"font-weight: bold; color: #ff0000;\">[email]</span>example@example.com<span style=\"font-weight: bold; color: #ff0000;\">[/email]</span></td>
<td class=\"trow1\"><a href=\"mailto:example@example.com\" class=\"mycode_email\">example@example.com</a></td>
<td class=\"trow1\"></td>
</tr>
<tr>
<td class=\"trow2\"><span style=\"font-weight: bold; color: #ff0000;\">[email=example@example.com]</span>E-mail Me!<span style=\"font-weight: bold; color: #ff0000;\">[/email]</span></td>
<td class=\"trow2\"><a href=\"mailto:example@example.com\" class=\"mycode_email\">E-mail Me!</a></td>
<td class=\"trow2\">A subject can be included by adding <strong>?subject=Subject Here</strong> after the email address.</td>
</tr>
<tr>
<td class=\"trow1\"><span style=\"font-weight: bold; color: #ff0000;\">[quote]</span>Quoted text will be here<span style=\"font-weight: bold; color: #ff0000;\">[/quote]</span></td>
<td class=\"trow1\"><blockquote class=\"mycode_quote\"><cite>Quote:</cite>Quoted text will be here</blockquote></td>
<td class=\"trow1\"></td>
</tr>
<tr>
<td class=\"trow2\"><span style=\"font-weight: bold; color: #ff0000;\">[quote='Admin' pid='1' dateline='946684800']</span>Quoted text will be here<span style=\"font-weight: bold; color: #ff0000;\">[/quote]</span></td>
<td class=\"trow2\"><blockquote class=\"mycode_quote\"><cite><span> (01-01-2000, 12:00 AM)</span>Admin Wrote:  <a href=\"http://www.example.com/showthread.php?pid=1#pid1\" class=\"quick_jump\" rel=\"nofollow\"></a></cite>Quoted text will be here</blockquote></td>
<td class=\"trow2\">This format is used when quoting posts. <strong>pid</strong> links to a post, <strong>dateline</strong> is a <a href=\"https://www.unixtimestamp.com/\">UNIX timestamp</a>.</td>
</tr>
<tr>
<td class=\"trow1\"><span style=\"font-weight: bold; color: #ff0000;\">[code]</span>Text with preserved formatting<span style=\"font-weight: bold; color: #ff0000;\">[/code]</span></td>
<td class=\"trow1\"><div class=\"codeblock\"><div class=\"title\">Code:</div><div class=\"body\" dir=\"ltr\"><code>Text with preserved formatting</code></div></div></td>
<td class=\"trow1\"></td>
</tr>
<tr>
<td class=\"trow2\"><span style=\"font-weight: bold; color: #ff0000;\">[php]</span>&lt;?php echo \"Hello world!\";?&gt;<span style=\"font-weight: bold; color: #ff0000;\">[/php]</span></td>
<td class=\"trow2\"><div class=\"codeblock phpcodeblock\"><div class=\"title\">PHP Code:</div><div class=\"body\"><div dir=\"ltr\"><code><span style=\"color: #0000BB\">&lt;?php&nbsp;</span><span style=\"color: #007700\">echo&nbsp;</span><span style=\"color: #DD0000\">\"Hello&nbsp;world!\"</span><span style=\"color: #007700\">;</span><span style=\"color: #0000BB\">?&gt;</span></code></div></div></div></div></td>
<td class=\"trow2\"></td>
</tr>
<tr>
<td class=\"trow1\"><span style=\"font-weight: bold; color: #ff0000;\">[img]</span>https://secure.php.net/images/php.gif<span style=\"font-weight: bold; color: #ff0000;\">[/img]</span></td>
<td class=\"trow1\"><img src=\"https://secure.php.net/images/php.gif\" class=\"mycode_img\"></td>
<td class=\"trow1\"></td>
</tr>
<tr>
<td class=\"trow2\"><span style=\"font-weight: bold; color: #ff0000;\">[img=50x50]</span>https://secure.php.net/images/php.gif<span style=\"font-weight: bold; color: #ff0000;\">[/img]</span></td>
<td class=\"trow2\"><img src=\"https://secure.php.net/images/php.gif\" width=\"50\" height=\"50\" class=\"mycode_img\"></td>
<td class=\"trow2\">Format is width x height</td>
</tr>
<tr>
<td class=\"trow1\"><span style=\"font-weight: bold; color: #ff0000;\">[color=red]</span>This text is red<span style=\"font-weight: bold; color: #ff0000;\">[/color]</span></td>
<td class=\"trow1\"><span style=\"color: red;\" class=\"mycode_color\">This text is red</span></td>
<td class=\"trow1\">Can use either <a href=\"https://www.w3schools.com/cssref/css_colors.asp\">CSS color name</a> or HEX code.</td>
</tr>
<tr>
<td class=\"trow2\"><span style=\"font-weight: bold; color: #ff0000;\">[size=large]</span>This text is large<span style=\"font-weight: bold; color: #ff0000;\">[/size]</span></td>
<td class=\"trow2\"><span style=\"font-size: large\" class=\"mycode_size\">This text is large</span></td>
<td class=\"trow2\">Acceptable values: xx-small, x-small, small, medium, large, x-large, xx-large</td>
</tr>
<tr>
<td class=\"trow1\"><span style=\"font-weight: bold; color: #ff0000;\">[size=30]</span>This text is 30px<span style=\"font-weight: bold; color: #ff0000;\">[/size]</span></td>
<td class=\"trow1\"><span style=\"font-size: 30px\" class=\"mycode_size\">This text is 30px</span></td>
<td class=\"trow1\">Accepts number from 1 to 50</td>
</tr>
<tr>
<td class=\"trow2\"><span style=\"font-weight: bold; color: #ff0000;\">[font=Impact]</span>This font is Impact<span style=\"font-weight: bold; color: #ff0000;\">[/font]</span></td>
<td class=\"trow2\"><span style=\"font-family: Impact;\" class=\"mycode_font\">This font is Impact</span></td>
<td class=\"trow2\">Font must be installed on your computer</td>
</tr>
<tr>
<td class=\"trow1\"><span style=\"font-weight: bold; color: #ff0000;\">[align=center]</span>This is centered<span style=\"font-weight: bold; color: #ff0000;\">[/align]</span></td>
<td class=\"trow1\"><div style=\"text-align: center;\" class=\"mycode_align\">This is centered</div></td>
<td class=\"trow1\">Acceptable values: left, center, right, justify</td>
</tr>
<tr>
<td class=\"trow2\"><span style=\"font-weight: bold; color: #ff0000;\">[list]</span><br />[*]List Item #1<br />[*]List Item #2<br />[*]List Item #3<br /><span style=\"font-weight: bold; color: #ff0000;\">[/list]</span></td>
<td class=\"trow2\"><ul class=\"mycode_list\"><li>List item #1</li><li>List item #2</li><li>List Item #3</li></ul></td>
<td class=\"trow2\"></td>
</tr>
<tr>
<td class=\"trow1\"><span style=\"font-weight: bold; color: #ff0000;\">[list=1]</span><br />[*]List Item #1<br />[*]List Item #2<br />[*]List Item #3<br /><span style=\"font-weight: bold; color: #ff0000;\">[/list]</span></td>
<td class=\"trow1\"><ol class=\"mycode_list\" type=\"1\"><li>List item #1</li><li>List item #2</li><li>List Item #3</li></ol></td>
<td class=\"trow1\"><strong>1</strong> can be used for a numbered list, <strong>a</strong> can be used for an alphabetical list, <strong>i</strong> for a roman numerals list.</td>
</tr>
<tr>
<td class=\"trow2\">A line that<span style=\"font-weight: bold; color: #ff0000;\">[hr]</span>divides</td>
<td class=\"trow2\">A line that<hr class=\"mycode_hr\">divides</td>
<td class=\"trow2\"></td>
</tr>
<tr>
<td class=\"trow1\"><span style=\"font-weight: bold; color: #ff0000;\">[video=youtube]</span>https://www.youtube.com/watch?v=dQw4w9WgXcQ<span style=\"font-weight: bold; color: #ff0000;\">[/video]</span></td>
<td class=\"trow1\"><iframe src=\"//www.youtube.com/embed/dQw4w9WgXcQ\" allowfullscreen=\"\" width=\"460\" height=\"255\" frameborder=\"0\"></iframe></td>
<td class=\"trow1\">Currently accepts Dailymotion, Facebook, LiveLeak, Metacafe, Mixer, MySpace TV, Twitch, Vimeo, Yahoo Videos and YouTube.</td>
</tr>
</tbody></table>
<br /><br />In addition, administrators may have created more MyCodes for use. Please contact an administrator to find out if there are any and on how to use them.";
