<?php
namespace Aivec\Welcart\ProprietaryAuthentication;

use InvalidArgumentException;

/**
 * Plugin/theme Sellers class for adding/subtracting sellers
 */
class Sellers {

    /**
     * Aivec proprietary authentication instance
     *
     * @var Auth
     */
    private $aauth;

    /**
     * Array of sellers (providers).
     *
     * This property is used to determine whether to display the seller selection section
     * on the 決済設定ページ for a Module. If only one seller is provided, they will be used
     * by default and the radio buttons selection WILL NOT appear.
     *
     * Only provide a seller if they are CURRENTLY selling. By default, the ninsho-validator
     * plugin on a sellers site will return 'success' if no such SKU exists, which means that
     * if you provide an invalid seller and the buyer selects it on the 決済設定ページ, this
     * Module will become authenticated regardless of whether they are paying or not.
     *
     * ['aivec', 'welcart']
     *
     * @var array
     */
    private $sellers;

    /**
     * Meta data for provided sellers
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @var array
     */
    private $meta;

    /**
     * Default provider to set
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @var string
     */
    private $default_provider;

    /**
     * Meta data for aivec and welcart sellers
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @var array
     */
    private $default_aauth_meta = [
        'aivec' => [
            'dev' => [
                'origin' => 'https://aivec.co.jp',
                'api_endpoint' => 'https://www.aivec.co.jp/plugin_test/',
                'seller_site' => 'aivec.co.jp/plugin_test',
            ],
            'prod' => [
                'origin' => 'https://aivec.co.jp',
                'api_endpoint' => 'https://www.aivec.co.jp/plugin/',
                'seller_site' => 'aivec.co.jp/plugin',
            ],
        ],
        'welcart' => [
            'dev' => [
                'origin' => 'https://php7.welcart.org',
                'api_endpoint' => 'https://php7.welcart.org/',
                'seller_site' => 'php7.welcart.org',
            ],
            'prod' => [
                'origin' => 'https://www.welcart.com',
                'api_endpoint' => 'https://www.welcart.com/',
                'seller_site' => 'www.welcart.com',
            ],
        ],
    ];

    /**
     * Init Providers
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param Auth   $aauth
     * @param string $default_provider
     * @param array  $sellers
     * @param array  $aauth_meta
     * @throws InvalidArgumentException Thrown if aauth is not an instance of
     * \Aivec\Welcart\ProprietaryAuthentication\Auth.
     */
    public function __construct($aauth, $default_provider = 'aivec', $sellers = ['aivec'], $aauth_meta = []) {
        if (!($aauth instanceof Auth)) {
            throw new InvalidArgumentException(
                'aauth is not an instance of \Aivec\Welcart\ProprietaryAuthentication\Auth'
            );
        }
        $this->aauth = $aauth;
        $this->default_provider = $default_provider;
        $this->sellers = $sellers;
        $this->meta = array_merge($this->default_aauth_meta, $aauth_meta);
        $this->validateSellers();
        $this->validateDefaultProvider();
        $this->validateAauthMeta();
        $this->setDefaultSellerOptions();

        add_action('init', array($this, 'updateAuthProvider'));
    }

    /**
     * Validates $sellers construct parameter
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @throws InvalidArgumentException Thrown if $sellers is malformed.
     * @return void
     */
    private function validateSellers() {
        if (!is_array($this->sellers)) {
            throw new InvalidArgumentException('sellers must be an array');
        }
        if (!in_array('aivec', $this->sellers, true) && !in_array('welcart', $this->sellers, true)) {
            throw new InvalidArgumentException('at least one of \'aivec\' or \'welcart\' must be in sellers');
        }
    }

    /**
     * Validates $default_provider construct parameter
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @throws InvalidArgumentException Thrown if $default_provider is invalid.
     * @return void
     */
    private function validateDefaultProvider() {
        if (!in_array($this->default_provider, $this->sellers, true)) {
            throw new InvalidArgumentException('default_provider does not exists in sellers');
        }
    }

    /**
     * Validates $aauth_meta construct parameter
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @throws InvalidArgumentException Thrown if $aauth_meta is missing required keys.
     * @return void
     */
    private function validateAauthMeta() {
        $i = 0;
        $temp_sellers = $this->sellers;
        foreach ($temp_sellers as $seller) {
            if ($seller === 'aivec' || $seller === 'welcart') {
                unset($temp_sellers[$i]);
            }
            $i++;
        }

        if (count($temp_sellers) < 1) {
            // no additional sellers provided. Abort
            return;
        }

        if (!is_array($this->meta)) {
            throw new InvalidArgumentException('meta must be an array');
        }

        foreach ($temp_sellers as $seller) {
            if (!array_key_exists($seller, $this->meta)) {
                throw new InvalidArgumentException('seller \'' . $seller . '\' missing from meta array');
            }
            if (!array_key_exists('prod', $this->meta[$seller])) {
                throw new InvalidArgumentException(
                    'seller \'' . $seller . '\' missing \'prod\' key in meta array'
                );
            }
            if (!array_key_exists('origin', $this->meta[$seller]['prod'])) {
                throw new InvalidArgumentException(
                    'seller \'' . $seller . '\' missing \'origin\' key in meta array under prod'
                );
            }
            if (!array_key_exists('api_endpoint', $this->meta[$seller]['prod'])) {
                throw new InvalidArgumentException(
                    'seller \'' . $seller . '\' missing \'api_endpoint\' key in meta array under prod'
                );
            }
            if (!array_key_exists('seller_site', $this->meta[$seller]['prod'])) {
                throw new InvalidArgumentException(
                    'seller \'' . $seller . '\' missing \'seller_site\' key in meta array under prod'
                );
            }
        }
    }

    /**
     * Set default seller options
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function setDefaultSellerOptions() {
        $aoptions = get_option(Auth::OPTIONS_KEY);
        $opts = $aoptions[$this->aauth->getSku()];
        $opts['provider'] = isset($opts['provider']) ? $opts['provider'] : $this->default_provider;
        $opts['meta'] = $this->meta;
        if (isset($_ENV['AVC_NODE_ENV']) &&
            $_ENV['AVC_NODE_ENV'] === 'development' &&
            isset($opts['meta'][$opts['provider']]['dev'])
        ) {
            $dev = $opts['meta'][$opts['provider']]['dev'];
            $opts['origin'] = isset($dev['origin']) ? $dev['origin'] : '';
            $opts['endpoint'] = isset($dev['api_endpoint']) ? $dev['api_endpoint'] : '';
            $opts['seller_site'] = isset($dev['seller_site']) ? $dev['seller_site'] : '';
            $aoptions[$this->aauth->getSku()] = $opts;
            update_option(Auth::OPTIONS_KEY, $aoptions);
            return;
        } elseif (isset($opts['meta'][$opts['provider']]['prod'])) {
            $prod = $opts['meta'][$opts['provider']]['prod'];
            $opts['origin'] = isset($prod['origin']) ? $prod['origin'] : '';
            $opts['endpoint'] = isset($prod['api_endpoint']) ? $prod['api_endpoint'] : '';
            $opts['seller_site'] = isset($prod['seller_site']) ? $prod['seller_site'] : '';
            $aoptions[$this->aauth->getSku()] = $opts;
            update_option(Auth::OPTIONS_KEY, $aoptions);
            return;
        }

        $opts['origin'] = '';
        $opts['endpoint'] = '';
        $opts['seller_site'] = '';
        $aoptions[$this->aauth->getSku()] = $opts;
        update_option(Auth::OPTIONS_KEY, $aoptions);
    }

    /**
     * Update provider from user selection on settlement settings page
     *
     * Since 'admin_notices' fires before the settlement options are updated, it will still display
     * the same nag message after options are changed so long as the page has not been refreshed.
     * To get around this, we update the auth provider selected from the settlement settings page
     * in this hook before displaying the nag message so that the correct result is displayed immediately.
     * It's not pretty, but it works.
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @global \usc_e_shop $usces
     * @return void
     */
    public function updateAuthProvider() {
        global $usces;

        if (isset($_POST['usces_option_update']) && isset($_POST['acting'])) {
            check_admin_referer('admin_settlement', 'wc_nonce');

            $_POST = $usces->stripslashes_deep_post($_POST);
            $provider = isset($_POST[$this->aauth->getSku()]['aauth_provider']) ? sanitize_text_field(wp_unslash($_POST[$this->aauth->getSku()]['aauth_provider'])) : '';
            if (!empty($provider)) {
                $opts = get_option(Auth::OPTIONS_KEY);
                $opts[$this->aauth->getSku()]['provider'] = $provider;
                $meta = $opts[$this->aauth->getSku()]['meta'][$provider]['prod'];
                if (isset($_ENV['AVC_NODE_ENV']) &&
                    $_ENV['AVC_NODE_ENV'] === 'development' &&
                    isset($opts[$this->aauth->getSku()]['meta'][$provider]['dev'])
                ) {
                    $meta = $opts[$this->aauth->getSku()]['meta'][$provider]['dev'];
                }
                // update origin/endpoint so that authentication is retried on the proper endpoint
                $opts[$this->aauth->getSku()]['origin'] = isset($meta['origin']) ? $meta['origin'] : '';
                $opts[$this->aauth->getSku()]['endpoint'] = isset($meta['api_endpoint']) ? $meta['api_endpoint'] : '';
                $opts[$this->aauth->getSku()]['seller_site'] = isset($meta['seller_site']) ? $meta['seller_site'] : '';
                $opts[$this->aauth->getSku()]['asmp_ved'] = false;
                update_option(Auth::OPTIONS_KEY, $opts);

                // retry authentication with new selections
                $this->aauth->processAuthData();
            }
        }
    }

    /**
     * Gets seller meta given a seller
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $seller
     * @return array
     */
    public function getSellerMeta($seller) {
        if (isset($_ENV['AVC_NODE_ENV']) && $_ENV['AVC_NODE_ENV'] === 'development') {
            return $this->meta[$seller]['dev'];
        }

        return $this->meta[$seller]['prod'];
    }

    /**
     * Seller selection box for Settlement Module using Aauth when more than
     * one seller exists
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return string
     */
    public function getSettlementModuleSellerRadioSelections() {
        $html = '';
        if (count($this->sellers) > 1) :
            ob_start();
            ?>
            <div class="settlement_service">
                <span class="service_title">
                    <?php echo esc_html__('Authentication', 'aauth'); ?>
                </span>
            </div>
            <table class="settle_table aivec">
                <tr class="radio">
                    <th><?php echo esc_html__('Please choose your provider', 'aauth'); ?></th>
                    <?php $opts = $this->aauth->getOptions(); ?>
                    <td>
                        <div>
                        <?php foreach ($this->meta as $seller => $meta) : ?>
                            <?php $m = $this->getSellerMeta($seller); ?>
                            <label>
                                <input
                                    name="<?php echo $this->aauth->getSku() ?>[aauth_provider]"
                                    type="radio"
                                    id="aauth_provider_<?php echo esc_attr($this->aauth->getSku() . $seller) ?>"
                                    value="<?php echo esc_attr($seller) ?>"
                                    <?php echo $opts['provider'] === $seller ? 'checked' : ''; ?>
                                />
                                <span><?php echo $m['seller_site'] ?></span>
                            </label>
                        <?php endforeach; ?>
                        </div>
                    </td>
                </tr>
            </table>
            <?php
            $html = ob_get_contents();
            ob_end_clean();
        endif;
        return $html;
    }
}
