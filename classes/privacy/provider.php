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
 * Privacy Subsystem implementation for enrol_lticourseshell.
 *
 * @package    enrol_lticourseshell
 * @category   privacy
 * @copyright  2018 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_lticourseshell\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy Subsystem for enrol_lticourseshell.
 *
 * @copyright  2018 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Return the fields which contain personal data.
     *
     * @param collection $items a reference to the collection to use to store the metadata.
     * @return collection the updated collection of metadata items.
     */
    public static function get_metadata(collection $items): collection {
        $items->add_database_table(
            'enrol_lti_cs_users',
            [
                'userid' => 'privacy:metadata:enrol_lti_cs_users:userid',
                'lastgrade' => 'privacy:metadata:enrol_lti_cs_users:lastgrade',
                'lastaccess' => 'privacy:metadata:enrol_lti_cs_users:lastaccess',
                'timecreated' => 'privacy:metadata:enrol_lti_cs_users:timecreated'
            ],
            'privacy:metadata:enrol_lti_cs_users'
        );

        return $items;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT DISTINCT ctx.id
                  FROM {enrol_lti_cs_users} lticourseshellusers
                  JOIN {enrol_lti_cs_tools} lticourseshelltools
                    ON lticourseshellusers.toolid = lticourseshelltools.id
                  JOIN {context} ctx
                    ON ctx.id = lticourseshelltools.contextid
                 WHERE lticourseshellusers.userid = :userid";
        $params = ['userid' => $userid];
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param   userlist    $userlist   The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!($context instanceof \context_course || $context instanceof \context_module)) {
            return;
        }

        $sql = "SELECT lticourseshellusers.userid
                  FROM {enrol_lti_cs_users} lticourseshellusers
                  JOIN {enrol_lti_cs_tools} lticourseshelltools ON lticourseshellusers.toolid = lticourseshelltools.id
                 WHERE lticourseshelltools.contextid = :contextid";
        $params = ['contextid' => $context->id];
        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();

        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);

        $sql = "SELECT lticourseshellusers.lastgrade, lticourseshellusers.lastaccess, lticourseshellusers.timecreated, lticourseshelltools.contextid
                  FROM {enrol_lti_cs_users} lticourseshellusers
                  JOIN {enrol_lti_cs_tools} lticourseshelltools
                    ON lticourseshellusers.toolid = lticourseshelltools.id
                  JOIN {context} ctx
                    ON ctx.id = lticourseshelltools.contextid
                 WHERE ctx.id {$contextsql}
                   AND lticourseshellusers.userid = :userid";
        $params = $contextparams + ['userid' => $user->id];
        $lticourseshellusers = $DB->get_recordset_sql($sql, $params);
        self::recordset_loop_and_export($lticourseshellusers, 'contextid', [], function($carry, $record) {
            $carry[] = [
                'lastgrade' => $record->lastgrade,
                'timecreated' => transform::datetime($record->lastaccess),
                'timemodified' => transform::datetime($record->timecreated)
            ];
            return $carry;
        }, function($contextid, $data) {
            $context = \context::instance_by_id($contextid);
            $finaldata = (object) $data;
            writer::with_context($context)->export_data(['enrol_lti_cs_users'], $finaldata);
        });
    }

    /**
     * Delete all user data which matches the specified context.
     *
     * @param \context $context A user context.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if (!($context instanceof \context_course || $context instanceof \context_module)) {
            return;
        }

        $enrollticourseshelltools = $DB->get_fieldset_select('enrol_lti_cs_tools', 'id', 'contextid = :contextid',
            ['contextid' => $context->id]);
        if (!empty($enrollticourseshelltools)) {
            list($sql, $params) = $DB->get_in_or_equal($enrollticourseshelltools, SQL_PARAMS_NAMED);
            $DB->delete_records_select('enrol_lti_cs_users', 'toolid ' . $sql, $params);
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!($context instanceof \context_course || $context instanceof \context_module)) {
                continue;
            }

            $enrollticourseshelltools = $DB->get_fieldset_select('enrol_lti_cs_tools', 'id', 'contextid = :contextid',
                ['contextid' => $context->id]);
            if (!empty($enrollticourseshelltools)) {
                list($sql, $params) = $DB->get_in_or_equal($enrollticourseshelltools, SQL_PARAMS_NAMED);
                $params = array_merge($params, ['userid' => $userid]);
                $DB->delete_records_select('enrol_lti_cs_users', "toolid $sql AND userid = :userid", $params);
            }
        }
    }

    /**
     * Delete multicourseshellple users within a single context.
     *
     * @param   approved_userlist   $userlist   The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();

        if (!($context instanceof \context_course || $context instanceof \context_module)) {
            return;
        }

        $enrollticourseshelltools = $DB->get_fieldset_select('enrol_lti_cs_tools', 'id', 'contextid = :contextid',
                ['contextid' => $context->id]);
        if (!empty($enrollticourseshelltools)) {
            list($toolsql, $toolparams) = $DB->get_in_or_equal($enrollticourseshelltools, SQL_PARAMS_NAMED);
            $userids = $userlist->get_userids();
            list($usersql, $userparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
            $params = $toolparams + $userparams;
            $DB->delete_records_select('enrol_lti_cs_users', "toolid $toolsql AND userid $usersql", $params);
        }
    }

    /**
     * Loop and export from a recordset.
     *
     * @param \moodle_recordset $recordset The recordset.
     * @param string $splitkey The record key to determine when to export.
     * @param mixed $initial The initial data to reduce from.
     * @param callable $reducer The function to return the dataset, receives current dataset, and the current record.
     * @param callable $export The function to export the dataset, receives the last value from $splitkey and the dataset.
     * @return void
     */
    protected static function recordset_loop_and_export(\moodle_recordset $recordset, $splitkey, $initial,
            callable $reducer, callable $export) {
        $data = $initial;
        $lastid = null;

        foreach ($recordset as $record) {
            if ($lastid && $record->{$splitkey} != $lastid) {
                $export($lastid, $data);
                $data = $initial;
            }
            $data = $reducer($data, $record);
            $lastid = $record->{$splitkey};
        }
        $recordset->close();

        if (!empty($lastid)) {
            $export($lastid, $data);
        }
    }
}
