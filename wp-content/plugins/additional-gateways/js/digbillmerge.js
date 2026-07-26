jQuery(function() {


    if(dig_billmerge.user_logged_in!=1 && dig_billmerge.merge==1) {
        var bp_wc = jQuery("#billing_phone");

        var bp_wc_val = bp_wc.val();

        bp_wc.attr({
            'only-mob' : 1,
            'value': '',
            'mob': 1,
            "id": 'username',
            'data-dig-main': 'billing_phone',
        }).parent().append('<input type="hidden" name="billing_phone" id="billing_phone" value="'+bp_wc_val+'" />');
    }
});