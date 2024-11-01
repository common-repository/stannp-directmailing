<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

require_once('class-wc-stannp-directmailing-api-abstract.php');
/**
 * Stannp DirectMailing Integration
 *
 * Class that implements Template related API calls
 *
 * @class   WC_Stannp_DirectMailing_Api_Template
 * @extends WC_Stannp_DirectMailing_Api_Abstract
 */
class WC_Stannp_DirectMailing_Api_Template extends WC_Stannp_DirectMailing_Api_Abstract
{
    protected $section = 'templates';

    /**
     * Create Template function
     *
     * @param array $params
     *
     * @return mixed|void
     */
    public function createElement(array $params)
    {
        // TODO: Implement createElement() method.
    }

    /**
     * List Templates by type
     *
     * @param string $type
     *
     * @return bool
     * @throws \Exception
     * @throws \Zend_Uri_Exception
     */
    public function getListByType($type = 'a5-postcard')
    {
        $action = 'list' . (($type)? "/" . $type : '');
        $this->action = $action ;
        $this->params = null;
        $this->method = 'get';

        return $this->call();
    }
}