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
 * Contains class for Paymob payment gateway.
 *
 * @package    paygw_paystack
 * @copyright  2023 Mo. Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_paystack;

/** Paystack live mode disabled. */
define('LIVE_MODE_DISABLED', 0);

/** Paystack live mode enabled.*/
define('LIVE_MODE_ENABLED', 1);
/**
 * The gateway class for PayPal payment gateway.
 *
 * @package    paygw_paystack
 * @copyright  2023 Mo. Farouk <phun.for.physics@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gateway extends \core_payment\gateway {
    /**
     * Returns the list of currencies that the payment gateway supports.
     * return an array of the currency codes in the three-character ISO-4217 format
     * @return array<string>
     */
    public static function get_supported_currencies(): array {
        return ['NGN', 'USD', 'GHS', 'KES', 'XOF', 'ZAR', 'EGP'];
    }

    /**
     * Configuration form for the gateway instance
     *
     * Use $form->get_mform() to access the \MoodleQuickForm instance
     *
     * @param \core_payment\form\account_gateway $form
     */
    public static function add_configuration_to_gateway_form(\core_payment\form\account_gateway $form): void {
        global $CFG;
        $mform = $form->get_mform();

        $webhook = "$CFG->wwwroot/payment/gateway/paystack/webhook.php";
        $url = "https://dashboard.paystack.com/#/settings/developer";
        $text = '<p>Add this Webhook Url <span style="color:blue; text-decoration:underline;">' . $webhook . '</span> to your paystack account developer settings page <a href="' . $url .'" target="_blank">here</a></p>';
        $mform->addElement('html', $text);
    
        $options = [
            LIVE_MODE_ENABLED  => get_string('mode_live', 'paygw_paystack'),
            LIVE_MODE_DISABLED => get_string('mode_test', 'paygw_paystack')
        ];
        $mform->addElement('select', 'mode', get_string('mode', 'paygw_paystack'), $options);
        $mform->addHelpButton('mode','mode', 'paygw_paystack');
    
        $mform->addElement('text', 'live_secretkey', get_string('live_secretkey', 'paygw_paystack'));
        $mform->setType('live_secretkey', PARAM_TEXT);
        $mform->addHelpButton('live_secretkey', 'live_secretkey', 'paygw_paystack');

        $mform->addElement('text', 'live_publickey', get_string('live_publickey', 'paygw_paystack'));
        $mform->setType('live_publickey', PARAM_TEXT);
        $mform->addHelpButton('live_publickey', 'live_publickey', 'paygw_paystack');

        $mform->addElement('text', 'test_secretkey', get_string('test_secretkey', 'paygw_paystack'));
        $mform->setType('test_secretkey', PARAM_TEXT);
        $mform->addHelpButton('test_secretkey', 'test_secretkey', 'paygw_paystack');

        $mform->addElement('text', 'test_publickey', get_string('test_publickey', 'paygw_paystack'));
        $mform->setType('test_publickey', PARAM_TEXT);
        $mform->addHelpButton('test_publickey', 'test_publickey', 'paygw_paystack');

        // TODO These options are not functional yet.
        $mform->addElement('checkbox', 'mailstudents', get_string('mailstudents', 'paygw_paystack'));
        $mform->addElement('checkbox', 'mailadmins', get_string('mailadmins', 'paygw_paystack'));

        global $DB;
        // Profile fields to use in the selector
        $customfieldrecords = $DB->get_records('user_info_field');
        if ($customfieldrecords) {
            $customfields = [];
            foreach ($customfieldrecords as $customfieldrecord) {
                $customfields[$customfieldrecord->shortname] = $customfieldrecord->name;
            }
            asort($customfields);
            $customfieldselement = $mform->addElement('select', 'customfields', get_string('customfields', 'paygw_paystack'), $customfields);
            $customfieldselement->setMultiple(true);
            $mform->addHelpButton('customfields', 'customfields', 'paygw_paystack');
        }

        // TODO Add default currency choice.
    }

    /**
     * Validates the gateway configuration form.
     *
     * @param \core_payment\form\account_gateway $form
     * @param \stdClass $data
     * @param array $files
     * @param array $errors form errors (passed by reference)
     */
    public static function validate_gateway_form(\core_payment\form\account_gateway $form,
                                                 \stdClass $data, array $files, array &$errors): void {
        $cant = false;
        if ($data->enabled) {
            if ($data->mode == LIVE_MODE_ENABLED) {
                if (empty($data->live_secretkey)) {
                    $errors['live_secretkey'] = 'Souldn\'t be blank in live moode';
                    $cant = true;
                }
                if (empty($data->live_publickey)) {
                    $errors['live_publickey'] = 'Souldn\'t be blank in live moode';
                    $cant = true;
                }
            } else if ($data->mode == LIVE_MODE_DISABLED) {
                if (empty($data->test_secretkey)) {
                    $errors['test_secretkey'] = 'Souldn\'t be blank in test moode';
                    $cant = true;
                }
                if (empty($data->test_publickey)) {
                    $errors['test_publickey'] = 'Souldn\'t be blank in test moode';
                    $cant = true;
                }
            }
            if ($cant) {
                $errors['enabled'] = get_string('gatewaycannotbeenabled', 'payment');
            }
        }
    }
}
