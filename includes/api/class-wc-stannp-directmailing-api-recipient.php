<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

require_once('class-wc-stannp-directmailing-api-abstract.php');

/**
 * Stannp DirectMailing Integration
 *
 * Class that implements Recipient related API calls
 *
 * @class   WC_Stannp_DirectMailing_Api_Recipient
 * @extends WC_Stannp_DirectMailing_Api_Abstract
 */
class WC_Stannp_DirectMailing_Api_Recipient extends WC_Stannp_DirectMailing_Api_Abstract
{
    protected $section = 'recipients';

    /**
     * API call to add a new recipient 'api/v1/recipients/new'
     *
     * @param array $params :
     *                      firstname    string Recipients first name
     *                      lastname     string Recipients last name
     *                      address1     string Address line 1
     *                      address2     string Address line 2
     *                      address3     string Address line 3
     *                      city         string Address city
     *                      postcode     string Address postal code
     *                      country      string County code(GB,US,FR...)
     *                      company      string The recipient's company name
     *                      group_id     int    The group ID of the recipient
     *                      on_duplicate string If duplicate(update/ignore/duplicate)
     *                      other        ?      Other variables
     *
     * @return bool|mixed
     *
     * @throws \Exception
     * @throws \Zend_Uri_Exception
     */
    public function createElement(array $params)
    {
        $this->action = 'new';
        $this->method = 'post';
        $this->params = $params;

        return $this->call();
    }
}