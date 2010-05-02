<?php
/**
 * A more locked-down approach to the Ratings extension.
 * @author Jason Grafinger
 */

# Startup hook
$wgHooks['OutputPageParserOutput'][] = array('PageReviews::addReviewForm');

class PageReviews {
    function handlePostBack() {
        global $wgRequest;
        global $wgUser;
        global $wgOut;
        global $wgArticle;

        $response = "";
        $extraClass = "";

        # Review and Comment must be set
        # User must be logged in
        if(!$wgRequest->getVal('reviewable-review')
            || !$wgRequest->getVal('reviewable-comment')
            || !is_numeric($wgRequest->getVal('reviewable-review'))
            || $wgUser->getID() == 0) {

            $extraClass = "PeerReview-PageReviews-warning";
            if($wgUser->getID() == 0) {
                $response = <<<EOT
<h2>You must log in to give a review!</h2>
<p>
    Please log in to leave a review on this page.
</p>
EOT;
            } else {
                $response = <<<EOT
<h2>Could not add your review!</h2>
<p>
    You may have incorrectly filled out the review form. Please try again.
</p>
<p>
    Remember, you must choose a score <strong>and</strong> write a comment!
</p>
EOT;
            }

        // Else, POST seems valid
        } else {
            $inReview = $wgRequest->getVal('reviewable-review');
            $inComment = $wgRequest->getVal('reviewable-comment');
            // Check selected review score against database
            $dbr = wfGetDB(DB_SLAVE);
            $res = $dbr->select('review_score', 'id', 'id=' . $inReview);
            $numRows = $dbr->numRows($res);
            $dbr->freeResult($res);
            if($numRows != 1) {
                // The incoming review score isn't in the database
                $extraClass = "PeerReview-PageReviews-warning";
                $response = <<<EOT
<h2>Error!</h2>
<p>
    Your selected review score is invalid!
</p>
EOT;
            } else {
                // Good to go
                $pageId = $wgArticle->getID();
                $dbw = wfGetDB(DB_MASTER);
                $toInsert = array(
                    'page_id' => $pageId,
                    'user_id' => $wgUser->getID(),
                    'review_score_id' => $wgRequest->getVal('reviewable-review'),
                    'comment' => $wgRequest->getVal('reviewable-comment'
                ));
                $dbw->insert('review', $toInsert);

                $extraClass = "PeerReview-PageReviews-success";
                $response = <<<EOT
<h2>Success!</h2>
<p>
    Your review has been added.
</p>
EOT;
            }
        }

        $finalResponse = <<<EOT
<div id="PeerReview-PageReviews-response" class="PeerReview-PageReviews-box {$extraClass}">
    <div style="padding-top:1em; float:right;">[<a href="#" id="reviewable-response-close">Close</a>]</div>
    {$response}
    <script type="text/javascript">
        $('a#reviewable-response-close').click(function() {
            $('div#reviewable-response').remove();
        });
    </script>
</div>
EOT;
        $wgOut->addHTML($finalResponse);
    }

    /**
     * Adds a form to make a page reviewable if it is in the correct namespace, and is already not reviewable.
     * Does not use the incoming parameters.
     * @param $out
     * @param $parseroutput
     */
    function addReviewForm(&$out, &$parseroutput) {
        global $wgRequest;
        global $wgUser;
        global $wgTitle;
        global $wgArticle;

        // Make sure this is even a reviewability situation
        // $namespace = $wgArticle->getTitle()->getNamespace();
        if(empty($wgArticle)
            || !$wgArticle->exists()
            || $wgArticle->getTitle()->isTalkPage()
            || $wgUser->getID() == 0) {
            return true;
        }

        // Handle the POST if necessary
        if($wgRequest->getVal('reviewable-hidden') == 'go') {
            PageReviews::handlePostBack();
        }

        // Display the form
        $url = $wgRequest->getFullRequestURL();

        // Get <option> list from review_score table
        $dbr = wfGetDB(DB_SLAVE);
        $res = $dbr->select('review_score', array('id', 'display_as'));
        $optionString = "";
        while($row = $dbr->fetchObject($res)) {
            $optionString .= "<option value=\"{$row->id}\">{$row->display_as}</option>";
        }
        $dbr->freeResult($res);

        // Get number of reviews for this page
        $res = $dbr->select('review', 'id', 'page_id=' . $wgArticle->getID());
        $numRows = $dbr->numRows($res);

        $buttonHtml = <<<EOT
<div id="PeerReview-PageReviews-outerbox" class="PeerReview-PageReviews-outer PeerReview-PageReviews-box">
    <span style="float: right;">[<a id="reviewable-toggle" href="#">Post Review</a>]</span>
    <b>{$numRows}</b> reviews
    <div id="PeerReview-PageReviews-form">
        <form action="{$url}" method="post">
            <input type="hidden" name="reviewable-hidden" value="go" />
            <p style="margin-bottom: 0em; font-weight: bold;">Comment:</p>
            <textarea name="reviewable-comment" id="PeerReview-PageReviews-comment"></textarea>
            <b>Score:</b>
            <select name="reviewable-review">
                <option value="-1">Select</option>
                {$optionString}
            </select>
            <button type="submit">Submit</button>
        </form>
    </div>
    <script type="text/javascript">
        $('a#reviewable-toggle').click(function() {
            if($('a#reviewable-toggle').html() == "Hide") {
                newText = "Post Review";
                $('div#PeerReview-PageReviews-outerbox').width(210);
            } else {
                newText = "Hide";
                $('div#PeerReview-PageReviews-outerbox').width(510);
            }
            $('div#PeerReview-PageReviews-form').toggle();
            $('a#reviewable-toggle').html(newText);
        });
    </script>
</div>
EOT;
        $out->addHTML($buttonHtml);
        return true;
    }
}
?>
