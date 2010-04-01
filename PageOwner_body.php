<?php
class PageOwner extends SpecialPage {
    function __construct() {
        parent::__construct( 'PageOwner' );
        wfLoadExtensionMessages('PageOwner');
    }

    function handleAddUser($pagename, $pageid, $username, $userid) {
        global $wgOut;
        $toinsert = array(
            'page_id' => $pageid,
            'user_id' => $userid
        );
        $dbw = wfGetDB(DB_MASTER);
        $dbw->insert('page_owner', $toinsert);
        $wgOut->addHTML("Added $username as an owner for $pagename<br>");
        return;
    }

    function handleRemoveUser($pagename, $pageid, $username, $userid) {
        global $wgOut;
        $todelete = array(
            'page_id' => $pageid,
            'user_id' => $userid
        );
        $dbw = wfGetDB(DB_MASTER);
        $dbw->delete('page_owner', $todelete);
        $wgOut->addHTML("Removed $username as an owner for $pagename<br>");
    }

    function handlePostBack() {
        global $wgRequest, $wgScriptPath, $wgOut;
        $pagename = $wgRequest->getText('par_pagename');
        $pageid = Title::newFromText($pagename)->getArticleId();
        $submit = $wgRequest->getText('submit');
        if ($submit == "Add") {
            $username = $wgRequest->getText('newowner');
            $userid = User::idFromName($username);
            $this->handleAddUser($pagename, $pageid, $username, $userid);
        } else {
            $username = $wgRequest->getText("par_username");
            $userid = User::idFromName($username);
            $this->handleRemoveUser($pagename, $pageid, $username, $userid);
        }
        $wgOut->addHTML("<a href=\"$wgScriptPath/Special:PageOwner/$pagename\">Back</a>");
    }

    function execute( $par ) {
        global $wgRequest, $wgOut;
        global $wgScriptPath;

        if ($wgRequest->wasPosted()) {
            $this->handlePostBack();
            return;
        }
        if (!isset($par)) {
            $wgOut->addHTML("<p>Error: No page specified</p>");
            return;
        }

        $pageid = Title::newFromText($par)->getArticleId();
        $dbr = wfGetDB(DB_SLAVE);
        $selectquery =<<<EOSQL
SELECT user.user_name
    FROM user, page_owner
    WHERE page_owner.page_id = '{$pageid}'
        AND user.user_id = page_owner.user_id;
EOSQL;
        $given = $dbr->query($selectquery);

        $currentowners = "";
        if($dbr->numRows($given) == 0) {
            $currentowners = "<p>No Owners</p>";
        }
        while($row = $dbr->fetchObject($given)) {
            $username = $row->user_name;
            $currentowners .= "<p>$username &mdash " .
                "[<a href=\"javascript: removeuser('$username')\">Remove</a>]" .
                "</p>";
        }

        $this->setHeaders();
        $html = <<<EOT
<h2 style="clear: both;">Ownership of Page <a href="{$wgScriptPath}/{$par}"<{$par}</a></h2>
<div style="width: 45%; border: 1px solid #000000; background-color: #F8F8F8; padding: 5px; float: left;">
    <h3>Current Page Owners</h3>
    <script type="text/javascript">
    function removeuser(user) {
        username = document.getElementById("par_username");
        username.value = user;
        document.removepeople.submit();
    }
    </script>
    <form name="removepeople" action="{$wgScriptPath}/index.php?title=Special:PageOwner" method="POST">
        {$currentowners}
        <input type="hidden" name="par_pagename" value="{$par}"/>
        <input type="hidden" name="par_username" id="par_username" value=""/>
    </form>
</div>
<div style="width: 45%; border: 1px solid #000000; background-color: #F8F8F8; padding: 5px; float: right;">
    <h3>Add New Owners</h3>
    <form action="{$wgScriptPath}/index.php?title=Special:PageOwner" method="POST">
        New owner: <input type="textbox" name="newowner"/>
        <input type="hidden" name="par_pagename" value="{$par}"/>
        <input type="submit" name="submit" value="Add"/>
    </form>

</div>
EOT;
        $wgOut->addHTML($html);
        return true;
    }
}

