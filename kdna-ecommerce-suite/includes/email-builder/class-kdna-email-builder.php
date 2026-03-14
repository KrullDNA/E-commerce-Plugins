<?php
/**
 * KDNA Email Template Builder
 *
 * Drag-and-drop email template builder with live preview.
 * Shared component used by Automations and Emails modules.
 *
 * Templates are stored as custom post type `kdna_email_template` with
 * a JSON structure in post_content that describes rows, columns, and blocks.
 */
defined( 'ABSPATH' ) || exit;

class KDNA_Email_Builder {

    const POST_TYPE    = 'kdna_email_tpl';
    const META_JSON    = '_kdna_email_builder_json';
    const META_CSS     = '_kdna_email_builder_css';
    const META_THUMB   = '_kdna_email_builder_thumb';
    const NONCE_ACTION = 'kdna_email_builder_save';

    /** Available block types with default configuration. */
    const BLOCK_TYPES = [
        'text'        => [ 'label' => 'Text',          'icon' => 'dashicons-editor-paragraph' ],
        'heading'     => [ 'label' => 'Heading',        'icon' => 'dashicons-heading' ],
        'image'       => [ 'label' => 'Image',          'icon' => 'dashicons-format-image' ],
        'button'      => [ 'label' => 'Button',         'icon' => 'dashicons-button' ],
        'divider'     => [ 'label' => 'Divider',        'icon' => 'dashicons-minus' ],
        'spacer'      => [ 'label' => 'Spacer',         'icon' => 'dashicons-editor-expand' ],
        'columns'     => [ 'label' => 'Columns',        'icon' => 'dashicons-columns' ],
        'social'      => [ 'label' => 'Social Icons',   'icon' => 'dashicons-share' ],
        'video'       => [ 'label' => 'Video',          'icon' => 'dashicons-video-alt3' ],
        'html'        => [ 'label' => 'Custom HTML',    'icon' => 'dashicons-editor-code' ],
        'logo'        => [ 'label' => 'Logo',           'icon' => 'dashicons-store' ],
        'product'     => [ 'label' => 'Product Card',   'icon' => 'dashicons-cart' ],
        'coupon'      => [ 'label' => 'Coupon Block',   'icon' => 'dashicons-tickets-alt' ],
        'footer'      => [ 'label' => 'Footer',         'icon' => 'dashicons-editor-insertmore' ],
        'menu'        => [ 'label' => 'Menu/Nav',       'icon' => 'dashicons-menu' ],
        'order_items' => [ 'label' => 'Order Items',    'icon' => 'dashicons-list-view' ],
        'blank_row'   => [ 'label' => 'Blank Row',      'icon' => 'dashicons-editor-contract' ],
        'content'     => [ 'label' => 'Content',        'icon' => 'dashicons-email-alt' ],
    ];

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action( 'init', [ $this, 'register_post_type' ] );
        add_action( 'admin_menu', [ $this, 'add_admin_pages' ] );
        add_action( 'wp_ajax_kdna_email_builder_save', [ $this, 'ajax_save' ] );
        add_action( 'wp_ajax_kdna_email_builder_preview', [ $this, 'ajax_preview' ] );
        add_action( 'wp_ajax_kdna_email_builder_load', [ $this, 'ajax_load' ] );
        add_action( 'wp_ajax_kdna_email_builder_list', [ $this, 'ajax_list' ] );
        add_action( 'wp_ajax_kdna_email_builder_delete', [ $this, 'ajax_delete' ] );
        add_action( 'wp_ajax_kdna_email_builder_duplicate', [ $this, 'ajax_duplicate' ] );
        add_action( 'wp_ajax_kdna_email_builder_upload_image', [ $this, 'ajax_upload_image' ] );
    }

    public function register_post_type() {
        register_post_type( self::POST_TYPE, [
            'labels' => [
                'name'          => __( 'Email Templates', 'kdna-ecommerce' ),
                'singular_name' => __( 'Email Template', 'kdna-ecommerce' ),
            ],
            'public'       => false,
            'show_ui'      => false,
            'supports'     => [ 'title' ],
            'map_meta_cap' => true,
            'capability_type' => 'post',
        ] );
    }

    public function add_admin_pages() {
        add_submenu_page(
            'kdna-ecommerce',
            __( 'Email Templates', 'kdna-ecommerce' ),
            __( 'Email Templates', 'kdna-ecommerce' ),
            'manage_woocommerce',
            'kdna-email-templates',
            [ $this, 'render_templates_page' ]
        );
    }

    /**
     * Get a default empty template structure.
     */
    public static function get_default_structure() {
        return [
            'settings' => [
                'width'            => 600,
                'bg_color'         => '#f7f7f7',
                'content_bg_color' => '#ffffff',
                'font_family'      => "'Helvetica Neue', Helvetica, Arial, sans-serif",
                'font_size'        => '14px',
                'line_height'      => '1.5',
                'text_color'       => '#333333',
                'link_color'       => '#0073aa',
                'heading_color'    => '#1a1a1a',
                'padding'          => '20px',
                'border_radius'    => '0px',
                'preheader'        => '',
            ],
            'rows' => [],
        ];
    }

    /**
     * Get all block type definitions for the builder UI.
     */
    public static function get_block_definitions() {
        $blocks = [];
        foreach ( self::BLOCK_TYPES as $type => $conf ) {
            $blocks[ $type ] = array_merge( $conf, [
                'type'     => $type,
                'defaults' => self::get_block_defaults( $type ),
            ] );
        }
        return $blocks;
    }

    /**
     * Default properties for each block type.
     */
    public static function get_block_defaults( $type ) {
        $defaults = [
            'text'    => [
                'content'    => '<p>Your text here.</p>',
                'padding'    => '10px 20px',
                'text_align' => 'left',
            ],
            'heading' => [
                'content'    => 'Heading',
                'tag'        => 'h2',
                'padding'    => '10px 20px',
                'text_align' => 'center',
                'color'      => '',
                'font_size'  => '24px',
            ],
            'image' => [
                'src'         => '',
                'alt'         => '',
                'width'       => '100%',
                'href'        => '',
                'padding'     => '10px 20px',
                'text_align'  => 'center',
            ],
            'button' => [
                'text'             => 'Click Here',
                'href'             => '#',
                'bg_color'         => '#0073aa',
                'text_color'       => '#ffffff',
                'border_radius'    => '4px',
                'padding'          => '12px 24px',
                'font_size'        => '16px',
                'font_weight'      => 'bold',
                'text_align'       => 'center',
                'full_width'       => false,
                'container_padding' => '10px 20px',
            ],
            'divider' => [
                'color'     => '#e0e0e0',
                'thickness' => '1px',
                'style'     => 'solid',
                'width'     => '100%',
                'padding'   => '10px 20px',
            ],
            'spacer' => [
                'height' => '20px',
            ],
            'columns' => [
                'columns'    => 2,
                'gap'        => '10px',
                'layout'     => '50-50',
                'padding'    => '10px 20px',
                'col_blocks' => [ [], [] ],
            ],
            'social' => [
                'icons'      => [ 'facebook', 'twitter', 'instagram', 'linkedin' ],
                'icon_size'  => '32px',
                'icon_style' => 'color',
                'spacing'    => '8px',
                'text_align' => 'center',
                'padding'    => '10px 20px',
                'urls'       => [
                    'facebook'  => '',
                    'twitter'   => '',
                    'instagram' => '',
                    'linkedin'  => '',
                    'youtube'   => '',
                    'pinterest' => '',
                    'tiktok'    => '',
                ],
            ],
            'video' => [
                'url'        => '',
                'thumbnail'  => '',
                'padding'    => '10px 20px',
                'text_align' => 'center',
            ],
            'html' => [
                'content' => '<!-- Custom HTML -->',
                'padding' => '10px 20px',
            ],
            'logo' => [
                'src'        => '',
                'width'      => '150px',
                'href'       => '',
                'text_align' => 'center',
                'padding'    => '20px',
            ],
            'product' => [
                'show_image'       => true,
                'show_title'       => true,
                'show_price'       => true,
                'show_description' => false,
                'show_button'      => true,
                'button_text'      => 'Shop Now',
                'columns'          => 2,
                'padding'          => '10px 20px',
            ],
            'coupon' => [
                'code_variable'  => '{coupon_code}',
                'bg_color'       => '#f0f9ff',
                'border_color'   => '#0073aa',
                'text_color'     => '#333',
                'code_font_size' => '20px',
                'show_expiry'    => true,
                'padding'        => '10px 20px',
            ],
            'footer' => [
                'content'    => '<p style="font-size:12px;color:#999;">&copy; {store_name} | <a href="{unsubscribe_url}">Unsubscribe</a></p>',
                'padding'    => '20px',
                'bg_color'   => '#f7f7f7',
                'text_align' => 'center',
            ],
            'menu' => [
                'items' => [
                    [ 'label' => 'Shop', 'url' => '' ],
                    [ 'label' => 'About', 'url' => '' ],
                    [ 'label' => 'Contact', 'url' => '' ],
                ],
                'separator'  => ' | ',
                'font_size'  => '13px',
                'text_align' => 'center',
                'padding'    => '10px 20px',
            ],
            'order_items' => [
                'show_image'    => true,
                'show_sku'      => false,
                'show_quantity' => true,
                'show_price'    => true,
                'show_total'    => true,
                'image_width'   => '64px',
                'padding'       => '10px 20px',
            ],
            'blank_row' => [
                'height'   => '40px',
                'bg_color' => '#f7f7f7',
                'padding'  => '0px',
            ],
            'content' => [
                'padding' => '10px 20px',
            ],
        ];

        return $defaults[ $type ] ?? [];
    }

    /**
     * Compile template JSON structure to email-safe HTML.
     */
    public static function compile_to_html( $structure, $variables = [] ) {
        if ( is_string( $structure ) ) {
            $structure = json_decode( $structure, true );
        }
        if ( empty( $structure ) || ! is_array( $structure ) ) {
            $structure = self::get_default_structure();
        }

        $s = $structure['settings'] ?? [];
        $width       = $s['width'] ?? 600;
        $bg          = $s['bg_color'] ?? '#f7f7f7';
        $content_bg  = $s['content_bg_color'] ?? '#ffffff';
        $font_family = $s['font_family'] ?? "'Helvetica Neue', Helvetica, Arial, sans-serif";
        $font_size   = $s['font_size'] ?? '14px';
        $line_height = $s['line_height'] ?? '1.5';
        $text_color  = $s['text_color'] ?? '#333333';
        $link_color  = $s['link_color'] ?? '#0073aa';
        $padding     = $s['padding'] ?? '20px';
        $preheader   = $s['preheader'] ?? '';

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
        $html .= '<style>body{margin:0;padding:0;background:' . esc_attr( $bg ) . ';font-family:' . esc_attr( $font_family ) . ';font-size:' . esc_attr( $font_size ) . ';line-height:' . esc_attr( $line_height ) . ';color:' . esc_attr( $text_color ) . ';}';
        $html .= 'a{color:' . esc_attr( $link_color ) . ';}';
        $html .= 'img{max-width:100%;height:auto;}';
        $html .= 'table{border-collapse:collapse;}';
        $html .= '.email-row{width:100%;}</style></head><body>';

        if ( $preheader ) {
            $html .= '<div style="display:none;font-size:1px;color:' . esc_attr( $bg ) . ';line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">' . esc_html( $preheader ) . '</div>';
        }

        $html .= '<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:' . esc_attr( $bg ) . ';">';
        $html .= '<tr><td align="center" style="padding:' . esc_attr( $padding ) . ';">';
        $html .= '<table width="' . intval( $width ) . '" cellpadding="0" cellspacing="0" role="presentation" style="background:' . esc_attr( $content_bg ) . ';border-radius:' . esc_attr( $s['border_radius'] ?? '0px' ) . ';overflow:hidden;max-width:100%;">';

        foreach ( ( $structure['rows'] ?? [] ) as $row ) {
            $html .= self::compile_row( $row, $s );
        }

        $html .= '</table></td></tr></table></body></html>';

        // Replace variables.
        if ( ! empty( $variables ) ) {
            foreach ( $variables as $key => $value ) {
                $html = str_replace( '{' . $key . '}', $value, $html );
                $html = str_replace( '{{ ' . $key . ' }}', $value, $html );
            }
        }

        return $html;
    }

    /**
     * Compile a single row.
     */
    private static function compile_row( $row, $settings ) {
        $bg      = ! empty( $row['bg_color'] ) ? 'background:' . esc_attr( $row['bg_color'] ) . ';' : '';
        $padding = ! empty( $row['padding'] ) ? 'padding:' . esc_attr( $row['padding'] ) . ';' : '';
        $html    = '<tr><td style="' . $bg . $padding . '">';

        foreach ( ( $row['blocks'] ?? [] ) as $block ) {
            $html .= self::compile_block( $block, $settings );
        }

        $html .= '</td></tr>';
        return $html;
    }

    /**
     * Compile a single block to HTML.
     */
    private static function compile_block( $block, $settings ) {
        $type = $block['type'] ?? 'text';
        $p    = $block['props'] ?? [];

        switch ( $type ) {
            case 'text':
                $align = $p['text_align'] ?? 'left';
                return '<div style="padding:' . esc_attr( $p['padding'] ?? '10px 20px' ) . ';text-align:' . esc_attr( $align ) . ';">' . wp_kses_post( $p['content'] ?? '' ) . '</div>';

            case 'heading':
                $tag   = in_array( ( $p['tag'] ?? 'h2' ), [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ], true ) ? $p['tag'] : 'h2';
                $color = ! empty( $p['color'] ) ? 'color:' . esc_attr( $p['color'] ) . ';' : 'color:' . esc_attr( $settings['heading_color'] ?? '#1a1a1a' ) . ';';
                $size  = ! empty( $p['font_size'] ) ? 'font-size:' . esc_attr( $p['font_size'] ) . ';' : '';
                $align = $p['text_align'] ?? 'center';
                return '<' . $tag . ' style="padding:' . esc_attr( $p['padding'] ?? '10px 20px' ) . ';text-align:' . esc_attr( $align ) . ';margin:0;' . $color . $size . '">' . esc_html( $p['content'] ?? '' ) . '</' . $tag . '>';

            case 'image':
                $align = $p['text_align'] ?? 'center';
                $img   = '<img src="' . esc_url( $p['src'] ?? '' ) . '" alt="' . esc_attr( $p['alt'] ?? '' ) . '" style="width:' . esc_attr( $p['width'] ?? '100%' ) . ';display:inline-block;" />';
                if ( ! empty( $p['href'] ) ) {
                    $img = '<a href="' . esc_url( $p['href'] ) . '">' . $img . '</a>';
                }
                return '<div style="padding:' . esc_attr( $p['padding'] ?? '10px 20px' ) . ';text-align:' . esc_attr( $align ) . ';">' . $img . '</div>';

            case 'button':
                $align    = $p['text_align'] ?? 'center';
                $fw       = ! empty( $p['full_width'] ) ? 'display:block;width:100%;' : 'display:inline-block;';
                $btn_style = $fw . 'background:' . esc_attr( $p['bg_color'] ?? '#0073aa' ) . ';color:' . esc_attr( $p['text_color'] ?? '#fff' ) . ';padding:' . esc_attr( $p['padding'] ?? '12px 24px' ) . ';border-radius:' . esc_attr( $p['border_radius'] ?? '4px' ) . ';font-size:' . esc_attr( $p['font_size'] ?? '16px' ) . ';font-weight:' . esc_attr( $p['font_weight'] ?? 'bold' ) . ';text-decoration:none;text-align:center;';
                return '<div style="padding:' . esc_attr( $p['container_padding'] ?? '10px 20px' ) . ';text-align:' . esc_attr( $align ) . ';"><a href="' . esc_url( $p['href'] ?? '#' ) . '" style="' . $btn_style . '">' . esc_html( $p['text'] ?? 'Click' ) . '</a></div>';

            case 'divider':
                return '<div style="padding:' . esc_attr( $p['padding'] ?? '10px 20px' ) . ';"><hr style="border:none;border-top:' . esc_attr( $p['thickness'] ?? '1px' ) . ' ' . esc_attr( $p['style'] ?? 'solid' ) . ' ' . esc_attr( $p['color'] ?? '#e0e0e0' ) . ';margin:0;width:' . esc_attr( $p['width'] ?? '100%' ) . ';" /></div>';

            case 'spacer':
                return '<div style="height:' . esc_attr( $p['height'] ?? '20px' ) . ';"></div>';

            case 'columns':
                $cols    = intval( $p['columns'] ?? 2 );
                $gap     = $p['gap'] ?? '10px';
                $padding = $p['padding'] ?? '10px 20px';
                $widths  = self::get_column_widths( $p['layout'] ?? '50-50', $cols );
                $html    = '<div style="padding:' . esc_attr( $padding ) . ';"><table width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr>';
                for ( $i = 0; $i < $cols; $i++ ) {
                    $col_blocks = $p['col_blocks'][ $i ] ?? [];
                    $w = $widths[ $i ] ?? ( 100 / $cols ) . '%';
                    $html .= '<td style="width:' . esc_attr( $w ) . ';vertical-align:top;padding:0 ' . esc_attr( $gap ) . ';">';
                    foreach ( $col_blocks as $cb ) {
                        $html .= self::compile_block( $cb, $settings );
                    }
                    $html .= '</td>';
                }
                $html .= '</tr></table></div>';
                return $html;

            case 'social':
                $align   = $p['text_align'] ?? 'center';
                $size    = $p['icon_size'] ?? '32px';
                $spacing = $p['icon_style'] ?? 'color';
                $gap     = $p['spacing'] ?? '8px';
                $urls    = $p['urls'] ?? [];
                $icons   = $p['icons'] ?? [ 'facebook', 'twitter', 'instagram' ];
                $html    = '<div style="padding:' . esc_attr( $p['padding'] ?? '10px 20px' ) . ';text-align:' . esc_attr( $align ) . ';">';
                foreach ( $icons as $icon ) {
                    $url = $urls[ $icon ] ?? '#';
                    if ( empty( $url ) ) { $url = '#'; }
                    $label = ucfirst( $icon );
                    $html .= '<a href="' . esc_url( $url ) . '" style="display:inline-block;margin:0 ' . esc_attr( $gap ) . ';text-decoration:none;" title="' . esc_attr( $label ) . '">';
                    $html .= '<img src="' . esc_url( KDNA_ECOMMERCE_URL . 'includes/email-builder/icons/' . $icon . '.png' ) . '" alt="' . esc_attr( $label ) . '" width="' . intval( $size ) . '" height="' . intval( $size ) . '" style="border:0;" />';
                    $html .= '</a>';
                }
                $html .= '</div>';
                return $html;

            case 'video':
                $align = $p['text_align'] ?? 'center';
                $thumb = $p['thumbnail'] ?? '';
                $url   = $p['url'] ?? '';
                if ( $thumb ) {
                    $img = '<a href="' . esc_url( $url ) . '"><img src="' . esc_url( $thumb ) . '" style="width:100%;display:block;" /></a>';
                } else {
                    $img = '<a href="' . esc_url( $url ) . '" style="display:block;padding:40px;background:#000;color:#fff;text-align:center;font-size:24px;">&#9654; Play Video</a>';
                }
                return '<div style="padding:' . esc_attr( $p['padding'] ?? '10px 20px' ) . ';text-align:' . esc_attr( $align ) . ';">' . $img . '</div>';

            case 'html':
                return '<div style="padding:' . esc_attr( $p['padding'] ?? '10px 20px' ) . ';">' . ( $p['content'] ?? '' ) . '</div>';

            case 'logo':
                $align = $p['text_align'] ?? 'center';
                $img   = '<img src="' . esc_url( $p['src'] ?? '' ) . '" alt="Logo" style="width:' . esc_attr( $p['width'] ?? '150px' ) . ';" />';
                if ( ! empty( $p['href'] ) ) {
                    $img = '<a href="' . esc_url( $p['href'] ) . '">' . $img . '</a>';
                }
                return '<div style="padding:' . esc_attr( $p['padding'] ?? '20px' ) . ';text-align:' . esc_attr( $align ) . ';">' . $img . '</div>';

            case 'product':
                return '<div style="padding:' . esc_attr( $p['padding'] ?? '10px 20px' ) . ';">' .
                    '<div style="text-align:center;padding:20px;border:1px solid #eee;border-radius:8px;">' .
                    '<p style="color:#999;font-size:13px;">[Product card - rendered dynamically at send time]</p>' .
                    '</div></div>';

            case 'coupon':
                $border = $p['border_color'] ?? '#0073aa';
                $bg     = $p['bg_color'] ?? '#f0f9ff';
                return '<div style="padding:' . esc_attr( $p['padding'] ?? '10px 20px' ) . ';">' .
                    '<div style="border:2px dashed ' . esc_attr( $border ) . ';background:' . esc_attr( $bg ) . ';border-radius:8px;padding:20px;text-align:center;">' .
                    '<div style="font-family:monospace;font-size:' . esc_attr( $p['code_font_size'] ?? '20px' ) . ';font-weight:bold;letter-spacing:2px;color:' . esc_attr( $p['text_color'] ?? '#333' ) . ';">' . esc_html( $p['code_variable'] ?? '{coupon_code}' ) . '</div>' .
                    ( ! empty( $p['show_expiry'] ) ? '<div style="font-size:12px;color:#999;margin-top:8px;">{coupon_expiry}</div>' : '' ) .
                    '</div></div>';

            case 'footer':
                $bg    = ! empty( $p['bg_color'] ) ? 'background:' . esc_attr( $p['bg_color'] ) . ';' : '';
                $align = $p['text_align'] ?? 'center';
                return '<div style="padding:' . esc_attr( $p['padding'] ?? '20px' ) . ';' . $bg . 'text-align:' . esc_attr( $align ) . ';">' . wp_kses_post( $p['content'] ?? '' ) . '</div>';

            case 'menu':
                $align = $p['text_align'] ?? 'center';
                $sep   = esc_html( $p['separator'] ?? ' | ' );
                $size  = $p['font_size'] ?? '13px';
                $items = $p['items'] ?? [];
                $links = [];
                foreach ( $items as $item ) {
                    $links[] = '<a href="' . esc_url( $item['url'] ?? '#' ) . '" style="font-size:' . esc_attr( $size ) . ';text-decoration:none;">' . esc_html( $item['label'] ?? '' ) . '</a>';
                }
                return '<div style="padding:' . esc_attr( $p['padding'] ?? '10px 20px' ) . ';text-align:' . esc_attr( $align ) . ';">' . implode( $sep, $links ) . '</div>';

            case 'order_items':
                return '<div style="padding:' . esc_attr( $p['padding'] ?? '10px 20px' ) . ';">' .
                    '<table width="100%" style="border-collapse:collapse;">' .
                    '<tr style="background:#f7f7f7;"><th style="padding:8px;text-align:left;border-bottom:1px solid #e0e0e0;">Item</th>' .
                    ( ! empty( $p['show_quantity'] ) ? '<th style="padding:8px;text-align:center;border-bottom:1px solid #e0e0e0;">Qty</th>' : '' ) .
                    ( ! empty( $p['show_price'] ) ? '<th style="padding:8px;text-align:right;border-bottom:1px solid #e0e0e0;">Price</th>' : '' ) .
                    '</tr><tr><td colspan="3" style="padding:12px;text-align:center;color:#999;font-size:13px;">[Order items rendered at send time]</td></tr></table></div>';

            case 'blank_row':
                return '<div style="height:' . esc_attr( $p['height'] ?? '40px' ) . ';background:' . esc_attr( $p['bg_color'] ?? '#f7f7f7' ) . ';padding:' . esc_attr( $p['padding'] ?? '0px' ) . ';"></div>';

            case 'content':
                return '<div style="padding:' . esc_attr( $p['padding'] ?? '10px 20px' ) . ';">{email_content}</div>';

            default:
                return '';
        }
    }

    /**
     * Get column width percentages from layout string.
     */
    private static function get_column_widths( $layout, $cols ) {
        $presets = [
            '50-50'    => [ '50%', '50%' ],
            '33-33-33' => [ '33.33%', '33.33%', '33.33%' ],
            '25-75'    => [ '25%', '75%' ],
            '75-25'    => [ '75%', '25%' ],
            '33-67'    => [ '33%', '67%' ],
            '67-33'    => [ '67%', '33%' ],
            '25-25-25-25' => [ '25%', '25%', '25%', '25%' ],
            '25-50-25' => [ '25%', '50%', '25%' ],
        ];

        if ( isset( $presets[ $layout ] ) ) {
            return $presets[ $layout ];
        }

        $w = ( 100 / max( 1, $cols ) ) . '%';
        return array_fill( 0, $cols, $w );
    }

    // ===================================================================
    // AJAX Handlers
    // ===================================================================

    public function ajax_save() {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Permission denied.' );
        }

        $template_id = intval( $_POST['template_id'] ?? 0 );
        $name        = sanitize_text_field( $_POST['name'] ?? 'Untitled Template' );
        $json        = wp_unslash( $_POST['json'] ?? '{}' );
        $custom_css  = wp_strip_all_tags( wp_unslash( $_POST['custom_css'] ?? '' ) );

        // Validate JSON.
        $decoded = json_decode( $json, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            wp_send_json_error( 'Invalid JSON structure.' );
        }

        $post_data = [
            'post_title'   => $name,
            'post_content' => $json,
            'post_type'    => self::POST_TYPE,
            'post_status'  => 'publish',
        ];

        if ( $template_id > 0 ) {
            $post_data['ID'] = $template_id;
            wp_update_post( $post_data );
        } else {
            $template_id = wp_insert_post( $post_data );
        }

        if ( is_wp_error( $template_id ) ) {
            wp_send_json_error( $template_id->get_error_message() );
        }

        update_post_meta( $template_id, self::META_JSON, $json );
        update_post_meta( $template_id, self::META_CSS, $custom_css );

        // Generate compiled HTML and store it.
        $compiled = self::compile_to_html( $decoded );
        update_post_meta( $template_id, '_kdna_email_compiled_html', $compiled );

        wp_send_json_success( [
            'template_id' => $template_id,
            'message'     => __( 'Template saved.', 'kdna-ecommerce' ),
        ] );
    }

    public function ajax_preview() {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        $json = wp_unslash( $_POST['json'] ?? '{}' );
        $decoded = json_decode( $json, true );
        if ( ! $decoded ) {
            wp_send_json_error( 'Invalid JSON.' );
        }

        // Sample variables for preview.
        $sample_vars = [
            'customer_first_name' => 'John',
            'customer_last_name'  => 'Doe',
            'customer_email'      => 'john@example.com',
            'customer_name'       => 'John Doe',
            'store_name'          => get_bloginfo( 'name' ),
            'store_url'           => home_url(),
            'site_title'          => get_bloginfo( 'name' ),
            'order_id'            => '1234',
            'order_number'        => '#1234',
            'order_total'         => '$99.99',
            'coupon_code'         => 'SAMPLE20',
            'coupon_amount'       => '20%',
            'coupon_expiry'       => 'Expires in 30 days',
            'unsubscribe_url'     => '#',
        ];

        $html = self::compile_to_html( $decoded, $sample_vars );
        wp_send_json_success( [ 'html' => $html ] );
    }

    public function ajax_load() {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        $template_id = intval( $_POST['template_id'] ?? 0 );
        $post = get_post( $template_id );

        if ( ! $post || $post->post_type !== self::POST_TYPE ) {
            wp_send_json_error( 'Template not found.' );
        }

        $json = get_post_meta( $template_id, self::META_JSON, true ) ?: $post->post_content;
        $css  = get_post_meta( $template_id, self::META_CSS, true ) ?: '';

        wp_send_json_success( [
            'id'   => $template_id,
            'name' => $post->post_title,
            'json' => $json,
            'css'  => $css,
        ] );
    }

    public function ajax_list() {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        $templates = get_posts( [
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => 100,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );

        $list = [];
        foreach ( $templates as $tpl ) {
            $list[] = [
                'id'       => $tpl->ID,
                'name'     => $tpl->post_title,
                'modified' => $tpl->post_modified,
            ];
        }

        wp_send_json_success( $list );
    }

    public function ajax_delete() {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Permission denied.' );
        }

        $template_id = intval( $_POST['template_id'] ?? 0 );
        wp_delete_post( $template_id, true );
        wp_send_json_success();
    }

    public function ajax_duplicate() {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Permission denied.' );
        }

        $template_id = intval( $_POST['template_id'] ?? 0 );
        $post = get_post( $template_id );
        if ( ! $post ) {
            wp_send_json_error( 'Template not found.' );
        }

        $new_id = wp_insert_post( [
            'post_title'   => $post->post_title . ' (Copy)',
            'post_content' => $post->post_content,
            'post_type'    => self::POST_TYPE,
            'post_status'  => 'publish',
        ] );

        $meta_keys = [ self::META_JSON, self::META_CSS, '_kdna_email_compiled_html' ];
        foreach ( $meta_keys as $key ) {
            $val = get_post_meta( $template_id, $key, true );
            if ( $val ) {
                update_post_meta( $new_id, $key, $val );
            }
        }

        wp_send_json_success( [ 'template_id' => $new_id ] );
    }

    public function ajax_upload_image() {
        check_ajax_referer( self::NONCE_ACTION, 'nonce' );

        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( 'Permission denied.' );
        }

        if ( empty( $_FILES['image'] ) ) {
            wp_send_json_error( 'No file uploaded.' );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $attachment_id = media_handle_upload( 'image', 0 );
        if ( is_wp_error( $attachment_id ) ) {
            wp_send_json_error( $attachment_id->get_error_message() );
        }

        wp_send_json_success( [
            'id'  => $attachment_id,
            'url' => wp_get_attachment_url( $attachment_id ),
        ] );
    }

    /**
     * Get compiled HTML for a given template ID.
     */
    public static function get_compiled_html( $template_id, $variables = [] ) {
        $json = get_post_meta( $template_id, self::META_JSON, true );
        if ( ! $json ) {
            $post = get_post( $template_id );
            $json = $post ? $post->post_content : '';
        }

        $structure = json_decode( $json, true );
        if ( ! $structure ) {
            return '';
        }

        return self::compile_to_html( $structure, $variables );
    }

    /**
     * Get list of all saved templates for use in select dropdowns.
     */
    public static function get_template_options() {
        $templates = get_posts( [
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );

        $options = [ '' => __( '— Select Template —', 'kdna-ecommerce' ) ];
        foreach ( $templates as $tpl ) {
            $options[ $tpl->ID ] = $tpl->post_title;
        }
        return $options;
    }

    // ===================================================================
    // Admin Template List & Builder Pages
    // ===================================================================

    public function render_templates_page() {
        $action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : 'list';
        $tpl_id = intval( $_GET['template_id'] ?? 0 );

        echo '<div class="wrap">';

        if ( $action === 'edit' || $action === 'new' ) {
            $this->render_builder( $tpl_id );
        } else {
            $this->render_template_list();
        }

        echo '</div>';
    }

    private function render_template_list() {
        $templates = get_posts( [
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => 100,
            'orderby'        => 'modified',
            'order'          => 'DESC',
        ] );
        ?>
        <h1 class="wp-heading-inline"><?php esc_html_e( 'Email Templates', 'kdna-ecommerce' ); ?></h1>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=kdna-email-templates&action=new' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add New', 'kdna-ecommerce' ); ?></a>
        <hr class="wp-header-end">

        <?php if ( empty( $templates ) ) : ?>
            <p><?php esc_html_e( 'No email templates yet. Create your first one!', 'kdna-ecommerce' ); ?></p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Template Name', 'kdna-ecommerce' ); ?></th>
                        <th><?php esc_html_e( 'Last Modified', 'kdna-ecommerce' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'kdna-ecommerce' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $templates as $tpl ) : ?>
                    <tr>
                        <td>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=kdna-email-templates&action=edit&template_id=' . $tpl->ID ) ); ?>">
                                <strong><?php echo esc_html( $tpl->post_title ); ?></strong>
                            </a>
                        </td>
                        <td><?php echo esc_html( get_the_modified_date( 'M j, Y g:i a', $tpl ) ); ?></td>
                        <td>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=kdna-email-templates&action=edit&template_id=' . $tpl->ID ) ); ?>"><?php esc_html_e( 'Edit', 'kdna-ecommerce' ); ?></a>
                            | <a href="#" class="kdna-etb-duplicate" data-id="<?php echo esc_attr( $tpl->ID ); ?>"><?php esc_html_e( 'Duplicate', 'kdna-ecommerce' ); ?></a>
                            | <a href="#" class="kdna-etb-delete" data-id="<?php echo esc_attr( $tpl->ID ); ?>" style="color:#a00;"><?php esc_html_e( 'Delete', 'kdna-ecommerce' ); ?></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif;
    }

    private function render_builder( $template_id = 0 ) {
        $name      = '';
        $json_data = wp_json_encode( self::get_default_structure() );
        $css       = '';

        if ( $template_id ) {
            $post = get_post( $template_id );
            if ( $post ) {
                $name      = $post->post_title;
                $json_data = get_post_meta( $template_id, self::META_JSON, true ) ?: $post->post_content;
                $css       = get_post_meta( $template_id, self::META_CSS, true ) ?: '';
            }
        }

        wp_enqueue_media();
        wp_enqueue_editor();
        $this->enqueue_builder_assets();
        ?>
        <h1><?php echo $template_id ? esc_html__( 'Edit Email Template', 'kdna-ecommerce' ) : esc_html__( 'New Email Template', 'kdna-ecommerce' ); ?></h1>

        <div id="kdna-email-builder"
             data-nonce="<?php echo esc_attr( wp_create_nonce( self::NONCE_ACTION ) ); ?>"
             data-template-id="<?php echo esc_attr( $template_id ); ?>"
             data-template-name="<?php echo esc_attr( $name ); ?>"
             data-json="<?php echo esc_attr( $json_data ); ?>"
             data-css="<?php echo esc_attr( $css ); ?>"
             data-blocks="<?php echo esc_attr( wp_json_encode( self::get_block_definitions() ) ); ?>"
             data-ajax-url="<?php echo esc_attr( admin_url( 'admin-ajax.php' ) ); ?>">

            <!-- Builder UI rendered by JS -->
            <div class="kdna-etb-loading">
                <span class="spinner is-active"></span>
                <?php esc_html_e( 'Loading builder...', 'kdna-ecommerce' ); ?>
            </div>
        </div>
        <?php
    }

    private function enqueue_builder_assets() {
        wp_enqueue_style(
            'kdna-email-builder',
            KDNA_ECOMMERCE_URL . 'includes/email-builder/email-builder.css',
            [],
            KDNA_ECOMMERCE_VERSION
        );
        wp_enqueue_script(
            'kdna-email-builder',
            KDNA_ECOMMERCE_URL . 'includes/email-builder/email-builder.js',
            [ 'jquery', 'jquery-ui-sortable', 'jquery-ui-draggable', 'jquery-ui-droppable', 'wp-color-picker' ],
            KDNA_ECOMMERCE_VERSION,
            true
        );
        wp_enqueue_style( 'wp-color-picker' );
    }
}
