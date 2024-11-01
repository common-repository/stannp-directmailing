<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

use WP_Http_Curl as Curl;
/**
 * Stannp DirectMailing Integration
 *
 * The abstract class for all API requests to Stannp
 *
 * @class   WC_Stannp_DirectMailing_Api_Abstract
 */
abstract class WC_Stannp_DirectMailing_Api_Abstract {
    /**
     * URL Separator
     */
    const US = '/';
    /**
     * Constant URL; base URL for Stannp API calls
     */
    const URL = 'https://dash.stannp.com/api/v1';

    /**
     * Section of API calls
     *
     * @var
     */
    protected $section;
    /**
     * Action of API calls
     *
     * @var string
     */
    protected $action;
    /**
     * Parameters sent to the API call
     *
     * @var array
     */
    protected $params;
    /**
     * Method of API calls
     *
     * @var string
     */
    protected $method;

    /**
     *
     * @var WP_Http_Curl
     */
    private $curl;
    /**
     * The API key
     *
     * @var string | null
     */
    private $api_key;

    /**
     * WC_Stannp_DirectMailing_Api_Abstract constructor.
     *
     * @param WP_Http_Curl $curl
     * @param string $api_key
     */
    public function __construct( Curl $curl, $api_key)
    {
        $this->curl = $curl;
        $this->api_key = $api_key;
    }

    /**
     * Creates and returns the URL string using the properties of this class
     *
     * @return string
     */
    public function getUrlString()
    {
        $url = self::URL . self::US . $this->section. self::US . $this->action;
        $url = $url . ((mb_strtolower($this->action) == 'get')
            && (!empty($this->params['id']))? self::US . $this->params['id']  : '');

        return $url;
    }

    /**
     * Performs the API call to Stannp
     *
     * @return bool
     *
     * @throws \Exception
     * @throws \Zend_Uri_Exception
     */
    protected function call()
    {
        $options = array();
        $uri = $this->getUrlString();
        $paramString = null;

        if (is_array($this->params)) {
            $paramString = http_build_query($this->params);
        }

        $options['body'] = $paramString;
        $options['method'] = strtoupper($this->method);
        $options['header'] = false;
        if (!empty($this->api_key)) {
            $options['headers'] = array('Authorization' => 'Basic ' . base64_encode($this->api_key . ":"));
        }
        $options['httpversion'] = '1.1';

        return $this->parseResponse($this->curl->request($uri, $options));
    }

    /**
     * The response is an array formed of:
     * **** headers - the curl response headers
     * **** body - the json_encoded response sent from Stannp
     * **** response - formed of two elements: "code" and "message"
     * **** cookies
     * **** filename
     * @param $response
     *
     * @return bool
     *
     * @throws \Exception
     */
    protected function parseResponse($response)
    {
        if(!is_wp_error($response)) {

            if ($response['response']['code'] == '200') {

                if (!empty($response['body'])) {
                    $bodyArray = json_decode($response['body'], true);

                    if (is_array($bodyArray) && isset($bodyArray['success'])) {

                        if ($bodyArray['success'] && isset($bodyArray['data'])) {
                            return $bodyArray['data'];
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * General list action
     *
     * @return bool
     * @throws Exception
     * @throws Zend_Uri_Exception
     */
    public function getList()
    {
        $this->action = 'list';
        $this->method = 'get';
        $this->params = null;

        return $this->call();
    }

    /**
     * Abstract function createElement - to be defined in every class that extends this one
     *
     * @param array $params
     * @return mixed
     */
    abstract public function createElement(array $params);

    /**
     * General get action
     *
     * @param $id
     *
     * @return bool
     *
     * @throws Exception
     * @throws Zend_Uri_Exception
     */
    public function getElement($id)
    {
        $this->action = 'get';
        $this->method = 'get';
        $this->params = ['id' => $id];

        return $this->call();
    }

    /**
     * General delete action
     *
     * @param $id
     *
     * @return bool|mixed
     * @throws \Exception
     * @throws \Zend_Uri_Exception
     */
    public function deleteElement($id)
    {
        $this->action = 'delete';
        $this->method = 'post';
        $this->params = ['id' => $id];

        return $this->call();
    }
}