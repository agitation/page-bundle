ag.ns("ag.tool");

var slashTrim = function(string)
{
    return string.replace(/\/+/g, "/").replace(/^\/|\/$/g, "");
};

ag.tool.createUrl = function(vPath, reqLang)
{
    var base = ag.cfg.baseUrl || "/",
        langCode = reqLang,
        parts = vPath.split("#");

    if (ag.cfg.languages)
    {
        ag.cfg.languages.forEach(function(lang){
            if (lang.id === reqLang || (!reqLang && lang.isCurrent)) {
                langCode = lang.isDefault ? "" : lang.id;
            }
        });
    }

    return base + slashTrim(parts[0] + "/" + langCode + (parts[1] ? "#" + parts[1] : ""));
};
