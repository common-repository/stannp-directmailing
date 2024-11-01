<?php
/**
 * Plugin Name: Direct Mail for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/stannp-directmailing/
 * Version: 0.7
 * Description: A plugin that connects to the Stannp.com API
 * Author: Stannp LTD
 * Author URI: http://stannp.com/
 * Developer: Stannp LTD
 * Developer URI: http://stannp.com/
 *
 * WC requires at least: 2.2
 * WC tested up to: 3.4.5
 *
 * Copyright: © 2009-2015 WooCommerce.
 * License: GPL-3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

// * Plugin--URI: https://www.stannp.com?source=woocommerce

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WC_Stannp_DirectMailing_Integration')) :

    /**
     * WooCommerce Stannp DirectMailing Integration main class.
     */
    class WC_Stannp_DirectMailing_Integration
    {

        /**
         * Plugin version.
         *
         * @var string
         */
        const VERSION = '0.7';

        /**
         * Instance of this class.
         *
         * @var object
         */
        protected static $instance = null;


        /**
         * WC_Stannp_DirectMailing_Integration constructor. Initializes the plugin.
         */
        private function __construct()
        {
            // Load plugin text domain
            add_action('init', array($this, 'load_plugin_textdomain'));

            // Checks if WooCommerce is installed.
            if (class_exists('WC_Integration') && defined('WOOCOMMERCE_VERSION') && version_compare(WOOCOMMERCE_VERSION, '2.1-beta-1', '>=')) {
                include_once 'includes/class-wc-stannp-directmailing.php';

                // Register the integration.
                add_filter('woocommerce_integrations', array($this, 'add_integration'));
                add_action('admin_notices', array($this, 'stannp_welcome_notice'));


            } else {
                add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            }
        }

        /**
         *
         * add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_links' ) );
         * }
         *
         * /**
         * Function to display the links under the plugin name
         *
         * @param $links
         *
         * @return array
         */
        public function plugin_links($links)
        {
            $settings_url = add_query_arg(
                array(
                    'page' => 'wc-settings',
                    'tab' => 'integration',
                    'section' => 'stannp_directmailing'
                ),
                admin_url('admin.php')
            );

            $plugin_links = array(
                '<a href="' . esc_url($settings_url) . '">' . __('Settings', 'woocommerce-stannp-directmailing-integration') . '</a>',
                '<a href="https://wordpress.org/support/plugin/stannp-directmailing">' . __('Support', 'woocommerce-stannp-directmailing-integration') . '</a>',
            );

            return array_merge($plugin_links, $links);
        }

        /**
         * Returns an instance of this class.
         *
         * @return object|WC_Stannp_DirectMailing_Integration
         */
        public static function get_instance()
        {
            // If the single instance hasn't been set, set it now.
            if (null == self::$instance) {
                self::$instance = new self;
            }

            return self::$instance;
        }

        /**
         *  Load the plugin text domain for translation.
         *
         * @return void
         */
        public function load_plugin_textdomain()
        {
            $locale = apply_filters('plugin_locale', get_locale(), 'woocommerce-stannp-directmailing-integration');

            load_textdomain('woocommerce-stannp-directmailing-integration', trailingslashit(WP_LANG_DIR) . 'woocommerce-stannp-directmailing-integration/woocommerce-stannp-directmailing-integration-' . $locale . '.mo');
            load_plugin_textdomain('woocommerce-stannp-directmailing-integration', false, dirname(plugin_basename(__FILE__)) . '/languages/');
        }

        /**
         * WooCommerce fallback notice.
         *
         * @return string
         */
        public function woocommerce_missing_notice()
        {
            echo '<div class="error"><p>' . sprintf(__('WooCommerce Stannp DirectMailing depends on the last version of %s to work!', 'woocommerce-stannp-directmailing-integration'), '<a href="http://www.woothemes.com/woocommerce/" target="_blank">' . __('WooCommerce', 'woocommerce-stannp-directmailing-integration') . '</a>') . '</p></div>';
        }

        /**
         * Stannp install notice.
         *
         * @return string
         */
        public function stannp_welcome_notice()
        {
            //TODO show this if there is no api key set

            $stannp_class = new WC_Stannp_DirectMailing();
            if (empty($stannp_class->settings['stannp_api_key'])) {
                echo '<div id="stannp-setup-message" class="updated stannp-message " >
	                <p>' . sprintf(__('Thanks for choosing', 'woocommerce'))  . '<strong> ' . sprintf(__('Direct Mail for WooCommerce', 'woocommerce')) . '</strong>, ' . sprintf(__('click the button to get started.', 'woocommerce')) . '</p>
	                <p>' . sprintf(__("We're so confident that you will love what we do, we are giving you ", 'woocommerce')) . '<strong>' . sprintf(__('£50 free credit*', 'woocommerce')) . '</strong> ' . sprintf(__ ('to try it for yourself!', 'woocommerce')) . '</p>
	                <p class="submit"><a href="' . esc_url(admin_url('admin.php?page=stannp-setup&&reset_admin_menu=1')) . '" class="stannp-giant-button">' . __('Get started with £50 free credit', 'woocommerce') . '</a> </p>
                    <img src="' . plugin_dir_url(__FILE__) . 'assets/images/logo2.png' .'" height="64" width="64">
                    <img src="' . plugin_dir_url(__FILE__) . 'assets/images/subtext.png' .'" class="subtext-image">
                </div>';
            }
        }

        /**
         * Add a new integration to WooCommerce.
         *
         * @param $integrations
         *
         * @return array Stannp DirectMailing integration
         */
        public function add_integration($integrations)
        {
            $integrations[] = 'WC_Stannp_DirectMailing';

            return $integrations;
        }
    }

    add_action('plugins_loaded', array('WC_Stannp_DirectMailing_Integration', 'get_instance'), 0);

endif;

