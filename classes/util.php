<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * PayPal payment plugin utility class.
 *
 * @package    paygw_paystack
 * @copyright  2019 Paystack
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_paystack;

defined('MOODLE_INTERNAL') || die();

/**
 * Paystack payment plugin utility class.
 *
 * @package   paygw_paystack
 * @copyright 2019 Paystack
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class util {
    /**
     * Return connection mode of this payment plugin.
     *
     * @return boolean
     */
    public function get_mode($config)
    {
        return $config->mode == "1" ? true : false;
    }

    /**
     * Return public key of this payment plugin.
     *
     * @return string
     */
    public function get_publickey($config)
    {
        return $this->get_mode($config) ? 
            $config->live_publickey :
            $config->test_publickey;
    }

    /**
     * Return secret key of this payment plugin.
     *
     * @return string
     */
    public function get_secretkey($config)
    {
        return $this->get_mode($config) ? 
            $config->live_secretkey :
            $config->test_secretkey;
    }
    /**
     * Send payment error message to the admin.
     *
     * @param string $subject
     * @param \stdClass $data
     */
    public static function message_paystack_error_to_admin($subject, $data) {
        $admin = get_admin();
        $site = get_site();
        $message = "$site->fullname:  Transaction failed.\n\n$subject\n\n";
        foreach ($data as $key => $value) {
            $message .= s($key) . " => " . s($value) . "\n";
        }
        $eventdata = new \core\message\message();
        $eventdata->modulename        = 'moodle';
        $eventdata->component         = 'paygw_paystack';
        $eventdata->name              = 'paystack_payment';
        $eventdata->userfrom          = $admin;
        $eventdata->userto            = $admin;
        $eventdata->subject           = "PAYSTACK PAYMENT ERROR: " . $subject;
        $eventdata->fullmessage       = $message;
        $eventdata->fullmessageformat = FORMAT_PLAIN;
        $eventdata->fullmessagehtml   = '';
        $eventdata->smallmessage      = '';
        message_send($eventdata);
    }

    /**
     * Silent exception handler.
     *
     * @return callable exception handler
     */
    public static function get_exception_handler() {
        return function($ex) {
            $info = get_exception_info($ex);

            $logerrmsg = "paygw_paystack exception handler: ".$info->message;
            if (debugging('', DEBUG_NORMAL)) {
                $logerrmsg .= ' Debug: '.$info->debuginfo."\n".format_backtrace($info->backtrace, true);
            }
            error_log($logerrmsg);

            if (http_response_code() == 200) {
                http_response_code(500);
            }

            exit(0);
        };
    }

    /**
     * Get all custom fields available for plugin.
     *
     * @return array $customfields.
     */
    public function get_custom_fields($config) {
        global $USER, $DB;

        $customfieldrecords = $DB->get_records('user_info_field');
        if (is_array((array)$config->customfields)) {
            $configured_customfields = (array)$config->customfields;
        } else if (is_string($config->customfields)) {
            $configured_customfields = explode(',', $config->customfields);
        } else {
            $configured_customfields = [];
        }

        $customfields = [];

        foreach ($customfieldrecords as $cus) {
            foreach($configured_customfields as $con) {
                if($con == $cus->shortname){
                    $customfields[] = [
                        'display_name' => $cus->name ,
                        'variable_name' => $cus->shortname,
                        'value' => $USER->profile[$con]
                    ];
                }
            }
        }

        return $customfields;
    }

    /**
     * Lists all currencies available for plugin.
     *
     * @return array $currencies.
     */
    public function get_currencies() {
        $codes = ['NGN', 'USD', 'GHS', 'KES', 'XOF', 'ZAR','EGP'];

        $currencies = [];
        foreach ($codes as $c) {
            $currencies[$c] = new \lang_string($c, 'core_currencies');
        }
        return $currencies;
    }

    /**
     * Converts all currency to cent value.
     *
     * @param  string $cost Reqular price.
     * @return integer $kobo price value in kobo.
     */
    public function get_cost_kobo($cost) {
        if (is_string($cost)) {
            $cost = floatval($cost);
        }
        $kobo = round($cost, 2) * 100;
        return $kobo;
    }

    /**
     * Converts cost from cent value to dollar.
     *
     * @param  string $cost cost in kobo.
     * @return float $full cost in naira.
     */
    public function get_cost_full($cost) {
        if (is_string($cost)) {
            $cost = floatval($cost);
        }
        $full = round($cost, 2) / 100;
        return $full;
    }

        /**
     * Generate a random secure crypt figure
     * @param  integer $min
     * @param  integer $max
     * @return integer
     */
    private static function secureCrypt($min, $max) {
        $range = $max - $min;
        if ($range < 0) {
            return $min; // not so random...
        }
        $log    = log($range, 2);
        $bytes  = (int) ($log / 8) + 1; // length in bytes
        $bits   = (int) $log + 1; // length in bits
        $filter = (int) (1 << $bits) - 1; // set all lower bits to 1
        do {
            $rnd = hexdec(bin2hex(openssl_random_pseudo_bytes($bytes)));
            $rnd = $rnd & $filter; // discard irrelevant bits
        } while ($rnd >= $range);
        return $min + $rnd;
    }

    /**
     * Finally, generate a hashed token
     * @param  integer $length
     * @return string
     */
    public static function getHashedToken($length = 25) {
        $token = "";
        $pool = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $max   = strlen($pool);
        for ($i = 0; $i < $length; $i++) {
            $token .= $pool[static::secureCrypt(0, $max)];
        }
        return $token;
    }
}
