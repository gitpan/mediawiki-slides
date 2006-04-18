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
# fontsize=120%			(default: 100%, the fontsize for the body, in %)
# showButtons=true		(default: true, show |< << >> >| buttons on the navbar)

# The "hide" options are only in effect in subsequent pages, this allows you
# to "break out" of the presentation by going to the very first page.

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

# For full documentation please see: http://bloodgate.com/wiki/

$wgExtensionFunctions[] = "wfSlidesExtension";
 
function wfSlidesExtension() {
  global $wgParser;

  # register the extension with the WikiText parser
  # the second parameter is the callback function for processing the text between the tags

  $wgParser->setHook( "slides", "renderNavigation" );
}

# for Makefile.PL
/*
$VERSION = 0.03; */

# for Special::Version:
$wgExtensionCredits['parserhook'][] = array(
	'name' => 'slides (presentation) extension',
	'author' => 'Tels',
	'url' => 'http://bloodgate.com/wiki/',
	'version' => 'v0.03',
);
 
# The callback function for outputting the HTML code
function renderNavigation( $sInput, $sParams, $parser = null )
  {
  global $wgArticlePath;
  global $wgScript;

  # if we didn't get the parser passed in, we are running under an older mediawiki
  if (!$parser) $parser =& $_GLOBALS['wgParser'];

  $aParams = array();
  $sCurrent;				# the page we are currently on
  $aLinks = array();			# all the entries in the navbar
  $sPrefix = '';			# the presentations name
  $bArticles = false;			# stopped parsing options?
  $sFontSize = '';			# set a new font size for the body?

  $bHideHeading = true;			# hide first-level headline?
  $bHideMenu = true;			# hide left menu-column?
  $bHideFooter = true;			# hide footer?
  $bShowButtons = true;			# show nav buttons (|< << >> >|)

  # Find out whether we are currently rendering for a preview, or the final
  # XXX TODO: unreliable and unused yet
  global $action;
  $bPreview = false; if ($action == 'submit') { $bPreview = true; }

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

	case 'fontsize':
	  if (preg_match('/^\d+%$/',$sVal)) { $sFontSize = $sVal; }
	  break;

	case 'showButtons':
	  # true is the default
	  if ($sVal == 'false') { $bShowButtons = false; }
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

  $sSmall = '85%';
  # Format the navbar as table (would love to do that as CSS, tho)
  $output = 
    "<table style=\"font-size:$sSmall;" . 'border:none;background:transparent"><tr><td style="vertical-align:top">' 
    . $sPrefix . ':&nbsp;</td><td>';

  # Get the current page from the Parser member mTitle, to make it different
  $sCurrent = $parser->mTitle->getText();

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
  # "/wiki/index.php?title="  =>  "/wiki/index.php?title=My_Presentation"
  $sPath .= $sPrefix;

  # we need two passes, in the first one we find the curren topic and subtopic:

  $sCurTopic = '';
  $sCurSubTopic = '';
  $sLastTopic = '';
  $iCurr = 1;				# index of current topic	
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
        $iCurr = $i;
	}
      }
    else
      {
      # topic equals current article?
      if (strcmp($sCurrent, $aTitle[0]) == 0)
	{
	$sCurTopic = $aTitle[0];
	$sCurSubTopic = '';
        $iCurr = $i;
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
      $sOut = '<b>' . preg_replace('/_/', ' ', $aTitle[1]) . '</b>';
      }
    else
      {
      $sOut = $sBold . _build_link($sPath, $sLink) . $sBold1;
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

  $aTitle = _explode($aLinks[0]);
  $bOnFirstPage = false;
  if (strcmp($sCurrent,$aTitle[0]) == 0) { $bOnFirstPage = true; }
  $aTitle = _explode($aLinks[count($aLinks)-1]);
  $bOnLastPage = false;
  if (strcmp($sCurrent,$aTitle[0]) == 0) { $bOnLastPage = true; }

  ###########################################################################
  # generate next/prev links

  $sButtons = '';

  # only include buttons if not editing the template
  if (($sCurTopic != '') && $bShowButtons)
    {
    if (!$bOnFirstPage)
      {
      $sButtons = 
        _build_link($sPath, $aLinks[0], '|&lt;', 'First page') . '&nbsp;' 
      . _build_link($sPath, $aLinks[$iCurr-1], '&lt;&lt;', 'Previous page'); 
      }
    if (!$bOnLastPage)
      {
      #											  accesskey=' '
      $sButtons .= '&nbsp;&nbsp;' . _build_link($sPath, $aLinks[$iCurr+1], '&gt;&gt;', 'Next page', ' ')
      . '&nbsp;' . _build_link($sPath, $aLinks[count($aLinks)-1], '&gt;|', 'Last page');
      }
    if ($sButtons != '')
      {
      $sButtons = "<span style=\"float:right;\">$sButtons&nbsp;</span>";
      }
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
  if ($bHideFooter)	{ $aStyles[] = '#footer'; }

  $sStyles = '';

  # on the first page, or if no topic (like when editing the template), do
  # not hide anything
  if ( $bOnFirstPage || ($sCurTopic == '') )
    {
    $aStyles = array();
    $sMoreStyles = '';
    }

  # hide the heading, even on the first page
  if ($bHideHeading)	{ $aStyles[] = '.firstHeading'; }

  # maybe we need to set the fontsize
  if (($sFontSize != '') && ($sCurTopic != '')) 
    {
    $sMoreStyles .= "#bodyContent{font-size: $sFontSize}";
    }

  # do we need to set some styles?
  if ( (count($aStyles) > 0) || ($sMoreStyles != '') )
#  # and we are not in preview
#      ($bPrewview) )
    {
    if (count($aStyles) > 0) { $sStyles = join(',',$aStyles) . "{display:none}"; }
    $sStyles = '<style type="text/css">' . "$sStyles$sMoreStyles</style>";
    }

  return $sStyles . $sButtons . $output . $sSubTopics . "</td></tr></table>";
  }

function _build_link ($sPath, $sTheLink, $sOptionalText = '', $sOptionalTitle = '', $sAccessKey = '')
  {
  # build a link from the prefix and one entry in the link-array

  $aTitle = _explode($sTheLink);
  $sLink = _escape($aTitle[0]);
  $sText = _escape($aTitle[1]);
  $sTitle = _escape($aTitle[2]);

  if ($sOptionalText != '') { $sText = $sOptionalText; }
  if ($sOptionalTitle != '') { $sTitle = $sOptionalTitle; }

  # escape quotes in these two
  $sLink = preg_replace('/"/', '&quot;', $sLink);
  $sTitle = preg_replace('/"/', '&quot;', $sTitle);
  # escape spaces in the link
  $sLink = preg_replace('/ /',  '_', $sLink);
  # inside the text, we want spaces, not underscored
  $sText = preg_replace('/_/', ' ', $sText);
  # remove the leading '*' from article names
  $sLink = preg_replace('/^\*/', '', $sLink);

  if ($sTitle != '')
    {
    $sTitle = ' title="' . $sTitle . '"';
    }

  if ($sAccessKey != '')
    {
    $sAccessKey = ' accesskey="' . $sAccessKey . '"';
    }

  # build the link
  return "<a href=\"$sPath$sLink\"$sTitle$sAccessKey>$sText</a>";
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
