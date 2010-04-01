<?php
class PageOwner extends SpecialPage {
    function __construct() {
        parent::__construct( 'PageOwner' );
        wfLoadExtensionMessages('PageOwner');
    }

    function execute( $par ) {
        global $wgRequest, $wgOut;
        global $wgScriptPath;

        $this->setHeaders();
        $html = <<<EOT
<h2 style="clear: both;">Ownership of Page <a href="{$wgScriptPath}/{$par}"<{$par}</a></h2>
<div style="width: 45%; border: 1px solid #000000; background-color: #F8F8F8; padding: 5px; float: left;">
    Stuff on the left
</div>
<div style="width: 45%; border: 1px solid #000000; background-color: #F8F8F8; padding: 5px; float: right;">
    Stuff on the right
</div>
EOT;
        $wgOut->addHTML($html);
        return true;
    }
}

