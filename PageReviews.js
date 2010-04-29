
function UserInGroup(group) {
    var username = wgUserName;
    var groups = wgUserGroups;
    for(var i = 0; i < groups.length; i++) {
        if(groups[i] == group)
            return true;
    }
    return false;
}

addOnloadHook(function () {
    if(
        (UserInGroup('Teachers') || UserInGroup('sysop')) &&
        ((wgCanonicalNamespace != "MediaWiki") && (wgCanonicalNamespace != "Special"))
    ) {
        addPortletLink('p-cactions', wgServer + wgScript + "?title=Special:PageOwner/" + wgPageName, 'ownership', 'ca-ownership', null, null);
    }
});

function AddPersonalLink(link, text, tag, ibefore) {
    var li = document.createElement( 'li' );
    li.id = tag;
    var a = document.createElement( 'a' );
    a.appendChild( document.createTextNode( text ) );
    a.href = link;
    li.appendChild( a );
    if (!ibefore) {
        // append to end (right) of list
        document.getElementById( 'pt-logout' ).parentNode.appendChild( li );
    } else {
        var before = document.getElementById( ibefore );
        before.appendChild( li, before );
    }
}

addOnloadHook(function() {
    AddPersonalLink(wgServer + wgScript + "?title=Special:MyReviews",
        'my reviews',
        'pt-myreviews',
        'pt-mytalk'
    );
});

$('a#reviewable-toggle').click(function() {
    newText = "Hide";
    if($('a#reviewable-toggle').html() == newText)
        newText = "Post Review";

    $('div#reviewable-main').toggle();
    $('a#reviewable-toggle').html(newText);
});

$('a#reviewable-response-close').click(function() {
    $('div#reviewable-response').remove();
});
