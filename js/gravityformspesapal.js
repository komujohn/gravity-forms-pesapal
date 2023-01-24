/**
 * Created by komu on 5/16/17.
 */
jQuery(document).ready(function ($) {
    var currency_selector = $('.currency_trigger').find('select');
    var selected_curency = $('.currency_trigger').find('select :selected').val();
    set_gf_curency_config(selected_curency);
    console.log(selected_curency);
    currency_selector.on('change', function (e) {
        var selected_curency = $('.currency_trigger').find('select :selected').val();
        console.log(selected_curency);
        set_gf_curency_config(selected_curency);
        //get the amount

    });
    console.log(gf_global);

    function set_gf_curency_config(currency) {

        if (currency == 'KES') {
            var gf_currency_config = {
                "name": "Kenya Shilling",
                "symbol_left": "KES",
                "symbol_right": "",
                "symbol_padding": "",
                "thousand_separator": ",",
                "decimal_separator": ".",
                "decimals": 2
            };

        }
        else{
            var gf_currency_config = {
                "name": "U.S. Dollar",
                "symbol_left": "$",
                "symbol_right": "",
                "symbol_padding": "",
                "thousand_separator": ",",
                "decimal_separator": ".",
                "decimals": 2
            };
        }
        return gf_global.gf_currency_config=gf_currency_config;
    }//end set_gf_curency_config
});