<?php

define('SFBROWSER', 1);

$wgExtensionCredits['specialpage'][] = array(
   'name' => 'Semantic Facet Browser',
   'author' => '[http://mediawiki.org/wiki/User:Michael_A._White Michael White]', 
   'version' => '0.1', 
   'descriptionmsg' => 'sfb_description'
);

$sfbgIP = __dir__ . '/';
$sfbgScriptPath = $wgScriptPath . '/extensions/SemanticFacetBrowser';

$wgExtensionFunctions[] = 'wfSFBSetup';


function wfSFBSetup() {
	global $wgAutoloadClasses, $wgSpecialPages, $sfbgIP;
	
	$wgAutoloadClasses['SFBFacetRetriever'] = $sfbgIP . 'SFB_FacetRetriever.php';
	$wgAutoloadClasses['SFBSpecialPage'] = $sfbgIP . 'SFB_Special.php';
	
	
	$wgSpecialPages['BrowseFacets'] = 'SFBSpecialPage';
}
