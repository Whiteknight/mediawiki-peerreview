<?php
# Alert the user that this is not a valid entry point to MediaWiki if they try to access the special pages file directly.
if (!defined('MEDIAWIKI')) {
        echo <<<EOT
To install my extension, put the following line in LocalSettings.php:
require_once( "\$IP/extensions/WITTIE/PageOwner.php" );
EOT;
        exit( 1 );
}

include_once( "$IP/extensions/PeerReview/PageReviews.php" );

$dir = dirname(__FILE__) . '/';
$wgAutoloadClasses['PageOwner'] = $dir . 'PageOwner_body.php';
$wgExtensionMessagesFiles['PageOwner'] = $dir . 'PageOwner.i18n.php';
$wgExtensionAliasesFiles['PageOwner'] = $dir . 'PageOwner.alias.php';
$wgSpecialPages['PageOwner'] = 'PageOwner';

$wgAutoloadClasses['MyReviews'] = $dir . 'MyReviews_body.php';
$wgExtensionMessagesFiles['MyReviews'] = $dir . 'MyReviews.i18n.php';
$wgExtensionAliasesFiles['MyReviews'] = $dir . 'MyReviews.alias.php';
$wgSpecialPages['MyReviews'] = 'MyReviews';

// by default, sysops can assign page ownership
$wgGroupPermissions['sysop']['assignpage'] = true;
$wgGroupPermissions['assigner']['assignpage'] = true;

$wgExtensionCredits['specialpage'][] = array(
   'name' => 'PeerReview',
   'author' => 'The WITTIE Team',
   'url' => 'http://www.wittieproject.org/',
   'description' => 'Displays user related reviews',
   'descriptionmsg' => 'myreviews-desc',
   'version' => '1.0'
);



?>
