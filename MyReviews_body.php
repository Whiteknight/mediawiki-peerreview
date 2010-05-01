<?php
class MyReviews extends SpecialPage {
    function __construct() {
        parent::__construct('MyReviews');
        wfLoadExtensionMessages('MyReviews');
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
        return Title::newFromText("Special:MyReviews/$args")->getFullURL();
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

    function execute($par) {
        global $wgRequest, $wgOut;
        global $wgUser, $wgContLang, $wgScriptPath;

        $this->setHeaders();
        $wgOut->setPageTitle("My Reviews");
        $wgOut->addLink(array(
            'rel' => 'stylesheet',
            'type' => 'text/css',
            'media' =>
            'screen,projection',
            'href' => "$wgScriptPath/extensions/PeerReview/PeerReview.css"
        ));
        if (isset($par)) {
            $parts = explode('/', $par);
            if ($parts[0] == "delete") {
                $this->deleteRecord($parts[1]);
                return;
            }
        }

        $userName = "";
        $userId = $wgUser->getID();
        if($userId == 0) {
            $html = <<<EOT
<h2>Denied!</h2>
<p>You must be logged in to view this page!</p>
EOT;
            $wgOut->addHTML($html);
            return true;
        } else {
            $userName = $wgUser->getName();
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
    WHERE user_id = '{$userId}';
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
            $givenReviews .= <<<EOT
            <div class="myReviews-review-given">
                <p>
                    <span style='float: right; font-size: 80%'>
                        <a href="{$deletelink}">delete</a>
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
        SELECT page_id FROM page_owner WHERE user_id = '{$userId}'
        UNION
        SELECT rev_page FROM revision
            WHERE rev_parent_id = 0 AND rev_user = '{$userId}'
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
            if ($row->user_id == $userId) {
                $extrainfo = "<span style='float: right; font-size: 80%'>(Given by Me)</span>";
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
<h2 style="clear:both;">{$userName}'s Reviews</h2>
<table style="width: 100%;" cellspacing="5" cellpadding="5">
    <tr>
        <td style="width: 50%; border: 1px solid #000040; background: #F0F0FF;" valign="top">
            <h3>Reviews given to me</h3>
            {$takenReviews}
        </td>
        <td style="width: 50%; border: 1px solid #004000; background: #F0FFF0;" valign="top">
            <h3>Reviews I've given</h3>
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
