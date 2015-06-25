<?php if (!defined('PmWiki')) exit();

/*
 * cluster.php for PmWiki 2
 *
 * DESCRIPTION:  The Cluster recipe adds group-clustering
 * functionality to PmWiki, in a pseudo-hierarchical way.
 * For info, see http://www.pmwiki.org/wiki/Cookbook/Cluster
 * Based on Hg by Dan Vis  <editor at fast dot st>, Copyright 2007.
 * 	see http://www.pmwiki.org/wiki/Cookbook/Hg
 * Modified by: Kathryn Andersen
 * Contributions by Hans Bracker and Feral.
 * File modified by Petko Yotov to work with PHP 5.5
 */
$RecipeInfo['Cluster']['Version'] = '2014-05-15';

/* ================================================================
 * Customisation Variables for use in local config file
 */
SDV($ClusterSeparator, '-');
SDV($ClusterMaxLevels, 7);
SDV($ClusterBreadCrumbSeparator, " $ClusterSeparator ");
SDV($ClusterEnableBreadCrumbName, 1);
SDV($ClusterEnableTitles, false);
SDV($ClusterEnableSpaces, false);
SDV($ClusterEnableNameClustering, false);

/* ================================================================
 * script Setup
 */
$pagename = ResolvePageName($pagename);
$EnablePGCust = 0;
$m = preg_split('/[.\\/]/', $pagename);
$group = $m[0];
$name = $m[1];

/* ================================================================
 * Page Variables
 */
/*
 * Please note:
 * Page Variables CANNOT be set by using a function which processes
 * the current page name, however efficient that may be.
 * They MUST be set by giving them a function to call, because
 * the *current* pagename is not the only pagename they might need
 * to process.  Inside pagelists, they are given the pagename of
 * the page in the list of pages that the pagelist is processing.
 * Therefore, if you use the current $pagename, then you break all use
 * inside pagelists.
 */
$FmtPV['$g0'] = 'ClusterCountLevels($group)';
$FmtPV['$g01'] = 'ClusterCountLevels($group, +1)';
$FmtPV['$n0'] = 'ClusterCountLevels($name)';
for ($i=0; $i < $ClusterMaxLevels; $i++) {
	$groupvar = "g" . ($i + 1);
	$namevar = "n" . ($i + 1);
	$FmtPV['$' . $groupvar] = 'ClusterSplitName($group, ' . $i . ')';
	$FmtPV['$' . $namevar] = 'ClusterSplitName($name, ' . $i . ')';
}

# separators
$FmtPV['$ClusterSep'] = '"'.$ClusterSeparator.'"';
$FmtPV['$ClusterBreadCrumbSep'] = '"'.$ClusterBreadCrumbSeparator.'"';
$FmtPV['$ClusterMaxLevels'] = '"'.$ClusterMaxLevels.'"';

# breadcrumb trail page variables:
$FmtPV['$BreadCrumbDepth'] = 'ClusterCountLevels($group)';
$FmtPV['$BreadCrumb'] = 'ClusterSlice($pn, "separator=\''.$ClusterBreadCrumbSeparator.'\' return=links")';
$FmtPV['$BreadCrumbTitle'] = 'ClusterSlice($pn, "separator=\''.$ClusterBreadCrumbSeparator.'\' return=links title=true space=true ")';
$FmtPV['$BreadCrumbNoTitle'] = 'ClusterSlice($pn, "separator=\''.$ClusterBreadCrumbSeparator.'\' return=links title=false space=false ")';

# Grouptitle PVs
$FmtPV['$GroupTitle']		= 'ClusterSlice($pn, "start=-1 name=false return=names title=true space=false")';
$FmtPV['$GroupTitlespaced']		= 'ClusterSlice($pn, "start=-1 name=false return=names title=true space=true")';

# Subpage PVs
$FmtPV['$ClusterSideBar'] = 'ClusterPageName($group, "SideBar", $name)';
$FmtPV['$ClusterRightBar'] = 'ClusterPageName($group, "RightBar", $name)';

/* ================================================================
 * Markup and directives
 */

# Markup for link shortcuts
if(function_exists('Markup_e')) { # added by Petko Yotov
  Markup_e('[[cluster','<links','/\\[\\[(-|[\\*\\^]+)(.*?)\\]\\]/',
    "ClusterLinks(\$pagename, \$m[1], \$m[2])");

  Markup('clusterslice', 'directives', '/\\(:clusterslice\\s*(.*?):\\)/i',
    "ClusterSlice(\$pagename, \$m[1])"
    );
}
else {
  Markup('[[cluster','<links','/\\[\\[(-|[\\*\\^]+)(.*?)\\]\\]/e',
    "ClusterLinks(\$pagename, '$1', PSS('$2'))");

  Markup('clusterslice', 'directives',
    '/\\(:clusterslice\\s*(.*?):\\)/ei',
    "ClusterSlice(\$pagename, PSS('$1'))"
    );
}

/* ================================================================
 * Search Patterns
 * If using name-based clustering, hide the name-based special pages
 */
if ($ClusterEnableNameClustering) {
	$SearchPatterns['normal']['clustername1'] = 
  '!\\' . $ClusterSeparator . 'Group(Print)?(Header|Footer|Attributes)$!';
	$SearchPatterns['normal']['clustername2'] = 
  '!\\' . $ClusterSeparator . '(Side|Right|Menu)Bar$!';
}

/* ================================================================
 * Set up clustered configurations
 */

$found_config = false;
$found_grouphead = false;
$found_groupfoot = false;
$found_attr = false;
if (file_exists("$LocalDir/$group.$name.php")) {
	include_once("$LocalDir/$group.$name.php");
	$found_config = true;
}
$PageCSSListFmt["pub/css/$group.$name.css"] = "$PubDirUrl/css/$group.$name.css"; 

if ($ClusterEnableNameClustering) {
	# Look for name-based configs, CSS, header, footer, attr
	$names = explode($ClusterSeparator, $name);
	while (count($names) != 0) {
		$cluster_name = implode ($ClusterSeparator, $names);
		if (!$found_config && file_exists("$LocalDir/$group.$cluster_name.php")) {
			include_once("$LocalDir/$group.$cluster_name.php");
			$found_config = true;
		}
		if (!$found_grouphead
		    && PageExists("$group.${cluster_name}${ClusterSeparator}GroupHeader")) {
			$GroupHeaderFmt = "(:include $group.${cluster_name}${ClusterSeparator}GroupHeader self=0:)(:nl:)";
			$found_grouphead = true;
		}
		if (!$found_groupfoot
		    && PageExists("$group.${cluster_name}${ClusterSeparator}GroupFooter")) {
			$GroupFooterFmt = "(:nl:)(:include $group.${cluster_name}${ClusterSeparator}GroupFooter self=0:)";
			$found_groupfoot = true;
		}
		if (!$found_attr
		    && PageExists("$group.${cluster_name}${ClusterSeparator}GroupAttributes")) {
			$GroupAttributesFmt = "$group.${cluster_name}${ClusterSeparator}GroupAttributes";
			$found_groupfoot = true;
		}
		$PageCSSListFmt["pub/css/$group.$cluster_name.css"] = "$PubDirUrl/css/$group.$cluster_name.css"; 
		array_pop($names);
	}
}

# Look for group-based configs, CSS, header, footer, attr
$groups = explode($ClusterSeparator, $group);
while (count($groups) != 0) {
	$cluster_group = implode ($ClusterSeparator, $groups);
	if (!$found_config && file_exists("$LocalDir/$cluster_group.php")) {
		include_once("$LocalDir/$cluster_group.php");
		$found_config = true;
	}
	if (!$found_grouphead && PageExists("$cluster_group.GroupHeader")) {
		$GroupHeaderFmt = "(:include $cluster_group.GroupHeader self=0:)(:nl:)";
		$found_grouphead = true;
	}
	if (!$found_groupfoot && PageExists("$cluster_group.GroupFooter")) {
		$GroupFooterFmt = "(:nl:)(:include $cluster_group.GroupFooter self=0:)";
		$found_groupfoot = true;
	}
	if (!$found_attr && PageExists("$cluster_group.GroupAttributes")) {
		$GroupAttributesFmt = "$cluster_group.GroupAttributes";
		$found_groupfoot = true;
	}
	$PageCSSListFmt["pub/css/$cluster_group.css"] = "$PubDirUrl/css/$cluster_group.css"; 
	array_pop($groups);
}
// set the defaults for those that haven't been found
if (!$found_config && file_exists("$LocalDir/$default.php")) {
	include_once("$LocalDir/$default.php");
}
if (!$found_grouphead && PageExists("$SiteGroup.GroupHeader")) {
	$GroupHeaderFmt = "(:include $SiteGroup.GroupHeader self=0:)(:nl:)";
}
if (!$found_groupfoot && PageExists("$SiteGroup.GroupFooter")) {
	$GroupFooterFmt = "(:nl:)(:include $SiteGroup.GroupFooter self=0:)";
}
$PageCSSListFmt["pub/css/local.css"] = "$PubDirUrl/css/local.css";
$PageCSSListFmt = array_reverse($PageCSSListFmt, true);


/* ================================================================
 * Functions
 */
function ClusterCountLevels($name, $offset=0) {
	global $ClusterSeparator;
	$parts = explode($ClusterSeparator, $name);
	settype($offset, 'int');
	return (count($parts)+$offset);
}

function ClusterSplitName($name, $ind) {
	global $ClusterSeparator;
	$parts = explode($ClusterSeparator, $name);
	if ($ind < 0 || $ind >= count($parts)) return '';
	else return $parts[$ind];
}

# return the pagename of the closest ancestor
function ClusterPageName($group, $sought_name, $curr_name = '') {
	global $ClusterSeparator, $ClusterEnableNameClustering;
	global $SiteGroup;
	$poss_name = '';
	if ($ClusterEnableNameClustering && $curr_name) {
		$names = explode($ClusterSeparator, $curr_name);
		while (count($names) != 0) {
			$cluster_name = implode ($ClusterSeparator, $names);
			$poss_name = "$group.${cluster_name}${ClusterSeparator}${sought_name}";
			if (PageExists($poss_name)) {
				// short-circuit return!
				return $poss_name;
			}
			array_pop($names);
		}
	}

	$groups = explode($ClusterSeparator, $group);
	while (count($groups) != 0) {
		$cluster_group = implode ($ClusterSeparator, $groups);
		$poss_name = "$cluster_group.$sought_name";
		if (PageExists($poss_name)) {
			// short-circuit return!
			return $poss_name;
		}
		array_pop($groups);
	}
	return "$SiteGroup.$sought_name";
}

# process link shortcut markup
function ClusterLinks($pagename, $prefix, $inlink) {
	global $ClusterSeparator, $DefaultName,
	       $ClusterEnableNameClustering;
	$m = preg_split('/[.\\/]/', $pagename);
	$group = $m[0];
	$name = $m[1];

	if ($prefix == "-") {
		# name-clustering only applies to non-homepage pages
		if ($ClusterEnableNameClustering
		    && !($name == $DefaultName || $name == $group))
		{
			$selfname = $pagename;
		}
		else {
			$selfname = $group;
		}
		if ($inlink) {
			if ($inlink[0] == '|') {
				return "[[$selfname$inlink]]";
			} else {
				return "[[$selfname$ClusterSeparator$inlink]]";
			}
		}
		else {
			return "[[$selfname]]";
		}
	}

	$levels = strlen($prefix);
	# type of prefix (not allowed to be mixed)
	# ^ is up, * is absolute
	$up = false;
	if (substr($prefix, 0, 1) == "^")
	{
		$up = true;
	}

	// name-clustering only applies to non-homepage pages
	$parts = ClusterHelper_MapName($group,
				       ($ClusterEnableNameClustering
					&& !($name == $DefaultName
					     || $name == $group)
					? $name : ''));

	if ($up)
	{
		// count backwards from the end
		$item = $parts[(count($parts) - 1) - $levels];
	}
	else // absolute
	{
		// level = 1, the index = 0
		$item = $parts[$levels - 1];
	}
	$link = $item['path'];
	return "[[$link$inlink]]";
}

function ClusterSlice($pagename, $opt) {
	global $ClusterSeparator, $ClusterBreadCrumbSeparator, 
	       $ClusterEnableNameClustering,
	       $ClusterEnableBreadCrumbName,
	       $ClusterEnableTitles,
	       $ClusterEnableSpaces,
	       $AsSpacedFunction,
	       $DefaultName;
	//
	// (making this similar to substr)
	// start:
	// If start is non-negative, the returned string will start at the
	// start'th position in cluster, counting from zero. 
	// If start is negative, the returned string will start at the start'th
	// segment from the end of cluster
	// length:
	// If length is given and is positive, the slice returned will contain
	// at most length segments beginning from start (depending on the
	// length of cluster). If cluster is less than or equal to start
	// segments long, empty string will be returned.
	// If length is given and is negative, then that many segments will
	// be omitted from the end of the slice (after the start position has
	// been calculated when a start is negative). If start denotes a
	// position beyond this truncation, an empty string will be returned.
	//
	$defaults = array(
			  'start' => 0,
			  'length' => 0,
			  'separator' => $ClusterBreadCrumbSeparator,
			  'title' => $ClusterEnableTitles,
			  'space' => $ClusterEnableSpaces,
			  'name' => ($ClusterEnableNameClustering
				     ? 'clustered'
				     : ($ClusterEnableBreadCrumbName
					? 'simple' : false)
				    ),
			  'noindex' => $ClusterEnableBreadCrumbName,
			  // return: 'links', 'groups' or 'names'
			  'return' => 'links',
			  'pagename' => $pagename,
			 );
	$opt = array_merge($defaults, ParseArgs($opt));
	$title = $opt['title'];
	if ($title == 'false') { $title = false; }
	settype($title, "boolean");
	$space = $opt['space'];
	if ($space == 'false') { $space = false; }
	settype($space, "boolean");
	if ($opt['noindex'] == 'false') { $opt['noindex'] = false; }
	settype($opt['noindex'], "boolean");

	if ($opt['name'] == 'false') {
		$opt['name'] = false;
	}

	$pn = $opt['pagename'];

	$name = PageVar($pn,'$Name');
	$group = PageVar($pn,'$Group');

	$parts = ClusterHelper_MapName($group, ($opt['name'] == 'clustered' ? $name : ''));
	if ($opt['name'] && $opt['name'] != 'clustered')
	{
		// simple name; add name to parts
		$parts[] = array('name' => $name,
			'path' => $pn,
			'link' => $pn);
	}

	//
	// Remove segments from start and end
	//

	# If start is positive, remove that many segments
	# from the start.
	# If start is negative, start at the start'th segment
	# from the end of the string, that is,
	# start at count + start

	$start = $opt['start'];
	if ($start >= count($parts) )
	{
	    return '';
	}
	if ($start >= 0)
	{
	    $trim = $start;
	}
	else if ($start < 0)
	{
	    $trim = count($parts) + $start;
	}

	if ($trim > 0)
	{
	    $index = 0;
	    while ($index < $trim)
	    {
		array_shift($parts);
		$index++;
	    }
	}

	# If length is positive, show that number of segments
	$length = $opt['length'];
	if ($length > 0 && $length < count($parts))
	{
	    while (count($parts) > $length)
	    {
		array_pop($parts);
	    }
	}
	# If length is negative, remove that many segments from the end
	if ($length < 0)
	{
	    $remove = abs($length);
	    while (count($parts) > 0 && $remove > 0)
	    {
		array_pop($parts);
		$remove--;
	    }
	}
	# remove the last segment if it is an index page and we don't want one
	if (count($parts) && $opt['noindex'])
	{
	    $lastitem = $parts[count($parts) - 1];
	    if ($lastitem['path'] == "$group.$group"
		  || $lastitem['path'] == "$group.$DefaultName")
	    {
		array_pop($parts);
	    }
	}

	$index = 0;
	$out = '';
	foreach($parts as $item)
	{
		// if this isn't the first item, add the separator
		if ($index != 0)
		{
			$out.= $opt['separator'];
		}

		$label = '';
		if ($title)
		{

			$label = ClusterHelper_FetchGroupTitle($item['path']);
			if (!$label)
			{
				$label = $item['name'];
			}
			if ($space)
			{
				$label = $AsSpacedFunction($label);
			}

		}
		else if ($space)
		{
			$label = $AsSpacedFunction($item['name']);
		}
		else
		{
			$label= $item['name'];
		}

		if (preg_match('/links/', $opt['return']))
		{
		    if ($index + 1 == count($parts)
		    	&& preg_match('/labellast/', $opt['return']))
		    {
			$out.= "'''" . $label . "'''";
		    }
		    else
		    {
			$out.= "[[{$item['link']}|$label]]";
		    }
		}
		elseif ($opt['return'] == 'groups' )
		{
			$out.= $item['path'];
		}
		elseif ($opt['return'] == 'names' )
		{
			$out.= $label;
		}
		else
		{
			return ("Error:ClusterSlice:Unknown return style: ".$opt['return']);
		}

		$index++;
	}

	return $out;
}

// ------------------------------------------------------------------------

/*
 * given a group and a name, returns an array of maps between 
 * the "name", "path" and "link" of the groups and pages in this cluster.
 * For example:
 * 	Foo => name=Foo, path=Foo, link=Foo/
 *	Foo-Bar => name=Foo, path=Foo, link=Foo/;
 * 		name=Bar, path=Foo-Bar, link=Foo-Bar/
 *	Foo-Bar.Baz-Boo => name=Foo, path=Foo, link=Foo/;
 *		name=Bar, path=Foo-Bar, link=Foo-Bar/;
 *		name=Baz, path=Foo-Bar.Baz, link=Foo-Bar.Baz;
 *		name=Boo, path=Foo-Bar.Baz-Boo, link=Foo-Bar.Baz-Boo
 */
function ClusterHelper_MapName($group, $name='')
{ 
	global $ClusterSeparator;

	$groups_orig = explode($ClusterSeparator, $group);
	$map = array();
	$index = 0;
	foreach($groups_orig as $item)
	{
		$path = '';
		for	($num = 0; $num <= $index; $num++)
		{
			if	(	$num != 0)
			{
				$path.= $ClusterSeparator;
			}

			$path.= $groups_orig[$num];
		}

		$map[] = array('name' => $item, 'path' => $path, 'link' => "$path/");

		$index++;
	}
	if ($name)
	{
	    $names_orig = explode($ClusterSeparator, $name);
	    $index = 0;
	    foreach($names_orig as $item)
	    {
		$path = '';
		for	($num = 0; $num <= $index; $num++)
		{
		    if	(	$num != 0)
		    {
			$path.= $ClusterSeparator;
		    }

		    $path.= $names_orig[$num];
		}

		$map[] = array('name' => $item,
			'path' =>$group . '.' . $path,
			'link' =>$group . '.' . $path);

		$index++;
	    }
	}

	return $map;
}

# given a group, find its title if it has one
// TODO name-based
function ClusterHelper_FetchGroupTitle($group, $fmt = NULL)
{ 
	global $DefaultName, $GroupTitlePathFmt;
	if (is_null($fmt))
	{
		SDV($GroupTitlePathFmt,
		    array(
			  '$Group.GroupAttributes',
			  '$Group.GroupHeader', 
			  '$Group.GroupFooter',
			  '$Group.$Group',
			  "\$Group.$DefaultName"));
		$fmt = $GroupTitlePathFmt;
	}
	$group_title=null;
	foreach((array)$fmt as $try)
	{
		$pn = FmtPageName($try, "$group.$group");
		$page = ReadPage($pn, READPAGE_CURRENT);
		if ($page['title'])
		{
		    $group_title = $page['title'];
		    break;
		}
	}
	return $group_title;
} 

