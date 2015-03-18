<?php
/**
 * Created by PhpStorm.
 * User: jesperborgstrup
 * Date: 16/03/15
 * Time: 10:53
 */

class CoinifyAPI {

    /**
     * Coinify API key. Get yours at https://coinify.com/merchant/api
     *
     * @var string
     */
    private $api_key;
    /**
     * Coinify API secret. Get yours at https://coinify.com/merchant/api
     *
     * @var string
     */
    private $api_secret;

    /**
     * Base URL to the Coinify API.
     *
     * @var string
     */
    private $api_base_url;

    /**
     * A human-readable error message for the last error that happened during a cURL call to the API.
     * This property is set whenever an API call returns false.
     *
     * @var string|null
     */
    public $last_curl_error = null;
    /**
     * A cURL error code for the last error that happened during a cURL call to the API.
     * This property is set whenever an API call returns false.
     *
     * @var string|null
     */
    public $last_curl_errno = null;

    /**
     * The base URL for the API without a trailing slash
     */
    const API_DEFAULT_BASE_URL = "https://api.coinify.com";

    public function __construct( $api_key, $api_secret, $api_base_url=null ) {
        $this->api_key = $api_key;
        $this->api_secret = $api_secret;
        $this->api_base_url = $api_base_url !== null ? $api_base_url : self::API_DEFAULT_BASE_URL;
    }

    /**
     * Returns an array of all your Coinify invoices
     *
     * @link https://coinify.com/docs/api/#list-all-invoices
     *
     * @return array A PHP array as described in https://coinify.com/docs/api/#response-format. If success,
     * then the 'data' value contains a list of all your invoices.
     */
    public function invoicesList() {
        return $this->callApiAuthenticated( '/v3/invoices' );
    }

    /**
     * Create a new invoice.
     *
     * @link https://coinify.com/docs/api/#create-an-invoice
     *
     * @param float $amount Fiat price of the invoice
     * @param string $currency 3 letter ISO 4217 currency code denominating amount
     * @param string $plugin_name The name of the plugin used to call this API
     * @param string $plugin_version The version of the above plugin
     * @param string $description Your custom text for this invoice.
     * @param array $custom Your custom data for this invoice
     * @param string $callback_url A URL that Coinify calls when the invoice state changes.
     * @param string $callback_email An email address to send a mail to when the invoice state changes
     * @param string $return_url We redirect your customer to this URL to when the invoice has been paid
     * @param string $cancel_url We redirect your customer to this URL if they cancel the invoice (not yet in use)
     * @return array A PHP array as described in https://coinify.com/docs/api/#response-format. If success,
     * then the 'data' value contains the new invoice.
     */
    public function invoiceCreate( $amount, $currency, $plugin_name, $plugin_version,
                                   $description=null, $custom=null, $callback_url=null, $callback_email=null,
                                   $return_url=null, $cancel_url=null ) {
        $params = [
            'amount' => $amount,
            'currency' => $currency,
            'return_url' => $return_url,
            'cancel_url' => $cancel_url,
            'plugin_name' => $plugin_name,
            'plugin_version' => $plugin_version,
        ];

        if ( $description !== null ) $params['description'] = $description;
        if ( $custom !== null ) $params['custom'] = $custom;
        if ( $callback_url !== null ) $params['callback_url'] = $callback_url;
        if ( $callback_email !== null ) $params['callback_email'] = $callback_email;
        if ( $return_url !== null ) $params['return_url'] = $return_url;
        if ( $cancel_url !== null ) $params['cancel_url'] = $cancel_url;

        return $this->callApiAuthenticated( '/v3/invoices', 'POST', $params );
    }

    /**
     * Get a specific invoice
     *
     * @link https://coinify.com/docs/api/#get-a-specific-invoice
     *
     * @param $invoice_id
     * @return array A PHP array as described in https://coinify.com/docs/api/#response-format. If success,
     * then the 'data' value contains the requested invoice.
     */
    public function invoiceGet( $invoice_id ) {
        return $this->callApiAuthenticated( "/v3/invoices/{$invoice_id}" );
    }

    /**
     * Update the description and custom data of an invoice
     *
     * @link https://coinify.com/docs/api/#update-an-invoice
     *
     * @param int $invoice_id The ID of the invoice you want to update
     * @param string $description Your custom text for this invoice.
     * @param array $custom Your custom data for this invoice
     * @return array A PHP array as described in https://coinify.com/docs/api/#response-format. If success,
     * then the 'data' value contains the updated invoice.
     */
    public function invoiceUpdate( $invoice_id, $description=null, $custom=null ) {
        $params = [];

        if ( $description !== null ) $params['description'] = $description;
        if ( $custom !== null ) $params['custom'] = $custom;

        return $this->callApiAuthenticated( "/v3/invoices/{$invoice_id}", "PUT", $params );
    }

    /**
     * Perform an authenticated API call, using the
     * API key and secret provided in the constructor.
     *
     * @param string $path The API path, WITH leading slash, e.g. '/v3/invoices'
     * @param array $params Associative array of parameters to the API call
     * @return array|false A PHP array as described in https://coinify.com/docs/api/#response-format,
     * or false if the HTTP call couldn't be performed correctly.
     * If false, use the $last_curl_error and $last_curl_errno properties to
     * get the error.
     */
    private function callApiAuthenticated( $path, $method='GET', $params=[] ) {
        $url = $this->api_base_url . $path;

        $ch = curl_init( $url );
        curl_setopt($ch, CURLOPT_HTTPHEADER, [ $this->generateAuthorizationHeader() ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if ($method != 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode( $params ) );
        }

        $json_response = curl_exec( $ch );

        if ( $json_response === false ) {
            /*
             * If an error occurred, remember the error
             * and return false.
             */
            $this->last_curl_error = curl_error( $ch );
            $this->last_curl_errno = curl_errno( $ch );
            return false;
        }

        /*
         * No error, just decode the JSON response, and return it.
         */
        $response = json_decode( $json_response, true );

        curl_close( $ch );

        return $response;
    }

    /**
     * Generate a nonce and a signature for an API call and wrap those in a HTTP header
     *
     * @return string A string with a full HTTP header like the following:
     * 'Authorization: Coinify apikey="<api_key>", nonce="<nonce>", signature="<signature>"'
     */
    private function generateAuthorizationHeader() {
        // Generate a nonce, based on the current time
        $mt = explode( ' ', microtime() );
        $nonce = $mt[1] . substr($mt[0], 2, 6 );

        $apikey = $this->api_key;

        // Concatenate the nonce and the API key
        $message = $nonce . $apikey;
        // Compute the signature and convert it to lowercase
        $signature = strtolower( hash_hmac('sha256', $message, $this->api_secret, false ) );

        // Construct the HTTP Authorization header.
        $auth_header = "Authorization: Coinify apikey=\"$apikey\", nonce=\"$nonce\", signature=\"$signature\"";

        return $auth_header;
    }

}