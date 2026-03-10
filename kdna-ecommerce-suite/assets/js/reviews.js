(function($) {
    'use strict';

    // Ensure comment form has enctype for file uploads.
    // Use the form ID passed from PHP when available, fall back to common selectors.
    var formId = (typeof kdnaReviewForm !== 'undefined' && kdnaReviewForm.formId)
        ? '#' + kdnaReviewForm.formId
        : '#commentform';
    $(formId).attr('enctype', 'multipart/form-data');

    // Fallback: if the form wasn't found by ID, try the review form container.
    if (!$(formId).length) {
        $('#review_form form, .comment-form, form.comment-form').first().attr('enctype', 'multipart/form-data');
    }

    // Voting
    $(document).on('click', '.kdna-vote-btn', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $wrapper = $btn.closest('.kdna-review-voting');
        var commentId = $wrapper.data('comment-id');
        var voteType = $btn.data('vote');

        if ($btn.hasClass('kdna-vote-loading')) {
            return;
        }
        $btn.addClass('kdna-vote-loading');

        $.post(kdnaReviews.ajaxUrl, {
            action: 'kdna_review_vote',
            nonce: kdnaReviews.nonce,
            comment_id: commentId,
            vote_type: voteType
        }, function(response) {
            if (response.success) {
                $wrapper.find('.kdna-vote-up .count').text(response.data.positive);
                $wrapper.find('.kdna-vote-down .count').text(response.data.negative);
                $btn.toggleClass('active');
                $wrapper.find('.kdna-vote-btn').not($btn).removeClass('active');
            } else if (response.data && response.data.message) {
                alert(response.data.message);
            }
        }).fail(function() {
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

        if ($btn.prop('disabled')) {
            return;
        }

        if (!confirm('Are you sure you want to report this review?')) {
            return;
        }

        var reason = prompt('Please provide a reason (optional):') || '';

        $btn.prop('disabled', true);

        $.post(kdnaReviews.ajaxUrl, {
            action: 'kdna_review_flag',
            nonce: kdnaReviews.nonce,
            comment_id: commentId,
            reason: reason
        }, function(response) {
            if (response.success) {
                $btn.text('Reported').css('color', '#999');
            } else if (response.data && response.data.message) {
                alert(response.data.message);
                $btn.prop('disabled', false);
            }
        }).fail(function() {
            alert('Request failed. Please try again.');
            $btn.prop('disabled', false);
        });
    });

})(jQuery);
