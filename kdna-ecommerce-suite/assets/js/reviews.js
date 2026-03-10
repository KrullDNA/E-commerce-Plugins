(function($) {
    'use strict';

    // ═══════════════════════════════════════════════════════════
    // KDNA DEBUG — Full diagnostic output
    // ═══════════════════════════════════════════════════════════

    console.group('%c[KDNA Debug] Full Diagnostics', 'color:#2271b1;font-weight:bold;font-size:13px');

    // 1. Check which KDNA stylesheets are loaded
    console.group('CSS Loaded');
    var sheets = document.querySelectorAll('link[rel="stylesheet"]');
    var kdnaSheets = [];
    sheets.forEach(function(s) {
        if (s.href && s.href.indexOf('kdna') !== -1) {
            kdnaSheets.push(s.href);
        }
    });
    if (kdnaSheets.length) {
        kdnaSheets.forEach(function(href) { console.log('✓', href); });
    } else {
        console.warn('✗ No KDNA stylesheets found in <head>');
    }
    console.groupEnd();

    // 2. Check which KDNA scripts are loaded
    console.group('JS Loaded');
    var scripts = document.querySelectorAll('script[src]');
    var kdnaScripts = [];
    scripts.forEach(function(s) {
        if (s.src && s.src.indexOf('kdna') !== -1) {
            kdnaScripts.push(s.src);
        }
    });
    if (kdnaScripts.length) {
        kdnaScripts.forEach(function(src) { console.log('✓', src); });
    } else {
        console.warn('✗ No KDNA scripts found (besides this one)');
    }
    console.groupEnd();

    // 3. Localized data
    console.group('Localized Data');
    console.log('kdnaReviews:', typeof kdnaReviews !== 'undefined' ? kdnaReviews : '✗ NOT DEFINED');
    console.log('kdnaReviewForm:', typeof kdnaReviewForm !== 'undefined' ? kdnaReviewForm : '✗ NOT DEFINED');
    console.groupEnd();

    // 4. Reviews widget DOM
    console.group('Reviews Widget DOM');
    var $reviewWidget = $('.kdna-reviews-widget');
    console.log('.kdna-reviews-widget:', $reviewWidget.length ? '✓ found ' + $reviewWidget.length : '✗ NOT FOUND');
    if ($reviewWidget.length) {
        console.log('  HTML (first 500 chars):', $reviewWidget[0].outerHTML.substring(0, 500));
        console.log('  .kdna-review-item:', $reviewWidget.find('.kdna-review-item').length);
        console.log('  .kdna-vote-btn:', $reviewWidget.find('.kdna-vote-btn').length);
        console.log('  .kdna-flag-btn:', $reviewWidget.find('.kdna-flag-btn').length);
        console.log('  .kdna-reviews-summary:', $reviewWidget.find('.kdna-reviews-summary').length);

        // Computed styles on vote buttons
        var $firstBtn = $reviewWidget.find('.kdna-vote-btn').first();
        if ($firstBtn.length) {
            var cs = window.getComputedStyle($firstBtn[0]);
            console.log('  Vote btn computed styles:', {
                padding: cs.padding,
                fontWeight: cs.fontWeight,
                background: cs.backgroundColor,
                border: cs.border,
                borderRadius: cs.borderRadius,
                cursor: cs.cursor
            });
        }

        // Computed styles on review title
        var $firstTitle = $reviewWidget.find('.kdna-review-item-title').first();
        if ($firstTitle.length) {
            var ts = window.getComputedStyle($firstTitle[0]);
            console.log('  Review title computed styles:', {
                fontWeight: ts.fontWeight,
                fontSize: ts.fontSize,
                color: ts.color,
                display: ts.display,
                tag: $firstTitle[0].tagName
            });
        }
    }
    console.groupEnd();

    // 5. Native WooCommerce reviews (what the page is actually using)
    console.group('Native WooCommerce Reviews');
    var $wcReviewTab = $('#tab-reviews, .woocommerce-Reviews');
    var $wcComments = $('.commentlist .comment, #comments .comment, .woocommerce-Reviews .comment');
    var $wcForm = $('#commentform, #review_form form');
    console.log('#tab-reviews / .woocommerce-Reviews:', $wcReviewTab.length ? '✓ found' : '✗ not found');
    console.log('Review comments (.comment):', $wcComments.length);
    console.log('Review form:', $wcForm.length ? '✓ found' : '✗ not found');
    if ($wcComments.length) {
        console.log('First comment HTML (300 chars):', $wcComments.first()[0].outerHTML.substring(0, 300));
    }
    // Check if KDNA enhancements are in the native form
    var $titleField = $('.kdna-review-title, .kdna-review-title-field, input[name="kdna_review_title"]');
    var $photoField = $('.kdna-upload-field, .kdna-review-photos, input[name="kdna_review_photos[]"]');
    console.log('KDNA title field in native form:', $titleField.length ? '✓ found' : '✗ not found');
    console.log('KDNA photo upload in native form:', $photoField.length ? '✓ found' : '✗ not found');
    // Check for KDNA voting in native comments
    var $nativeVoting = $('.comment .kdna-review-voting, .comment .kdna-vote-btn');
    console.log('KDNA voting in native comments:', $nativeVoting.length ? '✓ found ' + $nativeVoting.length : '✗ not found');
    console.groupEnd();

    // 6. Related Products widget DOM
    console.group('Related Products Widget DOM');
    var $relatedWidget = $('.kdna-related-products-widget');
    console.log('.kdna-related-products-widget:', $relatedWidget.length ? '✓ found ' + $relatedWidget.length : '✗ NOT FOUND');
    if ($relatedWidget.length) {
        var $grid = $relatedWidget.find('.kdna-related-grid');
        var $wooWrap = $grid.find('.woocommerce');
        var $products = $grid.find('li.product');
        var $loopItems = $grid.find('.kdna-related-grid-item');
        console.log('  .kdna-related-grid:', $grid.length ? '✓' : '✗');
        console.log('  .woocommerce wrapper:', $wooWrap.length ? '✓' : '✗');
        console.log('  li.product (WC default):', $products.length);
        console.log('  .kdna-related-grid-item (loop tpl):', $loopItems.length);
        console.log('  Grid HTML (500 chars):', $grid[0].outerHTML.substring(0, 500));

        // Computed styles on the grid
        var gs = window.getComputedStyle($grid[0]);
        console.log('  Grid computed styles:', {
            display: gs.display,
            gridTemplateColumns: gs.gridTemplateColumns,
            gap: gs.gap
        });

        // Check if .woocommerce wrapper has display:contents
        if ($wooWrap.length) {
            var ws = window.getComputedStyle($wooWrap[0]);
            console.log('  .woocommerce wrapper display:', ws.display, ws.display === 'contents' ? '✓ correct' : '✗ SHOULD BE contents');
        }

        // Check if ul.products has display:contents
        var $ulProducts = $grid.find('ul.products');
        if ($ulProducts.length) {
            var us = window.getComputedStyle($ulProducts[0]);
            console.log('  ul.products display:', us.display, us.display === 'contents' ? '✓ correct' : '✗ SHOULD BE contents');
        }

        // Check first product card styles
        if ($products.length) {
            var ps = window.getComputedStyle($products[0]);
            console.log('  First li.product styles:', {
                float: ps.float,
                width: ps.width,
                margin: ps.margin,
                padding: ps.padding,
                background: ps.backgroundColor,
                borderRadius: ps.borderRadius
            });
        }
    }
    // Also check for WooCommerce native related products
    var $wcRelated = $('section.related.products, .related.products');
    console.log('WC native related section:', $wcRelated.length ? '✓ found' : '✗ not found');
    console.groupEnd();

    // 7. Review Form Widget — Submit Button debug
    console.group('Review Form Widget (Submit Button)');
    var $formContainer = $('.kdna-review-form-container');
    console.log('.kdna-review-form-container:', $formContainer.length ? '✓ found' : '✗ NOT FOUND');
    if ($formContainer.length) {
        // Find the submit button via multiple selectors
        var $submitBtn = $formContainer.find('input[type="submit"], button[type="submit"], .form-submit input, #submit').first();
        console.log('Submit button found:', $submitBtn.length ? '✓' : '✗ NOT FOUND');
        if ($submitBtn.length) {
            var bs = window.getComputedStyle($submitBtn[0]);
            console.log('Submit button TAG:', $submitBtn[0].tagName, 'ID:', $submitBtn[0].id, 'CLASS:', $submitBtn[0].className);
            console.log('Submit button computed styles:', {
                padding: bs.padding,
                paddingTop: bs.paddingTop,
                paddingRight: bs.paddingRight,
                paddingBottom: bs.paddingBottom,
                paddingLeft: bs.paddingLeft,
                fontWeight: bs.fontWeight,
                fontSize: bs.fontSize,
                fontFamily: bs.fontFamily,
                color: bs.color,
                backgroundColor: bs.backgroundColor,
                border: bs.border,
                borderRadius: bs.borderRadius,
                textTransform: bs.textTransform,
                letterSpacing: bs.letterSpacing,
                display: bs.display,
                width: bs.width
            });
            // Check for inline styles that could override
            console.log('Submit button inline style attr:', $submitBtn[0].getAttribute('style') || '(none)');
        }

        // Check DOM structure around the button
        var $respond = $formContainer.find('#respond');
        var $preview = $formContainer.find('.kdna-review-form-preview');
        var $formSubmitP = $formContainer.find('.form-submit');
        console.log('#respond inside container:', $respond.length ? '✓' : '✗ (editor preview?)');
        console.log('.kdna-review-form-preview:', $preview.length ? '✓ (editor)' : '✗ (live form)');
        console.log('.form-submit wrapper:', $formSubmitP.length ? '✓' : '✗');

        // Dump all stylesheets rules targeting #submit or .submit to find competing rules
        try {
            var competingRules = [];
            for (var si = 0; si < document.styleSheets.length; si++) {
                try {
                    var rules = document.styleSheets[si].cssRules || document.styleSheets[si].rules;
                    if (!rules) continue;
                    for (var ri = 0; ri < rules.length; ri++) {
                        var r = rules[ri];
                        if (r.selectorText && (
                            r.selectorText.indexOf('#submit') !== -1 ||
                            r.selectorText.indexOf('input[type="submit"]') !== -1 ||
                            r.selectorText.indexOf("input[type='submit']") !== -1 ||
                            r.selectorText.indexOf('.form-submit') !== -1
                        )) {
                            var text = r.cssText;
                            if (text.indexOf('font-weight') !== -1 || text.indexOf('padding') !== -1) {
                                competingRules.push({
                                    sheet: document.styleSheets[si].href || '(inline)',
                                    rule: text.substring(0, 300)
                                });
                            }
                        }
                    }
                } catch(e) { /* cross-origin sheet, skip */ }
            }
            if (competingRules.length) {
                console.warn('CSS rules targeting submit button with padding/font-weight:');
                competingRules.forEach(function(cr) {
                    console.log('  Sheet:', cr.sheet);
                    console.log('  Rule:', cr.rule);
                });
            } else {
                console.log('No competing CSS rules found for submit button padding/font-weight');
            }
        } catch(e) {
            console.log('Could not scan stylesheets:', e.message);
        }
    }
    console.groupEnd();

    // 8. Elementor context
    console.group('Elementor Context');
    console.log('Elementor frontend loaded:', typeof elementorFrontend !== 'undefined' ? '✓' : '✗');
    console.log('Elementor editor mode:', document.body.classList.contains('elementor-editor-active') ? '✓ YES' : '✗ NO (frontend)');
    var $elWidgets = $('[data-widget_type*="kdna"]');
    console.log('Elementor KDNA widgets on page:', $elWidgets.length);
    $elWidgets.each(function() {
        console.log('  →', $(this).attr('data-widget_type'), this);
    });
    console.groupEnd();

    console.groupEnd(); // End KDNA Debug

    // ═══════════════════════════════════════════════════════════
    // Functional code below
    // ═══════════════════════════════════════════════════════════

    // Ensure comment form has enctype for file uploads.
    var formId = (typeof kdnaReviewForm !== 'undefined' && kdnaReviewForm.formId)
        ? '#' + kdnaReviewForm.formId
        : '#commentform';
    $(formId).attr('enctype', 'multipart/form-data');
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

        console.log('[KDNA Reviews] Vote clicked:', { commentId: commentId, voteType: voteType, button: this });

        if ($btn.hasClass('kdna-vote-loading')) {
            return;
        }
        $btn.addClass('kdna-vote-loading');

        if (typeof kdnaReviews === 'undefined') {
            console.error('[KDNA Reviews] kdnaReviews not defined — cannot send AJAX');
            $btn.removeClass('kdna-vote-loading');
            return;
        }

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
            console.error('[KDNA Reviews] kdnaReviews not defined — cannot send AJAX');
            $btn.prop('disabled', false);
            return;
        }

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
