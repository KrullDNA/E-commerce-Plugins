/* KDNA Smart Coupons - Frontend JS */
(function($) {
    'use strict';

    $(document).on('click', '.kdna-sc-apply-btn', function(e) {
        e.preventDefault();

        var $btn    = $(this);
        var code    = $btn.data('coupon');
        var nonce   = $btn.data('nonce');
        var origText = $btn.text();

        $btn.prop('disabled', true).text(kdna_sc.applying);

        $.ajax({
            url: kdna_sc.ajax_url,
            type: 'POST',
            data: {
                action: 'kdna_sc_apply_coupon',
                coupon_code: code,
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    $btn.text(kdna_sc.applied)
                        .closest('.kdna-sc-coupon-card')
                        .addClass('kdna-sc-applied');

                    // Replace button with badge.
                    $btn.replaceWith(
                        '<span class="kdna-sc-applied-badge">' + kdna_sc.applied + '</span>'
                    );

                    // Refresh cart fragments if available.
                    if (typeof wc_cart_fragments_params !== 'undefined') {
                        $(document.body).trigger('wc_fragment_refresh');
                    }

                    // Reload page after a brief delay for cart/checkout pages.
                    setTimeout(function() {
                        location.reload();
                    }, 800);
                } else {
                    $btn.prop('disabled', false).text(origText);
                    if (response.data && response.data.message) {
                        alert(response.data.message);
                    }
                }
            },
            error: function() {
                $btn.prop('disabled', false).text(origText);
            }
        });
    });

})(jQuery);
