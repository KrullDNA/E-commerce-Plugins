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
        'woo_content' => [ 'label' => 'WooCommerce Content', 'icon' => 'dashicons-cart' ],
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

        // WooCommerce email integration.
        add_filter( 'woocommerce_mail_content', [ $this, 'wrap_woo_email' ] );

        // Override WooCommerce email header/footer templates with minimal versions
        // when a custom KDNA template is active.
        add_filter( 'woocommerce_locate_template', [ $this, 'override_woo_email_templates' ], 10, 3 );
    }

    /**
     * Redirect WooCommerce email-header.php and email-footer.php to minimal
     * versions so the custom KDNA template provides the wrapper instead.
     */
    public function override_woo_email_templates( $template, $template_name, $template_path ) {
        if ( ! in_array( $template_name, [ 'emails/email-header.php', 'emails/email-footer.php' ], true ) ) {
            return $template;
        }

        $template_id = (int) get_option( 'kdna_woo_email_template_id', 0 );
        if ( ! $template_id ) {
            return $template;
        }

        $override_dir = KDNA_ECOMMERCE_PATH . 'includes/email-builder/woo-overrides/';
        $override_file = $override_dir . basename( $template_name );

        if ( file_exists( $override_file ) ) {
            return $override_file;
        }

        return $template;
    }

    /**
     * Wrap WooCommerce transactional email content with a builder template.
     *
     * Hooks into woocommerce_mail_content to replace WooCommerce's default
     * header/footer with the selected builder template. The template must
     * contain a {woo_email_content} placeholder (via the WooCommerce Content block).
     */
    public function wrap_woo_email( $html ) {
        $template_id = (int) get_option( 'kdna_woo_email_template_id', 0 );
        if ( ! $template_id ) {
            return $html;
        }

        $json = get_post_meta( $template_id, self::META_JSON, true );
        if ( ! $json ) {
            return $html;
        }

        $structure = json_decode( $json, true );
        if ( ! $structure ) {
            return $html;
        }

        // Check if the template actually uses the woo_content block.
        $has_woo_block = false;
        foreach ( ( $structure['rows'] ?? [] ) as $row ) {
            foreach ( ( $row['blocks'] ?? [] ) as $block ) {
                if ( ( $block['type'] ?? '' ) === 'woo_content' ) {
                    $has_woo_block = true;
                    break 2;
                }
            }
        }

        if ( ! $has_woo_block ) {
            return $html;
        }

        // Extract the <body> content from WooCommerce's email HTML.
        // With our template overrides active, WC outputs a minimal HTML shell
        // (just <html><body>content</body></html>) so we only need to extract body.
        $body_content = $html;
        if ( preg_match( '/<body[^>]*>(.*)<\/body>/si', $html, $matches ) ) {
            $body_content = trim( $matches[1] );
        }

        // Fallback: if WC templates were not overridden (e.g. theme override takes
        // priority), strip the WooCommerce wrapper tables to get inner content.
        if ( preg_match( '/id\s*=\s*["\']body_content_inner["\'][^>]*>(.*)/si', $body_content, $inner ) ) {
            $inner_html = $inner[1];
            // Remove trailing closing tags from the WooCommerce wrapper tables.
            $inner_html = preg_replace( '/(<\/td>\s*<\/tr>\s*<\/table>\s*){2,}.*$/si', '', $inner_html );
            $body_content = trim( $inner_html );
        }

        $compiled = self::compile_to_html( $structure, [
            'woo_email_content' => $body_content,
            'store_name'        => get_bloginfo( 'name' ),
            'store_url'         => home_url(),
            'site_title'        => get_bloginfo( 'name' ),
            'unsubscribe_url'   => '#',
        ] );

        return $compiled;
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
                'width'    => '100%',
                'height'   => '40px',
                'bg_color' => '#f7f7f7',
                'padding'  => '0px',
            ],
            'content' => [
                'padding' => '10px 20px',
            ],
            'woo_content' => [
                'padding' => '10px 20px',
            ],
        ];

        return $defaults[ $type ] ?? [];
    }

    /**
     * Escape a value for use inside a CSS style block.
     * Unlike esc_attr(), this preserves single quotes (needed for font-family)
     * while stripping characters that could break out of a style context.
     */
    private static function esc_css( $value ) {
        $value = str_replace( [ '<', '>', '&' ], '', $value );
        $value = preg_replace( '/[;\{\}]/', '', $value );
        return $value;
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
        $html .= '<style>body{margin:0;padding:0;background:' . self::esc_css( $bg ) . ';font-family:' . self::esc_css( $font_family ) . ';font-size:' . self::esc_css( $font_size ) . ';line-height:' . self::esc_css( $line_height ) . ';color:' . self::esc_css( $text_color ) . ';}';
        $html .= 'a{color:' . self::esc_css( $link_color ) . ';}';
        $html .= 'img{max-width:100%;height:auto;}';
        $html .= 'table{border-collapse:collapse;}';
        $html .= '.email-row{width:100%;}';
        $mobile_css = self::collect_mobile_css( $structure );
        $html .= '@media only screen and (max-width:480px){';
        $html .= '.email-content{width:100% !important;}';
        $html .= '.email-content td{display:block !important;width:100% !important;}';
        $html .= 'img{max-width:100% !important;height:auto !important;}';
        $html .= $mobile_css;
        $html .= '}';
        $html .= '</style></head><body>';

        if ( $preheader ) {
            $html .= '<div style="display:none;font-size:1px;color:' . esc_attr( $bg ) . ';line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">' . esc_html( $preheader ) . '</div>';
        }

        $font_style = 'font-family:' . $font_family . ';font-size:' . esc_attr( $font_size ) . ';line-height:' . esc_attr( $line_height ) . ';color:' . esc_attr( $text_color ) . ';';
        $html .= '<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:' . esc_attr( $bg ) . ';">';
        $html .= '<tr><td align="center" style="padding:' . esc_attr( $padding ) . ';' . $font_style . '">';
        $html .= '<table class="email-content" cellpadding="0" cellspacing="0" role="presentation" style="width:100%;max-width:' . intval( $width ) . 'px;background:' . esc_attr( $content_bg ) . ';border-radius:' . esc_attr( $s['border_radius'] ?? '0px' ) . ';overflow:hidden;">';

        foreach ( ( $structure['rows'] ?? [] ) as $row_index => $row ) {
            $html .= self::compile_row( $row, $s, $row_index );
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
    private static function compile_row( $row, $settings, $row_index = 0 ) {
        $bg      = ! empty( $row['bg_color'] ) ? 'background:' . esc_attr( $row['bg_color'] ) . ';' : '';
        $padding = ! empty( $row['padding'] ) ? 'padding:' . esc_attr( $row['padding'] ) . ';' : '';
        $html    = '<tr><td style="' . $bg . $padding . '">';

        foreach ( ( $row['blocks'] ?? [] ) as $block_index => $block ) {
            $html .= self::compile_block( $block, $settings, $row_index, $block_index );
        }

        $html .= '</td></tr>';
        return $html;
    }

    /**
     * Compile a single block to HTML.
     */
    private static function compile_block( $block, $settings, $row_index = -1, $block_index = -1 ) {
        $type = $block['type'] ?? 'text';
        $p    = $block['props'] ?? [];
        $has_mobile  = ! empty( $p['mobile'] ) && $row_index >= 0;
        $block_class = $has_mobile ? 'kdna-b-' . $row_index . '-' . $block_index : '';

        $output = '';

        switch ( $type ) {
            case 'text':
                $align  = $p['text_align'] ?? 'left';
                $output = '<div style="padding:' . esc_attr( $p['padding'] ?? '10px 20px' ) . ';text-align:' . esc_attr( $align ) . ';">' . wp_kses_post( $p['content'] ?? '' ) . '</div>';
                break;

            case 'heading':
                $tag   = in_array( ( $p['tag'] ?? 'h2' ), [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ], true ) ? $p['tag'] : 'h2';
                $color = ! empty( $p['color'] ) ? 'color:' . esc_attr( $p['color'] ) . ';' : 'color:' . esc_attr( $settings['heading_color'] ?? '#1a1a1a' ) . ';';
                $size  = ! empty( $p['font_size'] ) ? 'font-size:' . esc_attr( $p['font_size'] ) . ';' : '';
                $align = $p['text_align'] ?? 'center';
                $output = '<' . $tag . ' style="padding:' . esc_attr( $p['padding'] ?? '10px 20px' ) . ';text-align:' . esc_attr( $align ) . ';margin:0;' . $color . $size . '">' . esc_html( $p['content'] ?? '' ) . '</' . $tag . '>';
                break;

            case 'image':
                $align = $p['text_align'] ?? 'center';
                $img   = '<img src="' . esc_url( $p['src'] ?? '' ) . '" alt="' . esc_attr( $p['alt'] ?? '' ) . '" style="width:' . esc_attr( $p['width'] ?? '100%' ) . ';max-width:100%;height:auto;display:inline-block;" />';
                if ( ! empty( $p['href'] ) ) {
                    $img = '<a href="' . esc_url( $p['href'] ) . '">' . $img . '</a>';
                }
                $output = '<div style="padding:' . esc_attr( $p['padding'] ?? '10px 20px' ) . ';text-align:' . esc_attr( $align ) . ';">' . $img . '</div>';
                break;

            case 'button':
                $align     = $p['text_align'] ?? 'center';
                $fw        = ! empty( $p['full_width'] ) ? 'display:block;width:100%;' : 'display:inline-block;';
                $btn_style = $fw . 'background:' . esc_attr( $p['bg_color'] ?? '#0073aa' ) . ';color:' . esc_attr( $p['text_color'] ?? '#fff' ) . ';padding:' . esc_attr( $p['padding'] ?? '12px 24px' ) . ';border-radius:' . esc_attr( $p['border_radius'] ?? '4px' ) . ';font-size:' . esc_attr( $p['font_size'] ?? '16px' ) . ';font-weight:' . esc_attr( $p['font_weight'] ?? 'bold' ) . ';text-decoration:none;text-align:center;';
                $output    = '<div style="padding:' . esc_attr( $p['container_padding'] ?? '10px 20px' ) . ';text-align:' . esc_attr( $align ) . ';"><a href="' . esc_url( $p['href'] ?? '#' ) . '" style="' . $btn_style . '">' . esc_html( $p['text'] ?? 'Click' ) . '</a></div>';
                break;

            case 'divider':
                $output = '<div style="padding:' . esc_attr( $p['padding'] ?? '10px 20px' ) . ';"><hr style="border:none;border-top:' . esc_attr( $p['thickness'] ?? '1px' ) . ' ' . esc_attr( $p['style'] ?? 'solid' ) . ' ' . esc_attr( $p['color'] ?? '#e0e0e0' ) . ';margin:0;width:' . esc_attr( $p['width'] ?? '100%' ) . ';" /></div>';
                break;

            case 'spacer':
                $output = '<div style="height:' . esc_attr( $p['height'] ?? '20px' ) . ';"></div>';
                break;

            case 'columns':
                $cols    = intval( $p['columns'] ?? 2 );
                $gap     = $p['gap'] ?? '10px';
                $padding = $p['padding'] ?? '10px 20px';
                $widths  = self::get_column_widths( $p['layout'] ?? '50-50', $cols );
                $output  = '<div style="padding:' . esc_attr( $padding ) . ';"><table width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr>';
                for ( $i = 0; $i < $cols; $i++ ) {
                    $col_blocks = $p['col_blocks'][ $i ] ?? [];
                    $w = $widths[ $i ] ?? ( 100 / $cols ) . '%';
                    $output .= '<td style="width:' . esc_attr( $w ) . ';vertical-align:top;padding:0 ' . esc_attr( $gap ) . ';">';
                    foreach ( $col_blocks as $cb ) {
                        $output .= self::compile_block( $cb, $settings );
                    }
                    $output .= '</td>';
                }
                $output .= '</tr></table></div>';
                break;

            case 'social':
                $align   = $p['text_align'] ?? 'center';
                $size    = $p['icon_size'] ?? '32px';
                $spacing = $p['icon_style'] ?? 'color';
                $gap     = $p['spacing'] ?? '8px';
                $urls    = $p['urls'] ?? [];
                $icons   = $p['icons'] ?? [ 'facebook', 'twitter', 'instagram' ];
                $output  = '<div style="padding:' . esc_attr( $p['padding'] ?? '10px 20px' ) . ';text-align:' . esc_attr( $align ) . ';">';
                foreach ( $icons as $icon ) {
                    $url = $urls[ $icon ] ?? '#';
                    if ( empty( $url ) ) { $url = '#'; }
                    $label = ucfirst( $icon );
                    $output .= '<a href="' . esc_url( $url ) . '" style="display:inline-block;margin:0 ' . esc_attr( $gap ) . ';text-decoration:none;" title="' . esc_attr( $label ) . '">';
                    $output .= '<img src="' . esc_url( KDNA_ECOMMERCE_URL . 'includes/email-builder/icons/' . $icon . '.png' ) . '" alt="' . esc_attr( $label ) . '" width="' . intval( $size ) . '" height="' . intval( $size ) . '" style="border:0;" />';
                    $output .= '</a>';
                }
                $output .= '</div>';
                break;

            case 'video':
                $align = $p['text_align'] ?? 'center';
                $thumb = $p['thumbnail'] ?? '';
                $url   = $p['url'] ?? '';
                if ( $thumb ) {
                    $img = '<a href="' . esc_url( $url ) . '"><img src="' . esc_url( $thumb ) . '" style="width:100%;display:block;" /></a>';
                } else {
                    $img = '<a href="' . esc_url( $url ) . '" style="display:block;padding:40px;background:#000;color:#fff;text-align:center;font-size:24px;">&#9654; Play Video</a>';
                }
                $output = '<div style="padding:' . esc_attr( $p['padding'] ?? '10px 20px' ) . ';text-align:' . esc_attr( $align ) . ';">' . $img . '</div>';
                break;

            case 'html':
                $output = '<div style="padding:' . esc_attr( $p['padding'] ?? '10px 20px' ) . ';">' . ( $p['content'] ?? '' ) . '</div>';
                break;

            case 'logo':
                $align = $p['text_align'] ?? 'center';
                $img   = '<img src="' . esc_url( $p['src'] ?? '' ) . '" alt="Logo" style="width:' . esc_attr( $p['width'] ?? '150px' ) . ';max-width:100%;height:auto;" />';
                if ( ! empty( $p['href'] ) ) {
                    $img = '<a href="' . esc_url( $p['href'] ) . '">' . $img . '</a>';
                }
                $output = '<div style="padding:' . esc_attr( $p['padding'] ?? '20px' ) . ';text-align:' . esc_attr( $align ) . ';">' . $img . '</div>';
                break;

            case 'product':
                $output = '<div style="padding:' . esc_attr( $p['padding'] ?? '10px 20px' ) . ';">' .
                    '<div style="text-align:center;padding:20px;border:1px solid #eee;border-radius:8px;">' .
                    '<p style="color:#999;font-size:13px;">[Product card - rendered dynamically at send time]</p>' .
                    '</div></div>';
                break;

            case 'coupon':
                $border = $p['border_color'] ?? '#0073aa';
                $bg     = $p['bg_color'] ?? '#f0f9ff';
                $output = '<div style="padding:' . esc_attr( $p['padding'] ?? '10px 20px' ) . ';">' .
                    '<div style="border:2px dashed ' . esc_attr( $border ) . ';background:' . esc_attr( $bg ) . ';border-radius:8px;padding:20px;text-align:center;">' .
                    '<div class="kdna-coupon-code" style="font-family:monospace;font-size:' . esc_attr( $p['code_font_size'] ?? '20px' ) . ';font-weight:bold;letter-spacing:2px;color:' . esc_attr( $p['text_color'] ?? '#333' ) . ';">' . esc_html( $p['code_variable'] ?? '{coupon_code}' ) . '</div>' .
                    ( ! empty( $p['show_expiry'] ) ? '<div style="font-size:12px;color:#999;margin-top:8px;">{coupon_expiry}</div>' : '' ) .
                    '</div></div>';
                break;

            case 'footer':
                $bg    = ! empty( $p['bg_color'] ) ? 'background:' . esc_attr( $p['bg_color'] ) . ';' : '';
                $align = $p['text_align'] ?? 'center';
                $output = '<div style="padding:' . esc_attr( $p['padding'] ?? '20px' ) . ';' . $bg . 'text-align:' . esc_attr( $align ) . ';">' . wp_kses_post( $p['content'] ?? '' ) . '</div>';
                break;

            case 'menu':
                $align = $p['text_align'] ?? 'center';
                $sep   = esc_html( $p['separator'] ?? ' | ' );
                $size  = $p['font_size'] ?? '13px';
                $items = $p['items'] ?? [];
                $links = [];
                foreach ( $items as $item ) {
                    $links[] = '<a href="' . esc_url( $item['url'] ?? '#' ) . '" style="font-size:' . esc_attr( $size ) . ';text-decoration:none;">' . esc_html( $item['label'] ?? '' ) . '</a>';
                }
                $output = '<div style="padding:' . esc_attr( $p['padding'] ?? '10px 20px' ) . ';text-align:' . esc_attr( $align ) . ';">' . implode( $sep, $links ) . '</div>';
                break;

            case 'order_items':
                $output = '<div style="padding:' . esc_attr( $p['padding'] ?? '10px 20px' ) . ';">' .
                    '<table width="100%" style="border-collapse:collapse;">' .
                    '<tr style="background:#f7f7f7;"><th style="padding:8px;text-align:left;border-bottom:1px solid #e0e0e0;">Item</th>' .
                    ( ! empty( $p['show_quantity'] ) ? '<th style="padding:8px;text-align:center;border-bottom:1px solid #e0e0e0;">Qty</th>' : '' ) .
                    ( ! empty( $p['show_price'] ) ? '<th style="padding:8px;text-align:right;border-bottom:1px solid #e0e0e0;">Price</th>' : '' ) .
                    '</tr><tr><td colspan="3" style="padding:12px;text-align:center;color:#999;font-size:13px;">[Order items rendered at send time]</td></tr></table></div>';
                break;

            case 'blank_row':
                $output = '<div style="width:' . esc_attr( $p['width'] ?? '100%' ) . ';height:' . esc_attr( $p['height'] ?? '40px' ) . ';background:' . esc_attr( $p['bg_color'] ?? '#f7f7f7' ) . ';padding:' . esc_attr( $p['padding'] ?? '0px' ) . ';box-sizing:border-box;"></div>';
                break;

            case 'content':
                $output = '<div style="padding:' . esc_attr( $p['padding'] ?? '10px 20px' ) . ';">{email_content}</div>';
                break;

            case 'woo_content':
                $output = '<div style="padding:' . esc_attr( $p['padding'] ?? '10px 20px' ) . ';">{woo_email_content}</div>';
                break;
        }

        // Wrap with responsive class if block has mobile overrides.
        if ( $has_mobile && $output ) {
            $output = '<div class="' . esc_attr( $block_class ) . '">' . $output . '</div>';
        }

        return $output;
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

    /**
     * Collect mobile override CSS rules from all blocks.
     * Returns CSS rules to be placed inside a @media (max-width:480px) block.
     */
    private static function collect_mobile_css( $structure ) {
        $css = '';

        foreach ( ( $structure['rows'] ?? [] ) as $ri => $row ) {
            foreach ( ( $row['blocks'] ?? [] ) as $bi => $block ) {
                $m = $block['props']['mobile'] ?? [];
                if ( empty( $m ) ) {
                    continue;
                }

                $type  = $block['type'] ?? 'text';
                $sel   = '.kdna-b-' . $ri . '-' . $bi;
                $rules = [];

                // Padding — applies to the wrapper div for most block types.
                if ( ! empty( $m['padding'] ) ) {
                    $rules[] = 'padding:' . esc_attr( $m['padding'] ) . ' !important';
                }

                // Text alignment.
                if ( ! empty( $m['text_align'] ) ) {
                    $rules[] = 'text-align:' . esc_attr( $m['text_align'] ) . ' !important';
                }

                // Height — spacer and blank_row.
                if ( ! empty( $m['height'] ) && in_array( $type, [ 'spacer', 'blank_row' ], true ) ) {
                    $rules[] = 'height:' . esc_attr( $m['height'] ) . ' !important';
                }

                // Width — blank_row wrapper.
                if ( ! empty( $m['width'] ) && $type === 'blank_row' ) {
                    $rules[] = 'width:' . esc_attr( $m['width'] ) . ' !important';
                }

                // Container padding — button wrapper.
                if ( ! empty( $m['container_padding'] ) && $type === 'button' ) {
                    $rules[] = 'padding:' . esc_attr( $m['container_padding'] ) . ' !important';
                }

                if ( $rules ) {
                    $css .= $sel . ' > *{' . implode( ';', $rules ) . ';}';
                }

                // Font size — type-specific targeting.
                if ( ! empty( $m['font_size'] ) ) {
                    if ( $type === 'heading' ) {
                        $tag = $block['props']['tag'] ?? 'h2';
                        $css .= $sel . ' ' . $tag . '{font-size:' . esc_attr( $m['font_size'] ) . ' !important;}';
                    } elseif ( $type === 'button' ) {
                        $css .= $sel . ' a{font-size:' . esc_attr( $m['font_size'] ) . ' !important;}';
                    } elseif ( $type === 'coupon' ) {
                        $css .= $sel . ' .kdna-coupon-code{font-size:' . esc_attr( $m['font_size'] ) . ' !important;}';
                    } elseif ( $type === 'menu' ) {
                        $css .= $sel . ' a{font-size:' . esc_attr( $m['font_size'] ) . ' !important;}';
                    }
                }

                // Image/logo width.
                if ( ! empty( $m['width'] ) && in_array( $type, [ 'image', 'logo' ], true ) ) {
                    $css .= $sel . ' img{width:' . esc_attr( $m['width'] ) . ' !important;}';
                }

                // Social icon size.
                if ( ! empty( $m['icon_size'] ) && $type === 'social' ) {
                    $sz = intval( $m['icon_size'] );
                    $css .= $sel . ' img{width:' . $sz . 'px !important;height:' . $sz . 'px !important;}';
                }

                // Stack columns on mobile.
                if ( ! empty( $m['stack_on_mobile'] ) && $type === 'columns' ) {
                    $css .= $sel . ' table{display:block !important;}';
                    $css .= $sel . ' tr{display:block !important;}';
                    $css .= $sel . ' td{display:block !important;width:100% !important;padding-bottom:' . esc_attr( $m['gap'] ?? $block['props']['gap'] ?? '10px' ) . ' !important;}';
                }
            }
        }

        return $css;
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
            'post_content' => wp_slash( $json ),
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

        update_post_meta( $template_id, self::META_JSON, wp_slash( $json ) );
        update_post_meta( $template_id, self::META_CSS, $custom_css );

        // Generate compiled HTML and store it.
        $compiled = self::compile_to_html( $decoded );
        update_post_meta( $template_id, '_kdna_email_compiled_html', $compiled );

        // WooCommerce email template toggle.
        $use_for_woo = sanitize_text_field( $_POST['use_for_woo'] ?? '0' );
        if ( $use_for_woo === '1' ) {
            update_option( 'kdna_woo_email_template_id', $template_id );
        } elseif ( (int) get_option( 'kdna_woo_email_template_id', 0 ) === $template_id ) {
            // Unchecked and this was the active WooCommerce template — clear it.
            update_option( 'kdna_woo_email_template_id', 0 );
        }

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
            'woo_email_content'   => '<div style="padding:15px;border:1px solid #e0e0e0;border-radius:4px;margin:10px 0;">'
                . '<h2 style="margin:0 0 10px;font-size:18px;">Order #1234</h2>'
                . '<table width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse;font-size:13px;">'
                . '<tr style="background:#f7f7f7;"><td style="border:1px solid #e0e0e0;"><strong>Product</strong></td><td style="border:1px solid #e0e0e0;text-align:center;"><strong>Qty</strong></td><td style="border:1px solid #e0e0e0;text-align:right;"><strong>Price</strong></td></tr>'
                . '<tr><td style="border:1px solid #e0e0e0;">Sample Product</td><td style="border:1px solid #e0e0e0;text-align:center;">1</td><td style="border:1px solid #e0e0e0;text-align:right;">$49.99</td></tr>'
                . '<tr><td style="border:1px solid #e0e0e0;">Another Item</td><td style="border:1px solid #e0e0e0;text-align:center;">2</td><td style="border:1px solid #e0e0e0;text-align:right;">$25.00</td></tr>'
                . '<tr style="background:#f7f7f7;"><td colspan="2" style="border:1px solid #e0e0e0;text-align:right;"><strong>Total:</strong></td><td style="border:1px solid #e0e0e0;text-align:right;"><strong>$99.99</strong></td></tr>'
                . '</table>'
                . '<p style="margin:15px 0 5px;font-size:13px;"><strong>Billing address:</strong><br>John Doe<br>123 Sample St<br>Sydney NSW 2000</p>'
                . '</div>',
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

        // WooCommerce email template selector.
        $this->render_woo_email_settings( $templates ?? [] );
    }

    /**
     * Render the WooCommerce email template selector on the template list page.
     */
    private function render_woo_email_settings( $templates ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }

        // Handle save.
        if ( isset( $_POST['kdna_woo_email_template_save'] ) && check_admin_referer( 'kdna_woo_email_template_nonce' ) ) {
            update_option( 'kdna_woo_email_template_id', intval( $_POST['kdna_woo_email_template_id'] ?? 0 ) );
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'WooCommerce email template setting saved.', 'kdna-ecommerce' ) . '</p></div>';
        }

        $current_id = (int) get_option( 'kdna_woo_email_template_id', 0 );
        ?>
        <hr style="margin:30px 0 20px;">
        <h2><?php esc_html_e( 'WooCommerce Email Template', 'kdna-ecommerce' ); ?></h2>
        <p class="description"><?php esc_html_e( 'Select a template to wrap WooCommerce transactional emails (order confirmation, shipping, etc.). The template must contain a "WooCommerce Content" block where the order details will be inserted.', 'kdna-ecommerce' ); ?></p>
        <form method="post">
            <?php wp_nonce_field( 'kdna_woo_email_template_nonce' ); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="kdna_woo_email_template_id"><?php esc_html_e( 'Template', 'kdna-ecommerce' ); ?></label></th>
                    <td>
                        <select name="kdna_woo_email_template_id" id="kdna_woo_email_template_id">
                            <option value="0"><?php esc_html_e( '— None (use WooCommerce default) —', 'kdna-ecommerce' ); ?></option>
                            <?php foreach ( $templates as $tpl ) : ?>
                                <option value="<?php echo esc_attr( $tpl->ID ); ?>" <?php selected( $current_id, $tpl->ID ); ?>><?php echo esc_html( $tpl->post_title ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
            <?php submit_button( __( 'Save WooCommerce Template', 'kdna-ecommerce' ), 'secondary', 'kdna_woo_email_template_save' ); ?>
        </form>
        <?php
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

        <?php $is_woo_template = ( (int) get_option( 'kdna_woo_email_template_id', 0 ) === $template_id && $template_id > 0 ); ?>
        <div id="kdna-email-builder"
             data-nonce="<?php echo esc_attr( wp_create_nonce( self::NONCE_ACTION ) ); ?>"
             data-template-id="<?php echo esc_attr( $template_id ); ?>"
             data-template-name="<?php echo esc_attr( $name ); ?>"
             data-json="<?php echo esc_attr( $json_data ); ?>"
             data-css="<?php echo esc_attr( $css ); ?>"
             data-blocks="<?php echo esc_attr( wp_json_encode( self::get_block_definitions() ) ); ?>"
             data-ajax-url="<?php echo esc_attr( admin_url( 'admin-ajax.php' ) ); ?>"
             data-woo-template="<?php echo $is_woo_template ? '1' : '0'; ?>">

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
