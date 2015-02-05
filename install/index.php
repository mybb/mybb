<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

@set_time_limit(0);

define('MYBB_ROOT', dirname(dirname(__FILE__))."/");
define("INSTALL_ROOT", dirname(__FILE__)."/");
define("TIME_NOW", time());
define("IN_MYBB", 1);
define("IN_INSTALL", 1);

if(function_exists('date_default_timezone_set') && !ini_get('date.timezone'))
{
	date_default_timezone_set('GMT');
}

require_once MYBB_ROOT.'inc/class_error.php';
$error_handler = new errorHandler();

require_once MYBB_ROOT.'inc/class_core.php';
$mybb = new MyBB;

// Include the files necessary for installation
require_once MYBB_ROOT.'inc/class_timers.php';
require_once MYBB_ROOT.'inc/functions.php';

$admin_dir = "admin";

// Perform a check if MyBB is already installed or not
$installed = false;
if(file_exists(MYBB_ROOT."/inc/config.php"))
{
	require MYBB_ROOT."/inc/config.php";
	if(isset($config) && is_array($config))
	{
		$installed = true;
		if(isset($config['admindir']))
		{
			$admin_dir = $config['admindir'];
		}
		else if(isset($config['admin_dir']))
		{
			$admin_dir = $config['admin_dir'];
		}
	}
}

require_once MYBB_ROOT.'inc/class_xml.php';
require_once MYBB_ROOT.'inc/functions_user.php';
require_once MYBB_ROOT.'inc/class_language.php';
$lang = new MyLanguage();
$lang->set_path(MYBB_ROOT.'install/resources');
$lang->load('language');

// Load DB interface
require_once MYBB_ROOT."inc/db_base.php";

// Prevent any shut down functions from running
$done_shutdown = 1;

// Include the necessary contants for installation
$grouppermignore = array('gid', 'type', 'title', 'description', 'namestyle', 'usertitle', 'stars', 'starimage', 'image');
$groupzerogreater = array('pmquota', 'maxpmrecipients', 'maxreputationsday', 'attachquota', 'maxemails', 'maxwarningsday', 'maxposts', 'edittimelimit', 'canusesigxposts', 'maxreputationsperthread');
$displaygroupfields = array('title', 'description', 'namestyle', 'usertitle', 'stars', 'starimage', 'image');
$fpermfields = array('canview', 'canviewthreads', 'candlattachments', 'canpostthreads', 'canpostreplys', 'canpostattachments', 'canratethreads', 'caneditposts', 'candeleteposts', 'candeletethreads', 'caneditattachments', 'canpostpolls', 'canvotepolls', 'cansearch', 'modposts', 'modthreads', 'modattachments', 'mod_edit_posts');

// Include the installation resources
require_once INSTALL_ROOT.'resources/output.php';
$output = new installerOutput;

$dboptions = array();

if(function_exists('mysqli_connect'))
{
	$dboptions['mysqli'] = array(
		'class' => 'DB_MySQLi',
		'title' => 'MySQL Improved',
		'short_title' => 'MySQLi',
		'structure_file' => 'mysql_db_tables.php',
		'population_file' => 'mysql_db_inserts.php'
	);
}

if(function_exists('mysql_connect'))
{
	$dboptions['mysql'] = array(
		'class' => 'DB_MySQL',
		'title' => 'MySQL',
		'short_title' => 'MySQL',
		'structure_file' => 'mysql_db_tables.php',
		'population_file' => 'mysql_db_inserts.php'
	);
}

if(function_exists('pg_connect'))
{
	$dboptions['pgsql'] = array(
		'class' => 'DB_PgSQL',
		'title' => 'PostgreSQL',
		'short_title' => 'PostgreSQL',
		'structure_file' => 'pgsql_db_tables.php',
		'population_file' => 'mysql_db_inserts.php'
	);
}

if(class_exists('PDO'))
{
	$supported_dbs = PDO::getAvailableDrivers();
	if(in_array('sqlite', $supported_dbs))
	{
		$dboptions['sqlite'] = array(
			'class' => 'DB_SQLite',
			'title' => 'SQLite 3',
			'short_title' => 'SQLite',
			'structure_file' => 'sqlite_db_tables.php',
			'population_file' => 'mysql_db_inserts.php'
		);
	}
}

if(file_exists('lock') && $mybb->dev_mode != true)
{
	$output->print_error($lang->locked);
}
else if($installed == true && empty($mybb->input['action']))
{
	$output->print_header($lang->already_installed, "errormsg", 0);
	echo $lang->sprintf($lang->mybb_already_installed, $mybb->version);
	$output->print_footer();
}
else
{
	$output->steps = array(
		'intro' => $lang->welcome,
		'license' => $lang->license_agreement,
		'requirements_check' => $lang->req_check,
		'database_info' => $lang->db_config,
		'create_tables' => $lang->table_creation,
		'populate_tables' => $lang->data_insertion,
		'templates' => $lang->theme_install,
		'configuration' => $lang->board_config,
		'adminuser' => $lang->admin_user,
		'final' => $lang->finish_setup,
	);

	switch($mybb->get_input('action'))
	{
		case 'license':
			license_agreement();
			break;
		case 'requirements_check':
			requirements_check();
			break;
		case 'database_info':
			database_info();
			break;
		case 'create_tables':
			create_tables();
			break;
		case 'populate_tables':
			populate_tables();
			break;
		case 'templates':
			insert_templates();
			break;
		case 'configuration':
			configure();
			break;
		case 'adminuser':
			create_admin_user();
			break;
		case 'final':
			install_done();
			break;
		default:
			$mybb->input['action'] = 'intro';
			intro();
			break;
	}
}

function intro()
{
	global $output, $mybb, $lang;

	$output->print_header($lang->welcome, 'welcome');
	if(strpos(strtolower(get_current_location('', '', true)), '/upload/') !== false)
	{
		echo $lang->sprintf($lang->mybb_incorrect_folder);
	}
	echo $lang->sprintf($lang->welcome_step, $mybb->version);
	$output->print_footer('license');
}

function license_agreement()
{
	global $output, $lang, $mybb;

	ob_start();
	$output->print_header($lang->license_agreement, 'license');

	if($mybb->get_input('allow_anonymous_info', MyBB::INPUT_INT) == 1)
	{
		require_once MYBB_ROOT."inc/functions_serverstats.php";
		$build_server_stats = build_server_stats(1, '', $mybb->version_code);

		if($build_server_stats['info_sent_success'] == false)
		{
			echo $build_server_stats['info_image'];
		}
	}
	ob_end_flush();

	$license = <<<EOF
<pre>
                   GNU LESSER GENERAL PUBLIC LICENSE
                       Version 3, 29 June 2007

 Copyright (C) 2007 Free Software Foundation, Inc. <http://fsf.org/>
 Everyone is permitted to copy and distribute verbatim copies
 of this license document, but changing it is not allowed.


  This version of the GNU Lesser General Public License incorporates
the terms and conditions of version 3 of the GNU General Public
License, supplemented by the additional permissions listed below.

  0. Additional Definitions.

  As used herein, "this License" refers to version 3 of the GNU Lesser
General Public License, and the "GNU GPL" refers to version 3 of the GNU
General Public License.

  "The Library" refers to a covered work governed by this License,
other than an Application or a Combined Work as defined below.

  An "Application" is any work that makes use of an interface provided
by the Library, but which is not otherwise based on the Library.
Defining a subclass of a class defined by the Library is deemed a mode
of using an interface provided by the Library.

  A "Combined Work" is a work produced by combining or linking an
Application with the Library.  The particular version of the Library
with which the Combined Work was made is also called the "Linked
Version".

  The "Minimal Corresponding Source" for a Combined Work means the
Corresponding Source for the Combined Work, excluding any source code
for portions of the Combined Work that, considered in isolation, are
based on the Application, and not on the Linked Version.

  The "Corresponding Application Code" for a Combined Work means the
object code and/or source code for the Application, including any data
and utility programs needed for reproducing the Combined Work from the
Application, but excluding the System Libraries of the Combined Work.

  1. Exception to Section 3 of the GNU GPL.

  You may convey a covered work under sections 3 and 4 of this License
without being bound by section 3 of the GNU GPL.

  2. Conveying Modified Versions.

  If you modify a copy of the Library, and, in your modifications, a
facility refers to a function or data to be supplied by an Application
that uses the facility (other than as an argument passed when the
facility is invoked), then you may convey a copy of the modified
version:

   a) under this License, provided that you make a good faith effort to
   ensure that, in the event an Application does not supply the
   function or data, the facility still operates, and performs
   whatever part of its purpose remains meaningful, or

   b) under the GNU GPL, with none of the additional permissions of
   this License applicable to that copy.

  3. Object Code Incorporating Material from Library Header Files.

  The object code form of an Application may incorporate material from
a header file that is part of the Library.  You may convey such object
code under terms of your choice, provided that, if the incorporated
material is not limited to numerical parameters, data structure
layouts and accessors, or small macros, inline functions and templates
(ten or fewer lines in length), you do both of the following:

   a) Give prominent notice with each copy of the object code that the
   Library is used in it and that the Library and its use are
   covered by this License.

   b) Accompany the object code with a copy of the GNU GPL and this license
   document.

  4. Combined Works.

  You may convey a Combined Work under terms of your choice that,
taken together, effectively do not restrict modification of the
portions of the Library contained in the Combined Work and reverse
engineering for debugging such modifications, if you also do each of
the following:

   a) Give prominent notice with each copy of the Combined Work that
   the Library is used in it and that the Library and its use are
   covered by this License.

   b) Accompany the Combined Work with a copy of the GNU GPL and this license
   document.

   c) For a Combined Work that displays copyright notices during
   execution, include the copyright notice for the Library among
   these notices, as well as a reference directing the user to the
   copies of the GNU GPL and this license document.

   d) Do one of the following:

       0) Convey the Minimal Corresponding Source under the terms of this
       License, and the Corresponding Application Code in a form
       suitable for, and under terms that permit, the user to
       recombine or relink the Application with a modified version of
       the Linked Version to produce a modified Combined Work, in the
       manner specified by section 6 of the GNU GPL for conveying
       Corresponding Source.

       1) Use a suitable shared library mechanism for linking with the
       Library.  A suitable mechanism is one that (a) uses at run time
       a copy of the Library already present on the user's computer
       system, and (b) will operate properly with a modified version
       of the Library that is interface-compatible with the Linked
       Version.

   e) Provide Installation Information, but only if you would otherwise
   be required to provide such information under section 6 of the
   GNU GPL, and only to the extent that such information is
   necessary to install and execute a modified version of the
   Combined Work produced by recombining or relinking the
   Application with a modified version of the Linked Version. (If
   you use option 4d0, the Installation Information must accompany
   the Minimal Corresponding Source and Corresponding Application
   Code. If you use option 4d1, you must provide the Installation
   Information in the manner specified by section 6 of the GNU GPL
   for conveying Corresponding Source.)

  5. Combined Libraries.

  You may place library facilities that are a work based on the
Library side by side in a single library together with other library
facilities that are not Applications and are not covered by this
License, and convey such a combined library under terms of your
choice, if you do both of the following:

   a) Accompany the combined library with a copy of the same work based
   on the Library, uncombined with any other library facilities,
   conveyed under the terms of this License.

   b) Give prominent notice with the combined library that part of it
   is a work based on the Library, and explaining where to find the
   accompanying uncombined form of the same work.

  6. Revised Versions of the GNU Lesser General Public License.

  The Free Software Foundation may publish revised and/or new versions
of the GNU Lesser General Public License from time to time. Such new
versions will be similar in spirit to the present version, but may
differ in detail to address new problems or concerns.

  Each version is given a distinguishing version number. If the
Library as you received it specifies that a certain numbered version
of the GNU Lesser General Public License "or any later version"
applies to it, you have the option of following the terms and
conditions either of that published version or of any later version
published by the Free Software Foundation. If the Library as you
received it does not specify a version number of the GNU Lesser
General Public License, you may choose any version of the GNU Lesser
General Public License ever published by the Free Software Foundation.

  If the Library as you received it specifies that a proxy can decide
whether future versions of the GNU Lesser General Public License shall
apply, that proxy's public statement of acceptance of any version is
permanent authorization for you to choose that version for the
Library.

                    GNU GENERAL PUBLIC LICENSE
                       Version 3, 29 June 2007

 Copyright (C) 2007 Free Software Foundation, Inc. &lt;http://fsf.org/&gt;
 Everyone is permitted to copy and distribute verbatim copies
 of this license document, but changing it is not allowed.

                            Preamble

  The GNU General Public License is a free, copyleft license for
software and other kinds of works.

  The licenses for most software and other practical works are designed
to take away your freedom to share and change the works.  By contrast,
the GNU General Public License is intended to guarantee your freedom to
share and change all versions of a program--to make sure it remains free
software for all its users.  We, the Free Software Foundation, use the
GNU General Public License for most of our software; it applies also to
any other work released this way by its authors.  You can apply it to
your programs, too.

  When we speak of free software, we are referring to freedom, not
price.  Our General Public Licenses are designed to make sure that you
have the freedom to distribute copies of free software (and charge for
them if you wish), that you receive source code or can get it if you
want it, that you can change the software or use pieces of it in new
free programs, and that you know you can do these things.

  To protect your rights, we need to prevent others from denying you
these rights or asking you to surrender the rights.  Therefore, you have
certain responsibilities if you distribute copies of the software, or if
you modify it: responsibilities to respect the freedom of others.

  For example, if you distribute copies of such a program, whether
gratis or for a fee, you must pass on to the recipients the same
freedoms that you received.  You must make sure that they, too, receive
or can get the source code.  And you must show them these terms so they
know their rights.

  Developers that use the GNU GPL protect your rights with two steps:
(1) assert copyright on the software, and (2) offer you this License
giving you legal permission to copy, distribute and/or modify it.

  For the developers' and authors' protection, the GPL clearly explains
that there is no warranty for this free software.  For both users' and
authors' sake, the GPL requires that modified versions be marked as
changed, so that their problems will not be attributed erroneously to
authors of previous versions.

  Some devices are designed to deny users access to install or run
modified versions of the software inside them, although the manufacturer
can do so.  This is fundamentally incompatible with the aim of
protecting users' freedom to change the software.  The systematic
pattern of such abuse occurs in the area of products for individuals to
use, which is precisely where it is most unacceptable.  Therefore, we
have designed this version of the GPL to prohibit the practice for those
products.  If such problems arise substantially in other domains, we
stand ready to extend this provision to those domains in future versions
of the GPL, as needed to protect the freedom of users.

  Finally, every program is threatened constantly by software patents.
States should not allow patents to restrict development and use of
software on general-purpose computers, but in those that do, we wish to
avoid the special danger that patents applied to a free program could
make it effectively proprietary.  To prevent this, the GPL assures that
patents cannot be used to render the program non-free.

  The precise terms and conditions for copying, distribution and
modification follow.

                       TERMS AND CONDITIONS

  0. Definitions.

  "This License" refers to version 3 of the GNU General Public License.

  "Copyright" also means copyright-like laws that apply to other kinds of
works, such as semiconductor masks.

  "The Program" refers to any copyrightable work licensed under this
License.  Each licensee is addressed as "you".  "Licensees" and
"recipients" may be individuals or organizations.

  To "modify" a work means to copy from or adapt all or part of the work
in a fashion requiring copyright permission, other than the making of an
exact copy.  The resulting work is called a "modified version" of the
earlier work or a work "based on" the earlier work.

  A "covered work" means either the unmodified Program or a work based
on the Program.

  To "propagate" a work means to do anything with it that, without
permission, would make you directly or secondarily liable for
infringement under applicable copyright law, except executing it on a
computer or modifying a private copy.  Propagation includes copying,
distribution (with or without modification), making available to the
public, and in some countries other activities as well.

  To "convey" a work means any kind of propagation that enables other
parties to make or receive copies.  Mere interaction with a user through
a computer network, with no transfer of a copy, is not conveying.

  An interactive user interface displays "Appropriate Legal Notices"
to the extent that it includes a convenient and prominently visible
feature that (1) displays an appropriate copyright notice, and (2)
tells the user that there is no warranty for the work (except to the
extent that warranties are provided), that licensees may convey the
work under this License, and how to view a copy of this License.  If
the interface presents a list of user commands or options, such as a
menu, a prominent item in the list meets this criterion.

  1. Source Code.

  The "source code" for a work means the preferred form of the work
for making modifications to it.  "Object code" means any non-source
form of a work.

  A "Standard Interface" means an interface that either is an official
standard defined by a recognized standards body, or, in the case of
interfaces specified for a particular programming language, one that
is widely used among developers working in that language.

  The "System Libraries" of an executable work include anything, other
than the work as a whole, that (a) is included in the normal form of
packaging a Major Component, but which is not part of that Major
Component, and (b) serves only to enable use of the work with that
Major Component, or to implement a Standard Interface for which an
implementation is available to the public in source code form.  A
"Major Component", in this context, means a major essential component
(kernel, window system, and so on) of the specific operating system
(if any) on which the executable work runs, or a compiler used to
produce the work, or an object code interpreter used to run it.

  The "Corresponding Source" for a work in object code form means all
the source code needed to generate, install, and (for an executable
work) run the object code and to modify the work, including scripts to
control those activities.  However, it does not include the work's
System Libraries, or general-purpose tools or generally available free
programs which are used unmodified in performing those activities but
which are not part of the work.  For example, Corresponding Source
includes interface definition files associated with source files for
the work, and the source code for shared libraries and dynamically
linked subprograms that the work is specifically designed to require,
such as by intimate data communication or control flow between those
subprograms and other parts of the work.

  The Corresponding Source need not include anything that users
can regenerate automatically from other parts of the Corresponding
Source.

  The Corresponding Source for a work in source code form is that
same work.

  2. Basic Permissions.

  All rights granted under this License are granted for the term of
copyright on the Program, and are irrevocable provided the stated
conditions are met.  This License explicitly affirms your unlimited
permission to run the unmodified Program.  The output from running a
covered work is covered by this License only if the output, given its
content, constitutes a covered work.  This License acknowledges your
rights of fair use or other equivalent, as provided by copyright law.

  You may make, run and propagate covered works that you do not
convey, without conditions so long as your license otherwise remains
in force.  You may convey covered works to others for the sole purpose
of having them make modifications exclusively for you, or provide you
with facilities for running those works, provided that you comply with
the terms of this License in conveying all material for which you do
not control copyright.  Those thus making or running the covered works
for you must do so exclusively on your behalf, under your direction
and control, on terms that prohibit them from making any copies of
your copyrighted material outside their relationship with you.

  Conveying under any other circumstances is permitted solely under
the conditions stated below.  Sublicensing is not allowed; section 10
makes it unnecessary.

  3. Protecting Users' Legal Rights From Anti-Circumvention Law.

  No covered work shall be deemed part of an effective technological
measure under any applicable law fulfilling obligations under article
11 of the WIPO copyright treaty adopted on 20 December 1996, or
similar laws prohibiting or restricting circumvention of such
measures.

  When you convey a covered work, you waive any legal power to forbid
circumvention of technological measures to the extent such circumvention
is effected by exercising rights under this License with respect to
the covered work, and you disclaim any intention to limit operation or
modification of the work as a means of enforcing, against the work's
users, your or third parties' legal rights to forbid circumvention of
technological measures.

  4. Conveying Verbatim Copies.

  You may convey verbatim copies of the Program's source code as you
receive it, in any medium, provided that you conspicuously and
appropriately publish on each copy an appropriate copyright notice;
keep intact all notices stating that this License and any
non-permissive terms added in accord with section 7 apply to the code;
keep intact all notices of the absence of any warranty; and give all
recipients a copy of this License along with the Program.

  You may charge any price or no price for each copy that you convey,
and you may offer support or warranty protection for a fee.

  5. Conveying Modified Source Versions.

  You may convey a work based on the Program, or the modifications to
produce it from the Program, in the form of source code under the
terms of section 4, provided that you also meet all of these conditions:

    a) The work must carry prominent notices stating that you modified
    it, and giving a relevant date.

    b) The work must carry prominent notices stating that it is
    released under this License and any conditions added under section
    7.  This requirement modifies the requirement in section 4 to
    "keep intact all notices".

    c) You must license the entire work, as a whole, under this
    License to anyone who comes into possession of a copy.  This
    License will therefore apply, along with any applicable section 7
    additional terms, to the whole of the work, and all its parts,
    regardless of how they are packaged.  This License gives no
    permission to license the work in any other way, but it does not
    invalidate such permission if you have separately received it.

    d) If the work has interactive user interfaces, each must display
    Appropriate Legal Notices; however, if the Program has interactive
    interfaces that do not display Appropriate Legal Notices, your
    work need not make them do so.

  A compilation of a covered work with other separate and independent
works, which are not by their nature extensions of the covered work,
and which are not combined with it such as to form a larger program,
in or on a volume of a storage or distribution medium, is called an
"aggregate" if the compilation and its resulting copyright are not
used to limit the access or legal rights of the compilation's users
beyond what the individual works permit.  Inclusion of a covered work
in an aggregate does not cause this License to apply to the other
parts of the aggregate.

  6. Conveying Non-Source Forms.

  You may convey a covered work in object code form under the terms
of sections 4 and 5, provided that you also convey the
machine-readable Corresponding Source under the terms of this License,
in one of these ways:

    a) Convey the object code in, or embodied in, a physical product
    (including a physical distribution medium), accompanied by the
    Corresponding Source fixed on a durable physical medium
    customarily used for software interchange.

    b) Convey the object code in, or embodied in, a physical product
    (including a physical distribution medium), accompanied by a
    written offer, valid for at least three years and valid for as
    long as you offer spare parts or customer support for that product
    model, to give anyone who possesses the object code either (1) a
    copy of the Corresponding Source for all the software in the
    product that is covered by this License, on a durable physical
    medium customarily used for software interchange, for a price no
    more than your reasonable cost of physically performing this
    conveying of source, or (2) access to copy the
    Corresponding Source from a network server at no charge.

    c) Convey individual copies of the object code with a copy of the
    written offer to provide the Corresponding Source.  This
    alternative is allowed only occasionally and noncommercially, and
    only if you received the object code with such an offer, in accord
    with subsection 6b.

    d) Convey the object code by offering access from a designated
    place (gratis or for a charge), and offer equivalent access to the
    Corresponding Source in the same way through the same place at no
    further charge.  You need not require recipients to copy the
    Corresponding Source along with the object code.  If the place to
    copy the object code is a network server, the Corresponding Source
    may be on a different server (operated by you or a third party)
    that supports equivalent copying facilities, provided you maintain
    clear directions next to the object code saying where to find the
    Corresponding Source.  Regardless of what server hosts the
    Corresponding Source, you remain obligated to ensure that it is
    available for as long as needed to satisfy these requirements.

    e) Convey the object code using peer-to-peer transmission, provided
    you inform other peers where the object code and Corresponding
    Source of the work are being offered to the general public at no
    charge under subsection 6d.

  A separable portion of the object code, whose source code is excluded
from the Corresponding Source as a System Library, need not be
included in conveying the object code work.

  A "User Product" is either (1) a "consumer product", which means any
tangible personal property which is normally used for personal, family,
or household purposes, or (2) anything designed or sold for incorporation
into a dwelling.  In determining whether a product is a consumer product,
doubtful cases shall be resolved in favor of coverage.  For a particular
product received by a particular user, "normally used" refers to a
typical or common use of that class of product, regardless of the status
of the particular user or of the way in which the particular user
actually uses, or expects or is expected to use, the product.  A product
is a consumer product regardless of whether the product has substantial
commercial, industrial or non-consumer uses, unless such uses represent
the only significant mode of use of the product.

  "Installation Information" for a User Product means any methods,
procedures, authorization keys, or other information required to install
and execute modified versions of a covered work in that User Product from
a modified version of its Corresponding Source.  The information must
suffice to ensure that the continued functioning of the modified object
code is in no case prevented or interfered with solely because
modification has been made.

  If you convey an object code work under this section in, or with, or
specifically for use in, a User Product, and the conveying occurs as
part of a transaction in which the right of possession and use of the
User Product is transferred to the recipient in perpetuity or for a
fixed term (regardless of how the transaction is characterized), the
Corresponding Source conveyed under this section must be accompanied
by the Installation Information.  But this requirement does not apply
if neither you nor any third party retains the ability to install
modified object code on the User Product (for example, the work has
been installed in ROM).

  The requirement to provide Installation Information does not include a
requirement to continue to provide support service, warranty, or updates
for a work that has been modified or installed by the recipient, or for
the User Product in which it has been modified or installed.  Access to a
network may be denied when the modification itself materially and
adversely affects the operation of the network or violates the rules and
protocols for communication across the network.

  Corresponding Source conveyed, and Installation Information provided,
in accord with this section must be in a format that is publicly
documented (and with an implementation available to the public in
source code form), and must require no special password or key for
unpacking, reading or copying.

  7. Additional Terms.

  "Additional permissions" are terms that supplement the terms of this
License by making exceptions from one or more of its conditions.
Additional permissions that are applicable to the entire Program shall
be treated as though they were included in this License, to the extent
that they are valid under applicable law.  If additional permissions
apply only to part of the Program, that part may be used separately
under those permissions, but the entire Program remains governed by
this License without regard to the additional permissions.

  When you convey a copy of a covered work, you may at your option
remove any additional permissions from that copy, or from any part of
it.  (Additional permissions may be written to require their own
removal in certain cases when you modify the work.)  You may place
additional permissions on material, added by you to a covered work,
for which you have or can give appropriate copyright permission.

  Notwithstanding any other provision of this License, for material you
add to a covered work, you may (if authorized by the copyright holders of
that material) supplement the terms of this License with terms:

    a) Disclaiming warranty or limiting liability differently from the
    terms of sections 15 and 16 of this License; or

    b) Requiring preservation of specified reasonable legal notices or
    author attributions in that material or in the Appropriate Legal
    Notices displayed by works containing it; or

    c) Prohibiting misrepresentation of the origin of that material, or
    requiring that modified versions of such material be marked in
    reasonable ways as different from the original version; or

    d) Limiting the use for publicity purposes of names of licensors or
    authors of the material; or

    e) Declining to grant rights under trademark law for use of some
    trade names, trademarks, or service marks; or

    f) Requiring indemnification of licensors and authors of that
    material by anyone who conveys the material (or modified versions of
    it) with contractual assumptions of liability to the recipient, for
    any liability that these contractual assumptions directly impose on
    those licensors and authors.

  All other non-permissive additional terms are considered "further
restrictions" within the meaning of section 10.  If the Program as you
received it, or any part of it, contains a notice stating that it is
governed by this License along with a term that is a further
restriction, you may remove that term.  If a license document contains
a further restriction but permits relicensing or conveying under this
License, you may add to a covered work material governed by the terms
of that license document, provided that the further restriction does
not survive such relicensing or conveying.

  If you add terms to a covered work in accord with this section, you
must place, in the relevant source files, a statement of the
additional terms that apply to those files, or a notice indicating
where to find the applicable terms.

  Additional terms, permissive or non-permissive, may be stated in the
form of a separately written license, or stated as exceptions;
the above requirements apply either way.

  8. Termination.

  You may not propagate or modify a covered work except as expressly
provided under this License.  Any attempt otherwise to propagate or
modify it is void, and will automatically terminate your rights under
this License (including any patent licenses granted under the third
paragraph of section 11).

  However, if you cease all violation of this License, then your
license from a particular copyright holder is reinstated (a)
provisionally, unless and until the copyright holder explicitly and
finally terminates your license, and (b) permanently, if the copyright
holder fails to notify you of the violation by some reasonable means
prior to 60 days after the cessation.

  Moreover, your license from a particular copyright holder is
reinstated permanently if the copyright holder notifies you of the
violation by some reasonable means, this is the first time you have
received notice of violation of this License (for any work) from that
copyright holder, and you cure the violation prior to 30 days after
your receipt of the notice.

  Termination of your rights under this section does not terminate the
licenses of parties who have received copies or rights from you under
this License.  If your rights have been terminated and not permanently
reinstated, you do not qualify to receive new licenses for the same
material under section 10.

  9. Acceptance Not Required for Having Copies.

  You are not required to accept this License in order to receive or
run a copy of the Program.  Ancillary propagation of a covered work
occurring solely as a consequence of using peer-to-peer transmission
to receive a copy likewise does not require acceptance.  However,
nothing other than this License grants you permission to propagate or
modify any covered work.  These actions infringe copyright if you do
not accept this License.  Therefore, by modifying or propagating a
covered work, you indicate your acceptance of this License to do so.

  10. Automatic Licensing of Downstream Recipients.

  Each time you convey a covered work, the recipient automatically
receives a license from the original licensors, to run, modify and
propagate that work, subject to this License.  You are not responsible
for enforcing compliance by third parties with this License.

  An "entity transaction" is a transaction transferring control of an
organization, or substantially all assets of one, or subdividing an
organization, or merging organizations.  If propagation of a covered
work results from an entity transaction, each party to that
transaction who receives a copy of the work also receives whatever
licenses to the work the party's predecessor in interest had or could
give under the previous paragraph, plus a right to possession of the
Corresponding Source of the work from the predecessor in interest, if
the predecessor has it or can get it with reasonable efforts.

  You may not impose any further restrictions on the exercise of the
rights granted or affirmed under this License.  For example, you may
not impose a license fee, royalty, or other charge for exercise of
rights granted under this License, and you may not initiate litigation
(including a cross-claim or counterclaim in a lawsuit) alleging that
any patent claim is infringed by making, using, selling, offering for
sale, or importing the Program or any portion of it.

  11. Patents.

  A "contributor" is a copyright holder who authorizes use under this
License of the Program or a work on which the Program is based.  The
work thus licensed is called the contributor's "contributor version".

  A contributor's "essential patent claims" are all patent claims
owned or controlled by the contributor, whether already acquired or
hereafter acquired, that would be infringed by some manner, permitted
by this License, of making, using, or selling its contributor version,
but do not include claims that would be infringed only as a
consequence of further modification of the contributor version.  For
purposes of this definition, "control" includes the right to grant
patent sublicenses in a manner consistent with the requirements of
this License.

  Each contributor grants you a non-exclusive, worldwide, royalty-free
patent license under the contributor's essential patent claims, to
make, use, sell, offer for sale, import and otherwise run, modify and
propagate the contents of its contributor version.

  In the following three paragraphs, a "patent license" is any express
agreement or commitment, however denominated, not to enforce a patent
(such as an express permission to practice a patent or covenant not to
sue for patent infringement).  To "grant" such a patent license to a
party means to make such an agreement or commitment not to enforce a
patent against the party.

  If you convey a covered work, knowingly relying on a patent license,
and the Corresponding Source of the work is not available for anyone
to copy, free of charge and under the terms of this License, through a
publicly available network server or other readily accessible means,
then you must either (1) cause the Corresponding Source to be so
available, or (2) arrange to deprive yourself of the benefit of the
patent license for this particular work, or (3) arrange, in a manner
consistent with the requirements of this License, to extend the patent
license to downstream recipients.  "Knowingly relying" means you have
actual knowledge that, but for the patent license, your conveying the
covered work in a country, or your recipient's use of the covered work
in a country, would infringe one or more identifiable patents in that
country that you have reason to believe are valid.

  If, pursuant to or in connection with a single transaction or
arrangement, you convey, or propagate by procuring conveyance of, a
covered work, and grant a patent license to some of the parties
receiving the covered work authorizing them to use, propagate, modify
or convey a specific copy of the covered work, then the patent license
you grant is automatically extended to all recipients of the covered
work and works based on it.

  A patent license is "discriminatory" if it does not include within
the scope of its coverage, prohibits the exercise of, or is
conditioned on the non-exercise of one or more of the rights that are
specifically granted under this License.  You may not convey a covered
work if you are a party to an arrangement with a third party that is
in the business of distributing software, under which you make payment
to the third party based on the extent of your activity of conveying
the work, and under which the third party grants, to any of the
parties who would receive the covered work from you, a discriminatory
patent license (a) in connection with copies of the covered work
conveyed by you (or copies made from those copies), or (b) primarily
for and in connection with specific products or compilations that
contain the covered work, unless you entered into that arrangement,
or that patent license was granted, prior to 28 March 2007.

  Nothing in this License shall be construed as excluding or limiting
any implied license or other defenses to infringement that may
otherwise be available to you under applicable patent law.

  12. No Surrender of Others' Freedom.

  If conditions are imposed on you (whether by court order, agreement or
otherwise) that contradict the conditions of this License, they do not
excuse you from the conditions of this License.  If you cannot convey a
covered work so as to satisfy simultaneously your obligations under this
License and any other pertinent obligations, then as a consequence you may
not convey it at all.  For example, if you agree to terms that obligate you
to collect a royalty for further conveying from those to whom you convey
the Program, the only way you could satisfy both those terms and this
License would be to refrain entirely from conveying the Program.

  13. Use with the GNU Affero General Public License.

  Notwithstanding any other provision of this License, you have
permission to link or combine any covered work with a work licensed
under version 3 of the GNU Affero General Public License into a single
combined work, and to convey the resulting work.  The terms of this
License will continue to apply to the part which is the covered work,
but the special requirements of the GNU Affero General Public License,
section 13, concerning interaction through a network will apply to the
combination as such.

  14. Revised Versions of this License.

  The Free Software Foundation may publish revised and/or new versions of
the GNU General Public License from time to time.  Such new versions will
be similar in spirit to the present version, but may differ in detail to
address new problems or concerns.

  Each version is given a distinguishing version number.  If the
Program specifies that a certain numbered version of the GNU General
Public License "or any later version" applies to it, you have the
option of following the terms and conditions either of that numbered
version or of any later version published by the Free Software
Foundation.  If the Program does not specify a version number of the
GNU General Public License, you may choose any version ever published
by the Free Software Foundation.

  If the Program specifies that a proxy can decide which future
versions of the GNU General Public License can be used, that proxy's
public statement of acceptance of a version permanently authorizes you
to choose that version for the Program.

  Later license versions may give you additional or different
permissions.  However, no additional obligations are imposed on any
author or copyright holder as a result of your choosing to follow a
later version.

  15. Disclaimer of Warranty.

  THERE IS NO WARRANTY FOR THE PROGRAM, TO THE EXTENT PERMITTED BY
APPLICABLE LAW.  EXCEPT WHEN OTHERWISE STATED IN WRITING THE COPYRIGHT
HOLDERS AND/OR OTHER PARTIES PROVIDE THE PROGRAM "AS IS" WITHOUT WARRANTY
OF ANY KIND, EITHER EXPRESSED OR IMPLIED, INCLUDING, BUT NOT LIMITED TO,
THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
PURPOSE.  THE ENTIRE RISK AS TO THE QUALITY AND PERFORMANCE OF THE PROGRAM
IS WITH YOU.  SHOULD THE PROGRAM PROVE DEFECTIVE, YOU ASSUME THE COST OF
ALL NECESSARY SERVICING, REPAIR OR CORRECTION.

  16. Limitation of Liability.

  IN NO EVENT UNLESS REQUIRED BY APPLICABLE LAW OR AGREED TO IN WRITING
WILL ANY COPYRIGHT HOLDER, OR ANY OTHER PARTY WHO MODIFIES AND/OR CONVEYS
THE PROGRAM AS PERMITTED ABOVE, BE LIABLE TO YOU FOR DAMAGES, INCLUDING ANY
GENERAL, SPECIAL, INCIDENTAL OR CONSEQUENTIAL DAMAGES ARISING OUT OF THE
USE OR INABILITY TO USE THE PROGRAM (INCLUDING BUT NOT LIMITED TO LOSS OF
DATA OR DATA BEING RENDERED INACCURATE OR LOSSES SUSTAINED BY YOU OR THIRD
PARTIES OR A FAILURE OF THE PROGRAM TO OPERATE WITH ANY OTHER PROGRAMS),
EVEN IF SUCH HOLDER OR OTHER PARTY HAS BEEN ADVISED OF THE POSSIBILITY OF
SUCH DAMAGES.

  17. Interpretation of Sections 15 and 16.

  If the disclaimer of warranty and limitation of liability provided
above cannot be given local legal effect according to their terms,
reviewing courts shall apply local law that most closely approximates
an absolute waiver of all civil liability in connection with the
Program, unless a warranty or assumption of liability accompanies a
copy of the Program in return for a fee.

                     END OF TERMS AND CONDITIONS
</pre>
EOF;
	echo $lang->sprintf($lang->license_step, $license);
	$output->print_footer('requirements_check');
}

function requirements_check()
{
	global $output, $mybb, $dboptions, $lang;

	$mybb->input['action'] = "requirements_check";
	$output->print_header($lang->req_check, 'requirements');
	echo $lang->req_step_top;
	$errors = array();
	$showerror = 0;

	if(!file_exists(MYBB_ROOT."/inc/config.php"))
	{
		if(!@rename(MYBB_ROOT."/inc/config.default.php", MYBB_ROOT."/inc/config.php"))
		{
			if(!$configwritable)
			{
				$errors[] = $lang->sprintf($lang->req_step_error_box, $lang->req_step_error_configdefaultfile);
				$configstatus = $lang->sprintf($lang->req_step_span_fail, $lang->not_writable);
				$showerror = 1;
			}
		}
	}

	// Check PHP Version
	if(version_compare(PHP_VERSION, '5.2.0', "<"))
	{
		$errors[] = $lang->sprintf($lang->req_step_error_box, $lang->sprintf($lang->req_step_error_phpversion, PHP_VERSION));
		$phpversion = $lang->sprintf($lang->req_step_span_fail, PHP_VERSION);
		$showerror = 1;
	}
	else
	{
		$phpversion = $lang->sprintf($lang->req_step_span_pass, PHP_VERSION);
	}

	$mboptions = array();

	if(function_exists('mb_detect_encoding'))
	{
		$mboptions[] = $lang->multi_byte;
	}

	if(function_exists('iconv'))
	{
		$mboptions[] = 'iconv';
	}

	// Check Multibyte extensions
	if(count($mboptions) < 1)
	{
		$mbstatus = $lang->sprintf($lang->req_step_span_fail, $lang->none);
	}
	else
	{
		$mbstatus = implode(', ', $mboptions);
	}

	// Check database engines
	if(count($dboptions) < 1)
	{
		$errors[] = $lang->sprintf($lang->req_step_error_box, $lang->req_step_error_dboptions);
		$dbsupportlist = $lang->sprintf($lang->req_step_span_fail, $lang->none);
		$showerror = 1;
	}
	else
	{
		foreach($dboptions as $dboption)
		{
			$dbsupportlist[] = $dboption['title'];
		}
		$dbsupportlist = implode(', ', $dbsupportlist);
	}

	// Check XML parser is installed
	if(!function_exists('xml_parser_create'))
	{
		$errors[] = $lang->sprintf($lang->req_step_error_box, $lang->req_step_error_xmlsupport);
		$xmlstatus = $lang->sprintf($lang->req_step_span_fail, $lang->not_installed);
		$showerror = 1;
	}
	else
	{
		$xmlstatus = $lang->sprintf($lang->req_step_span_pass, $lang->installed);
	}

	// Check config file is writable
	$configwritable = @fopen(MYBB_ROOT.'inc/config.php', 'w');
	if(!$configwritable)
	{
		$errors[] = $lang->sprintf($lang->req_step_error_box, $lang->req_step_error_configfile);
		$configstatus = $lang->sprintf($lang->req_step_span_fail, $lang->not_writable);
		$showerror = 1;
	}
	else
	{
		$configstatus = $lang->sprintf($lang->req_step_span_pass, $lang->writable);
	}
	@fclose($configwritable);

	// Check settings file is writable
	$settingswritable = @fopen(MYBB_ROOT.'inc/settings.php', 'w');
	if(!$settingswritable)
	{
		$errors[] = $lang->sprintf($lang->req_step_error_box, $lang->req_step_error_settingsfile);
		$settingsstatus = $lang->sprintf($lang->req_step_span_fail, $lang->not_writable);
		$showerror = 1;
	}
	else
	{
		$settingsstatus = $lang->sprintf($lang->req_step_span_pass, $lang->writable);
	}
	@fclose($settingswritable);

	// Check cache directory is writable
	$cachewritable = @fopen(MYBB_ROOT.'cache/test.write', 'w');
	if(!$cachewritable)
	{
		$errors[] = $lang->sprintf($lang->req_step_error_box, $lang->req_step_error_cachedir);
		$cachestatus = $lang->sprintf($lang->req_step_span_fail, $lang->not_writable);
		$showerror = 1;
		@fclose($cachewritable);
	}
	else
	{
		$cachestatus = $lang->sprintf($lang->req_step_span_pass, $lang->writable);
		@fclose($cachewritable);
	  	@my_chmod(MYBB_ROOT.'cache', '0777');
	  	@my_chmod(MYBB_ROOT.'cache/test.write', '0777');
		@unlink(MYBB_ROOT.'cache/test.write');
	}

	// Check upload directory is writable
	$uploadswritable = @fopen(MYBB_ROOT.'uploads/test.write', 'w');
	if(!$uploadswritable)
	{
		$errors[] = $lang->sprintf($lang->req_step_error_box, $lang->req_step_error_uploaddir);
		$uploadsstatus = $lang->sprintf($lang->req_step_span_fail, $lang->not_writable);
		$showerror = 1;
		@fclose($uploadswritable);
	}
	else
	{
		$uploadsstatus = $lang->sprintf($lang->req_step_span_pass, $lang->writable);
		@fclose($uploadswritable);
	  	@my_chmod(MYBB_ROOT.'uploads', '0777');
	  	@my_chmod(MYBB_ROOT.'uploads/test.write', '0777');
		@unlink(MYBB_ROOT.'uploads/test.write');
	}

	// Check avatar directory is writable
	$avatarswritable = @fopen(MYBB_ROOT.'uploads/avatars/test.write', 'w');
	if(!$avatarswritable)
	{
		$errors[] =  $lang->sprintf($lang->req_step_error_box, $lang->req_step_error_avatardir);
		$avatarsstatus = $lang->sprintf($lang->req_step_span_fail, $lang->not_writable);
		$showerror = 1;
		@fclose($avatarswritable);
	}
	else
	{
		$avatarsstatus = $lang->sprintf($lang->req_step_span_pass, $lang->writable);
		@fclose($avatarswritable);
		@my_chmod(MYBB_ROOT.'uploads/avatars', '0777');
	  	@my_chmod(MYBB_ROOT.'uploads/avatars/test.write', '0777');
		@unlink(MYBB_ROOT.'uploads/avatars/test.write');
  	}

	// Output requirements page
	echo $lang->sprintf($lang->req_step_reqtable, $phpversion, $dbsupportlist, $mbstatus, $xmlstatus, $configstatus, $settingsstatus, $cachestatus, $uploadsstatus, $avatarsstatus);

	if($showerror == 1)
	{
		$error_list = error_list($errors);
		echo $lang->sprintf($lang->req_step_error_tablelist, $error_list);
		echo "\n			<input type=\"hidden\" name=\"action\" value=\"{$mybb->input['action']}\" />";
		echo "\n				<div id=\"next_button\"><input type=\"submit\" class=\"submit_button\" value=\"{$lang->recheck} &raquo;\" /></div><br style=\"clear: both;\" />\n";
		$output->print_footer();
	}
	else
	{
		echo $lang->req_step_reqcomplete;
		$output->print_footer('database_info');
	}
}

function database_info()
{
	global $output, $dbinfo, $errors, $mybb, $dboptions, $lang;

	$mybb->input['action'] = 'database_info';
	$output->print_header($lang->db_config, 'dbconfig');

	echo "<script type=\"text/javascript\">
		function updateDBSettings()
		{
			var dbengine = \$(\"#dbengine\").val();
			$('.db_settings').each(function()
			{
				var element = $(this);
				element.addClass('db_settings');
				if(dbengine+'_settings' == element.attr('id'))
				{
					element.show();
				}
				else
				{
					element.hide();
				}
			});
		}
		$(function()
		{
			updateDBSettings();
		});
		</script>";

	// Check for errors from this stage
	if(is_array($errors))
	{
		$error_list = error_list($errors);
		echo $lang->sprintf($lang->db_step_error_config, $error_list);
	}
	else
	{
		echo $lang->db_step_config_db;
	}

	$dbengines = '';

	// Loop through database engines
	foreach($dboptions as $dbfile => $dbtype)
	{
		if($mybb->get_input('dbengine') == $dbfile)
		{
			$dbengines .= "<option value=\"{$dbfile}\" selected=\"selected\">{$dbtype['title']}</option>";
		}
		else
		{
			$dbengines .= "<option value=\"{$dbfile}\">{$dbtype['title']}</option>";
		}
	}

	$db_info = array();
	foreach($dboptions as $dbfile => $dbtype)
	{
		require_once MYBB_ROOT."inc/db_{$dbfile}.php";
		$db = new $dbtype['class'];
		$encodings = $db->fetch_db_charsets();
		$encoding_select = '';
		$mybb->input['config'] = $mybb->get_input('config', MyBB::INPUT_ARRAY);
		if(empty($mybb->input['config'][$dbfile]['dbhost']))
		{
			$mybb->input['config'][$dbfile]['dbhost'] = "localhost";
		}
		if(empty($mybb->input['config'][$dbfile]['tableprefix']))
		{
			$mybb->input['config'][$dbfile]['tableprefix'] = "mybb_";
		}
		if(empty($mybb->input['config'][$dbfile]['dbname']))
		{
			$mybb->input['config'][$dbfile]['dbname'] = '';
		}
		if(empty($mybb->input['config'][$dbfile]['dbuser']))
		{
			$mybb->input['config'][$dbfile]['dbuser'] = '';
		}
		if(empty($mybb->input['config'][$dbfile]['dbpass']))
		{
			$mybb->input['config'][$dbfile]['dbpass'] = '';
		}
		if(empty($mybb->input['config'][$dbfile]['encoding']))
		{
			$mybb->input['config'][$dbfile]['encoding'] = "utf8";
		}

		$class = '';
		if(empty($first) && !$mybb->get_input('dbengine'))
		{
			$mybb->input['dbengine'] = $dbfile;
			$first = true;
		}
		if($dbfile == $mybb->input['dbengine'])
		{
			$class = "_selected";
		}

		$db_info[$dbfile] = "
			<tbody id=\"{$dbfile}_settings\" class=\"db_settings db_type{$class}\">
				<tr>
					<th colspan=\"2\" class=\"first last\">{$dbtype['title']} {$lang->database_settings}</th>
				</tr>";

		// SQLite gets some special settings
		if($dbfile == 'sqlite')
		{
			$db_info[$dbfile] .= "
				<tr class=\"alt_row\">
					<td class=\"first\"><label for=\"config_{$dbfile}_dbname\">{$lang->database_path}</label></td>
					<td class=\"last alt_col\"><input type=\"text\" class=\"text_input\" name=\"config[{$dbfile}][dbname]\" id=\"config_{$dbfile}_dbname\" value=\"".htmlspecialchars_uni($mybb->input['config'][$dbfile]['dbname'])."\" /></td>
				</tr>";
		}
		// Others get db host, username, password etc
		else
		{
			$db_info[$dbfile] .= "
				<tr class=\"alt_row\">
					<td class=\"first\"><label for=\"config_{$dbfile}_dbhost\">{$lang->database_host}</label></td>
					<td class=\"last alt_col\"><input type=\"text\" class=\"text_input\" name=\"config[{$dbfile}][dbhost]\" id=\"config_{$dbfile}_dbhost\" value=\"".htmlspecialchars_uni($mybb->input['config'][$dbfile]['dbhost'])."\" /></td>
				</tr>
				<tr>
					<td class=\"first\"><label for=\"config_{$dbfile}_dbuser\">{$lang->database_user}</label></td>
					<td class=\"last alt_col\"><input type=\"text\" class=\"text_input\" name=\"config[{$dbfile}][dbuser]\" id=\"config_{$dbfile}_dbuser\" value=\"".htmlspecialchars_uni($mybb->input['config'][$dbfile]['dbuser'])."\" /></td>
				</tr>
				<tr class=\"alt_row\">
					<td class=\"first\"><label for=\"config_{$dbfile}_dbpass\">{$lang->database_pass}</label></td>
					<td class=\"last alt_col\"><input type=\"password\" class=\"text_input\" name=\"config[{$dbfile}][dbpass]\" id=\"config_{$dbfile}_dbpass\" value=\"".htmlspecialchars_uni($mybb->input['config'][$dbfile]['dbpass'])."\" /></td>
				</tr>
				<tr class=\"last\">
					<td class=\"first\"><label for=\"config_{$dbfile}_dbname\">{$lang->database_name}</label></td>
					<td class=\"last alt_col\"><input type=\"text\" class=\"text_input\" name=\"config[{$dbfile}][dbname]\" id=\"config_{$dbfile}_dbname\" value=\"".htmlspecialchars_uni($mybb->input['config'][$dbfile]['dbname'])."\" /></td>
				</tr>";
		}

		// Now we're up to table settings
		$db_info[$dbfile] .= "
			<tr>
				<th colspan=\"2\" class=\"first last\">{$dbtype['title']} {$lang->table_settings}</th>
			</tr>
			<tr class=\"first\">
				<td class=\"first\"><label for=\"config_{$dbfile}_tableprefix\">{$lang->table_prefix}</label></td>
				<td class=\"last alt_col\"><input type=\"text\" class=\"text_input\" name=\"config[{$dbfile}][tableprefix]\" id=\"config_{$dbfile}_tableprefix\" value=\"".htmlspecialchars_uni($mybb->input['config'][$dbfile]['tableprefix'])."\" /></td>
			</tr>
			";

		// Encoding selection only if supported
		if(is_array($encodings))
		{
			$select_options = "";
			foreach($encodings as $encoding => $title)
			{
				if($mybb->input['config'][$dbfile]['encoding'] == $encoding)
				{
					$select_options .= "<option value=\"{$encoding}\" selected=\"selected\">{$title}</option>";
				}
				else
				{
					$select_options .= "<option value=\"{$encoding}\">{$title}</option>";
				}
			}
			$db_info[$dbfile] .= "
				<tr class=\"last\">
					<td class=\"first\"><label for=\"config_{$dbfile}_encoding\">{$lang->table_encoding}</label></td>
					<td class=\"last alt_col\"><select name=\"config[{$dbfile}][encoding]\" id=\"config_{$dbfile}_encoding\">{$select_options}</select></td>
				</tr>
				</tbody>";
		}
	}
	$dbconfig = implode("", $db_info);

	echo $lang->sprintf($lang->db_step_config_table, $dbengines, $dbconfig);
	$output->print_footer('create_tables');
}

function create_tables()
{
	global $output, $dbinfo, $errors, $mybb, $dboptions, $lang;

	$mybb->input['dbengine'] = $mybb->get_input('dbengine');
	if(!file_exists(MYBB_ROOT."inc/db_{$mybb->input['dbengine']}.php"))
	{
		$errors[] = $lang->db_step_error_invalidengine;
		database_info();
	}

	$mybb->input['config'] = $mybb->get_input('config', MyBB::INPUT_ARRAY);
	$config = $mybb->input['config'][$mybb->input['dbengine']];

	if(strstr($mybb->input['dbengine'], "sqlite") !== false)
	{
		if(strstr($config['dbname'], "./") !== false || strstr($config['dbname'], "../") !== false || empty($config['dbname']))
		{
			$errors[] = $lang->db_step_error_sqlite_invalid_dbname;
			database_info();
		}
	}

	// Attempt to connect to the db
	require_once MYBB_ROOT."inc/db_{$mybb->input['dbengine']}.php";
	switch($mybb->input['dbengine'])
	{
		case "sqlite":
			$db = new DB_SQLite;
			break;
		case "pgsql":
			$db = new DB_PgSQL;
			break;
		case "mysqli":
			$db = new DB_MySQLi;
			break;
		default:
			$db = new DB_MySQL;
	}
 	$db->error_reporting = 0;

	$connect_array = array(
		"hostname" => $config['dbhost'],
		"username" => $config['dbuser'],
		"password" => $config['dbpass'],
		"database" => $config['dbname'],
		"encoding" => $config['encoding']
	);

	$connection = $db->connect($connect_array);
	if($connection === false)
	{
		$errors[] = $lang->sprintf($lang->db_step_error_noconnect, $config['dbhost']);
	}
	// double check if the DB exists for MySQL
	elseif(method_exists($db, 'select_db') && !$db->select_db($config['dbname']))
	{
		$errors[] = $lang->sprintf($lang->db_step_error_nodbname, $config['dbname']);
	}

	// Most DB engines only allow certain characters in the table name. Oracle requires an alphabetic character first.
	if((!preg_match("#^[A-Za-z][A-Za-z0-9_]*$#", $config['tableprefix'])) && $config['tableprefix'] != '')
	{
		$errors[] = $lang->db_step_error_invalid_tableprefix;
	}

	// Needs to be smaller then 64 characters total (MySQL Limit).
	// This allows 24 characters for the actual table name, which should be sufficient.
	if(strlen($config['tableprefix']) > 40)
	{
		$errors[] = $lang->db_step_error_tableprefix_too_long;
	}

	if(($db->engine == 'mysql' || $db->engine == 'mysqli') && $config['encoding'] == 'utf8mb4' && version_compare($db->get_version(), '5.5.3', '<'))
	{
		$errors[] = $lang->db_step_error_utf8mb4_error;
	}

	if(is_array($errors))
	{
		database_info();
	}

	// Decide if we can use a database encoding or not
	if($db->fetch_db_charsets() != false)
	{
		$db_encoding = "\$config['database']['encoding'] = '{$config['encoding']}';";
	}
	else
	{
		$db_encoding = "// \$config['database']['encoding'] = '{$config['encoding']}';";
	}

	$config['dbpass'] = addslashes($config['dbpass']);

	// Write the configuration file
	$configdata = "<?php
/**
 * Database configuration
 *
 * Please see the MyBB Docs for advanced
 * database configuration for larger installations
 * http://docs.mybb.com/
 */

\$config['database']['type'] = '{$mybb->input['dbengine']}';
\$config['database']['database'] = '{$config['dbname']}';
\$config['database']['table_prefix'] = '{$config['tableprefix']}';

\$config['database']['hostname'] = '{$config['dbhost']}';
\$config['database']['username'] = '{$config['dbuser']}';
\$config['database']['password'] = '{$config['dbpass']}';

/**
 * Admin CP directory
 *  For security reasons, it is recommended you
 *  rename your Admin CP directory. You then need
 *  to adjust the value below to point to the
 *  new directory.
 */

\$config['admin_dir'] = 'admin';

/**
 * Hide all Admin CP links
 *  If you wish to hide all Admin CP links
 *  on the front end of the board after
 *  renaming your Admin CP directory, set this
 *  to 1.
 */

\$config['hide_admin_links'] = 0;

/**
 * Data-cache configuration
 *  The data cache is a temporary cache
 *  of the most commonly accessed data in MyBB.
 *  By default, the database is used to store this data.
 *
 *  If you wish to use the file system (cache/ directory), MemCache (or MemCached), xcache, APC, or eAccelerator
 *  you can change the value below to 'files', 'memcache', 'memcached', 'xcache', 'apc' or 'eaccelerator' from 'db'.
 */

\$config['cache_store'] = 'db';

/**
 * Memcache configuration
 *  If you are using memcache or memcached as your
 *  data-cache, you need to configure the hostname
 *  and port of your memcache server below.
 *
 * If not using memcache, ignore this section.
 */

\$config['memcache']['host'] = 'localhost';
\$config['memcache']['port'] = 11211;

/**
 * Super Administrators
 *  A comma separated list of user IDs who cannot
 *  be edited, deleted or banned in the Admin CP.
 *  The administrator permissions for these users
 *  cannot be altered either.
 */

\$config['super_admins'] = '1';

/**
 * Database Encoding
 *  If you wish to set an encoding for MyBB uncomment
 *  the line below (if it isn't already) and change
 *  the current value to the mysql charset:
 *  http://dev.mysql.com/doc/refman/5.1/en/charset-mysql.html
 */

{$db_encoding}

/**
 * Automatic Log Pruning
 *  The MyBB task system can automatically prune
 *  various log files created by MyBB.
 *  To enable this functionality for the logs below, set the
 *  the number of days before each log should be pruned.
 *  If you set the value to 0, the logs will not be pruned.
 */

\$config['log_pruning'] = array(
	'admin_logs' => 365, // Administrator logs
	'mod_logs' => 365, // Moderator logs
	'task_logs' => 30, // Scheduled task logs
	'mail_logs' => 180, // Mail error logs
	'user_mail_logs' => 180, // User mail logs
	'promotion_logs' => 180 // Promotion logs
);

";

	$file = fopen(MYBB_ROOT.'inc/config.php', 'w');
	fwrite($file, $configdata);
	fclose($file);

	// Error reporting back on
 	$db->error_reporting = 1;

	$output->print_header($lang->table_creation, 'createtables');
	echo $lang->sprintf($lang->tablecreate_step_connected, $dboptions[$mybb->input['dbengine']]['short_title'], $db->get_version());

	if($dboptions[$mybb->input['dbengine']]['structure_file'])
	{
		$structure_file = $dboptions[$mybb->input['dbengine']]['structure_file'];
	}
	else
	{
		$structure_file = 'mysql_db_tables.php';
	}

	require_once INSTALL_ROOT."resources/{$structure_file}";
	foreach($tables as $val)
	{
		$val = preg_replace('#mybb_(\S+?)([\s\.,\(]|$)#', $config['tableprefix'].'\\1\\2', $val);
		$val = preg_replace('#;$#', $db->build_create_table_collation().";", $val);
		preg_match('#CREATE TABLE (\S+)(\s?|\(?)\(#i', $val, $match);
		if($match[1])
		{
			$db->drop_table($match[1], false, false);
			echo $lang->sprintf($lang->tablecreate_step_created, $match[1]);
		}
		$db->query($val);
		if($match[1])
		{
			echo $lang->done . "<br />\n";
		}
	}
	echo $lang->tablecreate_step_done;
	$output->print_footer('populate_tables');
}

function populate_tables()
{
	global $output, $lang;

	require MYBB_ROOT.'inc/config.php';
	$db = db_connection($config);

	$output->print_header($lang->table_population, 'tablepopulate');
	echo $lang->sprintf($lang->populate_step_insert);

	if(!empty($dboptions[$db->type]['population_file']))
	{
		$population_file = $dboptions[$db->type]['population_file'];
	}
	else
	{
		$population_file = 'mysql_db_inserts.php';
	}

	require_once INSTALL_ROOT."resources/{$population_file}";
	foreach($inserts as $val)
	{
		$val = preg_replace('#mybb_(\S+?)([\s\.,]|$)#', $config['database']['table_prefix'].'\\1\\2', $val);
		$db->query($val);
	}

	// Update the sequences for PgSQL
	if($config['database']['type'] == "pgsql")
	{
		$db->query("SELECT setval('{$config['database']['table_prefix']}attachtypes_atid_seq', (SELECT max(atid) FROM {$config['database']['table_prefix']}attachtypes));");
		$db->query("SELECT setval('{$config['database']['table_prefix']}forums_fid_seq', (SELECT max(fid) FROM {$config['database']['table_prefix']}forums));");
		$db->query("SELECT setval('{$config['database']['table_prefix']}helpdocs_hid_seq', (SELECT max(hid) FROM {$config['database']['table_prefix']}helpdocs));");
		$db->query("SELECT setval('{$config['database']['table_prefix']}helpsections_sid_seq', (SELECT max(sid) FROM {$config['database']['table_prefix']}helpsections));");
		$db->query("SELECT setval('{$config['database']['table_prefix']}icons_iid_seq', (SELECT max(iid) FROM {$config['database']['table_prefix']}icons));");
		$db->query("SELECT setval('{$config['database']['table_prefix']}profilefields_fid_seq', (SELECT max(fid) FROM {$config['database']['table_prefix']}profilefields));");
		$db->query("SELECT setval('{$config['database']['table_prefix']}smilies_sid_seq', (SELECT max(sid) FROM {$config['database']['table_prefix']}smilies));");
		$db->query("SELECT setval('{$config['database']['table_prefix']}spiders_sid_seq', (SELECT max(sid) FROM {$config['database']['table_prefix']}spiders));");
		$db->query("SELECT setval('{$config['database']['table_prefix']}templategroups_gid_seq', (SELECT max(gid) FROM {$config['database']['table_prefix']}templategroups));");
	}

	echo $lang->populate_step_inserted;
	$output->print_footer('templates');
}

function insert_templates()
{
	global $mybb, $output, $cache, $db, $lang;

	require MYBB_ROOT.'inc/config.php';
	$db = db_connection($config);

	require_once MYBB_ROOT.'inc/class_datacache.php';
	$cache = new datacache;

	$output->print_header($lang->theme_installation, 'theme');

	echo $lang->theme_step_importing;

	$db->delete_query("themes");
	$db->delete_query("templates");
	$db->delete_query("themestylesheets");
	my_rmdir_recursive(MYBB_ROOT."cache/themes", array(MYBB_ROOT."cache/themes/index.html"));

	$insert_array = array(
		'title' => 'Default Templates'
	);
	$templateset = $db->insert_query("templatesets", $insert_array);

	$contents = @file_get_contents(INSTALL_ROOT.'resources/mybb_theme.xml');
	if(!empty($mybb->config['admin_dir']) && file_exists(MYBB_ROOT.$mybb->config['admin_dir']."/inc/functions_themes.php"))
	{
		require_once MYBB_ROOT.$mybb->config['admin_dir']."/inc/functions.php";
		require_once MYBB_ROOT.$mybb->config['admin_dir']."/inc/functions_themes.php";
	}
	elseif(file_exists(MYBB_ROOT."admin/inc/functions_themes.php"))
	{
		require_once MYBB_ROOT."admin/inc/functions.php";
		require_once MYBB_ROOT."admin/inc/functions_themes.php";
	}
	else
	{
		$output->print_error("Please make sure your admin directory is uploaded correctly.");
	}
	$theme_id = import_theme_xml($contents, array("templateset" => -2, "version_compat" => 1));
	$tid = build_new_theme("Default", null, $theme_id);

	// Update our properties template set to the correct one
	$query = $db->simple_select("themes", "stylesheets, properties", "tid='{$tid}'", array('limit' => 1));

	$theme = $db->fetch_array($query);
	$properties = my_unserialize($theme['properties']);
	$stylesheets = my_unserialize($theme['stylesheets']);

	$properties['templateset'] = $templateset;
	unset($properties['inherited']['templateset']);

	// 1.8: Stylesheet Colors
	$contents = @file_get_contents(INSTALL_ROOT.'resources/mybb_theme_colors.xml');

	require_once MYBB_ROOT."inc/class_xml.php";
	$parser = new XMLParser($contents);
	$tree = $parser->get_tree();

	if(is_array($tree) && is_array($tree['colors']))
	{
		if(is_array($tree['colors']['scheme']))
		{
			foreach($tree['colors']['scheme'] as $tag => $value)
			{
				$exp = explode("=", $value['value']);

				$properties['colors'][$exp[0]] = $exp[1];
			}
		}

		if(is_array($tree['colors']['stylesheets']))
		{
			$count = count($properties['disporder']) + 1;
			foreach($tree['colors']['stylesheets']['stylesheet'] as $stylesheet)
			{
				$new_stylesheet = array(
					"name" => $db->escape_string($stylesheet['attributes']['name']),
					"tid" => $tid,
					"attachedto" => $db->escape_string($stylesheet['attributes']['attachedto']),
					"stylesheet" => $db->escape_string($stylesheet['value']),
					"lastmodified" => TIME_NOW,
					"cachefile" => $db->escape_string($stylesheet['attributes']['name'])
				);

				$sid = $db->insert_query("themestylesheets", $new_stylesheet);
				$css_url = "css.php?stylesheet={$sid}";

				$cached = cache_stylesheet($tid, $stylesheet['attributes']['name'], $stylesheet['value']);

				if($cached)
				{
					$css_url = $cached;
				}

				// Add to display and stylesheet list
				$properties['disporder'][$stylesheet['attributes']['name']] = $count;
				$stylesheets[$stylesheet['attributes']['attachedto']]['global'][] = $css_url;

				++$count;
			}
		}
	}

	$db->update_query("themes", array("def" => 1, "properties" => $db->escape_string(my_serialize($properties)), "stylesheets" => $db->escape_string(my_serialize($stylesheets))), "tid = '{$tid}'");

	echo $lang->theme_step_imported;
	$output->print_footer('configuration');
}

function configure()
{
	global $output, $mybb, $errors, $lang;

	$output->print_header($lang->board_config, 'config');
	
	echo <<<EOF
		<script type="text/javascript">	
		function warnUser(inp, warn)
		{
			var parenttr = $('#'+inp.id).closest('tr');
			if(inp.value != inp.defaultValue)
			{
				if(!parenttr.next('.setting_peeker').length)
				{
					var revertlink = ' <a href="javascript:revertSetting(\''+inp.defaultValue+'\', \'#'+inp.id+'\');">{$lang->config_step_revert}</a>';
					parenttr.removeClass('last').after('<tr class="setting_peeker"><td colspan="2">'+warn+revertlink+'</td></tr>');
				}
			} else {
				parenttr.next('.setting_peeker').remove();
				if(parenttr.is(':last-child'))
				{
					parenttr.addClass('last');
				}
			}
		}
			
		function revertSetting(defval, inpid)
		{
			$(inpid).val(defval);			
			var parenttr = $(inpid).closest('tr');
			parenttr.next('.setting_peeker').remove();
			if(parenttr.is(':last-child'))
			{
				parenttr.addClass('last');
			}			
		}
		</script>
		
EOF;

	// If board configuration errors
	if(is_array($errors))
	{
		$error_list = error_list($errors);
		echo $lang->sprintf($lang->config_step_error_config, $error_list);

		$bbname = htmlspecialchars_uni($mybb->get_input('bbname'));
		$bburl = htmlspecialchars_uni($mybb->get_input('bburl'));
		$websitename = htmlspecialchars_uni($mybb->get_input('websitename'));
		$websiteurl = htmlspecialchars_uni($mybb->get_input('websiteurl'));
		$cookiedomain = htmlspecialchars_uni($mybb->get_input('cookiedomain'));
		$cookiepath = htmlspecialchars_uni($mybb->get_input('cookiepath'));
		$contactemail =  htmlspecialchars_uni($mybb->get_input('contactemail'));
	}
	else
	{
		$bbname = 'Forums';
		$cookiedomain = '';
		$websitename = 'Your Website';

		$protocol = "http://";
		if((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != "off"))
		{
			$protocol = "https://";
		}

		// Attempt auto-detection
		if($_SERVER['HTTP_HOST'])
		{
			$hostname = $protocol.$_SERVER['HTTP_HOST'];
			$cookiedomain = $_SERVER['HTTP_HOST'];
		}
		elseif($_SERVER['SERVER_NAME'])
		{
			$hostname = $protocol.$_SERVER['SERVER_NAME'];
			$cookiedomain = $_SERVER['SERVER_NAME'];
		}

		if(my_substr($cookiedomain, 0, 4) == "www.")
		{
			$cookiedomain = substr($cookiedomain, 4);
		}

		// IP addresses and hostnames are not valid
		if(my_inet_pton($cookiedomain) !== false || strpos($cookiedomain, '.') === false)
		{
			$cookiedomain = '';
		}
		else
		{
			$cookiedomain = ".{$cookiedomain}";
		}

		if($_SERVER['SERVER_PORT'] && $_SERVER['SERVER_PORT'] != 80 && !preg_match("#:[0-9]#i", $hostname))
		{
			$hostname .= ':'.$_SERVER['SERVER_PORT'];
		}
		
		$currentlocation = get_current_location('', '', true);
		$noinstall = substr($currentlocation, 0, strrpos($currentlocation, '/install/'));
		
		$cookiepath = $noinstall.'/';
		$bburl = $hostname.$noinstall;
		$websiteurl = $hostname.'/';
		$contactemail = $_SERVER['SERVER_ADMIN'];
	}

	echo $lang->sprintf($lang->config_step_table, $bbname, $bburl, $websitename, $websiteurl, $cookiedomain, $cookiepath, $contactemail);
	$output->print_footer('adminuser');
}

function create_admin_user()
{
	global $output, $mybb, $errors, $db, $lang;

	$mybb->input['action'] = "adminuser";
	// If no errors then check for errors from last step
	if(!is_array($errors))
	{
		if(empty($mybb->input['bburl']))
		{
			$errors[] = $lang->config_step_error_url;
		}
		if(empty($mybb->input['bbname']))
		{
			$errors[] = $lang->config_step_error_name;
		}
		if(is_array($errors))
		{
			configure();
		}
	}
	$output->print_header($lang->create_admin, 'admin');
	
	echo <<<EOF
		<script type="text/javascript">	
		function comparePass()
		{
			var parenttr = $('#adminpass2').closest('tr');
			var passval = $('#adminpass2').val();
			if(passval && passval != $('#adminpass').val())
			{
				if(!parenttr.next('.pass_peeker').length)
				{
					parenttr.removeClass('last').after('<tr class="pass_peeker"><td colspan="2">{$lang->admin_step_nomatch}</td></tr>');
				}
			} else {
				parenttr.addClass('last').next('.pass_peeker').remove();
			}
		}
		</script>
		
EOF;

	if(is_array($errors))
	{
		$error_list = error_list($errors);
		echo $lang->sprintf($lang->admin_step_error_config, $error_list);
		$adminuser = $mybb->get_input('adminuser');
		$adminemail = $mybb->get_input('adminemail');
	}
	else
	{
		require MYBB_ROOT.'inc/config.php';
		$db = db_connection($config);

		echo $lang->admin_step_setupsettings;
		$adminuser = $adminemail = '';

		$settings = file_get_contents(INSTALL_ROOT.'resources/settings.xml');
		$parser = new XMLParser($settings);
		$parser->collapse_dups = 0;
		$tree = $parser->get_tree();
		$groupcount = $settingcount = 0;

		// Insert all the settings
		foreach($tree['settings'][0]['settinggroup'] as $settinggroup)
		{
			$groupdata = array(
				'name' => $db->escape_string($settinggroup['attributes']['name']),
				'title' => $db->escape_string($settinggroup['attributes']['title']),
				'description' => $db->escape_string($settinggroup['attributes']['description']),
				'disporder' => (int)$settinggroup['attributes']['disporder'],
				'isdefault' => $settinggroup['attributes']['isdefault'],
			);
			$gid = $db->insert_query('settinggroups', $groupdata);
			++$groupcount;
			foreach($settinggroup['setting'] as $setting)
			{
				$settingdata = array(
					'name' => $db->escape_string($setting['attributes']['name']),
					'title' => $db->escape_string($setting['title'][0]['value']),
					'description' => $db->escape_string($setting['description'][0]['value']),
					'optionscode' => $db->escape_string($setting['optionscode'][0]['value']),
					'value' => $db->escape_string($setting['settingvalue'][0]['value']),
					'disporder' => (int)$setting['disporder'][0]['value'],
					'gid' => $gid,
					'isdefault' => 1
				);

				$db->insert_query('settings', $settingdata);
				$settingcount++;
			}
		}

		if(my_substr($mybb->get_input('bburl'), -1, 1) == '/')
		{
			$mybb->input['bburl'] = my_substr($mybb->get_input('bburl'), 0, -1);
		}

		$db->update_query("settings", array('value' => $db->escape_string($mybb->get_input('bbname'))), "name='bbname'");
		$db->update_query("settings", array('value' => $db->escape_string($mybb->get_input('bburl'))), "name='bburl'");
		$db->update_query("settings", array('value' => $db->escape_string($mybb->get_input('websitename'))), "name='homename'");
		$db->update_query("settings", array('value' => $db->escape_string($mybb->get_input('websiteurl'))), "name='homeurl'");
		$db->update_query("settings", array('value' => $db->escape_string($mybb->get_input('cookiedomain'))), "name='cookiedomain'");
		$db->update_query("settings", array('value' => $db->escape_string($mybb->get_input('cookiepath'))), "name='cookiepath'");
		$db->update_query("settings", array('value' => $db->escape_string($mybb->get_input('contactemail'))), "name='adminemail'");
		$db->update_query("settings", array('value' => 'contact.php'), "name='contactlink'");

		write_settings();

		echo $lang->sprintf($lang->admin_step_insertesettings, $settingcount, $groupcount);

		// Save the acp pin
		$pin = addslashes($mybb->get_input('pin'));

		$file = @fopen(MYBB_ROOT."inc/config.php", "a");

		@fwrite($file, "/**
 * Admin CP Secret PIN
 *  If you wish to request a PIN
 *  when someone tries to login
 *  on your Admin CP, enter it below.
 */

\$config['secret_pin'] = '{$pin}';");

		@fclose($file);

		include_once MYBB_ROOT."inc/functions_task.php";
		$tasks = file_get_contents(INSTALL_ROOT.'resources/tasks.xml');
		$parser = new XMLParser($tasks);
		$parser->collapse_dups = 0;
		$tree = $parser->get_tree();
		$taskcount = 0;

		// Insert scheduled tasks
		foreach($tree['tasks'][0]['task'] as $task)
		{
			$new_task = array(
				'title' => $db->escape_string($task['title'][0]['value']),
				'description' => $db->escape_string($task['description'][0]['value']),
				'file' => $db->escape_string($task['file'][0]['value']),
				'minute' => $db->escape_string($task['minute'][0]['value']),
				'hour' => $db->escape_string($task['hour'][0]['value']),
				'day' => $db->escape_string($task['day'][0]['value']),
				'weekday' => $db->escape_string($task['weekday'][0]['value']),
				'month' => $db->escape_string($task['month'][0]['value']),
				'enabled' => $db->escape_string($task['enabled'][0]['value']),
				'logging' => $db->escape_string($task['logging'][0]['value'])
			);

			$new_task['nextrun'] = fetch_next_run($new_task);

			$db->insert_query("tasks", $new_task);
			$taskcount++;
		}

		// For the version check task, set a random date and hour (so all MyBB installs don't query mybb.com all at the same time)
		$update_array = array(
			'hour' => rand(0, 23),
			'weekday' => rand(0, 6)
		);

		$db->update_query("tasks", $update_array, "file = 'versioncheck'");

		echo $lang->sprintf($lang->admin_step_insertedtasks, $taskcount);

		$views = file_get_contents(INSTALL_ROOT.'resources/adminviews.xml');
		$parser = new XMLParser($views);
		$parser->collapse_dups = 0;
		$tree = $parser->get_tree();
		$view_count = 0;

		// Insert admin views
		foreach($tree['adminviews'][0]['view'] as $view)
		{
			$fields = array();
			foreach($view['fields'][0]['field'] as $field)
			{
				$fields[] = $field['attributes']['name'];
			}

			$conditions = array();
			if(isset($view['conditions'][0]['condition']) && is_array($view['conditions'][0]['condition']))
			{
				foreach($view['conditions'][0]['condition'] as $condition)
				{
					if(!$condition['value']) continue;
					if($condition['attributes']['is_serialized'] == 1)
					{
						$condition['value'] = my_unserialize($condition['value']);
					}
					$conditions[$condition['attributes']['name']] = $condition['value'];
				}
			}

			$custom_profile_fields = array();
			if(isset($view['custom_profile_fields'][0]['field']) && is_array($view['custom_profile_fields'][0]['field']))
			{
				foreach($view['custom_profile_fields'][0]['field'] as $field)
				{
					$custom_profile_fields[] = $field['attributes']['name'];
				}
			}

			$new_view = array(
				"uid" => 0,
				"type" => $db->escape_string($view['attributes']['type']),
				"visibility" => (int)$view['attributes']['visibility'],
				"title" => $db->escape_string($view['title'][0]['value']),
				"fields" => $db->escape_string(my_serialize($fields)),
				"conditions" => $db->escape_string(my_serialize($conditions)),
				"custom_profile_fields" => $db->escape_string(my_serialize($custom_profile_fields)),
				"sortby" => $db->escape_string($view['sortby'][0]['value']),
				"sortorder" => $db->escape_string($view['sortorder'][0]['value']),
				"perpage" => (int)$view['perpage'][0]['value'],
				"view_type" => $db->escape_string($view['view_type'][0]['value'])
			);
			$db->insert_query("adminviews", $new_view);
			$view_count++;
		}

		echo $lang->sprintf($lang->admin_step_insertedviews, $view_count);

		echo $lang->admin_step_createadmin;
	}

	echo $lang->sprintf($lang->admin_step_admintable, $adminuser, $adminemail);
	$output->print_footer('final');
}

function install_done()
{
	global $output, $db, $mybb, $errors, $cache, $lang;

	if(empty($mybb->input['adminuser']))
	{
		$errors[] = $lang->admin_step_error_nouser;
	}
	if(empty($mybb->input['adminpass']))
	{
		$errors[] = $lang->admin_step_error_nopassword;
	}
	if($mybb->get_input('adminpass') != $mybb->get_input('adminpass2'))
	{
		$errors[] = $lang->admin_step_error_nomatch;
	}
	if(empty($mybb->input['adminemail']))
	{
		$errors[] = $lang->admin_step_error_noemail;
	}
	if(is_array($errors))
	{
		create_admin_user();
	}

	require MYBB_ROOT.'inc/config.php';
	$db = db_connection($config);

	require MYBB_ROOT.'inc/settings.php';
	$mybb->settings = &$settings;

	ob_start();
	$output->print_header($lang->finish_setup, 'finish');

	echo $lang->done_step_usergroupsinserted;

	// Insert all of our user groups from the XML file
	$usergroup_settings = file_get_contents(INSTALL_ROOT.'resources/usergroups.xml');
	$parser = new XMLParser($usergroup_settings);
	$parser->collapse_dups = 0;
	$tree = $parser->get_tree();

	$admin_gid = '';
	$group_count = 0;
	foreach($tree['usergroups'][0]['usergroup'] as $usergroup)
	{
		// usergroup[cancp][0][value]
		$new_group = array();
		foreach($usergroup as $key => $value)
		{
			if(!is_array($value))
			{
				continue;
			}

			$new_group[$key] = $db->escape_string($value[0]['value']);
		}
		$db->insert_query("usergroups", $new_group, false);

		// If this group can access the admin CP and we haven't established the admin group - set it (just in case we ever change IDs)
		if($new_group['cancp'] == 1 && !$admin_gid)
		{
			$admin_gid = $usergroup['gid'][0]['value'];
		}
		$group_count++;
	}

	// Restart usergroup sequence with correct # of groups
	if($config['database']['type'] == "pgsql")
	{
		$db->query("SELECT setval('{$config['database']['table_prefix']}usergroups_gid_seq', (SELECT max(gid) FROM {$config['database']['table_prefix']}usergroups));");
	}

	echo $lang->done . '</p>';

	echo $lang->done_step_admincreated;
	$now = TIME_NOW;
	$salt = random_str();
	$loginkey = generate_loginkey();
	$saltedpw = md5(md5($salt).md5($mybb->get_input('adminpass')));

	$newuser = array(
		'username' => $db->escape_string($mybb->get_input('adminuser')),
		'password' => $saltedpw,
		'salt' => $salt,
		'loginkey' => $loginkey,
		'email' => $db->escape_string($mybb->get_input('adminemail')),
		'usergroup' => $admin_gid, // assigned above
		'regdate' => $now,
		'lastactive' => $now,
		'lastvisit' => $now,
		'website' => '',
		'icq' => '',
		'aim' => '',
		'yahoo' => '',
		'skype' =>'',
		'google' =>'',
		'birthday' => '',
		'signature' => '',
		'allownotices' => 1,
		'hideemail' => 0,
		'subscriptionmethod' => '0',
		'receivepms' => 1,
		'pmnotice' => 1,
		'pmnotify' => 1,
		'showimages' => 1,
		'showvideos' => 1,
		'showsigs' => 1,
		'showavatars' => 1,
		'showquickreply' => 1,
		'invisible' => 0,
		'style' => '0',
		'timezone' => 0,
		'dst' => 0,
		'threadmode' => '',
		'daysprune' => 0,
		'regip' => $db->escape_binary(my_inet_pton(get_ip())),
		'language' => '',
		'showcodebuttons' => 1,
		'tpp' => 0,
		'ppp' => 0,
		'referrer' => 0,
		'buddylist' => '',
		'ignorelist' => '',
		'pmfolders' => '',
		'notepad' => '',
		'showredirect' => 1,
		'usernotes' => ''
	);
	$db->insert_query('users', $newuser);
	echo $lang->done . '</p>';

	echo $lang->done_step_adminoptions;
	$adminoptions = file_get_contents(INSTALL_ROOT.'resources/adminoptions.xml');
	$parser = new XMLParser($adminoptions);
	$parser->collapse_dups = 0;
	$tree = $parser->get_tree();
	$insertmodule = array();

	$db->delete_query("adminoptions");

	// Insert all the admin permissions
	foreach($tree['adminoptions'][0]['user'] as $users)
	{
		$uid = $users['attributes']['uid'];

		foreach($users['permissions'][0]['module'] as $module)
		{
			foreach($module['permission'] as $permission)
			{
				$insertmodule[$module['attributes']['name']][$permission['attributes']['name']] = $permission['value'];
			}
		}

		$defaultviews = array();
		foreach($users['defaultviews'][0]['view'] as $view)
		{
			$defaultviews[$view['attributes']['type']] = $view['value'];
		}

		$adminoptiondata = array(
			'uid' => (int)$uid,
			'cpstyle' => '',
			'notes' => '',
			'permissions' => $db->escape_string(my_serialize($insertmodule)),
			'defaultviews' => $db->escape_string(my_serialize($defaultviews))
		);

		$insertmodule = array();

		$db->insert_query('adminoptions', $adminoptiondata);
	}
	echo $lang->done . '</p>';

	// Automatic Login
	my_unsetcookie("sid");
	my_unsetcookie("mybbuser");
	my_setcookie('mybbuser', $uid.'_'.$loginkey, null, true);
	ob_end_flush();

	// Make fulltext columns if supported
	if($db->supports_fulltext('threads'))
	{
		$db->create_fulltext_index('threads', 'subject');
	}
	if($db->supports_fulltext_boolean('posts'))
	{
		$db->create_fulltext_index('posts', 'message');
	}

	echo $lang->done_step_cachebuilding;
	require_once MYBB_ROOT.'inc/class_datacache.php';
	$cache = new datacache;
	$cache->update_version();
	$cache->update_attachtypes();
	$cache->update_smilies();
	$cache->update_badwords();
	$cache->update_usergroups();
	$cache->update_forumpermissions();
	$cache->update_stats();
	$cache->update_statistics();
	$cache->update_forums();
	$cache->update_moderators();
	$cache->update_usertitles();
	$cache->update_reportedcontent();
	$cache->update_awaitingactivation();
	$cache->update_mycode();
	$cache->update_profilefields();
	$cache->update_posticons();
	$cache->update_spiders();
	$cache->update_bannedips();
	$cache->update_banned();
	$cache->update_bannedemails();
	$cache->update_birthdays();
	$cache->update_groupleaders();
	$cache->update_threadprefixes();
	$cache->update_forumsdisplay();
	$cache->update("plugins", array());
	$cache->update("internal_settings", array('encryption_key' => random_str(32)));
	$cache->update_default_theme();

	$version_history = array();
	$dh = opendir(INSTALL_ROOT."resources");
	while(($file = readdir($dh)) !== false)
	{
		if(preg_match("#upgrade([0-9]+).php$#i", $file, $match))
		{
			$version_history[$match[1]] = $match[1];
		}
	}
	sort($version_history, SORT_NUMERIC);
	$cache->update("version_history", $version_history);

	// Schedule an update check so it occurs an hour ago.  Gotta stay up to date!
	$update['nextrun'] = TIME_NOW - 3600;
	$db->update_query("tasks", $update, "tid='12'");

	$cache->update_update_check();
	$cache->update_tasks();

	echo $lang->done . '</p>';

	echo $lang->done_step_success;

	$written = 0;
	if(is_writable('./'))
	{
		$lock = @fopen('./lock', 'w');
		$written = @fwrite($lock, '1');
		@fclose($lock);
		if($written)
		{
			echo $lang->done_step_locked;
		}
	}
	if(!$written)
	{
		echo $lang->done_step_dirdelete;
	}
	echo $lang->done_whats_next;
	$output->print_footer('');
}

function db_connection($config)
{
	require_once MYBB_ROOT."inc/db_{$config['database']['type']}.php";
	switch($config['database']['type'])
	{
		case "sqlite":
			$db = new DB_SQLite;
			break;
		case "pgsql":
			$db = new DB_PgSQL;
			break;
		case "mysqli":
			$db = new DB_MySQLi;
			break;
		default:
			$db = new DB_MySQL;
	}

	// Connect to Database
	define('TABLE_PREFIX', $config['database']['table_prefix']);

	$db->connect($config['database']);
	$db->set_table_prefix(TABLE_PREFIX);
	$db->type = $config['database']['type'];

	return $db;
}

function error_list($array)
{
	$string = "<ul>\n";
	foreach($array as $error)
	{
		$string .= "<li>{$error}</li>\n";
	}
	$string .= "</ul>\n";
	return $string;
}

function write_settings()
{
	global $db;

	$settings = '';
	$query = $db->simple_select('settings', '*', '', array('order_by' => 'title'));
	while($setting = $db->fetch_array($query))
	{
		$setting['value'] = str_replace("\"", "\\\"", $setting['value']);
		$settings .= "\$settings['{$setting['name']}'] = \"{$setting['value']}\";\n";
	}
	if(!empty($settings))
	{
		$settings = "<?php\n/*********************************\ \n  DO NOT EDIT THIS FILE, PLEASE USE\n  THE SETTINGS EDITOR\n\*********************************/\n\n{$settings}\n";
		$file = fopen(MYBB_ROOT."inc/settings.php", "w");
		fwrite($file, $settings);
		fclose($file);
	}
}