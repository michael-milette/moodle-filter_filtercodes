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
 * @copyright  2017-2018 TNG Consulting Inc. - www.tngcosulting.ca
 * @author     Michael Milette
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Extends the moodle_text_filter class to provide plain text support for new tags.
 *
 * @copyright  2017-2018 TNG Consulting Inc. - www.tngconsulting.ca
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filter_filtercodes extends moodle_text_filter {
    /** @var object $archetypes Object array of Moodle archetypes. */
    public $archetypes = array();

    /**
     * Constructor: Get the role IDs associated with each of the archetypes.
     */
    public function __construct() {
        global $DB;

        // Note: This array must correspond to the one in function hasminarchetype().
        $archetypelist = array('manager' => 1, 'coursecreator' => 2, 'editingteacher' => 3, 'teacher' => 4, 'student' => 5);
        foreach ($archetypelist as $archetype => $level) {
            $roleids = array();
            // Build array of roles.
            foreach ($roles = get_archetype_roles($archetype) as $role) {
                $roleids[] = $role->id;
            }
            $this->archetypes[$archetype] = (object) ['level' => $level, 'roleids' => $roleids];
        }
    }

    /**
     * Determine if any of the user's roles includes specified archetype.
     *
     * @param string $archetype Name of archetype.
     * @return boolean  Does: true, Does not: false.
     */
    private function hasarchetype($archetype) {
        // If not logged in or is just a guestuser, definitely doesn't have the archetype we want.
        if (!isloggedin() || isguestuser()) {
            return false;
        }

        // Handle caching of results.
        static $archetypes = array();
        if (isset($archetypes[$archetype])) {
            return $archetypes[$archetype];
        }

        global $USER, $PAGE;
        $archetypes[$archetype] = false;
        if (is_role_switched($PAGE->course->id)) { // Has switched roles.
            $context = context_course::instance($PAGE->course->id);
            $id = $USER->access['rsw'][$context->path];
            $archetypes[$archetype] = in_array($id, $this->archetypes[$archetype]->roleids);
        } else {
            // For each of the roles associated with the archetype, check if the user has one of the roles.
            foreach ($this->archetypes[$archetype]->roleids as $roleid) {
                if (user_has_role_assignment($USER->id, $roleid, $PAGE->context->id)) {
                    $archetypes[$archetype] = true;
                }
            }
        }
        return $archetypes[$archetype];
    }

    /**
     * Determine if the user only has a specified archetype amongst the user's role and no others.
     * Example: Can be a student but not also be a teacher or manager.
     *
     * @param string $archetype Name of archetype.
     * @return boolean Does: true, Does not: false.
     */
    private function hasonlyarchetype($archetype) {
        if ($this->hasarchetype($archetype)) {
            foreach ($this->archetypes as $archetypename => $properties) {
                if ($archetypename != $archetype && $this->hasarchetype($archetypename)) {
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
     * Determine if the user has the specified archetype or one with elevated capabilities.
     * Example: Can be a teacher, course creator, manager or Administrator but not a student.
     *
     * @param string $minarchetype Name of archetype.
     * @return boolean User meets minimum archetype requirement: true, does not: false.
     */
    private function hasminarchetype($minarchetype) {
        // Note: This array must start with one blank entry followed by the same list found in in __construct().
        $archetypelist = array('', 'manager', 'coursecreator', 'editingteacher', 'teacher', 'student');
        // For each archetype level between the one specified and 'manager'.
        for ($level = $this->archetypes[$minarchetype]->level; $level >= 1; $level--) {
            // Check to see if any of the user's roles correspond to the archetype.
            if ($this->hasarchetype($archetypelist[$level])) {
                return true;
            }
        }
        // Return true regardless of the archetype if we are an administrator and not in a switched role.
        global $PAGE;
        return !is_role_switched($PAGE->course->id) && is_siteadmin();
    }

    /**
     * Retrieves the URL for the user's profile picture, if one is available.
     *
     * @param object $user The Moodle user object for which we want a photo.
     * @return string URL to the photo image file but with $1 for the size.
     */
    private function getprofilepictureurl($user) {
        if (isloggedin() && $user->picture > 0) {
            $usercontext = context_user::instance($user->id, IGNORE_MISSING);
            $url = moodle_url::make_pluginfile_url($usercontext->id, 'user', 'icon', null, '/', "f$1") . '?rev=' . $user->picture;
        } else {
            // If the user does not have a profile picture, use the default faceless picture.
            global $PAGE, $CFG;
            $renderer = $PAGE->get_renderer('core');
            if ($CFG->branch >= 33) {
                $url = $renderer->image_url('u/f$1');
            } else {
                $url = $renderer->pix_url('u/f$1'); // Deprecated as of Moodle 3.3.
            }
        }
        return str_replace('/f%24', '/f$', $url);
    }

    /**
     * Determine if running on http or https. Same as Moodle's is_https() except that it is backwards compatible to Moodle 2.7.
     *
     * @return boolean true if protocol is https, false if http.
     */
    private function ishttps() {
        global $CFG;
        if ($CFG->branch >= 28) {
            $ishttps = is_https(); // Available as of Moodle 2.8.
        } else {
            $ishttps = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443);
        }
        return $ishttps;
    }

    /**
     * Generates HTML code for a reCAPTCHA.
     *
     * @return string HTML Code for reCAPTCHA or blank if logged-in or Moodle reCAPTCHA is not configured.
     */
    private function getrecaptcha() {
        global $CFG;
        // Is user not logged-in or logged-in as guest?
        if (!isloggedin() || isguestuser()) {
            // Is Moodle reCAPTCHA configured?
            if (!empty($CFG->recaptchaprivatekey) && !empty($CFG->recaptchapublickey)) {
                // Yes? Generate reCAPTCHA.
                if (file_exists($CFG->libdir . '/recaptchalib_v2.php')) {
                    // For reCAPTCHA 2.0.
                    require_once($CFG->libdir . '/recaptchalib_v2.php');
                    return recaptcha_get_challenge_html(RECAPTCHA_API_URL, $CFG->recaptchapublickey);
                } else {
                    // For reCAPTCHA 1.0.
                    require_once($CFG->libdir . '/recaptchalib.php');
                    return recaptcha_get_html($CFG->recaptchapublickey, null, $this->ishttps());
                }
            } else if ($CFG->debugdisplay == 1) { // If debugging is set to DEVELOPER...
                // Show indicator that {reCAPTCHA} tag is not required.
                return 'Warning: The reCAPTCHA tag is not required here.';
            }
        }
        // Logged-in as non-guest user (reCAPTCHA is not required) or Moodle reCAPTCHA not configured.
        // Don't generate reCAPTCHA.
        return '';
    }
    /**
     * Main filter function called by Moodle.
     *
     * @param string $text   Content to be filtered.
     * @param array $options Moodle filter options. None are implemented in this plugin.
     * @return string Content with filters applied.
     */
    public function filter($text, array $options = array()) {
        global $CFG, $SITE, $PAGE, $USER, $DB;

        if (strpos($text, '{') === false && strpos($text, '%7B') === false) {
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
            $replace['/\{country\}/i'] = isloggedin() && !empty($USER->country) ? get_string($USER->country, 'countries') : '';
        }

        // Tag: {institution}.
        if (stripos($text, '{institution}') !== false) {
            $replace['/\{institution\}/i'] = isloggedin() ? $USER->institution : '';
        }

        // Tag: {department}.
        if (stripos($text, '{department}') !== false) {
            $replace['/\{department\}/i'] = isloggedin() ? $USER->department : '';
        }

        // Any {user*} tags.
        if (stripos($text, '{user') !== false) {

            // Tag: {username}.
            if (stripos($text, '{username}') !== false) {
                $replace['/\{username\}/i'] = isloggedin() ? $USER->username : get_string('defaultusername', 'filter_filtercodes');
            }

            // Tag: {userid}.
            if (stripos($text, '{userid}') !== false) {
                $replace['/\{userid\}/i'] = $USER->id;
            }
            // Alternative Tag: %7Buserid%7D (for encoded URLs).
            if (stripos($text, '%7Buserid%7D') !== false) {
                $replace['/%7Buserid%7D/i'] = $USER->id;
            }

            // Tags: {userpictureurl} and {userpictureimg}.
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
        }

        // Any {course*} or %7Bcourse*%7D tags.
        if (stripos($text, '{course') !== false || stripos($text, '%7Bcourse') !== false) {
            // Tag: {courseid}.
            if (stripos($text, '{courseid}') !== false) {
                $replace['/\{courseid\}/i'] = $PAGE->course->id;
            }
            // Alternative Tag: %7Bcourseid%7D (for encoded URLs).
            if (stripos($text, '%7Bcourseid%7D') !== false) {
                $replace['/%7Bcourseid%7D/i'] = $PAGE->course->id;
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

            // Tag: {coursestartdate}. The name of this course.
            if (stripos($text, '{coursestartdate}') !== false) {
                if (empty($PAGE->course->startdate)) {
                    $PAGE->course->startdate = $DB->get_field_select('course', 'startdate', 'id = :id', array('id' => $course->id));
                }
                if ($PAGE->course->startdate > 0) {
                    $replace['/\{coursestartdate\}/i'] = userdate($PAGE->course->startdate, get_string('strftimedatefullshort'));
                } else {
                    $replace['/\{coursestartdate\}/i'] = get_string('none');
                }
            }

            // Tag: {courseenddate}. The name of this course.
            if (stripos($text, '{courseenddate}') !== false) {
                if (empty($PAGE->course->enddate)) {
                    $PAGE->course->enddate = $DB->get_field_select('course', 'enddate', 'id = :id', array('id' => $course->id));
                }
                if ($PAGE->course->enddate > 0) {
                    $replace['/\{courseenddate\}/i'] = userdate($PAGE->course->enddate, get_string('strftimedatefullshort'));
                } else {
                    $replace['/\{courseenddate\}/i'] = get_string('none');
                }
            }

            // Tag: {coursecompletiondate}. The name of this course.
            if (stripos($text, '{coursecompletiondate}') !== false) {
                if ($PAGE->course
                        && isset($CFG->enablecompletion)
                        && $CFG->enablecompletion == COMPLETION_ENABLED
                        && $PAGE->course->enablecompletion) {
                    $ccompletion = new completion_completion(array('userid' => $USER->id, 'course' => $PAGE->course->id));
                    if ($ccompletion->timecompleted) {
                        $replace['/\{coursecompletiondate\}/i'] = userdate($ccompletion->timecompleted,
                                get_string('strftimedatefullshort'));
                    } else {
                        $replace['/\{coursecompletiondate\}/i'] = get_string('notcompleted', 'completion');
                    }
                } else {
                    $replace['/\{coursecompletiondate\}/i'] = get_string('completionnotenabled', 'completion');
                }
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
                        $list .= '<li><a href="' . (new moodle_url('/course/view.php', array('id' => $mycourse->id))) . '">' .
                                $mycourse->fullname . '</a></li>';
                    }
                    if (empty($list)) {
                        $list .= '<li>' . get_string(($CFG->branch >= 29 ? 'notenrolled' : 'nocourses'), 'grades') . '</li>';
                    }
                    $replace['/\{mycourses\}/i'] = '<ul class="mycourseslist">' . $list . '</ul>';
                }

                // Tag: {mycoursesmenu}. A custom menu list of enrolled course names with links.
                if (stripos($text, '{mycoursesmenu}') !== false) {
                    $list = '';
                    foreach ($mycourses as $mycourse) {
                        $list .= '-' . $mycourse->fullname . '|' .
                            (new moodle_url('/course/view.php', array('id' => $mycourse->id))) . PHP_EOL;
                    }
                    if (empty($list)) {
                        $list .= '-' . get_string(($CFG->branch >= 29 ? 'notenrolled' : 'nocourses'), 'grades') . PHP_EOL;
                    }
                    $replace['/\{mycoursesmenu\}/i'] = $list;
                }
                unset($list);
                unset($mycourses);

            } else { // Not logged in.
                // Replace tags with message indicating that you need to be logged in.
                $replace['/\{mycourses\}/i'] = '<ul class="mycourseslist"><li>' . get_string('loggedinnot') . '</li></ul>';
                $replace['/\{mycoursesmenu\}/i'] = '-' . get_string('loggedinnot') . PHP_EOL;
            }
        }

        // Tag: {categories} and {categoriesmenu}.
        if (stripos($text, '{categories') !== false) {

            // Retrieve list of top categories.
            require_once($CFG->libdir. '/coursecatlib.php');
            $categories = coursecat::make_categories_list();

            // Tag: {categories}. An unordered list of links to enrolled course.
            if (stripos($text, '{categories}') !== false) {
                $list = '';
                foreach ($categories as $id => $name) {
                    $list .= '<li><a href="' .
                            (new moodle_url('/course/index.php', array('categoryid' => $id))) . '">' . $name . '</a></li>';
                }
                $replace['/\{categories\}/i'] = '<ul class="categorylist">' . $list . '</ul>';
            }

            // Tag: {categoriesmenu}. A custom menu list course categories with links.
            if (stripos($text, '{categoriesmenu}') !== false) {
                $list = '';
                foreach ($categories as $id => $name) {
                    $list .= '-' . $name . '|/course/index.php?categoryid=' . $id . PHP_EOL;
                }
                $replace['/\{categoriesmenu\}/i'] = $list;
            }

            unset($list);
        }

        // Tag: {referer}.
        if (stripos($text, '{referer}') !== false) {
            if ($CFG->branch >= 28) {
                $replace['/\{referer\}/i'] = get_local_referer(false);
            } else {
                $replace['/\{referer\}/i'] = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
            }
        }

        // Tag: {wwwroot}.
        if (stripos($text, '{wwwroot}') !== false) {
            $replace['/\{wwwroot\}/i'] = $CFG->wwwroot;
        }

        // Tag: {protocol}.
        if (stripos($text, '{protocol}') !== false) {
            $replace['/\{protocol\}/i'] = 'http'.($this->ishttps() ? 's' : '');
        }

        // Tag: {ipaddress}.
        if (stripos($text, '{ipaddress}') !== false) {
            $replace['/\{ipaddress\}/i'] = getremoteaddr();
        }

        // Any {sesskey} or %7Bsesskey%7D tags.
        // Tag: {sesskey}.
        if (stripos($text, '{sesskey}') !== false) {
            $replace['/\{sesskey\}/i'] = sesskey();
        }
        // Alternative Tag: %7Bsesskey%7D (for encoded URLs).
        if (stripos($text, '%7Bsesskey%7D') !== false) {
            $replace['/%7Bsesskey%7D/i'] = sesskey();
        }

        // Tag: {recaptcha}.
        if (stripos($text, '{recaptcha}') !== false) {
            $replace['/\{recaptcha\}/i'] = $this->getrecaptcha();
        }

        // Tag: {readonly}.
        // This is to be used in forms to make some fields read-only when user is logged-in as non-guest.
        if (stripos($text, '{readonly}') !== false) {
            if (isloggedin() && !isguestuser()) {
                $replace['/\{readonly\}/i'] = 'readonly="readonly"';
            } else {
                $replace['/\{readonly\}/i'] = '';
            }
        }

        // HTML tagging.

        // Tag: {nbsp}.
        if (stripos($text, '{nbsp}') !== false) {
            $replace['/\{nbsp\}/i'] = '&nbsp;';
        }
        // Tag: {langx xx}.
        if (stripos($text, '{langx ') !== false) {
            $replace['/\{langx\s+(\w+)\}(.*?)\{\/langx\}/ims'] = '<span lang="$1">$2</span>';
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
                    $replace['/\{ifnotenrolled\}(.*?)\{\/ifnotenrolled\}/ims'] = '';
                }
            } else {
                if ($this->hasarchetype('student')) { // If user is enrolled in the course.
                    // If enrolled, remove the ifenrolled tags.
                    if (stripos($text, '{ifenrolled}') !== false) {
                        $replace['/\{ifenrolled\}/i'] = '';
                        $replace['/\{\/ifenrolled\}/i'] = '';
                    }
                    // Remove the ifnotenrolled strings.
                    if (stripos($text, '{ifnotenrolled}') !== false) {
                        $replace['/\{ifnotenrolled\}(.*?)\{\/ifnotenrolled\}/ims'] = '';
                    }
                } else {
                    // Otherwise, remove the ifenrolled strings.
                    if (stripos($text, '{ifenrolled}') !== false) {
                        $replace['/\{ifenrolled\}(.*?)\{\/ifenrolled\}/ims'] = '';
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
            if ($this->hasonlyarchetype('student')) {
                if (stripos($text, '{ifstudent}') !== false) {
                    // Just remove the tags.
                    $replace['/\{ifstudent\}/i'] = '';
                    $replace['/\{\/ifstudent\}/i'] = '';
                }
            } else {
                // And remove the ifstudent strings.
                if (stripos($text, '{ifstudent}') !== false) {
                    $replace['/\{ifstudent\}(.*?)\{\/ifstudent\}/ims'] = '';
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
                    $replace['/\{ifloggedout\}(.*?)\{\/ifloggedout\}/ims'] = '';
                }
            } else { // If logged-out.
                // Remove the ifloggedout tags.
                if (stripos($text, '{ifloggedout}') !== false) {
                    $replace['/\{ifloggedout\}/i'] = '';
                    $replace['/\{\/ifloggedout\}/i'] = '';
                }
                // Remove ifloggedin strings.
                if (stripos($text, '{ifloggedin}') !== false) {
                    $replace['/\{ifloggedin\}(.*?)\{\/ifloggedin\}/ims'] = '';
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
                    $replace['/\{ifguest}(.*?)\{\/ifguest\}/ims'] = '';
                }
            }

            // Tag: {ifassistant}.
            if (stripos($text, '{ifassistant}') !== false) {
                // If an assistant (non-editing teacher).
                if ($this->hasarchetype('teacher') && stripos($text, '{ifassistant}') !== false) {
                    // Just remove the tags.
                    $replace['/\{ifassistant\}/i'] = '';
                    $replace['/\{\/ifassistant\}/i'] = '';
                } else {
                    // Remove the ifassistant strings.
                    $replace['/\{ifassistant\}(.*?)\{\/ifassistant\}/ims'] = '';
                }
            }

            // Tag: {ifteacher}.
            if (stripos($text, '{ifteacher}') !== false) {
                if ($this->hasarchetype('editingteacher')) { // If a teacher.
                    // Just remove the tags.
                    $replace['/\{ifteacher\}/i'] = '';
                    $replace['/\{\/ifteacher\}/i'] = '';
                } else {
                    // Remove the ifteacher strings.
                    $replace['/\{ifteacher\}(.*?)\{\/ifteacher\}/ims'] = '';
                }
            }

            // Tag: {ifcreator}.
            if (stripos($text, '{ifcreator}') !== false) {
                if ($this->hasarchetype('coursecreator')) { // If a course creator.
                    // Just remove the tags.
                    $replace['/\{ifcreator\}/i'] = '';
                    $replace['/\{\/ifcreator\}/i'] = '';
                } else {
                    // Remove the iscreator strings.
                    $replace['/\{ifcreator\}(.*?)\{\/ifcreator\}/ims'] = '';
                }
            }

            // Tag: {ifmanager}.
            if (stripos($text, '{ifmanager}') !== false) {
                if ($this->hasarchetype('manager')) { // If a manager.
                    // Just remove the tags.
                    $replace['/\{ifmanager\}/i'] = '';
                    $replace['/\{\/ifmanager\}/i'] = '';
                } else {
                    // Remove the ifmanager strings.
                    $replace['/\{ifmanager\}(.*?)\{\/ifmanager\}/ims'] = '';
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
                    $replace['/\{ifadmin\}(.*?)\{\/ifadmin\}/ims'] = '';
                }
            }

            if (strpos($text, '{ifmin') !== false) { // If there are conditional ifmin tags.

                // Tag: {ifminassistant}.
                if (stripos($text, '{ifminassistant}') !== false) {
                    // If an assistant (non-editing teacher) or above.
                    if ($this->hasminarchetype('teacher') && stripos($text, '{ifminassistant}') !== false) {
                        // Just remove the tags.
                        $replace['/\{ifminassistant\}/i'] = '';
                        $replace['/\{\/ifminassistant\}/i'] = '';
                    } else {
                        // Remove the ifminassistant strings.
                        $replace['/\{ifminassistant\}(.*?)\{\/ifminassistant\}/ims'] = '';
                    }
                }

                // Tag: {ifminteacher}.
                if (stripos($text, '{ifminteacher}') !== false) {
                    if ($this->hasminarchetype('editingteacher')) { // If a teacher or above.
                        // Just remove the tags.
                        $replace['/\{ifminteacher\}/i'] = '';
                        $replace['/\{\/ifminteacher\}/i'] = '';
                    } else {
                        // Remove the ifminteacher strings.
                        $replace['/\{ifminteacher\}(.*?)\{\/ifminteacher\}/ims'] = '';
                    }
                }

                // Tag: {ifmincreator}.
                if (stripos($text, '{ifmincreator}') !== false) {
                    if ($this->hasminarchetype('coursecreator')) { // If a course creator or above.
                        // Just remove the tags.
                        $replace['/\{ifmincreator\}/i'] = '';
                        $replace['/\{\/ifmincreator\}/i'] = '';
                    } else {
                        // Remove the iscreator strings.
                        $replace['/\{ifmincreator\}(.*?)\{\/ifmincreator\}/ims'] = '';
                    }
                }

                // Tag: {ifminmanager}.
                if (stripos($text, '{ifminmanager}') !== false) {
                    if ($this->hasminarchetype('manager')) { // If a manager or above.
                        // Just remove the tags.
                        $replace['/\{ifminmanager\}/i'] = '';
                        $replace['/\{\/ifminmanager\}/i'] = '';
                    } else {
                        // Remove the ifminmanager strings.
                        $replace['/\{ifminmanager\}(.*?)\{\/ifminmanager\}/ims'] = '';
                    }
                }

            }

        }

        // Apply all of the filtercodes at once.
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
