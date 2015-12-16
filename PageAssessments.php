<?php

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'PageAssessments' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['PageAssessments'] = __DIR__ . '/i18n';
	$wgExtensionMessagesFiles['PageAssessmentsMagic'] = __DIR__ . '/PageAssessments.i18n.magic.php';
	wfWarn(
		'Deprecated PHP entry point used for PageAssessments extension. Please use wfLoadExtension ' .
		'instead, see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	);
	return true;
} else {
	die( 'This version of the PageAssessments extension requires MediaWiki 1.25+' );
}
