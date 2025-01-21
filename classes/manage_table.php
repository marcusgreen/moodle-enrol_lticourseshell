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
 * Displays enrolment lticourseshell instances.
 *
 * @package    enrol_lticourseshell
 * @copyright  2016 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_lticourseshell;

defined('MOODLE_INTERNAL') || die;

global $CFG;

require_once($CFG->libdir . '/tablelib.php');

/**
 * Handles displaying enrolment lticourseshell instances.
 *
 * @package    enrol_lticourseshell
 * @copyright  2016 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manage_table extends \table_sql {

    /**
     * @var \enrol_plugin $lticourseshellplugin
     */
    protected $lticourseshellplugin;

    /**
     * @var bool $lticourseshellenabled
     */
    protected $lticourseshellenabled;

    /**
     * @var bool $canconfig
     */
    protected $canconfig;

    /**
     * @var int $courseid The course id.
     */
    protected $courseid;

    /**
     * Sets up the table.
     *
     * @param string $courseid The id of the course.
     */
    public function __construct($courseid) {
        parent::__construct('enrol_lti_cs_manage_table');

        $this->define_columns(array(
            'name',
            'launch',
            'registration',
            'edit'
        ));
        $this->define_headers(array(
            get_string('name'),
            get_string('launchdetails', 'enrol_lticourseshell'),
            get_string('registrationurl', 'enrol_lticourseshell'),
            get_string('edit')
        ));
        $this->collapsible(false);
        $this->sortable(false);

        // Set the variables we need access to.
        $this->lticourseshellplugin = enrol_get_plugin('lticourseshell');
        $this->lticourseshellenabled = enrol_is_enabled('lticourseshell');
        $this->canconfig = has_capability('moodle/course:enrolconfig', \context_course::instance($courseid));
        $this->courseid = $courseid;

        // Set help icons.
        $launchicon = new \help_icon('launchdetails', 'enrol_lticourseshell');
        $regicon = new \help_icon('registrationurl', 'enrol_lticourseshell');
        $this->define_help_for_headers(['1' => $launchicon, '2' => $regicon]);
    }

    /**
     * Generate the name column.
     *
     * @param \stdClass $tool event data.
     * @return string
     */
    public function col_name($tool) {
        $toolcontext = \context::instance_by_id($tool->contextid, IGNORE_MISSING);
        $name = $toolcontext ? helper::get_name($tool) : $this->get_deleted_activity_name_html($tool);

        return $this->get_display_text($tool, $name);
    }

    /**
     * Generate the launch column.
     *
     * @param \stdClass $tool instance data.
     * @return string
     */
    public function col_launch($tool) {
        global $OUTPUT;

        $url = helper::get_cartridge_url($tool);

        $cartridgeurllabel = get_string('cartridgeurl', 'enrol_lticourseshell');
        $cartridgeurl = $url;
        $secretlabel = get_string('secret', 'enrol_lticourseshell');
        $secret = $tool->secret;
        $launchurl = helper::get_launch_url($tool->id);
        $launchurllabel = get_string('launchurl', 'enrol_lticourseshell');

        $data = [
                "rows" => [
                    [ "label" => $cartridgeurllabel, "text" => $cartridgeurl, "id" => "cartridgeurl", "hidelabel" => false ],
                    [ "label" => $secretlabel, "text" => $secret, "id" => "secret", "hidelabel" => false ],
                    [ "label" => $launchurllabel, "text" => $launchurl, "id" => "launchurl", "hidelabel" => false ],
                ]
            ];

        $return = $OUTPUT->render_from_template("enrol_lticourseshell/copy_grid", $data);

        return $return;
    }

    /**
     * Generate the Registration column.
     *
     * @param \stdClass $tool instance data.
     * @return string
     */
    public function col_registration($tool) {
        global $OUTPUT;

        $url = helper::get_proxy_url($tool);

        $toolurllabel = get_string("registrationurl", "enrol_lticourseshell");
        $toolurl = $url;

        $data = [
                "rows" => [
                    [ "label" => $toolurllabel, "text" => $toolurl, "id" => "toolurl" , "hidelabel" => true],
                ]
            ];

        $return = $OUTPUT->render_from_template("enrol_lticourseshell/copy_grid", $data);
        return $return;
    }

    /**
     * Generate the edit column.
     *
     * @param \stdClass $tool event data.
     * @return string
     */
    public function col_edit($tool) {
        global $OUTPUT;

        $buttons = array();

        $instance = new \stdClass();
        $instance->id = $tool->enrolid;
        $instance->courseid = $tool->courseid;
        $instance->enrol = 'lticourseshell';
        $instance->status = $tool->status;

        $strdelete = get_string('delete');
        $strenable = get_string('enable');
        $strdisable = get_string('disable');

        $url = new \moodle_url('/enrol/lticourseshell/index.php',
            array('sesskey' => sesskey(), 'courseid' => $this->courseid, 'legacy' => 1));

        if ($this->lticourseshellplugin->can_delete_instance($instance)) {
            $aurl = new \moodle_url($url, array('action' => 'delete', 'instanceid' => $instance->id));
            $buttons[] = $OUTPUT->action_icon($aurl, new \pix_icon('t/delete', $strdelete, 'core',
                array('class' => 'iconsmall')));
        }

        if ($this->lticourseshellenabled && $this->lticourseshellplugin->can_hide_show_instance($instance)) {
            if ($instance->status == ENROL_INSTANCE_ENABLED) {
                $aurl = new \moodle_url($url, array('action' => 'disable', 'instanceid' => $instance->id));
                $buttons[] = $OUTPUT->action_icon($aurl, new \pix_icon('t/hide', $strdisable, 'core',
                    array('class' => 'iconsmall')));
            } else if ($instance->status == ENROL_INSTANCE_DISABLED) {
                $aurl = new \moodle_url($url, array('action' => 'enable', 'instanceid' => $instance->id));
                $buttons[] = $OUTPUT->action_icon($aurl, new \pix_icon('t/show', $strenable, 'core',
                    array('class' => 'iconsmall')));
            }
        }

        if ($this->lticourseshellenabled && $this->canconfig) {
            $linkparams = array(
                'courseid' => $instance->courseid,
                'id' => $instance->id,
                'type' => $instance->enrol,
                'legacy' => 1,
                'returnurl' => new \moodle_url('/enrol/lticourseshell/index.php',
                    array('courseid' => $this->courseid, 'legacy' => 1))
            );
            $editlink = new \moodle_url("/enrol/editinstance.php", $linkparams);
            $buttons[] = $OUTPUT->action_icon($editlink, new \pix_icon('t/edit', get_string('edit'), 'core',
                array('class' => 'iconsmall')));
        }

        return implode(' ', $buttons);
    }

    /**
     * Query the reader. Store results in the object for use by build_table.
     *
     * @param int $pagesize size of page for paginated displayed table.
     * @param bool $useinitialsbar do you want to use the initials bar.
     */
    public function query_db($pagesize, $useinitialsbar = true) {
        $total = \enrol_lticourseshell\helper::count_lti_tools(['courseid' => $this->courseid, 'ltiversion' => 'lticourseshell-1p0/lticourseshell-2p0']);
        $this->pagesize($pagesize, $total);
        $tools = \enrol_lticourseshell\helper::get_lti_tools(['courseid' => $this->courseid, 'ltiversion' => 'lticourseshell-1p0/lticourseshell-2p0'],
            $this->get_page_start(), $this->get_page_size());
        $this->rawdata = $tools;
        // Set initial bars.
        if ($useinitialsbar) {
            $this->initialbars($total > $pagesize);
        }
    }

    /**
     * Returns text to display in the columns.
     *
     * @param \stdClass $tool the tool
     * @param string $text the text to alter
     * @return string
     */
    protected function get_display_text($tool, $text) {
        if ($tool->status != ENROL_INSTANCE_ENABLED) {
            return \html_writer::tag('div', $text, array('class' => 'dimmed_text'));
        }

        return $text;
    }

    /**
     * Get a warning icon, with toolticourseshellp, describing enrolment instances sharing activities which have been deleted.
     *
     * @param \stdClass $tool the tool instance record.
     * @return string the HTML for the name column.
     */
    protected function get_deleted_activity_name_html(\stdClass $tool): string {
        global $OUTPUT;
        $icon = \html_writer::tag(
            'a',
            $OUTPUT->pix_icon('enrolinstancewarning', get_string('deletedactivityalt' , 'enrol_lticourseshell'), 'enrol_lticourseshell'), [
                "class" => "btn btn-link p-0",
                "role" => "button",
                "data-container" => "body",
                "data-toggle" => "popover",
                "data-placement" => right_to_left() ? "left" : "right",
                "data-content" => get_string('deletedactivitydescription', 'enrol_lticourseshell'),
                "data-html" => "true",
                "tabindex" => "0",
                "data-trigger" => "focus"
            ]
        );
        $name = \html_writer::span($icon . get_string('deletedactivity', 'enrol_lticourseshell'));
        if ($tool->name) {
            $name .= \html_writer::empty_tag('br') . \html_writer::empty_tag('br') . $tool->name;
        }

        return $name;
    }
}
