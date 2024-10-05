<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Page to display payment form and process payment.
 *
 * @package     paygw_paystack
 * @copyright   2023 Mohammad Farouk <phun.for.physics@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use core_payment\helper;

require_once(__DIR__ . '/../../../config.php');

require_login(null, false);

global $CFG, $USER, $DB;

$component   = required_param('component', PARAM_ALPHANUMEXT);
$paymentarea = required_param('paymentarea', PARAM_ALPHANUMEXT);
$itemid      = required_param('itemid', PARAM_INT);
$description = required_param('description', PARAM_TEXT);

$params = [
    'component'   => $component,
    'paymentarea' => $paymentarea,
    'itemid'      => $itemid,
    'description' => $description,
];

$config = (object) helper::get_gateway_configuration($component, $paymentarea, $itemid, 'paystack');

$payable = helper::get_payable($component, $paymentarea, $itemid);// Get currency and payment amount.
$surcharge = helper::get_gateway_surcharge('paystack');// In case we use surcharge.

$currency = $payable->get_currency();
$cost = helper::get_rounded_cost($payable->get_amount(), $currency, $surcharge);

$paystack = new paygw_paystack\util;
// $secretkey = $paystack->get_secretkey($config);
$publickey = $paystack->get_publickey($config);
$customfields = $paystack->get_custom_fields($config);
$reference = $paystack->getHashedToken();

// Set the context of the page.
$PAGE->set_context(context_system::instance());

$PAGE->set_url('/payment/gateway/paystack/process.php', $params);
$PAGE->set_title(format_string('Paying for: '.$description));
$PAGE->set_heading(format_string('Paying for: '.$description));

// Set the appropriate headers for the page.
$PAGE->set_cacheable(false);
$PAGE->set_pagelayout('frontpage');

echo $OUTPUT->header();

$action = new moodle_url('/payment/gateway/paystack/verify.php');

$mform = new moodleQuickForm('paystackprocess', 'post', $action);
$data = [
    "cmd" => "_xclick",
    "charset" => "utf-8",
    "item_name" => s($component.'_'.$paymentarea),
    "item_number" => s($itemid),
    "quantity" => "1",
    "on0" => get_string("user"),
    "os0" => s(fullname($USER)),
    "custom" => "{$USER->id}-{$itemid}-{$component}-{$paymentarea}",
    "currency_code" => $currency,
    "amount" => $cost,
    "for_auction" => "false",
    "no_note" => "1",
    "no_shipping" => "1" ,
    "rm" => "2",
    "first_name" => s($USER->firstname),
    "last_name" => s($USER->lastname),
    "email" => s($USER->email),
    "reference" => $reference,
    "custom_fields" => $customfields,
];

foreach ($data as $key => $value) {
    if (is_array($value)) {
        $value = json_encode($value);
    }
    $mform->addElement('hidden', $key);
    $mform->setType($key, PARAM_RAW);
    $mform->setDefault($key, $value);
}

$scripttag = '<script src="https://js.paystack.co/v1/inline.js" 
    data-key="'.$publickey.'"
    data-ref="'.$reference.'"
    data-firstname="'.s($USER->firstname).'"
    data-lastname="'.s($USER->lastname).'"
    data-currency="'.s($currency).'"
    data-amount="'.s($cost * 100).'"
    data-email="'.s($USER->email).'"
    data-metadata=\''.json_encode($data).'\'
    data-locale="'.current_language().'">
</script>';
$scripttag .= "<script>console.log(".json_encode((object)$data).")</script>";
$mform->addElement('html', $scripttag);

$mform->display();

echo $OUTPUT->footer();
