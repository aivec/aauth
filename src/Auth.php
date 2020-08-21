<?php
namespace Aivec\Welcart\ProprietaryAuthentication;

use InvalidArgumentException;

/**
 * Proprietary authentication API calls for Aivec plugins/themes.
 */
class Auth {

    const VERSION = '6.0.0';
    const OPTIONS_KEY = 'asmp_authdata';

    /**
     * The `productUniqueId` of this plugin/theme
     *
     * @var string
     */
    protected $productUniqueId;

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
     * @param string $productUniqueId
     * @param string $product_version
     */
    public function __construct($productUniqueId, $product_version) {
        $this->productUniqueId = $productUniqueId;
        $this->product_version = $product_version;
        header('Access-Control-Allow-Origin: ' . $this->getOrigin(), false);
    }

    /**
     * Creates `cURL` instance that attempts to validate a proprietary theme/plugin with Aivec's
     * validation plugin.
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @global string $wp_version
     * @global \wpdb $wpdb
     * @return array
     */
    protected function authenticate() {
        global $wp_version, $wpdb;

        $server_info = null;
        if ($wpdb->use_mysqli) {
            $server_info = mysqli_get_server_info($wpdb->dbh);
        } else {
            $server_info = mysql_get_server_info($wpdb->dbh);
        }
        
        $domain = $this->getHost();
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

        $curl_opts = [
            CURLOPT_URL            => trim($this->getEndpoint(), '/') . "/wcexcptm/authenticate/{$this->productUniqueId}",
            CURLOPT_REFERER        => $this->getHost(),
            CURLOPT_HEADER         => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($reqbody),
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, $curl_opts);
        $result = json_decode(curl_exec($ch), true);
        $errno = curl_errno($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $opts = $this->getOptions();
        $seller = isset($opts['provider']) ? $opts['provider'] : 'Aivec';
        $defaulte = '* ' . sprintf(
            // translators: the name of the company whose server is being called
            __('There was a problem accessing %s server. Please try again.', 'aauth'),
            $seller
        );

        if (!empty($errno) || $httpcode >= 400) {
            if (is_array($result)) {
                return isset($result['message']) ? $result['message'] : $defaulte;
            }

            return $defaulte;
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
     * Returns options for the `productUniqueId` of this instance
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return array
     */
    public function getOptions() {
        $asmp_options = get_option(self::OPTIONS_KEY);
        return $asmp_options[$this->productUniqueId];
    }

    /**
     * Setter for asmp_ved
     *
     * @param boolean $asmp_ved
     * @return void
     */
    public function setAsmpVED($asmp_ved) {
        $asmp_options = get_option(self::OPTIONS_KEY);
        $asmp_options[$this->productUniqueId]['asmp_ved'] = $asmp_ved;
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
        $asmp_options[$this->productUniqueId]['nag_error_message'] = $error_message;
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
        return isset($asmp_options[$this->productUniqueId]['asmp_ved']) ? $asmp_options[$this->productUniqueId]['asmp_ved'] : false;
    }

    /**
     * Getter for nag error message
     *
     * @return string
     */
    public function getNagErrorMessage() {
        $asmp_options = get_option(self::OPTIONS_KEY);
        return isset($asmp_options[$this->productUniqueId]['nag_error_message']) ?
            $asmp_options[$this->productUniqueId]['nag_error_message']
            : '';
    }

    /**
     * Getter for `productUniqueId` member var
     *
     * @return string
     */
    public function getProductUniqueId() {
        return $this->productUniqueId;
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
