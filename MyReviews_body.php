<?php
class MyReviews extends SpecialPage {
    function __construct() {
        parent::__construct('MyReviews');
        wfLoadExtensionMessages('PeerReview');
    }

    protected $username = "";
    protected $userID = 0;
    protected $viewer = false;

    # Validate the user. To use this page the user must be logged in
    function validateUser()
    {
        global $wgUser;

        $this->userName = "";
        $this->userID = $wgUser->getID();
        if($this->userID == 0) {
            return false;
        } else {
            $this->username = $wgUser->getName();
            $this->viewer = $wgUser->isAllowed('viewreviews');
            return true;
        }
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
    function linkArgs($args = "")
    {
        $title = "Special:MyReviews";
        if ($args != "")
            $title .= "/" . $args;
        return Title::newFromText($title)->getFullURL();
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
        $html = <<<EOT
<p>
    Comment deleted.<br>
    <a href="$back">Back</a>
</p>
EOT;
        $wgOut->addHTML($html);
    }

    # Show an edit form to edit an existing comment
    # TODO: We should either have a link to the page, or find a way to display
    #       an inline preview of the page here so we can see it while we review
    function editRecordForm($recordid, $ownerid)
    {
        global $wgOut;
        if ($this->userID != $ownerid) {
            $html = <<<EOT
<h2>Access Denied!</h2>
<p>
    You cannot edit review {$recordid} because you did not create it
</p>
EOT;
            $wgOut->addHTML($html);
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
        $html = <<<EOT
<form action="$href" method="POST">
    <input type="hidden" name="postbackmode" value="editcomment"/>
    <input type="hidden" name="recordid" value="{$recordid}"/>
    <input type="hidden" name="ownerid" value="{$ownerid}"/>
    <p><b>Comment:</b></p>
    <textarea name="commenttext" style="height: 250px;">$comment</textarea>
    <b>Score:</b> <select name="commentscore">
        $optionString
    </select>
    <input type="submit" name="submitsave" value="Save">
    <a href="$href">Cancel</a>
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
            $text = $dbr->addQuotes($text);
            $score = $dbr->addQuotes($score);
            // TODO: How to sanitize SQL input?
            $dbr->query("UPDATE review SET comment=$text, review_score_id=$score WHERE id='$recordid'");
            $href = $this->linkArgs();
            $html = <<<EOT
<h2>Comment Updated</h2>
<p>
    Your comment has been successfully updated!
</p>
<a href="$href">Back</a>
EOT;
            $wgOut->addHTML($html);
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
        $wgOut->setPageTitle("My Reviews");
        if (!$this->validateUser()) {
            $html = <<<EOT
<h2>Access Denied!</h2>
<p>
    You must be logged in to view this page!
</p>
EOT;
            $wgOut->addHTML($html);
            return;
        }
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

    function showViewerAdditions()
    {
        global $wgOut;
        if ($this->viewer) {
            $html = <<<EOT
<div style="float: right;">
    <form action="" method="POST">
        <input type="hidden" name="postbackmode" value="impersonateuser"/>
        <b>Choose user to view: </b>
        <input type="text" name="username"/>
        <input type="submit" name="submit" value="View"/>
    </form>
</div>
EOT;
            $wgOut->addHTML($html);
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
        if($dbr->numRows($given) == 0) {
            $givenReviews = "<p>No reviews</p>";
        }
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
                        <a href="javascript: really_delete({$row->id});">delete</a>
                        &mdash;
                        <a href="{$editlink}">edit</a>
                    </span>
                    <b>Page</b>: <a href="{$pagehref}">{$pagelink}</a>
                </p>
                <p><b>Score</b>: {$row->display_as}</p>
                <p><b>Comment</b>: {$comment}</p>
            </div>
EOT;
        }
        return $givenReviews;
    }

    # Reviews given to this user
    function reviewsIReceive() {
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
    WHERE review.page_id IN (
        SELECT page_id
            FROM page_owner
            WHERE user_id = '{$this->userID}'
    );
EOSQL;
        $dbr = wfGetDB(DB_SLAVE);
        $taken = $dbr->query($selectquery);
        $takenReviews = "";
        if($dbr->numRows($taken) == 0) {
            $takenReviews = "<p>No reviews</p>";
        }
        while($row = $dbr->fetchObject($taken)) {
            $extrainfo = "";
            if ($row->user_id == $this->userID) {
                $extrainfo = "<span style='float: right; font-size: 80%'>(Self)</span>";
            }
            $namespaceId = $row->page_namespace;
            $namespace = $this->getNamespaceNameFromId($namespaceId);
            $pagelink = $namespace . $row->page_title;
            $pagehref = Title::newFromText($pagelink)->getFullURL();
            $comment = str_replace("\n", "<br>", $row->comment);
            $takenReviews .= <<<EOT
            <div class="PeerReview-MyReviews-received">
                <p>{$extrainfo}<b>Page</b>: <a href="{$pagehref}">{$pagelink}</a></p>
                <p><b>Score</b>: {$row->display_as}</p>
                <p><b>Comment</b>: {$comment}</p>
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
            $pagehtml .= "<p><a href='$pagehref'>$pagename</a> (<a href='$talkhref'>Talk</a>)</p>";
        }
        return $pagehtml;
    }

    # Show the basic main page
    function showMainPage() {
        global $wgRequest, $wgOut;

        $this->showViewerAdditions();
        $givenReviews = $this->reviewsIGave();
        $takenReviews = $this->reviewsIReceive();
        $ownedpages = $this->ownedPages();
        $baseurl = $this->linkArgs();

        # TODO: Show a list of all pages I own
        $html = <<<EOT
<script type="text/javascript">
    function really_delete(id) {
        var url = "{$baseurl}/delete/" + id;
        if(confirm("Really delete this review?"))
            document.location = url;
    }
</script>
<h2 style="clear:both;">{$this->username}'s Reviews</h2>
<table style="width: 100%;" cellspacing="5" cellpadding="5">
    <tr>
        <td id="PeerReview-MyReviews-received-side" valign="top">
            <h3>Reviews received by {$this->username}</h3>
            {$takenReviews}
        </td>
        <td id="PeerReview-MyReviews-given-side" valign="top">
            <h3>Reviews given by {$this->username}</h3>
            {$givenReviews}
            <h3>Pages owned by {$this->username}</h3>
            {$ownedpages}
        </td>
    </tr>
</table>
EOT;
        $wgOut->addHTML($html);
        return true;
    }
}
?>
