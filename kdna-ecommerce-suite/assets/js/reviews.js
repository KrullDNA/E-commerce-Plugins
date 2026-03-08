(function($) {
    'use strict';

    // Ensure comment form has enctype for file uploads
    $('#commentform').attr('enctype', 'multipart/form-data');

    // Voting
    $(document).on('click', '.kdna-vote-btn', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $wrapper = $btn.closest('.kdna-review-voting');
        var commentId = $wrapper.data('comment-id');
        var voteType = $btn.data('vote');

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
            }
        });
    });

    // Flagging
    $(document).on('click', '.kdna-flag-btn', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var commentId = $btn.data('comment-id');

        if (!confirm('Are you sure you want to report this review?')) {
            return;
        }

        var reason = prompt('Please provide a reason (optional):') || '';

        $.post(kdnaReviews.ajaxUrl, {
            action: 'kdna_review_flag',
            nonce: kdnaReviews.nonce,
            comment_id: commentId,
            reason: reason
        }, function(response) {
            if (response.success) {
                $btn.text('Reported').prop('disabled', true).css('color', '#999');
            } else if (response.data && response.data.message) {
                alert(response.data.message);
            }
        });
    });

})(jQuery);
