/**
 * KDNA Email Template Builder
 * Drag-and-drop email template editor with live preview.
 */
(function ($) {
    'use strict';

    var Builder = {
        el: null,
        nonce: '',
        ajaxUrl: '',
        templateId: 0,
        structure: null,
        blocks: {},
        selectedRow: null,
        selectedBlock: null,

        init: function () {
            this.el = $('#kdna-email-builder');
            if (!this.el.length) return;

            this.nonce = this.el.data('nonce');
            this.ajaxUrl = this.el.data('ajax-url');
            this.templateId = parseInt(this.el.data('template-id')) || 0;
            this.blocks = this.el.data('blocks') || {};

            try {
                this.structure = JSON.parse(this.el.attr('data-json'));
            } catch (e) {
                this.structure = { settings: {}, rows: [] };
            }

            if (!this.structure || !this.structure.settings) {
                this.structure = { settings: this.getDefaultSettings(), rows: [] };
            }

            this.render();
            this.bindEvents();
        },

        getDefaultSettings: function () {
            return {
                width: 600,
                bg_color: '#f7f7f7',
                content_bg_color: '#ffffff',
                font_family: "'Helvetica Neue', Helvetica, Arial, sans-serif",
                font_size: '14px',
                line_height: '1.5',
                text_color: '#333333',
                link_color: '#0073aa',
                heading_color: '#1a1a1a',
                padding: '0px',
                border_radius: '0px',
                preheader: ''
            };
        },

        render: function () {
            var html = '';

            // Sidebar
            html += '<div class="kdna-etb-sidebar">';
            html += '<div class="kdna-etb-tabs">';
            html += '<button class="kdna-etb-tab active" data-panel="blocks">Blocks</button>';
            html += '<button class="kdna-etb-tab" data-panel="settings">Design</button>';
            html += '<button class="kdna-etb-tab" data-panel="block-settings">Block</button>';
            html += '</div>';

            // Blocks Panel
            html += '<div class="kdna-etb-panel active" data-panel="blocks">';
            html += '<div class="kdna-etb-block-grid">';
            for (var type in this.blocks) {
                var b = this.blocks[type];
                html += '<div class="kdna-etb-block-item" data-type="' + type + '" draggable="true">';
                html += '<span class="dashicons ' + (b.icon || 'dashicons-editor-paragraph') + '"></span>';
                html += '<span>' + (b.label || type) + '</span>';
                html += '</div>';
            }
            html += '</div></div>';

            // Design Settings Panel
            html += '<div class="kdna-etb-panel" data-panel="settings">';
            html += this.renderDesignSettings();
            html += '</div>';

            // Block Settings Panel
            html += '<div class="kdna-etb-panel" data-panel="block-settings">';
            html += '<p style="color:#999;text-align:center;padding:20px;">Select a block to edit its settings.</p>';
            html += '</div>';

            html += '</div>'; // sidebar end

            // Canvas
            html += '<div class="kdna-etb-canvas">';

            // Toolbar
            html += '<div class="kdna-etb-toolbar">';
            html += '<input type="text" class="kdna-etb-name" value="' + this.escAttr(this.el.data('template-name') || 'Untitled Template') + '" placeholder="Template name" />';
            html += '<div class="kdna-etb-device-toggle">';
            html += '<button class="kdna-etb-device-btn active" data-device="desktop" title="Desktop"><span class="dashicons dashicons-desktop"></span></button>';
            html += '<button class="kdna-etb-device-btn" data-device="mobile" title="Mobile"><span class="dashicons dashicons-smartphone"></span></button>';
            html += '</div>';
            html += '<button class="button kdna-etb-preview-btn">Preview</button>';
            html += '<button class="button button-primary kdna-etb-save-btn">Save</button>';
            html += '</div>';

            // Email Frame
            html += '<div class="kdna-etb-email-frame" style="max-width:' + parseInt(this.structure.settings.width) + 'px;">';
            html += '<div class="kdna-etb-email-body" style="background:' + (this.structure.settings.content_bg_color || '#fff') + ';padding:' + (this.structure.settings.padding || '0px') + ';">';
            html += this.renderRows();
            html += '</div></div>';

            // Add Row button
            html += '<button class="kdna-etb-add-row"><span class="dashicons dashicons-plus-alt2"></span> Add Row</button>';

            html += '</div>'; // canvas end

            this.el.html(html);
            this.applyFullbleedMargins();
            this.initSortable();
            this.initColorPickers();
        },

        renderRows: function () {
            if (!this.structure.rows || !this.structure.rows.length) {
                return '<div class="kdna-etb-drop-zone">Drag blocks here to start building your email</div>';
            }

            var html = '<div class="kdna-etb-drop-zone has-blocks">';
            for (var i = 0; i < this.structure.rows.length; i++) {
                html += this.renderRow(i, this.structure.rows[i]);
            }
            html += '</div>';
            return html;
        },

        renderRow: function (index, row) {
            var rowStyle = '';
            var blocks = row.blocks || [];

            // Apply row-level background color if the single block is a blank_row
            var rowClass = 'kdna-etb-row';
            if (blocks.length === 1 && blocks[0].type === 'blank_row') {
                var brBg = blocks[0].props.bg_color || '#f7f7f7';
                rowStyle = ' style="background:' + brBg + ';"';
                rowClass += ' kdna-etb-row-fullbleed';
            }

            var html = '<div class="' + rowClass + '" data-index="' + index + '"' + rowStyle + '>';
            html += '<div class="kdna-etb-row-actions">';
            html += '<button class="kdna-etb-row-action move" title="Move"><span class="dashicons dashicons-move"></span></button>';
            html += '<button class="kdna-etb-row-action duplicate" title="Duplicate"><span class="dashicons dashicons-admin-page"></span></button>';
            html += '<button class="kdna-etb-row-action delete" title="Delete"><span class="dashicons dashicons-trash"></span></button>';
            html += '</div>';

            for (var j = 0; j < blocks.length; j++) {
                html += this.renderBlockPreview(blocks[j], index, j);
            }

            if (!blocks.length) {
                html += '<div style="padding:20px;text-align:center;color:#ccc;font-size:13px;">Empty row</div>';
            }

            html += '</div>';
            return html;
        },

        renderBlockPreview: function (block, rowIndex, blockIndex) {
            var type = block.type || 'text';
            var p = block.props || {};
            var pad = p.padding || '10px 20px';
            var html = '<div class="kdna-etb-block" data-row="' + rowIndex + '" data-block="' + blockIndex + '" data-type="' + type + '">';

            switch (type) {
                case 'text':
                    html += '<div style="padding:' + pad + ';text-align:' + (p.text_align || 'left') + ';">' + (p.content || '<p>Text block</p>') + '</div>';
                    break;
                case 'heading':
                    var tag = p.tag || 'h2';
                    html += '<' + tag + ' style="padding:' + (p.padding || '10px 20px') + ';text-align:' + (p.text_align || 'center') + ';margin:0;font-size:' + (p.font_size || '24px') + ';">' + this.escHtml(p.content || 'Heading') + '</' + tag + '>';
                    break;
                case 'image':
                    if (p.src) {
                        html += '<div style="padding:' + pad + ';text-align:' + (p.text_align || 'center') + ';"><img src="' + this.escAttr(p.src) + '" style="width:' + (p.width || '100%') + ';max-width:100%;" /></div>';
                    } else {
                        html += '<div style="padding:20px;text-align:center;background:#f0f0f1;border:1px dashed #c3c4c7;border-radius:4px;margin:10px 20px;"><span class="dashicons dashicons-format-image" style="font-size:32px;color:#ccc;"></span><br><small style="color:#999;">Click to add image</small></div>';
                    }
                    break;
                case 'button':
                    html += '<div style="padding:' + (p.container_padding || '10px 20px') + ';text-align:' + (p.text_align || 'center') + ';"><span style="display:inline-block;background:' + (p.bg_color || '#0073aa') + ';color:' + (p.text_color || '#fff') + ';padding:' + (p.padding || '12px 24px') + ';border-radius:' + (p.border_radius || '4px') + ';font-size:' + (p.font_size || '16px') + ';font-weight:' + (p.font_weight || 'bold') + ';">' + this.escHtml(p.text || 'Button') + '</span></div>';
                    break;
                case 'divider':
                    html += '<div style="padding:' + (p.padding || '10px 20px') + ';"><hr style="border:none;border-top:' + (p.thickness || '1px') + ' ' + (p.style || 'solid') + ' ' + (p.color || '#e0e0e0') + ';margin:0;" /></div>';
                    break;
                case 'spacer':
                    html += '<div class="kdna-etb-spacer-inner" style="height:' + (p.height || '20px') + ';min-height:10px;background:repeating-linear-gradient(45deg,transparent,transparent 5px,#f8f8f8 5px,#f8f8f8 10px);"></div>';
                    break;
                case 'social':
                    html += '<div style="padding:' + (p.padding || '10px 20px') + ';text-align:' + (p.text_align || 'center') + ';">';
                    var icons = p.icons || ['facebook', 'twitter', 'instagram'];
                    for (var si = 0; si < icons.length; si++) {
                        html += '<span style="display:inline-block;margin:0 4px;padding:6px 10px;background:#e0e0e0;border-radius:4px;font-size:11px;">' + icons[si] + '</span>';
                    }
                    html += '</div>';
                    break;
                case 'html':
                    html += '<div style="padding:' + (p.padding || '10px 20px') + ';background:#f9f9f9;border:1px dashed #ddd;"><code style="font-size:11px;color:#666;">&lt;/&gt; Custom HTML</code></div>';
                    break;
                case 'footer':
                    html += '<div style="padding:' + (p.padding || '20px') + ';background:' + (p.bg_color || '#f7f7f7') + ';text-align:' + (p.text_align || 'center') + ';font-size:12px;color:#999;">' + (p.content || 'Footer content') + '</div>';
                    break;
                case 'coupon':
                    html += '<div style="padding:' + (p.padding || '10px 20px') + ';"><div style="border:2px dashed ' + (p.border_color || '#0073aa') + ';background:' + (p.bg_color || '#f0f9ff') + ';border-radius:8px;padding:15px;text-align:center;"><span style="font-family:monospace;font-size:' + (p.code_font_size || '20px') + ';font-weight:bold;">' + this.escHtml(p.code_variable || '{coupon_code}') + '</span></div></div>';
                    break;
                case 'product':
                    html += '<div style="padding:' + (p.padding || '10px 20px') + ';text-align:center;border:1px dashed #ddd;border-radius:4px;"><span class="dashicons dashicons-cart" style="font-size:24px;color:#ccc;"></span><br><small style="color:#999;">Product Card</small></div>';
                    break;
                case 'order_items':
                    html += '<div style="padding:' + (p.padding || '10px 20px') + ';text-align:center;border:1px dashed #ddd;border-radius:4px;"><span class="dashicons dashicons-list-view" style="font-size:24px;color:#ccc;"></span><br><small style="color:#999;">Order Items Table</small></div>';
                    break;
                case 'logo':
                    if (p.src) {
                        html += '<div style="padding:' + (p.padding || '20px') + ';text-align:' + (p.text_align || 'center') + ';"><img src="' + this.escAttr(p.src) + '" style="width:' + (p.width || '150px') + ';" /></div>';
                    } else {
                        html += '<div style="padding:20px;text-align:center;"><span class="dashicons dashicons-store" style="font-size:32px;color:#ccc;"></span><br><small style="color:#999;">Logo</small></div>';
                    }
                    break;
                case 'menu':
                    var items = p.items || [{ label: 'Link' }];
                    var sep = p.separator || ' | ';
                    html += '<div style="padding:' + (p.padding || '10px 20px') + ';text-align:' + (p.text_align || 'center') + ';font-size:' + (p.font_size || '13px') + ';">';
                    var links = [];
                    for (var mi = 0; mi < items.length; mi++) {
                        links.push('<a href="#" style="text-decoration:none;">' + this.escHtml(items[mi].label || 'Link') + '</a>');
                    }
                    html += links.join(sep);
                    html += '</div>';
                    break;
                case 'video':
                    html += '<div style="padding:' + (p.padding || '10px 20px') + ';text-align:center;"><div style="background:#000;color:#fff;border-radius:4px;padding:30px;font-size:24px;">&#9654;</div></div>';
                    break;
                case 'columns':
                    html += '<div style="padding:' + (p.padding || '10px 20px') + ';display:flex;gap:' + (p.gap || '10px') + ';">';
                    var cols = parseInt(p.columns) || 2;
                    for (var ci = 0; ci < cols; ci++) {
                        html += '<div style="flex:1;border:1px dashed #ddd;border-radius:4px;padding:10px;min-height:40px;text-align:center;">';
                        var colBlocks = (p.col_blocks && p.col_blocks[ci]) ? p.col_blocks[ci] : [];
                        if (colBlocks.length) {
                            for (var cbi = 0; cbi < colBlocks.length; cbi++) {
                                html += this.renderBlockPreview(colBlocks[cbi], rowIndex, blockIndex + '.' + ci + '.' + cbi);
                            }
                        } else {
                            html += '<small style="color:#ccc;">Col ' + (ci + 1) + '</small>';
                        }
                        html += '</div>';
                    }
                    html += '</div>';
                    break;
                case 'blank_row':
                    html += '<div class="kdna-etb-blankrow-inner" style="width:' + (p.width || '100%') + ';height:' + (p.height || '40px') + ';min-height:20px;background:' + (p.bg_color || '#f7f7f7') + ';padding:' + (p.padding || '0px') + ';box-sizing:border-box;"></div>';
                    break;
                case 'content':
                    html += '<div style="padding:' + (p.padding || '10px 20px') + ';border:1px dashed #c3c4c7;border-radius:4px;background:#f0f8ff;">';
                    html += '<div style="text-align:center;padding:15px 10px;">';
                    html += '<span class="dashicons dashicons-email-alt" style="font-size:28px;color:#0073aa;"></span>';
                    html += '<p style="margin:8px 0 4px;font-weight:600;color:#333;">Email Content</p>';
                    html += '<small style="color:#666;">Personalised email body content<br>(Hi {customer_first_name}, ...)</small>';
                    html += '</div></div>';
                    break;
                default:
                    html += '<div style="padding:10px 20px;color:#999;">Unknown block: ' + type + '</div>';
            }

            html += '</div>';
            return html;
        },

        renderDesignSettings: function () {
            var s = this.structure.settings || {};
            var html = '';

            html += '<div class="kdna-etb-settings-group"><h4>Email Size</h4>';
            html += this.field('number', 'width', 'Width (px)', s.width || 600);
            html += '</div>';

            html += '<div class="kdna-etb-settings-group"><h4>Colors</h4>';
            html += this.colorField('bg_color', 'Background', s.bg_color || '#f7f7f7');
            html += this.colorField('content_bg_color', 'Content BG', s.content_bg_color || '#ffffff');
            html += this.colorField('text_color', 'Text', s.text_color || '#333333');
            html += this.colorField('link_color', 'Links', s.link_color || '#0073aa');
            html += this.colorField('heading_color', 'Headings', s.heading_color || '#1a1a1a');
            html += '</div>';

            html += '<div class="kdna-etb-settings-group"><h4>Typography</h4>';
            html += this.field('select', 'font_family', 'Font Family', s.font_family || "'Helvetica Neue'", {
                "'Helvetica Neue', Helvetica, Arial, sans-serif": 'Helvetica/Arial',
                "Georgia, 'Times New Roman', serif": 'Georgia/Times',
                "'Courier New', Courier, monospace": 'Courier',
                "Verdana, Geneva, sans-serif": 'Verdana',
                "Tahoma, Geneva, sans-serif": 'Tahoma'
            });
            html += this.field('text', 'font_size', 'Font Size', s.font_size || '14px');
            html += this.field('text', 'line_height', 'Line Height', s.line_height || '1.5');
            html += '</div>';

            html += '<div class="kdna-etb-settings-group"><h4>Layout</h4>';
            html += this.field('text', 'padding', 'Outer Padding', s.padding || '20px');
            html += this.field('text', 'border_radius', 'Border Radius', s.border_radius || '0px');
            html += '</div>';

            html += '<div class="kdna-etb-settings-group"><h4>Preheader</h4>';
            html += this.field('text', 'preheader', 'Preview Text', s.preheader || '', null, 'Text shown in email client before opening');
            html += '</div>';

            return html;
        },

        renderBlockSettings: function (block) {
            var type = block.type || 'text';
            var p = block.props || {};
            var html = '<div class="kdna-etb-settings-group"><h4>' + (this.blocks[type] ? this.blocks[type].label : type) + ' Settings</h4>';

            switch (type) {
                case 'text':
                    html += this.field('textarea', 'content', 'Content (HTML)', p.content || '');
                    html += this.field('text', 'padding', 'Padding', p.padding || '10px 20px');
                    html += this.alignField('text_align', p.text_align || 'left');
                    break;
                case 'heading':
                    html += this.field('text', 'content', 'Text', p.content || '');
                    html += this.field('select', 'tag', 'Level', p.tag || 'h2', { h1: 'H1', h2: 'H2', h3: 'H3', h4: 'H4' });
                    html += this.field('text', 'font_size', 'Font Size', p.font_size || '24px');
                    html += this.colorField('color', 'Color', p.color || '');
                    html += this.field('text', 'padding', 'Padding', p.padding || '10px 20px');
                    html += this.alignField('text_align', p.text_align || 'center');
                    break;
                case 'image':
                    html += '<div class="kdna-etb-field"><label>Image</label><button class="button kdna-etb-pick-image" data-prop="src">Choose Image</button>';
                    if (p.src) html += '<br><img src="' + this.escAttr(p.src) + '" style="max-width:100%;margin-top:8px;" />';
                    html += '</div>';
                    html += this.field('text', 'alt', 'Alt Text', p.alt || '');
                    html += this.field('text', 'width', 'Width', p.width || '100%');
                    html += this.field('url', 'href', 'Link URL', p.href || '');
                    html += this.field('text', 'padding', 'Padding', p.padding || '10px 20px');
                    html += this.alignField('text_align', p.text_align || 'center');
                    break;
                case 'button':
                    html += this.field('text', 'text', 'Button Text', p.text || 'Click Here');
                    html += this.field('url', 'href', 'URL', p.href || '#');
                    html += this.colorField('bg_color', 'Background', p.bg_color || '#0073aa');
                    html += this.colorField('text_color', 'Text Color', p.text_color || '#ffffff');
                    html += this.field('text', 'border_radius', 'Border Radius', p.border_radius || '4px');
                    html += this.field('text', 'padding', 'Button Padding', p.padding || '12px 24px');
                    html += this.field('text', 'container_padding', 'Container Padding', p.container_padding || '10px 20px');
                    html += this.field('text', 'font_size', 'Font Size', p.font_size || '16px');
                    html += this.alignField('text_align', p.text_align || 'center');
                    break;
                case 'divider':
                    html += this.colorField('color', 'Color', p.color || '#e0e0e0');
                    html += this.field('text', 'thickness', 'Thickness', p.thickness || '1px');
                    html += this.field('select', 'style', 'Style', p.style || 'solid', { solid: 'Solid', dashed: 'Dashed', dotted: 'Dotted' });
                    html += this.field('text', 'padding', 'Padding', p.padding || '10px 20px');
                    break;
                case 'spacer':
                    html += this.field('text', 'height', 'Height', p.height || '20px');
                    break;
                case 'social':
                    html += this.field('text', 'padding', 'Padding', p.padding || '10px 20px');
                    html += this.field('text', 'icon_size', 'Icon Size', p.icon_size || '32px');
                    html += this.alignField('text_align', p.text_align || 'center');
                    var urls = p.urls || {};
                    html += '<h4 style="margin-top:12px;">Social URLs</h4>';
                    var socials = ['facebook', 'twitter', 'instagram', 'linkedin', 'youtube', 'pinterest', 'tiktok'];
                    for (var si = 0; si < socials.length; si++) {
                        html += this.field('url', 'urls.' + socials[si], socials[si].charAt(0).toUpperCase() + socials[si].slice(1), urls[socials[si]] || '');
                    }
                    break;
                case 'footer':
                    html += this.field('textarea', 'content', 'Content (HTML)', p.content || '');
                    html += this.colorField('bg_color', 'Background', p.bg_color || '#f7f7f7');
                    html += this.field('text', 'padding', 'Padding', p.padding || '20px');
                    html += this.alignField('text_align', p.text_align || 'center');
                    break;
                case 'coupon':
                    html += this.field('text', 'code_variable', 'Code Variable', p.code_variable || '{coupon_code}');
                    html += this.colorField('bg_color', 'Background', p.bg_color || '#f0f9ff');
                    html += this.colorField('border_color', 'Border', p.border_color || '#0073aa');
                    html += this.colorField('text_color', 'Text Color', p.text_color || '#333');
                    html += this.field('text', 'code_font_size', 'Code Size', p.code_font_size || '20px');
                    html += this.toggleField('show_expiry', 'Show Expiry', p.show_expiry !== false);
                    html += this.field('text', 'padding', 'Padding', p.padding || '10px 20px');
                    break;
                case 'html':
                    html += this.field('textarea', 'content', 'HTML Code', p.content || '');
                    html += this.field('text', 'padding', 'Padding', p.padding || '10px 20px');
                    break;
                case 'logo':
                    html += '<div class="kdna-etb-field"><label>Logo Image</label><button class="button kdna-etb-pick-image" data-prop="src">Choose Image</button></div>';
                    html += this.field('text', 'width', 'Width', p.width || '150px');
                    html += this.field('url', 'href', 'Link URL', p.href || '');
                    html += this.field('text', 'padding', 'Padding', p.padding || '20px');
                    html += this.alignField('text_align', p.text_align || 'center');
                    break;
                case 'menu':
                    html += this.field('text', 'separator', 'Separator', p.separator || ' | ');
                    html += this.field('text', 'font_size', 'Font Size', p.font_size || '13px');
                    html += this.field('text', 'padding', 'Padding', p.padding || '10px 20px');
                    html += this.alignField('text_align', p.text_align || 'center');
                    break;
                case 'columns':
                    html += this.field('select', 'columns', 'Columns', String(p.columns || 2), { '2': '2', '3': '3', '4': '4' });
                    html += this.field('select', 'layout', 'Layout', p.layout || '50-50', { '50-50': '50/50', '33-67': '33/67', '67-33': '67/33', '25-75': '25/75', '75-25': '75/25', '33-33-33': '33/33/33', '25-50-25': '25/50/25', '25-25-25-25': '25x4' });
                    html += this.field('text', 'gap', 'Gap', p.gap || '10px');
                    html += this.field('text', 'padding', 'Padding', p.padding || '10px 20px');
                    break;
                case 'product':
                    html += this.toggleField('show_image', 'Show Image', p.show_image !== false);
                    html += this.toggleField('show_title', 'Show Title', p.show_title !== false);
                    html += this.toggleField('show_price', 'Show Price', p.show_price !== false);
                    html += this.toggleField('show_description', 'Show Description', p.show_description === true);
                    html += this.toggleField('show_button', 'Show Button', p.show_button !== false);
                    html += this.field('text', 'button_text', 'Button Text', p.button_text || 'Shop Now');
                    html += this.field('select', 'columns', 'Columns', String(p.columns || 2), { '1': '1', '2': '2', '3': '3', '4': '4' });
                    html += this.field('text', 'padding', 'Padding', p.padding || '10px 20px');
                    break;
                case 'order_items':
                    html += this.toggleField('show_image', 'Show Image', p.show_image !== false);
                    html += this.toggleField('show_sku', 'Show SKU', p.show_sku === true);
                    html += this.toggleField('show_quantity', 'Show Quantity', p.show_quantity !== false);
                    html += this.toggleField('show_price', 'Show Price', p.show_price !== false);
                    html += this.toggleField('show_total', 'Show Total', p.show_total !== false);
                    html += this.field('text', 'image_width', 'Image Width', p.image_width || '64px');
                    html += this.field('text', 'padding', 'Padding', p.padding || '10px 20px');
                    break;
                case 'video':
                    html += this.field('url', 'url', 'Video URL', p.url || '');
                    html += '<div class="kdna-etb-field"><label>Thumbnail</label><button class="button kdna-etb-pick-image" data-prop="thumbnail">Choose Thumbnail</button>';
                    if (p.thumbnail) html += '<br><img src="' + this.escAttr(p.thumbnail) + '" style="max-width:100%;margin-top:8px;" />';
                    html += '</div>';
                    html += this.field('text', 'padding', 'Padding', p.padding || '10px 20px');
                    html += this.alignField('text_align', p.text_align || 'center');
                    break;
                case 'blank_row':
                    html += this.field('text', 'width', 'Width', p.width || '100%');
                    html += this.field('text', 'height', 'Height', p.height || '40px');
                    html += this.colorField('bg_color', 'Background Color', p.bg_color || '#f7f7f7');
                    html += this.field('text', 'padding', 'Padding', p.padding || '0px');
                    break;
                case 'content':
                    html += this.field('text', 'padding', 'Padding', p.padding || '10px 20px');
                    html += '<p class="description" style="margin-top:8px;">This block inserts the personalised email body content that is written when creating a follow-up email (e.g. Hi {customer_first_name}, ...).</p>';
                    break;
                default:
                    html += '<p>No settings available for this block type.</p>';
            }

            html += '</div>';
            return html;
        },

        // Field helpers
        field: function (type, name, label, value, options, description) {
            var html = '<div class="kdna-etb-field"><label>' + this.escHtml(label) + '</label>';
            if (type === 'select') {
                html += '<select class="kdna-etb-input" data-prop="' + name + '">';
                for (var k in options) {
                    html += '<option value="' + this.escAttr(k) + '"' + (k == value ? ' selected' : '') + '>' + this.escHtml(options[k]) + '</option>';
                }
                html += '</select>';
            } else if (type === 'textarea') {
                html += '<textarea class="kdna-etb-input" data-prop="' + name + '" rows="4">' + this.escHtml(value || '') + '</textarea>';
            } else {
                html += '<input type="' + type + '" class="kdna-etb-input" data-prop="' + name + '" value="' + this.escAttr(value || '') + '" />';
            }
            if (description) {
                html += '<p class="description">' + this.escHtml(description) + '</p>';
            }
            html += '</div>';
            return html;
        },

        toggleField: function (name, label, value) {
            var checked = value === true || value === 'true' || value === 1 || value === '1';
            return '<div class="kdna-etb-field kdna-etb-toggle"><label>' + this.escHtml(label) + '</label><input type="checkbox" class="kdna-etb-input kdna-etb-toggle-input" data-prop="' + name + '"' + (checked ? ' checked' : '') + ' /></div>';
        },

        colorField: function (name, label, value) {
            return '<div class="kdna-etb-field"><label>' + this.escHtml(label) + '</label><input type="text" class="kdna-etb-color kdna-etb-input" data-prop="' + name + '" value="' + this.escAttr(value || '') + '" /></div>';
        },

        alignField: function (name, value) {
            var html = '<div class="kdna-etb-field"><label>Alignment</label><div class="kdna-etb-align-group">';
            var aligns = ['left', 'center', 'right'];
            var icons = { left: 'dashicons-editor-alignleft', center: 'dashicons-editor-aligncenter', right: 'dashicons-editor-alignright' };
            for (var i = 0; i < aligns.length; i++) {
                html += '<button class="kdna-etb-align-btn' + (value === aligns[i] ? ' active' : '') + '" data-prop="' + name + '" data-value="' + aligns[i] + '"><span class="dashicons ' + icons[aligns[i]] + '"></span></button>';
            }
            html += '</div></div>';
            return html;
        },

        // Event binding
        bindEvents: function () {
            var self = this;

            // Tab switching
            this.el.on('click', '.kdna-etb-tab', function () {
                var panel = $(this).data('panel');
                self.el.find('.kdna-etb-tab').removeClass('active');
                $(this).addClass('active');
                self.el.find('.kdna-etb-panel').removeClass('active');
                self.el.find('.kdna-etb-panel[data-panel="' + panel + '"]').addClass('active');
            });

            // Block drag
            this.el.on('dragstart', '.kdna-etb-block-item', function (e) {
                e.originalEvent.dataTransfer.setData('block-type', $(this).data('type'));
            });

            // Drop zone
            this.el.on('dragover', '.kdna-etb-drop-zone, .kdna-etb-row', function (e) {
                e.preventDefault();
                $(this).addClass('drag-over');
            }).on('dragleave', '.kdna-etb-drop-zone, .kdna-etb-row', function () {
                $(this).removeClass('drag-over');
            }).on('drop', '.kdna-etb-drop-zone, .kdna-etb-row', function (e) {
                e.preventDefault();
                $(this).removeClass('drag-over');
                var type = e.originalEvent.dataTransfer.getData('block-type');
                if (type) {
                    var defaults = self.blocks[type] ? self.blocks[type].defaults : {};
                    var rowIndex = $(this).data('index');
                    if (typeof rowIndex !== 'undefined') {
                        // Insert a new row after the dropped-on row (blocks always stack vertically).
                        self.structure.rows.splice(rowIndex + 1, 0, { blocks: [{ type: type, props: $.extend(true, {}, defaults) }] });
                    } else {
                        self.structure.rows.push({ blocks: [{ type: type, props: $.extend(true, {}, defaults) }] });
                    }
                    self.refreshCanvas();

                    // Auto-select the newly added block.
                    var newRowIdx = (typeof rowIndex !== 'undefined') ? rowIndex + 1 : self.structure.rows.length - 1;
                    self.selectBlock(newRowIdx, 0);
                }
            });

            // Add row — insert a blank_row block so the user can configure height, background, etc.
            this.el.on('click', '.kdna-etb-add-row', function () {
                var defaults = self.blocks['blank_row'] ? self.blocks['blank_row'].defaults : { height: '40px', bg_color: '#f7f7f7', padding: '0px' };
                self.structure.rows.push({ blocks: [{ type: 'blank_row', props: $.extend(true, {}, defaults) }] });
                self.refreshCanvas();
                self.selectBlock(self.structure.rows.length - 1, 0);
            });

            // Click on a block palette item — add it to the canvas (fallback for non-drag interactions).
            this.el.on('click', '.kdna-etb-block-item', function () {
                var type = $(this).data('type');
                if (!type) return;
                var defaults = self.blocks[type] ? self.blocks[type].defaults : {};
                self.structure.rows.push({ blocks: [{ type: type, props: $.extend(true, {}, defaults) }] });
                self.refreshCanvas();
                self.selectBlock(self.structure.rows.length - 1, 0);
            });

            // Row actions
            this.el.on('click', '.kdna-etb-row-action.delete', function (e) {
                e.stopPropagation();
                var idx = $(this).closest('.kdna-etb-row').data('index');
                self.structure.rows.splice(idx, 1);
                self.selectedRow = null;
                self.selectedBlock = null;
                self.refreshCanvas();
            });

            this.el.on('click', '.kdna-etb-row-action.duplicate', function (e) {
                e.stopPropagation();
                var idx = $(this).closest('.kdna-etb-row').data('index');
                var clone = JSON.parse(JSON.stringify(self.structure.rows[idx]));
                self.structure.rows.splice(idx + 1, 0, clone);
                self.refreshCanvas();
            });

            // Select block — delegate to both .kdna-etb-block and row click
            this.el.on('click', '.kdna-etb-block', function (e) {
                e.stopPropagation();
                var rowIdx = $(this).data('row');
                var blockIdx = $(this).data('block');
                self.selectBlock(rowIdx, blockIdx);
            });

            // Also select single-block rows when clicking the row itself
            this.el.on('click', '.kdna-etb-row', function (e) {
                var $row = $(this);
                var rowIdx = $row.data('index');
                var blocks = self.structure.rows[rowIdx] ? self.structure.rows[rowIdx].blocks : [];
                if (blocks.length === 1) {
                    self.selectBlock(rowIdx, 0);
                }
            });

            // Block/design setting changes
            this.el.on('change input', '.kdna-etb-input', function () {
                var prop = $(this).data('prop');
                var val = $(this).is(':checkbox') ? $(this).is(':checked') : $(this).val();
                var panel = $(this).closest('.kdna-etb-panel').data('panel');

                if (panel === 'settings') {
                    self.structure.settings[prop] = val;
                    self.refreshCanvas();
                } else if (panel === 'block-settings' && self.selectedRow !== null) {
                    var block = self.structure.rows[self.selectedRow].blocks[self.selectedBlock];
                    if (prop.indexOf('.') > -1) {
                        var parts = prop.split('.');
                        if (!block.props[parts[0]]) block.props[parts[0]] = {};
                        block.props[parts[0]][parts[1]] = val;
                    } else {
                        block.props[prop] = val;
                        // Auto-resize col_blocks array when columns count changes.
                        if (prop === 'columns' && block.type === 'columns') {
                            var newCols = parseInt(val) || 2;
                            if (!block.props.col_blocks) block.props.col_blocks = [];
                            while (block.props.col_blocks.length < newCols) {
                                block.props.col_blocks.push([]);
                            }
                        }
                    }
                    self.refreshCanvas();
                }
            });

            // Alignment buttons
            this.el.on('click', '.kdna-etb-align-btn', function () {
                var prop = $(this).data('prop');
                var val = $(this).data('value');
                $(this).siblings().removeClass('active');
                $(this).addClass('active');
                if (self.selectedRow !== null) {
                    self.structure.rows[self.selectedRow].blocks[self.selectedBlock].props[prop] = val;
                    self.refreshCanvas();
                }
            });

            // Image picker
            this.el.on('click', '.kdna-etb-pick-image', function () {
                var prop = $(this).data('prop');
                var frame = wp.media({ title: 'Select Image', multiple: false, library: { type: 'image' } });
                frame.on('select', function () {
                    var attachment = frame.state().get('selection').first().toJSON();
                    if (self.selectedRow !== null) {
                        self.structure.rows[self.selectedRow].blocks[self.selectedBlock].props[prop] = attachment.url;
                        self.refreshCanvas();
                        // Re-select the block to refresh settings panel
                        var block = self.structure.rows[self.selectedRow].blocks[self.selectedBlock];
                        self.el.find('.kdna-etb-panel[data-panel="block-settings"]').html(self.renderBlockSettings(block));
                    }
                });
                frame.open();
            });

            // Device toggle
            this.el.on('click', '.kdna-etb-device-btn', function () {
                self.el.find('.kdna-etb-device-btn').removeClass('active');
                $(this).addClass('active');
                var device = $(this).data('device');
                var isMobile = device === 'mobile';
                self.el.find('.kdna-etb-canvas').toggleClass('mobile-preview', isMobile);
                var frame = self.el.find('.kdna-etb-email-frame');
                if (isMobile) {
                    frame.css('max-width', '375px');
                } else {
                    frame.css('max-width', (parseInt(self.structure.settings.width) + 60) + 'px');
                }
            });

            // Preview
            this.el.on('click', '.kdna-etb-preview-btn', function () {
                $.post(self.ajaxUrl, {
                    action: 'kdna_email_builder_preview',
                    nonce: self.nonce,
                    json: JSON.stringify(self.structure)
                }, function (resp) {
                    if (resp.success) {
                        var w = window.open('', 'email_preview', 'width=700,height=600');
                        w.document.open();
                        w.document.write(resp.data.html);
                        w.document.close();
                    }
                });
            });

            // Save
            this.el.on('click', '.kdna-etb-save-btn', function () {
                var btn = $(this);
                btn.prop('disabled', true).text('Saving...');
                $.post(self.ajaxUrl, {
                    action: 'kdna_email_builder_save',
                    nonce: self.nonce,
                    template_id: self.templateId,
                    name: self.el.find('.kdna-etb-name').val(),
                    json: JSON.stringify(self.structure),
                    custom_css: ''
                }, function (resp) {
                    btn.prop('disabled', false).text('Save');
                    if (resp.success) {
                        self.templateId = resp.data.template_id;
                        self.el.data('template-id', resp.data.template_id);
                        // Update URL without reload
                        if (window.history.replaceState) {
                            var url = new URL(window.location);
                            url.searchParams.set('action', 'edit');
                            url.searchParams.set('template_id', resp.data.template_id);
                            window.history.replaceState({}, '', url);
                        }
                        self.showNotice('Template saved!', 'success');
                    } else {
                        self.showNotice('Error saving template.', 'error');
                    }
                });
            });

            // Template list actions
            $(document).on('click', '.kdna-etb-delete', function (e) {
                e.preventDefault();
                if (!confirm('Delete this template?')) return;
                var id = $(this).data('id');
                var row = $(this).closest('tr');
                $.post(self.ajaxUrl, {
                    action: 'kdna_email_builder_delete',
                    nonce: self.nonce,
                    template_id: id
                }, function () {
                    row.fadeOut(300, function () { $(this).remove(); });
                });
            });

            $(document).on('click', '.kdna-etb-duplicate', function (e) {
                e.preventDefault();
                var id = $(this).data('id');
                $.post(self.ajaxUrl, {
                    action: 'kdna_email_builder_duplicate',
                    nonce: self.nonce,
                    template_id: id
                }, function (resp) {
                    if (resp.success) {
                        window.location.reload();
                    }
                });
            });
        },

        /**
         * Programmatically select a block and open its settings panel.
         */
        selectBlock: function (rowIdx, blockIdx) {
            if (!this.structure.rows[rowIdx] || !this.structure.rows[rowIdx].blocks[blockIdx]) return;

            this.selectedRow = rowIdx;
            this.selectedBlock = blockIdx;
            this.el.find('.kdna-etb-row').removeClass('selected');
            this.el.find('.kdna-etb-block').removeClass('selected');
            var $row = this.el.find('.kdna-etb-row[data-index="' + rowIdx + '"]');
            $row.addClass('selected');
            $row.find('.kdna-etb-block[data-block="' + blockIdx + '"]').addClass('selected');

            var block = this.structure.rows[rowIdx].blocks[blockIdx];
            var settingsHtml = this.renderBlockSettings(block);
            this.el.find('.kdna-etb-panel[data-panel="block-settings"]').html(settingsHtml);
            this.el.find('.kdna-etb-tab').removeClass('active');
            this.el.find('.kdna-etb-tab[data-panel="block-settings"]').addClass('active');
            this.el.find('.kdna-etb-panel').removeClass('active');
            this.el.find('.kdna-etb-panel[data-panel="block-settings"]').addClass('active');
            this.initColorPickers();
        },

        refreshCanvas: function () {
            var frame = this.el.find('.kdna-etb-email-frame');
            var body = frame.find('.kdna-etb-email-body');
            body.html(this.renderRows());
            body.css({
                'background': this.structure.settings.content_bg_color || '#fff',
                'padding': this.structure.settings.padding || '0px'
            });
            frame.css('max-width', parseInt(this.structure.settings.width) + 'px');
            this.applyFullbleedMargins();
            this.initSortable();

            // Re-highlight currently selected block if still valid.
            if (this.selectedRow !== null && this.structure.rows[this.selectedRow] && this.structure.rows[this.selectedRow].blocks[this.selectedBlock]) {
                var $row = this.el.find('.kdna-etb-row[data-index="' + this.selectedRow + '"]');
                $row.addClass('selected');
                $row.find('.kdna-etb-block[data-block="' + this.selectedBlock + '"]').addClass('selected');
            }
        },

        applyFullbleedMargins: function () {
            var padPx = parseInt(this.structure.settings.padding) || 0;
            var $rows = this.el.find('.kdna-etb-row-fullbleed');
            if (padPx > 0 && $rows.length) {
                $rows.css({
                    'margin-left': -padPx + 'px',
                    'margin-right': -padPx + 'px'
                });
            }
        },

        initSortable: function () {
            var self = this;
            this.el.find('.kdna-etb-drop-zone.has-blocks').sortable({
                items: '.kdna-etb-row',
                handle: '.kdna-etb-row-action.move',
                placeholder: 'ui-sortable-placeholder',
                tolerance: 'pointer',
                update: function () {
                    var newOrder = [];
                    $(this).find('.kdna-etb-row').each(function () {
                        var idx = $(this).data('index');
                        newOrder.push(self.structure.rows[idx]);
                    });
                    self.structure.rows = newOrder;
                    self.refreshCanvas();
                }
            });
        },

        initColorPickers: function () {
            this.el.find('.kdna-etb-color').not('.wp-color-picker').wpColorPicker({
                change: function (event, ui) {
                    $(this).val(ui.color.toString()).trigger('change');
                }
            });
        },

        showNotice: function (message, type) {
            var notice = $('<div class="notice notice-' + type + ' is-dismissible" style="position:fixed;top:40px;right:20px;z-index:9999;padding:10px 15px;"><p>' + message + '</p></div>');
            $('body').append(notice);
            setTimeout(function () { notice.fadeOut(400, function () { $(this).remove(); }); }, 3000);
        },

        escHtml: function (str) {
            if (!str) return '';
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        },

        escAttr: function (str) {
            if (!str) return '';
            return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }
    };

    $(document).ready(function () {
        Builder.init();
    });

})(jQuery);
