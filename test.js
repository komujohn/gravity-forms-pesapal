if (typeof gf_global == 'undefined') var gf_global = {
    "gf_currency_config": {
        "name": "U.S. Dollar",
        "symbol_left": "$",
        "symbol_right": "",
        "symbol_padding": "",
        "thousand_separator": ",",
        "decimal_separator": ".",
        "decimals": 2
    },
    "base_url": "http:\/\/localhost\/2017\/wordpress\/wp-content\/plugins\/gravityforms",
    "number_formats": [],
    "spinnerUrl": "http:\/\/localhost\/2017\/wordpress\/wp-content\/plugins\/gravityforms\/images\/spinner.gif"
};
jQuery(document).bind('gform_post_render', function (event, formId, currentPage) {
    if (formId == 1) {
        gf_global["number_formats"][1] = {
            "9": {"price": false, "value": false},
            "1": {"price": false, "value": false},
            "2": {"price": false, "value": false},
            "3": {"price": false, "value": false},
            "8": {"price": false, "value": false},
            "10": {"price": "decimal_dot", "value": false}
        };
        if (window["gformInitPriceFields"]) jQuery(document).ready(function () {
            gformInitPriceFields();
        });
        if (!/(android)/i.test(navigator.userAgent)) {
            jQuery('#input_1_3').mask('(999) 999-9999').bind('keypress', function (e) {
                if (e.which == 13) {
                    jQuery(this).blur();
                }
            });
        }
    }
});
jQuery(document).bind('gform_post_conditional_logic', function (event, formId, fields, isInit) {
});