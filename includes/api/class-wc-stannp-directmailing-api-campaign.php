<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

require_once('class-wc-stannp-directmailing-api-abstract.php');
/**
 * Stannp DirectMailing Integration
 *
 * Class that implements Campaign related API calls
 *
 * @class   WC_Stannp_DirectMailing_Api_Campaign
 * @extends WC_Stannp_DirectMailing_Api_Abstract
 */
class WC_Stannp_DirectMailing_Api_Campaign extends WC_Stannp_DirectMailing_Api_Abstract
{
    protected $section = 'campaigns';

    /**
     * Create Campaign function
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
        $this->params = [
            'name'            => isset($params['name']) ? $params['name'] : null, // to become ?? from PHP7
            'type'            => isset($params['type']) ? $params['type'] : null,
            'template_id'     => isset($params['template_id']) ? $params['template_id'] : 0,
            'group_id'        => isset($params['group_id']) ? $params['group_id'] : null,
            'what_recipients' => isset($params['what_recipients']) ? $params['what_recipients'] : 'all',
            'code'            => isset($params['code']) ? $params['code'] : null
        ];

        return $this->call();
    }
}