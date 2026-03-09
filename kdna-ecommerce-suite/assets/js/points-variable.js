/**
 * Dynamic points update for variable products.
 *
 * Listens to WooCommerce's found_variation / reset_data events and
 * recalculates the points displayed in the .kdna-points-message based
 * on the selected variation's price.
 */
(function ($) {
    'use strict';

    if (typeof kdna_points_var === 'undefined') {
        return;
    }

    var $form = $('form.variations_form');
    if (!$form.length) {
        return;
    }

    var $messages = $('.kdna-points-message');
    if (!$messages.length) {
        return;
    }

    // Store original HTML for each message element.
    var originals = [];
    $messages.each(function () {
        originals.push($(this).html());
    });
    var earnPoints = parseFloat(kdna_points_var.earn_points) || 1;
    var earnMonetary = parseFloat(kdna_points_var.earn_monetary) || 1;
    var roundingMode = kdna_points_var.rounding_mode || 'round';
    var messageTemplate = kdna_points_var.variable_message;
    var singleMessageTemplate = kdna_points_var.single_message;
    var pointsLabel = kdna_points_var.points_label;
    var pointsLabelSingular = kdna_points_var.points_label_singular;
    var taxSetting = kdna_points_var.tax_setting || 'inclusive';

    function roundPoints(raw) {
        switch (roundingMode) {
            case 'floor':
                return Math.floor(raw);
            case 'ceil':
                return Math.ceil(raw);
            default:
                return Math.round(raw);
        }
    }

    function calcPoints(price) {
        return roundPoints(price * (earnPoints / earnMonetary));
    }

    function getLabel(count) {
        return count === 1 ? pointsLabelSingular : pointsLabel;
    }

    function replaceMessage(template, points) {
        return template
            .replace(/\{points\}/g, points)
            .replace(/\{points_label\}/g, getLabel(points));
    }

    // When a variation is selected.
    $form.on('found_variation', function (event, variation) {
        var price = 0;

        if (taxSetting === 'exclusive') {
            // Use price excluding tax if available.
            price = parseFloat(variation.display_price) || 0;
            // WooCommerce's display_price is the price as displayed (tax-inclusive or exclusive
            // depending on store settings). We use display_price_excluding_tax when available.
            if (typeof variation.price_excluding_tax !== 'undefined') {
                price = parseFloat(variation.price_excluding_tax) || price;
            }
        } else {
            price = parseFloat(variation.display_price) || 0;
        }

        if (price > 0) {
            var points = calcPoints(price);
            var html = replaceMessage(singleMessageTemplate, points);
            $messages.each(function () {
                var $el = $(this);
                // If the widget has a .kdna-points-text span, update only that.
                var $text = $el.find('.kdna-points-text');
                if ($text.length) {
                    $text.html(html);
                } else {
                    $el.html(html);
                }
            });
        }
    });

    // When variation is reset / cleared.
    $form.on('reset_data', function () {
        $messages.each(function (i) {
            $(this).html(originals[i]);
        });
    });
})(jQuery);
