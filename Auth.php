<?php
namespace Aivec\Welcart\ProprietaryAuthentication;

/**
 * Proprietary authentication API calls for Aivec plugins/themes.
 */
class Auth {

    const OPTIONS_KEY = 'asmp_authdata';

    /**
     * The SKU of this plugin/theme
     *
     * @var string
     */
    protected $sku;

    /**
     * Authentication endpoint
     *
     * @var string
     */
    protected $auth_endpoint;
   
    /**
     * Initialize this class and set member variables $sku and $auth_endpoint.
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $sku
     * @param string $auth_endpoint
     * @param string $allow_origin
     */
    public function __construct($sku, $auth_endpoint, $allow_origin) {
        $this->sku = $sku;
        $this->auth_endpoint = $auth_endpoint;

        header('Access-Control-Allow-Origin: ' . $allow_origin, false);
    }

    /**
     * Creates curl instance that attempts to validate a proprietary theme/plugin with Aivec's
     * validation plugin. The validation plugin waits for requests at the given .
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return array
     */
    protected function authenticate() {
        $ch = curl_init();
        $client_data = array();
        $client_data['sku'] = $this->sku;
        $curl_data = array(
            'asmp_action' => 'asmp_validate',
            'client_data' => $client_data,
        );

        $curl_opts = array(
            CURLOPT_URL            => $this->auth_endpoint,
            CURLOPT_REFERER        => $this->getHost(),
            CURLOPT_HEADER         => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($curl_data),
        );
        
        curl_setopt_array($ch, $curl_opts);
        $output = curl_exec($ch);
        $errno = curl_errno($ch);

        $asmp_results = array();
        if ($errno) {
            $error_message = curl_strerror($errno);
            $error_mes = '* ' . __('There was a problem accessing Aivecs server. Please try again.', 'aivec');
            $asmp_results['curl_error'] = $error_mes;
        } else {
            $asmp_results = json_decode($output, true);
        }

        curl_close($ch);

        return $asmp_results;
    }

    /**
     * Gets the host name of the current server. The host name extracted via this method
     * MUST be the same as the domain registered by the client.
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return string   // the domain name
     */
    protected function getHost() {
        $possible_host_sources = array('HTTP_X_FORWARDED_HOST', 'HTTP_HOST', 'SERVER_NAME');
        $host = '';
        foreach ($possible_host_sources as $source) {
            if (!empty($host)) {
                break;
            }
            if (empty($_SERVER[$source])) {
                continue;
            }
            $url = esc_url_raw(wp_unslash($_SERVER[$source]));
            $scheme = wp_parse_url($url, PHP_URL_SCHEME);
            if (!$scheme) {
                $url = 'http://'.$url;
            }
            $host = wp_parse_url($url, PHP_URL_HOST);
        }
        return trim($host);
    }
   
    /**
     * Utility function that returns true if authenticated and false if otherwise.
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return boolean
     */
    public function authenticated() {
        return ($this->getAsmpVED());
    }

    /**
     * Setter for asmp_ved
     *
     * @param boolean $asmp_ved
     * @return void
     */
    public function setAsmpVED($asmp_ved) {
        $asmp_options = get_option(self::OPTIONS_KEY);
        $asmp_options[$this->sku]['asmp_ved'] = $asmp_ved;
        update_option(self::OPTIONS_KEY, $asmp_options);
    }

    /**
     * Setter for nag error message
     *
     * @param string $error_message
     * @return void
     */
    public function setNagErrorMessage($error_message) {
        $asmp_options = get_option(self::OPTIONS_KEY);
        $asmp_options[$this->sku]['nag_error_message'] = $error_message;
        update_option(self::OPTIONS_KEY, $asmp_options);
    }

    /**
     * Getter for asmp_ved
     *
     * @return boolean
     */
    public function getAsmpVED() {
        $asmp_options = get_option(self::OPTIONS_KEY);
        return isset($asmp_options[$this->sku]['asmp_ved']) ? $asmp_options[$this->sku]['asmp_ved'] : false;
    }

    /**
     * Getter for nag error message
     *
     * @return string
     */
    public function getNagErrorMessge() {
        $asmp_options = get_option(self::OPTIONS_KEY);
        return isset($asmp_options[$this->sku]['nag_error_message']) ?
            $asmp_options[$this->sku]['nag_error_message']
            : '';
    }
}
