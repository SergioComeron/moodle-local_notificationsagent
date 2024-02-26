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
 * @package    local_notificationsagent
 * @copyright  2023 Proyecto UNIMOODLE
 * @author     UNIMOODLE Group (Coordinator) <direccion.area.estrategia.digital@uva.es>
 * @author     ISYC <soporte@isyc.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace notificationscondition_activityopen;

use local_notificationsagent\evaluationcontext;
use local_notificationsagent\form\editrule_form;
use local_notificationsagent\notificationplugin;
use local_notificationsagent\helper\test\phpunitutil;
use local_notificationsagent\rule;
use notificationscondition_activityopen\activityopen;

/**
 * @group notificationsagent
 */
class activityopen_test extends \advanced_testcase {

    private static $rule;
    private static $subplugin;
    private static $coursetest;
    private static $subtype;
    private static $user;
    private static $context;
    private static $coursecontext;
    private static $elements;
    private static $cmtestao;
    public const CONDITIONID = 1;
    public const CMID = 246000;
    public const COURSE_DATESTART = 1704099600; // 01/01/2024 10:00:00.
    public const COURSE_DATEEND = 1706605200; // 30/01/2024 10:00:00,

    public const CM_DATESTART = 1704099600; // 01/01/2024 10:00:00,
    public const CM_DATEEND = 1705741200; // 20/01/2024 10:00:00,

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        self::$rule = new rule();

        self::$subplugin = new activityopen(self::$rule);
        self::$subplugin->set_id(self::CONDITIONID);
        self::$coursetest = self::getDataGenerator()->create_course(
            ['startdate' => self::COURSE_DATESTART, 'enddate' => self::COURSE_DATEEND]
        );
        self::$coursecontext = \context_course::instance(self::$coursetest->id);
        self::$user = self::getDataGenerator()->create_user();
        self::$context = new evaluationcontext();
        self::$context->set_userid(self::$user->id);
        self::$context->set_courseid(self::$coursetest->id);
        self::$subtype = 'activityopen';
        self::$elements = ['[TTTT]', '[AAAA]'];
        $quizgenerator = self::getDataGenerator()->get_plugin_generator('mod_quiz');
        self::$cmtestao = $quizgenerator->create_instance([
            'course' => self::$coursetest->id,
            "timeopen" => self::CM_DATESTART,
            "timeclose" => self::CM_DATEEND,
        ]);
    }

    /**
     *
     * @param int  $timeaccess
     * @param int  $param
     * @param bool $expected
     *
     * @covers       \notificationscondition_activityopen\activityopen::evaluate
     *
     * @dataProvider dataprovider
     * @throws \coding_exception
     */
    public function test_evaluate($timeaccess, $usecache, $param, $complementary, $expected) {
        self::$context->set_timeaccess($timeaccess);
        self::$context->set_complementary($complementary);

        $cm = get_coursemodule_from_instance('quiz', self::$cmtestao->id, self::$coursetest->id);
        self::$context->set_params(json_encode(['time' => $param, 'cmid' => $cm->id]));

        if ($usecache) {
            global $DB;
            $objdb = new \stdClass();
            $objdb->userid = self::$user->id;
            $objdb->courseid = self::$coursetest->id;
            $objdb->timestart = self::$cmtestao->timeopen + $param;
            $objdb->pluginname = self::$subtype;
            $objdb->conditionid = self::CONDITIONID;
            // Insert.
            $DB->insert_record('notificationsagent_cache', $objdb);
        }

        // Test evaluate.
        $result = self::$subplugin->evaluate(self::$context);
        $this->assertSame($expected, $result);
        // Test estimate next time.

    }

    public static function dataprovider(): array {
        return [
            [1704445200, false, 864000, notificationplugin::COMPLEMENTARY_CONDITION, false],
            [1704445200, true, 864000, notificationplugin::COMPLEMENTARY_EXCEPTION, false],
            [1705050000, false, 864000, notificationplugin::COMPLEMENTARY_EXCEPTION, true],
            [1705050000, true, 864000, notificationplugin::COMPLEMENTARY_CONDITION, true],

        ];
    }

    /**
     * @covers \notificationscondition_activityopen\activityopen::get_subtype
     */
    public function test_getsubtype() {
        $this->assertSame(self::$subtype, self::$subplugin->get_subtype());
    }

    /**
     * @covers \notificationscondition_activityopen\activityopen::is_generic
     */
    public function test_isgeneric() {
        $this->assertTrue(self::$subplugin->is_generic());
    }

    /**
     * @covers \notificationscondition_activityopen\activityopen::get_elements
     */
    public function test_getelements() {
        $this->assertSame(self::$elements, self::$subplugin->get_elements());
    }

    /**
     * @covers \notificationscondition_activityopen\activityopen::check_capability
     */
    public function test_checkcapability() {
        $this->assertSame(
            has_capability('local/notificationsagent:' . self::$subtype, self::$coursecontext),
            self::$subplugin->check_capability(self::$coursecontext)
        );
    }

    /**
     * @covers       \notificationscondition_activityopen\activityopen::estimate_next_time
     * @dataProvider dataprovider
     */
    public function test_estimatenexttime($timeaccess, $usecache, $param, $complementary, $expected) {
        self::$context->set_complementary($complementary);
        $cm = get_coursemodule_from_instance('quiz', self::$cmtestao->id, self::$coursetest->id);
        self::$context->set_params(json_encode(['time' => $param, 'cmid' => $cm->id]));
        // Test estimate next time.
        if (self::$context->is_complementary()) {
            self::assertNull(self::$subplugin->estimate_next_time(self::$context));
        } else {
            self::assertSame(self::CM_DATESTART + $param, self::$subplugin->estimate_next_time(self::$context));
        }
    }

    /**
     * @covers \notificationscondition_activityopen\activityopen::get_title
     */
    public function test_gettitle() {
        $this->assertNotNull(self::$subplugin->get_title());
        foreach (self::$elements as $element) {
            $this->assertStringContainsString($element, self::$subplugin->get_title());
        }
    }

    /**
     * @covers \notificationscondition_activityopen\activityopen::get_description
     */
    public function test_getdescription() {
        $this->assertSame(
            self::$subplugin->get_description(),
            [
                'title' => self::$subplugin->get_title(),
                'name' => self::$subplugin->get_subtype(),
            ]
        );
    }

    /**
     * @covers \notificationscondition_activityopen\activityopen::convert_parameters
     */
    public function test_convertparameters() {
        $params = [
            "5_activityopen_days" => "1",
            "5_activityopen_hours" => "0",
            "5_activityopen_minutes" => "0",
            "5_activityopen_seconds" => "1",
            "5_activityopen_cmid" => "7",
        ];
        $expected = '{"time":86401,"cmid":7}';
        $method = phpunitutil::get_method(self::$subplugin, 'convert_parameters');
        $result = $method->invoke(self::$subplugin, 5, $params);
        $this->assertSame($expected, $result);
    }

    /**
     * @covers \notificationscondition_activityopen\activityopen::process_markups
     */
    public function test_processmarkups() {
        $quizgenerator = self::getDataGenerator()->get_plugin_generator('mod_quiz');
        $cmgen = $quizgenerator->create_instance([
            'course' => self::$coursetest->id,
        ]);
        $time = '86401';
        $params[self::$subplugin::UI_TIME] = $time;
        $params[self::$subplugin::UI_ACTIVITY] = $cmgen->cmid;
        $params = json_encode($params);
        $expected = str_replace(self::$subplugin->get_elements(), [to_human_format($time, true), $cmgen->name],
            self::$subplugin->get_title());
        self::$subplugin->set_parameters($params);
        $content = [];
        self::$subplugin->process_markups($content, self::$coursetest->id);
        $this->assertSame([$expected], $content);
    }

    /**
     * @covers \notificationscondition_activityopen\activityopen::get_ui
     */
    public function test_getui() {
        $courseid = self::$coursetest->id;
        $ruletype = rule::RULE_TYPE;
        $typeaction = "add";
        $customdata = [
            'ruleid' => self::$rule->get_id(),
            notificationplugin::TYPE_CONDITION => self::$rule->get_conditions(),
            notificationplugin::TYPE_EXCEPTION => self::$rule->get_exceptions(),
            notificationplugin::TYPE_ACTION => self::$rule->get_actions(),
            'type' => $ruletype,
            'timesfired' => rule::MINIMUM_EXECUTION,
            'courseid' => $courseid,
            'getaction' => $typeaction,
        ];

        $form = new editrule_form(new \moodle_url('/'), $customdata);
        $form->definition();
        $form->definition_after_data();
        $mform = phpunitutil::get_property($form, '_form');
        $id = time();
        $subtype = notificationplugin::TYPE_CONDITION;
        self::$subplugin->get_ui($mform, $id, $courseid, $subtype);

        $method = phpunitutil::get_method(self::$subplugin, 'get_name_ui');
        $uiactivityname = $method->invoke(self::$subplugin, $id, self::$subplugin::UI_ACTIVITY);
        $uigroupname = $method->invoke(self::$subplugin, $id, self::$subplugin->get_subtype());
        $uigroupelements = [];
        foreach ($mform->getElement($uigroupname)->getElements() as $element) {
            $uigroupelements[] = $element->getName();
        }
        $uidays = $method->invoke(self::$subplugin, $id, self::$subplugin::UI_DAYS);
        $uihours = $method->invoke(self::$subplugin, $id, self::$subplugin::UI_HOURS);
        $uiminutes = $method->invoke(self::$subplugin, $id, self::$subplugin::UI_MINUTES);
        $uiseconds = $method->invoke(self::$subplugin, $id, self::$subplugin::UI_SECONDS);

        $this->assertTrue($mform->elementExists($uiactivityname));
        $this->assertTrue($mform->elementExists($uigroupname));
        $this->assertTrue(in_array($uidays, $uigroupelements));
        $this->assertTrue(in_array($uihours, $uigroupelements));
        $this->assertTrue(in_array($uiminutes, $uigroupelements));
        $this->assertTrue(in_array($uiseconds, $uigroupelements));
    }

    /**
     * @covers \notificationscondition_activityopen\activityopen::set_default
     */
    public function test_setdefault() {
        $courseid = self::$coursetest->id;
        $ruletype = rule::RULE_TYPE;
        $typeaction = "add";
        $customdata = [
            'ruleid' => self::$rule->get_id(),
            notificationplugin::TYPE_CONDITION => self::$rule->get_conditions(),
            notificationplugin::TYPE_EXCEPTION => self::$rule->get_exceptions(),
            notificationplugin::TYPE_ACTION => self::$rule->get_actions(),
            'type' => $ruletype,
            'timesfired' => rule::MINIMUM_EXECUTION,
            'courseid' => $courseid,
            'getaction' => $typeaction,
        ];

        $form = new editrule_form(new \moodle_url('/'), $customdata);
        $form->definition();
        $addjson = phpunitutil::get_method($form, 'addJson');
        $addjson->invoke($form, notificationplugin::TYPE_CONDITION, self::$subplugin::NAME);
        $form->definition_after_data();

        $mform = phpunitutil::get_property($form, '_form');
        $jsoncondition = $mform->getElementValue(editrule_form::FORM_JSON_CONDITION);
        $arraycondition = array_keys(json_decode($jsoncondition, true));
        $id = $arraycondition[0];

        $method = phpunitutil::get_method(self::$subplugin, 'get_name_ui');
        $uigroupname = $method->invoke(self::$subplugin, $id, self::$subplugin->get_subtype());
        $defaulttime = [];
        foreach ($mform->getElement($uigroupname)->getElements() as $element) {
            $defaulttime[$element->getName()] = $element->getValue();
        }

        $uidays = $method->invoke(self::$subplugin, $id, self::$subplugin::UI_DAYS);
        $uihours = $method->invoke(self::$subplugin, $id, self::$subplugin::UI_HOURS);
        $uiminutes = $method->invoke(self::$subplugin, $id, self::$subplugin::UI_MINUTES);
        $uiseconds = $method->invoke(self::$subplugin, $id, self::$subplugin::UI_SECONDS);

        $this->assertTrue(isset($defaulttime[$uidays]) && $defaulttime[$uidays] == self::$subplugin::UI_DAYS_DEFAULT_VALUE);
        $this->assertTrue(isset($defaulttime[$uihours]) && $defaulttime[$uihours] == self::$subplugin::UI_HOURS_DEFAULT_VALUE);
        $this->assertTrue(
            isset($defaulttime[$uiminutes]) && $defaulttime[$uiminutes] == self::$subplugin::UI_MINUTES_DEFAULT_VALUE
        );
        $this->assertTrue(
            isset($defaulttime[$uiseconds]) && $defaulttime[$uiseconds] == self::$subplugin::UI_SECONDS_DEFAULT_VALUE
        );
    }
}

