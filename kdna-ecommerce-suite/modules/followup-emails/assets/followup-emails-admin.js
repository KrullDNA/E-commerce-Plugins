/**
 * KDNA Emails - Admin JavaScript
 * Handles the email editor UI: type selection, trigger config, delay, merge tags, coupon config,
 * exclusions, test email, queue management, reports, and subscriber management.
 */
(function ($) {
    'use strict';

    if (typeof kdnaFUE === 'undefined') {
        return;
    }

    /* ============================================================
       Email Editor
       ============================================================ */
    var EmailEditor = {

        init: function () {
            this.cacheDom();
            this.bindEvents();
            this.loadExistingData();
        },

        cacheDom: function () {
            this.$editor       = $('#kdna-fue-email-editor');
            this.$typeCards     = this.$editor.find('.kdna-fue-type-card');
            this.$typeInput     = this.$editor.find('input[name="fue_email_type"]');
            this.$triggerConfig = this.$editor.find('.kdna-fue-trigger-config');
            this.$couponToggle  = this.$editor.find('#fue_include_coupon');
            this.$couponConfig  = this.$editor.find('.kdna-fue-coupon-config');
            this.$testBtn       = this.$editor.find('.kdna-fue-send-test');
            this.$testEmail     = this.$editor.find('.kdna-fue-test-email-input');
        },

        bindEvents: function () {
            var self = this;

            // Email type selection.
            this.$typeCards.on('click', function () {
                self.$typeCards.removeClass('selected');
                $(this).addClass('selected');
                var type = $(this).data('type');
                self.$typeInput.val(type);
                self.onTypeChange(type);
            });

            // Trigger subtype change.
            this.$editor.on('change', '.kdna-fue-trigger-subtype', function () {
                self.onTriggerSubtypeChange($(this).val());
            });

            // Coupon toggle.
            this.$couponToggle.on('change', function () {
                self.$couponConfig.toggle($(this).is(':checked'));
            });

            // Merge tags inserter.
            this.$editor.on('click', '.kdna-fue-merge-tags-btn', function (e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).find('.kdna-fue-merge-tags-dropdown').toggleClass('open');
            });

            this.$editor.on('click', '.kdna-fue-merge-tag-item', function (e) {
                e.stopPropagation();
                var tag     = $(this).data('tag');
                var $target = self.getActiveEditor();

                if ($target && $target.length) {
                    if ($target.is('textarea') || $target.is('input')) {
                        var pos = $target[0].selectionStart || $target.val().length;
                        var val = $target.val();
                        $target.val(val.substring(0, pos) + tag + val.substring(pos));
                        $target.focus();
                    }
                }

                $(this).closest('.kdna-fue-merge-tags-dropdown').removeClass('open');
            });

            // Close dropdowns.
            $(document).on('click', function () {
                $('.kdna-fue-merge-tags-dropdown').removeClass('open');
            });

            // Test email.
            this.$testBtn.on('click', function (e) {
                e.preventDefault();
                self.sendTestEmail();
            });

            // Exclusion add.
            this.$editor.on('click', '.kdna-fue-add-exclusion', function (e) {
                e.preventDefault();
                self.addExclusion();
            });

            // Exclusion remove.
            this.$editor.on('click', '.remove-exclusion', function (e) {
                e.preventDefault();
                $(this).closest('li').slideUp(200, function () { $(this).remove(); });
            });

            // Template change - load template and show/hide content editor.
            this.$editor.on('change', '.kdna-fue-template-select', function () {
                var val = $(this).val();
                self.$editor.find('.kdna-fue-content-editor').toggle(!val);
                self.onTemplateChange(val);
            });
        },

        loadExistingData: function () {
            if (!kdnaFUE.email) {
                return;
            }

            var data = kdnaFUE.email;

            // Set type.
            if (data.type) {
                this.$typeCards.filter('[data-type="' + data.type + '"]').trigger('click');
            }

            // Set coupon.
            if (data.include_coupon === 'yes') {
                this.$couponToggle.prop('checked', true).trigger('change');
            }

            // Load template preview if a template is already selected.
            var selectedTemplate = this.$editor.find('.kdna-fue-template-select').val();
            if (selectedTemplate) {
                this.onTemplateChange(selectedTemplate);
            }
        },

        onTypeChange: function (type) {
            // Show/hide trigger-specific options.
            this.$triggerConfig.find('.kdna-fue-trigger-section').hide();
            this.$triggerConfig.find('.kdna-fue-trigger-section[data-type="' + type + '"]').show();
            this.$triggerConfig.show();

            // Show/hide sections based on type.
            var showCoupon = ['purchase', 're_engagement', 'signup'].indexOf(type) !== -1;
            this.$editor.find('.kdna-fue-coupon-section').toggle(showCoupon);
        },

        onTriggerSubtypeChange: function (subtype) {
            // Show/hide subtype-specific fields.
            this.$triggerConfig.find('.kdna-fue-subtype-fields').hide();
            this.$triggerConfig.find('.kdna-fue-subtype-fields[data-subtype="' + subtype + '"]').show();
        },

        getActiveEditor: function () {
            // Find the focused textarea/input, or fall back to the main content editor.
            var $focused = this.$editor.find('textarea:focus, input[type="text"]:focus');
            if ($focused.length) {
                return $focused;
            }
            return this.$editor.find('.kdna-fue-content-textarea');
        },

        sendTestEmail: function () {
            var email = this.$testEmail.val();
            if (!email) {
                alert(kdnaFUE.i18n.enter_email || 'Please enter an email address.');
                return;
            }

            var $btn = this.$testBtn;
            $btn.prop('disabled', true).text(kdnaFUE.i18n.sending || 'Sending...');

            var postId = this.$editor.find('input[name="post_ID"]').val() || kdnaFUE.postId || 0;

            $.post(kdnaFUE.ajaxUrl, {
                action: 'kdna_fue_send_test_email',
                post_id: postId,
                email: email,
                _wpnonce: kdnaFUE.nonce
            }, function (response) {
                $btn.prop('disabled', false).text(kdnaFUE.i18n.send_test || 'Send Test');
                if (response.success) {
                    alert(kdnaFUE.i18n.test_sent || 'Test email sent successfully!');
                } else {
                    alert(response.data || kdnaFUE.i18n.test_failed || 'Failed to send test email.');
                }
            }).fail(function () {
                $btn.prop('disabled', false).text(kdnaFUE.i18n.send_test || 'Send Test');
                alert(kdnaFUE.i18n.test_failed || 'Failed to send test email.');
            });
        },

        onTemplateChange: function (templateId) {
            var $hiddenField = this.$editor.find('.kdna-fue-selected-template-id');
            var $preview     = this.$editor.find('.kdna-fue-template-preview');
            var $previewFrame = this.$editor.find('.kdna-fue-template-preview-frame');

            // Store the selected template ID in the hidden field.
            $hiddenField.val(templateId);

            if (!templateId) {
                $preview.hide();
                $previewFrame.empty();
                return;
            }

            // Load the template via the email builder AJAX endpoint.
            $previewFrame.html('<p>' + (kdnaFUE.i18n.loading_template || 'Loading template...') + '</p>');
            $preview.show();

            $.post(kdnaFUE.ajaxUrl, {
                action: 'kdna_email_builder_get_template',
                template_id: templateId,
                _wpnonce: kdnaFUE.nonce
            }, function (response) {
                if (response.success && response.data) {
                    var html = response.data.compiled_html || response.data.html || '';
                    if (html) {
                        $previewFrame.html(
                            '<iframe class="kdna-fue-template-preview-iframe" ' +
                            'style="width:100%;min-height:400px;border:1px solid #ddd;background:#fff;" ' +
                            'sandbox="allow-same-origin"></iframe>'
                        );
                        var iframe = $previewFrame.find('iframe')[0];
                        var iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
                        iframeDoc.open();
                        iframeDoc.write(html);
                        iframeDoc.close();
                    } else {
                        $previewFrame.html('<p>' + (response.data.title || 'Template selected.') + '</p>');
                    }
                } else {
                    $previewFrame.html('<p style="color:#a00;">' + (kdnaFUE.i18n.template_load_failed || 'Failed to load template preview.') + '</p>');
                }
            }).fail(function () {
                $previewFrame.html('<p style="color:#a00;">' + (kdnaFUE.i18n.template_load_failed || 'Failed to load template preview.') + '</p>');
            });
        },

        addExclusion: function () {
            var $typeSelect = this.$editor.find('.kdna-fue-exclusion-type');
            var $valueInput = this.$editor.find('.kdna-fue-exclusion-value');
            var type  = $typeSelect.val();
            var value = $valueInput.val();

            if (!type || !value) {
                return;
            }

            var label = $typeSelect.find('option:selected').text() + ': ' + value;
            var $list = this.$editor.find('.kdna-fue-exclusion-list');
            var count = $list.find('li').length;

            $list.append(
                '<li>' +
                '<span>' + $('<span>').text(label).html() + '</span>' +
                '<input type="hidden" name="fue_exclusions[' + count + '][type]" value="' + type + '" />' +
                '<input type="hidden" name="fue_exclusions[' + count + '][value]" value="' + $('<span>').text(value).html() + '" />' +
                '<button type="button" class="remove-exclusion"><span class="dashicons dashicons-no-alt"></span></button>' +
                '</li>'
            );

            $valueInput.val('');
        }
    };

    /* ============================================================
       Queue Page
       ============================================================ */
    var QueuePage = {

        init: function () {
            this.bindEvents();
        },

        bindEvents: function () {
            // Cancel queued email.
            $(document).on('click', '.kdna-fue-queue-cancel', function (e) {
                e.preventDefault();
                if (!confirm(kdnaFUE.i18n.confirm_cancel || 'Cancel this scheduled email?')) {
                    return;
                }
                var id   = $(this).data('id');
                var $row = $(this).closest('tr');
                $.post(kdnaFUE.ajaxUrl, {
                    action: 'kdna_fue_cancel_queue_item',
                    id: id,
                    _wpnonce: kdnaFUE.nonce
                }, function (response) {
                    if (response.success) {
                        $row.fadeOut(300, function () { $(this).remove(); });
                    }
                });
            });

            // Reschedule.
            $(document).on('click', '.kdna-fue-queue-reschedule', function (e) {
                e.preventDefault();
                var id   = $(this).data('id');
                var $row = $(this).closest('tr');
                var newDate = prompt(kdnaFUE.i18n.reschedule_prompt || 'Enter new date (YYYY-MM-DD HH:MM):');
                if (!newDate) {
                    return;
                }
                $.post(kdnaFUE.ajaxUrl, {
                    action: 'kdna_fue_reschedule_queue_item',
                    id: id,
                    date: newDate,
                    _wpnonce: kdnaFUE.nonce
                }, function (response) {
                    if (response.success) {
                        $row.find('.kdna-fue-scheduled-date').text(newDate);
                    } else {
                        alert(response.data || 'Error');
                    }
                });
            });
        }
    };

    /* ============================================================
       Reports Page
       ============================================================ */
    var ReportsPage = {

        init: function () {
            this.bindEvents();
            this.initDatePickers();
        },

        bindEvents: function () {
            // Date range filter.
            $(document).on('click', '.kdna-fue-report-filter', function (e) {
                e.preventDefault();
                var from = $('input[name="report_from"]').val();
                var to   = $('input[name="report_to"]').val();
                var url  = window.location.href.split('?')[0];
                var params = new URLSearchParams(window.location.search);
                params.set('report_from', from);
                params.set('report_to', to);
                window.location.href = url + '?' + params.toString();
            });

            // Export.
            $(document).on('click', '.kdna-fue-export-report', function (e) {
                e.preventDefault();
                var from = $('input[name="report_from"]').val();
                var to   = $('input[name="report_to"]').val();
                window.location.href = kdnaFUE.ajaxUrl + '?action=kdna_fue_export_report&from=' + from + '&to=' + to + '&_wpnonce=' + kdnaFUE.nonce;
            });
        },

        initDatePickers: function () {
            if ($.fn.datepicker) {
                $('.kdna-fue-datepicker').datepicker({
                    dateFormat: 'yy-mm-dd',
                    maxDate: 0
                });
            }
        }
    };

    /* ============================================================
       Subscribers Page
       ============================================================ */
    var SubscribersPage = {

        init: function () {
            this.bindEvents();
        },

        bindEvents: function () {
            // Toggle import form.
            $(document).on('click', '.kdna-fue-toggle-import', function (e) {
                e.preventDefault();
                $('.kdna-fue-import-form').toggleClass('open');
            });

            // Import CSV.
            $(document).on('submit', '.kdna-fue-import-form form', function (e) {
                e.preventDefault();
                var formData = new FormData(this);
                formData.append('action', 'kdna_fue_import_subscribers');
                formData.append('_wpnonce', kdnaFUE.nonce);

                var $btn = $(this).find('button[type="submit"]');
                $btn.prop('disabled', true).text(kdnaFUE.i18n.importing || 'Importing...');

                $.ajax({
                    url: kdnaFUE.ajaxUrl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function (response) {
                        $btn.prop('disabled', false).text(kdnaFUE.i18n.import || 'Import');
                        if (response.success) {
                            alert(response.data.message || 'Import complete.');
                            window.location.reload();
                        } else {
                            alert(response.data || 'Import failed.');
                        }
                    },
                    error: function () {
                        $btn.prop('disabled', false).text(kdnaFUE.i18n.import || 'Import');
                        alert('Import failed.');
                    }
                });
            });

            // Export subscribers.
            $(document).on('click', '.kdna-fue-export-subscribers', function (e) {
                e.preventDefault();
                window.location.href = kdnaFUE.ajaxUrl + '?action=kdna_fue_export_subscribers&_wpnonce=' + kdnaFUE.nonce;
            });

            // Delete subscriber.
            $(document).on('click', '.kdna-fue-delete-subscriber', function (e) {
                e.preventDefault();
                if (!confirm(kdnaFUE.i18n.confirm_delete || 'Delete this subscriber?')) {
                    return;
                }
                var id   = $(this).data('id');
                var $row = $(this).closest('tr');
                $.post(kdnaFUE.ajaxUrl, {
                    action: 'kdna_fue_delete_subscriber',
                    id: id,
                    _wpnonce: kdnaFUE.nonce
                }, function (response) {
                    if (response.success) {
                        $row.fadeOut(300, function () { $(this).remove(); });
                    }
                });
            });

            // Resend to subscriber.
            $(document).on('click', '.kdna-fue-resend-subscriber', function (e) {
                e.preventDefault();
                var id = $(this).data('id');
                $.post(kdnaFUE.ajaxUrl, {
                    action: 'kdna_fue_resend_to_subscriber',
                    id: id,
                    _wpnonce: kdnaFUE.nonce
                }, function (response) {
                    if (response.success) {
                        alert(kdnaFUE.i18n.resent || 'Email queued for resending.');
                    } else {
                        alert(response.data || 'Error');
                    }
                });
            });
        }
    };

    /* ============================================================
       Initialize
       ============================================================ */
    $(document).ready(function () {
        if ($('#kdna-fue-email-editor').length) {
            EmailEditor.init();
        }
        if ($('.kdna-fue-queue-table').length) {
            QueuePage.init();
        }
        if ($('.kdna-fue-report-page').length) {
            ReportsPage.init();
        }
        if ($('.kdna-fue-subscribers-page').length) {
            SubscribersPage.init();
        }
    });

})(jQuery);
