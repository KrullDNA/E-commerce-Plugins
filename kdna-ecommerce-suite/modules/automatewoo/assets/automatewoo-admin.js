/**
 * KDNA AutomateWoo - Admin JavaScript
 * Handles the workflow editor UI: trigger selection, rule builder, action builder, timing, and variables.
 */
(function ($) {
    'use strict';

    if (typeof kdnaAW === 'undefined') {
        return;
    }

    var WorkflowEditor = {

        triggerOptions: {},
        currentActions: [],
        ruleGroupCount: 0,

        init: function () {
            this.cacheDom();
            this.bindEvents();
            this.loadExistingData();
            this.initSortable();
        },

        cacheDom: function () {
            this.$editor      = $('#kdna-aw-workflow-editor');
            this.$trigger      = this.$editor.find('.kdna-aw-trigger-select');
            this.$triggerOpts  = this.$editor.find('.kdna-aw-trigger-options');
            this.$rulesWrap    = this.$editor.find('.kdna-aw-rules-wrap');
            this.$actionsWrap  = this.$editor.find('.kdna-aw-actions-wrap');
            this.$timingType   = this.$editor.find('.kdna-aw-timing-type');
            this.$timingDelay  = this.$editor.find('.kdna-aw-timing-delay');
            this.$timingUnit   = this.$editor.find('.kdna-aw-timing-unit');
            this.$timingSched  = this.$editor.find('.kdna-aw-timing-scheduled');
        },

        bindEvents: function () {
            var self = this;

            // Trigger selection.
            this.$trigger.on('change', function () {
                self.onTriggerChange($(this).val());
            });

            // Add rule.
            this.$editor.on('click', '.kdna-aw-add-rule', function (e) {
                e.preventDefault();
                var $group = $(this).closest('.kdna-aw-rule-group');
                self.addRule($group);
            });

            // Add rule group (OR).
            this.$editor.on('click', '.kdna-aw-add-rule-group', function (e) {
                e.preventDefault();
                self.addRuleGroup();
            });

            // Remove rule.
            this.$editor.on('click', '.kdna-aw-remove-rule', function (e) {
                e.preventDefault();
                var $row = $(this).closest('.kdna-aw-rule-row');
                var $group = $row.closest('.kdna-aw-rule-group');
                $row.remove();
                if ($group.find('.kdna-aw-rule-row').length === 0) {
                    $group.next('.kdna-aw-or-divider').remove();
                    $group.remove();
                }
            });

            // Remove rule group.
            this.$editor.on('click', '.kdna-aw-remove-rule-group', function (e) {
                e.preventDefault();
                var $group = $(this).closest('.kdna-aw-rule-group');
                $group.next('.kdna-aw-or-divider').remove();
                $group.remove();
            });

            // Add action.
            this.$editor.on('click', '.kdna-aw-add-action', function (e) {
                e.preventDefault();
                self.addAction();
            });

            // Toggle action body.
            this.$editor.on('click', '.kdna-aw-action-header', function () {
                $(this).closest('.kdna-aw-action-item').toggleClass('open');
            });

            // Remove action.
            this.$editor.on('click', '.kdna-aw-remove-action', function (e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).closest('.kdna-aw-action-item').slideUp(200, function () {
                    $(this).remove();
                });
            });

            // Action type change.
            this.$editor.on('change', '.kdna-aw-action-type-select', function () {
                var $item = $(this).closest('.kdna-aw-action-item');
                self.onActionTypeChange($item, $(this).val());
            });

            // Timing type change.
            this.$timingType.on('change', function () {
                var val = $(this).val();
                self.$timingDelay.closest('.kdna-aw-timing-delay-wrap').toggle(val === 'delayed');
                self.$timingSched.toggleClass('visible', val === 'scheduled');
            });

            // Rule compare type change.
            this.$editor.on('change', '.kdna-aw-rule-compare', function () {
                var $row = $(this).closest('.kdna-aw-rule-row');
                var compare = $(this).val();
                $row.find('.kdna-aw-rule-value').toggle(compare !== 'is_empty' && compare !== 'is_not_empty');
            });

            // Variables inserter.
            this.$editor.on('click', '.kdna-aw-variables-btn', function (e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).find('.kdna-aw-variables-dropdown').toggleClass('open');
            });

            this.$editor.on('click', '.kdna-aw-variable-item', function (e) {
                e.stopPropagation();
                var variable = $(this).data('variable');
                var $action  = $(this).closest('.kdna-aw-action-item');
                var $target  = $action.find('.kdna-aw-variable-target');

                if ($target.length) {
                    var pos = $target[0].selectionStart || $target.val().length;
                    var val = $target.val();
                    $target.val(val.substring(0, pos) + variable + val.substring(pos));
                    $target.focus();
                }

                $(this).closest('.kdna-aw-variables-dropdown').removeClass('open');
            });

            // Close dropdowns on outside click.
            $(document).on('click', function () {
                $('.kdna-aw-variables-dropdown').removeClass('open');
            });

            // Rule name change → update compare options.
            this.$editor.on('change', '.kdna-aw-rule-name', function () {
                var $row  = $(this).closest('.kdna-aw-rule-row');
                var rule  = $(this).val();
                self.updateRuleCompareOptions($row, rule);
            });
        },

        loadExistingData: function () {
            if (!kdnaAW.workflow) {
                return;
            }

            var data = kdnaAW.workflow;

            // Set trigger.
            if (data.trigger) {
                this.$trigger.val(data.trigger).trigger('change');
                if (data.trigger_options) {
                    $.each(data.trigger_options, function (key, val) {
                        var $field = this.$triggerOpts.find('[name="workflow_trigger_options[' + key + ']"]');
                        if ($field.length) {
                            $field.val(val);
                        }
                    }.bind(this));
                }
            }

            // Set rules.
            if (data.rules && data.rules.length) {
                this.$rulesWrap.find('.kdna-aw-rule-group').remove();
                this.$rulesWrap.find('.kdna-aw-or-divider').remove();
                for (var g = 0; g < data.rules.length; g++) {
                    var $group = this.addRuleGroup();
                    for (var r = 0; r < data.rules[g].length; r++) {
                        var ruleData = data.rules[g][r];
                        var $row = this.addRule($group);
                        $row.find('.kdna-aw-rule-name').val(ruleData.name);
                        this.updateRuleCompareOptions($row, ruleData.name);
                        $row.find('.kdna-aw-rule-compare').val(ruleData.compare);
                        $row.find('.kdna-aw-rule-value').val(ruleData.value);
                    }
                }
            }

            // Set actions.
            if (data.actions && data.actions.length) {
                for (var a = 0; a < data.actions.length; a++) {
                    var $action = this.addAction();
                    $action.find('.kdna-aw-action-type-select').val(data.actions[a].type).trigger('change');
                    $.each(data.actions[a].options || {}, function (key, val) {
                        $action.find('[name*="[' + key + ']"]').val(val);
                    });
                }
            }

            // Set timing.
            if (data.timing) {
                this.$timingType.val(data.timing.type).trigger('change');
                if (data.timing.delay) {
                    this.$timingDelay.val(data.timing.delay);
                }
                if (data.timing.unit) {
                    this.$timingUnit.val(data.timing.unit);
                }
            }
        },

        initSortable: function () {
            this.$actionsWrap.sortable({
                handle: '.move-handle',
                items: '.kdna-aw-action-item',
                placeholder: 'kdna-aw-action-placeholder',
                tolerance: 'pointer',
                update: function () {
                    // Re-index action names.
                    this.$actionsWrap.find('.kdna-aw-action-item').each(function (i) {
                        $(this).find('[name^="workflow_actions["]').each(function () {
                            var name = $(this).attr('name');
                            $(this).attr('name', name.replace(/workflow_actions\[\d+\]/, 'workflow_actions[' + i + ']'));
                        });
                    });
                }.bind(this)
            });
        },

        onTriggerChange: function (trigger) {
            this.$triggerOpts.html('').removeClass('active');

            if (!trigger || !kdnaAW.triggers || !kdnaAW.triggers[trigger]) {
                return;
            }

            var config = kdnaAW.triggers[trigger];

            if (config.options && config.options.length) {
                var html = '';
                for (var i = 0; i < config.options.length; i++) {
                    var opt = config.options[i];
                    html += '<div class="form-field">';
                    html += '<label>' + opt.label + '</label>';
                    if (opt.type === 'select') {
                        html += '<select name="workflow_trigger_options[' + opt.name + ']">';
                        for (var v = 0; v < opt.choices.length; v++) {
                            html += '<option value="' + opt.choices[v].value + '">' + opt.choices[v].label + '</option>';
                        }
                        html += '</select>';
                    } else if (opt.type === 'multiselect') {
                        html += '<select name="workflow_trigger_options[' + opt.name + '][]" multiple style="min-height:80px;">';
                        for (var m = 0; m < opt.choices.length; m++) {
                            html += '<option value="' + opt.choices[m].value + '">' + opt.choices[m].label + '</option>';
                        }
                        html += '</select>';
                    } else {
                        html += '<input type="' + (opt.type || 'text') + '" name="workflow_trigger_options[' + opt.name + ']" />';
                    }
                    if (opt.description) {
                        html += '<p class="description">' + opt.description + '</p>';
                    }
                    html += '</div>';
                }
                this.$triggerOpts.html(html).addClass('active');
            }

            // Update available rules based on trigger data items.
            this.updateAvailableRules(config.data_items || []);
        },

        updateAvailableRules: function (dataItems) {
            // Store for use when adding rules.
            this.availableDataItems = dataItems;
        },

        addRuleGroup: function () {
            this.ruleGroupCount++;
            var groupIndex = this.ruleGroupCount;

            if (this.$rulesWrap.find('.kdna-aw-rule-group').length > 0) {
                this.$rulesWrap.append(
                    '<div class="kdna-aw-or-divider"><span>' + (kdnaAW.i18n.or || 'OR') + '</span></div>'
                );
            }

            var html = '<div class="kdna-aw-rule-group" data-group="' + groupIndex + '">' +
                '<div class="kdna-aw-rule-group-header">' +
                '<h4>' + (kdnaAW.i18n.rule_group || 'Rule Group') + ' ' + groupIndex + '</h4>' +
                '<button type="button" class="button-link kdna-aw-remove-rule-group">' +
                '<span class="dashicons dashicons-no-alt"></span></button>' +
                '</div>' +
                '<div class="kdna-aw-rule-rows"></div>' +
                '<button type="button" class="button kdna-aw-add-rule">' +
                '<span class="dashicons dashicons-plus-alt2" style="vertical-align:text-bottom;"></span> ' +
                (kdnaAW.i18n.add_rule || 'Add Rule') + '</button>' +
                '</div>';

            var $group = $(html);
            this.$rulesWrap.append($group);

            // Add an initial rule row.
            this.addRule($group);

            return $group;
        },

        addRule: function ($group) {
            var groupIndex = $group.data('group');
            var ruleIndex  = $group.find('.kdna-aw-rule-row').length;

            var ruleOptions = this.getRuleOptions();
            var compareOptions = this.getCompareOptions();

            var html = '<div class="kdna-aw-rule-row">' +
                '<select class="kdna-aw-rule-name" name="workflow_rules[' + groupIndex + '][' + ruleIndex + '][name]">' +
                '<option value="">' + (kdnaAW.i18n.select_rule || '— Select Rule —') + '</option>' +
                ruleOptions +
                '</select>' +
                '<select class="kdna-aw-rule-compare" name="workflow_rules[' + groupIndex + '][' + ruleIndex + '][compare]">' +
                compareOptions +
                '</select>' +
                '<input type="text" class="kdna-aw-rule-value" name="workflow_rules[' + groupIndex + '][' + ruleIndex + '][value]" placeholder="Value" />' +
                '<button type="button" class="kdna-aw-remove-rule" title="Remove"><span class="dashicons dashicons-no-alt"></span></button>' +
                '</div>';

            var $row = $(html);
            $group.find('.kdna-aw-rule-rows').append($row);
            return $row;
        },

        getRuleOptions: function () {
            if (!kdnaAW.rules) {
                return '';
            }

            var html = '';
            $.each(kdnaAW.rules, function (group, rules) {
                html += '<optgroup label="' + group + '">';
                $.each(rules, function (key, label) {
                    html += '<option value="' + key + '">' + label + '</option>';
                });
                html += '</optgroup>';
            });
            return html;
        },

        getCompareOptions: function () {
            var compares = kdnaAW.compares || {
                'is': 'is',
                'is_not': 'is not',
                'greater_than': 'greater than',
                'less_than': 'less than',
                'contains': 'contains',
                'not_contains': 'does not contain',
                'starts_with': 'starts with',
                'ends_with': 'ends with',
                'is_empty': 'is empty',
                'is_not_empty': 'is not empty',
                'matches_any': 'matches any of',
                'matches_none': 'matches none of'
            };

            var html = '';
            $.each(compares, function (val, label) {
                html += '<option value="' + val + '">' + label + '</option>';
            });
            return html;
        },

        updateRuleCompareOptions: function ($row, rule) {
            // Some rules have specific compare types.
            if (!kdnaAW.ruleCompares || !kdnaAW.ruleCompares[rule]) {
                return;
            }

            var $compare = $row.find('.kdna-aw-rule-compare');
            var current  = $compare.val();
            var html     = '';

            $.each(kdnaAW.ruleCompares[rule], function (val, label) {
                html += '<option value="' + val + '">' + label + '</option>';
            });

            $compare.html(html);
            if ($compare.find('option[value="' + current + '"]').length) {
                $compare.val(current);
            }
        },

        addAction: function () {
            var index = this.$actionsWrap.find('.kdna-aw-action-item').length;

            var actionOptions = this.getActionOptions();

            var html = '<div class="kdna-aw-action-item open">' +
                '<div class="kdna-aw-action-header">' +
                '<h4>' + (kdnaAW.i18n.action || 'Action') + ' ' + (index + 1) + '</h4>' +
                '<div class="kdna-aw-action-controls">' +
                '<button type="button" class="move-handle" title="Reorder"><span class="dashicons dashicons-move"></span></button>' +
                '<button type="button" class="kdna-aw-remove-action" title="Remove"><span class="dashicons dashicons-no-alt"></span></button>' +
                '</div>' +
                '</div>' +
                '<div class="kdna-aw-action-body">' +
                '<div class="form-field">' +
                '<label>' + (kdnaAW.i18n.action_type || 'Action Type') + '</label>' +
                '<select class="kdna-aw-action-type-select" name="workflow_actions[' + index + '][type]">' +
                '<option value="">' + (kdnaAW.i18n.select_action || '— Select Action —') + '</option>' +
                actionOptions +
                '</select>' +
                '</div>' +
                '<div class="kdna-aw-action-fields"></div>' +
                '</div>' +
                '</div>';

            var $item = $(html);
            this.$actionsWrap.append($item);
            return $item;
        },

        getActionOptions: function () {
            if (!kdnaAW.actions) {
                return '';
            }

            var html = '';
            $.each(kdnaAW.actions, function (group, actions) {
                html += '<optgroup label="' + group + '">';
                $.each(actions, function (key, label) {
                    html += '<option value="' + key + '">' + label + '</option>';
                });
                html += '</optgroup>';
            });
            return html;
        },

        onActionTypeChange: function ($item, actionType) {
            var $fields = $item.find('.kdna-aw-action-fields');
            $fields.empty();

            if (!actionType || !kdnaAW.actionFields || !kdnaAW.actionFields[actionType]) {
                return;
            }

            var fields = kdnaAW.actionFields[actionType];
            var index  = $item.index();
            var variablesBtn = this.buildVariablesButton();

            for (var i = 0; i < fields.length; i++) {
                var f    = fields[i];
                var name = 'workflow_actions[' + index + '][' + f.name + ']';
                var html = '<div class="form-field">';
                html += '<label>' + f.label + '</label>';

                switch (f.type) {
                    case 'text':
                    case 'email':
                    case 'url':
                    case 'number':
                        html += '<input type="' + f.type + '" name="' + name + '" value="' + (f.default || '') + '" class="regular-text kdna-aw-variable-target" />';
                        break;
                    case 'textarea':
                        html += variablesBtn;
                        html += '<textarea name="' + name + '" class="large-text kdna-aw-variable-target" rows="6">' + (f.default || '') + '</textarea>';
                        break;
                    case 'select':
                        html += '<select name="' + name + '">';
                        if (f.choices) {
                            for (var c = 0; c < f.choices.length; c++) {
                                var sel = f.choices[c].value === (f.default || '') ? ' selected' : '';
                                html += '<option value="' + f.choices[c].value + '"' + sel + '>' + f.choices[c].label + '</option>';
                            }
                        }
                        html += '</select>';
                        break;
                    case 'wysiwyg':
                        html += variablesBtn;
                        html += '<textarea name="' + name + '" class="large-text kdna-aw-variable-target" rows="10">' + (f.default || '') + '</textarea>';
                        break;
                    case 'template_select':
                        html += '<select name="' + name + '">';
                        html += '<option value="">' + (kdnaAW.i18n.no_template || '— No Template —') + '</option>';
                        if (kdnaAW.emailTemplates) {
                            $.each(kdnaAW.emailTemplates, function (id, title) {
                                html += '<option value="' + id + '">' + title + '</option>';
                            });
                        }
                        html += '</select>';
                        break;
                }

                if (f.description) {
                    html += '<p class="description">' + f.description + '</p>';
                }

                html += '</div>';
                $fields.append(html);
            }

            // Update header title.
            var $select = $item.find('.kdna-aw-action-type-select');
            var label   = $select.find('option:selected').text();
            $item.find('.kdna-aw-action-header h4').text(label || (kdnaAW.i18n.action || 'Action') + ' ' + ($item.index() + 1));
        },

        buildVariablesButton: function () {
            if (!kdnaAW.variables) {
                return '';
            }

            var html = '<div class="kdna-aw-variables-btn button button-small" style="margin-bottom:6px;">' +
                '<span class="dashicons dashicons-editor-code" style="vertical-align:text-bottom;"></span> ' +
                (kdnaAW.i18n.insert_variable || 'Insert Variable') +
                '<div class="kdna-aw-variables-dropdown">';

            $.each(kdnaAW.variables, function (group, vars) {
                html += '<div class="kdna-aw-variables-group">';
                html += '<h5>' + group + '</h5>';
                $.each(vars, function (key, label) {
                    html += '<div class="kdna-aw-variable-item" data-variable="{{ ' + key + ' }}">' +
                        '<span>' + label + '</span>' +
                        '<code>{{ ' + key + ' }}</code>' +
                        '</div>';
                });
                html += '</div>';
            });

            html += '</div></div>';
            return html;
        }
    };

    $(document).ready(function () {
        if ($('#kdna-aw-workflow-editor').length) {
            WorkflowEditor.init();
        }

        // Queue page: bulk actions.
        $('.kdna-aw-queue-cancel').on('click', function (e) {
            e.preventDefault();
            if (!confirm(kdnaAW.i18n.confirm_cancel || 'Cancel this queued event?')) {
                return;
            }
            var id = $(this).data('id');
            var $row = $(this).closest('tr');
            $.post(kdnaAW.ajaxUrl, {
                action: 'kdna_aw_cancel_queue_item',
                id: id,
                _wpnonce: kdnaAW.nonce
            }, function (response) {
                if (response.success) {
                    $row.fadeOut(300, function () { $(this).remove(); });
                }
            });
        });

        // Logs page: expand detail.
        $('.kdna-aw-log-expand').on('click', function (e) {
            e.preventDefault();
            $(this).closest('tr').next('.kdna-aw-log-detail-row').toggle();
        });

        // Abandoned carts: delete.
        $('.kdna-aw-cart-delete').on('click', function (e) {
            e.preventDefault();
            if (!confirm(kdnaAW.i18n.confirm_delete || 'Delete this cart?')) {
                return;
            }
            var id = $(this).data('id');
            var $row = $(this).closest('tr');
            $.post(kdnaAW.ajaxUrl, {
                action: 'kdna_aw_delete_cart',
                id: id,
                _wpnonce: kdnaAW.nonce
            }, function (response) {
                if (response.success) {
                    $row.fadeOut(300, function () { $(this).remove(); });
                }
            });
        });
    });

})(jQuery);
