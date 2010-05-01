<?php
class PageOwner extends SpecialPage {
    function __construct() {
        parent::__construct( 'PageOwner' );
        wfLoadExtensionMessages('PageOwner');
    }

    protected $username = "";
    protected $userID = 0;

    function validateUser()
    {
        global $wgUser;

        $this->userName = "";
        $this->userID = $wgUser->getID();
        if($this->userID != 0 && $wgUser->isAllowed("assignpage")) {
            $this->userName = $wgUser->getName();
            return true;
        } else {
            return false;
        }
    }

    function handleAddUser($pagename, $pageid, $username, $userid) {
        global $wgOut;
        if ($userid == 0) {
            $wgOut->addHTML("User $username doesn't exist");
            return;
        }
        $toinsert = array(
            'page_id' => $pageid,
            'user_id' => $userid
        );
        $dbw = wfGetDB(DB_MASTER);
        $dbw->insert('page_owner', $toinsert);
        $wgOut->addHTML("Added $username as an owner for $pagename");
        return;
    }

    function handleRemoveUser($pagename, $pageid, $username, $userid) {
        global $wgOut;
        if ($userid == 0) {
            $wgOut->addHTML("User $username doesn't exist");
            return;
        }
        $todelete = array(
            'page_id' => $pageid,
            'user_id' => $userid
        );
        $dbw = wfGetDB(DB_MASTER);
        $dbw->delete('page_owner', $todelete);
        $wgOut->addHTML("Removed $username as an owner for $pagename");
    }

    function handlePostBack() {
        global $wgRequest, $wgOut;
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
        $href = Title::newFromText("Special:PageOwner/$pagename")->getFullURL();
        $wgOut->addHTML("<br><a href=\"$href\">Back</a>");
    }

    function execute( $par ) {
        global $wgRequest, $wgOut;

        if (!$this->validateUser()) {
            $html = <<<EOT
<h2>Access Denied!</h2>
<p>
    You must be logged in and have assigner permissions to access this
    page. Please contact a site administrator if you think you should have
    this right.
</p>
EOT;
            $wgOut->addHTML($html);
            return;
        }
        if ($wgRequest->wasPosted()) {
            $this->handlePostBack();
            return;
        }
        if (!isset($par)) {
            $wgOut->addHTML("<p>Error: No page specified</p>");
            return;
        }
        $this->showMainSpecialPage($par);
    }

    function showMainSpecialPage($par)
    {
        global $wgRequest, $wgOut;

        $pageid = Title::newFromText($par)->getArticleId();
        if ($pageid == 0) {
            $wgOut->addHTML("<p>Error: Page '$par' does not exist</p>");
            return;
        }
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
            $userpage = Title::newFromText("User:$username");
            $userhref = $userpage->getFullURL();
            $talkhref = $userpage->getTalkPage()->getFullURL();
            $currentowners .= <<<EOT
<p>
    [<a href="javascript: removeuser('$username')" style="font-size: 80%;">Remove</a>]
    &mdash;
    <a href="$userhref">$username</a> (<a href="$talkhref">Talk</a>)
</p>
EOT;
        }

        $href = Title::newFromText("Special:PageOwner")->getFullURL();
        $pagehref = Title::newFromText($par)->getFullURL();
        $this->setHeaders();
        $html = <<<EOT
<script type="text/javascript">
    function removeuser(user) {
        username = document.getElementById("par_username");
        username.value = user;
        document.removepeople.submit();
    }
</script>
<table style="width: 100%;" cellspacing="5" cellpadding="5">
    <tr>
        <td style="width: 50%; border: 1px solid #000000; background-color: #F8F8F8;" valign="top">
            <h3>Current Page Owners</h3>
            <form name="removepeople" action="{$href}" method="POST">
                {$currentowners}
                <input type="hidden" name="par_pagename" value="{$par}"/>
                <input type="hidden" name="par_username" id="par_username" value=""/>
            </form>
        </td>
        <td style="width: 50%; border: 1px solid #000000; background-color: #F8F8F8;" valign="top">
            <h3><a href="{$pagehref}">{$par}</a></h3>
            <hr>
            <h3>Add New Owners</h3>
            <form action="$href" method="POST">
                New owner: <input type="textbox" name="newowner"/>
                <input type="hidden" name="par_pagename" value="{$par}"/>
                <input type="submit" name="submit" value="Add"/>
            </form>
        </td>
    </tr>
</table>
EOT;
        $wgOut->addHTML($html);
        return true;
    }
}

