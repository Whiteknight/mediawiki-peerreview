<?php
class MyReviews extends SpecialPage {
    function __construct() {
        parent::__construct('MyReviews', 'autoconfirmed');
        wfLoadExtensionMessages('PeerReview');
    }

    protected $username = "";
    protected $userID = 0;
    protected $viewer = false;

    # Validate the user. To use this page the user must be logged in
    function validateUser()
    {
        global $wgUser;
        if (!$this->userCanExecute($wgUser)) {
            $this->displayRestrictionError();
            return false;
        }
        $this->username = $wgUser->getName();
        $this->userID = $wgUser->getID();
        $this->viewer = $wgUser->isAllowed('viewreviews');
        return true;
    }

    # Given a namespace ID value, convert it to a textual representation suitable
    # for direct prepending to the title string
    function getNamespaceNameFromId($id) {
        global $wgContLang, $wgExtraNamespaces;
        $namespace = "";
        if(isset($wgExtraNamespaces[$id])) {
            $namespace = $wgExtraNamespaces[$id] . ":";
        } elseif($id == 0) {
            $namespace = "";
        } else {
            $namespace = $wgContLang->getNsText($id) . ":";
        }
        return $namespace;
    }

    # Create a URL link to this special page, optionally with some arguments
    function linkArgs($args = false)
    {
        return $this->getTitle($args)->getFullURL();
    }

    # Delete a comment
    function deleteRecord($recordid)
    {
        global $wgOut;
        $todelete = array(
            'id' => $recordid
        );
        $dbw = wfGetDB(DB_MASTER);
        $dbw->delete('review', $todelete);
        $back = $this->linkArgs();
        $msgDeleted = wfMsg('peerreview-commentdelete');
        $msgBack = wfMsg('peerreview-back');
        $wgOut->addHTML("<p>{$msgDeleted}</p><a href=\"$back\">{$msgBack}</a>");
    }

    # Show an edit form to edit an existing comment
    # TODO: We should either have a link to the page, or find a way to display
    #       an inline preview of the page here so we can see it while we review
    function editRecordForm($recordid, $ownerid)
    {
        global $wgOut;
        if ($this->userID != $ownerid) {
            $wgOut->wrapWikiMsg('<p>$1</p>', array('peerreview-errowncomment', $recordid));
            return;
        }
        $dbr = wfGetDB(DB_SLAVE);
        $res = $dbr->query("SELECT * FROM review WHERE id = '$recordid';");
        $row = $dbr->fetchObject($res);
        $scoreid = $row->review_score_id;
        $comment = $row->comment;
        $dbr->freeResult($res);

        $res = $dbr->select('review_score', array('id', 'display_as'));
        $optionString = "";
        while($row = $dbr->fetchObject($res)) {
            $selected = "";
            if ($row->id == $scoreid) {
                $selected = "selected";
            }
            $optionString .= "<option value=\"{$row->id}\" $selected>{$row->display_as}</option>";
        }
        $dbr->freeResult($res);
        $href = $this->linkArgs();
        $msgComment = wfMsg('peerreview-comment');
        $msgScore = wfMsg('peerreview-score');
        $msgCancel = wfMsg('peerreview-cancel');
        $msgSave = wfMsg('peerreview-save');
        $html = <<<EOT
<form action="$href" method="POST">
    <input type="hidden" name="postbackmode" value="editcomment"/>
    <input type="hidden" name="recordid" value="{$recordid}"/>
    <input type="hidden" name="ownerid" value="{$ownerid}"/>
    <p><b>{$msgComment}:</b></p>
    <textarea name="commenttext" style="height: 250px;">$comment</textarea>
    <b>{$msgScore}:</b> <select name="commentscore">
        $optionString
    </select>
    <input type="submit" name="submitsave" value="{$msgSave}">
    <a href="$href">{$msgCancel}</a>
</form>
EOT;
        $wgOut->addHTML($html);
    }

    # Postback handler. Postback can be either an edit comment or a user
    # impersonation request from a viewer
    function handlePostBack()
    {
        global $wgOut, $wgRequest;

        $postbackmode = $wgRequest->getText("postbackmode");
        if ($postbackmode == "editcomment") {
            $recordid = $wgRequest->getText("recordid");
            $ownerid = $wgRequest->getText("ownerid");
            $score = $wgRequest->getVal("commentscore");
            $text = $wgRequest->getVal("commenttext");
            $dbr = wfGetDB(DB_MASTER);
            $dbr->update('review',
                array('comment' => $text, "review_score_id" => $score),
                array('id' => $recordid),
                __METHOD__
            );
            $href = $this->linkArgs();
            $msgBack = wfMsg('peerreview-back');
            $msgSaved = wfMsg('peerreview-commentsaved');
            $wgOut->addHTML("<p>$msgSaved</p><a href=\"$href\">$msgBack</a>");;
        }
        else if ($postbackmode == "impersonateuser") {
            $this->username = $wgRequest->getText("username");
            $this->userID = User::idFromName($this->username);
            $this->showMainPage();
        }
    }

    # Execute function. Dispatch the request to the proper handler function
    function execute($par) {
        global $wgOut, $wgScriptPath, $wgRequest;

        $this->setHeaders();
        if (!$this->validateUser())
            return;
        if ($wgRequest->wasPosted()) {
            $this->handlePostBack();
            return;
        }
        if (isset($par)) {
            $parts = explode('/', $par);
            if ($parts[0] == "delete") {
                $this->deleteRecord($parts[1]);
                return;
            }
            if ($parts[0] == "edit") {
                $this->editRecordForm($parts[1], $parts[2]);
                return;
            }
        } else {
            $this->showMainPage();
        }
    }

    # Show the header of the page. If the user is able to impersonate,
    # give them a form to change the username to view
    function showUsernameHeader($username)
    {
        global $wgOut;
        if ($this->viewer) {
            $html = <<<EOT
<form action="" method="POST">
    <input type="hidden" name="postbackmode" value="impersonateuser"/>
    <h2>
        Reviews for
        <input type="text" name="username" value="{$username}"/>
        <input type="submit" name="submit" value="View"/>
    </h2>
</form>
EOT;
            $wgOut->addHTML($html);
        } else {
            $wgOut->addHTML("<h2>Reviews for $username</h2>");
        }
    }

    # Reviews this user has given
    function reviewsIGave() {
        $selectquery = <<<EOSQL
SELECT
    review_score.display_as, page.page_namespace, page.page_title, review.*
    FROM
        review_score INNER JOIN (
            review INNER JOIN page ON review.page_id = page.page_id
        ) ON review.review_score_id = review_score.id
    WHERE user_id = '{$this->userID}';
EOSQL;
        $dbr = wfGetDB(DB_SLAVE);
        $given = $dbr->query($selectquery);
        $givenReviews = "";
        $msgEdit = wfMsg('peerreview-edit');
        $msgDelete = wfMsg('peerreview-delete');
        while($row = $dbr->fetchObject($given)) {
            $namespaceId = $row->page_namespace;
            $namespace = $this->getNamespaceNameFromId($namespaceId);
            $pagehref = Title::newFromText($namespace . $row->page_title)->getFullURL();
            $pagelink = $namespace . $row->page_title;
            $editlink = $this->linkArgs("edit/{$row->id}/{$row->user_id}");
            $comment = str_replace("\n", "<br>", $row->comment);
            $givenReviews .= <<<EOT
            <div class="PeerReview-MyReviews-given">
                <p>
                    <span style='float: right; font-size: 80%'>
                        [<a href="javascript: really_delete({$row->id});">{$msgDelete}</a>
                        &mdash;
                        <a href="{$editlink}">{$msgEdit}</a>]
                    </span>
                    <b><a href="{$pagehref}">{$pagelink}</a></b>
                </p>
                <p><b>{$row->display_as}</b>: {$comment}</p>
            </div>
EOT;
        }
        return $givenReviews;
    }

    # Reviews given to this user
    function reviewsIReceive() {
        global $wgPeerReviewSeeReviewers;

        # Explanation of this SQL: We want to get page/review information (along
        # with the displayable name of the review) for all pages that this user
        # owns
        $selectquery = <<<EOSQL
SELECT
    page.page_namespace, page.page_title, review_score.display_as, review.*
    FROM (
        review INNER JOIN review_score
            ON review.review_score_id = review_score.id
    ) INNER JOIN page
        ON review.page_id = page.page_id
    WHERE
        review.page_id IN (
            SELECT page_id
                FROM page_owner
                WHERE user_id = '{$this->userID}'
        )
        AND review.user_id != '{$this->userID}';
EOSQL;
        $dbr = wfGetDB(DB_SLAVE);
        $taken = $dbr->query($selectquery);
        $takenReviews = "";
        $msgTalk = wfMsg('peerreview-talk');
        while($row = $dbr->fetchObject($taken)) {
            $namespaceId = $row->page_namespace;
            $namespace = $this->getNamespaceNameFromId($namespaceId);
            $pagelink = $namespace . $row->page_title;
            $pagehref = Title::newFromText($pagelink)->getFullURL();
            $comment = str_replace("\n", "<br>", $row->comment);
            $reviewer = "";
            if ($wgPeerReviewSeeReviewers) {
                $reviewuser = User::newFromId($row->user_id);
                $username = $reviewuser->getName();
                $userpage = $reviewuser->getUserPage();
                $userhref = $userpage->getFullURL();
                $talkhref = $userpage->getTalkPage()->getFullURL();
                $reviewer = <<<EOR
                <p style="margin-left: 2em;">
                    &mdash;
                    <a href="{$userhref}">{$username}</a>
                    (<a href="{$talkhref}">{$msgTalk}</a>)
                </p>
EOR;
            }
            $takenReviews .= <<<EOT
            <div class="PeerReview-MyReviews-received">
                <p><b><a href="{$pagehref}">{$pagelink}</a></b></p>
                {$reviewer}
                <p><b>{$row->display_as}</b>: {$comment}</p>
            </div>
EOT;
        }
        return $takenReviews;
    }

    function ownedPages() {
        $selectquery = <<<EOT
SELECT
    page.page_namespace, page.page_title
    FROM page, page_owner
    WHERE page_owner.user_id = '{$this->userID}'
        AND page_owner.page_id = page.page_id
EOT;
        $dbr = wfGetDB(DB_SLAVE);
        $pages = $dbr->query($selectquery);
        $pagehtml = "";
        while ($row = $dbr->fetchObject($pages)) {
            $pagename = $this->getNamespaceNameFromId($row->page_namespace) . $row->page_title;
            $page = Title::newFromText($pagename);
            $pagehref = $page->getFullURL();
            $talkhref = $page->getTalkPage()->getFullURL();
            $pagehtml .= <<<EOT
            <div style="float: left; margin-right: 5em;">
                <a href='$pagehref'>$pagename</a> (<a href='$talkhref'>Talk</a>)
            </div>
EOT;
        }
        return $pagehtml;
    }

    # Show the basic main page
    function showMainPage() {
        global $wgRequest, $wgOut;

        $this->showUsernameHeader($this->username);
        $givenReviews = $this->reviewsIGave();
        $takenReviews = $this->reviewsIReceive();
        $ownedpages = $this->ownedPages();
        $baseurl = $this->linkArgs();
        $msgReally = wfMsg('peerreview-reallydelete');
        $msgOwned = wfMsg('peerreview-pagesowned');
        $msgGiven = wfMsg('peerreview-reviewsgiven');
        $msgTaken = wfMsg('peerreview-reviewstaken');
        $html = <<<EOT
<script type="text/javascript">
    function really_delete(id) {
        var url = "{$baseurl}/delete/" + id;
        if(confirm("R{$msgReally}"))
            document.location = url;
    }
</script>
<table style="width: 100%;" cellspacing="5" cellpadding="5">
    <tr>
        <td id="PeerReview-MyReviews-owned-side" valign="top" colspan="2">
            <h3>{$msgOwned}</h3>
            {$ownedpages}
        </td>
    </tr>
    <tr>
        <td id="PeerReview-MyReviews-received-side" valign="top">
            <h3>{$msgTaken}</h3>
            {$takenReviews}
        </td>
        <td id="PeerReview-MyReviews-given-side" valign="top">
            <h3>{$msgGiven}</h3>
            {$givenReviews}
        </td>
    </tr>
</table>
EOT;
        $wgOut->addHTML($html);
        return true;
    }
}
?>
