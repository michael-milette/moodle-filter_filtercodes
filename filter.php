<?php
// This file is part of FilterCodes for Moodle - http://moodle.org/
//
// FilterCodes is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// FilterCodes is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Main filter code for FilterCodes.
 *
 * @package    filter_filtercodes
 * @copyright  2017 TNG Consulting Inc. - www.tngcosulting.ca
 * @author     Michael Milette
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Extends the moodle_text_filter class to provide plain text support for new tags.
 *
 * @copyright  2017 TNG Consulting Inc. - www.tngconsulting.ca
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filter_filtercodes extends moodle_text_filter {
    // Moodle roles.
    /** Manager role id. */
    const MANAGER  = 1;
    /** Manager role id. */
    const COURSECREATOR  = 2;
    /** Course creator role id. */
    const EDITINGTEACHER  = 3;
    /** Editing teacher role id. */
    const NONEDITINGTEACHER  = 4;
    /** Non-editing teacher role id. */
    const STUDENT = 5;

    /**
     * Determine if the user has a role.
     *
     * @param integer $roleid   ID of role (1 to 5).
     * @return boolean  Does: true, Does not: false.
     */
    private function hasrole($roleid) {
        // If not logged in or is just a guestuser, definitely doesn't have a role.
        if (!isloggedin() || isguestuser()) {
            return false;
        }

        static $isrole = array();
        // If result is not cached.
        if (!isset($isrole[$roleid])) {
            global $DB, $USER, $PAGE;
            if (is_role_switched($PAGE->course->id)) { // Has switched roles.
                $context = context_course::instance($PAGE->course->id);
                if ($role = $DB->get_record('role', array('id' => $USER->access['rsw'][$context->path]))) {
                    $isrole[$roleid] = ($role->sortorder == $roleid);
                } else {
                    $isrole[$roleid] = false;
                }
            } else {
                $isrole[$roleid] = user_has_role_assignment($USER->id, $roleid, $PAGE->context->id);
            }
        }
        return $isrole[$roleid];
    }

    /**
     * Determine if the user only has a specified role and no others.
     * Example: Can be a student but not also be a teacher or manager.
     *
     * @param integer $roleid   ID of role (1 to 5).
     * @return boolean  Does: true, Does not: false.
     */
    private function hasonlyrole($roleid) {
        if ($this->hasrole($roleid)) {
            for ($role = $this::MANAGER; $role <= $this::STUDENT; $role++) {
                if ($role != $roleid && $this->hasrole($role)) {
                    return false;
                }
            }
            global $PAGE;
            if (is_role_switched($PAGE->course->id)) {
                // Ignore site admin status if we have switched roles.
                return true;
            } else {
                return is_siteadmin();
            }
        }
        return false;
    }

    /**
     * Determine if the user has the specified role or one with elevated capabilities.
     * Example: Can be a teacher, course creator, manager or Administrator but not a student.
     *
     * @param integer $roleid   ID of role (1 to 5).
     * @return boolean  Does: true, Does not: false.
     */
    private function hasminimumrole($roleid) {
        for ($role = $roleid; $role >= $this::MANAGER; $role--) {
            if ($this->hasrole($role)) {
                return true;
            }
        }
        global $PAGE;
        return (!is_role_switched($PAGE->course->id) && is_siteadmin());
    }

    /**
     * Retrieves the URL for the user's profile picture, if one is available.
     *
     * @param object $user.
     * @return string $url This is the url to the photo image file but with $1 for the size.
     */
    private function getprofilepictureurl($user) {
        if (isloggedin() && $user->picture > 0) {
            $usercontext = context_user::instance($user->id, IGNORE_MISSING);
            $url = moodle_url::make_pluginfile_url($usercontext->id, 'user', 'icon', NULL, '/', "f$1") . '?rev=' . $user->picture;
        } else {
            global $PAGE;
            $renderer = $PAGE->get_renderer('core');
            $url = $renderer->pix_url('u/f$1'); // Default image.
        }
        return str_replace('/f%24', '/f$', $url);
    }

    /**
     * Generates HTML code for a recaptcha.
     *
     * @return string  HTML Code for recaptcha.
     */
    private function getrecaptcha() {
        // Is recaptcha configured in moodle?
        global $CFG;
        if (empty($CFG->recaptchaprivatekey) XOR empty($CFG->recaptchapublickey)) {
            echo get_string('missingrecaptchachallengefield');
            return null;
        }
        if (!empty($CFG->recaptchaprivatekey) && !empty($CFG->recaptchapublickey)) {
            require_once($CFG->libdir . '/recaptchalib.php');
            return recaptcha_get_html($CFG->recaptchapublickey);
        }
    }

    /**
     * Main filter function called by Moodle.
     *
     * @param string $text   Content to be filtered.
     * @param array $options Moodle filter options. None are implemented in this plugin.
     * @return string Content with filters applied.
     */
    public function filter($text, array $options = array()) {
        global $CFG, $SITE, $PAGE, $USER;

        if (strpos($text, '{') === false) {
            return $text;
        }

        $replace = []; // Array of key/value filterobjects.

        // Substitutions.

        if (isloggedin()) {
            $firstname = $USER->firstname;
            $lastname = $USER->lastname;
        } else {
            $firstname = get_string('defaultfirstname', 'filter_filtercodes');
            $lastname = get_string('defaultsurname', 'filter_filtercodes');
        }

        // Tag: {firstname}.
        if (stripos($text, '{firstname}') !== false) {
            $replace['/\{firstname\}/i'] = $firstname;
        }

        // Tag: {surname}.
        if (stripos($text, '{surname}') !== false) {
            $replace['/\{surname\}/i'] = $lastname;
        }

        // Tag: {fullname}.
        if (stripos($text, '{fullname}') !== false) {
            $replace['/\{fullname\}/i'] = trim($firstname . ' ' . $lastname);
        }

        // Tag: {alternatename}.
        if (stripos($text, '{alternatename}') !== false) {
            // If alternate name is empty, use firstname instead.
            if (isloggedin() && !empty(trim($USER->alternatename))) {
                $replace['/\{alternatename\}/i'] = $USER->alternatename;
            } else {
                $replace['/\{alternatename\}/i'] = $firstname;
            }
        }

        // Tag: {username}.
        if (stripos($text, '{username}') !== false) {
            $replace['/\{username\}/i'] = isloggedin() ? $USER->username : get_string('defaultusername', 'filter_filtercodes');
        }

        // Tag: {email}.
        if (stripos($text, '{email}') !== false) {
            $replace['/\{email\}/i'] = isloggedin() ? $USER->email : '';
        }

        // Tag: {city}.
        if (stripos($text, '{city}') !== false) {
            $replace['/\{city\}/i'] = isloggedin() ? $USER->city : '';
        }

        // Tag: {country}.
        if (stripos($text, '{country}') !== false) {
            $replace['/\{country\}/i'] = isloggedin() ? get_string($USER->country, 'countries') : '';
        }

        // Tag: {institution}.
        if (stripos($text, '{institution}') !== false) {
            $replace['/\{institution\}/i'] = isloggedin() ? $USER->institution : '';
        }

        // Tag: {department}.
        if (stripos($text, '{department}') !== false) {
            $replace['/\{department\}/i'] = isloggedin() ? $USER->department : '';
        }

        // Tag: {userid}.
        if (stripos($text, '{userid}') !== false) {
            $replace['/\{userid\}/i'] = $USER->id;
        }

        if (stripos($text, '{userpicture') !== false) {
            // Tag: {userpictureurl size}. User photo URL.
            // Sizes: 2 or sm (small), 1 or md (medium), 3 or lg (large).
            if (stripos($text, '{userpictureurl ') !== false) {
                $url = $this->getprofilepictureurl($USER);
                // Substitute the $1 in URL with value of (\w+), making sure to substitute text versions into numbers.
                $newtext = preg_replace_callback('/\{userpictureurl\s+(\w+)\}/i',
                    function ($matches) {
                        $sublist = array('sm' => '2', '2' => '2', 'md' => '1', '1' => '1', 'lg' => '3', '3' => '3');
                        return '{userpictureurl ' . $sublist[$matches[1]] . '}';
                    }, $text);
                if ($newtext !== false) {
                    $text = $newtext;
                }
                $replace['/\{userpictureurl\s+(\w+)\}/i'] = $url;
            }

            // Tag: {userpictureimg size}. User photo URL wrapped in HTML image tag.
            // Sizes: 2 or sm (small), 1 or md (medium), 3 or lg (large).
            if (stripos($text, '{userpictureimg ') !== false) {
                $url = $this->getprofilepictureurl($USER);
                $tag = '<img src="' . $url . '" alt="' . $firstname . ' ' . $lastname . '" class="userpicture">';
                // Will substitute the $1 in URL with value of (\w+).
                $newtext = preg_replace_callback('/\{userpictureimg\s+(\w+)\}/i',
                    function ($matches) {
                        $sublist = array('sm' => '2', '2' => '2', 'md' => '1', '1' => '1', 'lg' => '3', '3' => '3');
                        return '{userpictureimg ' . $sublist[$matches[1]] . '}';
                    }, $text);
                if ($newtext !== false) {
                    $text = $newtext;
                }
                $replace['/\{userpictureimg\s+(\w+)\}/i'] = $tag;
            }
        }

        // Tag: {courseid}.
        if (stripos($text, '{courseid}') !== false) {
            $replace['/\{courseid\}/i'] = $PAGE->course->id;
        }

        // Tag: {coursename}. The name of this course.
        if (stripos($text, '{coursename}') !== false) {
            $course = $PAGE->course;
            if ($course->id == $SITE->id) { // Front page - use site name.
                $replace['/\{coursename\}/i'] = format_string($SITE->fullname);
            } else { // In a course - use course full name.
                $coursecontext = context_course::instance($course->id);
                $replace['/\{coursename\}/i'] = format_string($course->fullname, true, array('context' => $coursecontext));
            }
        }

        // Tag: {mycourses} and {mycoursesmenu}.
        if (stripos($text, '{mycourses') !== false) {
            if (isloggedin() && !isguestuser()) {
                // Retrieve list of user's enrolled courses.
                $sortorder = 'visible DESC';
                // Prevent undefined $CFG->navsortmycoursessort errors.
                if (empty($CFG->navsortmycoursessort)) {
                    $CFG->navsortmycoursessort = 'sortorder';
                }
                // Append the chosen sortorder.
                $sortorder = $sortorder . ',' . $CFG->navsortmycoursessort . ' ASC';
                $mycourses = enrol_get_my_courses('fullname,id', $sortorder);

                // Tag: {mycourses}. An unordered list of links to enrolled course.
                if (stripos($text, '{mycourses}') !== false) {
                    $list = '';
                    foreach ($mycourses as $mycourse) {
                        $list .= '<li><a href="' . (new moodle_url('/course/view.php', array('id'=>$mycourse->id))) . '">' .
                                $mycourse->fullname . '</a></li>';
                    }
                    if (empty($list)) {
                        $list .= '<li>' . get_string('notenrolled', 'grades') . '</li>';
                    }
                    $replace['/\{mycourses\}/i'] = '<ul class="mycourseslist">' . $list . '</ul>';
                }
                // Tag: {mycoursesmenu}. A custom menu list of enrolled course names with links.
                if (stripos($text, '{mycoursesmenu}') !== false) {
                    $list = '';
                    foreach ($mycourses as $mycourse) {
                        $list .= '-' . $mycourse->fullname . '|' . 
                            (new moodle_url('/course/view.php', array('id'=>$mycourse->id))) . PHP_EOL;
                    }
                    if (empty($list)) {
                        $list .= '-' . get_string('notenrolled', 'grades') . PHP_EOL;
                    }
                    $replace['/\{mycoursesmenu\}/i'] = $list;
                }
                unset($list);
                unset($mycourses);
            }
            $replace['/\{mycourses\}/i'] = '';
            $replace['/\{mycoursesmenu\}/i'] = '';
        }

        // Tag: {referer}.
        if (stripos($text, '{referer}') !== false) {
            $replace['/\{referer\}/i'] = get_local_referer(false);
        }

        // Tag: {wwwroot}.
        if (stripos($text, '{wwwroot}') !== false) {
            $replace['/\{wwwroot\}/i'] = $CFG->wwwroot;
        }

        // Tag: {protocol}.
        if (stripos($text, '{protocol}') !== false) {
            $replace['/\{protocol\}/i'] = 'http'.(is_https() ? 's' : '');
        }

        // Tag: {ipaddress}.
        if (stripos($text, '{ipaddress}') !== false) {
            $replace['/\{ipaddress\}/i'] = getremoteaddr();
        }

        // Tag: {recaptcha}.
        if (stripos($text, '{recaptcha}') !== false) {
            $replace['/\{recaptcha\}/i'] = $this->getrecaptcha();
        }

        // HTML tagging.

        // Tag: {nbsp}.
        if (stripos($text, '{nbsp}') !== false) {
            $replace['/\{nbsp\}/i'] = '&nbsp;';
        }
        // Tag: {langx xx}.
        if (stripos($text, '{langx ') !== false) {
            $replace['/\{langx\s+(\w+)\}(.*)\{\/langx\}/i'] = '<span lang="$1">$2</span>';
        }

        // Conditional block tags.

        if (strpos($text, '{if') !== false) { // If there are conditional tags.

            // Tags: {ifenrolled} and {ifnotenrolled}.
            if ($PAGE->course->id == $SITE->id) { // If frontpage course.
                // Everyone is automatically enrolled in the Front Page course.
                // Remove the ifenrolled tags.
                if (stripos($text, '{ifenrolled}') !== false) {
                    $replace['/\{ifenrolled\}/i'] = '';
                    $replace['/\{\/ifenrolled\}/i'] = '';
                }
                // Remove the ifnotenrolled strings.
                if (stripos($text, '{ifnotenrolled}') !== false) {
                    $replace['/\{ifnotenrolled\}(.*)\{\/ifnotenrolled\}/i'] = '';
                }
            } else {
                if ($this->hasrole($this::STUDENT)) { // If user is enrolled in the course.
                    // If enrolled, remove the ifenrolled tags.
                    if (stripos($text, '{ifenrolled}') !== false) {
                        $replace['/\{ifenrolled\}/i'] = '';
                        $replace['/\{\/ifenrolled\}/i'] = '';
                    }
                    // Remove the ifnotenrolled strings.
                    if (stripos($text, '{ifnotenrolled}') !== false) {
                        $replace['/\{ifnotenrolled\}(.*)\{\/ifnotenrolled\}/i'] = '';
                    }
                } else {
                    // Otherwise, remove the ifenrolled strings.
                    if (stripos($text, '{ifenrolled}') !== false) {
                        $replace['/\{ifenrolled\}(.*)\{\/ifenrolled\}/i'] = '';
                    }
                    // And remove the ifnotenrolled tags.
                    if (stripos($text, '{ifnotenrolled}') !== false) {
                        $replace['/\{ifnotenrolled\}/i'] = '';
                        $replace['/\{\/ifnotenrolled\}/i'] = '';
                    }
                }
            }
            // Tag: {ifstudent}. This is similar to {ifenrolled} but only displays if user is enrolled
            // but must be logged-in and must not have no additional higher level roles as well.
            // Example: Student but not Administrator, or Student but not Teacher.
            if ($this->hasonlyrole($this::STUDENT)) {
                if (stripos($text, '{ifstudent}') !== false) {
                    // Just remove the tags.
                    $replace['/\{ifstudent\}/i'] = '';
                    $replace['/\{\/ifstudent\}/i'] = '';
                }
            } else {
                // And remove the ifstudent strings.
                if (stripos($text, '{ifstudent}') !== false) {
                    $replace['/\{ifstudent\}(.*)\{\/ifstudent\}/i'] = '';
                }
            }
            // Tags: {ifloggedin} and {ifloggedout}.
            if (isloggedin() && !isguestuser()) { // If logged-in but not just as guest.
                // Just remove ifloggedin tags.
                if (stripos($text, '{ifloggedin}') !== false) {
                    $replace['/\{ifloggedin\}/i'] = '';
                    $replace['/\{\/ifloggedin\}/i'] = '';
                }
                // Remove the ifloggedout strings.
                if (stripos($text, '{ifloggedout}') !== false) {
                    $replace['/\{ifloggedout\}(.*)\{\/ifloggedout\}/i'] = '';
                }
            } else { // If logged-out.
                // Remove the ifloggedout tags.
                if (stripos($text, '{ifloggedout}') !== false) {
                    $replace['/\{ifloggedout\}/i'] = '';
                    $replace['/\{\/ifloggedout\}/i'] = '';
                }
                // Remove ifloggedin strings.
                if (stripos($text, '{ifloggedin}') !== false) {
                    $replace['/\{ifloggedin\}(.*)\{\/ifloggedin\}/i'] = '';
                }
            }

            // Tag: {ifguest}.
            if (stripos($text, '{ifguest}') !== false) {
                if (isguestuser()) { // If logged-in as guest.
                    // Just remove the tags.
                    $replace['/\{ifguest\}/i'] = '';
                    $replace['/\{\/ifguest\}/i'] = '';
                } else {
                    // If not logged-in as guest, remove the ifguest text.
                    $replace['/\{ifguest}(.*)\{\/ifguest\}/i'] = '';
                }
            }

            // Tag: {ifassistant}.
            if (stripos($text, '{ifassistant}') !== false) {
                // If an assistant (non-editing teacher).
                if ($this->hasrole($this::NONEDITINGTEACHER) && stripos($text, '{ifassistant}') !== false) {
                    // Just remove the tags.
                    $replace['/\{ifassistant\}/i'] = '';
                    $replace['/\{\/ifassistant\}/i'] = '';
                } else {
                    // Remove the ifassistant strings.
                    $replace['/\{ifassistant\}(.*)\{\/ifassistant\}/i'] = '';
                }
            }

            // Tag: {ifteacher}.
            if (stripos($text, '{ifteacher}') !== false) {
                if ($this->hasrole($this::EDITINGTEACHER)) { // If a teacher.
                    // Just remove the tags.
                    $replace['/\{ifteacher\}/i'] = '';
                    $replace['/\{\/ifteacher\}/i'] = '';
                } else {
                    // Remove the ifteacher strings.
                    $replace['/\{ifteacher\}(.*)\{\/ifteacher\}/i'] = '';
                }
            }

            // Tag: {ifcreator}.
            if (stripos($text, '{ifcreator}') !== false) {
                if ($this->hasrole($this::COURSECREATOR)) { // If a course creator.
                    // Just remove the tags.
                    $replace['/\{ifcreator\}/i'] = '';
                    $replace['/\{\/ifcreator\}/i'] = '';
                } else {
                    // Remove the iscreator strings.
                    $replace['/\{ifcreator\}(.*)\{\/ifcreator\}/i'] = '';
                }
            }

            // Tag: {ifmanager}.
            if (stripos($text, '{ifmanager}') !== false) {
                if ($this->hasrole($this::MANAGER)) { // If a manager.
                    // Just remove the tags.
                    $replace['/\{ifmanager\}/i'] = '';
                    $replace['/\{\/ifmanager\}/i'] = '';
                } else {
                    // Remove the ifmanager strings.
                    $replace['/\{ifmanager\}(.*)\{\/ifmanager\}/i'] = '';
                }
            }

            // Tag: {ifadmin}.
            global $PAGE;
            if (stripos($text, '{ifadmin}') !== false) {
                if (is_siteadmin() && !is_role_switched($PAGE->course->id)) { // If an administrator.
                    // Just remove the tags.
                    $replace['/\{ifadmin\}/i'] = '';
                    $replace['/\{\/ifadmin\}/i'] = '';
                } else {
                    // Remove the ifadmin strings.
                    $replace['/\{ifadmin\}(.*)\{\/ifadmin\}/i'] = '';
                }
            }

            if (strpos($text, '{ifmin') !== false) { // If there are conditional ifmin tags.

                // Tag: {ifminassistant}.
                if (stripos($text, '{ifminassistant}') !== false) {
                    // If an assistant (non-editing teacher) or above.
                    if ($this->hasminimumrole($this::NONEDITINGTEACHER) && stripos($text, '{ifminassistant}') !== false) {
                        // Just remove the tags.
                        $replace['/\{ifminassistant\}/i'] = '';
                        $replace['/\{\/ifminassistant\}/i'] = '';
                    } else {
                        // Remove the ifminassistant strings.
                        $replace['/\{ifminassistant\}(.*)\{\/ifminassistant\}/i'] = '';
                    }
                }

                // Tag: {ifminteacher}.
                if (stripos($text, '{ifminteacher}') !== false) {
                    if ($this->hasminimumrole($this::EDITINGTEACHER)) { // If a teacher or above.
                        // Just remove the tags.
                        $replace['/\{ifminteacher\}/i'] = '';
                        $replace['/\{\/ifminteacher\}/i'] = '';
                    } else {
                        // Remove the ifminteacher strings.
                        $replace['/\{ifminteacher\}(.*)\{\/ifminteacher\}/i'] = '';
                    }
                }

                // Tag: {ifmincreator}.
                if (stripos($text, '{ifmincreator}') !== false) {
                    if ($this->hasminimumrole($this::COURSECREATOR)) { // If a course creator or above.
                        // Just remove the tags.
                        $replace['/\{ifmincreator\}/i'] = '';
                        $replace['/\{\/ifmincreator\}/i'] = '';
                    } else {
                        // Remove the iscreator strings.
                        $replace['/\{ifmincreator\}(.*)\{\/ifmincreator\}/i'] = '';
                    }
                }

                // Tag: {ifminmanager}.
                if (stripos($text, '{ifminmanager}') !== false) {
                    if ($this->hasminimumrole($this::MANAGER)) { // If a manager or above.
                        // Just remove the tags.
                        $replace['/\{ifminmanager\}/i'] = '';
                        $replace['/\{\/ifminmanager\}/i'] = '';
                    } else {
                        // Remove the ifminmanager strings.
                        $replace['/\{ifminmanager\}(.*)\{\/ifminmanager\}/i'] = '';
                    }
                }

            }

        }

        // Apply all of the filtercodes.
        if (count($replace) > 0) {
            $newtext = preg_replace(array_keys($replace), array_values($replace), $text);
        } else {
            $newtext = null;
        }

        // Return original text if an error occurred during regex processing.
        if (is_null($newtext)) {
            return $text;
        } else {
            return $newtext;
        }

    }
}
