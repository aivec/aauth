<?php
namespace Aivec\Welcart\ProprietaryAuthentication;

use AAUTH\GuzzleHttp;
use AAUTH\GuzzleHttp\Exception\RequestException;
use InvalidArgumentException;

/**
 * Proprietary authentication API calls for Aivec plugins/themes.
 */
class Auth {

    const VERSION = '4.0.0';
    const OPTIONS_KEY = 'asmp_authdata';

    /**
     * The SKU of this plugin/theme
     *
     * @var string
     */
    protected $sku;

    /**
     * The version number of this plugin/theme
     *
     * @var string
     */
    protected $product_version;
   
    /**
     * Aivec proprietary authentication Sellers instance
     *
     * @var Sellers
     */
    private $sellers;

    /**
     * Initialize this class and sets member variables.
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $sku
     * @param string $product_version
     */
    public function __construct($sku, $product_version) {
        $this->sku = $sku;
        $this->product_version = $product_version;
        header('Access-Control-Allow-Origin: ' . $this->getOrigin(), false);
    }

    /**
     * Creates `GuzzleHttp\Client` instance that attempts to validate a proprietary theme/plugin with Aivec's
     * validation plugin. The validation plugin waits for requests at the given endpoint.
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return array
     */
    protected function authenticate() {
        global $wp_version, $wpdb;

        $domain = $this->getHost();
        $guzzle = new GuzzleHttp\Client([
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Referer' => $domain,
            ],
        ]);

        $server_info = null;
        if ($wpdb->use_mysqli) {
            $server_info = mysqli_get_server_info($wpdb->dbh);
        } else {
            $server_info = mysql_get_server_info($wpdb->dbh);
        }
        
        $reqbody = [
            'payload' => json_encode(
                [
                    'domain' => $domain,
                    'aauthVersion' => self::VERSION,
                    'productVersion' => $this->product_version,
                    'welcartVersion' => USCES_VERSION,
                    'wordpressVersion' => $wp_version,
                    'phpVersion' => phpversion(),
                    'webServer' => isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : '',
                    'databaseInfo' => $server_info,
                    'databaseVersion' => $wpdb->db_version(),
                ]
            ),
        ];

        $opts = $this->getOptions();
        $seller = isset($opts['provider']) ? $opts['provider'] : 'Aivec';
        $defaulte = '* ' . sprintf(
            // translators: the name of the company whose server is being called
            __('There was a problem accessing %s server. Please try again.', 'aauth'),
            $seller
        );

        $result = $defaulte;
        try {
            $response = $guzzle->post(
                trim($this->getEndpoint(), '/') . "/wcexasmp/authenticate/{$this->sku}",
                ['form_params' => $reqbody]
            );
            $result = json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $error = json_decode($e->getResponse()->getBody(), true);
                if (is_array($error)) {
                    $result = isset($error['message']) ? $error['message'] : $defaulte;
                }
            }
        }

        return $result;
    }

    /**
     * Gets the host name of the current server. The host name extracted via this method
     * MUST be the same as the domain registered by the client.
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return string   // the domain name
     */
    protected function getHost() {
        $possible_host_sources = ['HTTP_X_FORWARDED_HOST', 'HTTP_HOST', 'SERVER_NAME'];
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
     * Returns options for the SKU of this instance
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return array
     */
    public function getOptions() {
        $asmp_options = get_option(self::OPTIONS_KEY);
        return $asmp_options[$this->sku];
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
     * Override Auth default sellers instance
     *
     * @param Sellers $sellers
     * @throws InvalidArgumentException Thrown if sellers is invalid.
     * @return void
     */
    public function setSellers($sellers) {
        if (!($sellers instanceof Sellers)) {
            throw new InvalidArgumentException(
                'sellers is not an instance of Aivec\Welcart\ProprietaryAuthentication\Sellers'
            );
        }

        $this->sellers = $sellers;
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
    public function getNagErrorMessage() {
        $asmp_options = get_option(self::OPTIONS_KEY);
        return isset($asmp_options[$this->sku]['nag_error_message']) ?
            $asmp_options[$this->sku]['nag_error_message']
            : '';
    }

    /**
     * Getter for SKU member var
     *
     * @return string
     */
    public function getSku() {
        return $this->sku;
    }

    /**
     * Getter for provider
     *
     * @return string
     */
    public function getProvider() {
        return $this->getOptions()['provider'];
    }

    /**
     * Getter for origin
     *
     * @return string
     */
    public function getOrigin() {
        return $this->getOptions()['origin'];
    }

    /**
     * Getter for endpoint
     *
     * @return string
     */
    public function getEndpoint() {
        return $this->getOptions()['endpoint'];
    }

    /**
     * Getter for seller_site
     *
     * @return string
     */
    public function getSellerSite() {
        return $this->getOptions()['seller_site'];
    }

    /**
     * Getter for sellers object
     *
     * @return Sellers|null
     */
    public function getSellers() {
        return $this->sellers;
    }
}
