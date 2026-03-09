(function($) {
    'use strict';

    // Apply Points to Previous Orders.
    $('#kdna-apply-previous-orders').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $spinner = $('#kdna-apply-previous-spinner');
        var $result = $('#kdna-apply-previous-result');
        var since = $('#kdna-apply-previous-since').val();

        if ( ! confirm( kdna_admin.confirm_apply_points || 'Are you sure? This will award points to all qualifying previous orders.' ) ) {
            return;
        }

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');
        $result.text('');

        $.post(kdna_admin.ajax_url, {
            action: 'kdna_apply_previous_orders',
            nonce: kdna_admin.nonce,
            since: since
        }, function(response) {
            $spinner.removeClass('is-active');
            $btn.prop('disabled', false);
            if (response.success) {
                $result.css('color', '#00a32a').text(response.data);
            } else {
                $result.css('color', '#d63638').text(response.data || 'Error');
            }
        }).fail(function() {
            $spinner.removeClass('is-active');
            $btn.prop('disabled', false);
            $result.css('color', '#d63638').text('Request failed.');
        });
    });

})(jQuery);
