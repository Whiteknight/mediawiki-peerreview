<?php
$dir = dirname(__FILE__) . '/';

# Alert the user that this is not a valid entry point to MediaWiki if they try to access the special pages file directly.
if (!defined('MEDIAWIKI')) {
        echo <<<EOT
To install my extension, put the following line in LocalSettings.php:
require_once( "{$dir}PeerReview.php" );
EOT;
        exit( 1 );
}

include_once( "{$dir}PageReviews.php" );


# Setup the PageOwner special page
$wgAutoloadClasses['PageOwner'] = $dir . 'PageOwner_body.php';
$wgExtensionMessagesFiles['PageOwner'] = $dir . 'PageOwner.i18n.php';
$wgExtensionAliasesFiles['PageOwner'] = $dir . 'PageOwner.alias.php';
$wgSpecialPages['PageOwner'] = 'PageOwner';

# Setup the MyReviews special page
$wgAutoloadClasses['MyReviews'] = $dir . 'MyReviews_body.php';
$wgExtensionMessagesFiles['MyReviews'] = $dir . 'MyReviews.i18n.php';
$wgExtensionAliasesFiles['MyReviews'] = $dir . 'MyReviews.alias.php';
$wgSpecialPages['MyReviews'] = 'MyReviews';

# Add hooks on setup
$wgExtensionFunctions[] = 'PeerReview_Setup';
function PeerReview_Setup() {
    global $wgUser, $wgHooks;
    PeerReview_addJSAndCSS();
    if ($wgUser->getID()) {
        $wgHooks['PersonalUrls'][] = 'PeerReview_addPersonalUrl';
        if ($wgUser->isAllowed("assignpage")) {
            $wgHooks['SkinTemplateTabs'][] = 'PeerReview_AddActionContentHook';
            $wgHooks['SkinTemplateNavigation'][] = 'PeerReview_AddActionContentHook2';
        }
    }
}

function PeerReview_addJSAndCSS()
{
    global $wgOut, $wgUser, $wgScriptPath;
    $skin = $wgUser->getSkin()->getSkinName();
    $wgOut->addLink(array(
        'rel' => 'stylesheet',
        'type' => 'text/css',
        'media' =>
        'screen,projection',
        'href' => "$wgScriptPath/extensions/PeerReview/PeerReview.css"
    ));
    $wgOut->addLink(array(
        'rel' => 'stylesheet',
        'type' => 'text/css',
        'media' =>
        'screen,projection',
        'href' => "$wgScriptPath/extensions/PeerReview/PeerReview_{$skin}.css"
    ));
    $wgOut->addScriptFile("http://ajax.googleapis.com/ajax/libs/jquery/1.4.2/jquery.min.js");
}

# Add the "my reviews" link to the personal URLs list
function PeerReview_addPersonalUrl(&$personal_urls, $title) {
    $personal_urls['myreviews'] = array(
        'text' => "My reviews",
        'href' => Skin::makeSpecialUrl('MyReviews')
    );
    return true;
}

# Add the "Ownership" link to the page tabs
function PeerReview_AddActionContentHook($skin, &$content_actions) {
    global $wgTitle;

    if ($wgTitle->getNamespace() != NS_SPECIAL) {
        $content_actions['ownership'] = array(
            'class' => false,
            'text' => "Ownership",
            'href' => Skin::makeSpecialUrl('PageOwner')
        );
    }

    return true;
}

# Add the "Ownership" link on the Vector skin
function PeerReview_AddActionContentHook2($skin, &$links) {
    global $wgTitle;

    if ($wgTitle->getNamespace() != NS_SPECIAL) {
        $links['actions']['ownership'] = array(
            'class' => false,
            'text' => "Ownership",
            'href' => Skin::makeSpecialUrl('PageOwner') . "/" . $skin->mTitle->getEscapedText()
        );
    }

    return true;
}

# "assignpage" allows a person to use Special:PageOwner
# "viewreviews" allows a person to see the reviews of another user
# by default, sysops can assign page ownership
$wgGroupPermissions['sysop']['assignpage'] = true;
$wgGroupPermissions['sysop']['viewreviews'] = true;
$wgGroupPermissions['assigner']['assignpage'] = true;
$wgGroupPermissions['grader']['viewreviews'] = true;

# Credits
$wgExtensionCredits['specialpage'][] = array(
   'name' => 'PeerReview',
   'author' => 'Andrew Whitworth and Jason Grafinger',
   'url' => 'http://www.wittieproject.org/',
   'description' => 'Displays user related reviews',
   'descriptionmsg' => 'myreviews-desc',
   'version' => '1.0'
);
?>
