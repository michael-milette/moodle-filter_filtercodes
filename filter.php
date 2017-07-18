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
        if(stripos($text,'{firstname}') !== false) {
            $replace['/\{firstname\}/i'] = $firstname;
        }

        // Tag: {surname}.
        if(stripos($text,'{surname}') !== false) {
            $replace['/\{surname\}/i'] = $lastname;
        }

        // Tag: {fullname}.
        if(stripos($text,'{fullname}') !== false) {
            $replace['/\{fullname\}/i'] = trim($firstname . ' ' . $lastname);
        }

        // Tag: {username}.
        if(stripos($text,'{username}') !== false) {
            $replace['/\{username\}/i'] = isloggedin() ? $USER->username : get_string('defaultusername', 'filter_filtercodes');
        }

        // Tag: {email}.
        if(stripos($text,'{email}') !== false) {
            $replace['/\{email\}/i'] = isloggedin() ? $USER->email : '';
        }

        // Tag: {userid}.
        if(stripos($text,'{userid}') !== false) {
            $replace['/\{userid\}/i'] = $USER->id;
        }

        // Tag: {courseid}.
        if(stripos($text,'{courseid}') !== false) {
            $replace['/\{courseid\}/i'] = $PAGE->course->id;
        }

        // Tag: {referer}.
        if(stripos($text,'{referer}') !== false) {
            $replace['/\{referer\}/i'] = get_local_referer(false);
        }

        // Tag: {wwwroot}.
        if(stripos($text,'{wwwroot}') !== false) {
            $replace['/\{wwwroot\}/i'] = $CFG->wwwroot;
        }

        // Tag: {protocol}.
        if(stripos($text,'{protocol}') !== false) {
            $replace['/\{protocol\}/i'] = 'http'.(is_https() ? 's' : '');
        }

        // Tag: {ipaddress}.
        if(stripos($text,'{ipaddress}') !== false) {
            //$replace['/\{ipaddress\}/i'] = $this->getuserip();
            $replace['/\{ipaddress\}/i'] = $this->getuserip();
        }

        // Tag: {recaptcha}.
        if(stripos($text,'{recaptcha}') !== false) {
            $replace['/\{recaptcha\}/i'] = $this->getrecaptcha();
        }

        // HTML tagging.

        // Tag: {nbsp}.
        if(stripos($text,'{nbsp}') !== false) {
            $replace['/\{nbsp\}/i'] = '&nbsp;';
        }
        // Tag: {langx xx}.
        if(stripos($text,'{langx ') !== false) {
            $replace['/\{langx\s+(\w+)\}(.*)\{\/langx\}/i'] = '<span lang="$1">$2</span>';
        }

        // Conditional blocks.

        // Tags: {ifenrolled} and {ifnotenrolled}.
        if ($PAGE->course->id == $SITE->id) { // If enrolled in the course.
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
            if ($CFG->version >= 2013051400) { // Moodle 2.5+.
                $coursecontext = context_course::instance($PAGE->course->id);
            } else {
                $coursecontext = get_context_instance(CONTEXT_COURSE, $PAGE->course->id);
            }
            if (is_enrolled($coursecontext, $USER, '', true)) { // If user is enrolled in the course.
                // If enrolled, remove the ifenrolled tags.
                if (stripos($text, '{ifenrolled}') !== false) {
                    $replace['/\{ifenrolled\}/i'] = '';
                    $replace['/\{\/ifenrolled\}/i'] = '';
                }
                // Remove the ifnotenrolled strings.
                if (stripos($text, '{ifnotenrolled}') !== false) {
                    $replace['/\{ifnotenrolled)\}(.*)\{\/ifnotenrolled\}/i'] = '';
                }
            } else {
                // Otherwise, remove the ifenrolled strings.
                if (stripos($text, '{ifenrolled}') !== false) {
                    $replace['/\{ifenrolled)\}(.*)\{\/ifenrolled\}/i'] = '';
                }
                // And remove the ifnotenrolled tags.
                if (stripos($text, '{ifnotenrolled}') !== false) {
                    $replace['/\{ifnotenrolled\}/i'] = '';
                    $replace['/\{\/ifnotenrolled\}/i'] = '';
                }
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
        if(stripos($text, '{ifguest}') !== false) {
            if (isguestuser()) { // If logged-in as guest.
                // Just remove the tags.
                $replace['/\{ifguest\}/i'] = '';
                $replace['/\{\/ifguest\}/i'] = '';
            } else {
                // If not logged-in as guest, remove the ifguest text.
                $replace['/\{ifguest}(.*)\{\/ifguest\}/i'] = '';
            }
        }

        // Tag: {ifstudent}.
        if(stripos($text, '{ifstudent}') !== false) {
            if ($this->isstudent()) { // If an administrator.
                // Just remove the tags.
                $replace['/\{ifstudent\}/i'] = '';
                $replace['/\{\/ifstudent\}/i'] = '';
            } else {
                // Remove the ifstudent strings.
                $replace['/\{ifstudent\}(.*)\{\/ifstudent\}/i'] = '';
            }
        }

        // Tag: {ifassistant}.
        if (stripos($text, '{ifassistant}') !== false) {
            // If an assistant (non-editing teacher).
            if ($this->isassistant() && stripos($text, '{ifassistant}') !== false) {
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
            if ($this->isteacher()) { // If a teacher.
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
            if ($this->iscreator()) { // If a course creator.
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
            if ($this->ismanager()) { // If a manager.
                // Just remove the tags.
                $replace['/\{ifmanager\}/i'] = '';
                $replace['/\{\/ifmanager\}/i'] = '';
            } else {
                // Remove the ifmanager strings.
                $replace['/\{ifmanager\}(.*)\{\/ifmanager\}/i'] = '';
            }
        }

        // Tag: {ifadmin}.
        if (stripos($text, '{ifadmin}') !== false) {
            if (is_siteadmin()) { // If an administrator.
                // Just remove the tags.
                $replace['/\{ifadmin\}/i'] = '';
                $replace['/\{\/ifadmin\}/i'] = '';
            } else {
                // Remove the ifadmin strings.
                $replace['/\{ifadmin\}(.*)\{\/ifadmin\}/i'] = '';
            }
        }

        // Apply all of the filtercodes.
        if (sizeof($replace) > 0) {
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
