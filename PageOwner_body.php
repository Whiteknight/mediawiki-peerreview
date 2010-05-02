<?php
class PageOwner extends SpecialPage {
    function __construct() {
        parent::__construct( 'PageOwner' );
        wfLoadExtensionMessages('PageOwner');
    }

    protected $username = "";
    protected $userID = 0;

    # To use this page the user must be logged in, and must have the
    # "assignpage" permission
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

    # Add a user as an owner for the specified page.
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

    # Remove a user as an owner of the specified page
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

    # We have a postback. It can be either an Add or a Remove of a user
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
            $method = $wgRequest->getText("par_method");
            $username = $wgRequest->getText("par_username");
            $userid = User::idFromName($username);
            if ($method == "remove") {
                $this->handleRemoveUser($pagename, $pageid, $username, $userid);
            } else if ($method == "assign") {
                $this->handleAddUser($pagename, $pageid, $username, $userid);
            }
        }
        $href = Title::newFromText("Special:PageOwner/$pagename")->getFullURL();
        $wgOut->addHTML("<br><a href=\"$href\">Back</a>");
    }

    # Execute function. Validate the user, then show whatever they need to see
    function execute($par) {
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

    # Display the main special page
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
            $currentowners = "<p>No owners</p>";
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

        # Now, get a list of all page editors to make a list of possible people
        # to assign to this page.
        # Break down this sql statement: We want a list of all users who have
        # made edits to this page, but we want to exclude any users who are
        # already listed as owners of the page
        $selectquery = <<<EOSQL
SELECT DISTINCT rev_user
    FROM revision
    WHERE rev_page = '{$pageid}'
        AND rev_user NOT IN (
            SELECT user.user_id
                FROM user, page_owner
                WHERE page_owner.page_id = '{$pageid}'
                    AND user.user_id = page_owner.user_id
        );
EOSQL;
        $res = $dbr->query($selectquery);
        $possibleowners = "";
        while ($row = $dbr->fetchObject($res)) {
            if ($row->rev_user == 0) {
                continue;
            }
            $username = User::newFromId($row->rev_user)->getName();
            $userpage = Title::newFromText("User:$username");
            $userhref = $userpage->getFullURL();
            $talkhref = $userpage->getTalkPage()->getFullURL();
            $possibleowners .= <<<EOT
 <p>
    [<a href="javascript: assignimplicituser('{$username}')" style="font-size: 80%;">Assign</a>]
    &mdash;
    <a href="{$userhref}">{$username}</a> (<a href="{$talkhref}">Talk</a>)<br/>
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
        method = document.getElementById("par_method");
        method.value = "remove";
        document.changepeople.submit();
    }

    function assignimplicituser(user) {
        username = document.getElementById("par_username");
        username.value = user;
        method = document.getElementById("par_method");
        method.value = "assign";
        document.changepeople.submit();
    }
</script>
<table style="width: 100%;" cellspacing="5" cellpadding="5">
    <tr>
        <td id="PeerReview-PageOwner-owners" valign="top">
            <h3>Current Page Owners</h3>
            <form name="changepeople" action="{$href}" method="POST">
                {$currentowners}
                <input type="hidden" name="par_method" id="par_method" value="remove">
                <input type="hidden" name="par_pagename" value="{$par}"/>
                <input type="hidden" name="par_username" id="par_username" value=""/>
            </form>
        </td>
        <td id="PeerReview-PageOwner-new" valign="top">
            <h3><a href="{$pagehref}">{$par}</a></h3>
            <hr>
            <h3>Add New Owners</h3>
            <form action="$href" method="POST">
                New owner: <input type="textbox" name="newowner" id="par_newowner"/>
                <input type="hidden" name="par_pagename" value="{$par}"/>
                <input type="submit" name="submit" value="Add"/>
                {$possibleowners}
            </form>
        </td>
    </tr>
</table>
EOT;
        $wgOut->addHTML($html);
        return true;
    }
}

