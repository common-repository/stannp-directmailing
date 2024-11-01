<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

require_once('class-wc-stannp-directmailing-api-abstract.php');
/**
 * Stannp DirectMailing Integration
 *
 * Class that implements Account related API calls
 *
 * @class   WC_Stannp_DirectMailing_Api_Account
 * @extends WC_Stannp_DirectMailing_Api_Abstract
 */
class WC_Stannp_DirectMailing_Api_Account extends WC_Stannp_DirectMailing_Api_Abstract
{
    protected $section = 'accounts';

    /**
     * API call to get account balance 'api/v1/accounts/balance'
     *
     * @return bool
     * @throws Exception
     * @throws Zend_Uri_Exception
     */
    public function getBalance()
    {
        $this->action = 'balance';
        $this->method = 'get';
        $this->params = null;

        return $this->call();
    }

    /**
     * API call to get the API key based on the token 'api/v1/accounts/balance'
     *
     * @return bool
     * @throws Exception
     * @throws Zend_Uri_Exception
     */
    public function getKey($token)
    {
        $this->action = 'getKey';
        $this->method = 'get';
        $this->params = array('token' => $token);

        return $this->call();
    }

    /**
     * Create Account function
     *
     * @param array $params
     * @return mixed|void
     */
    public function createElement(array $params)
    {
        // TODO: Implement createElement() method.
    }
}