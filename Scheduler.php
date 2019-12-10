<?php
namespace Aivec\Welcart\ProprietaryAuthentication;

/**
 * Proprietary authentication for Aivec plugins/themes.
 */
class Scheduler extends Auth implements Scaffold {

    /**
     * The display name for the admin console nag.
     *
     * @var string
     */
    private $nag_display_name;

    /**
     * Schedules cron if authenticated, otherwise sends cURL auth request.
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @param string $sku
     * @param string $nag_display_name
     * @param string $plugin_file  name of plugin entry file INCLUDING absolute path
     */
    public function __construct($sku, $nag_display_name, $plugin_file) {
        require_once(__DIR__ . '/vendor/autoload.php');

        load_textdomain('aauth', __DIR__ . '/languages/aauth-ja.mo');
        load_textdomain('aauth', __DIR__ . '/languages/aauth-en.mo');
        parent::__construct($sku);

        $this->nag_display_name = $nag_display_name;

        add_action('admin_notices', array($this, 'nag'));
        add_action($sku . '_validate_install', array($this, 'cronValidateInstall'));
        if ($this->authenticated() === false) {
            $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
            if ($action !== 'heartbeat') {
                $this->processAuthData();
            }
        } else {
            $cron = wp_next_scheduled($sku . '_validate_install');
            if (!$cron) {
                $date = new \DateTime('03:00', new \DateTimeZone('Asia/Tokyo'));
                $date = $date->add(new \DateInterval('P1D'));
                $timestamp = $date->getTimestamp();
                wp_schedule_event($timestamp, 'daily', $sku . '_validate_install');
            }
        }

        register_deactivation_hook($plugin_file, array($this, 'clearCron'));
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
        $asmp_results = $this->authenticate();

        if ($asmp_results['status'] === 'success') {
            $this->setAsmpVED(true);
            $this->setNagErrorMessage('');
            do_action('aauth_' . $this->sku . '_on_success');
        }
        if ($asmp_results['status'] === 'error') {
            $this->setAsmpVED(false);
            $this->setNagErrorMessage($asmp_results['error']['message']);
            do_action('aauth_' . $this->sku . '_on_failure');
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
        if ($this->authenticated() === false) {
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
    }

    /**
     * Clear cron when plugin using Aauth is deactivated
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function clearCron() {
        wp_clear_scheduled_hook($this->sku . '_validate_install');
    }
}
