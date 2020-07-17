<?php
namespace Aivec\Welcart\ProprietaryAuthentication;

/**
 * Aivec proprietary authentication interface. Basic scaffolding for an implementation
 * of the auth library.
 *
 * @author Evan D Shaw <evandanielshaw@gmail.com>
 */
interface Scaffold {

    /**
     * A catch all function that sends the authentication request to aivec.co.jp/plugin and
     * processes any authentication errors.
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function processAuthData();

    /**
     * 'nag' refers to the dialog box that shows at the top of the WordPress admin panel. Should
     * be used to show any errors or warnings regarding subscriptions.
     *
     * @author Evan D Shaw <evandanielshaw@gmail.com>
     * @return void
     */
    public function nag();
}
