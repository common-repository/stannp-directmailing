jQuery( document ).ready(function($) {
    $("#stannp_account_dropdown").on('change', function () {
        var account_option = $('#stannp_account_dropdown').find(":selected").val();

        if (account_option) {
            if (account_option === '1') {
                $(".stannp-account-no").hide();
                $.each($('#stannp-account-no-form :input'), function(){
                    $(this).removeAttr('required');
                });
                $("#stannp_account_dropdown_submit").trigger("click");
            } else {
                $(".stannp-account-no").show();
                $("#stannp-register-terms").show();
                $.each($('#stannp-account-no-form :input'), function(){
                    $(this).attr('required');
                });
            }
        } else {
            $(".stannp-account-no").hide();
            // $(".stannp-account-yes").hide();
        }
    });

    $("#stannp_account_password2").blur(function(){
        $(this).get(0).setCustomValidity('');

        if(!(($(this).val()) === ($("#stannp_account_password").val()))) {
            $(this).get(0).setCustomValidity('Passwords must match.');
        }
    });
});