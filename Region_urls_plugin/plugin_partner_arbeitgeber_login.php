<?php
/**
 * Plugin Name: Region Login URLs
 * Description: Fügt globale Login-URL-Felder und Shortcodes für Partnerportal und Arbeitgeberportal hinzu.
 * Version: 1.0.0
 * Author: Marco Feldhaus
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Menü-Links ersetzen, falls Platzhalter im Menü hinterlegt wurden.
 */
add_filter('nav_menu_link_attributes', 'dcwd_nav_menu_link_attributes', 10, 4);
function dcwd_nav_menu_link_attributes($atts, $item, $args, $depth)
{
    if (!empty($atts['href']) && false !== strpos($atts['href'], '[region_partnerportal_login_url]')) {
        $atts['href'] = do_shortcode('[region_partnerportal_login_url]');
    }

    if (!empty($atts['href']) && false !== strpos($atts['href'], '[region_arbeitgeberportal_login_url]')) {
        $atts['href'] = do_shortcode('[region_arbeitgeberportal_login_url]');
    }

    return $atts;
}

/**
 * Optionen auslesen.
 */
function get_white_label_partnerportal_login_url()
{
    return get_option('region_partnerportal_login_url', '');
}

function get_white_label_arbeitgeberportal_login_url()
{
    return get_option('region_arbeitgeberportal_login_url', '');
}

/**
 * Settings registrieren.
 */
add_action('admin_init', 'region_login_urls_register_settings');
function region_login_urls_register_settings()
{
    register_setting(
        'general',
        'region_partnerportal_login_url',
        array(
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => '',
        )
    );

    add_settings_field(
        'region_partnerportal_login_url',
        '<label for="region_partnerportal_login_url">Partnerportal Login URL ([region_partnerportal_login_url])</label>',
        'callback_input_partnerportal_login_url',
        'general'
    );

    register_setting(
        'general',
        'region_arbeitgeberportal_login_url',
        array(
            'type'              => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default'           => '',
        )
    );

    add_settings_field(
        'region_arbeitgeberportal_login_url',
        '<label for="region_arbeitgeberportal_login_url">Arbeitgeberportal Login URL ([region_arbeitgeberportal_login_url])</label>',
        'callback_input_arbeitgeberportal_login_url',
        'general'
    );
}

/**
 * Felder im Backend.
 */
function callback_input_partnerportal_login_url()
{
    $value = esc_attr(get_white_label_partnerportal_login_url());
    echo '<input type="url" id="region_partnerportal_login_url" name="region_partnerportal_login_url" value="' . $value . '" size="110" class="regular-text" />';
}

function callback_input_arbeitgeberportal_login_url()
{
    $value = esc_attr(get_white_label_arbeitgeberportal_login_url());
    echo '<input type="url" id="region_arbeitgeberportal_login_url" name="region_arbeitgeberportal_login_url" value="' . $value . '" size="110" class="regular-text" />';
}

/**
 * Shortcodes.
 */
function white_label_partnerportal_login_url_shortcode()
{
    return esc_url(get_white_label_partnerportal_login_url());
}

function white_label_arbeitgeberportal_login_url_shortcode()
{
    return esc_url(get_white_label_arbeitgeberportal_login_url());
}

add_shortcode('region_partnerportal_login_url', 'white_label_partnerportal_login_url_shortcode');
add_shortcode('region_arbeitgeberportal_login_url', 'white_label_arbeitgeberportal_login_url_shortcode');