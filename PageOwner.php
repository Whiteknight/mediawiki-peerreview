<?php
# Alert the user that this is not a valid entry point to MediaWiki if they try to access the special pages file directly.
if (!defined('MEDIAWIKI')) {
        echo <<<EOT
To install my extension, put the following line in LocalSettings.php:
require_once( "\$IP/extensions/WITTIE/PageOwner.php" );
EOT;
        exit( 1 );
}

$wgExtensionCredits['specialpage'][] = array(
	'name' => 'PageOwner',
	'author' => 'The WITTIE Team',
	'url' => 'http://www.wittieproject.org/',
	'description' => 'Allows setting ownership of a page',
	'descriptionmsg' => 'PageOwner-desc',
	'version' => '0.0.1'
);

$dir = dirname(__FILE__) . '/';

$wgAutoloadClasses['PageOwner'] = $dir . 'PageOwner_body.php'; # Tell MediaWiki to load the extension body.
$wgExtensionMessagesFiles['PageOwner'] = $dir . 'PageOwner.i18n.php';
$wgExtensionAliasesFiles['PageOwner'] = $dir . 'PageOwner.alias.php';
$wgSpecialPages['PageOwner'] = 'PageOwner'; # Let MediaWiki know about your new special page.


