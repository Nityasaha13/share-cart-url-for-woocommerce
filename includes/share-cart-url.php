<?php
if ( ! defined( 'ABSPATH' ) ) {
    die("No direct access!");
}

if ( ! class_exists( 'Share_Cart_URL' ) ) {

    class Share_Cart_URL {
    
        public function __construct() {
            $this->init();
        }

        private static $session_cart_keys = array(
            'cart', 'cart_totals', 'applied_coupons', 'coupon_discount_totals', 'coupon_discount_tax_totals'
        );

        public function init() {
            // Get the button position (hook) from settings. Default to 'woocommerce_before_cart_table'.
            $position = get_option( 'scurl_button_position', 'woocommerce_before_cart_table' );
            add_action( $position, array( __CLASS__, 'scurl_render_share_cart_interface' ) );

            add_action( 'woocommerce_load_cart_from_session', array( __CLASS__, 'scurl_apply_shared_cart_session' ), 1 );
            add_filter( 'woocommerce_update_cart_action_cart_updated', array( __CLASS__, 'scurl_update_cart' ), 10, 1 );
            add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'scurl_apply_custom_prices' ), 10, 1 );
            add_action( 'wp_ajax_generate_share_link', array( __CLASS__, 'scurl_ajax_generate_share_link' ) );
            add_action( 'wp_ajax_nopriv_generate_share_link', array( __CLASS__, 'scurl_ajax_generate_share_link' ) );
        }

        public static function scurl_get_session_cart() {
            $cart_session = array();
            foreach ( self::$session_cart_keys as $key ) {
                $cart_session[ $key ] = WC()->session->get( $key );
            }
            return serialize( $cart_session );
        }

        public static function scurl_apply_shared_cart_session() {
            if ( isset( $_REQUEST['share'] ) ) {
                $hash = sanitize_file_name( $_REQUEST['share'] );
                $file = get_temp_dir() . $hash;
                if ( file_exists( $file ) ) {
                    $cart = unserialize( file_get_contents( $file ) );
                    foreach ( self::$session_cart_keys as $key ) {
                        WC()->session->set( $key, $cart[ $key ] );
                    }
                }
            }
        }

        public static function scurl_render_share_cart_interface() {
            ?>
            <button id="share-cart-btn"><?php esc_html_e( 'Share this cart', 'share-cart-for-woocommerce' ); ?></button>
            <div id="share-cart-url"></div>
            <script>
            jQuery(document).ready(function($) {
                $('#share-cart-btn').on('click', function(e) {
                    e.preventDefault();
                    $.post("<?php echo esc_url(admin_url('admin-ajax.php')); ?>", { action: 'generate_share_link' }, function(response) {
                        if (response.success) {
                            var shareUrl = response.data.url;
                            if (navigator.clipboard && window.isSecureContext) {
                                navigator.clipboard.writeText(shareUrl).then(function() {
                                    $('#share-cart-url').html('<p><?php echo esc_html__('Link copied to clipboard:', 'share-cart-for-woocommerce'); ?> ' + shareUrl + '</p>');
                                    $('#share-cart-btn').hide();
                                });
                            } else {
                                var tempInput = $('<input>');
                                $('body').append(tempInput);
                                tempInput.val(shareUrl).select();
                                document.execCommand("copy");
                                tempInput.remove();
                                $('#share-cart-url').html('<span><?php echo esc_html__('Link copied to clipboard - ', 'share-cart-for-woocommerce'); ?> ' + shareUrl + '</span>');
                                $('#share-cart-btn').hide();
                                $('#share-cart-url').css({
                                    'margin-bottom': '10px',
                                    'background': 'rgb(241 241 241)',
                                    'padding': '0.6em 1em'
                                });
                            }
                        }
                    });
                });
                // Reset the share link and show the button when the cart updates.
                $(document.body).on('updated_wc_div', function() {
                    $('#share-cart-url').empty();
                    $('#share-cart-btn').show();
                });
            });
            </script>
            <style>
                #share-cart-url span{
                    font-size: 14px;
                }
                #share-cart-btn{
                    margin-bottom: 10px;
                }
            </style>
            <?php
        }

        public static function scurl_ajax_generate_share_link() {
            $session_cart = self::scurl_get_session_cart();
            $hash         = wp_hash( $session_cart );
            file_put_contents( get_temp_dir() . $hash, $session_cart );
            $share_url = esc_url( wc_get_cart_url() . '?share=' . $hash );
            wp_send_json_success( array( 'url' => $share_url ) );
        }

        public static function scurl_update_cart( $cart_updated ) {
            if ( isset( $_REQUEST['cart'] ) ) {
                $cart = WC()->cart->get_cart();
                foreach ( $_REQUEST['cart'] as $key => $data ) {
                    if ( isset( $data['custom_price'] ) && isset( $cart[ $key ] ) ) {
                        $cart[ $key ]['scurl_price'] = $data['custom_price'];
                    }
                }
                WC()->cart->set_cart_contents( $cart );
            }
            return $cart_updated;
        }

        public static function scurl_apply_custom_prices( $cart ) {
            $cart_contents = WC()->cart->get_cart();
            foreach ( $cart_contents as $key => $value ) {
                if ( isset( $value['scurl_price'] ) ) {
                    $value['data']->set_price( $value['scurl_price'] );
                }
            }
        }
    }

    new Share_Cart_URL();
}
