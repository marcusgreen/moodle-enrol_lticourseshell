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

use enrol_lticourseshell\local\ltiadvantage\entity\application_registration;
use enrol_lticourseshell\local\ltiadvantage\repository\application_registration_repository;
use enrol_lticourseshell\local\ltiadvantage\repository\deployment_repository;

/**
 * lticourseshell Enrolment test data generator class.
 *
 * @package enrol_lticourseshell
 * @category test
 * @copyright 2021 Jake Dallimore <jrhdallimore@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_lti_cs_generator extends component_generator_base {

    /**
     * Test method to generate an application registration (and optionally a deployment) for a platform.
     *
     * @param array $data the application registration data, with optional deployment data.
     * @return application_registration
     */
    public function create_application_registration(array $data): application_registration {
        $bytes = random_bytes(30);
        $uniqueid = bin2hex($bytes);
        if (empty($data['platformid']) || empty($data['clientid']) || empty($data['authrequesturl']) || empty($data['jwksurl']) ||
                empty($data['accesstokenurl'])) {
            $registration = application_registration::create_draft(
                $data['name'],
                $uniqueid
            );
        } else {
            $registration = application_registration::create(
                $data['name'],
                $uniqueid,
                new moodle_url($data['platformid']),
                $data['clientid'],
                new moodle_url($data['authrequesturl']),
                new moodle_url($data['jwksurl']),
                new moodle_url($data['accesstokenurl'])
            );
        }

        $appregrepo = new application_registration_repository();
        $createdregistration = $appregrepo->save($registration);

        if (isset($data['deploymentname']) && isset($data['deploymentid'])) {
            $deployment = $createdregistration->add_tool_deployment($data['deploymentname'], $data['deploymentid']);
            $deploymentrepo = new deployment_repository();
            $deploymentrepo->save($deployment);
        }

        return $createdregistration;
    }

    /**
     * Test method to generate a published resource for a course.
     *
     * @param array $data the data required to publish the resource.
     * @return stdClass the enrol_lti_cs_tools record, representing the published resource.
     */
    public function create_published_resource(array $data): stdClass {

        if (!empty($data['ltiversion']) && !in_array($data['ltiversion'], ['LTI-1p3', 'lticourseshell-1p0/lticourseshell-2p0'])) {
            throw new coding_exception("The field 'ltiversion' must be either 'LTI-1p3' or 'lticourseshell-1p0/lticourseshell-2p0'.");
        }

        $instancedata = (object) [
            'name' => $data['name'],
            'courseid' => $data['courseid'],
            'cmid' => $data['activityid'],
            'ltiversion' => $data['ltiversion'] ?? 'LTI-1p3'
        ];
        $tool = $this->datagenerator->create_lticourseshell_tool($instancedata);

        if (empty($data['uuid'])) {
            return $tool;
        }

        // Allow tests to create predictable uuids.
        global $DB;
        $DB->set_field('enrol_lti_cs_tools', 'uuid', $data['uuid']);
        return enrol_lticourseshell\helper::get_lticourseshell_tool($tool->id);
    }
}
