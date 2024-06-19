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
// Project implemented by the "Recovery, Transformation and Resilience Plan.
// Funded by the European Union - Next GenerationEU".
//
// Produced by the UNIMOODLE University Group: Universities of
// Valladolid, Complutense de Madrid, UPV/EHU, León, Salamanca,
// Illes Balears, Valencia, Rey Juan Carlos, La Laguna, Zaragoza, Málaga,
// Córdoba, Extremadura, Vigo, Las Palmas de Gran Canaria y Burgos.

/**
 * Version details
 *
 * @package    notificationscondition_ac
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     ISYC <soporte@isyc.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace notificationscondition_ac\task;

use local_notificationsagent\rule;
use local_notificationsagent\task\notificationsagent_trigger_cron;
use notificationscondition_ac\ac;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/../../../../../../lib/cronlib.php');

/**
 * Class for testing the ac_crontask task.
 *
 * @group notificationsagent
 */
class ac_crontask_test extends \advanced_testcase {
    /**
     * @var rule
     */
    private static $rule;
    /**
     * @var \stdClass
     */
    private static $user;
    /**
     * @var \stdClass
     */
    private static $course;
    /**
     * Date start for the course
     */
    public const COURSE_DATESTART = 1704099600; // 01/01/2024 10:00:00.
    /**
     * Date end for the course
     */
    public const COURSE_DATEEND = 1706605200; // 30/01/2024 10:00:00,
    /**
     * Activity date start
     */
    public const CM_DATESTART = 1704099600; // 01/01/2024 10:00:00,
    /**
     * Activity date end
     */
    public const CM_DATEEND = 1705741200; // 20/01/2024 10:00:00,
    /**
     * User first access to a course
     */
    public const USER_FIRSTACCESS = 1704099600;
    /**
     * User last access to a course
     */
    public const USER_LASTACCESS = 1704099600;

    final public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $rule = new rule();
        self::$rule = $rule;
        self::$user = self::getDataGenerator()->create_user(['firstname' => 'Fernando']);
        self::$course = self::getDataGenerator()->create_course(
            ([
                        'startdate' => self::COURSE_DATESTART,
                        'enddate' => self::COURSE_DATEEND,
                ])
        );
        self::getDataGenerator()->enrol_user(self::$user->id, self::$course->id);
    }

    /**
     *  Testing excute method from task.
     *
     * @covers       \notificationscondition_ac\task\ac_crontask::execute
     * @covers       \local_notificationsagent\helper\helper::custom_mtrace
     *
     */
    public function test_execute() {
        global $DB;

        $pluginname = ac::NAME;

        $dataform = new \StdClass();
        $dataform->title = "Rule Test";
        $dataform->type = 1;
        $dataform->courseid = self::$course->id;
        $dataform->timesfired = 2;
        $dataform->runtime_group = ['runtime_days' => 5, 'runtime_hours' => 0, 'runtime_minutes' => 0];
        self::setUser(2);// Admin.
        $ruleid = self::$rule->create($dataform);
        self::$rule->set_id($ruleid);

        $objdb = new \stdClass();
        $objdb->ruleid = self::$rule->get_id();
        $objdb->courseid = self::$course->id;
        $objdb->type = 'condition';
        $objdb->pluginname = $pluginname;
        $json =
                '{"op":"&","c":[{"op":"&","c":[{"type":"profile","sf":"firstname","op":"isequalto","v":"Fernando"}]},
                {"op":"!|","c":[]}],"showc":[true,true],"errors":["availability:error_list_nochildren"]}';
        $objdb->parameters = $json;
        $objdb->cmid = null;

        // Insert.
        $conditionid = $DB->insert_record('notificationsagent_condition', $objdb);
        $this->assertIsInt($conditionid);
        self::$rule::create_instance($ruleid);

        $task = \core\task\manager::get_scheduled_task(ac_crontask::class);
        $task->execute();
        $trigger = $DB->get_record(
            'notificationsagent_triggers',
            [
                        'conditionid' => $conditionid,
                        'userid' => self::$user->id,
                        'courseid' => self::$course->id,
                        'ruleid' => self::$rule->get_id(),
                ]
        );

        $this->assertEquals(self::$course->id, $trigger->courseid);
        $this->assertEquals(self::$user->id, $trigger->userid);
        $this->assertEquals(self::$rule->get_id(), $trigger->ruleid);
    }

    /**
     * Get name test
     *
     * @covers \notificationscondition_ac\task\ac_crontask::get_name
     * @return void
     */
    public function test_get_name() {
        $task = \core\task\manager::get_scheduled_task(ac_crontask::class);

        $this->assertIsString($task->get_name());
    }
}
