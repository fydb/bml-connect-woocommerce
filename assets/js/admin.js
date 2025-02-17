jQuery(document).ready(function($) {
    // Handle date range picker
    $('.bml-connect-reports input[type="date"]').on('change', function() {
        var startDate = $('input[name="start_date"]').val();
        var endDate = $('input[name="end_date"]').val();
        
        if (startDate && endDate && startDate > endDate) {
            alert(bmlConnect.i18n.invalidDateRange);
            $(this).val('');
        }
    });
    
    // Handle API key visibility toggle
    $('.bml-connect-settings .toggle-password').on('click', function(e) {
        e.preventDefault();
        var input = $(this).prev('input');
        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            $(this).text(bmlConnect.i18n.hideKey);
        } else {
            input.attr('type', 'password');
            $(this).text(bmlConnect.i18n.showKey);
        }
    });
    
    // Handle test mode warning
    $('#woocommerce_bml_connect_testmode').on('change', function() {
        if ($(this).is(':checked')) {
            alert(bmlConnect.i18n.testModeWarning);
        }
    });
    
    // Handle transaction status refresh
    $('.bml-connect-refresh-status').on('click', function(e) {
        e.preventDefault();
        var button = $(this);
        var transactionId = button.data('transaction');
        
        button.prop('disabled', true).text(bmlConnect.i18n.refreshing);
        
        $.ajax({
            url: bmlConnect.ajaxUrl,
            type: 'POST',
            data: {
                action: 'bml_connect_refresh_status',
                transaction_id: transactionId,
                nonce: bmlConnect.nonce
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || bmlConnect.i18n.refreshError);
                }
            },
            error: function() {
                alert(bmlConnect.i18n.refreshError);
            },
            complete: function() {
                button.prop('disabled', false).text(bmlConnect.i18n.refresh);
            }
        });
    });
});