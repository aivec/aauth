<?php

namespace Aivec\Welcart\ProprietaryAuthentication;

/**
 * Proprietary authentication for Aivec plugins/themes.
 */
class Scheduler extends Auth implements Scaffold
{
    /**
     * The display name for the admin console nag.
     *
     * @var string
     */
    private $nag_display_name;

    /**
     * Absolute path to the plugin entry file
     *
     * @var string
     */
    private $plugin_file;

    /**
     * Schedules cron if authenticated, otherwise sends cURL auth request.
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $productUniqueId
     * @param string $product_version
     * @param string $nag_display_name
     * @param string $plugin_file name of plugin entry file INCLUDING absolute path
     */
    public function __construct($productUniqueId, $product_version, $nag_display_name, $plugin_file) {
        $mopath = __DIR__ . '/languages/aauth-' . get_locale() . '.mo';
        if (file_exists($mopath)) {
            load_textdomain('aauth', $mopath);
        } else {
            load_textdomain('aauth', __DIR__ . '/languages/aauth-en.mo');
        }
        parent::__construct($productUniqueId, $product_version);

        $this->nag_display_name = $nag_display_name;
        $this->plugin_file = $plugin_file;

        add_action('admin_notices', [$this, 'nag']);
        add_action('wp_error_added', [$this, 'setUpdateApiErrorResponse'], 10, 4);
        add_action($productUniqueId . '_validate_install', [$this, 'cronValidateInstall']);
        if ($this->authenticated() === false) {
            $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
            if ($action !== 'heartbeat') {
                $this->processAuthData();
            }
        } else {
            $cron = wp_next_scheduled($productUniqueId . '_validate_install');
            if (!$cron) {
                $date = new \DateTime('03:00', new \DateTimeZone('Asia/Tokyo'));
                $date = $date->add(new \DateInterval('P1D'));
                $timestamp = $date->getTimestamp();
                wp_schedule_event($timestamp, 'daily', $productUniqueId . '_validate_install');
            }
        }

        register_deactivation_hook($this->plugin_file, [$this, 'clearCron']);
    }

    /**
     * Sets the message for the `WP_Error` used for a failed update attempt.
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string|int $code     Error code.
     * @param string     $message  Error message.
     * @param mixed      $data     Error data. Might be empty.
     * @param WP_Error   $wperror The WP_Error object.
     * @return void
     */
    public function setUpdateApiErrorResponse($code, $message, $data, $wperror) {
        $body = isset($data['body']) ? (string)$data['body'] : '';
        if (empty($body)) {
            return;
        }
        $json = json_decode($body, true);
        if (empty($json)) {
            return;
        }
        if (empty($json['type']) || empty($json['cptItem']) || empty($json['error'])) {
            return;
        }
        if ($json['type'] === 'WCEXCPTM_API_ERROR') {
            if ($json['cptItem']['itemUniqueId'] !== $this->productUniqueId) {
                return;
            }

            /*
             * http_404 is set because WordPress automatically interprets a failed download request
             * as if the file couldn't be found...
             *
             * {@see wp-admin/includes/file.php download_url()}
             */
            if (!isset($wperror->errors['http_404'])) {
                return;
            }
            if (!isset($wperror->errors['http_404'][0])) {
                return;
            }
            $wperror->errors['http_404'][0] = $json['error']['message'];
        }
    }

    /**
     * Checks for plugin updates at the appropriate endpoint every hour
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function initUpdateChecker() {
        $updateEndpoint = trim($this->getEndpoint(), '/');
        if (isset($_ENV['AVC_NODE_ENV'])) {
            if ($_ENV['AVC_NODE_ENV'] === 'development') {
                $bridgeIp = isset($_ENV['DOCKER_BRIDGE_IP']) ? $_ENV['DOCKER_BRIDGE_IP'] : '';
                $port = isset($_ENV['UPDATE_CONTAINER_PORT']) ? $_ENV['UPDATE_CONTAINER_PORT'] : '';
                if (!empty($bridgeIp) && !empty($port)) {
                    $updateEndpoint = 'http://' . $bridgeIp . ':' . $port;
                }
            }
        }
        $updateChecker = \Puc_v4_Factory::buildUpdateChecker(
            add_query_arg(
                [
                    'wcexcptm_update_action' => 'get_metadata',
                    'wcexcptm_cptitem_unique_id' => $this->productUniqueId,
                    'domain' => $this->getHost(),
                ],
                $updateEndpoint . '/wp-update-server/'
            ),
            $this->plugin_file,
            '',
            1
        );
        $updateChecker->addQueryArgFilter(function ($queryArgs) {
            $queryArgs['wcexcptm_cptitem_unique_id'] = $this->productUniqueId;
            $queryArgs['domain'] = $this->getHost();
            return $queryArgs;
        });
    }

    /**
     * Runs daily for an authenticated install
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function cronValidateInstall() {
        $this->processAuthData();
    }

    /**
     * Processes authentication data for a given user.
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function processAuthData() {
        $res = $this->authenticate();

        if (!empty($res['cptItem']) && is_array($res['cptItem'])) {
            $this->setCptItem($res['cptItem']);
        }

        if ($res['result'] === 'success') {
            $this->setAsmpVED(true);
            $this->setNagErrorMessage('');
            do_action('aauth_' . $this->productUniqueId . '_on_success');
        } else {
            $this->setAsmpVED(false);
            $this->setNagErrorMessage($res['error']['message']);
            do_action('aauth_' . $this->productUniqueId . '_on_failure');
        }
    }

    /**
     * The nag (persistant warning or error message displayed at the top of the admin panel)
     * for this plugin. Displays if client is unauthenticated.
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function nag() {
        if ($this->authenticated() === true) {
            return;
        }
        $cptItem = $this->getCptItem();
        if ($cptItem === null) {
            // cptItem isn't initiated, fallback to 'success'
            return;
        }
        if (is_array($cptItem)) {
            $authmode = isset($cptItem['usageTermsCategory']) ? $cptItem['usageTermsCategory'] : '';
            if ($authmode !== 'restricted_usage_by_domain') {
                // Don't show an error message if the plugin/theme can be used anyways.
                // In other words, if only updates won't work, let the user discover that fact
                // from the plugin/theme update screen
                return;
            }
        }
        $nag_message = $this->getNagErrorMessage();
        if (!empty($nag_message)) {
            $class = 'notice notice-error';
            $message = sprintf(
                /* translators: 1: formatted plugin name, 2: response message from server */
                __('%1$s： %2$s', 'aauth'),
                $this->nag_display_name,
                $this->getNagErrorMessage()
            );
            printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), $message);
        }
    }

    /**
     * Clear cron when plugin using Aauth is deactivated
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function clearCron() {
        wp_clear_scheduled_hook($this->productUniqueId . '_validate_install');
    }
}
