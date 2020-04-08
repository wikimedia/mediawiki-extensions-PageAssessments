<?php
$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['suppress_issue_types'] = array_merge( $cfg['suppress_issue_types'], [
	// This test seems to be buggy or overly strict (T249738)
	'PhanTypeMismatchArgumentNullableInternal',
] );

return $cfg;
