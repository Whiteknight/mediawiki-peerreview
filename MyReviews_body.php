<?php
class MyReviews extends SpecialPage {
    function __construct() {
        parent::__construct('MyReviews');
        wfLoadExtensionMessages('MyReviews');
    }

    protected $username = "";
    protected $userID = 0;
    protected $viewer = false;

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

    function linkArgs($args = "")
    {
        $title = "Special:MyReviews";
        if ($args != "")
            $title .= "/" . $args;
        return Title::newFromText($title)->getFullURL();
    }

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

    function execute($par) {
        global $wgOut, $wgScriptPath, $wgRequest;

        $this->setHeaders();
        $wgOut->setPageTitle("My Reviews");
        if (!$this->validateUser()) {
            $html = <<<EOT
<h2>Access Denied!</h2>
<p>You must be logged in to view this page!</p>
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

    function showMainPage() {
        global $wgRequest, $wgOut;

        if ($this->viewer) {
            $html = <<<EOT
<form action="" method="POST">
    <input type="hidden" name="postbackmode" value="impersonateuser">
    <b>Choose user to view: </b>
    <input type="text" name="username"/>
    <input type="submit" name="submit" value="View"/>
</form>
EOT;
            $wgOut->addHTML($html);
        }


        $dbr = wfGetDB(DB_SLAVE);

        // Reviews this user has given
        $selectquery =<<<EOSQL
SELECT
    review_score.display_as, page.page_namespace, page.page_title, review.*
    FROM
        review_score INNER JOIN (
            review INNER JOIN page ON review.page_id = page.page_id
        ) ON review.review_score_id = review_score.id
    WHERE user_id = '{$this->userID}';
EOSQL;
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
            $deletelink = $this->linkArgs("delete/{$row->id}");
            $editlink = $this->linkArgs("edit/{$row->id}/{$row->user_id}");
            $givenReviews .= <<<EOT
            <div class="myReviews-review-given">
                <p>
                    <span style='float: right; font-size: 80%'>
                        <a href="{$deletelink}">delete</a>
                        &mdash;
                        <a href="{$editlink}">edit</a>
                    </span>
                    <b>Page</b>: <a href="{$pagehref}">{$pagelink}</a>
                </p>
                <p><b>Score</b>: {$row->display_as}</p>
                <p><b>Comment</b>: {$row->comment}</p>
            </div>
EOT;
        }

        // Reviews given to this user
        $selectquery =<<<EOSQL
SELECT
    page.page_namespace, page.page_title, review_score.display_as, review.*
    FROM (
        review INNER JOIN review_score
            ON review.review_score_id = review_score.id
    ) INNER JOIN page
        ON review.page_id = page.page_id
    WHERE review.page_id IN (
        SELECT page_id FROM page_owner WHERE user_id = '{$this->userID}'
        UNION
        SELECT rev_page FROM revision
            WHERE rev_parent_id = 0 AND rev_user = '{$this->userID}'
            GROUP BY rev_page
    );
EOSQL;
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
            $takenReviews .= <<<EOT
            <div class="myReviews-review-received">
                <p>{$extrainfo}<b>Page</b>: <a href="{$pagehref}">{$pagelink}</a></p>
                <p><b>Score</b>: {$row->display_as}</p>
                <p><b>Comment</b>: {$row->comment}</p>
            </div>
EOT;
        }

        $html = <<<EOT
<h2 style="clear:both;">{$this->username}'s Reviews</h2>
<table style="width: 100%;" cellspacing="5" cellpadding="5">
    <tr>
        <td style="width: 50%; border: 1px solid #000040; background: #F0F0FF;" valign="top">
            <h3>Reviews given to {$this->username}</h3>
            {$takenReviews}
        </td>
        <td style="width: 50%; border: 1px solid #004000; background: #F0FFF0;" valign="top">
            <h3>Reviews given by {$this->username}</h3>
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
