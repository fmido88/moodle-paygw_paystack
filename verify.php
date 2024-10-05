<?php
// This file is part of Moodle - http://moodle.org/
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
/**
 * Listens for Instant Payment Notification from Paystack
 *
 * This script waits for Payment notification from Paystack,
 * then double checks that data by sending it back to Paystack.
 * If Paystack verifies this then it save payment and deliver order
 *
 * @package    paygw_paystack
 * @copyright   2023 Mohammad Farouk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// Disable moodle specific debug messages and any errors in output,
// comment out when debugging or better look into error log!
define('NO_DEBUG_DISPLAY', true);

use core_payment\helper;

require_once(__DIR__ . '/../../../config.php');
// require_once('lib.php');
if ($CFG->version < 2018101900) {
    require_once($CFG->libdir . '/eventslib.php');
}
require_once($CFG->libdir . '/filelib.php');
global $DB;
require_login();

// Paystack does not like when we return error messages here,
// the custom handler just logs exceptions and stops.
set_exception_handler(\paygw_paystack\util::get_exception_handler());

// Keep out casual intruders.
if (empty($_POST) || !empty($_GET)) {
    http_response_code(400);
    throw new moodle_exception('invalidrequest', 'core_error');
}

required_param('paystack-trxref', PARAM_RAW);

$data = new stdClass();

foreach ($_POST as $key => $value) {
    if ($key !== clean_param($key, PARAM_ALPHANUMEXT)) {
        throw new moodle_exception('invalidrequest', 'core_error', '', null, $key);
    }
    if (is_array($value)) {
        throw new moodle_exception('invalidrequest', 'core_error', '', null, 'Unexpected array param: ' . $key);
    }
    $data->$key = fix_utf8($value);
}

if (empty($data->custom)) {
    throw new moodle_exception('invalidrequest', 'core_error', '', null, 'Missing request param: custom');
}

$custom = explode('-', $data->custom);
unset($data->custom);
if (empty($custom) || count($custom) < 4) {
    throw new moodle_exception('invalidrequest', 'core_error', '', null, 'Invalid value of the request param: custom');
}

$data->userid           = (int) $custom[0];
$data->itemid           = (int) $custom[1];
$data->component        = trim($custom[2]);
$data->paymentarea      = trim($custom[3]);
$data->payment_gross    = $data->amount;
$data->payment_currency = $data->currency_code;
$data->timeupdated      = time();

// Get the user and course records.
$user = $DB->get_record("user", array("id" => $data->userid), "*", MUST_EXIST);
$context = context_course::instance(SITEID);
$PAGE->set_context($context);

// Use the queried course's full name for the item_name field.
$data->item_name = $data->component .'_'. $data->paymentarea;

$util = new paygw_paystack\util;
$config = (object) helper::get_gateway_configuration($data->component, $data->paymentarea, $data->itemid, 'paystack');

$paystack = new \paygw_paystack\paystack($util->get_publickey($config), $util->get_secretkey($config), $data->component);
$payable = helper::get_payable($data->component, $data->paymentarea, $data->itemid);// Get currency and payment amount.
$surcharge = helper::get_gateway_surcharge('paystack');// In case user uses surcharge.
$url = helper::get_success_url($data->component, $data->paymentarea, $data->itemid);

$currency = $payable->get_currency();
$cost = $payable->get_amount();
$accountid = $payable->get_account_id();

// Verify Transaction 
$res = $paystack->verify_transaction($data->reference);

if (!$res['status']) {
    notice($res['message'], $url);
}

// Send the file, this line will be reached if no error was thrown above.
$data->tax = $res['data']['amount'] / 100;
$data->memo = $res['data']['gateway_response'];
$data->payment_status = $res['data']['status'];
$data->reason_code = $code;

// If currency is incorrectly set then someone maybe trying to cheat the system
if ($data->currency_code != $currency) {
    $message = "Currency does not match payment settings, received: " . $data->currency_code;
    \paygw_paystack\util::message_paystack_error_to_admin(
        $message,
        $data
    );
    notice($message, $url);
}

// Use the same rounding of floats.
$cost = format_float($cost, 2, false);

// If cost is greater than payment_gross, then someone maybe trying to cheat the system
if ($data->payment_gross < $cost) {
    $message = "Amount paid is not enough ($data->payment_gross < $cost))";
    \paygw_paystack\util::message_paystack_error_to_admin(
        $message,
        $data
    );
    notice($message, $url);
}

$checkrecord = [
    'component'   => $data->component,
    'paymentarea' => $data->paymentarea,
    'itemid'      => $data->itemid,
    'userid'      => $user->id,
    'amount'      => $payable->get_amount(),
    'currency'    => $currency,
    'gateway'     => 'paystack',
    'accountid'   => $accountid,
];
if ($DB->record_exists('payments', $checkrecord)) {
    redirect($url, get_string('paymentthanks', '', ''));
}

if ($data->payment_status == 'success') {
    // ALL CLEAR !
    $paystack->log_transaction_success($data->reference);
    $DB->insert_record("paygw_paystack", $data);
    $paymentid = helper::save_payment($accountid,
                                      $data->component,
                                      $data->paymentarea,
                                      $data->itemid,
                                      $user->id,
                                      $cost,
                                      $currency,
                                      'paystack'
                                    );
    $done = helper::deliver_order($data->component, $data->paymentarea, $data->itemid, $paymentid, $user->id);

    $mailstudents = $config->mailstudents;
    $mailadmins   = $config->mailadmins;
    // TODO Notify Admins and users according to settings.
    // $shortname = format_string($course->shortname, true, array('context' => $context));
    // if (!empty($mailstudents)) {
    //     $a = new stdClass();
    //     $a->course = format_string($course->fullname, true, array('context' => $coursecontext));
    //     $a->profileurl = "$CFG->wwwroot/user/view.php?id=$user->id";
    //     $eventdata = new \core\message\message();
    //     $eventdata->modulename        = 'moodle';
    //     $eventdata->component         = 'paygw_paystack';
    //     $eventdata->name              = 'paystack_enrolment';
    //     $eventdata->userfrom          = empty($teacher) ? core_user::get_support_user() : $teacher;
    //     $eventdata->userto            = $user;
    //     $eventdata->subject           = get_string("enrolmentnew", 'enrol', $shortname);
    //     $eventdata->fullmessage       = get_string('welcometocoursetext', '', $a);
    //     $eventdata->fullmessageformat = FORMAT_PLAIN;
    //     $eventdata->fullmessagehtml   = '';
    //     $eventdata->smallmessage      = '';
    //     message_send($eventdata);
    // }

    // if (!empty($mailadmins)) {
    //     $a = new stdClass();
    //     $a->course = format_string($course->fullname, true, array('context' => $coursecontext));
    //     $a->user = fullname($user);
    //     $admins = get_admins();
    //     foreach ($admins as $admin) {
    //         $eventdata = new \core\message\message();
    //         $eventdata->modulename        = 'moodle';
    //         $eventdata->component         = 'paygw_paystack';
    //         $eventdata->name              = 'paystack_enrolment';
    //         $eventdata->userfrom          = $user;
    //         $eventdata->userto            = $admin;
    //         $eventdata->subject           = get_string("enrolmentnew", 'enrol', $shortname);
    //         $eventdata->fullmessage       = get_string('enrolmentnewuser', 'enrol', $a);
    //         $eventdata->fullmessageformat = FORMAT_PLAIN;
    //         $eventdata->fullmessagehtml   = '';
    //         $eventdata->smallmessage      = '';
    //         message_send($eventdata);
    //     }
    // }
} else {
    $message = "Payment status not successful" . $data->memo;
    \paygw_paystack\util::message_paystack_error_to_admin(
        $message,
        $data
    );
    notice($message, $url);
}

if ($done) {
    redirect($url, get_string('paymentthanks', 'paygw_paystack'));
} else {   // Somehow they aren't enrolled yet!
    $PAGE->set_url($url);
    echo $OUTPUT->header();

    notice(get_string('paymentsorry', 'paygw_paystack'), $url);
}
