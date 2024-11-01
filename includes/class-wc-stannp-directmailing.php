<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

include_once "class-wc-stannp-setup.php";
/**
 * Stannp DirectMailing Integration
 *
 * Adds Stannp Menu to the Admin Panel and the relevant pages
 * Implements the bulk export to Stannp of Wordpress users
 *
 * @class   WC_Stannp_DirectMailing
 * @extends WC_Integration
 */
class WC_Stannp_DirectMailing extends WC_Integration
{

    const STANNP_API_CLASS_NAMES = array('account', 'campaign', 'group', 'recipient', 'template');
    const DEFAULT_ACCOUNT_TEXT = 'There is no information to show';

    private $stannp_api_key;

    /**
     * WC_Stannp_DirectMailing constructor. Init and hook in the integration.
     *
     * @return void
     */
    public function __construct()
    {

        $user = wp_get_current_user();
        $param_string = '';

        if (!empty($user->ID)) {
            //if we have a logged on user build the parameter string

            $params = array(
                'source' => 'woocommerce',
                'firstname' => $user->user_firstname,
                'lastname' => $user->user_lastname,
                'email' => $user->user_email,
            );

            $param_string = http_build_query($params);
        }

        $this->id = 'stannp_directmailing';
        $this->method_title = __('Stannp DirectMailing', 'woocommerce-stannp-directmailing-integration');
        $this->method_description = __('Stannp DirectMailing is a free service that communicates with Stannp.com. Use the buttons below to: ', 'woocommerce-stannp_directmailing-integration') . '<div class="banner-under">' .
            '<a href="https://dash.stannp.com/my-key" target="_blank" class="button">' . __('Get your API key', 'woocommerce-stannp-directmailing-integration') . '</a>' .
            '<a href="https://dash.stannp.com/register?source=woocommerce" target="_blank" class="button">' . __('Register on our website', 'woocommerce-stannp-directmailing-integration') . '</a>' .
            '<a href="' . esc_url('https://dash.stannp.com/register?' . $param_string) . '" target="_blank" class="button">' . __('Get registered automatically',  "woocommerce-stannp-directmailing-integration") . '</a>' .
            '</div>';
        $this->stannp_api_key = sanitize_text_field($this->get_stannp_api_key());


        // Load the settings
        $this->include_api_files();
        // Load the settings
        //$this->include_setup_files();
        $this->init_form_fields();
        $this->init_settings();
        $this->init_hooks();

    }

    public  function init_hooks()
    {
        $setupClass = new WC_Stannp_Setup();

        // Admin Options
        add_action('admin_enqueue_scripts', array($this, 'load_admin_assets'));
        add_action('admin_enqueue_scripts', array($this, 'load_stannp_css'));
        add_action('woocommerce_update_options_integration_stannp_directmailing', array($this, 'process_admin_options'));
        add_action('woocommerce_update_options_integration_stannp_directmailing', array($this, 'update_textarea'));

        //add Stannp Menu
        add_action('admin_menu', array($this, 'stannp_menu_page'));
        add_action('admin_menu', array($this, 'stannp_campaign_page'));
        add_action('admin_menu', array($setupClass, 'stannp_setup_page'));
        add_action( 'admin_init', array( $setupClass, 'setup_wizard' ) );
        add_filter('bulk_actions-users', array($this, 'register_my_bulk_actions'));
        add_filter('handle_bulk_actions-users', array($this, 'stannp_bulk_action_handler'), 10, 3);
        add_action('admin_notices', array($this, 'stannp_bulk_action_admin_notice'));
        add_action('admin_notices', array($this, 'stannp_campaign_form_submit'));
    }


    public function load_stannp_css(){
        wp_register_style('wc-stannp-directmailing-admin-css', plugins_url('/assets/css/stannp.css', dirname(__FILE__)), false, null);
        wp_enqueue_style('wc-stannp-directmailing-admin-css');
    }
    /**
     * The fields to be displayed under the Settings->Integration txab->Stannp DirectMailing (admin)
     */
    public function init_form_fields()
    {
        $group_names = $this->get_group_names();

        if (count($group_names) === 1) {
            //TODO Show the wizard instead?
//            add_action('admin_notices', array($this, 'curl_gone_wrong'));
        }

        $this->form_fields = array(
            'stannp_api_key' => array(
                'title' => __('Stannp API Key', 'woocommerce-stannp-directmailing-integration'),
                'description' => __('Go to <a href="https://dash.stannp.com/my-key" target="_blank">this URL</a> to get your Stannp API key', 'woocommerce-stannp_directMailing-integration'),
                'type' => 'text',
                'class' => 'required',
                'placeholder' => 'xxxxxxxxxxxxxxxx',
            ),
            'stannp_account_information' => array(
                'title' => __('Account Information', 'woocommerce-stannp-directmailing-integration'),
                'description' => __('Your Stannp account information. Add your API key to see more...', 'woocommerce-stannp_directMailing-integration'),
                'type' => 'textarea',
                'custom_attributes' => array('readonly' => 'readonly'),
                'default' => __(self::DEFAULT_ACCOUNT_TEXT,  "woocommerce-stannp-directmailing-integration"),
            ),
            'stannp_groups' => array(
                'title' => __('Stannp Groups', 'woocommerce-stannp-directmailing-integration'),
                'description' => __('List of your groups created in your Stannp.com account', 'woocommerce-stannp-directmailing-integration'),
                'type' => 'select',
                'options' => $group_names,
            ),
        );
    }


    /**
     * Adds a new menu page for Stannp + menu icon
     *
     * @return void
     */
    function stannp_menu_page()
    {
        //add top level menu page
        add_menu_page(
            'Stannp DirectMailing',
            'Stannp',
            'manage_options',
            'stannp',
            array($this, 'stannp_settings_page_html'),
            'data:image/svg+xml;base64,' . base64_encode(
                '<svg viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg"><path fill="#5e5e5f" style="fill: var(--color1, #5e5e5f)" d="M26.699 1.205c-0.030 0.002-0.060 0.004-0.090 0.008l-0.071 0.009c0.002 0.009 0.005 0.023 0.008 0.037l0.001 0.005c0.004 0.029 0.006 0.062 0.006 0.095 0 0.388-0.29 0.709-0.665 0.757l-0.004 0c-0.029 0.004-0.062 0.006-0.095 0.006-0.388 0-0.709-0.29-0.757-0.665l-0-0.004c-0.002-0.010-0.004-0.024-0.005-0.039l-0-0.003-0.249 0.032c0.002 0.009 0.005 0.023 0.008 0.037l0.001 0.005c0.004 0.029 0.006 0.062 0.006 0.095 0 0.388-0.29 0.709-0.665 0.757l-0.004 0c-0.029 0.004-0.062 0.006-0.095 0.006-0.388 0-0.709-0.29-0.757-0.665l-0-0.004c-0.002-0.010-0.004-0.024-0.005-0.039l-0-0.003-0.249 0.032c0.002 0.009 0.005 0.023 0.008 0.037l0.001 0.005c0.004 0.029 0.006 0.062 0.006 0.095 0 0.388-0.29 0.709-0.665 0.757l-0.004 0c-0.029 0.004-0.062 0.006-0.095 0.006-0.388 0-0.709-0.29-0.757-0.665l-0-0.004c-0.002-0.010-0.004-0.024-0.005-0.039l-0-0.003-0.249 0.032c0.002 0.009 0.005 0.023 0.008 0.037l0.001 0.005c0.004 0.029 0.006 0.062 0.006 0.095 0 0.388-0.29 0.709-0.665 0.757l-0.004 0c-0.029 0.004-0.062 0.006-0.095 0.006-0.388 0-0.709-0.29-0.757-0.665l-0-0.004c-0.002-0.010-0.004-0.024-0.005-0.039l-0-0.003-0.249 0.032c0.002 0.009 0.005 0.023 0.008 0.037l0.001 0.005c0.004 0.029 0.006 0.062 0.006 0.095 0 0.388-0.29 0.709-0.665 0.757l-0.004 0c-0.029 0.004-0.062 0.006-0.095 0.006-0.388 0-0.709-0.29-0.757-0.665l-0-0.004c-0.002-0.010-0.004-0.024-0.005-0.039l-0-0.003-0.249 0.032c0.002 0.009 0.005 0.023 0.008 0.037l0.001 0.005c0.004 0.029 0.006 0.062 0.006 0.095 0 0.388-0.29 0.709-0.665 0.757l-0.004 0c-0.029 0.004-0.062 0.006-0.095 0.006-0.388 0-0.709-0.29-0.757-0.665l-0-0.004c-0.002-0.010-0.004-0.024-0.005-0.039l-0-0.003-0.249 0.032c0.002 0.009 0.005 0.023 0.008 0.037l0.001 0.005c0.004 0.029 0.006 0.062 0.006 0.095 0 0.388-0.29 0.709-0.665 0.757l-0.004 0c-0.029 0.004-0.062 0.006-0.095 0.006-0.388 0-0.709-0.29-0.757-0.665l-0-0.004c-0.002-0.010-0.004-0.024-0.005-0.039l-0-0.003-0.249 0.032c0.002 0.009 0.005 0.023 0.008 0.037l0.001 0.005c0.004 0.029 0.006 0.062 0.006 0.095 0 0.388-0.29 0.709-0.665 0.757l-0.004 0c-0.028 0.004-0.061 0.006-0.095 0.006-0.388 0-0.709-0.29-0.757-0.665l-0-0.004c-0.002-0.010-0.004-0.024-0.005-0.039l-0-0.003-0.249 0.032c0.002 0.009 0.005 0.023 0.008 0.037l0.001 0.005c0.004 0.029 0.006 0.062 0.006 0.095 0 0.388-0.29 0.709-0.665 0.757l-0.004 0c-0.028 0.004-0.061 0.006-0.095 0.006-0.388 0-0.709-0.29-0.757-0.665l-0-0.004c-0.002-0.010-0.004-0.024-0.005-0.039l-0-0.003-0.249 0.032c0.002 0.009 0.005 0.023 0.008 0.037l0.001 0.005c0.004 0.029 0.006 0.062 0.006 0.095 0 0.388-0.29 0.709-0.665 0.757l-0.004 0c-0.028 0.004-0.061 0.006-0.095 0.006-0.388 0-0.709-0.29-0.757-0.665l-0-0.004c-0.002-0.010-0.004-0.024-0.005-0.039l-0-0.003-0.249 0.032c0.002 0.009 0.005 0.023 0.008 0.037l0.001 0.005c0.004 0.029 0.006 0.062 0.006 0.095 0 0.388-0.29 0.709-0.665 0.757l-0.004 0c-0.028 0.004-0.061 0.006-0.095 0.006-0.388 0-0.709-0.29-0.757-0.665l-0-0.004c-0.002-0.010-0.004-0.024-0.005-0.039l-0-0.003-0.249 0.032c0.002 0.009 0.005 0.023 0.008 0.037l0.001 0.005c0.004 0.029 0.006 0.062 0.006 0.095 0 0.388-0.29 0.709-0.665 0.757l-0.004 0c-0.028 0.004-0.061 0.006-0.095 0.006-0.388 0-0.709-0.29-0.757-0.665l-0-0.004c-0.002-0.010-0.004-0.024-0.005-0.039l-0-0.003-0.249 0.032c0.002 0.009 0.005 0.023 0.008 0.037l0.001 0.005c0.004 0.029 0.006 0.062 0.006 0.095 0 0.388-0.29 0.709-0.665 0.757l-0.004 0c-0.028 0.004-0.061 0.006-0.095 0.006-0.388 0-0.709-0.29-0.757-0.665l-0-0.004c-0.002-0.010-0.004-0.024-0.005-0.039l-0-0.003-0.249 0.032c0.002 0.009 0.005 0.023 0.008 0.037l0.001 0.005c0.004 0.029 0.006 0.062 0.006 0.095 0 0.388-0.29 0.709-0.665 0.757l-0.004 0c-0.028 0.004-0.061 0.006-0.095 0.006-0.388 0-0.709-0.29-0.757-0.665l-0-0.004c-0.002-0.010-0.004-0.024-0.005-0.039l-0-0.003-0.068-0.006c-0.061 0.006-0.119 0.021-0.175 0.039 0.001 0.003 0.003 0.012 0.004 0.021l0.001 0.005c0.004 0.029 0.006 0.062 0.006 0.095 0 0.388-0.29 0.709-0.665 0.757l-0.004 0c-0.005 0.001-0.014 0.002-0.023 0.002l-0.003 0c-0.004 0.059-0.002 0.119 0.006 0.179l0.009 0.071c0.009-0.002 0.023-0.005 0.037-0.007l0.004-0.001c0.029-0.004 0.062-0.006 0.095-0.006 0.388 0 0.709 0.29 0.757 0.665l0 0.004c0.004 0.029 0.006 0.062 0.006 0.095 0 0.388-0.29 0.709-0.665 0.757l-0.004 0c-0.010 0.002-0.024 0.004-0.039 0.005l-0.003 0 0.032 0.249c0.009-0.002 0.023-0.005 0.037-0.007l0.004-0.001c0.029-0.004 0.062-0.006 0.095-0.006 0.388 0 0.709 0.29 0.757 0.665l0 0.004c0.004 0.029 0.006 0.062 0.006 0.095 0 0.388-0.29 0.709-0.665 0.757l-0.004 0c-0.010 0.002-0.024 0.003-0.039 0.005l-0.003 0 0.032 0.249c0.009-0.002 0.023-0.005 0.037-0.007l0.004-0.001c0.029-0.004 0.062-0.006 0.095-0.006 0.388 0 0.709 0.29 0.757 0.665l0 0.004c0.004 0.029 0.006 0.062 0.006 0.095 0 0.388-0.29 0.709-0.665 0.757l-0.004 0c-0.010 0.002-0.024 0.003-0.039 0.005l-0.003 0 0.032 0.249c0.009-0.002 0.023-0.005 0.037-0.007l0.004-0.001c0.029-0.004 0.062-0.006 0.095-0.006 0.388 0 0.709 0.29 0.757 0.665l0 0.004c0.004 0.029 0.006 0.062 0.006 0.095 0 0.388-0.29 0.709-0.665 0.757l-0.004 0c-0.010 0.002-0.024 0.003-0.039 0.005l-0.003 0 0.032 0.249c0.009-0.002 0.023-0.005 0.037-0.007l0.004-0.001c0.029-0.004 0.062-0.006 0.095-0.006 0.388 0 0.709 0.29 0.757 0.665l0 0.004c0.004 0.029 0.006 0.062 0.006 0.095 0 0.388-0.29 0.709-0.665 0.757l-0.004 0c-0.010 0.002-0.024 0.003-0.039 0.005l-0.003 0 0.032 0.249c0.009-0.002 0.023-0.005 0.037-0.007l0.004-0.001c0.029-0.004 0.062-0.006 0.095-0.006 0.388 0 0.709 0.29 0.757 0.665l0 0.004c0.004 0.029 0.006 0.062 0.006 0.095 0 0.388-0.29 0.709-0.665 0.757l-0.004 0c-0.010 0.002-0.024 0.004-0.039 0.005l-0.003 0 0.032 0.249c0.009-0.002 0.023-0.005 0.037-0.007l0.005-0.001c0.029-0.004 0.062-0.006 0.095-0.006 0.388 0 0.709 0.29 0.757 0.665l0 0.004c0.004 0.029 0.006 0.062 0.006 0.095 0 0.388-0.29 0.709-0.665 0.757l-0.004 0c-0.010 0.002-0.024 0.003-0.039 0.005l-0.003 0 0.032 0.249c0.009-0.002 0.023-0.005 0.037-0.007l0.004-0.001c0.029-0.004 0.062-0.006 0.095-0.006 0.388 0 0.709 0.29 0.757 0.665l0 0.004c0.004 0.029 0.006 0.062 0.006 0.095 0 0.388-0.29 0.709-0.665 0.757l-0.004 0c-0.011 0.001-0.025 0.003-0.040 0.004l-0.002 0 0.032 0.249c0.009-0.002 0.023-0.005 0.038-0.007l0.004-0c0.029-0.004 0.062-0.006 0.095-0.006 0.388 0 0.709 0.29 0.757 0.665l0 0.004c0.004 0.029 0.006 0.062 0.006 0.095 0 0.388-0.29 0.709-0.665 0.757l-0.004 0c-0.011 0.001-0.025 0.003-0.040 0.004l-0.002 0 0.032 0.249c0.009-0.002 0.023-0.005 0.038-0.007l0.004-0.001c0.029-0.004 0.062-0.006 0.095-0.006 0.388 0 0.709 0.29 0.757 0.665l0 0.004c0.004 0.029 0.006 0.062 0.006 0.095 0 0.388-0.29 0.709-0.665 0.757l-0.004 0c-0.011 0.001-0.025 0.003-0.040 0.004l-0.002 0 0.032 0.249c0.009-0.002 0.023-0.005 0.038-0.007l0.004-0c0.029-0.004 0.062-0.006 0.095-0.006 0.388 0 0.709 0.29 0.757 0.665l0 0.004c0.004 0.029 0.006 0.062 0.006 0.095 0 0.388-0.29 0.709-0.665 0.757l-0.004 0c-0.011 0.001-0.025 0.003-0.040 0.004l-0.002 0 0.032 0.249c0.009-0.002 0.023-0.005 0.038-0.007l0.004-0c0.029-0.004 0.062-0.006 0.095-0.006 0.388 0 0.709 0.29 0.757 0.665l0 0.004c0.004 0.029 0.006 0.062 0.006 0.095 0 0.388-0.29 0.709-0.665 0.757l-0.004 0c-0.011 0.001-0.025 0.003-0.040 0.004l-0.002 0 0.032 0.249c0.009-0.002 0.023-0.005 0.038-0.007l0.004-0c0.029-0.004 0.062-0.006 0.095-0.006 0.388 0 0.709 0.29 0.757 0.665l0 0.004c0.004 0.029 0.006 0.062 0.006 0.095 0 0.388-0.29 0.709-0.665 0.757l-0.004 0c-0.011 0.001-0.025 0.003-0.040 0.004l-0.002 0 0.032 0.249c0.009-0.002 0.023-0.005 0.038-0.007l0.004-0c0.029-0.004 0.062-0.006 0.095-0.006 0.388 0 0.709 0.29 0.757 0.665l0 0.004c0.004 0.029 0.006 0.062 0.006 0.095 0 0.388-0.29 0.709-0.665 0.757l-0.004 0c-0.011 0.001-0.025 0.003-0.040 0.004l-0.002 0 0.009 0.071c0.006 0.061 0.021 0.119 0.039 0.175 0.004-0.001 0.012-0.002 0.021-0.004l0.004-0.001c0.029-0.004 0.062-0.006 0.095-0.006 0.388 0 0.709 0.29 0.757 0.665l0 0.004c0.001 0.005 0.002 0.014 0.002 0.022l0 0.003c0.059 0.003 0.119 0.002 0.179-0.006l0.071-0.009c-0.002-0.009-0.005-0.023-0.008-0.037l-0.001-0.005c-0.004-0.028-0.006-0.061-0.006-0.095 0-0.388 0.29-0.709 0.665-0.757l0.004-0c0.029-0.004 0.062-0.006 0.095-0.006 0.388 0 0.709 0.29 0.757 0.665l0 0.004c0.002 0.010 0.003 0.024 0.005 0.039l0 0.003 0.249-0.031c-0.002-0.009-0.005-0.023-0.008-0.037l-0.001-0.005c-0.004-0.029-0.006-0.061-0.006-0.095 0-0.388 0.29-0.709 0.665-0.757l0.004-0c0.029-0.004 0.062-0.006 0.095-0.006 0.388 0 0.709 0.29 0.757 0.665l0 0.004c0.002 0.010 0.003 0.024 0.005 0.039l0 0.003 0.249-0.031c-0.002-0.009-0.005-0.023-0.008-0.037l-0.001-0.005c-0.004-0.029-0.006-0.061-0.006-0.095 0-0.388 0.29-0.709 0.665-0.757l0.004-0c0.029-0.004 0.062-0.006 0.095-0.006 0.388 0 0.709 0.29 0.757 0.665l0 0.004c0.002 0.010 0.003 0.024 0.005 0.039l0 0.003 0.249-0.031c-0.002-0.009-0.005-0.023-0.008-0.037l-0.001-0.005c-0.004-0.028-0.006-0.061-0.006-0.095 0-0.388 0.29-0.709 0.665-0.757l0.004-0c0.029-0.004 0.062-0.006 0.095-0.006 0.388 0 0.709 0.29 0.757 0.665l0 0.004c0.002 0.010 0.003 0.024 0.005 0.039l0 0.003 0.249-0.031c-0.002-0.009-0.005-0.023-0.008-0.037l-0.001-0.005c-0.004-0.029-0.006-0.061-0.006-0.095 0-0.388 0.29-0.709 0.665-0.757l0.004-0c0.029-0.004 0.062-0.006 0.095-0.006 0.388 0 0.709 0.29 0.757 0.665l0 0.004c0.002 0.010 0.003 0.024 0.005 0.039l0 0.003 0.249-0.031c-0.002-0.009-0.005-0.023-0.008-0.037l-0.001-0.005c-0.004-0.028-0.006-0.061-0.006-0.095 0-0.388 0.29-0.709 0.665-0.757l0.004-0c0.029-0.004 0.062-0.006 0.095-0.006 0.388 0 0.709 0.29 0.757 0.665l0 0.004c0.002 0.010 0.003 0.024 0.005 0.039l0 0.003 0.249-0.031c-0.002-0.009-0.005-0.023-0.008-0.037l-0.001-0.005c-0.004-0.029-0.006-0.061-0.006-0.095 0-0.388 0.29-0.709 0.665-0.757l0.004-0c0.029-0.004 0.062-0.006 0.095-0.006 0.388 0 0.709 0.29 0.757 0.665l0 0.004c0.002 0.010 0.003 0.024 0.005 0.039l0 0.003 0.249-0.031c-0.002-0.009-0.005-0.023-0.008-0.037l-0.001-0.005c-0.004-0.028-0.006-0.061-0.006-0.095 0-0.388 0.29-0.709 0.665-0.757l0.004-0c0.028-0.004 0.061-0.006 0.095-0.006 0.388 0 0.709 0.29 0.757 0.665l0 0.004c0.002 0.010 0.003 0.024 0.005 0.039l0 0.003 0.249-0.031c-0.002-0.009-0.005-0.023-0.008-0.037l-0.001-0.005c-0.004-0.028-0.006-0.061-0.006-0.095 0-0.388 0.29-0.709 0.665-0.757l0.004-0c0.029-0.004 0.061-0.006 0.095-0.006 0.388 0 0.709 0.29 0.757 0.665l0 0.004c0.002 0.010 0.003 0.024 0.005 0.039l0 0.003 0.249-0.031c-0.002-0.009-0.005-0.023-0.008-0.037l-0.001-0.005c-0.004-0.028-0.006-0.061-0.006-0.095 0-0.388 0.29-0.709 0.665-0.757l0.004-0c0.029-0.004 0.061-0.006 0.095-0.006 0.388 0 0.709 0.29 0.757 0.665l0 0.004c0.002 0.010 0.003 0.024 0.005 0.039l0 0.003 0.249-0.031c-0.002-0.009-0.005-0.023-0.008-0.037l-0.001-0.005c-0.004-0.029-0.006-0.061-0.006-0.095 0-0.388 0.29-0.709 0.665-0.757l0.004-0c0.029-0.004 0.061-0.006 0.095-0.006 0.388 0 0.709 0.29 0.757 0.665l0 0.004c0.002 0.010 0.003 0.024 0.005 0.039l0 0.003 0.249-0.031c-0.002-0.009-0.005-0.023-0.008-0.037l-0.001-0.005c-0.004-0.028-0.006-0.061-0.006-0.095 0-0.388 0.29-0.709 0.665-0.757l0.004-0c0.029-0.004 0.061-0.006 0.095-0.006 0.388 0 0.709 0.29 0.757 0.665l0 0.004c0.002 0.010 0.003 0.024 0.005 0.039l0 0.003 0.249-0.031c-0.002-0.009-0.005-0.023-0.008-0.037l-0.001-0.005c-0.004-0.029-0.006-0.061-0.006-0.095 0-0.388 0.29-0.709 0.665-0.757l0.004-0c0.029-0.004 0.061-0.006 0.095-0.006 0.388 0 0.709 0.29 0.757 0.665l0 0.004c0.002 0.010 0.003 0.024 0.005 0.039l0 0.003 0.249-0.031c-0.002-0.009-0.005-0.023-0.008-0.037l-0.001-0.005c-0.004-0.028-0.006-0.061-0.006-0.095 0-0.388 0.29-0.709 0.665-0.757l0.004-0c0.028-0.004 0.061-0.006 0.095-0.006 0.388 0 0.709 0.29 0.757 0.665l0 0.004c0.002 0.010 0.003 0.024 0.005 0.039l0 0.003 0.071-0.009c0.061-0.008 0.119-0.021 0.175-0.039-0.001-0.003-0.003-0.012-0.004-0.020l-0.001-0.005c-0.004-0.029-0.006-0.061-0.006-0.095 0-0.388 0.29-0.709 0.665-0.757l0.004-0c0.006-0.001 0.014-0.001 0.023-0.002l0.002-0c0.004-0.059 0.002-0.119-0.006-0.179l-0.010-0.071c-0.009 0.002-0.023 0.005-0.038 0.007l-0.004 0.001c-0.028 0.004-0.061 0.006-0.095 0.006-0.388 0-0.709-0.29-0.757-0.665l-0-0.004c-0.004-0.028-0.006-0.061-0.006-0.095 0-0.388 0.29-0.709 0.665-0.757l0.004-0c0.011-0.001 0.025-0.003 0.040-0.004l0.002-0-0.032-0.249c-0.009 0.002-0.023 0.005-0.038 0.007l-0.004 0c-0.028 0.004-0.061 0.006-0.095 0.006-0.388 0-0.709-0.29-0.757-0.665l-0-0.004c-0.004-0.028-0.006-0.061-0.006-0.095 0-0.388 0.29-0.709 0.665-0.757l0.004-0c0.011-0.001 0.025-0.003 0.040-0.004l0.002-0-0.032-0.249c-0.009 0.002-0.023 0.005-0.038 0.007l-0.004 0.001c-0.029 0.004-0.061 0.006-0.095 0.006-0.388 0-0.709-0.29-0.757-0.665l-0-0.004c-0.004-0.029-0.006-0.061-0.006-0.095 0-0.388 0.29-0.709 0.665-0.757l0.004-0c0.011-0.001 0.025-0.003 0.040-0.004l0.002-0-0.032-0.249c-0.009 0.002-0.023 0.005-0.038 0.007l-0.004 0.001c-0.028 0.004-0.061 0.006-0.095 0.006-0.388 0-0.709-0.29-0.757-0.665l-0-0.004c-0.004-0.029-0.006-0.061-0.006-0.095 0-0.388 0.29-0.709 0.665-0.757l0.004-0c0.011-0.001 0.025-0.003 0.040-0.004l0.002-0-0.032-0.249c-0.009 0.002-0.023 0.005-0.038 0.007l-0.004 0.001c-0.028 0.004-0.061 0.006-0.095 0.006-0.388 0-0.709-0.29-0.757-0.665l-0-0.004c-0.004-0.028-0.006-0.061-0.006-0.095 0-0.388 0.29-0.709 0.665-0.757l0.004-0c0.011-0.001 0.025-0.003 0.040-0.004l0.002-0-0.032-0.249c-0.009 0.002-0.023 0.005-0.038 0.007l-0.004 0.001c-0.028 0.004-0.061 0.006-0.095 0.006-0.388 0-0.709-0.29-0.757-0.665l-0-0.004c-0.004-0.029-0.006-0.061-0.006-0.095 0-0.388 0.29-0.709 0.665-0.757l0.004-0c0.011-0.001 0.026-0.002 0.041-0.003l0.002-0-0.032-0.249c-0.009 0.002-0.023 0.005-0.038 0.007l-0.004 0.001c-0.028 0.004-0.061 0.006-0.095 0.006-0.388 0-0.709-0.29-0.757-0.665l-0-0.004c-0.004-0.028-0.006-0.061-0.006-0.095 0-0.388 0.29-0.709 0.665-0.757l0.004-0c0.010-0.002 0.024-0.004 0.039-0.005l0.003-0-0.032-0.249c-0.009 0.002-0.023 0.005-0.037 0.008l-0.005 0.001c-0.028 0.004-0.061 0.006-0.095 0.006-0.388 0-0.709-0.29-0.757-0.665l-0-0.004c-0.004-0.028-0.006-0.061-0.006-0.095 0-0.388 0.29-0.709 0.665-0.757l0.004-0c0.010-0.002 0.024-0.004 0.039-0.005l0.003-0-0.032-0.249c-0.009 0.002-0.023 0.005-0.037 0.008l-0.005 0.001c-0.028 0.004-0.061 0.006-0.095 0.006-0.388 0-0.709-0.29-0.757-0.665l-0-0.004c-0.004-0.028-0.006-0.061-0.006-0.095 0-0.388 0.29-0.709 0.665-0.757l0.004-0c0.010-0.002 0.024-0.004 0.039-0.005l0.003-0-0.032-0.249c-0.009 0.002-0.023 0.005-0.037 0.008l-0.005 0.001c-0.028 0.004-0.061 0.006-0.095 0.006-0.388 0-0.709-0.29-0.757-0.665l-0-0.004c-0.004-0.028-0.006-0.061-0.006-0.095 0-0.388 0.29-0.709 0.665-0.757l0.004-0c0.010-0.002 0.024-0.004 0.039-0.005l0.003-0-0.032-0.249c-0.009 0.002-0.023 0.005-0.037 0.008l-0.005 0.001c-0.028 0.004-0.061 0.006-0.095 0.006-0.388 0-0.709-0.29-0.757-0.665l-0-0.004c-0.004-0.028-0.006-0.061-0.006-0.095 0-0.388 0.29-0.709 0.665-0.757l0.004-0c0.010-0.002 0.024-0.004 0.039-0.005l0.003-0-0.032-0.249c-0.009 0.002-0.023 0.005-0.037 0.008l-0.005 0.001c-0.028 0.004-0.061 0.006-0.095 0.006-0.388 0-0.709-0.29-0.757-0.665l-0-0.004c-0.004-0.028-0.006-0.061-0.006-0.095 0-0.388 0.29-0.709 0.665-0.757l0.004-0c0.010-0.002 0.024-0.004 0.039-0.005l0.003-0-0.032-0.249c-0.009 0.002-0.023 0.005-0.037 0.008l-0.005 0.001c-0.028 0.004-0.061 0.006-0.095 0.006-0.388 0-0.709-0.29-0.757-0.665l-0-0.004c-0.004-0.029-0.006-0.061-0.006-0.095 0-0.388 0.29-0.709 0.665-0.757l0.004-0c0.010-0.002 0.024-0.004 0.039-0.005l0.003-0-0.032-0.249c-0.009 0.002-0.023 0.005-0.037 0.008l-0.005 0.001c-0.028 0.004-0.061 0.006-0.095 0.006-0.388 0-0.709-0.29-0.757-0.665l-0-0.004c-0.004-0.029-0.006-0.061-0.006-0.095 0-0.388 0.29-0.709 0.665-0.757l0.004-0c0.010-0.002 0.024-0.004 0.039-0.005l0.003-0-0.008-0.071c-0.006-0.061-0.021-0.119-0.039-0.176-0.003 0.001-0.012 0.003-0.020 0.004l-0.005 0.001c-0.028 0.004-0.061 0.006-0.095 0.006-0.388 0-0.709-0.29-0.757-0.665l-0-0.004c-0.001-0.005-0.002-0.014-0.003-0.022l-0-0.003c-0.029-0.002-0.059-0.002-0.089-0.002zM18.374 5.658c1.489 0.002 3.787 0.359 3.918 2.889 0.076 1.472-0.665 2.649-2.008 2.719-0.413 0.021-0.834-0.112-1.19-0.482 0.676-0.449 1.091-1.429 1.047-2.281-0.047-0.904-0.606-1.703-1.974-1.632-1.42 0.074-2.358 0.951-2.285 2.371 0.21 4.053 6.031 3.985 6.33 9.742 0.189 3.64-2.374 6.129-6.531 6.345-2.065 0.107-5.729-0.143-5.921-3.861-0.126-2.427 1.614-3.812 3.603-3.915 1.162-0.060 1.905 0.29 2.16 1.208-2.082 0.289-2.966 1.215-2.873 2.997 0.090 1.73 1.471 2.409 2.685 2.346 2.040-0.106 2.748-1.929 2.648-3.839-0.252-4.854-5.883-5.623-6.083-9.47-0.141-2.711 2.823-4.962 5.896-5.121 0.171-0.009 0.367-0.016 0.58-0.016z"/></svg>'
            ));
    }

    /**
 * Adds a submenu for Stannp Settings
 *
 * @return void
 */
    public function stannp_campaign_page()
    {
        add_submenu_page(
            'stannp',
            __('Create a new Campaign', 'woocommerce-stannp-directmailing-integration'),
            __('Create a new Campaign', 'woocommerce-stannp-directmailing-integration'),
            'manage_options',
            'stannp_campaign',
            array($this, 'stannp_campaign_page_html')
        );
    }

    /**
     * Callback function to display the html for the campaign page
     */
    public function stannp_campaign_page_html()
    {
        $url_extras = (!empty($_GET['page'])) ? "?page=" . $_GET['page'] : '';

        echo
            '<div class="wrap">
                <h1>' . __("Create a new Campaign", "woocommerce-stannp-directmailing-integration") . '</h1>
            
                <form id="stannp_campaign_form" method="post" action="' . htmlentities($_SERVER['PHP_SELF'] . $url_extras) . '">
            
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">' . __("Campaign Name", "woocommerce-stannp-directmailing-integration") . '
                                <span class="um_required" style="color:red">*</span>
                            </th>
                            <td><input type="text" type="text" name="stannp_campaign_name" id="stannp_campaign_name" size="32" value="' .   sanitize_text_field($_POST["stannp_campaign_name"]) . '"/></td>
                        </tr>
            
                        <tr valign="top">
                            <th scope="row">' . __("Campaign Type", "woocommerce-stannp-directmailing-integration") . '
                                <span class="um_required" style="color:red">*</span>
                            </th>
                            <td>
                                <select id="stannp_campaign_type" name="stannp_campaign_type" title="Campaign Type">
                                    <option value="a5-postcard" ' . (($_POST["stannp_campaign_type"] == "a5-postcard")? "selected='selected'" : null) .  '>'
            . __("A5 Postcard", "woocommerce-stannp-directmailing-integration") . '
                                    </option>
                                    <option value="a6-postcard" ' . (($_POST["stannp_campaign_type"] == "a6-postcard")? "selected='selected'" : null) .  '>'
            . __("A6 Postcard", "woocommerce-stannp-directmailing-integration") . '
                                    </option>
                                    <option value="letter" ' . (($_POST["stannp_campaign_type"] == "letter")? "selected='selected'" : null) .  '>'
            . __("Letter", "woocommerce-stannp-directmailing-integration") . '
                                    </option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr valign="top">
                            <th scope="row">' . __("Template Name", "woocommerce-stannp-directmailing-integration") . '</th>
                            <td>'
            . $this->get_template_select_html(null) .
            '</td>
                        </tr>
            
                        <tr valign="top">
                            <th scope="row">'. __('Recipients', "woocommerce-stannp-directmailing-integration") . '<span class="um_required" style="color:red">*</span></th>
                            <td>
                                <select id="stannp_campaign_recipients" name="stannp_campaign_recipients" title="Which Recipients" value="' . esc_attr($_POST["stannp_campaign_recipients"]) . '">
                                    <option value="all" ' . (($_POST["stannp_campaign_recipients"] == "all")? "selected='selected'" : null) .  '>All</option>
                                    <option value="int" ' . (($_POST["stannp_campaign_recipients"] == "int")? "selected='selected'" : null) .  '>International only</option>
                                    <option value="valid" ' . (($_POST["stannp_campaign_recipients"] == "valid")? "selected='selected'" : null) .  '>UK validated addresses only</option>
                                    <option value="not_valid" ' . (($_POST["stannp_campaign_recipients"] == "not_valid")? "selected='selected'" : null) .  '>UK non validated addresses only</option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr valign="top">
                            <th scope="row">'. __('Number of Recipients', "woocommerce-stannp-directmailing-integration") . '</th>
                            <td><input type="text" name="stannp_campaign_recipients_number" id="stannp_campaign_recipients_number" readonly="readonly" size="32" value="' . ((isset($_POST["stannp_campaign_recipients_number"])) ? sanitize_text_field($_POST["stannp_campaign_recipients_number"]) : $this->get_recipients_number()['all']) .'"/></td>
                        </tr>
            
                        <tr valign="top">
                            <th scope="row">'. __('Voucher Code', "woocommerce-stannp-directmailing-integration") . '</th>
                            <td><input type="text" name="stannp_campaign_voucher" id="stannp_campaign_voucher" size="32" value="' . sanitize_text_field($_POST["stannp_campaign_voucher"]) . '"/></td>
                        </tr>
                    </table>';
        submit_button('Create Campaign', 'primary' , 'stannp_campaign_submit');
        echo
        '</form>
            </div>';


    }

    /**
     * Callback for Stannp Settings submenu; redirects to the WooCommerce Settings Integration page
     */
    public function stannp_settings_page_html()
    {

        if (!current_user_can('manage_options')) {
            return;
        }

        wp_safe_redirect(
            add_query_arg(
                array(
                    'page' => 'wc-settings',
                    'tab' => 'integration',
                    'section' => 'stannp_directmailing'
                ),
                admin_url('admin.php')
            )
        );
    }


    //////////////////////////////////////// USERS SECTION ////////////////////////////////////////////////////////////

    /***
     * Adds Export to Stannp action to bulk action
     *
     * @param $bulk_actions
     *
     * @return mixed
     */
    function register_my_bulk_actions($bulk_actions)
    {
        $bulk_actions['export_to_stannp'] = __('Export to Stannp', 'woocommerce-stannp-directmailing-integration');
        return $bulk_actions;
    }

    /**
     * Handler for the Export to Stannp bulk action
     *
     * @param $redirect_to
     * @param $doaction
     * @param $user_ids
     *
     * @return string
     *
     * @throws Exception
     * @throws Zend_Uri_Exception
     */
    function stannp_bulk_action_handler($redirect_to, $doaction, $user_ids)
    {
        if ($doaction !== 'export_to_stannp') {
            return $redirect_to;
        }

        $exported_users = 0;

        foreach ($user_ids as $user_id) {

            $customer = new WC_Customer($user_id);
            $billing_address = $customer->get_billing();
            $shipping_address = $customer->get_billing();
            $billing_string = $billing_address['first_name'] . $billing_address['last_name'] . $billing_address['address_1'] . $billing_address['city'];
            $shipping_string = $shipping_address['first_name'] . $shipping_address['last_name'] . $shipping_address['address_1'] . $shipping_address['city'];


            if ((!($billing_string)) && (!($shipping_string))) {
                //if important data is not set on the customer address
                continue;
            }

            $params = array(
                'firstname' => (!empty($billing_address['first_name'])) ? $billing_address['first_name'] : $shipping_address['first_name'],
                'lastname'  => (!empty($billing_address['last_name'])) ? $billing_address['last_name'] : $shipping_address['last_name'],
                'address1'  => (!empty($billing_address['address_1'])) ? $billing_address['address_1'] : $shipping_address['address_1'],
                'address2'  => (!empty($billing_address['address_2'])) ? $billing_address['address_2'] : $shipping_address['address_2'],
                'city'      => (!empty($billing_address['city'])) ? $billing_address['city'] : $shipping_address['city'],
                'country'   => (!empty($billing_address['country'])) ? $billing_address['country'] : $shipping_address['country'],
                'company'   => (!empty($billing_address['company'])) ? $billing_address['company'] : $shipping_address['company'],
                'postcode'  => (!empty($billing_address['postcode'])) ? $billing_address['postcode'] : $shipping_address['postcode'],
                'group_id'  => (int)$this->get_option('stannp_groups'),
            );


            $recipient_class = new WC_Stannp_DirectMailing_Api_Recipient(
                new WP_Http_Curl(),
                $this->stannp_api_key
            );

            $result = $recipient_class->createElement($params);

            if (!empty($result['id'])) {
                $exported_users++;
            }
        }

        $redirect_to = add_query_arg(
            array(
                'action' => 'export_to_stannp',
                'bulk_exported_users' => $exported_users,
                'bulk_total_users' => count($user_ids),
            ), $redirect_to);

        return $redirect_to;
    }

    /**
     * Admin messages for the Export to Stannp bulk action
     */
    function stannp_bulk_action_admin_notice()
    {
        if (((isset($_REQUEST['action'])) && ($_REQUEST['action'] === 'export_to_stannp')) ||
        ((isset($_REQUEST['action2'])) && ($_REQUEST['action2'] === 'export_to_stannp'))){
            if (!empty($_REQUEST['bulk_total_users'])) {
                if ($_REQUEST['bulk_exported_users']) {
                    $exported_count = intval($_REQUEST['bulk_exported_users']);
                    $users_total = intval($_REQUEST['bulk_total_users']);
                    $extra_text = ($exported_count != $users_total) ? ' Please make sure that your users have a billing or shipping address set' : '';

                    printf('<div id="message" class="updated notice fade"><p>' .
                        //updated notice is-dismissible
                        _n('Exported %s user out of %s to Stannp.' . $extra_text,
                            'Exported %s users out of %s to Stannp.' . $extra_text,
                            $exported_count,
                            'woocommerce-stannp-directmailing-integration'
                        ) . '</p></div>', $exported_count, $users_total);
                } else {
                    echo '<div id="message" class="updated error fade"><p>' . __('Users could not be exported. Please make sure you have a valid API Key  and that your users have a billing or shipping address set.', "woocommerce-stannp-directmailing-integration") . '</p></div>';
                }
            } else {
                echo '<div id="message" class="updated error fade"><p>' . __('Please select the users you wish to export to Stannp.', "woocommerce-stannp-directmailing-integration") . '</p></div>';
            }
        }
    }

    //////////////////////////////////////// USERS SECTION ENDS ////////////////////////////////////////////////////////

    /**
     *
     */
    function stannp_campaign_form_submit()
    {
        if (((isset($_REQUEST['page'])) &&($_REQUEST['page'] === 'stannp_campaign')) && (!empty($_REQUEST['stannp_campaign_submit']))) {
            if ((!empty($_POST['stannp_campaign_name'])) && (!empty($_POST['stannp_campaign_type'])) &&
                (!empty($_POST['stannp_campaign_recipients'])) && (!empty($_POST['stannp_campaign_recipients_number']))) {
                $campaign_class = new WC_Stannp_DirectMailing_Api_Campaign(
                    new WP_Http_Curl(),
                    $this->stannp_api_key
                );

                $response = $campaign_class->createElement(
                    array(
                        'name'             => sanitize_text_field($_POST['stannp_campaign_name']),
                        'type'             => esc_attr($_POST['stannp_campaign_type']),
                        'template_id'      => esc_attr($_POST['stannp_campaign_template']),
                        'group_id'         => (int)esc_attr($this->get_option('stannp_groups')),
                        'which_recipients' => esc_attr($_POST['stannp_campaign_recipients']),
                        'code'             => sanitize_text_field($_POST['stannp_campaign_voucher']),
                    )
                );

                if ($response) {
                    echo '<div id="message" class="updated notice fade"><p>' . __("Campaign created successfully. Please follow this url to see it: ",  "woocommerce-stannp-directmailing-integration")
                        . '<a href="' . esc_url("https://dash.stannp.com/wizard/" . $response) . '" target="_blank">' . sanitize_text_field($_POST['stannp_campaign_name']) . "</a>"
                        . '</p></div>';
                } else {
                    echo '<div id="message" class="updated error fade"><p>' . __('An error occured while creating the campaign. Please try again later.',  "woocommerce-stannp-directmailing-integration") . '  </p></div>';
                }
            } else {
                if (empty($_POST['stannp_campaign_recipients_number'])) {
                    echo '<div id="message" class="updated error fade"><p>' . __('Number of Recipients must be greater than 0. Please check that you have selected a recipient group in the WooCommerce Settings or select a different group if the current one does not have recipients.', "woocommerce-stannp-directmailing-integration") . '  </p></div>';
                } else {
                    echo '<div id="message" class="updated error fade"><p>' . __('Fields marked with a red star are required.', "woocommerce-stannp-directmailing-integration") . '  </p></div>';
                }

            }
        }
    }


    /**
     * Load and binds the admin assets - JS scripts
     */
    function load_admin_assets()
    {

        wp_register_script('wc-stannp-directmailing-admin-scripts', plugins_url('/assets/js/admin-stannp-scripts.js', dirname(__FILE__)), array('jquery'), null, false);
        wp_localize_script( 'wc-stannp-directmailing-admin-scripts', 'template_names_by_size', $this->get_templates_by_size() );
        wp_localize_script( 'wc-stannp-directmailing-admin-scripts', 'recipients_number', $this->get_recipients_number() );

        wp_enqueue_script('wc-stannp-directmailing-admin-scripts');

        $screen = get_current_screen();

        if ("stannp_page_stannp_campaign" !== $screen->id) {
            //make sure we add this to the right page
            return;
        }

        // Register the script like this for a plugin:
        wp_register_script('wc-stannp-directmailing-admin-campaign-scripts', plugins_url('/assets/js/admin-campaign-scripts.js', dirname(__FILE__)), array('jquery'), null, false);
        wp_localize_script( 'wc-stannp-directmailing-admin-campaign-scripts', 'template_names_by_size', $this->get_templates_by_size() );
        wp_localize_script( 'wc-stannp-directmailing-admin-campaign-scripts', 'recipients_number', $this->get_recipients_number() );

        wp_enqueue_script('wc-stannp-directmailing-admin-campaign-scripts');

    }

    /**
     * Includes all the files required by the API
     *
     * @return void
     */
    protected function include_api_files()
    {
        foreach (self::STANNP_API_CLASS_NAMES as $class_name) {
            include_once "api/class-wc-stannp-directmailing-api-{$class_name}.php";
        }
    }

    /**
     * Function to retrieve the account balance using the Stannp API key
     *
     * @param $stannp_api_key
     *
     * @return string|void
     *
     * @throws Exception
     * @throws Zend_Uri_Exception
     */
    private function get_account_balance($stannp_api_key)
    {
        $result = __(self::DEFAULT_ACCOUNT_TEXT,  "woocommerce-stannp-directmailing-integration");

        if (!empty($stannp_api_key)) {

            $group_class = new WC_Stannp_DirectMailing_Api_Account(
                new WP_Http_Curl(),
                $stannp_api_key
            );

            $account_result = $group_class->getBalance();

            if (is_array($account_result) && (!empty($account_result['balance']))) {
                $result = __('Your current balance is: £',  "woocommerce-stannp-directmailing-integration") . $account_result['balance'];
            }
        }

        return $result;
    }

    /**
     * Function to retrieve the group names using the Stannp API key
     *
     * @return array
     *
     * @throws Exception
     * @throws Zend_Uri_Exception
     */
    private function get_group_names()
    {
        $result = array('' => __('Please select...',  "woocommerce-stannp-directmailing-integration"));

        if (!empty($this->stannp_api_key)) {

            $group_class = new WC_Stannp_DirectMailing_Api_Group(
                new WP_Http_Curl(),
                $this->stannp_api_key
            );

            $group_result = $group_class->getList();

            if (is_array($group_result)) {

                foreach ($group_result as $group) {
                    if (!empty($group['id'])) {
                        $result[$group['id']] = $group['name'];
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Updates the textarea content accordingly
     *
     * @throws Exception
     * @throws Zend_Uri_Exception
     */
    public function update_textarea()
    {
        $this->settings['stannp_account_information'] = $this->get_account_balance($this->stannp_api_key);
        update_option($this->get_option_key(), apply_filters('woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings));
    }

    /**
     * Returns the api key
     *
     * @return null|string
     */
    private function get_stannp_api_key()
    {
        $stannp_api_key = null;
        $post = $this->get_post_data();

        if (isset($post['woocommerce_stannp_directmailing_stannp_api_key'])) {
            $stannp_api_key = $post['woocommerce_stannp_directmailing_stannp_api_key'];
        } else if (!empty($this->get_option('stannp_api_key'))) {
            $stannp_api_key = $this->get_option('stannp_api_key');
        }

        return $stannp_api_key;
    }

    /**
     * Returns the html for the Template Select
     *
     * @return string
     * @throws Exception
     * @throws Zend_Uri_Exception
     */
    private function get_template_select_html()
    {
        $result = '<select id="stannp_campaign_template" name="stannp_campaign_template" title="Campaign Type">' .
            '<option value="0" ' . (($_POST["stannp_campaign_template"] == "0")? "selected='selected'" : null) .
            '>' . __("Blank Template",  "woocommerce-stannp-directmailing-integration") . '</option>';

        if (!empty($this->stannp_api_key)) {

            $template_class = new WC_Stannp_DirectMailing_Api_Template(
                new WP_Http_Curl(),
                $this->stannp_api_key
            );

            $template_result =  (!empty($_POST['stannp_campaign_type'])) ? $template_class->getListByType($_POST['stannp_campaign_type']) : $template_class->getListByType();

            if (is_array($template_result)) {

                foreach ($template_result as $template) {

                    if (!empty($template['id'])) {
                        $result .= '<option value="' . $template['id'] . '"  ' . (($_POST["stannp_campaign_template"] == $template['id'])? "selected='selected'" : null) .
                            '>' . $template['template_name'] . '</option>';
                    }
                }
            }
        }

        $result .= '</select>';

        return $result;
    }

    /**
     * Returns an array of arrays containing the size as a key and the template names and ids as values
     *
     * @return array
     *
     * @throws Exception
     * @throws Zend_Uri_Exception
     */
    private function get_templates_by_size()
    {
        $result_array = array();

        if (!empty($this->stannp_api_key)) {

            $template_class = new WC_Stannp_DirectMailing_Api_Template(
                new WP_Http_Curl(),
                $this->stannp_api_key
            );

            $template_result = $template_class->getList();

            if (is_array($template_result)) {

                foreach ($template_result as $template) {

                    if ((!empty($template['id'])) && (!empty($template['size']))) {
                        $result_array[$template['size']] = array($template['id'] => $template['template_name']);
                    }
                }
            }
        }

        return $result_array;
    }


    /**
     * Returns an array of recipients number, having the recipient type as key and the number as value
     *
     * @return array
     *
     * @throws Exception
     * @throws Zend_Uri_Exception
     */
    private function get_recipients_number()
    {
        $result_array = array();

        if (!empty($this->stannp_api_key)) {

            $group_class = new WC_Stannp_DirectMailing_Api_Group(
                new WP_Http_Curl(),
                $this->stannp_api_key
            );

            $group_result = $group_class->getElement((int)$this->get_option('stannp_groups')); //TODO add validation for when a group is not selected

            if (is_array($group_result)) {
                if ((isset($group_result['recipients'])) && (isset($group_result['valid'])) && (isset($group_result['international']))) {
                    $result_array['int'] = $group_result['international'];
                    $result_array['valid'] = $group_result['valid'];
                    $result_array['not_valid'] = $group_result['recipients'] - $group_result['valid'] - $group_result['international'];
                    $result_array['all'] = $group_result['recipients'];
                }
            }
        }

        return $result_array;
    }

    /**
     * Shows an error message in the admin when a connection to Stannp couldn't be established.
     */
    function curl_gone_wrong(){
        echo '<div class="error"><p>' . __( 'Something went wrong while connecting to Stannp. Please check your API key and try again!', 'woocommerce-stannp-directmailing-integration' ) .'</p></div>';
    }
}
