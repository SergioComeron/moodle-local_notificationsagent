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
 * notificationsagent content.php description here.
 *
 * @package    notificationsagent
 * @copyright  2023 ISYC
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content {

    public function renderform(&$mform, $idcourse, $type, $exception = null) {
        global $SESSION, $CFG;
        if ($exception !== null && isset($SESSION->NOTIFICATIONS[$exception])) {
            $this->renderformbyexception($mform, $idcourse, $type, $exception);
        } else if ($exception == null && isset($SESSION->NOTIFICATIONS[$type])) {
            $this->renderformbytype($mform, $idcourse, $type);
        }
    }

    public function renderformbytype(&$mform, $idcourse, $type) {
        global $SESSION, $CFG;
        
            $items = $SESSION->NOTIFICATIONS[$type];
            $id = 1;
        foreach ($items as $key => $value) {
                $remove = "<i class='btn icon fa fa-trash align-top' id='".$type . $key . "_remove'></i>";
                $buttons = $remove;
                $title = '<h5>' . $id . ') ' . $value['title'] . $buttons . '</h5>';
                $mform->addElement('html', $title);
                $pluginname[$id] = $value['name'];
                $this->get_plugin_ui($mform, $id, $idcourse,$pluginname[$id], $type, $exception =null);
                $id++;
            }
    }
    public function renderformbyexception(&$mform, $idcourse, $type, $exception) {
        global $SESSION, $CFG;

            $items = $SESSION->NOTIFICATIONS[$exception];
            $id = 1;
        foreach ($items as $key => $value) {
                $remove = "<i class='btn icon fa fa-trash align-top' id='".$type.$exception . $key . "_remove'></i>";
                $buttons = $remove;
                $title = '<h5>' . $id . ') ' . $value['title'] . $buttons . '</h5>';
                $mform->addElement('html', $title);
                $pluginname[$id] = $value['name'];
                $this->get_plugin_ui($mform, $id, $idcourse, $pluginname[$id], $type, $exception);
                $id++;
        }
    }

    private function get_plugin_ui($mform, $id, $idcourse, $pluginname, $subtype, $exception){
        global  $CFG;
        $rule = new \stdClass();
        $rule->ruleid = null;
        require_once($CFG->dirroot . '/local/notificationsagent/' . $subtype . '/' . $pluginname . '/' . $pluginname . '.php');
        $pluginclass = 'notificationsagent_' . $subtype . '_' . $pluginname;
        $pluginobj = new $pluginclass($rule);
        $pluginobj->get_ui($mform, $id, $idcourse, $exception);
    }

}
