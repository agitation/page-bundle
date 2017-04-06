ag.ns("ag.tool");

var slashTrim = function(string)
{
    return string.replace(/^\/+|\/+$/g, "");
};

ag.tool.createUrl = function(vPath, reqLang)
{
    var baseUrl = ag.cfg.baseUrl || "",
        langCode = reqLang;

    if (ag.cfg.languages)
    {
        ag.cfg.languages.forEach(function(lang){
            if (lang.id === reqLang || (!reqLang && lang.isCurrent)) {
                langCode = lang.isDefault ? "" : lang.id;
            }
        });
    }

    return ag.ui.tool.fmt.sprintf("%s/%s/%s", slashTrim(baseUrl), slashTrim(vPath), langCode);
};
