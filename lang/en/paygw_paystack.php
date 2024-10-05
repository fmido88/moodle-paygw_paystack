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
 * Plugin strings are defined here.
 *
 * @package     enrol_paystack
 * @copyright   2019 Paystack
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['btntext']= 'Pay Now';
$string['billingaddress'] = 'Require users to enter their billing address';
$string['billingaddress_help'] = 'This sets the Paystack payment option for whether the user should be asked to input their billing address. It is off by default, but it is a good idea to turn it on.';
$string['cost'] = 'Enrol cost';
$string['costerror'] = 'The enrolment cost is not numeric';
$string['costorkey'] = 'Please choose one of the following methods of enrolment.';
$string['currency'] = 'Currency';
$string['customfields'] = 'Profile fields to be used as custom fields';
$string['customfields_help'] = 'Which user profile fields can be used during enrolment';
$string['live_secretkey'] = 'Paystack Live Secret Key';
$string['live_publickey'] = 'Paystack Live Public Key';
$string['live_secretkey_help'] = 'The Live API Secret Key of Paystack account';
$string['live_publickey_help'] = 'The Live API Public Key of Paystack account';
$string['mailadmins'] = 'Notify admin';
$string['mailstudents'] = 'Notify students';
$string['mailteachers'] = 'Notify teachers';
$string['messageprovider:paystack_enrolment'] = 'Paystack enrolment messages';
$string['mode'] = 'Paystack Connection Mode';
$string['mode_desc'] = 'Set api configuration to use when Paystack charges for enrolment into a course by default.';
$string['mode_live'] = 'Live Mode';
$string['mode_test'] = 'Test Mode';
$string['nocost'] = 'There is no cost associated with enrolling in this course!';
$string['paymentthanks']='Thanks for your payment';
$string['paymentsorry'] = 'Sorry, The payment didn\'t proceed.';
$string['paystackaccepted'] = 'Paystack payments accepted';
$string['pluginname'] = 'Paystack';
$string['pluginname_desc'] = 'The Paystack Payment Gateway.';
$string['test_secretkey'] = 'Paystack Test Secret Key';
$string['test_publickey'] = 'Paystack Test Public Key';
$string['test_secretkey_help'] = 'The Test API Secret Key of Paystack account';
$string['test_publickey_help'] = 'The Test API Public Key of Paystack account';
$string['messageprovider:paystackpayment_gateway'] = 'Message Provider for Paystack';
$string['validatezipcode'] = 'Validate the billing postal code';
$string['validatezipcode_desc'] = 'This sets the Paystack payment option for whether the billing address should be verified as part of processing the payment. They strongly recommend that this option should be on, to reduce fraud.';
$string['charge_description1'] = "Create customer for email receipt";
$string['charge_description2'] = 'Charge for Course Enrolment Cost.';
$string['paystack_sorry'] = "Sorry, you can not use the script that way.";
$string['webhook'] = 'Paystack Webhook Url';
$string['webhook_desc'] = 'Add this Webhook Url "{$a->webhook}" to your paystack account developer settings page <a href="{$a->url}">here</a>';
$string['gatewaydescription'] = 'Paystack Payment Gateway.';
$string['gatewayname'] = 'PayStack';
