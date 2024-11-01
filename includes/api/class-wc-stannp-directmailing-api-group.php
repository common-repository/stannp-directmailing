<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

require_once('class-wc-stannp-directmailing-api-abstract.php');
/**
 * Stannp DirectMailing Integration
 *
 * Class that implements Group related API calls
 *
 * @class   WC_Stannp_DirectMailing_Api_Group
 * @extends WC_Stannp_DirectMailing_Api_Abstract
 */
class WC_Stannp_DirectMailing_Api_Group extends WC_Stannp_DirectMailing_Api_Abstract
{
    protected $section = 'groups';

    /**
     * Create Group function
     *
     * @param array $params
     *
     * @return bool|mixed
     *
     * @throws Exception
     * @throws Zend_Uri_Exception
     */
    public function createElement(array $params)
    {
        $this->action = 'new';
        $this->method = 'post';
        $this->params = $params;

        return $this->call();
    }
}