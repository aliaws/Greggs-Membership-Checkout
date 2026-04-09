<?php
/**
 * Plugin Name: Greggs Membership Checkout
 * Description: Bypasses cart for membership products and simplifies checkout.
 * Version: 2.1
 * Author: Accurate Digital
 */

if (!defined('ABSPATH')) exit;

class Greggs_Membership_Checkout {

    private static $instance = null;

    private $pricing_slug = 'pricing';

    private $hear_options = array(
        ''               => 'Select an option...',
        'google'         => 'Google Search',
        'social_media'   => 'Social Media',
        'friend_family'  => 'Friend or Family',
        'facebook'       => 'Facebook',
        'instagram'      => 'Instagram',
        'tiktok'         => 'TikTok',
        'youtube'        => 'YouTube',
        'email'          => 'Email Newsletter',
        'advertisement'  => 'Advertisement',
        'other'          => 'Other',
    );

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_enqueue_scripts',                        array($this, 'enqueue_scripts'));
        add_action('template_redirect',                         array($this, 'redirect_cart_to_checkout'));
        add_filter('woocommerce_add_to_cart_validation',        array($this, 'clear_cart_before_add'), 1, 2);
        add_filter('woocommerce_add_to_cart_redirect',          array($this, 'redirect_to_checkout'));
        add_filter('woocommerce_product_add_to_cart_text',      array($this, 'change_button_text'), 10, 2);
        add_action('woocommerce_after_order_notes',             array($this, 'add_hear_about_field'));
        add_action('woocommerce_checkout_update_order_meta',    array($this, 'save_hear_about_field'));
        add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'show_hear_about_in_admin'));
    }

    public function is_membership_product($product_id) {
        $product = wc_get_product($product_id);
        if (!$product) return false;
        return $product->get_type() === 'ads_simple_subscription';
    }

    private function is_from_pricing_page() {
        $referer = wp_get_referer();
        if (!$referer) return false;
        return strpos($referer, '/' . $this->pricing_slug) !== false;
    }

    private function cart_has_membership() {
        if (!WC()->cart) return false;

        foreach (WC()->cart->get_cart() as $item) {
            if ($this->is_membership_product($item['product_id'])) {
                return true;
            }
        }
        return false;
    }

    // ── Scripts ──

    public function enqueue_scripts() {
        if (!is_page($this->pricing_slug)) return;

        wp_enqueue_script(
            'greggs-checkout-redirect',
            plugin_dir_url(__FILE__) . 'js/checkout-redirect.js',
            array(),
            '2.1',
            true
        );
    }

    // ── Cart & Checkout ──

    public function clear_cart_before_add($passed, $product_id) {
        if ($this->is_membership_product($product_id) && $this->is_from_pricing_page()) {
            WC()->cart->empty_cart();
            WC()->session->set('cart', array());
        }
        return $passed;
    }

    public function redirect_to_checkout($url) {
        if (!isset($_REQUEST['add-to-cart'])) return $url;

        $product_id = (int) $_REQUEST['add-to-cart'];

        if ($this->is_membership_product($product_id) && $this->is_from_pricing_page()) {
            return wc_get_checkout_url();
        }

        return $url;
    }

    public function redirect_cart_to_checkout() {
        if (!is_cart()) return;
        if (!WC()->cart || WC()->cart->is_empty()) return;

        if ($this->cart_has_membership()) {
            wp_safe_redirect(wc_get_checkout_url());
            exit;
        }
    }

    public function change_button_text($text, $product) {
        if ($this->is_membership_product($product->get_id())) {
            return 'Subscribe Now';
        }
        return $text;
    }

    // ── Custom Checkout Field ──

    public function add_hear_about_field($checkout) {
        woocommerce_form_field('hear_about_us', array(
            'type'     => 'select',
            'label'    => 'How Did You Hear About Us?',
            'required' => false,
            'class'    => array('form-row-wide'),
            'options'  => $this->hear_options,
        ), $checkout->get_value('hear_about_us'));
    }

    public function save_hear_about_field($order_id) {
        if (!empty($_POST['hear_about_us'])) {
            update_post_meta($order_id, '_hear_about_us', sanitize_text_field($_POST['hear_about_us']));
        }
    }

    public function show_hear_about_in_admin($order) {
        $value = get_post_meta($order->get_id(), '_hear_about_us', true);
        if ($value) {
            $label = $this->hear_options[$value] ?? $value;
            echo '<p><strong>How Did You Hear About Us:</strong> ' . esc_html($label) . '</p>';
        }
    }
}

Greggs_Membership_Checkout::instance();
