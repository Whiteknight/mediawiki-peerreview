<?php
if(!defined('MEDIAWIKI')) {
   echo <<<EOT
To install 'MyReviews', put the following line in LocalSettings.php:
require_once("\$IP/extensions/WITTIE/MyReviews.php");
EOT;
   exit(1);
}

$wgExtensionCredits['specialpage'][] = array(
   'name' => 'MyReviews',
   'author' => 'WITTIE Team',
   'url' => 'http://www.wittieproject.org/',
   'description' => 'Displays user related reviews',
   'descriptionmsg' => 'myreviews-desc',
   'version' => '1.0');

$dir = dirname(__FILE__) . '/';

$wgAutoloadClasses['MyReviews'] = $dir . 'MyReviews_body.php';
$wgExtensionMessagesFiles['MyReviews'] = $dir . 'MyReviews.i18n.php';
$wgExtensionAliasesFiles['MyReviews'] = $dir . 'MyReviews.alias.php';
$wgSpecialPages['MyReviews'] = 'MyReviews';

?>