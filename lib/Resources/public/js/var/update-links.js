$(document).ready(function() {

var
    links = $("[rel=canonical], [rel=alternate]"),
    stateMgr;

    ag.srv("broker").sub("ag.state.change ag.state.update", function(state){
        stateMgr = stateMgr || ag.srv("state");

        var hash = stateMgr.createHash(state.path, state.request);

        links.each(function(){
            var
                link = $(this),
                href = link.attr("href").split("#")[0];

            link.attr("href", href + hash);
        });
    });
});
