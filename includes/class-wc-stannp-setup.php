<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Stannp DirectMailing Integration
 *
 * Adds Stannp Menu to the Admin Panel and the relevant pages
 * Implements the bulk export to Stannp of Wordpress users
 *
 * @class   WC_Stannp_DirectMailing
 * @extends WC_Integration
 */
class WC_Stannp_Setup
{

    private $step = "";

    /**
     * Adds a submenu for Stannp Settings
     *
     * @return void
     */

    public function __construct()
    {

        add_action('init', array($this, 'start_stannp_setup_session'), 1); //TODO destroy on exit!!
        add_action('phpmailer_init', array($this, 'mailer_config'), 10, 1);
    }

    public function stannp_setup_page()
    {
        add_submenu_page(
            'stannp',
            __('Stannp Setup', 'woocommerce-stannp-directmailing-integration'),
            __('Stannp Setup', 'woocommerce-stannp-directmailing-integration'),
            'manage_options',
            'stannp-setup',
            array($this, 'stannp_setup_page_html')
        );

        //      add_dashboard_page( '', '', 'manage_options', 'stannp-setup', '' );
    }

    public function get_error_html()
    {
        if (!empty($_SESSION['stannp_setup_errors'][$this->step])) {
            echo '<div class="stannp-error-message">' .
                '<p>' . $_SESSION['stannp_setup_errors'][$this->step] . '</p>' .
                '</div>';
            unset($_SESSION['stannp_setup_errors'][$this->step]);
        }
    }


    function end_session()
    {
        session_destroy();
    }

    public function setup_wizard()
    {
        if (empty($_GET['page']) || 'stannp-setup' !== $_GET['page']) { // WPCS: CSRF ok, input var ok.
            return;
        }

        $default_steps = array(
            'stannp_account' => array(
                'name' => __('Stannp Account', 'woocommerce'),
                'view' => array($this, 'stannp_directmailing_setup_account'),
                'handler' => array($this, 'stannp_directmailing_setup_account_save'),
            ),
            'stannp_login' => array(
                'name' => __('Log In', 'woocommerce'),
                'view' => array($this, 'stannp_directmailing_setup_login'),
                'handler' => array($this, 'stannp_directmailing_setup_login_save'),
            ),
            'sync' => array(
                'name' => __('Sync Confirmation', 'woocommerce'),
                'view' => array($this, 'stannp_directmailing_setup_sync'),
                'handler' => array($this, 'stannp_directmailing_setup_sync_save'),
            ),
            'campaign' => array(
                'name' => __('Sample Pack', 'woocommerce'),
                'view' => array($this, 'stannp_directmailing_setup_campaign'),
                'handler' => array($this, 'stannp_directmailing_setup_campaign_save'),
            ),
            'next_steps' => array(
                'name' => __('Good to go!', 'woocommerce'),
                'view' => array($this, 'stannp_directmailing_setup_ready'),
                'handler' => '',
            ),
        );

        $this->steps = apply_filters('woocommerce_setup_wizard_steps', $default_steps);
        $this->step = isset($_GET['step']) ? sanitize_key($_GET['step']) : current(array_keys($this->steps)); // WPCS: CSRF ok, input var ok.
        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

        wp_enqueue_style('wc-setup', WC()->plugin_url() . '/assets/css/wc-setup.css', array('dashicons', 'install'), WC_VERSION);
        wp_enqueue_style('wc-stannp-directmailing-setup', plugins_url('/assets/css/setup.css', dirname(__FILE__)), array('wc-setup'), null);
        wp_register_script('wc-stannp-directmailing-setup', plugins_url('/assets/js/wc-stannp-directmailing-setup.js', dirname(__FILE__)), array('jquery'), null, false);

        // @codingStandardsIgnoreStart
        if (!empty($_POST['save_step']) && isset($this->steps[$this->step]['handler'])) {
            call_user_func($this->steps[$this->step]['handler'], $this);
        }
        // @codingStandardsIgnoreEnd

        ob_start();
        $this->setup_wizard_header();
        $this->setup_wizard_steps();
        $this->setup_wizard_content();
        $this->setup_wizard_footer();
        exit;
    }

    function start_stannp_setup_session()
    {
        if (!session_id()) {
            session_start();
        }
    }

    public function get_next_step_link($step = '')
    {
        if (!$step) {
            $step = $this->step;
        }

        $keys = array_keys($this->steps);
        if (end($keys) === $step) {
            return admin_url();
        }

        $step_index = array_search($step, $keys, true);
        if (false === $step_index) {
            return '';
        }

        return add_query_arg('step', $keys[$step_index + 1]);
    }

    /**
     * Setup Wizard Header.
     */
    public function setup_wizard_header()
    {
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta name="viewport" content="width=device-width"/>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
            <title><?php esc_html_e('Stannp &rsaquo; Setup Wizard', 'stannp'); ?></title>
            <?php wp_print_scripts('wc-stannp-directmailing-setup'); ?>
            <?php do_action('admin_print_styles'); ?>
            <?php do_action('admin_head'); ?>
        </head>
        <body class="stannp-setup wp-core-ui">
        <h1 id="wc-logo"><a href="https://stannp.com?source=woocommerce"><img
                        src="<?php echo plugin_dir_url(dirname(__FILE__)) . 'assets/images/stannp-logo.png' ?>"
                        alt="Stannp"/></a></h1>
        <?php
    }

    /**
     * Setup Wizard Footer.
     */
    public function setup_wizard_footer()
    {

        ?>
        <?php if ('stannp_account' === $this->step) : ?>
        <a class="wc-stannp-return-to-dashboard"
           href="<?php echo esc_url(admin_url()); ?>"><?php esc_html_e('Not right now', 'woocommerce'); ?></a>
    <?php elseif ('stannp_login' === $this->step) : ?>
        <a class="wc-stannp-return-to-dashboard"
           href="<?php echo esc_url(admin_url()); ?>"><?php esc_html_e('Return to your dashboard', 'woocommerce'); ?></a>
    <?php elseif ('campaign' === $this->step) : ?>
        <a class="wc-stannp-return-to-dashboard"
           href="<?php echo esc_url($this->get_next_step_link()); ?>"><?php esc_html_e('Skip this step', 'woocommerce'); ?></a>
    <?php endif; ?>
        </body>
        </html>
        <?php
    }

    /**
     * Output the steps.
     */
    public function setup_wizard_steps()
    {
        $output_steps = $this->steps;
        ?>
        <ol class="wc-stannp-setup-steps">
            <?php foreach ($output_steps as $step_key => $step) : ?>
                <li class="
					<?php
                if ($step_key === $this->step) {
                    echo 'active';
                } elseif (array_search($this->step, array_keys($this->steps), true) > array_search($step_key, array_keys($this->steps), true)) {
                    echo 'done';
                }
                ?>
				"><?php echo esc_html($step['name']); ?></li>
            <?php endforeach; ?>
        </ol>
        <?php
    }


    /**
     * Output the content for the current step.
     */
    public function setup_wizard_content()
    {
        echo '<div class="wc-setup-content">';
        if (!empty($this->steps[$this->step]['view'])) {
            call_user_func($this->steps[$this->step]['view'], $this);
        }
        echo '</div>';
    }

    public function stannp_directmailing_setup_account()
    {
        ?>
        <h1><?php esc_html_e('Stannp Account', 'woocommerce-stannp-directmailing-integration'); ?></h1>

        <?php $this->get_error_html() ?>

        <p>
            <?php

            /* translators: %s: Link */
            _e('Do you have a Stannp.com account?', 'woocommerce-stannp-directmailing-integration')

            ?>
        </p>

        <?php $current_user = wp_get_current_user(); ?>

        <form method="post" class="form" id="stannp-account-no-form">
            <select id="stannp_account_dropdown" name="stannp_account_dropdown" class="stannp-setup-dropdown" required>
                <option value=""><?php _e("--- Please select ---", 'woocommerce-stannp-directmailing-integration'); ?></option>
                <option value="1"
                ><?php _e("Yes", 'woocommerce-stannp-directmailing-integration'); ?> </option>
                <option value="0"><?php _e("No", 'woocommerce-stannp-directmailing-integration'); ?></option>
            </select>

            <div class="stannp-account-no">
                <p><?php _e("Please register by filling in the following fields: ", 'woocommerce-stannp-directmailing-integration'); ?></p>
                <div class="stannp-setup-label"><?php _e("E-mail address: ", 'woocommerce-stannp-directmailing-integration') ?></div><input type="email"
                                                                                                      name="email"
                                                                                                      id="stannp_account_email"
                                                                                                      required
                                                                                                      value="<?php echo (!empty($_SESSION['stannp_register_data']['email'])) ? $_SESSION['stannp_register_data']['email'] : $current_user->user_email  ?>"><br>
                <div class="stannp-setup-label"><?php _e("First name: ", 'woocommerce-stannp-directmailing-integration'); ?></div><input type="text"
                                                                                                   name="first_name"
                                                                                                   id="stannp_account_first_name"
                                                                                                   required
                                                                                                   value="<?php echo (!empty($_SESSION['stannp_register_data']['first_name'])) ? $_SESSION['stannp_register_data']['first_name'] : $current_user->first_name ?>"><br>
                <div class="stannp-setup-label"><?php _e("Last name: ", 'woocommerce-stannp-directmailing-integration'); ?></div><input type="text"
                                                                                                  name="last_name"
                                                                                                  id="stannp_account_last_name"
                                                                                                  required
                                                                                                  value="<?php (!empty($_SESSION['stannp_register_data']['last_name'])) ? $_SESSION['stannp_register_data']['last_name'] : $current_user->last_name ?>"><br>
                <div class="stannp-setup-label"><?php _e("Password: ", 'woocommerce-stannp-directmailing-integration'); ?></div><input type="password"
                                                                                                 name="password"
                                                                                                 id="stannp_account_password"
                                                                                                 required><br>
                <div class="stannp-setup-label"><?php _e("Repeat Password: ", 'woocommerce-stannp-directmailing-integration'); ?></div><input type="password"
                                                                                                        name="password2"
                                                                                                        id="stannp_account_password2"
                                                                                                        required><br>
                <div class="stannp-setup-label"><?php _e("Company: ", 'woocommerce-stannp-directmailing-integration'); ?></div><input type="text"
                                                                                                name="company"
                                                                                                id="stannp_account_company"
                                                                                                required
                                                                                                value="<?php echo (!empty($_SESSION['stannp_register_data']['first_name'])) ? $_SESSION['stannp_register_data']['first_name'] : '' ?>"><br>

            </div>
            <div class="terms" id="stannp-register-terms" style="display:none">
                <input type="checkbox" name="terms" value="Yes" required
                       checked> <?php _e("By registering and using the Stannp.com platform you must agree to our ", 'woocommerce-stannp-directmailing-integration') ?>
                <a href="https://www.stannp.com/direct-mail/terms-of-service/"><?php _e("terms of service", 'woocommerce-stannp-directmailing-integration') ?></a>
                <?php _e("and", 'woocommerce-stannp-directmailing-integration') ?> <a href="https://www.stannp.com/direct-mail/privacy-policy/"> <?php _e("privacy policy", 'woocommerce-stannp-directmailing-integration') ?></a>.
                <?php _e("Please also be aware that we will automatically start syncing your customer data to our service, meaning you agree to Stannp.com being a data processor as stated in our ", 'woocommerce-stannp-directmailing-integration') ?></a>
                <a href="https://www.stannp.com/direct-mail/terms-of-service/"><?php _e("terms of service", 'woocommerce-stannp-directmailing-integration') ?></a>.
                <br>
            </div>

            <div class="wc-setup-actions step">
                <button type="submit" class="button-primary button button-large button-next"
                        id="stannp_account_dropdown_submit"
                        value="<?php esc_attr_e('Continue', 'woocommerce'); ?>"
                        name="save_step"><?php esc_html_e('Continue', 'woocommerce'); ?></button>
                <?php wp_nonce_field('stannp-setup'); ?>
            </div>
        </form>

        <?php
    }

    public function stannp_directmailing_setup_login()
    {
        if (!empty($_SESSION['stannp_register_data'])) {
            if ((isset($_SESSION['stannp_register_data']['stannp_account_dropdown'])) &&
                (($_SESSION['stannp_register_data']['stannp_account_dropdown']) === '0')) {
                $_POST['email'] = $_SESSION['stannp_register_data']['email'];
                $_POST['password'] = $_SESSION['stannp_register_data']['password'];
                $this->stannp_directmailing_setup_login_save();
            }
        };

        ?>
        <h1><?php esc_html_e('Stannp Log In', 'woocommerce-stannp-directmailing-integration'); ?></h1>

        <?php $this->get_error_html() ?>

        <p>
            <?php
            /* translators: %s: Link */
            _e('Please enter your login details:', 'woocommerce-stannp-directmailing-integration')
            ?>
        </p>

        <?php $current_user = wp_get_current_user(); ?>

        <div class="stannp-login-step">
            <form method="post" class=" form">
                <div class="stannp-login-form">
                    <div class="stannp-setup-label"><?php _e("E-mail: ", 'woocommerce-stannp-directmailing-integration') ?></div><input type="text"
                                                                                                  name="email"
                                                                                                  id="stannp_login_email"
                                                                                                  required
                                                                                                  value="<?php echo (!empty($_POST['email'])) ? $_POST['email'] : $current_user->user_email ; ?>"><br>
                    <div class="stannp-setup-label"><?php _e("Password: ", 'woocommerce-stannp-directmailing-integration') ?></div><input type="password"
                                                                                                    name="password"
                                                                                                    id="stannp_login_password"
                                                                                                    required
                                                                                                    value="<?php echo (!empty($_POST['password'])) ? $_POST['password'] : '' ?>"><br>
                </div>

                <div class="terms">
                    <input type="checkbox" name="terms" value="Yes" required
                           checked> <?php _e("By registering and using the Stannp.com platform you must agree to our ", 'woocommerce-stannp-directmailing-integration') ?>
                    <a href="https://www.stannp.com/direct-mail/terms-of-service/"><?php _e("terms of service", 'woocommerce-stannp-directmailing-integration') ?></a>
                    <?php _e("and", 'woocommerce-stannp-directmailing-integration') ?> <a href="https://www.stannp.com/direct-mail/privacy-policy/"> <?php _e("privacy policy", 'woocommerce-stannp-directmailing-integration') ?></a>.
                    <?php _e("Please also be aware that we will automatically start syncing your customer data to our service, meaning you agree to Stannp.com being a data processor as stated in our ", 'woocommerce-stannp-directmailing-integration') ?></a>
                    <a href="https://www.stannp.com/direct-mail/terms-of-service/"><?php _e("terms of service", 'woocommerce-stannp-directmailing-integration') ?></a>.
                    <br>
                </div>
                <div class="wc-setup-actions step">
                    <button type="submit" class="button-primary button button-large button-next"
                            value="<?php esc_attr_e('Continue', 'woocommerce'); ?>"
                            name="save_step"><?php esc_html_e('Continue', 'woocommerce'); ?></button>
                    <?php wp_nonce_field('stannp-setup'); ?>
                </div>
            </form>
        </div>
        <?php
    }

    /**
     * Payment Step save.
     */
    public function stannp_directmailing_setup_account_save()
    {
        check_admin_referer('stannp-setup');

        if ($_POST['stannp_account_dropdown'] === "0") {
            if (!empty($_POST['email']) &&
                !empty($_POST['first_name']) &&
                !empty($_POST['last_name']) &&
                !empty($_POST['password']) &&
                !empty($_POST['password2']) &&
                !empty($_POST['company'])
            ) {

                $_POST['source'] = 'woocommerce';
                //save everything in the session $_SESSION
                $_SESSION['stannp_register_data'] = $_POST;

                if ($_POST['password'] === $_POST['password2']) {
                    $url = 'https://dash.stannp.com/register-integrations';

                    // use key 'http' even if you send the request to https://...
                    $options = array(
                        'http' => array(
                            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                            'method' => 'POST',
                            'content' => http_build_query($_POST)
                        )
                    );

                    $context = stream_context_create($options);
                    $result = file_get_contents($url, false, $context);

                    if ($result === FALSE) { /* Handle error */
                        $_SESSION['stannp_setup_errors'][$this->step] = 'Could not log you in. Please check username and password and try again.';
                        wp_redirect(add_query_arg('step', $this->step));
                        exit;
                    }

                    $json_to_array = json_decode($result, true);

                    if (isset($json_to_array['success'])) {
                        if ($json_to_array['success'] == false) {
                            $_SESSION['stannp_setup_errors'][$this->step] = $json_to_array['error'];
                            wp_redirect(add_query_arg('step', $this->step));
                            exit;
                        }
                    }

                } else {
                    $_SESSION['stannp_setup_errors'][$this->step] = 'Passwords do not match!';

                    wp_redirect(add_query_arg('step', $this->step));
                    exit;
                }
            } else {
                $_SESSION['stannp_setup_errors'][$this->step] = 'All fields need to be completed!';
                wp_redirect(add_query_arg('step', $this->step));
                exit;
            }
        }

        wp_redirect(esc_url_raw($this->get_next_step_link()));
        exit;
    }

    public function stannp_directmailing_setup_login_save()
    {
        //  check_admin_referer('stannp-setup');

        if ((!empty($_POST['email'])) && (!empty($_POST['password']))) {
            $url = 'https://dash.stannp.com/login-integrations';

            // use key 'http' even if you send the request to https://...
            $options = array(
                'http' => array(
                    'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                    'method' => 'POST',
                    'content' => http_build_query($_POST)
                )
            );

            $context = stream_context_create($options);
            $result = file_get_contents($url, false, $context);
            //get data[token] from result and make a request using it instead of the api_key

            if ($result == FALSE) { /* Handle error */
                $_SESSION['stannp_setup_errors'][$this->step] = 'Could not log you in. Please check username and password and try again.';
                wp_redirect(add_query_arg('step', $this->step));
                exit();
            }

            $json_to_array = json_decode($result, true);

            $token = '';

            if (isset($json_to_array['success'])) {
                if ($json_to_array['success'] == false) {
                    $_SESSION['stannp_setup_errors'][$this->step] = $json_to_array['error'];
                    wp_redirect(add_query_arg('step', $this->step));
                    exit();
                } else {
                    if ((!empty($json_to_array['data'])) && (!empty($json_to_array['data']['token']))) {
                        $token = $json_to_array['data']['token'];
                    }
                }
            }

            $repeat_customers = 0;
            $zero_orders_customers = 0;
            $order_more_than_half_year_ago = 0;
            $one_time_customers = 0;
            $high_spenders = 0;

            if (!empty($token)) {

                //TODO uncomment when this is on dash.stannp.com
//                $account_class = new WC_Stannp_DirectMailing_Api_Account(
//                    new WP_Http_Curl(),
//                    null
//                );
//
//                $api_key = $account_class->getKey($token);

                $token_result = file_get_contents("https://biscuits.stannp.com/api/v1/accounts/getKey?token=" . $token);
                $json_to_array = json_decode($token_result, true);

                $api_key = null;

                if (isset($json_to_array['success'])) {
                    if ($json_to_array['success'] == false) {
                        $_SESSION['stannp_setup_errors'][$this->step] = 'Could not log you in. Please check username and password and try again.';
                        wp_redirect(add_query_arg('step', $this->step));
                        exit();
                    } else {
                        if ((!empty($json_to_array['data'])) && (!empty($json_to_array['data']['api_key']))) {
                            $api_key = $json_to_array['data']['api_key'];
                        }
                    }
                }

                if (!empty($api_key)) {

                    //update api_key on the settings page
                    $settings['stannp_api_key'] = $api_key;

                    update_option('woocommerce_stannp_directmailing_settings', apply_filters('woocommerce_settings_api_sanitized_fields_stannp_directmailing', $settings));

                    //TODO maybe add a success message
                    //TODO definitely add an error message

                    // Woocommerce saves customers' data as usermeta.

                    $group_class = new WC_Stannp_DirectMailing_Api_Group(
                        new WP_Http_Curl(),
                        $api_key
                    );

                    $rez = $group_class->createElement(array('name' => 'WooCommerce', 'icon' => 'https://dash.stannp.com/bulkmailer/assets/integrations/woo-icon.png')); //this just returns a string consisting of the group id number or false on error
                    $group_id = $rez;

                    if (empty($rez)) {
                        $group_id = $group_class->createElement(array('name' => 'WooCommerce_' . time()));
                    }
                    $settings['stannp_groups'] = $group_id;

                    //account class might already be declared, so please check above
                    $account_class = new WC_Stannp_DirectMailing_Api_Account(
                        new WP_Http_Curl(),
                        $api_key
                    );

                    $account_balance_result = $account_class->getBalance();
                    $balance_text = __('There is no information to show.',  "woocommerce-stannp-directmailing-integration");

                    if (is_array($account_balance_result) && (!empty($account_balance_result['balance']))) {
                        $balance_text = __('Your current balance is: £',  "woocommerce-stannp-directmailing-integration") . $account_balance_result['balance'];
                    }
                    $settings['stannp_account_information'] = $balance_text;

                    update_option('woocommerce_stannp_directmailing_settings', apply_filters('woocommerce_settings_api_sanitized_fields_stannp_directmailing', $settings));

                    $customers = get_users('orderby=register&role=customer'); //get all the customers ordered by the register date
                    $customer_spend_report = array();
                    $total_spent_all_customers = 0;
                    $total_customers = 0;


                    foreach ($customers as $customer) {

                        //check that billing or shipping postcode is set
                        if ((empty(get_user_meta($customer->ID, 'billing_postcode', true))) && (empty(get_user_meta($customer->ID, 'shipping_postcode', true)))) {
                            continue;
                        }
                        $total_customers++;
                        $total_spent = wc_get_customer_total_spent($customer->ID);
                        $orders_count = (wc_get_customer_order_count($customer->ID));
                        $last_order_date = (wc_get_customer_last_order($customer->ID)) ? (wc_get_customer_last_order($customer->ID)->get_date_completed()->date('F j, Y,')) : '';
                        $customer_spend_report[$customer->ID] = $total_spent;
                        $total_spent_all_customers += $total_spent;

                        //exclude it when last order date is null
                        $order_more_than_half_year_ago += ($last_order_date) ? (strtotime($last_order_date) < strtotime("-6 months")) : 0;
                        $repeat_customers += ($orders_count > 2);
                        $one_time_customers += ($orders_count === 1);
                        $zero_orders_customers += ($orders_count === 0);

                        $params = array(
                            'firstname' => esc_html($customer->first_name),
                            'lastname' => esc_html($customer->last_name),
                            'address1' => (!empty(get_user_meta($customer->ID, 'billing_address_1', true))) ?
                                get_user_meta($customer->ID, 'billing_address_1', true) : get_user_meta($customer->ID, 'shipping_address_1', true),
                            'address2' => (!empty(get_user_meta($customer->ID, 'billing_address_2', true))) ?
                                get_user_meta($customer->ID, 'billing_address_2', true) : get_user_meta($customer->ID, 'shipping_address_2', true),
                            'city' => (!empty(get_user_meta($customer->ID, 'billing_city', true))) ?
                                get_user_meta($customer->ID, 'billing_city', true) : get_user_meta($customer->ID, 'shipping_city', true),
                            'country' => (!empty(get_user_meta($customer->ID, 'billing_country', true))) ?
                                get_user_meta($customer->ID, 'billing_country', true) : get_user_meta($customer->ID, 'shipping_country', true),
                            'company' => (!empty(get_user_meta($customer->ID, 'billing_company', true))) ?
                                get_user_meta($customer->ID, 'billing_company', true) : get_user_meta($customer->ID, 'shipping_company', true),
                            'postcode' => (!empty(get_user_meta($customer->ID, 'billing_postcode', true))) ?
                                get_user_meta($customer->ID, 'billing_postcode', true) : get_user_meta($customer->ID, 'shipping_postcode', true),
                            'group_id' => $group_id,
                            'total_spent' => $total_spent,
                            'orders_count' => $orders_count,
                            'last_order_date' => $last_order_date,
                        );
                        $recipient_class = new WC_Stannp_DirectMailing_Api_Recipient(
                            new WP_Http_Curl(),
                            $api_key
                        );

                        $result = $recipient_class->createElement($params);
                    }

                    $customers_who_have_placed_orders =  $one_time_customers + $repeat_customers;

                    if ($customers_who_have_placed_orders > 0) {
                        $average_order_amount = $total_spent_all_customers / $customers_who_have_placed_orders;

                        foreach ($customer_spend_report as $key => $value) {
                            $high_spenders += ($value >= $average_order_amount);
                        }
                    }
                }
            }

            $group_data = array(
                    'zero_orders_customers'         => $zero_orders_customers,
                    'one_time_customers'            => $one_time_customers,
                    'high_spenders'                 => $high_spenders,
                    'repeat_customers'              => $repeat_customers,
                    'order_more_than_half_year_ago' => $order_more_than_half_year_ago,
                    'recipients'                    => $total_customers
            );
            $_SESSION['group_data'] = $group_data; //save in session for next step
        } else {
            $_SESSION['stannp_setup_errors'][$this->step] = 'Please fill in the username and password';
            wp_redirect(add_query_arg('step', $this->step));
            exit();
        }

        wp_redirect(esc_url_raw($this->get_next_step_link()));
        exit;
    }

    /**
     * Payment Step save.
     */
    public function stannp_directmailing_setup_sync_save()
    {
        check_admin_referer('stannp-setup');

        wp_redirect(esc_url_raw($this->get_next_step_link()));
        exit;
    }

    public function stannp_directmailing_setup_sync()
    {
        ?>
        <h1><?php esc_html_e('Stannp Sync', 'woocommerce-stannp-directmailing-integration'); ?></h1>

        <p>
            <?php
            $data = $_SESSION['group_data'];

            /* translators: %s: Link */
            _e('Great! We found ' . $data['recipients'] . ' recipients.', 'woocommerce-stannp-directmailing-integration');
            ?>
        </p>
        <table class="stannp-group-stats">
            <tr>
                <th colspan="2"><?php _e('Customer Stats', 'woocommerce-stannp-directmailing-integration'); ?></th>
            </tr>
            <tr>
                <td><?php _e('Customers with no orders: ', 'woocommerce-stannp-directmailing-integration'); ?></td>
                <td><?php echo esc_html($data['zero_orders_customers']); ?> </td>
            </tr>
            <tr>
                <td><?php _e('Customers who ordered only once: ', 'woocommerce-stannp-directmailing-integration'); ?></td>
                <td><?php echo esc_html($data['one_time_customers']); ?> </td>
            </tr>
            <tr>
                <td><?php _e('High spending customers: ', 'woocommerce-stannp-directmailing-integration'); ?></td>
                <td><?php echo esc_html(($data['high_spenders'])); ?> </td>
            </tr>
            <tr>
                <td><?php _e('Repeat customers: ', 'woocommerce-stannp-directmailing-integration'); ?></td>
                <td><?php echo esc_html($data['repeat_customers']); ?> </td>
            </tr>
            <tr>
                <td><?php _e('Active more than 6 months ago:  ', 'woocommerce-stannp-directmailing-integration'); ?></td>
                <td><?php echo esc_html($data['order_more_than_half_year_ago']); ?> </td>
            </tr>
        </table>

        <form method="post" class=" form" id="stannp-sync-form">
            <div class="wc-setup-actions step">
                <button type="submit" class="button-primary button button-large button-next"
                        value="<?php esc_attr_e('Continue', 'woocommerce'); ?>"
                        name="save_step"><?php esc_html_e('Create your first campaign', 'woocommerce'); ?></button>
                <?php wp_nonce_field('stannp-setup'); ?>
            </div>
        </form>
        <?php
    }

    public function stannp_directmailing_setup_campaign()
    {
        ?>
        <h1><?php esc_html_e('Stannp Campaign', 'woocommerce-stannp-directmailing-integration'); ?></h1>

        <p>
            <?php
            /* translators: %s: Link */
            _e("Let's create your first Campaign", 'woocommerce-stannp-directmailing-integration')
            ?>
        </p>
        <form method="post" class=" form">
            <div class="stannp-setup-campaign-form">
                <div class="stannp-setup-label">
                    <?php _e("First Name: ", 'woocommerce-stannp-directmailing-integration') ?>
                </div>
                <input type="text" name="firstname" id="stannp_setup_address_firstname" required><br>
                <div class="stannp-setup-label">
                    <?php _e("First Name: ", 'woocommerce-stannp-directmailing-integration') ?>
                </div>
                <input type="text" name="lastname" id="stannp_setup_address_lastname" required><br>
                <div class="stannp-setup-label">
                    <?php _e("Company: ", 'woocommerce-stannp-directmailing-integration') ?>
                </div>
                <input type="text" name="company" id="stannp_setup_address_company" required><br>
                <div class="stannp-setup-label">
                    <?php _e("Address Line 1: ", 'woocommerce-stannp-directmailing-integration') ?>
                </div>
                <input type="text" name="address1" id="stannp_setup_address_line1" required><br>
                <div class="stannp-setup-label">
                    <?php _e("Address Line 2: ", 'woocommerce-stannp-directmailing-integration') ?>
                </div>
                <input type="text" name="address2" id="stannp_setup_address_line2"><br>
                <div class="stannp-setup-label">
                    <?php _e("City: ", 'woocommerce-stannp-directmailing-integration') ?>
                </div>
                <input type="text" name="city" id="stannp_setup_address_city" required><br>
                <div class="stannp-setup-label">
                    <?php _e("Postcode / ZIP: ", 'woocommerce-stannp-directmailing-integration') ?>
                </div>
                <input type="text" name="postcode" id="stannp_setup_address_postcode" required><br>
                <?php

                $countries_obj = new WC_Countries();
                $countries = $countries_obj->__get('countries');
                ?>
                <div class="stannp-setup-label">
                    <?php _e("Country: ", 'woocommerce-stannp-directmailing-integration') ?>
                </div>
                <select id="stannp_country_dropdown" name="country" class="stannp-setup-dropdown"
                        required>
                    <?php //added "Please select" at the beginning of that array ?>
                    <?php $countries = array(''=> __('Please select...')) + $countries; ?>
                    <?php foreach ($countries as $key => $country) : ?>
                        <option value="<?php echo $key ?>"><?php echo __($country) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="stannp-setup-label">
                    <?php _e("State / County: ", 'woocommerce-stannp-directmailing-integration') ?>
                </div>
                <input type="text" name="region" id="stannp_setup_address_region"><br>

                <div class="wc-setup-actions step">
                    <button type="submit" class="button-primary button button-large button-next"
                            id="stannp_setup_campaign_submit"
                            value="<?php esc_attr_e('Continue', 'woocommerce'); ?>"
                            name="save_step"><?php esc_html_e('Send me a sample pack', 'woocommerce'); ?></button>
                    <?php wp_nonce_field('stannp-setup'); ?>
                </div>
            </div>
        </form>
        <?php
    }

    public function stannp_directmailing_setup_ready()
    {
        $this->end_session();
        ?>
        <h1><?php esc_html_e('Good To Go!', 'woocommerce-stannp-directmailing-integration'); ?></h1>

        <p>
            <?php
            /* translators: %s: Link */
            _e("Good work! You are now set up and you can start easily sending Direct Mail campaigns to your customers.", 'woocommerce-stannp-directmailing-integration')
            ?>
        </p>
        <p>
            <?php
            /* translators: %s: Link */
            _e("What would you like to do next?", 'woocommerce-stannp-directmailing-integration')
            ?>
        </p>

<!--        <ul>-->
<!--            <li>-->
<!--                <a href="https://dash.stannp.com/wizard">--><?php //esc_html_e('Create your first Direct Mail campaign', 'woocommerce'); ?><!--</a>-->
<!--            </li>-->
<!--            <li>-->
<!--                --><?php
//                /* translators: %s: Link */
//                ?>
<!--                <a href="--><?php //echo esc_url(admin_url()); ?><!--" id="finish_stannp_setup">--><?php //esc_html_e('Return to your Woo dashboard.', 'woocommerce'); ?><!--</a>-->
<!--            </li>-->
<!--        </ul>-->

        <div class="wc-setup-actions step">
            <a href="https://dash.stannp.com/wizard" target="_blank" class="button-primary"><?php esc_html_e('Create your first Direct Mail campaign', 'woocommerce'); ?></a>
            <a href="<?php echo esc_url(admin_url()); ?>" class="button-primary"><?php esc_html_e('Return to your Woo dashboard.', 'woocommerce'); ?></a>
        </div>

        <p>
            <?php
            /* translators: %s: Link */
            _e("Thank you for your time!", 'woocommerce-stannp-directmailing-integration')
            ?>
        </p>

        <?php
    }

    function mailer_config(PHPMailer $mailer)
    {
        $mailer->IsSMTP();
        $mailer->Host = "smtp.sendgrid.net"; // your SMTP server
        $mailer->Port = 25;
        $mailer->SMTPDebug = 0; // write 0 if you don't want to see client/server communication in page
        $mailer->SMTPAuth = true;
        $mailer->CharSet = "utf-8";
        $mailer->Username = "azure_51db21795160867ce844e1e3a98d4e9c@azure.com";
        $mailer->Password = "5Ij7wixsm3H4WMz";        // SMTP account password
        $mailer->setFrom('hello@stannp.com', 'Stannp.com');
    }

    public function stannp_directmailing_setup_campaign_save()
    {
        check_admin_referer('stannp-setup');

        //check if all the mandatory fields have been filled in
        if (!empty($_POST['firstname']) &&
            !empty($_POST['lastname']) &&
            !empty($_POST['address1']) &&
            !empty($_POST['postcode']) &&
            !empty($_POST['company']) &&
            !empty($_POST['city']) &&
            !empty($_POST['country'])
        ) {
            //send an e-mail with the details to orders@stannp.com
            $mail_sent = wp_mail('orders@stannp.com',
                'Please send a sample pack for WooCommerce',
                json_encode($_POST),
                "Cc: sam@stannp.com"
            );
            //error handling for e-mail
            if (!$mail_sent) {
                $_SESSION['stannp_setup_errors'][$this->step] = 'There has been an error while processing your request. Please try again.';
                wp_redirect(add_query_arg('step', $this->step));
                exit();
            }
        } else {
            $_SESSION['stannp_setup_errors'][$this->step] = 'All fields need to be completed.';
            wp_redirect(add_query_arg('step', $this->step));
            exit();
        }

        wp_redirect(esc_url_raw($this->get_next_step_link()));
        exit;
    }
}