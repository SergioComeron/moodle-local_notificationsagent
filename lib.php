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
function custom_mtrace($message) {
    $tracelog = get_config('notificationsagent', 'tracelog');
    if ($tracelog) {
        mtrace($message);
    }
}

/**
 * @throws coding_exception
 * @throws moodle_exception
 */
function local_notificationsagent_extend_navigation_course(navigation_node $parentnode, stdClass $course, context_course $context) {
    if (get_config('local_notificationsagent', 'disable_user_use')) {
        if (!has_capability('local/notificationsagent:managecourserule', $context)) {
            return;
        }
    }

    $menuentrytext = get_string('menu', 'local_notificationsagent');
    $courseid = $course->id;
    $url = '/local/notificationsagent/index.php?courseid='.$courseid;
    $parentnode->add(
        $menuentrytext,
        new moodle_url($url),
        navigation_node::TYPE_SETTING,
        null,
        "notificationsagent"
    );
}

/**
 * Retrieve data for modal window
 *
 * @param $category
 * @param $ruleid
 *
 * @return array
 */

function build_category_array($category, $ruleid) {
    global $DB;
    $courses = $category->get_courses();
    $count = $category->coursecount;
    $coursesarry = [];
    foreach ($courses as $course) {
        $coursesarry[] = [
            'id' => $course->id,
            'name' => format_text($course->fullname),
        ];
    }

    $categoryarray = [
        'id' => $category->id,
        'name' => format_text($category->name),
        'categories' => [],
        'courses' => $coursesarry,
        'count' => $count,
    ];

    $categoryarray['countsubcategoriescourses'] = count_category_courses($category);

    $subcategories = $category->get_children();
    foreach ($subcategories as $subcategory) {
        $hascourses = count_category_courses($subcategory);
        if ($hascourses > 0) {
            $subcategoryarray = build_category_array($subcategory, $ruleid);
            $categoryarray['categories'][] = $subcategoryarray;
        }
    }

    return $categoryarray;
}

/**
 * Count courses under category parent
 *
 * @param $category
 *
 * @return array
 */

function count_category_courses($category) {
    $countcategorycourses = $category->coursecount;

    $subcategories = $category->get_children();
    foreach ($subcategories as $subcategory) {
        $countsuncategorycourses = count_category_courses($subcategory);
        $countcategorycourses += $countsuncategorycourses;
    }
    return $countcategorycourses;
}


/**
 * Retrieve output for modal window
 *
 * @param $arraycategories
 *
 * @return array
 */

function build_output_categories($arraycategories, $categoryid = 0) {
    $output = "";
    foreach ($arraycategories as $key => $category) {
        $output .= html_writer::start_tag("li", [
            "id" => "listitem-category-" . $category["id"],
            "class" => "listitem listitem-category list-group-item list-group-item-action collapsed",
        ]);
        $output .= html_writer::start_div("", ["class" => "category-listing-header d-flex"]);
        $output .= html_writer::start_div("", ["class" => "custom-control custom-checkbox mr-1"]);
        $output .= html_writer::tag("input", "", [
            "id" => "checkboxcategory-" . $category["id"],
            "type" => "checkbox", "class" => "custom-control-input",
            "data-parent" => "#category-listing-content-" . $categoryid,
        ]);
        $output .= html_writer::tag(
            "label", "",
            ["class" => "custom-control-label", "for" => "checkboxcategory-" . $category["id"]]
        );
        $output .= html_writer::end_div();// ... .custom-checkbox
        $output .= html_writer::start_div("", [
            "class" => "d-flex px-0", "data-toggle" => "collapse",
            "data-target" => "#category-listing-content-" . $category["id"],
            "aria-controls" => "category-listing-content-" . $category["id"],
        ]);
        $output .= html_writer::start_div("", ["class" => "categoryname d-flex align-items-center"]);
        $output .= $category["name"];
        $output .= html_writer::tag("i", "", ["class" => "fa fa-angle-down ml-2"]);
        $output .= html_writer::end_div();// ....categoryname
        $output .= html_writer::end_div();// ... .data-toggle
        $output .= html_writer::start_div("", ["class" => "ml-auto px-0"]);
        $output .= html_writer::start_tag("span", ["class" => "course-count text-muted"]);
        $output .= $category["countsubcategoriescourses"];
        $output .= html_writer::tag("i", "", ["class" => "fa fa-graduation-cap fa-fw ml-2"]);
        $output .= html_writer::end_tag("span");// ... .course-count
        $output .= html_writer::end_div();// ... .col-auto
        $output .= html_writer::end_div();// ... .d-flex
        $output .= html_writer::start_tag("ul", [
            "id" => "category-listing-content-" . $category["id"],
            "class" => "collapse", "data-parent" => "#category-listing-content-" . $categoryid,
        ]);
        if (!empty($category["categories"])) {
                    $output .= build_output_categories($category["categories"], $category["id"]);
        }
        if (!empty($category["courses"])) {
            foreach ($category["courses"] as $key => $course) {
                $output .= html_writer::start_tag("li", [
                    "id" => "listitem-course-" . $course["id"],
                    "class" => "listitem listitem-course list-group-item list-group-item-action",
                ]);
                $output .= html_writer::start_div("", ["class" => "d-flex"]);
                $output .= html_writer::start_div("", ["class" => "custom-control custom-checkbox mr-1"]);
                $output .= html_writer::tag(
                    "input", "",
                    [
                        "id" => "checkboxcourse-" . $course["id"],
                        "type" => "checkbox", "class" => "custom-control-input",
                        "data-parent" => "#category-listing-content-" . $category["id"],
                    ]
                );
                $output .= html_writer::tag(
                    "label", "",
                    ["class" => "custom-control-label", "for" => "checkboxcourse-" . $course["id"]]
                );
                $output .= html_writer::end_div();// ... .custom-checkbox
                $output .= html_writer::start_div("", ["class" => "coursename"]);
                $output .= $course["name"];
                $output .= html_writer::end_div();// ... .coursename
                $output .= html_writer::end_div();// ... .d-flex
                $output .= html_writer::end_tag("li");
                        // ... .listitem.listitem-course.list-group-item.list-group-item-action
            }
        }
        $output .= html_writer::end_tag("ul");// ... #category-listing-content-x
        $output .= html_writer::end_tag("li");// ... .listitem.listitem-category.list-group-item
    }
    return $output;
}

function get_rulesbytimeinterval($timestarted, $tasklastrunttime) {
    global $DB;
    $rulesidquery = "
                    SELECT id, ruleid, courseid, userid
                      FROM {notificationsagent_triggers}
                     WHERE startdate
                   BETWEEN :tasklastrunttime AND :timestarted
                     ";

    $rulesid = $DB->get_records_sql(
        $rulesidquery,
        [
            'tasklastrunttime' => $tasklastrunttime,
            'timestarted' => $timestarted,
        ]
    );
    return $rulesid;
}

/**
 * Returns seconds in human format
 *
 * @param integer $seconds Seconds
 * @return array $data Time in days, hours, minutes and seconds
 */
function to_human_format($seconds) {
    $secondsinaminute = 60;
    $secondsinhour = 60 * $secondsinaminute;
    $secondsinday = 24 * $secondsinhour;

    $days = floor($seconds / $secondsinday);

    $hourseconds = $seconds % $secondsinday;
    $hours = floor($hourseconds / $secondsinhour);

    $minuteseconds = $hourseconds % $secondsinhour;
    $minutes = floor($minuteseconds / $secondsinaminute);

    $remainingseconds = $minuteseconds % $secondsinaminute;
    $seconds = ceil($remainingseconds);

    return [
        'days' => $days,
        'hours' => $hours,
        'minutes' => $minutes,
        'seconds' => $seconds,
    ];
}

/**
 * Returns human format in seconds
 * @param array $time Time in days, hours, minutes and seconds
 *
 * @return integer $seconds Seconds
 */
function to_seconds_format($time) {
    $seconds = 0;

    if (isset($time['days']) && $time['days'] != "") {
        $seconds = $time['days'] * 24 * 60 * 60;
    }
    if (isset($time['hours']) && $time['hours'] != "") {
        $seconds += $time['hours'] * 60 * 60;
    }
    if (isset($time['minutes']) && $time['minutes'] != "") {
        $seconds += $time['minutes'] * 60;
    }
    if (isset($time['seconds']) && $time['seconds'] != "") {
        $seconds += $time['seconds'];
    }

    return $seconds;
}



