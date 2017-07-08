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

    /**
     * Get the user's public or private IP address.
     *
     * @return     string  Public IP address or the private IP address if the public address cannot be identified.
     */
    private function getuserip() {
        $fieldlist = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED',
                'REMOTE_ADDR', 'HTTP_CF_CONNECTING_IP', 'HTTP_X_CLUSTER_CLIENT_IP');

        // Public range first.
        $filterlist = array(
            FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );

        foreach ($filterlist as $filter) {
            foreach ($fieldlist as $field) {

                if (!array_key_exists($field, $_SERVER) || empty($_SERVER[$field])) {
                    continue;
                }

                $iplist = explode(',', $_SERVER[$field]);
                foreach ($iplist as $ip) {

                    // Strips off port number if it exists.
                    if (substr_count($ip, ':') == 1) {
                        // IPv4 with a port.
                        list($ip) = explode(':', $ip);
                    } else if ($start = (substr($ip, 0, 1) == '[') && $end = strpos($ip, ']:') !== false) {
                        // IPv6 with a port.
                        $ip = substr($ip, $start + 1, $end - 2);
                    }
                    // Sanitize so that we only get public addresses.
                    $lastip = $ip; // But save other address just in case.
                    $ip = filter_var(trim($ip), FILTER_VALIDATE_IP, $filter);
                    if ($ip !== false) {
                        return $ip;
                    }
                }
            }
        }
        // Private or restricted range.
        return $lastip;
    }

    /**
     * Determine if the user is a student.
     *
     * @return boolean  Is: true, Not: false.
     */
    private function isstudent() {
        if (!isloggedin() || isguestuser()) {
            return false;
        }

        static $isrole = null;
        if ($isrole != null) {
            return $isrole;
        }
        $isrole = false;

        global $PAGE;
        $coursecontext = $PAGE->context->get_course_context(false);
        if ($coursecontext) {
            $isrole = has_any_capability(array(
                    'moodle/course:view',
                    'gradereport/user:view',
            ), $coursecontext);
        }
    }

    /**
     * Determine if the user is a Non-editing teacher in the current course.
     *
     * @return boolean  Is: true, Not: false.
     */
    private function isassistant() {
        if (!isloggedin() || isguestuser()) {
            return false;
        }

        static $isrole = null;
        if ($isrole != null) {
            return $isrole;
        }
        $isrole = false;

        global $PAGE;
        if ($coursecontext = $PAGE->context->get_course_context(false)) {
            $isrole = has_any_capability(array(
                'gradereport/grader:view',
                'gradereport/outcomes:view',
                'moodle/badges:awardbadge',
                'moodle/badges:viewawarded',
                'moodle/course:markcomplete',
                'moodle/course:viewhiddencourses',
            ), $coursecontext);
        }
        return $isrole;
    }

    /**
     * Determine if the user is a Editing teacher in the current course.
     *
     * @return boolean  Is: true, Not: false.
     */
    private function isteacher() {
        if (!isloggedin() || isguestuser()) {
            return false;
        }

        static $isrole = null;
        if ($isrole != null) {
            return $isrole;
        }
        $isrole = false;

        global $PAGE;
        if ($coursecontext = $PAGE->context->get_course_context(false)) {
            $isrole = has_any_capability(array(
                'moodle/course:manageactivities',
                'moodle/backup:backupcourse',
                'moodle/course:managefiles',
                'moodle/course:reset',
                'moodle/course:update',
                'moodle/filter:manage',
                'moodle/grade:viewall',
                'moodle/restore:restorecourse',
                'moodle/restore:restoretargetimport',
                'moodle/role:switchroles',
                'moodle/site:viewreports',
                ), $coursecontext);
        }
        return $isrole;
    }

    /**
     * Determine if the user is a Course creator.
     *
     * @return boolean  Is: true, Not: false.
     */
    private function iscreator() {
        if (!isloggedin() || isguestuser()) {
            return false;
        }

        static $isrole = null;
        if ($isrole != null) {
            return $isrole;
        }
        $isrole = false;

        global $PAGE;
        $isrole = has_any_capability(array(
                'moodle/course:create',
                'moodle/course:delete',
                'moodle/role:assign',
                'moodle/role:manage',
                ), $PAGE->context);
        return $isrole;
    }

    /**
     * Determine if the user is a Manager.
     *
     * @return boolean  Is: true, Not: false.
     */
    private function ismanager() {
        if (!isloggedin() || isguestuser()) {
            return false;
        }

        static $isrole = null;
        if ($isrole != null) {
            return $isrole;
        }
        $isrole = false;

        global $PAGE;
        $isrole = has_any_capability(array(
            'moodle/backup:backupactivity',
            'moodle/filter:manage',
            'moodle/grade:managegradingforms',
            'moodle/restore:restoreactivity',
            'moodle/role:override',
            'moodle/role:review',
            'moodle/role:safeoverride',
            'moodle/user:create',
            'moodle/user:delete'
            ), $PAGE->context);
        return $isrole;
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
        $newtext = $text;

        // Substitutions.

        if (isloggedin()) {
            $newtext = preg_replace('/\{firstname\}/is', $USER->firstname, $newtext);
            $newtext = preg_replace('/\{surname\}/is', $USER->lastname, $newtext);
            $newtext = preg_replace('/\{fullname\}/is', $USER->firstname . ' ' . $USER->lastname, $newtext);
            if (isguestuser()) {
                $newtext = preg_replace('/\{email\}/is', '', $newtext);
            } else {
                $newtext = preg_replace('/\{email\}/is', $USER->email, $newtext);
            }
            $newtext = preg_replace('/\{username\}/is', $USER->username, $newtext);
        } else {
            $firstname = get_string('defaultfirstname', 'filter_filtercodes');
            $lastname = get_string('defaultsurname', 'filter_filtercodes');
            $newtext = preg_replace('/\{firstname\}/is', $firstname, $newtext);
            $newtext = preg_replace('/\{surname\}/is',  $lastname, $newtext);
            $newtext = preg_replace('/\{fullname\}/is', trim($firstname . ' ' . $lastname), $newtext);
            $newtext = preg_replace('/\{email\}/is',  get_string('defaultemail', 'filter_filtercodes'), $newtext);
            $newtext = preg_replace('/\{username\}/is',  get_string('defaultusername', 'filter_filtercodes'), $newtext);
        }
        $newtext = preg_replace('/\{userid\}/is', $USER->id, $newtext);
        $newtext = preg_replace('/\{courseid\}/is', $PAGE->course->id, $newtext);
        $newtext = preg_replace('/\{referer\}/is', get_local_referer(false), $newtext);
        $newtext = preg_replace('/\{wwwroot\}/is', $CFG->wwwroot, $newtext);
        $newtext = preg_replace('/\{protocol\}/is', 'http'.(is_https() ? 's' : ''), $newtext);
        $newtext = preg_replace('/\{ipaddress\}/is', $this->getuserip(), $newtext);
        $newtext = preg_replace('/\{recaptcha\}/is', $this->getrecaptcha(), $newtext);

        // HTML tagging.

        $newtext = preg_replace('/\{nbsp\}/is', '&nbsp;', $newtext);
        $newtext = preg_replace('/\{langx\s+(\w+)\}(.*)\{\/langx\}/is', '<span lang="$1">$2</span>', $newtext);

        // Conditional blocks.

        if (isloggedin() && !isguestuser()) {// If logged-in but not just as guest.
            // Just remove ifloggedin tags.
            $newtext = preg_replace('/\{ifloggedin\}/is', '', $newtext);
            $newtext = preg_replace('/\{\/ifloggedin\}/is', '', $newtext);

            // Remove the ifloggedout strings.
            $newtext = preg_replace('/\{ifloggedout\}(.*)\{\/ifloggedout\}/is', '', $newtext);
        } else { // If logged-out.
            // Remove the ifloggedout tags.
            $newtext = preg_replace('/\{ifloggedout\}/is', '', $newtext);
            $newtext = preg_replace('/\{\/ifloggedout\}/is', '', $newtext);

            // Remove ifloggedin strings.
            $newtext = preg_replace('/\{ifloggedin\}(.*)\{\/ifloggedin\}/is', '', $newtext);
        }

        if (isguestuser()) { // If logged-in as guest.
            // Just remove the tags.
            $newtext = preg_replace('/\{ifguest\}/is', '', $newtext);
            $newtext = preg_replace('/\{\/ifguest\}/is', '', $newtext);
        } else {
            // If not logged-in as guest, remove the ifguest text.
            $newtext = preg_replace('/\{ifguest}(.*)\{\/ifguest\}/is', '', $newtext);
        }

        if ($this->isstudent()) { // If an administrator.
            // Just remove the tags.
            $newtext = preg_replace('/\{ifstudent\}/is', '', $newtext);
            $newtext = preg_replace('/\{\/ifstudent\}/is', '', $newtext);
        } else {
            // Remove the ifstudent strings.
            $newtext = preg_replace('/\{ifstudent\}(.*)\{\/ifstudent\}/is', '', $newtext);
        }

        if ($this->isassistant()) { // If an assistant (non-editing teacher).
            // Just remove the tags.
            $newtext = preg_replace('/\{ifassistant\}/is', '', $newtext);
            $newtext = preg_replace('/\{\/ifassistant\}/is', '', $newtext);
        } else {
            // Remove the ifassistant strings.
            $newtext = preg_replace('/\{ifassistant\}(.*)\{\/ifassistant\}/is', '', $newtext);
        }

        if ($this->isteacher()) { // If a teacher.
            // Just remove the tags.
            $newtext = preg_replace('/\{ifteacher\}/is', '', $newtext);
            $newtext = preg_replace('/\{\/ifteacher\}/is', '', $newtext);
        } else {
            // Remove the ifteacher strings.
            $newtext = preg_replace('/\{ifteacher\}(.*)\{\/ifteacher\}/is', '', $newtext);
        }

        if ($this->iscreator()) { // If a course creator.
            // Just remove the tags.
            $newtext = preg_replace('/\{ifcreator\}/is', '', $newtext);
            $newtext = preg_replace('/\{\/ifcreator\}/is', '', $newtext);
        } else {
            // Remove the iscreator strings.
            $newtext = preg_replace('/\{ifcreator\}(.*)\{\/ifcreator\}/is', '', $newtext);
        }

        if ($this->ismanager()) { // If a manager.
            // Just remove the tags.
            $newtext = preg_replace('/\{ifmanager\}/is', '', $newtext);
            $newtext = preg_replace('/\{\/ifmanager\}/is', '', $newtext);
        } else {
            // Remove the ifmanager strings.
            $newtext = preg_replace('/\{ifmanager\}(.*)\{\/ifmanager\}/is', '', $newtext);
        }

        if (is_siteadmin()) { // If an administrator.
            // Just remove the tags.
            $newtext = preg_replace('/\{ifadmin\}/is', '', $newtext);
            $newtext = preg_replace('/\{\/ifadmin\}/is', '', $newtext);
        } else {
            // Remove the ifadmin strings.
            $newtext = preg_replace('/\{ifadmin\}(.*)\{\/ifadmin\}/is', '', $newtext);
        }

        if ($PAGE->course->id == $SITE->id) { // If enrolled in the course.
            // Everyone is automatically enrolled in the Front Page course.
            // Remove the ifenrolled tags.
            $newtext = preg_replace('/\{ifenrolled\}/is', '', $newtext);
            $newtext = preg_replace('/\{\/ifenrolled\}/is', '', $newtext);
        } else {
            if ($CFG->version >= 2013051400) { // Moodle 2.5+.
                $coursecontext = context_course::instance($PAGE->course->id);
            } else {
                $coursecontext = get_context_instance(CONTEXT_COURSE, $PAGE->course->id);
            }
            if (is_enrolled($coursecontext, $USER, '', true)) { // If user is enrolled in the course.
                // If enrolled, remove the ifenrolled tags.
                $newtext = preg_replace('/\{ifenrolled\}/is', '', $newtext);
                $newtext = preg_replace('/\{\/ifenrolled\}/is', '', $newtext);
            } else {
                // Otherwise, remove the ifenrolled strings.
                $newtext = preg_replace('/\{ifenrolled)\}(.*)\{\/ifenrolled\}/is', '', $text);
            }
        }

        // Return original text if an error occurred during regex processing.
        if (is_null($newtext)) {
            return $text;
        } else {
            return $newtext;
        }

    }
}
