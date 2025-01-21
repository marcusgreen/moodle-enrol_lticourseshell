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
 * List the tool provided in a course
 *
 * @package    enrol_lticourseshell
 * @copyright  2016 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use enrol_lticourseshell\local\ltiadvantage\table\published_resources_table;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot.'/enrol/lticourseshell/lib.php');

$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$legacy = optional_param('legacy', false, PARAM_BOOL);
if ($action) {
    require_sesskey();
    $instanceid = required_param('instanceid', PARAM_INT);
    $instance = $DB->get_record('enrol', array('id' => $instanceid), '*', MUST_EXIST);
}
$confirm = optional_param('confirm', 0, PARAM_INT);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);

$context = context_course::instance($course->id);

require_login($course);
require_capability('moodle/course:enrolreview', $context);

$lticourseshellplugin = enrol_get_plugin('lticourseshell');
$canconfig = has_capability('moodle/course:enrolconfig', $context);
$pageurl = new moodle_url('/enrol/lticourseshell/index.php', array('courseid' => $courseid, 'legacy' => $legacy));

$PAGE->set_url($pageurl);
$PAGE->set_title(get_string('course') . ': ' . $course->fullname);
$PAGE->set_pagelayout('admin');
xdebug_break();
// Check if we want to perform any actions.
if ($action) {
    if ($action === 'delete') {
        if ($lticourseshellplugin->can_delete_instance($instance)) {
            if ($confirm) {
                $lticourseshellplugin->delete_instance($instance);
                redirect($PAGE->url);
            }

            $yesurl = new moodle_url('/enrol/lticourseshell/index.php',
                array('courseid' => $course->id,
                    'action' => 'delete',
                    'instanceid' => $instance->id,
                    'confirm' => 1,
                    'sesskey' => sesskey())
                );
            $displayname = $lticourseshellplugin->get_instance_name($instance);
            $users = $DB->count_records('user_enrolments', array('enrolid' => $instance->id));
            if ($users) {
                $message = markdown_to_html(get_string('deleteinstanceconfirm', 'enrol',
                    array('name' => $displayname,
                          'users' => $users)));
            } else {
                $message = markdown_to_html(get_string('deleteinstancenousersconfirm', 'enrol',
                    array('name' => $displayname)));
            }
            echo $OUTPUT->header();
            echo $OUTPUT->confirm($message, $yesurl, $PAGE->url);
            echo $OUTPUT->footer();
            die();
        }
    } else if ($action === 'disable') {
        if ($lticourseshellplugin->can_hide_show_instance($instance)) {
            if ($instance->status != ENROL_INSTANCE_DISABLED) {
                $lticourseshellplugin->update_status($instance, ENROL_INSTANCE_DISABLED);
                redirect($PAGE->url);
            }
        }
    } else if ($action === 'enable') {
        if ($lticourseshellplugin->can_hide_show_instance($instance)) {
            if ($instance->status != ENROL_INSTANCE_ENABLED) {
                $lticourseshellplugin->update_status($instance, ENROL_INSTANCE_ENABLED);
                redirect($PAGE->url);
            }
        }
    }
}

echo $OUTPUT->header();
if ($legacy) {
    echo $OUTPUT->heading(get_string('toolsprovided', 'enrol_lticourseshell'));
    echo html_writer::tag('p', get_string('toolsprovided_help', 'enrol_lticourseshell'));
} else {
    echo $OUTPUT->heading(get_string('publishedcontent', 'enrol_lticourseshell'));
    echo html_writer::tag('p', get_string('publishedcontent_help', 'enrol_lticourseshell'));
}
echo html_writer::tag('p', $OUTPUT->doc_link('enrol/lticourseshell/index', get_string('morehelp')), ['class' => 'helplink']);


// Distinguish between legacy published tools and lticourseshell-Advantage published resources.
$tabs = [
    0 => [
        new tabobject('0', new moodle_url('/enrol/lticourseshell/index.php', ['courseid' => $courseid]),
            get_string('lticourseshell13', 'enrol_lticourseshell')),
        new tabobject('1', new moodle_url('/enrol/lticourseshell/index.php', ['legacy' => 1, 'courseid' => $courseid]),
             get_string('lticourseshelllegacy', 'enrol_lticourseshell')),
    ]
];
$selected = $legacy ? '1' : '0';
echo html_writer::div(print_tabs($tabs, $selected, null, null, true), 'lticourseshell-resource-publication');

if ($legacy) {
    $notify = new \core\output\notification(get_string('lticourseshelllegacydeprecatednotice', 'enrol_lticourseshell'),
        \core\output\notification::NOTIFY_WARNING);
    echo $OUTPUT->render($notify);
    if (\enrol_lticourseshell\helper::count_lti_tools(array('courseid' => $courseid, 'ltiversion' => 'LTI-1p0/LTI-2p0')) > 0) {

        $table = new \enrol_lticourseshell\manage_table($courseid);
        $table->define_baseurl($pageurl);
        $table->out(50, false);
    } else {
        $notify = new \core\output\notification(get_string('notoolsprovided', 'enrol_lticourseshell'),
            \core\output\notification::NOTIFY_INFO);
        echo $OUTPUT->render($notify);
    }
} else {
    if (\enrol_lticourseshell\helper::count_lti_tools(array('courseid' => $courseid, 'ltiversion' => 'LTI-1p3')) > 0) {
        $table = new published_resources_table($courseid);
        $table->define_baseurl($pageurl);
        $table->out(50, false);
    } else {
        $notify = new \core\output\notification(get_string('nopublishedcontent', 'enrol_lticourseshell'),
            \core\output\notification::NOTIFY_INFO);
        echo $OUTPUT->render($notify);
    }
}
if ($lticourseshellplugin->can_add_instance($course->id)) {
    echo $OUTPUT->single_button(new moodle_url('/enrol/editinstance.php',
        array(
            'legacy' => $legacy,
            'type' => 'lticourseshell',
            'courseid' => $course->id,
            'returnurl' => new moodle_url('/enrol/lticourseshell/index.php', ['courseid' => $course->id, 'legacy' => $legacy]))
        ),
        get_string('add'));
}

echo $OUTPUT->footer();
