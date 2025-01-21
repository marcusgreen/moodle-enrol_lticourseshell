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
 * The main entry point for the external system.
 *
 * @package    enrol_lticourseshell
 * @copyright  2016 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$toolid = required_param('id', PARAM_INT);

$PAGE->set_context(context_system::instance());
$url = new moodle_url('/enrol/lticourseshell/tool.php');
$PAGE->set_url($url);
$PAGE->set_pagelayout('popup');
$PAGE->set_title(get_string('opentool', 'enrol_lticourseshell'));

// Get the tool.
$tool = \enrol_lticourseshell\helper::get_lticourseshell_tool($toolid);

// Check if the authentication plugin is disabled.
if (!is_enabled_auth('lti')) {
    throw new \moodle_exception('pluginnotenabled', 'auth', '', get_string('pluginname', 'auth_lti'));
    exit();
}

// Check if the enrolment plugin is disabled.
if (!enrol_is_enabled('lticourseshell')) {
    throw new \moodle_exception('enrolisdisabled', 'enrol_lticourseshell');
    exit();
}

// Check if the enrolment instance is disabled.
if ($tool->status != ENROL_INSTANCE_ENABLED) {
    throw new \moodle_exception('enrolisdisabled', 'enrol_lticourseshell');
    exit();
}

// Check if the enrolment instance has been upgraded to a newer lticourseshell version.
if ($tool->ltiversion != 'lticourseshell-1p0/lticourseshell-2p0') {
    throw new \moodle_exception('enrolltiversionincorrect', 'enrol_lticourseshell');
    exit();
}

$consumerkey = required_param('oauth_consumer_key', PARAM_TEXT);
$ltiversion = optional_param('lticourseshell_version', null, PARAM_TEXT);
$messagetype = required_param('lticourseshell_message_type', PARAM_TEXT);

// Only accept launch requests from this endpoint.
if ($messagetype != "basic-lticourseshell-launch-request") {
    throw new \moodle_exception('invalidrequest', 'enrol_lticourseshell');
    exit();
}

// Initialise tool provider.
$toolprovider = new \enrol_lticourseshell\tool_provider($toolid);

// Special handling for lticourseshellv1 launch requests.
if ($ltiversion === \IMSGlobal\LTI\ToolProvider\ToolProvider::lticourseshell_VERSION1) {
    $dataconnector = new \enrol_lticourseshell\data_connector();
    $consumer = new \IMSGlobal\LTI\ToolProvider\ToolConsumer($consumerkey, $dataconnector);
    // Check if the consumer has already been registered to the enrol_lti_cs_lticourseshell2_consumer table. Register if necessary.
    $consumer->ltiversion = \IMSGlobal\LTI\ToolProvider\ToolProvider::lticourseshell_VERSION1;
    // For lticourseshellv1, set the tool secret as the consumer secret.
    $consumer->secret = $tool->secret;
    $consumer->name = optional_param('tool_consumer_instance_name', '', PARAM_TEXT);
    $consumer->consumerName = $consumer->name;
    $consumer->consumerGuid = optional_param('tool_consumer_instance_guid', null, PARAM_TEXT);
    $consumer->consumerVersion = optional_param('tool_consumer_info_version', null, PARAM_TEXT);
    $consumer->enabled = true;
    $consumer->protected = true;
    $consumer->save();

    // Set consumer to tool provider.
    $toolprovider->consumer = $consumer;
    // Map tool consumer and published tool, if necessary.
    $toolprovider->map_tool_to_consumer();
}

// Handle the request.
$toolprovider->handleRequest();

echo $OUTPUT->header();
echo $OUTPUT->footer();
