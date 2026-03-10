(function($) {
    'use strict';

    console.log('[KDNA Reviews] JS loaded');
    console.log('[KDNA Reviews] kdnaReviews localized data:', typeof kdnaReviews !== 'undefined' ? kdnaReviews : 'NOT DEFINED');
    console.log('[KDNA Reviews] kdnaReviewForm localized data:', typeof kdnaReviewForm !== 'undefined' ? kdnaReviewForm : 'NOT DEFINED');

    // Check if review elements exist on the page
    var $reviewWidget = $('.kdna-reviews-widget');
    var $voteButtons = $('.kdna-vote-btn');
    var $flagButtons = $('.kdna-flag-btn');
    console.log('[KDNA Reviews] .kdna-reviews-widget found:', $reviewWidget.length);
    console.log('[KDNA Reviews] .kdna-vote-btn found:', $voteButtons.length);
    console.log('[KDNA Reviews] .kdna-flag-btn found:', $flagButtons.length);

    // Ensure comment form has enctype for file uploads.
    // Use the form ID passed from PHP when available, fall back to common selectors.
    var formId = (typeof kdnaReviewForm !== 'undefined' && kdnaReviewForm.formId)
        ? '#' + kdnaReviewForm.formId
        : '#commentform';
    var $form = $(formId);
    console.log('[KDNA Reviews] Comment form selector:', formId, 'found:', $form.length);
    $form.attr('enctype', 'multipart/form-data');

    // Fallback: if the form wasn't found by ID, try the review form container.
    if (!$form.length) {
        var $fallbackForm = $('#review_form form, .comment-form, form.comment-form').first();
        console.log('[KDNA Reviews] Fallback form found:', $fallbackForm.length);
        $fallbackForm.attr('enctype', 'multipart/form-data');
    }

    // Voting
    $(document).on('click', '.kdna-vote-btn', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $wrapper = $btn.closest('.kdna-review-voting');
        var commentId = $wrapper.data('comment-id');
        var voteType = $btn.data('vote');

        console.log('[KDNA Reviews] Vote clicked:', { commentId: commentId, voteType: voteType });

        if ($btn.hasClass('kdna-vote-loading')) {
            console.log('[KDNA Reviews] Vote already loading, skipping');
            return;
        }
        $btn.addClass('kdna-vote-loading');

        if (typeof kdnaReviews === 'undefined') {
            console.error('[KDNA Reviews] kdnaReviews not defined — AJAX will fail');
            $btn.removeClass('kdna-vote-loading');
            return;
        }

        console.log('[KDNA Reviews] Sending AJAX vote to:', kdnaReviews.ajaxUrl);

        $.post(kdnaReviews.ajaxUrl, {
            action: 'kdna_review_vote',
            nonce: kdnaReviews.nonce,
            comment_id: commentId,
            vote_type: voteType
        }, function(response) {
            console.log('[KDNA Reviews] Vote response:', response);
            if (response.success) {
                $wrapper.find('.kdna-vote-up .count').text(response.data.positive);
                $wrapper.find('.kdna-vote-down .count').text(response.data.negative);
                $btn.toggleClass('active');
                $wrapper.find('.kdna-vote-btn').not($btn).removeClass('active');
            } else if (response.data && response.data.message) {
                console.warn('[KDNA Reviews] Vote failed:', response.data.message);
                alert(response.data.message);
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            console.error('[KDNA Reviews] Vote AJAX error:', textStatus, errorThrown);
            alert('Request failed. Please try again.');
        }).always(function() {
            $btn.removeClass('kdna-vote-loading');
        });
    });

    // Flagging
    $(document).on('click', '.kdna-flag-btn', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var commentId = $btn.data('comment-id');

        console.log('[KDNA Reviews] Flag clicked:', { commentId: commentId });

        if ($btn.prop('disabled')) {
            return;
        }

        if (!confirm('Are you sure you want to report this review?')) {
            return;
        }

        var reason = prompt('Please provide a reason (optional):') || '';

        $btn.prop('disabled', true);

        if (typeof kdnaReviews === 'undefined') {
            console.error('[KDNA Reviews] kdnaReviews not defined — AJAX will fail');
            $btn.prop('disabled', false);
            return;
        }

        console.log('[KDNA Reviews] Sending AJAX flag to:', kdnaReviews.ajaxUrl);

        $.post(kdnaReviews.ajaxUrl, {
            action: 'kdna_review_flag',
            nonce: kdnaReviews.nonce,
            comment_id: commentId,
            reason: reason
        }, function(response) {
            console.log('[KDNA Reviews] Flag response:', response);
            if (response.success) {
                $btn.text('Reported').css('color', '#999');
            } else if (response.data && response.data.message) {
                console.warn('[KDNA Reviews] Flag failed:', response.data.message);
                alert(response.data.message);
                $btn.prop('disabled', false);
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            console.error('[KDNA Reviews] Flag AJAX error:', textStatus, errorThrown);
            alert('Request failed. Please try again.');
            $btn.prop('disabled', false);
        });
    });

})(jQuery);
