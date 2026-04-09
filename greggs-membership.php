<?php
/**
 * Plugin Name: Greggs Membership Checkout
 * Description: Bypasses cart for membership products and simplifies checkout.
 * Version: 1.7
 * Author: Accurate Digital
 */

if (!defined('ABSPATH')) exit;

function greggs_is_membership_product($product_id) {
    return has_term('membership', 'product_cat', $product_id);
}

function greggs_is_pricing_page() {
    $referer = wp_get_referer();
    if (!$referer) return false;
    return strpos($referer, '/pricing') !== false;
}

// Enqueue JS only on pricing page
add_action('wp_enqueue_scripts', function() {
    if (!is_page('pricing')) return;

    wp_enqueue_script(
        'greggs-checkout-redirect',
        plugin_dir_url(__FILE__) . 'js/checkout-redirect.js',
        array(),
        '1.7',
        true
    );
});

// If membership product in cart and user visits cart page, redirect to checkout
add_action('template_redirect', function() {
    if (!is_cart()) return;
    if (!WC()->cart || WC()->cart->is_empty()) return;

    foreach (WC()->cart->get_cart() as $item) {
        if (greggs_is_membership_product($item['product_id'])) {
            wp_safe_redirect(wc_get_checkout_url());
            exit;
        }
    }
});

// Add "How Did You Hear About Us" dropdown to checkout
add_action('woocommerce_after_order_notes', function($checkout) {
    woocommerce_form_field('hear_about_us', array(
        'type'     => 'select',
        'label'    => 'How Did You Hear About Us?',
        'required' => false,
        'class'    => array('form-row-wide'),
        'options'  => array(
            ''                => 'Select an option...',
            'google'          => 'Google Search',
            'social_media'    => 'Social Media',
            'friend_family'   => 'Friend or Family',
            'facebook'        => 'Facebook',
            'instagram'       => 'Instagram',
            'tiktok'          => 'TikTok',
            'youtube'         => 'YouTube',
            'email'           => 'Email Newsletter',
            'advertisement'   => 'Advertisement',
            'other'           => 'Other',
        ),
    ), $checkout->get_value('hear_about_us'));
});

// Save the field value to order
add_action('woocommerce_checkout_update_order_meta', function($order_id) {
    if (!empty($_POST['hear_about_us'])) {
        update_post_meta($order_id, '_hear_about_us', sanitize_text_field($_POST['hear_about_us']));
    }
});

// Show field in admin order details
add_action('woocommerce_admin_order_data_after_billing_address', function($order) {
    $value = get_post_meta($order->get_id(), '_hear_about_us', true);
    if ($value) {
        $options = array(
            'google'        => 'Google Search',
            'social_media'  => 'Social Media',
            'friend_family' => 'Friend or Family',
            'facebook'      => 'Facebook',
            'instagram'     => 'Instagram',
            'tiktok'        => 'TikTok',
            'youtube'       => 'YouTube',
            'email'         => 'Email Newsletter',
            'advertisement' => 'Advertisement',
            'other'         => 'Other',
        );
        echo '<p><strong>How Did You Hear About Us:</strong> ' . esc_html($options[$value] ?? $value) . '</p>';
    }
});

// Clear cart BEFORE add — only from pricing page
add_filter('woocommerce_add_to_cart_validation', function($passed, $product_id) {
    if (greggs_is_membership_product($product_id) && greggs_is_pricing_page()) {
        WC()->cart->empty_cart();
        WC()->session->set('cart', array());
    }
    return $passed;
}, 1, 2);

// Redirect to checkout — only from pricing page
add_filter('woocommerce_add_to_cart_redirect', function($url) {
    if (!isset($_REQUEST['add-to-cart'])) return $url;

    $product_id = (int) $_REQUEST['add-to-cart'];

    if (greggs_is_membership_product($product_id) && greggs_is_pricing_page()) {
        return wc_get_checkout_url();
    }

    return $url;
});



// Change button text
add_filter('woocommerce_product_add_to_cart_text', function($text, $product) {
    if (greggs_is_membership_product($product->get_id())) {
        return 'Subscribe Now';
    }
    return $text;
}, 10, 2);

// Simplify checkout for membership products
add_filter('woocommerce_checkout_fields', function($fields) {
    if (!WC()->cart) return $fields;

    $has_membership = false;
    foreach (WC()->cart->get_cart() as $item) {
        if (greggs_is_membership_product($item['product_id'])) {
            $has_membership = true;
            break;
        }
    }

    if ($has_membership) {
        unset($fields['shipping']);
        unset($fields['billing']['billing_company']);
        unset($fields['billing']['billing_address_1']);
        unset($fields['billing']['billing_address_2']);
        unset($fields['billing']['billing_city']);
        unset($fields['billing']['billing_postcode']);
        unset($fields['billing']['billing_state']);
        unset($fields['billing']['billing_phone']);
    }

    return $fields;
});
