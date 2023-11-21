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
// Project implemented by the \"Recovery, Transformation and Resilience Plan.
// Funded by the European Union - Next GenerationEU\".
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

namespace local_notificationsagent;

defined('MOODLE_INTERNAL') || die();
require_once('notificationplugin.php');
require_once('notificationactionplugin.php');
require_once('notificationconditionplugin.php');

use notificationactionplugin;
use notificationconditionplugin;
use notificationplugin;
use local_notificationsagent\EvaluationContext;
use moodle_url;
use notificationsagent\notificationsagent;

class Rule {
    private $id;
    private $name;
    private $description;
    private $status = 0;
    private $createdby;
    private $createdat;
    private $shared = 1;
    private $defaultrule = 1;
    private $template = 1;
    private $forced = 1;
    private $assigned = 1;
    private $timesfired = 1;
    private $runtime = 86400;
    private $conditions;
    private $exceptions;
    private $actions;

    public const SEPARATOR = '______________________';

    private const PLACEHOLDERS = [
        'User_FirstName', 'User_LastName', 'User_Email', 'User_Username', 'User_Address', 'Course_FullName', 'Course_Url',
        'Teacher_FirstName', 'Teacher_LastName', 'Teacher_Email', 'Teacher_Username', 'Teacher_Address', 'Current_time',
        self::SEPARATOR,
    ];

    /** @var array Types of a rule */
    private const RULE_TYPES = [
        0 => 'template',
        1 => 'rule',
    ];
    /** @var int Template type identifier */
    public const TEMPLATE_TYPE = 0;
    /** @var int Rule type identifier */
    public const RULE_TYPE = 1;

    /** @var int Status of a rule that is enabled */
    public const RESUME_RULE = 0;
    /** @var int The status of a rule that is disabled */
    public const PAUSE_RULE = 1;
    /** @var int Status of a rule that is shared with the administrator */
    public const SHARED_RULE = 0;
    /** @var int The status of a rule that is not shared with the administrador */
    public const UNSHARED_RULE = 1;
    /** @var int Status of a rule that is forced */
    public const FORCED_RULE = 0;
    /** @var int The status of a rule that is not forced */
    public const NONFORCED_RULE = 1;
    /** @var int Minimum number of rule executions */
    public const MINIMUM_EXECUTION = 1;
    /** @var int Minimum days of rule execution */
    public const MINIMUM_RUNTIME = 1;

    /** Construct an empty Rule.
     *
     * @param $rule
     */
    public function __construct($id = null) {
        global $DB;

        if (!is_null($id)) {
            $rule = $DB->get_record('notificationsagent_rule', ['id' => $id]);
            $this->set_id($rule->id);
            $this->set_name($rule->name);
            $this->set_description($rule->description);
            $this->set_status($rule->status);
            $this->set_createdby($rule->createdby);
            $this->set_createdat($rule->createdat);
            $this->set_shared($rule->shared);
            $this->set_defaultrule($rule->defaultrule);
            $this->set_template($rule->template);
            $this->set_forced($rule->forced);
            $this->set_timesfired($rule->timesfired);
            $this->set_runtime($rule->runtime);
        }
    }

    private function to_record() {
        $record = [
            'id' => $this->get_id(),
            'name' => $this->get_name(),
            'description' => $this->get_description(),
            'status' => $this->get_status(),
            'createdby' => $this->get_createdby(),
            'createdat' => $this->get_createdat(),
            'shared' => $this->get_shared(),
            'defaultrule' => $this->get_defaultrule(),
            'template' => $this->get_template(),
            'forced' => $this->get_forced(),
            'timesfired' => $this->get_timesfired(),
            'runtime' => $this->get_runtime(),
        ];

        return (object) $record;
    }

    /**
     * Factory for loading a Rule from database .
     */
    public static function create_instance($id = null) {
        $rule = new Rule($id);
        if ($rule) {
            $rule->conditions = $rule->get_conditions();
            $rule->exceptions = $rule->get_exceptions();
            $rule->actions = $rule->get_actions();
        }

        return $rule;
    }

    /**
     * Get the rules
     * @param object $context context object
     * @param integer $courseid course id
     *
     * @return array $instances Rule object
     */
    public static function get_rules($context, $courseid) {
        $rules = [];
        $instances = [];

        if (has_capability('local/notificationsagent:managesiterule', $context)) {
            $rules = self::get_administrator_rules($courseid);
        } else if ($courseid != SITEID) {
            if (has_capability('local/notificationsagent:managecourserule', $context)) {
                $rules = self::get_teacher_rules($courseid);
            } else if (has_capability('local/notificationsagent:manageownrule', $context)) {
                $rules = self::get_student_rules($courseid);
            }
        }

        if (!empty($rules)) {
            foreach ($rules as $rule) {
                $instances[] = self::create_instance($rule->id);
            }
        }

        return $instances;
    }

    /**
     * @return mixed
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function get_name() {
        return $this->name;
    }

    /**
     * @param mixed $name
     */
    public function set_name($name): void {
        $this->name = $name;
    }

    /**
     * @return mixed
     */
    public function get_description() {
        return $this->description;
    }

    /**
     * @param mixed $description
     */
    public function set_description($description): void {
        $this->description = $description;
    }

    public function get_conditions() {
        global $DB;
        $this->conditions = notificationconditionplugin::create_subplugins($DB->get_records('notificationsagent_condition',
            ['ruleid' => $this->id, 'type' => 'condition', 'complementary' => 0]));
        return $this->conditions;
    }

    public function get_exceptions() {
        global $DB;
        $this->exceptions = notificationconditionplugin::create_subplugins($DB->get_records('notificationsagent_condition',
            ['ruleid' => $this->id, 'type' => 'condition', 'complementary' => 1]));
        return $this->exceptions;
    }

    public function get_actions() {
        global $DB;
        $this->actions = notificationactionplugin::create_subplugins($DB->get_records('notificationsagent_action',
            ['ruleid' => $this->id, 'type' => 'action']));
        return $this->actions;
    }

    public function delete_conditions($id) {
        global $DB;
        $this->conditions = $DB->delete_records('notificationsagent_condition', ['ruleid' => $id]);
            return $this->conditions;
    }

    public function delete_actions($id) {
        global $DB;
        $this->actions = $DB->delete_records('notificationsagent_action', ['ruleid' => $id]);
            return $this->actions;
    }

    /**
     * Delete all context records of the rule
     *
     * @return void
     */
    private function delete_context() {
        global $DB;

        $DB->delete_records('notificationsagent_context', ['ruleid' => $this->get_id()]);
    }

    /**
     * Delete all launched records of the rule
     *
     * @return void
     */
    private function delete_launched() {
        global $DB;

        $DB->delete_records('notificationsagent_launched', ['ruleid' => $this->get_id()]);
    }

    /**
     * @param mixed $conditions
     */
    public function set_conditions($conditions): void {
        $this->conditions = $conditions;
    }

    /**
     * @param mixed $exceptions
     */
    public function set_exceptions($exceptions): void {
        $this->exceptions = $exceptions;
    }

    /**
     * @param mixed $actions
     */
    public function set_actions($actions): void {
        $this->actions = $actions;
    }

    /**
     * @return mixed
     */
    public function get_assigned() {
        return $this->assigned;
    }

    /**
     * @param mixed $assigned
     */
    public function set_assigned($assigned): void {
        $this->assigned = $assigned;
    }

    /**
     * @return mixed
     */
    public function get_template() {
        return $this->template;
    }

    /**
     * @param mixed $template
     */
    public function set_template($template): void {
        $this->template = $template;
    }

    /**
     * @return mixed
     */
    public function get_status() {
        return $this->status;
    }

    /**
     * @param mixed $status
     */
    public function set_status($status): void {
        $this->status = $status;
    }

    /**
     * @param mixed $id
     */
    public function set_id($id): void {
        $this->id = $id;
    }

    /**
     * @return mixed
     */
    public function get_createdby() {
        return $this->createdby;
    }

    /**
     * @param mixed $createdby
     */
    public function set_createdby($createdby): void {
        $this->createdby = $createdby;
    }

    /**
     * @return mixed
     */
    public function get_createdat() {
        return $this->createdat;
    }

    /**
     * @param mixed $createdat
     */
    public function set_createdat($createdat): void {
        $this->createdat = $createdat;
    }

    /**
     * @return mixed
     */
    public function get_forced() {
        return $this->forced;
    }

    /**
     * @param mixed $forced
     */
    public function set_forced($forced): void {
        $this->forced = $forced;
    }

    /**
     * @return mixed
     */
    public function get_shared() {
        return $this->shared;
    }

    /**
     * @param mixed $forced
     */
    public function set_shared($shared): void {
        $this->shared = $shared;
    }

    /**
     * @return mixed
     */
    public function get_defaultrule() {
        return $this->defaultrule;
    }

    /**
     * @param mixed $defaultrule
     */
    public function set_defaultrule($defaultrule): void {
        $this->defaultrule = $defaultrule;
    }

    /**
     * @return mixed
     */
    public function get_timesfired() {
        return $this->timesfired;
    }

    /**
     * @param mixed $timesfired
     */
    public function set_timesfired($timesfired): void {
        $this->timesfired = $timesfired;
    }

    /**
     * @return mixed
     */
    public function get_runtime() {
        return $this->runtime;
    }

    /**
     * @param mixed $runtime
     */
    public function set_runtime($runtime): void {
        $this->runtime = $runtime;
    }

    public function evaluate(EvaluationContext $context): bool {
        // Evaluate conditions.
        $auxc = [];
        $auxe = [];
        foreach ($this->conditions as $condition) {
            $auxc[] = $condition;
            $context->set_params($condition->get_parameters());
            $context->set_complementary(false);
            $context->set_conditions($auxc);
            $result = $condition->evaluate($context);
            if ($result === false) {
                /* Las condiciones temporales poseen un método adicional que permite calcular y devolver
                la próxima fecha en la que se cumplirán estas condiciones. Esta fecha se almacena en la
                tabla de triggers para que pueda ser consultada en cualquier momento sin tener
                que recalcularla repetidamente, lo que mejora la eficiencia y rendimiento del
                sistema de notificaciones.
                */
                $timetrigger = $condition->estimate_next_time($context);
                // Keep record in trigger.
                // Event driven conditions return a null timetrigger.
                if (!empty($timetrigger)) {
                    notificationsagent::set_time_trigger($this->get_id(),
                        $context->get_userid(),
                        $context->get_courseid(),
                        $timetrigger);
                }
                return false;
            }
        }
        // Evaluate exceptions.
        foreach ($this->exceptions as $exception) {
            $auxe[] = $exception;
            $context->set_params($exception->get_parameters());
            $context->set_complementary(true);
            $context->set_exceptions($auxe);
            $result = $exception->evaluate($context);
            if ($result === true) {
                $timetrigger = $condition->estimate_next_time($context);
                // Keep record in trigger.
                // Event driven conditions return a null timetrigger.
                if (!empty($timetrigger)) {
                    notificationsagent::set_time_trigger(
                        $this->get_id(),
                        $context->get_userid(),
                        $context->get_courseid(),
                        $timetrigger
                    );
                }
                return false;
            }
        }

        notificationsagent::set_time_trigger($this->get_id(), $context->get_userid(), $context->get_courseid(),
        time() + $this->get_runtime());

        return true;
    }

    /**
     * Return list of placeholders.
     *
     * @return array
     */
    public static function get_placeholders(): array {
        return self::PLACEHOLDERS;
    }

    /**
     * Replace place holders in the template with respective content.
     *
     * @param string $template Message template.
     * @param subscription $subscription subscription instance
     * @param \stdclass $eventobj Event data
     * @param \context $context context object
     *
     * @return mixed final template string.
     */
    public function replace_placeholders($parameters, $courseid = null, $userid = null, $rule = null) {
        $paramstoreplace = [];
        $placeholderstoreplace = [];
        $placeholders = self::get_placeholders();
        $idcreatedby = $rule->get_createdby();

        if (!empty($userid)) {
            $user = \core_user::get_user($userid, '*', MUST_EXIST);
        }

        if (!empty($courseid)) {
            $course = get_course($courseid);
        }

        if (!empty($idcreatedby)) {
            $createdbyuser = \core_user::get_user($idcreatedby, '*', MUST_EXIST);
        }

        $jsonparams = json_decode($parameters);

        foreach ($jsonparams as $item) {
            foreach ($placeholders as $placeholder) {
                if (strpos($item, $placeholder) !== false) {
                    switch ($placeholder) {
                        case 'User_FirstName':
                            $paramstoreplace[] = $user->firstname;
                            $placeholderstoreplace[] = '{' . $placeholder . '}';

                        case 'User_LastName':
                            $paramstoreplace[] = $user->lastname;
                            $placeholderstoreplace[] = '{' . $placeholder . '}';

                        case 'User_Email':
                            $paramstoreplace[] = $user->email;
                            $placeholderstoreplace[] = '{' . $placeholder . '}';

                        case 'User_Username':
                            $paramstoreplace[] = $user->username;
                            $placeholderstoreplace[] = '{' . $placeholder . '}';

                        case 'User_Address':
                            $paramstoreplace[] = $user->address;
                            $placeholderstoreplace[] = '{' . $placeholder . '}';

                        case 'Course_FullName':
                            $paramstoreplace[] = $course->fullname;
                            $placeholderstoreplace[] = '{' . $placeholder . '}';

                        case 'Course_Url':
                            $paramstoreplace[] = new moodle_url('/course/view.php', [
                                'id' => $courseid,
                            ]);
                            $placeholderstoreplace[] = '{' . $placeholder . '}';

                        case 'Teacher_FirstName':
                            $paramstoreplace[] = $createdbyuser->firstname;
                            $placeholderstoreplace[] = '{' . $placeholder . '}';

                        case 'Teacher_LastName':
                            $paramstoreplace[] = $createdbyuser->lastname;
                            $placeholderstoreplace[] = '{' . $placeholder . '}';

                        case 'Teacher_Email':
                            $paramstoreplace[] = $createdbyuser->email;
                            $placeholderstoreplace[] = '{' . $placeholder . '}';

                        case 'Teacher_Username':
                            $paramstoreplace[] = $createdbyuser->username;
                            $placeholderstoreplace[] = '{' . $placeholder . '}';

                        case 'Teacher_Address':
                            $paramstoreplace[] = $createdbyuser->address;
                            $placeholderstoreplace[] = '{' . $placeholder . '}';

                        case 'Current_time':
                            $paramstoreplace[] = date('d-m-Y h:i:s', time());
                            $placeholderstoreplace[] = '{' . $placeholder . '}';
                    }
                }
            }
        }

        $humanvalue = str_replace($placeholderstoreplace, $paramstoreplace, $parameters);

        return $humanvalue;
    }

    /**
     * Hook to execute before deleting a rule
     *
     * @return void
     */
    public function before_delete() {
        $this->delete_launched();
        notificationsagent::delete_cache_by_ruleid($this->get_id());
        notificationsagent::delete_triggers_by_ruleid($this->get_id());
        $this->delete_conditions($this->get_id());
        $this->delete_actions($this->get_id());
        $this->delete_context();
    }

    /**
     * Delete rule entry from the database
     *
     * @return void
     */
    public function delete() {
        global $DB;

        self::before_delete();

        $DB->delete_records('notificationsagent_rule', ['id' => $this->get_id()]);
    }

    public function get_assignedcontext() {
        global $DB;

        $data = ['category' => [], 'course' => []];

        $results = $DB->get_records('notificationsagent_context', ['ruleid' => $this->id]);
        foreach ($results as $result) {
            if ($result->contextid == CONTEXT_COURSE) {
                $data['course'][] = $result->objectid;
            }
            if ($result->contextid == CONTEXT_COURSECAT) {
                $data['category'][] = $result->objectid;
            }
        }

        return $data;
    }

    /**
     * Create the entity rule
     * @param object $data Form data
     *
     * @return integer $id Rule id
     */
    public function create($data) {
        global $USER;

        $record = $this->to_record();
        $record->name = $data->title;
        $record->createdat = time();
        $record->createdby = $USER->id;
        $record->template = $data->type;

        if (isset($data->timesfired) && !empty($data->timesfired)) {
            $record->timesfired = $data->timesfired;
        }
        if (isset($data->runtime_group)) {
            $record->runtime = self::get_runtime_database_format($data->runtime_group);
        }

        $this->save($record);
        $this->set_default_context($data->courseid);

        return $this->get_id();
    }

    /**
     * Create rule entry in the database
     * @param object $record Rule object
     *
     * @return void
     */
    private function save($record) {
        global $DB;

        unset($record->id);
        $id = $DB->insert_record('notificationsagent_rule', $record);
        $this->set_id($id);
    }

    /**
     * Set the default context of a rule
     * @param integer $courseid course id
     *
     * @return void
     */
    public function set_default_context($courseid) {
        global $DB;

        $record = new \stdClass();
        $record->ruleid = $this->get_id();
        $record->contextid = CONTEXT_COURSE;
        $record->objectid = $courseid;

        $DB->insert_record('notificationsagent_context', $record);
    }

    /**
     * Get the main context of a rule
     *
     * @return integer $objectid Course ID
     */
    public function get_default_context() {
        global $DB;

        $data = $DB->get_records('notificationsagent_context',
            ['ruleid' => $this->get_id(), 'contextid' => CONTEXT_COURSE], '', 'objectid', 0, 1);

        return reset($data)->objectid;
    }

    /**
     * Update rule entry in the database
     * @param object $data Form data
     *
     * @return void
     */
    public function update($data) {
        global $DB;

        $this->set_name($data->title);

        if (!empty($data->timesfired)) {
            $this->set_timesfired($data->timesfired);
        } else {
            $this->set_timesfired(self::MINIMUM_EXECUTION);
        }
        $this->set_runtime(self::get_runtime_database_format($data->runtime_group));

        $record = new \stdClass();
        $record->id = $this->get_id();
        $record->name = $this->get_name();
        $record->timesfired = $this->get_timesfired();
        $record->runtime = $this->get_runtime();

        $DB->update_record('notificationsagent_rule', $record);
    }

    /**
     * Cloning one rule from another and converting it into a template
     *
     * @param integer $id Rule from which to clone
     * @return void
     */
    public function clone($id) {
        global $DB;

        $fromrule = self::create_instance($id);
        $request = new \stdClass();
        $request->id = $fromrule->get_id();
        $request->defaultrule = self::TEMPLATE_TYPE;
        $DB->update_record('notificationsagent_rule', $request);

        $record = new \stdClass();
        $record->title = $fromrule->get_name();
        $record->type = self::TEMPLATE_TYPE;
        $record->courseid = SITEID;

        $torule = self::create_instance();
        $torule->create($record);

        $torule->clone_conditions($fromrule->get_id());
        $torule->clone_actions($fromrule->get_id());
    }

    /**
     * Cloning the conditions of a rule to another rule
     *
     * @param integer $id Rule from which to clone
     * @return void
     */
    private function clone_conditions($id) {
        global $DB;

        $conditions = $DB->get_records('notificationsagent_condition',
            ['ruleid' => $id], '', 'pluginname, type, parameters, complementary'
        );
        foreach ($conditions as $condition) {
            $data = new \stdClass();
            $data->ruleid = $this->get_id();
            $data->pluginname = $condition->pluginname;
            $data->type = $condition->type;
            $data->parameters = $condition->parameters;
            $data->complementary = $condition->complementary;
            $DB->insert_record('notificationsagent_condition', $data);
        }
    }

    /**
     * Cloning the actions of a rule to another rule
     *
     * @param integer $id Rule from which to clone
     * @return void
     */
    private function clone_actions($id) {
        global $DB;

        $actions = $DB->get_records('notificationsagent_action',
            ['ruleid' => $id], '', 'pluginname, type, parameters'
        );
        foreach ($actions as $action) {
            $data = new \stdClass();
            $data->ruleid = $this->get_id();
            $data->pluginname = $action->pluginname;
            $data->type = $action->type;
            $data->parameters = $action->parameters;
            $DB->insert_record('notificationsagent_action', $data);
        }
    }

    /**
     * Get the administrator rules
     * @param integer $courseid Course id
     *
     * @return array $data rules
     */
    private static function get_administrator_rules($courseid) {
        $data = [];
        if ($courseid == SITEID) {
            $siterules = self::get_site_rules();
            $sharedrules = self::get_shared_rules();
            $data = array_unique(array_merge($siterules, $sharedrules), SORT_REGULAR);
        } else {
            $courserules = self::get_course_rules($courseid);
            $data = array_unique($courserules, SORT_REGULAR);
        }

        return $data;
    }

    /**
     * Get the rules created in a site context
     *
     * @return array $data rules
     */
    private static function get_site_rules() {
        global $DB;

        $data = [];

        $sql = 'SELECT DISTINCT nr.id
                  FROM {notificationsagent_rule} nr
                  JOIN {notificationsagent_context} nctx ON nr.id = nctx.ruleid
                   AND nctx.contextid = :coursecontextid AND nctx.objectid = :siteid
        ';
        $data = $DB->get_records_sql($sql, [
            'coursecontextid' => CONTEXT_COURSE,
            'siteid' => SITEID,
        ]);

        return $data;
    }

    /**
     * Get the shared rules
     *
     * @return array $data rules
     */
    private static function get_shared_rules() {
        global $DB;

        $data = [];

        $sql = 'SELECT DISTINCT nr.id
                  FROM {notificationsagent_rule} nr
                 WHERE nr.shared = 0
        ';
        $data = $DB->get_records_sql($sql);

        return $data;
    }

    /**
     * Get the rules related to a given course
     * @param integer $courseid Course id
     *
     * @return array $data rules
     */
    private static function get_course_rules($courseid) {
        global $DB;

        $data = [];

        $sql = 'SELECT nr.id
                  FROM {notificationsagent_rule} nr
                  JOIN {notificationsagent_context} nctx ON nr.id = nctx.ruleid
                   AND nctx.contextid = :coursecontextid
                 WHERE nctx.objectid = :coursecontext
                 UNION
                SELECT nr.id
                  FROM {notificationsagent_rule} nr
                  JOIN {notificationsagent_context} nctx ON nr.id = nctx.ruleid
                   AND nctx.contextid = :categorycontextid
                  JOIN {course_categories} cc ON nctx.objectid = cc.id
                  JOIN {course} c ON cc.id = c.category
                 WHERE c.id = :categorycontext
        ';

        $data = $DB->get_records_sql($sql, [
            'coursecontextid' => CONTEXT_COURSE,
            'coursecontext' => $courseid,
            'categorycontextid' => CONTEXT_COURSECAT,
            'categorycontext' => $courseid,
        ]);

        return $data;
    }

    /**
     * Get the teacher rules
     * @param integer $courseid Course id
     *
     * @return array $data rules
     */
    private static function get_teacher_rules($courseid) {
        $data = [];

        $data = self::get_course_rules($courseid);

        return $data;
    }

    /**
     * Get the student rules
     * @param integer $courseid Course id
     *
     * @return array $data rules
     */
    private static function get_student_rules($courseid) {
        global $DB, $USER;

        $data = [];

        $sql = 'SELECT nctx.id as ctxid, nr.id
                  FROM {notificationsagent_rule} nr
                  JOIN {notificationsagent_context} nctx ON nr.id = nctx.ruleid
                   AND nctx.contextid = :coursecontextid AND nctx.objectid = :objectid
                 WHERE nr.createdby = :createdby
        ';
        $data = $DB->get_records_sql($sql, [
            'coursecontextid' => CONTEXT_COURSE,
            'objectid' => $courseid,
            'createdby' => $USER->id,
        ]);

        return $data;
    }

    /**
     * Get if the record is of rule or template type
     *
     * @return string $data Rule or template
     */
    public function get_type() {
        return self::RULE_TYPES[$this->get_template()];
    }

    /**
     * Check if a rule is created from a template
     * @param integer $course Course ID
     * @param object $context context object
     *
     * @return boolean $data Is it using a template
     */
    public function is_use_template($courseid, $context) {
        return
            $courseid != SITEID
            && has_capability('local/notificationsagent:managecourserule', $context)
            && !$this->get_template();
    }

    /**
     * Check if a rule can be shared
     *
     * @return bool $data Is it the owner?
     */
    public function can_share() {
        global $USER;

        return $this->get_createdby() == $USER->id;
    }

    /**
     * Check if the rule has a context other than the default one
     *
     * @return bool $hascontext Is there any other context?
     */
    public function has_context() {
        global $DB;

        $hascontext = false;

        $sql = 'SELECT nctx.id as ctxid, nr.id
                  FROM {notificationsagent_rule} nr
                  JOIN {notificationsagent_context} nctx ON nr.id = nctx.ruleid
                   AND (nctx.contextid != :contextid OR nctx.objectid != :objectid)
                   AND nr.id = :id
        ';
        $data = $DB->get_records_sql($sql, [
            'contextid' => CONTEXT_COURSE,
            'objectid' => $this->get_default_context(),
            'id' => $this->get_id(),
        ]);

        if ($data) {
            $hascontext = true;
        }

        return $hascontext;
    }

    /**
     * Check if a rule can be deleted
     *
     * @return bool $hasdelete Is it the owner, or the administrator?
     */
    public function can_delete() {
        global $USER;
        $hasdelete = false;

        $context = \context_course::instance($this->get_default_context());
        if ($this->get_createdby() == $USER->id || has_capability('local/notificationsagent:managesiterule', $context)) {
            $hasdelete = true;
        }

        return $hasdelete;
    }

    /**
     * Returns the human format of a rule's runtime
     *
     * @return integer $data Time in days, hours and minutes
     */
    public function get_runtime_format() {
        return to_human_format($this->get_runtime());
    }

    /**
     * Returns the seconds of a rule's runtime
     * @param array $runtime Time in days, hours and minutes
     *
     * @return integer $data Seconds
     */
    private static function get_runtime_database_format($runtime) {
        $data = to_seconds_format(['days' => self::MINIMUM_RUNTIME]);

        $days = trim($runtime['runtime_days']);
        $hours = trim($runtime['runtime_hours']);
        $minutes = trim($runtime['runtime_minutes']);
        if (!empty($days)|| !empty($hours) || !empty($minutes)) {
            $data = to_seconds_format([
                'days' => $days,
                'hours' => $hours,
                'minutes' => $minutes,
            ]);
        }

        return $data;
    }

    /**
     * Store the number of times a rule has been executed in a specific context
     * @param object $context Evaluation Context
     *
     * @return int $timesfired Total user timesfired
     */
    public function set_launched($context) {
        global $DB;

        if ($record = $DB->get_record('notificationsagent_launched', ['ruleid' => $this->get_id(),
            'courseid' => $context->get_courseid(), 'userid' => $context->get_userid(), ])) {
            $record->timesfired++;
            $record->timemodified = time();
            $DB->update_record('notificationsagent_launched', $record);
        } else {
            $record = new \stdClass();
            $record->ruleid = $this->get_id();
            $record->courseid = $context->get_courseid();
            $record->userid = $context->get_userid();
            $record->timesfired = self::MINIMUM_EXECUTION;
            $record->timecreated = time();
            $record->timemodified = time();

            $DB->insert_record('notificationsagent_launched', $record);
        }

        return $record->timesfired;
    }

    /**
     * Returns the number of times the rule has been executed in a given context
     * @param object $context Evaluation Context
     *
     * @return object $record Timesfired of rule launched
     */
    public function get_launched($context) {
        global $DB;

        $record = $DB->get_record('notificationsagent_launched', [
            'ruleid' => $this->get_id(), 'courseid' => $context->get_courseid(),
            'userid' => $context->get_userid(), ], 'timesfired');

        return $record;
    }
}

