<?php

# Slides (presentation) WikiMedia extension

# (c) by Tels http://bloodgate.com 2006. Released under the GPL v2.0.

# Takes text between <slides> </slides> tags, and splits it into individual
# lines. Each of the lines is either one of the following options, or treated
# as a topic (articl/slide) name, or a sub-topic name (starting with '*'):

# Optional options:
# name=Name of presentation	(remove "name - " prefix from articles)
# hideAll=true/false		(sets hideMenu/hideFooter/hideHeading to true/false)
# hideMenu=true/false		(default: true, hide the left menu column)
# hideFooter=true/false		(default: true, hide the footer)
# hideHeading=true/false	(default: true, hide the first-level headline)

# The "hide" options are only in effect in subsequent pages, this allows you
# to "break out" of the presentation by going to the first page.

# Article reference:
# Name1|Text in Navbar|Mouseover title
# Name2|Text in Navbar
# Name3
# *Subarticle1|Text in Navbar|Mouseover		(belongs to last normal topic "Name3")
# *Subarticle2|Text in Navbar
# *Subarticle3

# Example:
# name=My Presentation
# Start
# *Intro
# *About
# How to
# Contact

# Install: Copy this file into the extensions/ directory of your wiki, and then
# add at the bottom of LocalSettings.php, but before the "? >", the following:
# require_once("extensions/Slides.php");

# For documentation see http://bloodgate.com/wiki/

$wgExtensionFunctions[] = "wfSlidesExtension";
 
function wfSlidesExtension() {
  global $wgParser;

  # register the extension with the WikiText parser
  # the second parameter is the callback function for processing the text between the tags

  $wgParser->setHook( "slides", "renderNavigation" );
}

# for Special::Version:

$wgExtensionCredits['parserhook'][] = array(
	'name' => 'slides (presentation) extension',
	'author' => 'Tels',
	'url' => 'http://wwww.bloodgate.com/wiki/',
	'version' => 'v0.01',
);
 
# The callback function for outputting the HTML code
function renderNavigation( $sInput, $sParams )
  {
  global $wgParser;
  global $wgArticlePath;
  global $wgScript;

  $aParams = array();
  $sCurrent;				# the page we are currently on
  $aLinks = array();			# all the entries in the navbar
  $sPrefix = '';			# the presentations name
  $bArticles = false;			# stopped parsing options?

  $bHideHeading = true;			# hide first-level headline?
  $bHideMenu = true;			# hide left menu-column?
  $bHideFooter = true;			# hide footer?

  ###########################################################################
  # Parse the parameters, stop at first "Invalid" looking one

  $aParams = explode("\n", $sInput);
  foreach ($aParams as $sCur)
    {
    $sCur = trim($sCur);

    # skip empty lines
    if ($sCur == '') { continue; }

    $aCur = explode("=", $sCur);

    if (count($aCur) == 2)
      {
      $sType = trim($aCur[0]);
      $sVal = trim($aCur[1]);

      switch ($sType)
	{
	case 'name':
	  $sPrefix = $sVal;
	  break;

	case 'hideMenu':
	  # true is the default
	  if ($sVal == 'false') { $bHideMenu = false; }
	  break;

	case 'hideFooter':
	  # true is the default
	  if ($sVal == 'false') { $bHideFooter = false; }
	  break;

	case 'hideHeading':
	  # true is the default
	  if ($sVal == 'false') { $bHideHeading = false; }
	  break;

	case 'hideAll':
	  # true is the default
	  if ($sVal == 'false')
	    {
	    $bHideMenu = false;
	    $bHideFooter = false;
	    $bHideHeading = false;
	    }
	  break;

	default:
	  # doesn't look valid to me
	  $bArticles = true;
	}
      }
    # Not exactly one "=", so stop parsing options and begin with articles
    else { $bArticles = true; }

    # store the Article reference as-is (e.g. '*Name|Foo|Click here')
    if ($bArticles)
      {
      # normalize underscores
      $aLinks[] = preg_replace('/ /',  '_', $sCur);
      }
    } 

  # Format the navbar as table
  $output = 
    '<table style="border: none; background: inherit;"><tr><td style="vertical-align: top">' 
    . $sPrefix . ':&nbsp;</td><td>';

  # Get the current page from the Parser member mTitle, to make it different
  $sCurrent = $wgParser->mTitle->getText();

  # 'My presentation - Start' => 'Start'
  if ($sPrefix != '')
    {
    $sPrefix .= ' - ';
    $sCurrent = preg_replace('/^' . preg_quote($sPrefix) . '/', '', $sCurrent);
    }

  # turn spaces into underscores
  $sPrefix = preg_replace('/ /', '_', $sPrefix);
  $sCurrent = preg_replace('/ /', '_', $sCurrent);

  ###########################################################################
  # finally generate the HTML output

  # the lower nav bar with the subtopics
  $sSubTopics = '';

  # the last seen topic was the current one
  $bCurrent = false;
  # we see currently a subtopic
  $bSubtopic = false;

  # build the path for article links
  $sPath = $wgArticlePath;
  if ($sPath == '') { $sPath = $wgScript . '/'; }
  # "index.php?title=$1" => "index.php?title="
  $sPath = preg_replace('/\$1/', '', $sPath);

  # we need two passes, in the first one we find the curren topic and subtopic:

  $sCurTopic = '';
  $sCurSubTopic = '';
  $sLastTopic = '';
  # find the current topic
  $i = 0;
  while ($i < count($aLinks))
    {
    # convert all spaces to underscores
    $aTitle = _explode($aLinks[$i]);

    if (preg_match('/^\*/', $aTitle[0]))
      {
      # subtopic equals current article?
      if (strcmp('*' . $sCurrent, $aTitle[0]) == 0)
	{
        # remove the leading '*'
	$sCurTopic = $sLastTopic;
	$sCurSubTopic = preg_replace('/^\*/', '', $aTitle[0]);
	}
      }
    else
      {
      # topic equals current article?
      if (strcmp($sCurrent, $aTitle[0]) == 0)
	{
	$sCurTopic = $aTitle[0];
	$sCurSubTopic = '';
	}
      $sLastTopic = $aTitle[0];
      }
    $i++;
    }

  # second pass, build the output
  $iFirstSub = 0;
  $i = 0;
  while ($i < count($aLinks))
    {
    $sLink = $aLinks[$i];

    $bSubtopic = false;
    if (preg_match('/^\*/', $sLink))
      {
      $bSubtopic = true;

      # if we aren't in the current topic, supress the subtopic
      if (!$bCurrent) { $i++; continue; }

      # for each subtopic, count up
      $iFirstSub++;

      # remove the leading '*'
      $sLink = preg_replace('/^\*/', '', $sLink);
      }
    else
      {
      $iFirstSub = 0;			# reset
      }

    # Article name|Navbar name|Mouseover
    $aTitle = _explode($sLink);

    # for topics, compare against $sCurTopic
    $sCmp = $sCurTopic;
    if ($bSubtopic) 
      {
      $sCmp = $sCurSubTopic;
      }

    $sBold = '';
    $sBold1 = '';
    $bBuildLink = true;
    if (strcmp($aTitle[0], $sCmp) == 0) 
      {
      $sBold = '<b>';
      $sBold1 = '</b>';
      if ($bSubtopic || $sCurSubTopic == '')
	{
	# the current page is a topic header
	$bBuildLink = false;
	}
      if (!$bSubtopic)
	{
	$sSubTopics = '<span style="font-size: 90%">';
	}
      $bCurrent = true;
      }
    else
      {
      if (!$bSubtopic) { $bCurrent = false; }
      }

    if (!$bBuildLink)
      {	
      $sOut = '<b>' . $aTitle[1] . '</b>';
      }
    else
      {
      $sLink = _escape($aTitle[0]);
      $sText = _escape($aTitle[1]);
      $sTitle = _escape($aTitle[2]);

      # escape quotes in these two
      $sLink = preg_replace('/"/', '&quot;', $sLink);
      $sTitle = preg_replace('/"/', '&quot;', $sTitle);
      # escape spaces in the link
      $sLink = preg_replace('/ /',  '_', $sLink);

      if ($sTitle != '')
	{
        $sTitle = ' title="' . $sTitle . '"';
	}

      # build the link
      $sLink = $sPrefix . $sLink;
      $sOut = $sBold . "<a href=\"$sPath$sLink\"$sTitle>$sText</a>" . $sBold1;
      }

    if ($i != 0 && $iFirstSub != 1) { $sOut = ' - ' . $sOut; }

    # and add it to the navbar
    if ($bSubtopic)
      {
      $sSubTopics .= $sOut;
      }
    else
      {
      $output .= $sOut;
      }

    # next topic
    $i++;
    }

  if ($sSubTopics != '')
    {
    $sSubTopics = '<br />' . $sSubTopics . '</span>';
    }

  ###########################################################################
  # generate style to suppress the different elements

  $aStyles = array();
  $sMoreStyles = '';
 
  if ($bHideMenu)
    {
    $aStyles[] = '#p-logo,#p-navigation,#p-search,#p-tb';
    $sMoreStyles = '#column-content{ margin: 0 0 0.6em -1em}#content{margin: 2.8em 0 0 1em;}#p-actions{margin-left: 1em;}';
    }
  if ($bHideHeading)	{ $aStyles[] = '.firstHeading'; }
  if ($bHideFooter)	{ $aStyles[] = '#footer'; }

  $sStyles = '';

  $aTitle = _explode($aLinks[0]);
  # on the first page, do not hide anything
  if ((strcmp($sCurrent, $aTitle[0]) != 0) &&
  # likewise if we do not have anything to suppress
      (count($aStyles) > 0) && 
  # or don't have a current topic (like when editing the template)
      ($sCurTopic != '') )
    {
    $sStyles = '<style type="text/css">' . join(',',$aStyles) . "{display:none}$sMoreStyles</style>";
    }

  return $sStyles . $output . $sSubTopics . '</td></tr></table';
  }

function _explode ($sLink)
  {
  # split into the three components, with defaults:
  # Article name|Navbar name|Mouseover
  $aTitle = explode('|',$sLink);

  if (count($aTitle) == 0)
    {
    $aTitle[0] = $sLink;		# 'Article'
    } 
  if (count($aTitle) == 1)
    {
    $aTitle[1] = $aTitle[0];		# navbar name eq article
    $aTitle[2] = '';			# no mouseover
    }
  if (count($aTitle) == 2)
    {
    $aTitle[2] = '';			# no mouseover
    }
  return $aTitle;
  }

function _escape ($sName)
  {
  # escape important HTML special chars
  $sName = preg_replace('/</',  '&lt;',  $sName);
  $sName = preg_replace('/>/',  '&gt;',  $sName);
  $sName = preg_replace('/&/',  '&amp;', $sName);
  $sName = preg_replace('/=/',  '%3D',   $sName);
  $sName = preg_replace('/\?/', '%3F',   $sName);

  return $sName;
  }
?>
