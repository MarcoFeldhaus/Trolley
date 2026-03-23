<?php
add_filter('nav_menu_link_attributes', 'dcwd_nav_menu_link_attributes', 10, 4);
function dcwd_nav_menu_link_attributes($atts, $item, $args, $depth)
{

     if (false !== strpos($atts['href'], '[region_partnerportal_login_url]')) {
          $atts['href'] = do_shortcode('[region_partnerportal_login_url]');
     }
     if (false !== strpos($atts['href'], '[region_arbeitgeberportal_login_url]')) {
          $atts['href'] = do_shortcode('[region_arbeitgeberportal_login_url]');
     }

     return $atts;
}


function get_white_label_partnerportal_login_url()
{
     $region_partnerportal_login_url = get_option('region_partnerportal_login_url', '');
     return $region_partnerportal_login_url;
}

function get_white_label_arbeitgeberportal_login_url()
{
     $region_arbeitgeberportal_login_url = get_option('region_arbeitgeberportal_login_url', '');
     return $region_arbeitgeberportal_login_url;
}

register_setting('general', 'region_partnerportal_login_url', 'esc_attr');
add_settings_field('region_partnerportal_login_url', '<label for="region_partnerportal_login_url">Partnerportal Login URL  ([region_partnerportal_login_url])</label>', 'callback_input_partnerportal_login_url', 'general');

register_setting('general', 'region_arbeitgeberportal_login_url', 'esc_attr');
add_settings_field('region_arbeitgeberportal_login_url', '<label for="region_arbeitgeberportal_login_url">Arbeitgeberportal Login URL  ([region_arbeitgeberportal_login_url])</label>', 'callback_input_arbeitgeberportal_login_url', 'general');


function callback_input_partnerportal_login_url()
{
     $value = get_white_label_partnerportal_login_url();
     echo '<input type="text" id="region_partnerportal_login_url" name="region_partnerportal_login_url" value="' . $value . '" size="110" />';
}

function callback_input_arbeitgeberportal_login_url()
{
     $value = get_white_label_arbeitgeberportal_login_url();
     echo '<input type="text" id="region_arbeitgeberportal_login_url" name="region_arbeitgeberportal_login_url" value="' . $value . '" size="110" />';
}

function white_label_partnerportal_login_url_shortcode()
{

     return get_white_label_partnerportal_login_url();
}

function white_label_arbeitgeberportal_login_url_shortcode()
{

     return get_white_label_arbeitgeberportal_login_url();
}




add_shortcode('region_partnerportal_login_url', 'white_label_partnerportal_login_url_shortcode');

add_shortcode('region_arbeitgeberportal_login_url', 'white_label_arbeitgeberportal_login_url_shortcode');
