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
     * @param string $auth_endpoint
     * @param string $allow_origin
     */
    public function __construct($sku, $nag_display_name, $auth_endpoint, $allow_origin) {
        parent::__construct($sku, $auth_endpoint, $allow_origin);

        $this->nag_display_name = $nag_display_name;

        add_action('admin_notices', array($this, 'nag'));
        add_action($sku . '_validate_install', array($this, 'cronValidateInstall'));
        if ($this->authenticated() === false) {
            $this->processAuthData();
        } else {
            $cron = wp_next_scheduled($sku . '_validate_install');
            if (!$cron) {
                $date = new \DateTime('03:00', new \DateTimeZone('Asia/Tokyo'));
                $date = $date->add(new \DateInterval('P1D'));
                $timestamp = $date->getTimestamp();
                // wp_schedule_event($timestamp, 'daily', 'asmp_validate_install');
                wp_schedule_event(time(), 'hourly', $sku . '_validate_install');
            }
        }
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
     * for this plugin.
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function nag() {
        if ($this->authenticated() === false) {
            $nag_message = $this->getNagErrorMessge();
            if (!empty($nag_message)) {
                $class = 'notice notice-error';
                $message = sprintf(
                    /* translators: %s: formatted plugin name. */
                    __('%1$s： %2$s', 'aivec'),
                    $this->nag_display_name,
                    $this->getNagErrorMessge()
                );
                printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), $message);
            }
        }
    }
}
