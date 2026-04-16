<?php
/**
 * Theme functions and definitions
 *
 * @package HelloElementorChild
 */

/**
 * Load child theme css and optional scripts
 *
 * @return void
 */
function hello_elementor_child_enqueue_scripts() {
     wp_enqueue_style(
          'hello-elementor-child-style',
          get_stylesheet_directory_uri() . '/style.css',
          [
               'hello-elementor-theme-style',
          ],
          '1.0.0'
     );

     if(is_front_page()) {
         wp_enqueue_script('customjs', get_stylesheet_directory_uri() . '/custom.js', array(), '1.0.0', 'true' );
     }


     wp_enqueue_script('participating-partners-grid', get_stylesheet_directory_uri() . '/participating-partners-grid.js', array('jquery'), '1.0.0', true );
     wp_enqueue_script('tm-accordion', get_stylesheet_directory_uri() . '/tm-accordion.js', array('jquery'), '1.0.0', true );

     $request_data = [ 'x_api_key' => get_white_label_region_api_key() ];
     wp_localize_script('participating-partners-grid', 'request_data', $request_data);
}
add_action( 'wp_enqueue_scripts', 'hello_elementor_child_enqueue_scripts', 20 );



function add_matomo_to_html_head(){

     if(get_white_label_matomo_website_id() && get_white_label_matomo_website_id() != '') {
     ?>
          <script>
            var _paq = window._paq = window._paq || [];
            _paq.push(['trackPageView']);
            _paq.push(['enableLinkTracking']);
            (function() {
              var u="//matomo.herbolzheim-karte.de/";
              _paq.push(['setTrackerUrl', u+'matomo.php']);
              _paq.push(['setSiteId', '<?php echo get_white_label_matomo_website_id(); ?>']);
              var d=document, g=d.createElement('script'), s=d.getElementsByTagName('script')[0];
              g.async=true; g.src=u+'matomo.js'; s.parentNode.insertBefore(g,s);
            })();
          </script>
     <?php
     }
};
add_action('wp_head', 'add_matomo_to_html_head', 999);


function current_year_shortcode () {
     $year = date_i18n ('Y');
     return $year;
}
add_shortcode ('current_year', 'current_year_shortcode');


function show_or_hide_ad_notice () {

     $text = '';

     if( get_field('werbebanner_bild') ) {
          $text = 'Anzeige:';
     }

     return $text;
}
add_shortcode ('ad_notice', 'show_or_hide_ad_notice');


add_filter( 'nav_menu_link_attributes', 'dcwd_nav_menu_link_attributes', 10, 4 ); 
function dcwd_nav_menu_link_attributes( $atts, $item, $args, $depth ) {

     if ( false !== strpos( $atts[ 'href' ], '[region_kundenportal_login_url]' ) ) {
          $atts[ 'href' ] = do_shortcode('[region_kundenportal_login_url]');
     }

     if ( false !== strpos( $atts[ 'href' ], '[region_kundenportal_registrierung_url]' ) ) {
          $atts[ 'href' ] = do_shortcode('[region_kundenportal_registrierung_url]');
     }

     if ( false !== strpos( $atts[ 'href' ], '[region_partnerportal_login_url]' ) ) {
          $atts[ 'href' ] = do_shortcode('[region_partnerportal_login_url]');
     }

     if ( false !== strpos( $atts[ 'href' ], '[region_partnerportal_registrierung_url]' ) ) {
          $atts[ 'href' ] = do_shortcode('[region_partnerportal_registrierung_url]');
     }

     if ( false !== strpos( $atts[ 'href' ], '[region_interessenten_registrierung_url]' ) ) {
          $atts[ 'href' ] = do_shortcode('[region_interessenten_registrierung_url]');
     }

     if ( false !== strpos( $atts[ 'href' ], '[region_name]' ) ) {
          $atts[ 'href' ] = do_shortcode('[region_name]');
     }

     return $atts;
}


add_filter('woocommerce_disable_admin_bar', '_wc_disable_admin_bar', 10, 1);
 
function _wc_disable_admin_bar($prevent_admin_access) {
    if (!current_user_can('view_admin_dashboard')) {
        return true;
    }
    return false;
}
 
add_filter('woocommerce_prevent_admin_access', '_wc_prevent_admin_access', 10, 1);
 
function _wc_prevent_admin_access($prevent_admin_access) {
    if (!current_user_can('view_admin_dashboard')) {
        return true;
    }
    return false;
}

add_filter('woo_vou_assigned_admin_roles', 'voucher_assigned_admin_roles', 21);
 
function voucher_assigned_admin_roles() {
     //add user role tm-support to access to woocommerce voucher codes page
     return array('administrator', 'tm-support');
}

function get_white_label_region_api_key() {
     $x_api_key = get_option('region_api_key', '');
     return $x_api_key;
}


function get_white_label_matomo_website_id() {
     $matomo_website_id = get_option('matomo_website_id', '');
     return $matomo_website_id;
}

function get_white_label_region_name() {
     $region_name = get_option('region_name', '');
     return $region_name;
}

function get_white_label_region_gemeinde_name() {
     $region_gemeinde = get_option('region_gemeinde', '');
     return $region_gemeinde;
}

function get_white_label_region_gemeinde_orte() {
     $region_gemeinde_orte = get_option('region_gemeinde_orte', '');
     return $region_gemeinde_orte;
}

function get_white_label_region_quellennachweise() {
     $region_quellennachweise = get_option('region_quellennachweise', '');
     return $region_quellennachweise;
}

function get_white_label_region_personenbezeichnung() {
     $region_personenbezeichnung = get_option('region_personenbezeichnung', '');
     return $region_personenbezeichnung;
}

function get_white_label_card_name() {
     $card_name = get_option('card_name', '');
     return $card_name;
}

function get_white_label_card_name_prefix() {
     $card_name_prefix = get_option('card_name_prefix', '');
     return $card_name_prefix;
}

function get_white_label_contact_person() {
     $region_contact_person = get_option('region_contact_person', '');
     return $region_contact_person;
}

function get_white_label_email_address() {
     $region_email_adresse = get_option('region_email_adress', '');
     return $region_email_adresse;
}

function get_white_label_phone() {
     $region_phone = get_option('region_phone', '');
     return $region_phone;
}

function get_white_label_website_domain() {
     $region_website_domain = get_option('region_website_domain', '');
     return $region_website_domain;
}

function get_white_label_website_url() {
     $region_website_url = get_option('region_website_url', '');
     return $region_website_url;
}

function get_white_label_card_image_url() {
     $region_card_image_url = get_option('region_card_image_url', '');
     return $region_card_image_url;
}

function get_white_label_gutscheincard_image_url() {
     $region_gutscheincard_image_url = get_option('region_gutscheincard_image_url', '');
     return $region_gutscheincard_image_url;
}

function get_white_label_app_store_url() {
     $region_app_store_url = get_option('region_app_store_url', '');
     return $region_app_store_url;
}

function get_white_label_play_store_url() {
     $region_play_store_url = get_option('region_play_store_url', '');
     return $region_play_store_url;
}

function get_white_label_shop_agb_pdf_url() {
     $region_shop_agb_pdf_url = get_option('region_shop_agb_pdf_url', '');
     return $region_shop_agb_pdf_url;
}

function get_white_label_partner_agb_pdf_url() {
     $region_partner_agb_pdf_url = get_option('region_partner_agb_pdf_url', '');
     return $region_partner_agb_pdf_url;
}

function get_white_label_teilnahmebedingungen_pdf_url() {
     $region_teilnahmebedingungen_pdf_url = get_option('region_teilnahmebedingungen_pdf_url', '');
     return $region_teilnahmebedingungen_pdf_url;
}

function get_white_label_kundenportal_login_url() {
     $region_kundenportal_login_url = get_option('region_kundenportal_login_url', '');
     return $region_kundenportal_login_url;
}

function get_white_label_kundenportal_registrierung_url() {
     $region_kundenportal_registrierung_url = get_option('region_kundenportal_registrierung_url', '');
     return $region_kundenportal_registrierung_url;
}

function get_white_label_interessenten_registrierung_url() {
     $region_interessenten_registrierung_url = get_option('region_interessenten_registrierung_url', '');
     return $region_interessenten_registrierung_url;
}

function get_white_label_paypal_fee() {
     $region_paypal_fee = get_option('region_paypal_fee', 5);
     return $region_paypal_fee;
}

function get_white_label_paypal_fee_per_voucher() {
     $region_paypal_fee_per_voucher = get_option('region_paypal_fee_per_voucher', 0);
     return $region_paypal_fee_per_voucher;
}



add_action('admin_init', 'add_white_label_settings_fields');
function add_white_label_settings_fields(){

     register_setting( 'general', 'region_api_key', 'esc_attr' );
     add_settings_field('region_api_key', '<label for="region_api_key">Region X-API-KEY</label>' , 'callback_input_region_api_key' , 'general' );

     register_setting( 'general', 'matomo_website_id', 'esc_attr' );
     add_settings_field('matomo_website_id', '<label for="matomo_website_id">Matomo Website ID</label>' , 'callback_input_matomo_website_id' , 'general' );

     register_setting( 'general', 'card_name', 'esc_attr' );
     add_settings_field('card_name', '<label for="card_name">CARD Name ([card_name])</label>' , 'callback_input_cardname' , 'general' );

     register_setting( 'general', 'card_name_prefix', 'esc_attr' );
     add_settings_field('card_name_prefix', '<label for="card_name_prefix">CARD Name Prefix ([card_name_prefix])</label>' , 'callback_input_cardnameprefix' , 'general' );

     register_setting( 'general', 'region_name', 'esc_attr' );
     add_settings_field('region_name', '<label for="region_name">Regionname ([region_name])</label>' , 'callback_input_regionname' , 'general' );

     register_setting( 'general', 'region_gemeinde', 'esc_attr' );
     add_settings_field('region_gemeinde', '<label for="region_gemeinde">Gemeindename(n) ([region_gemeinde])</label>' , 'callback_input_gemeindename' , 'general' );

     register_setting( 'general', 'region_gemeinde_orte', 'esc_attr' );
     add_settings_field('region_gemeinde_orte', '<label for="region_gemeinde_orte">Gemeinde Orte ([region_gemeinde_orte])</label>' , 'callback_input_gemeinde_orte' , 'general' );

     register_setting( 'general', 'region_quellennachweise', 'esc_attr' );
     add_settings_field('region_quellennachweise', '<label for="region_quellennachweise">Quellennachweise ([region_quellennachweise])</label>' , 'callback_input_quellennachweise' , 'general' );

     register_setting( 'general', 'region_personenbezeichnung', 'esc_attr' );
     add_settings_field('region_personenbezeichnung', '<label for="region_personenbezeichnung">Personenbezeichnung (z.B. Karlsruher) ([region_personenbezeichnung])</label>' , 'callback_input_personenbezeichnung' , 'general' );

     register_setting( 'general', 'region_contact_person', 'esc_attr' );
     add_settings_field('region_contact_person', '<label for="region_contact_person">Kontaktperson/-Stelle der Region ([region_contact_person])</label>' , 'callback_input_contact_person' , 'general' );

     register_setting( 'general', 'region_email_adress', 'esc_attr' );
     add_settings_field('region_email_adress', '<label for="region_email_adress">E-Mail Adresse der Region ([region_email_address])</label>' , 'callback_input_email' , 'general' );

     register_setting( 'general', 'region_phone', 'esc_attr' );
     add_settings_field('region_phone', '<label for="region_phone">Telefonnummer der Region ([region_phone])</label>' , 'callback_input_phone' , 'general' );

     register_setting( 'general', 'region_website_domain', 'esc_attr' );
     add_settings_field('region_website_domain', '<label for="region_website_domain">Website Domain ([region_website_domain])</label>' , 'callback_input_website_domain' , 'general' );

     register_setting( 'general', 'region_website_url', 'esc_attr' );
     add_settings_field('region_website_url', '<label for="region_website_url">Website URL (https://www.) ([region_website_url])</label>' , 'callback_input_website_url' , 'general' );

     register_setting( 'general', 'region_card_image_url', 'esc_attr' );
     add_settings_field('region_card_image_url', '<label for="region_card_image_url">CARD Bild URL ([region_card_image_url])</label>' , 'callback_input_card_image_url' , 'general' );

     register_setting( 'general', 'region_gutscheincard_image_url', 'esc_attr' );
     add_settings_field('region_gutscheincard_image_url', '<label for="region_gutscheincard_image_url">GutscheinCARD Bild URL ([region_gutscheincard_image_url])</label>' , 'callback_input_gutscheincard_image_url' , 'general' );

     register_setting( 'general', 'region_app_store_url', 'esc_attr' );
     add_settings_field('region_app_store_url', '<label for="region_app_store_url">App Store URL ([region_app_store_url])</label>' , 'callback_input_app_store_url' , 'general' );

     register_setting( 'general', 'region_play_store_url', 'esc_attr' );
     add_settings_field('region_play_store_url', '<label for="region_play_store_url">Play Store URL  ([region_play_store_url])</label>' , 'callback_input_play_store_url' , 'general' );

     register_setting( 'general', 'region_shop_agb_pdf_url', 'esc_attr' );
     add_settings_field('region_shop_agb_pdf_url', '<label for="region_shop_agb_pdf_url">Shop AGB PDF URL  ([region_shop_agb_pdf_url])</label>' , 'callback_input_shop_agb_pdf_url' , 'general' );

     register_setting( 'general', 'region_partner_agb_pdf_url', 'esc_attr' );
     add_settings_field('region_partner_agb_pdf_url', '<label for="region_partner_agb_pdf_url">Partner AGB PDF URL  ([region_partner_agb_pdf_url])</label>' , 'callback_input_partner_agb_pdf_url' , 'general' );


     register_setting( 'general', 'region_teilnahmebedingungen_pdf_url', 'esc_attr' );
     add_settings_field('region_teilnahmebedingungen_pdf_url', '<label for="region_teilnahmebedingungen_pdf_url">Teilnahmebedingungen PDF URL  ([region_teilnahmebedingungen_pdf_url])</label>' , 'callback_input_teilnahmebedingungen_pdf_url' , 'general' );


     register_setting( 'general', 'region_kundenportal_login_url', 'esc_attr' );
     add_settings_field('region_kundenportal_login_url', '<label for="region_kundenportal_login_url">Kundenportal Login URL  ([region_kundenportal_login_url])</label>' , 'callback_input_kundenportal_login_url' , 'general' );

     register_setting( 'general', 'region_kundenportal_registrierung_url', 'esc_attr' );
     add_settings_field('region_kundenportal_registrierung_url', '<label for="region_kundenportal_registrierung_url">Kundenportal Registrierung URL  ([region_kundenportal_registrierung_url])</label>' , 'callback_input_kundenportal_registrierung_url' , 'general' );

     register_setting( 'general', 'region_interessenten_registrierung_url', 'esc_attr' );
     add_settings_field('region_interessenten_registrierung_url', '<label for="region_interessenten_registrierung_url">Interessenten Registrierung URL  ([region_interessenten_registrierung_url])</label>' , 'callback_input_interessenten_registrierung_url' , 'general' );

     register_setting( 'general', 'region_paypal_fee', 'esc_attr' );
     add_settings_field('region_paypal_fee', '<label for="region_paypal_fee">PayPal Gebühr pro Bestellung (in Prozent)</label>' , 'callback_input_paypal_fee' , 'general' );

     register_setting( 'general', 'region_paypal_fee_per_voucher', 'esc_attr' );
     add_settings_field('region_paypal_fee_per_voucher', '<label for="region_paypal_fee_per_voucher">PayPal Gebühr pro Gutschein (in Euro)</label>' , 'callback_input_paypal_fee_per_voucher' , 'general' );

}

function callback_input_region_api_key() {
     $value = get_white_label_region_api_key();
     echo '<input type="text" id="region_api_key" name="region_api_key" value="' . $value . '" />';
}


function callback_input_matomo_website_id() {
     $value = get_white_label_matomo_website_id();
     echo '<input type="text" id="matomo_website_id" name="matomo_website_id" value="' . $value . '" />';
}

function callback_input_cardname() {
     $value = get_white_label_card_name();
     echo '<input type="text" id="card_name" name="card_name" value="' . $value . '" />';
}

function callback_input_cardnameprefix() {
     $value = get_white_label_card_name_prefix();
     echo '<input type="text" id="card_name_prefix" name="card_name_prefix" value="' . $value . '" />';
}

function callback_input_regionname() {
     $value = get_white_label_region_name();
     echo '<input type="text" id="region_name" name="region_name" value="' . $value . '" />';
}

function callback_input_gemeindename() {
     $value = get_white_label_region_gemeinde_name();
     echo '<input type="text" id="region_gemeinde" name="region_gemeinde" value="' . $value . '" />';
}

function callback_input_gemeinde_orte() {
     $value = get_white_label_region_gemeinde_orte();
     echo '<input type="text" id="region_gemeinde_orte" name="region_gemeinde_orte" value="' . $value . '" size="110" />';
}

function callback_input_quellennachweise() {
     $value = get_white_label_region_quellennachweise();
     echo '<input type="text" id="region_quellennachweise" name="region_quellennachweise" value="' . $value . '" size="110" />';
}

function callback_input_personenbezeichnung() {
     $value = get_white_label_region_personenbezeichnung();
     echo '<input type="text" id="region_personenbezeichnung" name="region_personenbezeichnung" value="' . $value . '" size="110" />';
}

function callback_input_contact_person() {
     $value = get_white_label_contact_person();
     echo '<input type="text" id="region_contact_person" name="region_contact_person" value="' . $value . '" size="110" />';
}

function callback_input_email() {
     $value = get_white_label_email_address();
     echo '<input type="text" id="region_email_adress" name="region_email_adress" value="' . $value . '" size="110" />';
}

function callback_input_phone() {
     $value = get_white_label_phone();
     echo '<input type="text" id="region_phone" name="region_phone" value="' . $value . '" size="110" />';
}

function callback_input_website_domain() {
     $value = get_white_label_website_domain();
     echo '<input type="text" id="region_website_domain" name="region_website_domain" value="' . $value . '" size="110" />';
}

function callback_input_website_url() {
     $value = get_white_label_website_url();
     echo '<input type="text" id="region_website_url" name="region_website_url" value="' . $value . '" size="110" />';
}

function callback_input_card_image_url() {
     $value = get_white_label_card_image_url();
     echo '<input type="text" id="region_card_image_url" name="region_card_image_url" value="' . $value . '" size="110" />';
}

function callback_input_gutscheincard_image_url() {
     $value = get_white_label_gutscheincard_image_url();
     echo '<input type="text" id="region_gutscheincard_image_url" name="region_gutscheincard_image_url" value="' . $value . '" size="110" />';
}

function callback_input_app_store_url() {
     $value = get_white_label_app_store_url();
     echo '<input type="text" id="region_app_store_url" name="region_app_store_url" value="' . $value . '" size="110" />';
}

function callback_input_play_store_url() {
     $value = get_white_label_play_store_url();
     echo '<input type="text" id="region_play_store_url" name="region_play_store_url" value="' . $value . '" size="110" />';
}

function callback_input_shop_agb_pdf_url() {
     $value = get_white_label_shop_agb_pdf_url();
     echo '<input type="text" id="region_shop_agb_pdf_url" name="region_shop_agb_pdf_url" value="' . $value . '" size="110" />';
}

function callback_input_partner_agb_pdf_url() {
     $value = get_white_label_partner_agb_pdf_url();
     echo '<input type="text" id="region_partner_agb_pdf_url" name="region_partner_agb_pdf_url" value="' . $value . '" size="110" />';
}

function callback_input_teilnahmebedingungen_pdf_url() {
     $value = get_white_label_teilnahmebedingungen_pdf_url();
     echo '<input type="text" id="region_teilnahmebedingungen_pdf_url" name="region_teilnahmebedingungen_pdf_url" value="' . $value . '" size="110" />';
}

function callback_input_kundenportal_login_url() {
     $value = get_white_label_kundenportal_login_url();
     echo '<input type="text" id="region_kundenportal_login_url" name="region_kundenportal_login_url" value="' . $value . '" size="110" />';
}

function callback_input_kundenportal_registrierung_url() {
     $value = get_white_label_kundenportal_registrierung_url();
     echo '<input type="text" id="region_kundenportal_registrierung_url" name="region_kundenportal_registrierung_url" value="' . $value . '" size="110" />';
}

function callback_input_interessenten_registrierung_url() {
     $value = get_white_label_interessenten_registrierung_url();
     echo '<input type="text" id="region_interessenten_registrierung_url" name="region_interessenten_registrierung_url" value="' . $value . '" size="110" />';
}

function callback_input_paypal_fee() {
     $value = get_white_label_paypal_fee();
     echo '<input type="number" step="0.01" id="region_paypal_fee" name="region_paypal_fee" value="' . $value . '" />';
}

function callback_input_paypal_fee_per_voucher() {
     $value = get_white_label_paypal_fee_per_voucher();
     echo '<input type="number" step="0.01" id="region_paypal_fee_per_voucher" name="region_paypal_fee_per_voucher" value="' . $value . '" />';
}



function white_label_region_card_name_shortcode() { 
 
     return get_white_label_card_name();
}
add_shortcode('card_name', 'white_label_region_card_name_shortcode'); 


function white_label_region_card_name_prefix_shortcode() { 
 
     return get_white_label_card_name_prefix();
}
add_shortcode('card_name_prefix', 'white_label_region_card_name_prefix_shortcode'); 


function white_label_regionname_shortcode() { 
 
     return get_white_label_region_name();
}
add_shortcode('region_name', 'white_label_regionname_shortcode'); 


function white_label_gemeindename_shortcode() { 
 
     return get_white_label_region_gemeinde_name();
}
add_shortcode('region_gemeinde', 'white_label_gemeindename_shortcode');


function white_label_gemeinde_orte_shortcode() { 
 
     return get_white_label_region_gemeinde_orte();
}
add_shortcode('region_gemeinde_orte', 'white_label_gemeinde_orte_shortcode'); 

function white_label_quellennachweise_shortcode() { 
 
     return get_white_label_region_quellennachweise();
} 
add_shortcode('region_quellennachweise', 'white_label_quellennachweise_shortcode'); 


function white_label_region_personenbezeichnung_shortcode() { 
 
     return get_white_label_region_personenbezeichnung();
} 
add_shortcode('region_personenbezeichnung', 'white_label_region_personenbezeichnung_shortcode'); 

function white_label_contact_person_shortcode() { 
 
     return html_entity_decode(get_white_label_contact_person());
} 
add_shortcode('region_contact_person', 'white_label_contact_person_shortcode');

function white_label_email_shortcode() { 
 
     return '<a href="mailto:' . get_white_label_email_address() . '">' . get_white_label_email_address() . '</a>';
} 
add_shortcode('region_email_address', 'white_label_email_shortcode');


function white_label_phone_shortcode() { 
 
     return get_white_label_phone();
} 
add_shortcode('region_phone', 'white_label_phone_shortcode');


function white_label_website_domain_shortcode() { 
 
     return get_white_label_website_domain();
} 
add_shortcode('region_website_domain', 'white_label_website_domain_shortcode');


function white_label_website_url_shortcode() { 
 
     return get_white_label_website_url();
}
add_shortcode('region_website_url', 'white_label_website_url_shortcode');


function white_label_card_image_url_shortcode() { 
 
     return get_white_label_card_image_url();
}
add_shortcode('region_card_image_url', 'white_label_card_image_url_shortcode');


function white_label_gutscheincard_image_url_shortcode() { 
 
     return get_white_label_gutscheincard_image_url();
}
add_shortcode('region_gutscheincard_image_url', 'white_label_gutscheincard_image_url_shortcode');


function white_label_app_store_url_shortcode() { 
 
     return get_white_label_app_store_url();
}
add_shortcode('region_app_store_url', 'white_label_app_store_url_shortcode'); 


function white_label_play_store_url_shortcode() { 
 
     return get_white_label_play_store_url();
}
add_shortcode('region_play_store_url', 'white_label_play_store_url_shortcode'); 


function white_label_shop_agb_pdf_url_shortcode() { 
 
     return get_white_label_shop_agb_pdf_url();
}
add_shortcode('region_shop_agb_pdf_url', 'white_label_shop_agb_pdf_url_shortcode'); 


function white_label_partner_agb_pdf_url_shortcode() { 
 
     return get_white_label_partner_agb_pdf_url();
}
add_shortcode('region_partner_agb_pdf_url', 'white_label_partner_agb_pdf_url_shortcode'); 


function white_label_teilnahmebedingungen_pdf_url_shortcode() { 
 
     return get_white_label_teilnahmebedingungen_pdf_url();
}
add_shortcode('region_teilnahmebedingungen_pdf_url', 'white_label_teilnahmebedingungen_pdf_url_shortcode'); 


function white_label_kundenportal_login_url_shortcode() { 
 
     return get_white_label_kundenportal_login_url();
}
add_shortcode('region_kundenportal_login_url', 'white_label_kundenportal_login_url_shortcode'); 


function white_label_kundenportal_registrierung_url_shortcode() { 
 
     return get_white_label_kundenportal_registrierung_url();
}
add_shortcode('region_kundenportal_registrierung_url', 'white_label_kundenportal_registrierung_url_shortcode'); 


function white_label_interessenten_registrierung_url_shortcode() { 
 
     return get_white_label_interessenten_registrierung_url();
}
add_shortcode('region_interessenten_registrierung_url', 'white_label_interessenten_registrierung_url_shortcode'); 


function white_label_interessent_registrierung_url_shortcode() { 
 
     $region_name = get_white_label_region_name();
     return 'https://mycity.cards/interessent-registrierung?region=' . $region_name;
}
add_shortcode('region_interessent_registrierung_url', 'white_label_interessent_registrierung_url_shortcode'); 



function white_label_teilnahmebedingungen_pdf_download_shortcode() { 
 
     $pdf_url = get_white_label_teilnahmebedingungen_pdf_url();
     $escaped_pdf_url = esc_url( $pdf_url );

     return '<div class="elementor-button-wrapper">
               <a href="' . $escaped_pdf_url. '" target="_blank" class="elementor-button-link elementor-button elementor-size-sm" role="button">
                              <span class="elementor-button-content-wrapper">
                              <span class="elementor-button-text"><i class="fas fa-file-pdf" aria-hidden="true"></i> Teilnahmebedingungen herunterladen &gt;&gt;</span>
          </span>
                         </a>
          </div>
          Sie benötigen zum öffnen der Datei das kostenlose Programm <a href="https://acrobat.adobe.com/de/de/acrobat/pdf-reader.html" target="_blank" rel="noopener">Adobe Reader</a>';
}
add_shortcode('white_label_teilnahmebedingungen_pdf_download', 'white_label_teilnahmebedingungen_pdf_download_shortcode');


function white_label_shop_agb_pdf_download_shortcode() { 
 
     $pdf_url = get_white_label_shop_agb_pdf_url();
     $escaped_pdf_url = esc_url( $pdf_url );

     return '<div class="elementor-button-wrapper">
               <a href="' . $escaped_pdf_url. '" target="_blank" class="elementor-button-link elementor-button elementor-size-sm" role="button">
                              <span class="elementor-button-content-wrapper">
                              <span class="elementor-button-text"><i class="fas fa-file-pdf" aria-hidden="true"></i> AGB herunterladen &gt;&gt;</span>
          </span>
                         </a>
          </div>
          Sie benötigen zum öffnen der Datei das kostenlose Programm <a href="https://acrobat.adobe.com/de/de/acrobat/pdf-reader.html" target="_blank" rel="noopener">Adobe Reader</a>';
}
add_shortcode('white_label_shop_agb_pdf_download', 'white_label_shop_agb_pdf_download_shortcode');



function white_label_partner_agb_pdf_download_shortcode() { 
 
     $pdf_url = get_white_label_partner_agb_pdf_url();
     $escaped_pdf_url = esc_url( $pdf_url );

     return '<div class="elementor-button-wrapper">
               <a href="' . $escaped_pdf_url. '" target="_blank" class="elementor-button-link elementor-button elementor-size-sm" role="button">
                              <span class="elementor-button-content-wrapper">
                              <span class="elementor-button-text"><i class="fas fa-file-pdf" aria-hidden="true"></i> AGB herunterladen &gt;&gt;</span>
          </span>
                         </a>
          </div>
          Sie benötigen zum öffnen der Datei das kostenlose Programm <a href="https://acrobat.adobe.com/de/de/acrobat/pdf-reader.html" target="_blank" rel="noopener">Adobe Reader</a>';
}
add_shortcode('white_label_partner_agb_pdf_download', 'white_label_partner_agb_pdf_download_shortcode'); 



function white_label_paypal_fee() { 
     $paypal_fee = get_white_label_paypal_fee();
     $paypal_fee_per_voucher = get_white_label_paypal_fee_per_voucher();
     $text = $paypal_fee . '% (zzgl. 19% MwSt.) des Gutscheinwertes';
     if($paypal_fee_per_voucher > 0) {
         $text .=  ' und ' . $paypal_fee_per_voucher . '€ pro Gutschein';
     }
     return $text;
}
add_shortcode('region_paypal_fee_text', 'white_label_paypal_fee'); 


add_action('wpcf7_init', 'custom_add_form_white_label_form_tags');
 
function custom_add_form_white_label_form_tags()
{
    wpcf7_add_form_tag('white_label_email_address', 'custom_white_label_email_address_form_tag_handler');
    wpcf7_add_form_tag('region_name', 'custom_white_label_card_name_form_tag_handler');
}
 
function custom_white_label_email_address_form_tag_handler($tag)
{
    return get_white_label_email_address();
}

function custom_white_label_card_name_form_tag_handler($tag)
{
    return get_white_label_card_name();
}


/**
 * A tag to be used in "Mail" section so the user receives the special tag
 * [white_label_email_address]
 */
add_filter('wpcf7_special_mail_tags', 'wpcf7_tag_white_label_email_address', 10, 3);
function wpcf7_tag_white_label_email_address($output, $name, $html)
{
    $name = preg_replace('/^wpcf7\./', '_', $name); // for back-compat

    $submission = WPCF7_Submission::get_instance();

    if (! $submission) {
        return $output;
    }
    
    if ('white_label_email_address' == $name) {
        return get_white_label_email_address();
    }

    if ('white_label_card_name' == $name) {
        return get_white_label_card_name();
    }

    return $output;
}




add_filter( 'woocommerce_enable_order_notes_field', '__return_false' );

if (!class_exists('WPWEB_TCPDF')) { //If class not exist
     if(defined("WOO_VOU_DIR")) {
        //include tcpdf file
          require_once WOO_VOU_DIR . '/includes/tcpdf/tcpdf.php';
     }
        
 }


 /**
 * Adding Custom shortcode value in PDF voucher
 */
function woo_vou_pdf_template_replace_shortcodes( $voucher_template_html, $orderid, $item_key, $items, $voucodes, $productid ) {

     $pdf_vou_codes = !empty($voucodes) ? $voucodes : '';
     
     $pdf = new WPWEB_TCPDF(WPWEB_PDF_PAGE_ORIENTATION, WPWEB_PDF_UNIT, WPWEB_PDF_PAGE_FORMAT, true, 'UTF-8', false);

     if (!empty($pdf_vou_codes) && strpos($voucher_template_html, '{code128}') !== false) {

        $style = array(
                  'position' => 'C',
                  'align' => 'C',
                  'border' => false,
                  'fgcolor' => array(0,0,0),
                 'fontsize' => 100,
                  'bgcolor' => false
             );

        $voucher_code = trim($pdf_vou_codes);

        $vou_code_params = $pdf->serializeTCPDFtagParameters(array($voucher_code, 'C128', '', '', '', '18', '0.4', $style, 'N'));
        $vou_code = '<tcpdf method="write1DBarcode" params="' . $vou_code_params . '" />';

       
     $voucher_template_html = str_replace( '{code128}', $vou_code, $voucher_template_html );
       
          return $voucher_template_html;
     }
}

// Add filter to replace voucher template shortcodes in download pdf
add_filter('woo_vou_pdf_template_inner_html', 'woo_vou_pdf_template_replace_shortcodes', 10, 6 );


function action_woocommerce_before_single_product( $wc_print_notices ) { 
    echo '<div class="elementor-element elementor-element-9abbb83 elementor-widget__width-auto elementor-widget elementor-widget-button" data-id="9abbb83" data-element_type="widget" data-widget_type="button.default" style="margin-left: 10px;"><div class="elementor-widget-container"><div class="elementor-button-wrapper"><a href="' . site_url() . '/gutschein-shop/" class="elementor-button-link elementor-button elementor-size-sm" role="button" id="back-to-shop-button"><span class="elementor-button-content-wrapper"><span class="elementor-button-text"><< Zurück zur Übersicht</span></span></a></div></div></div><br />';
}; 
add_action( 'woocommerce_before_single_product', 'action_woocommerce_before_single_product', 10, 1 ); 



/**
 * Preview PDF - Replace shortcodes with value
 * Replace {buyerphone} shortcode with value
 */
function woo_vou_pdf_template_preview_replace_shortcodes( $voucher_template_html, $voucher_template_id ) {
 
     $pdf = new WPWEB_TCPDF(WPWEB_PDF_PAGE_ORIENTATION, WPWEB_PDF_UNIT, WPWEB_PDF_PAGE_FORMAT, true, 'UTF-8', false);
     $style = array(
                  'position' => 'C',
                  'align' => 'C',
                  'border' => false,
                  'fgcolor' => array(0,0,0),
                 'fontsize' => 100,
                  'bgcolor' => false
             );

    $voucher_code = "Vorschau Code";

    $vou_code_params = $pdf->serializeTCPDFtagParameters(array($voucher_code, 'C128', '', '', '', '18', '0.4', $style, 'N'));
    $vou_code = '<tcpdf method="write1DBarcode" params="' . $vou_code_params . '" />';
   
   // replace {buyerphone} shortcode with value
   $voucher_template_html = str_replace( '{code128}', $vou_code, $voucher_template_html );
   
   return $voucher_template_html;
}

add_filter( 'woo_vou_pdf_template_preview_html', 'woo_vou_pdf_template_preview_replace_shortcodes', 10, 2 );



/** 
* Handles to change download button text on downloads page
*/
function woo_vou_download_page_vou_download_btn_func($btn_name, $product_id, $product_name, $download_file, $voucher_number, $order_date){

  return __("Gutschein downloaden", 'woovoucher');
}
add_filter( 'woo_vou_download_page_vou_download_btn', 'woo_vou_download_page_vou_download_btn_func', 10, 6 );




function woocommerce_template_loop_product_title() {
    echo '<h2 class="woocommerce-loop-product__title">' . get_the_title() . '*</h2>';
}


function change_checkout_fields( $fields ) {

     unset( $fields['billing']['billing_address_2'] );

     unset( $fields['order']['order_comments'] );

     $fields['account']['account_username']['required'] = false;

     $fields['billing']['billing_company']['priority'] = 2;
     $fields['billing']['billing_company']['label'] = 'Firmenname';

     $fields['billing']['billing_options'] = array(
        'label' => 'Privatkauf oder Firmenkauf?',
        'priority' => 1,
        'required' => true,
        'type' => 'select',
        'options' => array('' => '', 'privatkauf' => 'Privatkauf', 'firmenkauf' => 'Firmenkauf'),
        'class' => array('update_totals_on_change')
     );


     return $fields;
}
add_filter( 'woocommerce_checkout_fields', 'change_checkout_fields' );





function rename_address_my_account( $items ) {
  $items['dashboard'] = 'Kontoübersicht';
  return $items;
}
add_filter( 'woocommerce_account_menu_items', 'rename_address_my_account', 999 );





function hide_display_name($required_fields)
{
  unset($required_fields["account_display_name"]);
  return $required_fields;
}
add_filter('woocommerce_save_account_details_required_fields', 'hide_display_name');



function wc_email_as_username( $user_login ) {
    if( isset($_POST['billing_email'] ) ) {
        $user_login = $_POST['billing_email'];
    }

    if( isset($_POST['email'] ) ) {
        $user_login = $_POST['email'];
    }
    return $user_login;
}
add_filter( 'pre_user_login', 'wc_email_as_username' );




add_filter( 'woocommerce_new_customer_data', function( $data ) {
  $data['user_login'] = $data['user_email'];

  return $data;
});


add_action('woocommerce_checkout_process', 'matching_email_addresses');
function matching_email_addresses() { 
    $billing_email = $_POST['billing_email'];
    $_POST['account_username'] = $billing_email;
}


 
function attach_pdf_to_emails( $attachments, $email_id, $order, $email ) {
    $email_ids = array( 'customer_processing_order' );
    if ( in_array ( $email_id, $email_ids ) ) {
        //$upload_dir = wp_upload_dir();
        $attachments[] = get_white_label_shop_agb_pdf_url();
    }
    return $attachments;
}
add_filter( 'woocommerce_email_attachments', 'attach_pdf_to_emails', 10, 4 );


/*
add_filter('woocommerce_get_terms_and_conditions_checkbox_text', function() {
    return 'Ich erkläre mich damit einverstanden, dass meine Daten zur Bearbeitung der Bestellung/Vertragsabwicklung verarbeitet und gespeichert werden. Die Übermittlung der Daten erfolgt über eine verschlüsselte Verbindung. Sie können Ihre erteilte Einwilligung jederzeit widerrufen. Weitere Informationen und Widerrufshinweise finden Sie in der Datenschutzerklärung dieser Webseite. Zur Datenschutzerklärung';
});
*/


add_action( 'woocommerce_review_order_before_submit', 'add_checkout_checkbox', 1 );
function add_checkout_checkbox() {
   
    woocommerce_form_field( 'checkout-checkbox', array( // CSS ID
       'type'          => 'checkbox',
       'class'         => array('form-row mycheckbox'), // CSS Class
       'label_class'   => array('woocommerce-form__label woocommerce-form__label-for-checkbox checkbox'),
       'input_class'   => array('woocommerce-form__input woocommerce-form__input-checkbox input-checkbox'),
       'required'      => true, // Mandatory or Optional
       'label'         => 'Ich stimme ausdrücklich zu, dass Sie vor Ablauf der Widerrufsfrist mit der Ausführung des Vertrages beginnen. Mir ist bekannt, dass ich durch die Zustimmung mit Beginn der Ausführung des Vertrages mein Widerrufsrecht verliere.', // Label and Link
    ));    
}

add_action( 'woocommerce_checkout_process', 'add_checkout_checkbox_warning', 100 );
function add_checkout_checkbox_warning() {
     if ( ! (int) isset( $_POST['checkout-checkbox'] ) ) {
        wc_add_notice( __( 'Bei digitalen Inhalten (Online-Gutscheine) müssen Sie auf das Widerrufsrecht verzichten, da diese sofort gültig sind.' ), 'error' );
     }

     if(isset($_POST['billing_options']) && $_POST['billing_options'] == 'firmenkauf') {
          if ( !isset( $_POST['billing_company']) || !$_POST['billing_company'] || empty($_POST['billing_company'])) {
               wc_add_notice( __( 'Bei Firmenkauf muss der Firmenname angegeben werden' ), 'error' );
          }
     }

     if(isset($_POST['billing_options']) && $_POST['billing_options'] == 'privatkauf') {
          $items = WC()->cart->get_cart();

          $quantities = 0;
          foreach ($items as $cart_item)
          {
             $quantities += $cart_item['quantity'];
          }

          if($quantities > 5) {
               wc_add_notice( __( 'Privatkunden können maximal 5 Gutscheine pro Bestellung bestellen.' ), 'error' );
          }
     }
}

add_action('woocommerce_checkout_update_order_meta', function($order_id) {
     if (!empty($_POST['billing_options'])) {
          update_post_meta($order_id, 'billing_options', sanitize_text_field($_POST['billing_options']));
     }
});

add_action('woocommerce_admin_order_data_after_billing_address', function($order) {
     $billing_option = get_post_meta($order->get_id(), 'billing_options', true);
     if (!empty($billing_option)) {
          echo '<p><strong>Privatkauf/Firmenkauf:</strong> ' . $billing_option . '</p>';
     }
});


add_action( 'wp_footer', function(){
 
     // we need it only on our checkout page
     if( ! is_checkout() ) {
          return;
     }
 
     ?>
     <style>
          #billing_company_field .optional {
               display: none !important;
          }
     </style>
     <script>
     jQuery(function($){

          if($('#billing_options').val() == 'firmenkauf') {
               $('#billing_company_field').show();
               $('#billing_company_field').removeClass('woocommerce-validated');
          } else {
               $('#billing_company_field').hide();
               $('#billing_company').val('');
          }

          $( 'body' ).on( 'blur change', '#billing_options', function(){
               const wrapper = $(this).closest( '.form-row' );
               // you do not have to removeClass() because Woo do it in checkout.js
               const value = $(this).val();
               if( value.length == 0 || value == '' || value === '0' || value === 0 ) {
                    wrapper.addClass( 'woocommerce-invalid' ); // error
                    wrapper.addClass( 'woocommerce-invalid-required-field' );
                    wrapper.removeClass( 'woocommerce-validated' );
               } else {
                    wrapper.removeClass( 'woocommerce-invalid' );
                    wrapper.removeClass( 'woocommerce-invalid-required-field' );
                    wrapper.addClass( 'woocommerce-validated' ); // success
               }

               if(value == 'firmenkauf') {
                    $('#billing_company_field').show();
                    $('#billing_company_field').removeClass('woocommerce-validated');
               } else {
                    $('#billing_company_field').hide();
                    $('#billing_company').val('');
               }
          });
     });
     </script>
     <?php
} );


add_action('woocommerce_after_order_notes', 'insert_pflichtfeld', 10);
function insert_pflichtfeld() {
  echo '<p><span style="color: red; font-weight: bold;">*</span>Pflichtfeld</p>';
}


add_filter( 'woocommerce_loop_add_to_cart_link', 'replace_loop_add_to_cart_button', 10, 2 );
function replace_loop_add_to_cart_button( $button, $product  ) {

    // Button text here
    $button_text = __( "Zum Gutschein", "woocommerce" );

    return '<a class="button product_type_simple add_to_cart_button" href="' . $product->get_permalink() . '">' . $button_text . '</a>';
}


add_filter('woocommerce_email_subject_customer_new_account', 'change_customer_new_account_email_subject', 1, 2);
function change_customer_new_account_email_subject( $subject, $order ) {
     $subject = sprintf( 'Ihr %s Konto wurde erstellt', do_shortcode('[card_name]') );
     return $subject;
}


add_filter('woocommerce_email_subject_new_order', 'change_new_order_email_subject', 1, 2);
function change_new_order_email_subject( $subject, $order ) {
     $subject = sprintf( '[%s] Neue Bestellung (#%s)', do_shortcode('[card_name]'), $order->id );
     return $subject;
}

add_filter('woocommerce_email_subject_customer_processing_order', 'change_customer_processing_order_email_subject', 1, 2);
function change_customer_processing_order_email_subject( $subject, $order ) {
     $subject = sprintf( 'Ihre %s Gutschein Bestellung', do_shortcode('[card_name]') );
     return $subject;
}



if (!function_exists('write_log')) {

    function write_log($log) {
        if (true === WP_DEBUG) {
            if (is_array($log) || is_object($log)) {
                error_log(print_r($log, true));
            } else {
                error_log($log);
            }
        }
    }

}


/*
function replace_legal_texts_shortcodes($html) {
     $card_name = do_shortcode('[card_name]');
     $website_domain = do_shortcode('[region_website_domain]');
     $quellennachweise = do_shortcode('[region_quellennachweise]');

     $html = str_replace("[card_name]", $card_name, $html);
     $html = str_replace("[region_website_domain]", $website_domain, $html);
     $html = str_replace("[region_quellennachweise]", $quellennachweise, $html);
     return $html;
}*/

function white_label_get_impressum_shortcode() { 
     $html = file_get_contents('https://rechtliches.trolleymaker.com/impressum.html');
     return $html;
}
add_shortcode('white_label_impressum', 'white_label_get_impressum_shortcode');


function white_label_get_datenschutzerklaerung_shortcode() { 
     $html = file_get_contents('https://rechtliches.trolleymaker.com/datenschutzerklaerung.html');
     return $html;
}
add_shortcode('white_label_datenschutzerklaerung', 'white_label_get_datenschutzerklaerung_shortcode');


function white_label_get_datenschutzhinweise_shortcode() { 
     $html = file_get_contents('https://rechtliches.trolleymaker.com/datenschutzhinweise.html');
     return $html;
}
add_shortcode('white_label_datenschutzhinweise', 'white_label_get_datenschutzhinweise_shortcode');


function white_label_get_teilnahmebedingungen_shortcode() { 
     $html = file_get_contents('https://rechtliches.trolleymaker.com/teilnahmebedingungen.html');
     return $html;
}
add_shortcode('white_label_teilnahmebedingungen', 'white_label_get_teilnahmebedingungen_shortcode');



function white_label_get_einwilligungserklaerung_shortcode() { 
     $html = file_get_contents('https://rechtliches.trolleymaker.com/einwilligungserklaerung.html');
     return $html;
}
add_shortcode('white_label_einwilligungserklaerung', 'white_label_get_einwilligungserklaerung_shortcode');


function white_label_get_agb_partner_and_mitarbeitercard_shortcode() { 
     $html = file_get_contents('https://rechtliches.trolleymaker.com/agb-allgemein.html');
     return $html;
}
add_shortcode('white_label_agb_partner_and_mitarbeitercard', 'white_label_get_agb_partner_and_mitarbeitercard_shortcode');



function white_label_get_agb_shortcode() { 
     $html = file_get_contents('https://rechtliches.trolleymaker.com/agb.html');
     return $html;
}
add_shortcode('white_label_agb', 'white_label_get_agb_shortcode');


function white_label_get_participating_partners_table_shortcode() {
     $region_name = get_white_label_region_name();
     $output = '';
     try {
          $response = wp_remote_get( 'https://backend.mycity.cards/api/v1/partners?includeNonVisiblePartners=true&region_name=' . $region_name, array(
               'headers' => array(
                    'Accept' => 'application/json',
                    'X-API-KEY' => get_white_label_region_api_key()
               )
          ) );
          if ( ( !is_wp_error($response)) && (200 === wp_remote_retrieve_response_code( $response ) ) ) {
               $responseBody = json_decode($response['body']);
               if( json_last_error() === JSON_ERROR_NONE ) {
                   
                    if(count($responseBody) > 0) {
                         $output = '<h2>Teilnehmende Partner</h2>';
                         $output .= '<table class="tm-participating-partners-table">';
                         $output .= '<thead>';
                         $output .= '<tr>';
                         $output .= '<th>Name</th><th>Straße</th><th>PLZ</th><th>Ort</th>';
                         $output .= '</tr>';
                         $output .= '</thead>';
                         $output .= '<tbody>';
                         foreach ($responseBody as $key => $partner) {
                             $output .= '<tr>';
                             $output .= '<td>' . $partner->companyName . '</td>';
                             $output .= '<td>' . $partner->street . '</td>';
                             $output .= '<td>' . $partner->zip . '</td>';
                             $output .= '<td>' . $partner->city . '</td>';
                             $output .= '</tr>'; 
                         }
                         $output .= '</tbody>';
                         $output .= '</table>';
                    }
               }
          }
     } catch( Exception $ex ) {
          return $output;
     }

     $pdf_url = esc_url(site_url('?generate_partners_pdf=1&type=table'));
     $output .= '<div class="tm-partners-pdf-download">';
     $output .= '<button class="tm-partners-pdf-button" data-pdf-url="' . $pdf_url . '">';
     $output .= '<span class="tm-pdf-btn-text"><i class="fas fa-file-pdf"></i> Teilnehmende Partner als PDF herunterladen</span>';
     $output .= '<span class="tm-pdf-btn-spinner" style="display:none;"><i class="fas fa-spinner fa-spin"></i> PDF wird erstellt...</span>';
     $output .= '</button>';
     $output .= '</div>';

     return $output;
}
add_shortcode('white_label_participating_partners_table', 'white_label_get_participating_partners_table_shortcode');


function white_label_get_participating_partners_grid_shortcode() {
     $region_name = get_white_label_region_name();
     $outputFilter = '';
     $outputItems = '';
     $categories = array();

     try {
          $response = wp_remote_get( 'https://backend.mycity.cards/api/v1/partners?region_name=' . $region_name, array(
               'headers' => array(
                    'Accept' => 'application/json',
                    'X-API-KEY' => get_white_label_region_api_key()
               )
          ) );
          if ( ( !is_wp_error($response)) && (200 === wp_remote_retrieve_response_code( $response ) ) ) {
               $responseBody = json_decode($response['body']);
               if( json_last_error() === JSON_ERROR_NONE ) {
                   
                    if(count($responseBody) > 0) {
                         //echo print_r($responseBody, true);
                         $outputItems .= '<div class="participating-partners-container">';
                         foreach ($responseBody as $key => $partner) {
                             foreach ($partner->categories as $key => $category) {
                                   if(!array_key_exists($category, $categories)) {
                                        $categories[$category] = 1;
                                   } else {
                                        $categories[$category] = $categories[$category] + 1;
                                   }
                             }
 
                             $outputItems .= '<div class="participating-partners-item" data-tmid="' . $partner->gguid . '" data-category="' . implode(',', $partner->categories) . '">';
                             $outputItems .= '<img src="' . $partner->logoUrl . '" alt="' . $partner->companyName . ' Logo">';
                             $outputItems .= '<div class="participating-partners-item-companyName">' . $partner->companyName . '</div>';
                             $outputItems .= '<div class="participating-partners-item-overlay">';

                             $outputItems .= '</div>';
                             $outputItems .= '</div>';
                         }
                         $outputItems .= '</div>';
                         $outputItems .= '<div id="geschaefte-modal-wrapper">';
                         $outputItems .= '<div id="geschaefte-modal-container">';
                         $outputItems .= '<div id="geschaefte-modal-content-container" style="flex-basis: 95%; display: flex; flex-wrap: wrap;">';
                         $outputItems .= '<div id="geschaefte-modal-container-left-column">';
                         $outputItems .= '<img id="geschaefte-modal-logo" src="">';
                         $outputItems .= '</div>';
                         $outputItems .= '<div id="geschaefte-modal-container-right-column">';
                         $outputItems .= '<h3 id="geschaefte-modal-companyname" style="margin-bottom: 6px;"></h3>';
                         $outputItems .= '<h3 id="geschaefte-modal-permanentBonusInfoText" style="margin-bottom: 20px;"></h3>';
                         $outputItems .= '<h3 id="geschaefte-modal-actionBonusInfoText" style="margin-bottom: 20px;"></h3>';
                         $outputItems .= '<ul id="geschaefte-modal-ul">
                                        <li>
                                        <span><i aria-hidden="true" class="fas fa-home"></i></span>
                                        <span id="geschaefte-modal-address"></span>
                                        </li>
                                        <li>
                                        <span> <i aria-hidden="true" class="fas fa-phone"></i></span>
                                        <span id="geschaefte-modal-phone"></span>
                                        </li>
                                        <li>
                                        <span><i aria-hidden="true" class="fas fa-envelope"></i></span>
                                        <span id="geschaefte-modal-email"></span>
                                        </li>
                                        <li>
                                        <a id="geschaefte-modal-website" href="#" target="_blank" style="color: #333; font-weight: normal;"><span>
                                        <i aria-hidden="true" class="fas fa-globe-americas"></i></span>
                                        <span>Zur Website</span>
                                        </a>
                                        </li>
                                        <li>
                                        <span><i aria-hidden="true" class="fas fa-clock"></i></span>
                                        <span>Öffnungszeiten</span>
                                        <div id="geschaefte-modal-open-hours-additional-info"></div>
                                        <div id="mon" class="geschaefte-modal-opening-hours"><span class="geschaefte-modal-open-hours-day">Mo: </span><span class="geschaefte-modal-open-hours-times"></span></div>
                                        <div id="tue" class="geschaefte-modal-opening-hours"><span class="geschaefte-modal-open-hours-day">Di: </span><span class="geschaefte-modal-open-hours-times"></span></div>
                                        <div id="wed" class="geschaefte-modal-opening-hours"><span class="geschaefte-modal-open-hours-day">Mi: </span><span class="geschaefte-modal-open-hours-times"></span></div>
                                        <div id="thu" class="geschaefte-modal-opening-hours"><span class="geschaefte-modal-open-hours-day">Do: </span><span class="geschaefte-modal-open-hours-times"></span></div>
                                        <div id="fri" class="geschaefte-modal-opening-hours"><span class="geschaefte-modal-open-hours-day">Fr: </span><span class="geschaefte-modal-open-hours-times"></span></div>
                                        <div id="sat" class="geschaefte-modal-opening-hours"><span class="geschaefte-modal-open-hours-day">Sa: </span><span class="geschaefte-modal-open-hours-times"></span></div>
                                        <div id="sun" class="geschaefte-modal-opening-hours"><span class="geschaefte-modal-open-hours-day">So: </span><span class="geschaefte-modal-open-hours-times"></span></div>
                                        </li>
                                   </ul>';
                         $outputItems .= '</div>';
                         $outputItems .= '</div>';
                         $outputItems .= '<div id="participating-partners-loading-animation"><i class="fas fa-circle-notch rotating"></i></div>';
                         $outputItems .= '<div style="flex-basis: 5%;">';
                         $outputItems .= '<i id="close-geschaefte-modal-button" class="fas fa-times" style="font-size: 20px;"></i>';
                         $outputItems .= '</div>';
                         $outputItems .= '</div>';
                         $outputItems .= '</div>';
                    }
               }
          }
     } catch( Exception $ex ) {
          return $outputItems;
     }

     try {
          $responseCategories = wp_remote_get( 'https://backend.mycity.cards/api/v1/partners/categories', array(
               'headers' => array(
                    'Accept' => 'application/json',
                    'X-API-KEY' => get_white_label_region_api_key()
               )
          ) );
          if ( ( !is_wp_error($responseCategories)) && (200 === wp_remote_retrieve_response_code( $responseCategories ) ) ) {
               $responseBody = json_decode($responseCategories['body']);
               if( json_last_error() === JSON_ERROR_NONE ) {
                   
                    if(count($responseBody) > 0) {
                         $outputFilter .= '<div class="participating-partners-filter-container">';
                         $outputFilter .= '<ul>';
                         $outputFilter .= '<li class="participating-partners-filter-item" data-category="all">Alle anzeigen</li>';
                         foreach ($responseBody as $key => $category) {
                              if(array_key_exists($category, $categories)) {
                                   $outputFilter .= '<li class="participating-partners-filter-item" data-category="' . $category . '">';
                                   $outputFilter .= $category . ' (' . (array_key_exists($category, $categories) ? $categories[$category] : 0) . ')';
                                   $outputFilter .= '</li>';
                              }    
                         }
                         $outputFilter .= '</ul>';
                         $outputFilter .= '</div>';
                         
                    }
               }
          }
     } catch( Exception $ex ) {
          return 'error';
     }

     $output = $outputFilter . $outputItems;

     $pdf_url = esc_url(site_url('?generate_partners_pdf=1&type=grid'));
     $output .= '<div class="tm-partners-pdf-download">';
     $output .= '<button class="tm-partners-pdf-button" data-pdf-url="' . $pdf_url . '">';
     $output .= '<span class="tm-pdf-btn-text"><i class="fas fa-file-pdf"></i> Teilnehmende Partner als PDF herunterladen</span>';
     $output .= '<span class="tm-pdf-btn-spinner" style="display:none;"><i class="fas fa-spinner fa-spin"></i> PDF wird erstellt...</span>';
     $output .= '</button>';
     $output .= '</div>';

     return $output;
}
add_shortcode('white_label_participating_partners_grid', 'white_label_get_participating_partners_grid_shortcode');

// PDF download JavaScript (Fetch + Spinner + Blob download)
add_action('wp_footer', function() {
     if (!did_action('tm_partners_pdf_js_printed')) {
          do_action('tm_partners_pdf_js_printed');
          ?>
          <script>
          document.addEventListener('click', function(e) {
               var btn = e.target.closest('.tm-partners-pdf-button');
               if (!btn) return;
               e.preventDefault();
               if (btn.disabled) return;

               var textEl = btn.querySelector('.tm-pdf-btn-text');
               var spinnerEl = btn.querySelector('.tm-pdf-btn-spinner');
               var url = btn.getAttribute('data-pdf-url');

               btn.disabled = true;
               if (textEl) textEl.style.display = 'none';
               if (spinnerEl) spinnerEl.style.display = 'inline';

               fetch(url)
                    .then(function(response) {
                         if (!response.ok) throw new Error('PDF konnte nicht erstellt werden.');
                         var disposition = response.headers.get('Content-Disposition') || '';
                         var match = disposition.match(/filename="?([^";\n]+)"?/);
                         var filename = match ? match[1] : 'Teilnehmende_Akzeptanzstellen.pdf';
                         return response.blob().then(function(blob) {
                              return { blob: blob, filename: filename };
                         });
                    })
                    .then(function(result) {
                         var a = document.createElement('a');
                         a.href = URL.createObjectURL(result.blob);
                         a.download = result.filename;
                         document.body.appendChild(a);
                         a.click();
                         setTimeout(function() {
                              URL.revokeObjectURL(a.href);
                              document.body.removeChild(a);
                         }, 100);
                    })
                    .catch(function(err) {
                         alert('Fehler beim Erstellen des PDF: ' + err.message);
                    })
                    .finally(function() {
                         btn.disabled = false;
                         if (textEl) textEl.style.display = 'inline';
                         if (spinnerEl) spinnerEl.style.display = 'none';
                    });
          });
          </script>
          <?php
     }
});

// REST API handler for generating Partners PDF
function generate_partners_pdf_handler($request = null) {
     $type = $request ? $request->get_param('type') : (isset($_GET['type']) ? sanitize_text_field(wp_unslash($_GET['type'])) : 'table');
     if (!in_array($type, array('table', 'grid'), true)) {
          wp_die('Ungültige Anfrage.');
     }
     $region_name = get_white_label_region_name();
     $card_name = get_white_label_card_name();

     $include_non_visible = ($type === 'table') ? 'true' : 'false';
     $response = wp_remote_get('https://backend.mycity.cards/api/v1/partners?includeNonVisiblePartners=' . $include_non_visible . '&region_name=' . $region_name, array(
          'headers' => array(
               'Accept' => 'application/json',
               'X-API-KEY' => get_white_label_region_api_key()
          )
     ));

     if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
          wp_die('Fehler beim Laden der Partnerdaten.');
     }

     $partners = json_decode($response['body']);
     if (json_last_error() !== JSON_ERROR_NONE || empty($partners)) {
          wp_die('Keine Partnerdaten verfügbar.');
     }

     // Load our own namespaced TCPDF (TM_TCPDF) to avoid conflicts with voucher plugin
     if (!class_exists('TM_TCPDF')) {
          require_once get_stylesheet_directory() . '/lib/TCPDF-6.7.5/tcpdf.php';
     }
     $pdf = new TM_TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

     $pdf->SetCreator($card_name);
     $pdf->SetAuthor($card_name);
     $pdf->SetTitle('Teilnehmende Akzeptanzstellen - ' . $card_name);

     // Exo fonts — pre-converted into lib/TCPDF-6.7.5/fonts/ via convert-fonts.php
     $our_fonts = get_stylesheet_directory() . '/lib/TCPDF-6.7.5/fonts/';
     $exo_regular = (file_exists($our_fonts . 'exo.php')) ? 'exo' : 'dejavusans';
     $exo_semibold = (file_exists($our_fonts . 'exosemib.php')) ? 'exosemib' : 'dejavusansb';

     $pdf->setPrintHeader(false);
     $pdf->setPrintFooter(true);
     $pdf->setFooterData(array(0, 0, 0), array(0, 0, 0));
     $pdf->setFooterFont(array($exo_regular, '', 8));
     $pdf->setFooterMargin(10);

     $pdf->SetMargins(15, 15, 15);
     $pdf->SetAutoPageBreak(true, 20);

     $pdf->AddPage();

     // Card image (centered)
     $card_image_url = get_white_label_card_image_url();
     if (!empty($card_image_url)) {
          // Try local file path (WordPress uploads) — fastest, no HTTP request
          $card_img_path = null;
          $upload_dir = wp_get_upload_dir();
          if (!empty($upload_dir['baseurl']) && strpos($card_image_url, $upload_dir['baseurl']) === 0) {
               $local_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $card_image_url);
               if (file_exists($local_path)) {
                    $card_img_path = $local_path;
               }
          }
          // Fallback: download to temp file
          if (!$card_img_path) {
               $downloaded = download_url($card_image_url, 15);
               if (!is_wp_error($downloaded)) {
                    $card_img_path = $downloaded;
               }
          }
          if ($card_img_path) {
               try {
                    $pdf->Image($card_img_path, '', '', 60, 0, '', '', 'N', false, 300, 'C');
                    $pdf->Ln(5);
               } catch (Exception $e) {
                    // Image could not be loaded
               }
               // Only delete temp files, not the original upload
               if (!isset($local_path) || $card_img_path !== $local_path) {
                    @unlink($card_img_path);
               }
          }
     }

     // Header: Title (centered, uppercase) — Exo SemiBold 24pt #555555
     $pdf->SetFont($exo_semibold, '', 24);
     $pdf->SetTextColor(85, 85, 85);
     $pdf->Cell(0, 14, mb_strtoupper('Teilnehmende Akzeptanzstellen', 'UTF-8'), 0, 1, 'C');

     // Date (centered) — Exo Regular 10pt #555555
     $pdf->SetFont($exo_regular, '', 10);
     $pdf->Cell(0, 6, 'Stand: ' . date_i18n('d.m.Y'), 0, 1, 'C');

     // Description text (centered) — Exo Regular 10pt #555555
     $pdf->Ln(2);
     $pdf->SetFont($exo_regular, '', 10);
     $site_url = home_url();
     $description_html = '<span style="color: #555555;">Sie können Ihren Gutschein bei diesen Akzeptanzstellen einlösen.<br>Mehr Informationen unter: <a href="' . esc_url($site_url) . '">' . esc_html($site_url) . '</a></span>';
     $pdf->writeHTMLCell(0, 0, '', '', $description_html, 0, 1, false, true, 'C');

     $pdf->Ln(6);

     if ($type === 'grid') {
          // Grid layout: 4 columns with logo and company name
          $page_width = $pdf->getPageWidth() - 30; // 15mm margin each side
          $col_count = 4;
          $col_spacing = 3;
          $col_width = ($page_width - ($col_count - 1) * $col_spacing) / $col_count;
          $cell_height = 35;
          $margin_left = 15;
          $row_start_y = $pdf->GetY();

          // Batch-download all logos in parallel using curl_multi
          $logo_files = array();
          if (function_exists('curl_multi_init')) {
               $mh = curl_multi_init();
               $curl_handles = array();
               $tmp_files = array();
               foreach ($partners as $idx => $partner) {
                    if (!empty($partner->logoUrl)) {
                         $ch = curl_init($partner->logoUrl);
                         $tmp_file = tempnam(sys_get_temp_dir(), 'logo_');
                         $fp = fopen($tmp_file, 'w');
                         curl_setopt($ch, CURLOPT_FILE, $fp);
                         curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                         curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                         curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                         curl_multi_add_handle($mh, $ch);
                         $curl_handles[$idx] = array('ch' => $ch, 'fp' => $fp, 'file' => $tmp_file);
                    }
               }
               // Execute all requests in parallel
               do {
                    $status = curl_multi_exec($mh, $running);
                    curl_multi_select($mh);
               } while ($running > 0 && $status === CURLM_OK);
               // Collect results
               foreach ($curl_handles as $idx => $handle) {
                    fclose($handle['fp']);
                    $http_code = curl_getinfo($handle['ch'], CURLINFO_HTTP_CODE);
                    curl_multi_remove_handle($mh, $handle['ch']);
                    curl_close($handle['ch']);
                    if ($http_code === 200 && filesize($handle['file']) > 0) {
                         $logo_files[$idx] = $handle['file'];
                    } else {
                         @unlink($handle['file']);
                    }
               }
               curl_multi_close($mh);
          }

          $i = 0;
          foreach ($partners as $idx => $partner) {
               $col = $i % $col_count;

               if ($col === 0) {
                    $row_start_y = $pdf->GetY();
                    // Check if we need a new page (box + address text below)
                    if ($row_start_y + $cell_height + 14 > $pdf->getPageHeight() - 20) {
                         $pdf->AddPage();
                         $row_start_y = $pdf->GetY();
                    }
               }

               $x = $margin_left + $col * ($col_width + $col_spacing);
               $y = $row_start_y;

               // Draw border box — 1px solid #d0d0d0, border-radius 6px (~2mm)
               $pdf->RoundedRect($x, $y, $col_width, $cell_height, 2, '1111', 'D', array('width' => 0.26, 'color' => array(208, 208, 208)));

               // Add logo from pre-downloaded files
               if (isset($logo_files[$idx])) {
                    $img_info = @getimagesize($logo_files[$idx]);
                    if ($img_info !== false) {
                         $max_logo_w = 40;
                         $max_logo_h = 18;
                         $logo_x = $x + ($col_width - $max_logo_w) / 2;
                         $logo_y = $y + 3;
                         try {
                              $pdf->Image($logo_files[$idx], $logo_x, $logo_y, $max_logo_w, $max_logo_h, '', '', '', false, 300, '', false, false, 0, 'CM');
                         } catch (Exception $e) {
                              // Logo could not be loaded, skip it
                         }
                    }
               }

               // Company name below logo — Exo Regular 7pt #555555
               $pdf->SetFont($exo_regular, '', 7);
               $pdf->SetTextColor(85, 85, 85);
               $pdf->SetXY($x + 2, $y + $cell_height - 12);
               $pdf->MultiCell($col_width - 4, 4, $partner->companyName, 0, 'C');

               // Address below box — Exo Regular 6pt #555555
               $pdf->SetFont($exo_regular, '', 6);
               $addr_y = $y + $cell_height + 1;
               $street = !empty($partner->street) ? $partner->street : '';
               $zip_city = trim((!empty($partner->zip) ? $partner->zip . ' ' : '') . (!empty($partner->city) ? $partner->city : ''));
               if ($street) {
                    $pdf->SetXY($x, $addr_y);
                    $pdf->Cell($col_width, 3, $street, 0, 0, 'C');
                    $addr_y += 3;
               }
               if ($zip_city) {
                    $pdf->SetXY($x, $addr_y);
                    $pdf->Cell($col_width, 3, $zip_city, 0, 0, 'C');
               }

               // After last column in a row, move Y to next row (box + address space)
               if ($col === $col_count - 1) {
                    $pdf->SetY($row_start_y + $cell_height + 14);
               }

               $i++;
          }

          // If last row wasn't full, move Y down
          if ($i % $col_count !== 0) {
               $pdf->SetY($row_start_y + $cell_height + 14);
          }

          // Clean up temp logo files
          foreach ($logo_files as $tmp_logo) {
               @unlink($tmp_logo);
          }
     } else {
          // Table layout
          $html = '<table border="0.5" cellpadding="5" style="border-collapse: collapse;">';
          $html .= '<thead>';
          $html .= '<tr style="background-color: #f2f2f2; font-weight: bold; color: #555555;">';
          $html .= '<th width="35%" style="border-bottom: 2px solid #555555;">Name</th>';
          $html .= '<th width="30%" style="border-bottom: 2px solid #555555;">Straße</th>';
          $html .= '<th width="10%" style="border-bottom: 2px solid #555555;">PLZ</th>';
          $html .= '<th width="25%" style="border-bottom: 2px solid #555555;">Ort</th>';
          $html .= '</tr>';
          $html .= '</thead>';
          $html .= '<tbody>';

          foreach ($partners as $index => $partner) {
               $bg = ($index % 2 === 0) ? '#ffffff' : '#f9f9f9';
               $html .= '<tr style="background-color: ' . $bg . '; color: #555555;">';
               $html .= '<td width="35%" style="border-bottom: 1px solid #d0d0d0;">' . esc_html($partner->companyName) . '</td>';
               $html .= '<td width="30%" style="border-bottom: 1px solid #d0d0d0;">' . esc_html($partner->street) . '</td>';
               $html .= '<td width="10%" style="border-bottom: 1px solid #d0d0d0;">' . esc_html($partner->zip) . '</td>';
               $html .= '<td width="25%" style="border-bottom: 1px solid #d0d0d0;">' . esc_html($partner->city) . '</td>';
               $html .= '</tr>';
          }

          $html .= '</tbody>';
          $html .= '</table>';

          $pdf->SetFont($exo_regular, '', 10);
          $pdf->SetTextColor(85, 85, 85);
          $pdf->writeHTML($html, true, false, true, false, '');
     }

     // Total count (centered) — Exo Regular 10pt #555555
     $pdf->Ln(5);
     $pdf->SetFont($exo_regular, 'I', 10);
     $pdf->SetTextColor(85, 85, 85);
     $pdf->Cell(0, 6, 'Gesamt: ' . count($partners) . ' teilnehmende Akzeptanzstellen', 0, 1, 'C');

     $filename = 'Teilnehmende_Akzeptanzstellen_' . sanitize_file_name($card_name) . '_' . gmdate('Y-m-d') . '.pdf';
     $pdf->Output($filename, 'D');
     exit;
}
add_action('init', function() {
     if (isset($_GET['generate_partners_pdf']) && $_GET['generate_partners_pdf'] === '1') {
          generate_partners_pdf_handler();
     }
});


function white_label_faqs_shortcode($atts) {

     $atts = shortcode_atts( array(
          'faq_object' => '',
          'faq_type' => ''
     ), $atts, 'white_label_faqs' );

     if($atts['faq_type'] == '') {
          return '';
     }

     $faq_object = '';
     if($atts['faq_object'] == 'customer' || $atts['faq_object'] == 'customers') {
          $faq_object = 'customers';
     }
     if($atts['faq_object'] == 'partner' || $atts['faq_object'] == 'partners') {
          $faq_object = 'partners';
     }
     if($atts['faq_object'] == 'employer' || $atts['faq_object'] == 'employers') {
          $faq_object = 'employers';
     }
     if($faq_object == '') {
          return '';
     }

     $region_name = get_white_label_region_name();

     $customer_faqs = array();

     try {
          $response = wp_remote_get( 'https://backend.mycity.cards/api/v1/' . $faq_object . '/faqs?region_name=' . $region_name . '&faq_type=' . $atts['faq_type'], array(
               'headers' => array(
                    'Accept' => 'application/json',
                    'X-API-KEY' => get_white_label_region_api_key()
               )
          ) );
          if ( ( !is_wp_error($response)) && (200 === wp_remote_retrieve_response_code( $response ) ) ) {
               $responseBody = json_decode($response['body']);
               if( json_last_error() === JSON_ERROR_NONE ) {
                   
                    if(count($responseBody) > 0) {
                        
                         foreach ($responseBody as $key => $faq) {
                             
                             $outputItems .= '<button class="accordion-question"><span class="question-text">' . $faq->question . '</span></button>';

                             $outputItems .= '<div class="accordion-answer">';
                             $outputItems .= $faq->answer;
                             $outputItems .= '</div>';
                         }


                    }
               }
          }
     } catch( Exception $ex ) {
          return $outputItems;
     }


     $output = $outputItems;

     return $output;
}
add_shortcode('white_label_faqs', 'white_label_faqs_shortcode');



add_action('parse_query',     function ( $query, $error = true ) {
          if ( is_search() && ! is_admin() ) {
               $query->is_search       = false;
               $query->query_vars['s'] = false;
               $query->query['s']      = false;
               $query->set_404();
               status_header( 404 );
               nocache_headers();
               if ( true === $error ) {
                    $query->is_404 = true;
               }
          }
},   15, 2);

// Remove the Search Widget.
add_action('widgets_init',    function () {
          unregister_widget( 'WP_Widget_Search' );
     }
);

// Remove the search form.
add_filter( 'get_search_form', '__return_empty_string', 999 );

// Remove the core search block.
add_action(    'init', function () {
     if ( ! function_exists( 'unregister_block_type' ) || ! class_exists( 'WP_Block_Type_Registry' ) ) {
          return;
     }
     $block = 'core/search';
     if ( WP_Block_Type_Registry::get_instance()->is_registered( $block ) ) {
          unregister_block_type( $block );
     }
});

// Remove admin bar menu search box.
add_action('admin_bar_menu', function ( $wp_admin_bar ) {
          $wp_admin_bar->remove_menu( 'search' );
},   11 );



add_action( 'rest_api_init', 'register_rest_images' );
function register_rest_images() {
    register_rest_field( array( 'post' ),
        'featured_image_url',
        array(
            'get_callback'    => 'get_rest_featured_image',
            'update_callback' => null,
            'schema'          => null,
        )
    );

    register_rest_field( array( 'veranstaltungen' ),
        'featured_image_url',
        array(
            'get_callback'    => 'get_rest_featured_image',
            'update_callback' => null,
            'schema'          => null,
        )
    );

    register_rest_field( array( 'veranstaltungen' ),
        'categories',
        array(
            'get_callback'    => 'get_rest_veranstaltungen_categories',
            'update_callback' => null,
            'schema'          => null,
        )
    );

    register_rest_field( array( 'jobs' ),
        'featured_image_url',
        array(
            'get_callback'    => 'get_rest_featured_image',
            'update_callback' => null,
            'schema'          => null,
        )
    );

    register_rest_field( array( 'traineejobs' ),
        'featured_image_url',
        array(
            'get_callback'    => 'get_rest_featured_image',
            'update_callback' => null,
            'schema'          => null,
        )
    );
}

function get_rest_featured_image( $object, $field_name, $request ) {
    if ( $object['featured_media'] ) {
        $img = wp_get_attachment_image_src( $object['featured_media'], 'full' );
        if ( empty( $img ) ) {
            return false;
        }
        return $img[0];
    }
    return false;
}

function get_rest_veranstaltungen_categories($post_arr) {

     $terms = get_the_terms( $post_arr['id'], 'veranstaltungen_kategorien' ); // <- Name der Taxonomy
     if ( empty( $terms ) || is_wp_error( $terms ) ) {
          return null;
     }

     // Gib nur die Namen zurück
     return wp_list_pluck( $terms, 'name' );
}



add_action( 'rest_api_init', function () {
  register_rest_route( 'slider/v1', '/all/', array(
    'methods' => 'GET',
    'callback' => 'get_slider_content',
    'permission_callback' => '__return_true',
  ) );
} );


function get_slider_content() {
     $posts = get_posts( array(
          'post_type' => array('veranstaltungen', 'jobs', 'post'),
          'posts_per_page' => '10',
          'post_status' => 'publish',
          'tax_query' => array(
              array(
                'taxonomy' => 'steuerung',
                'field' => 'slug',
                'terms' => 'im-slider-anzeigen',
                'include_children' => false
               )
          )
     ) );

     $response = array();

     foreach ($posts as $post) {
          $tempPost = new stdClass();
          $tempPost->id = $post->ID;
          $tempPost->title = $post->post_title;
          $tempPost->excerpt = $post->post_excerpt;
          switch ($post->post_type) {
               case 'veranstaltungen':
                    $tempPost->type = 'veranstaltungen';
                    break;
               case 'veranstaltungens':
                    $tempPost->type = 'veranstaltungen';
                    break;
               case 'veranstaltungs':
                    $tempPost->type = 'veranstaltungen';
                    break;
               case 'jobs':
                    $tempPost->type = 'jobs';
                    break;
               case 'job':
                    $tempPost->type = 'jobs';
                    break;
               case 'posts':
                    $tempPost->type = 'news';
                    break;
               case 'post':
                    $tempPost->type = 'news';
                    break;
               default:
                    $tempPost->type = '';
                    break;
          }
          $post_thumbnail = get_the_post_thumbnail_url($post->ID,'full');
          if(is_bool($post_thumbnail) || $post_thumbnail == false) {
               $tempPost->featured_image = "https://www.trolleymaker.com/wp-content/uploads/placeholder.png";
          } else {
               $tempPost->featured_image = $post_thumbnail;
          }
          $acf = get_fields($post->ID);
          if(is_array($acf)) {
               if(array_key_exists('link_zur_stellenanzeige', $acf)) {
                    $tempPost->link_to_job = $acf['link_zur_stellenanzeige'];
               }
               if(array_key_exists('startzeitpunkt', $acf)) {
                    $tempPost->start_date = $acf['startzeitpunkt'];
               }
               if(array_key_exists('endzeitpunkt', $acf)) {
                    $tempPost->end_date = $acf['endzeitpunkt'];
               }
          }
          array_push($response, $tempPost);
     }


     if(count($posts) === 0) {
          return [];
     }

     return $response;
}

/**
 * Add PayPal fee if customer is company
 *
 */
function woo_add_paypal_fee($cart) {

     if ( is_admin() && ! defined( 'DOING_AJAX' ) )  {
          return;
     }
     
     parse_str(WC()->checkout()->get_value('post_data'), $post_data);

     if(count($post_data) == 0 && count($_POST) == 0) {
          return;
     }

     //info: on updating cart the data is in $post_data, and on checkout button clicked the data is in $_POST

     $percent = get_white_label_paypal_fee();
     $euro_per_voucher = get_white_label_paypal_fee_per_voucher();
     $cart_total = $cart->cart_contents_total;

     $fee = ($cart_total / 100 * $percent);

     if($euro_per_voucher > 0) {
          $quantity = 0;
          foreach ( $cart->get_cart() as $cart_item ) {
               $quantity += $cart_item['quantity']; 
          }
          $fee = ($cart_total / 100 * $percent) + ($quantity * $euro_per_voucher);
     }
     
     //if(array_key_exists('payment_method', $post_data)) {
          //$chosen_payment_method = $post_data['payment_method'];
          //if ($chosen_payment_method == 'paypal_plus' || $chosen_payment_method == 'paypal') {
               if((array_key_exists('billing_options', $post_data) && !empty($post_data['billing_options']) && $post_data['billing_options'] == 'firmenkauf' ) || (array_key_exists('billing_options', $_POST) && !empty($_POST['billing_options']) && $_POST['billing_options'] == 'firmenkauf')) {
                    $cart->add_fee( 'Bearbeitungsgebühr', $fee, true, 'Steuern Fee' );
               }
          //}
     //}
}
add_action( 'woocommerce_cart_calculate_fees', 'woo_add_paypal_fee' );



function add_fee_to_billomat_invoice( $invoice_data, $order ) {

     $billing_option = get_post_meta($order->get_id(), 'billing_options', true);
     if($billing_option == 'privatkauf') {
          foreach ( $invoice_data['invoice-items']['invoice-item'] as $index => $invoice_item ) {
               $invoice_data['invoice-items']['invoice-item'][$index]['description'] = 'Privatkauf';
          }
     }

     $fees = $order->get_fees();
    
     if($fees != NULL && count($fees) > 0) {
          foreach ( $fees as $fee ) {
               if($fee->get_total() > 0) {
                    $invoice_item_data = array(
                         'quantity' => 1,
                         'unit_price' => $fee->get_total(),
                         'tax_name' => "MwSt",
                         'tax_rate' => 19.0,
                         'tax_changed_manually' => true,
                         'title' => $fee->get_name(),
                    );

                    array_push($invoice_data['invoice-items']['invoice-item'], $invoice_item_data);
               }
          }
     }

     return $invoice_data;
}
add_filter( 'woocommerce_billomat_invoice_data', 'add_fee_to_billomat_invoice', 20, 2 );


function change_woo_voucher_table_buyer_information($buyer_details, $order) {

     $billing_option = get_post_meta($order->get_id(), 'billing_options', true);
     $buyer_details['privatkauffirmenkauf'] = 't';
     if (!empty($billing_option)) {
          write_log('no empty');
          $buyer_details['privatkauffirmenkauf'] = $billing_option;
     }

     return $buyer_details;
}
add_filter('woo_vou_get_buyer_information', 'change_woo_voucher_table_buyer_information', 10, 2);


function change_woo_voucher_csv_esport_buyer_information($buyer_details_html,$buyers_information) {

     if (isset($buyers_information['privatkauffirmenkauf']) && !empty($buyers_information['privatkauffirmenkauf'])) {
          $billing_option = $buyers_information['privatkauffirmenkauf'];
          $buyer_details_html .= "\n" . 'Zweck: '. $billing_option;
     }

     return $buyer_details_html;
}
add_filter('woo_vou_csv_buyer_info', 'change_woo_voucher_csv_esport_buyer_information', 10, 2);



function change_woo_voucher_table_buyer_information_html($buyer_details_html, $buyers_information) {

     if (isset($buyers_information['privatkauffirmenkauf']) && !empty($buyers_information['privatkauffirmenkauf'])) {
          $buyer_details_html = str_replace('</table>', '', $buyer_details_html);
          $billing_option = $buyers_information['privatkauffirmenkauf'];
          $buyer_details_html .= '<tr>';
          $buyer_details_html .= '<td width="20%" style="font-weight:bold;">Zweck:</td>';
          $buyer_details_html .= '<td width="80%">' . $billing_option . '</td>';
          $buyer_details_html .= '</tr>';
          $buyer_details_html .= '</table>';

     }

     return $buyer_details_html;
}
add_filter('woo_vou_display_buyer_info_html', 'change_woo_voucher_table_buyer_information_html', 30, 2);



function change_woo_voucher_table_order_information_html($order_details_html, $order_id, $type) {

     $billing_option = get_post_meta($order_id, 'billing_options', true);

     $order = wc_get_order( $order_id );
     if($order) {
           $fees = $order->get_fees();
    
          if($fees != NULL && count($fees) > 0) {
               foreach ( $fees as $fee ) {
                    if($fee->get_total() > 0) {
                         if($type == 'html') {
                              $order_details_html = str_replace('</table>', '', $order_details_html);
                              $order_details_html .= '<tr><td style="font-weight:bold;">' . $fee->get_name() . '</td><td>' . $fee->get_total() . ' €</td></tr>';
                              $order_details_html .= '</table>';
                         } else if($type == 'csv') {
                              $order_details_html .= "\n" . $fee->get_name() . ': ' . html_entity_decode(strip_tags($fee->get_total())) . ' €';
                         }
                    }
               }
          }

          foreach( $order->get_items('tax') as $item ) {
               if($item->get_tax_total() > 0) {
                    if($type == 'html') {
                         $order_details_html = str_replace('</table>', '', $order_details_html);
                         $order_details_html .= '<tr><td style="font-weight:bold;">' . $item->get_label() . '</td><td>' . $item->get_tax_total() . ' €</td></tr>';
                         $order_details_html .= '</table>';
                    } else if($type == 'csv') {
                         $order_details_html .= "\n" . $item->get_label() . ': ' . html_entity_decode(strip_tags($item->get_tax_total())) . ' €';
                    }
               }
          }
     }

     return $order_details_html;
}
add_filter('woo_vou_display_order_info_html', 'change_woo_voucher_table_order_information_html', 30, 3);

add_action( 'woocommerce_payment_complete_order_status', 'wc_auto_complete_paid_order', 10, 3 );
function wc_auto_complete_paid_order( $status, $order_id, $order ) {
    return 'completed';
}
