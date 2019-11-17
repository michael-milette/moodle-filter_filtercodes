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
 * @copyright  2017-2019 TNG Consulting Inc. - www.tngcosulting.ca
 * @author     Michael Milette
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use block_online_users\fetcher;

/**
 * Extends the moodle_text_filter class to provide plain text support for new tags.
 *
 * @copyright  2017-2019 TNG Consulting Inc. - www.tngconsulting.ca
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filter_filtercodes extends moodle_text_filter {
    /** @var object $archetypes Object array of Moodle archetypes. */
    public $archetypes = [];

    /**
     * Constructor: Get the role IDs associated with each of the archetypes.
     */
    public function __construct() {

        // Note: This array must correspond to the one in function hasminarchetype().
        $archetypelist = ['manager' => 1, 'coursecreator' => 2, 'editingteacher' => 3, 'teacher' => 4, 'student' => 5];
        foreach ($archetypelist as $archetype => $level) {
            $roleids = [];
            // Build array of roles.
            foreach (get_archetype_roles($archetype) as $role) {
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
        static $archetypes = [];
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
            $archetypes = array_keys($this->archetypes);
            foreach ($archetypes as $archetypename) {
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
        $archetypelist = ['', 'manager', 'coursecreator', 'editingteacher', 'teacher', 'student'];
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
     * Scrape HTML (callback)
     *
     * Extract content from another web page.
     * Example: Can be used to extract a shared privacy policy across your websites.
     *
     * @param string $url URL address of content source.
     * @param string $tag HTML tag that contains the information we want to retrieve.
     * @param string $class (optional) HTML tag class attribute we should match.
     * @param string $id (optional) HTML tag id attribute we should match.
     * @param string $code (optional) any URL encoded HTML code you want to insert after the retrieved content.
     * @return string Extracted content+optional code. If content is unavailable, returns message to contact webmaster.
     */
    private function scrapehtml($url, $tag = '', $class = '', $id = '', $code = '') {
        // Retrieve content. If the URL fails, return a message.
        $content = @file_get_contents($url);
        if (empty($content)) {
            return get_string('contentmissing', 'filter_filtercodes');
        }

        // Disable warnings.
        $libxmlpreviousstate = libxml_use_internal_errors(true);

        // Load content into DOM object.
        $dom = new DOMDocument();
        $dom->loadHTML($content);

        // Clear suppressed warnings.
        libxml_clear_errors();
        libxml_use_internal_errors($libxmlpreviousstate);

        // Scrape out the content we want. If not found, return everything.
        $xpath = new DOMXPath($dom);

        // If a tag was not specified.
        if (empty($tag)) {
            $tag .= '*'; // Match any tag.
        }
        $query = "//${tag}";

        // If a class was specified.
        if (!empty($class)) {
            $query .= "[@class=\"${class}\"]";
        }

        // If an id was specified.
        if (!empty($id)) {
            $query .= "[@id=\"${id}\"]";
        }

        $tag = $xpath->query($query);
        $tag = $tag->item(0);

        return $dom->saveXML($tag) . urldecode($code);
    }

    /**
     * Main filter function called by Moodle.
     *
     * @param string $text   Content to be filtered.
     * @param array $options Moodle filter options. None are implemented in this plugin.
     * @return string Content with filters applied.
     */
    public function filter($text, array $options = []) {
        global $CFG, $SITE, $PAGE, $USER, $DB;

        if (strpos($text, '{') === false && strpos($text, '%7B') === false) {
            return $text;
        }

        $replace = []; // Array of key/value filterobjects.
        $changed = false; // Will be true if there were any changes.

        // Handle escaped tags.

        // Ignore double bracketed tags.
        $doublesescapes = (strpos($text, '{{') !== false && strpos($text, '}}') !== false);
        if ($doublesescapes) {
            $text = str_replace('{{', chr(2), $text);
            $text = str_replace('}}', chr(3), $text);
        }
        // Ignore encoded tags.
        $escapesencoded = (strpos($text, '{%7B') !== false && strpos($text, '%7D}') !== false);
        if ($escapesencoded) {
            $text = str_replace('{%7B', chr(4), $text);
            $text = str_replace('%7D}', chr(5), $text);
        }

        // START: Process tags that may end up containing other tags first.

        // Tag: {form...}.
        if (stripos($text, '{form') !== false) {
            $pre = '<form action="{wwwcontactform}" method="post" class="cf ';
            $post = '</form>';
            $options = ['noclean' => true, 'para' => false, 'newlines' => false];
            // These require that you already be logged-in.
            foreach (['formquickquestion', 'formcheckin'] as $form) {
                if (stripos($text, '{' . $form . '}') !== false) {
                    if (isloggedin() && !isguestuser()) {
                        $formcode = get_string($form, 'filter_filtercodes');
                        $replace['/\{' . $form . '\}/i'] = $pre . $form . '">' . get_string($form, 'filter_filtercodes') . $post;
                    } else {
                        $replace['/\{' . $form . '\}/i'] = '';
                    }
                }
            }
            // These work regardless of whether you are logged-in or not.
            foreach (['formcontactus', 'formcourserequest', 'formsupport'] as $form) {
                if (stripos($text, '{' . $form . '}') !== false) {
                    $formcode = get_string($form, 'filter_filtercodes');
                    $replace['/\{' . $form . '\}/i'] = $pre . $form . '">' . $formcode . $post;
                } else {
                    $replace['/\{' . $form . '\}/i'] = '';
                }
            }
        }
        // Apply all of the filtercodes so far.
        $newtext = null;
        if (count($replace) > 0) {
            $newtext = preg_replace(array_keys($replace), array_values($replace), $text);
        }
        if (!is_null($newtext)) {
            $text = $newtext;
            $changed = true;
        }
        $replace = [];

        // END: Process tags that may end up containing other tags first.

        // Tag: {profile_field_...}.
        // Custom Profile Fields.
        if (stripos($text, '{profile_field') !== false) {
            if (isloggedin() && !isguestuser()) {
                // Cached the visibity status of all the defined custom profile fields.
                static $fields;
                if (!isset($fields)) {
                    $fields = $DB->get_records('user_info_field', null, '', 'shortname, visible');
                }
                foreach ($USER->profile as $field => $value) {
                    $shortname = strtolower($field);
                    // If the tag exists and it is not hidden in the custom profile field's settings.
                    if (stripos($text, '{profile_field_' . $shortname . '}') !== false && $fields[$field]->visible != '0') {
                        $replace['/\{profile_field_' . $shortname . '\}/i'] = $value;
                    } else {
                        $replace['/\{profile_field_' . $shortname . '\}/i'] = '';
                    }
                }
            }
        }

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

        // Tag: {lastname} (same as surname... just easier to remember).
        if (stripos($text, '{lastname}') !== false) {
            $replace['/\{lastname\}/i'] = $lastname;
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

        // Tag: {idnumber}.
        if (stripos($text, '{idnumber}') !== false) {
            $replace['/\{idnumber\}/i'] = isloggedin() ? $USER->idnumber : '';
        }

        if (get_config('filter_filtercodes', 'enable_scrape')) { // Must be enabled in FilterCodes settings.
            // Tag: {scrape url="" tag="" class="" id="" code=""}.
            if (stripos($text, '{scrape ') !== false) {
                // Replace {scrape} tag and parameters with retrieved content.
                $newtext = preg_replace_callback('/\{scrape\s+(.*?)\}/i',
                    function ($matches) {
                        $scrape = '<' . substr($matches[0], 1, -1) . '/>';
                        $scrape = new SimpleXMLElement($scrape);
                        $url = (string) $scrape->attributes()->url;
                        $tag = (string) $scrape->attributes()->tag;
                        $class = (string) $scrape->attributes()->class;
                        $id = (string) $scrape->attributes()->id;
                        $code = (string) $scrape->attributes()->code;
                        if (empty($url)) {
                            return "SCRAPE error: Missing required URL parameter.";
                        }
                        return $this->scrapehtml($url, $tag, $class, $id, $code);
                    }, $text);
                if ($newtext !== false) {
                    $text = $newtext;
                    $changed = true;
                }
            }
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
                            $sublist = ['sm' => '2', '2' => '2', 'md' => '1', '1' => '1', 'lg' => '3', '3' => '3'];
                            return '{userpictureurl ' . $sublist[$matches[1]] . '}';
                        }, $text);
                    if ($newtext !== false) {
                        $text = $newtext;
                        $changed = true;
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
                            $sublist = ['sm' => '2', '2' => '2', 'md' => '1', '1' => '1', 'lg' => '3', '3' => '3'];
                            return '{userpictureimg ' . $sublist[$matches[1]] . '}';
                        }, $text);
                    if ($newtext !== false) {
                        $text = $newtext;
                        $changed = true;
                    }
                    $replace['/\{userpictureimg\s+(\w+)\}/i'] = $tag;
                }
            }

            // Tag: {usercount}.
            if (stripos($text, '{usercount}') !== false) {
                // Count total number of current users on the site.
                // Exclude deleted users, admin and guest.
                $cnt = $DB->count_records('user', array('deleted' => 0)) - 2;
                $replace['/\{usercount\}/i'] = $cnt;
            }

            // Tag: {usersactive}.
            if (stripos($text, '{usersactive}') !== false) {
                // Count total number of current users on the site.
                // Exclude deleted, suspended and unconfirmed users, admin and guest.
                $cnt = $DB->count_records('user', array('deleted' => 0, 'suspended' => 0, 'confirmed' => 1)) - 2;
                $replace['/\{usersactive\}/i'] = $cnt;
            }

            // Tag: {usersonline}.
            if (stripos($text, '{usersonline}') !== false) {
                $timetosee = 300; // Within last number of seconds (300 = 5 minutes).
                if (isset($CFG->block_online_users_timetosee)) {
                    $timetosee = $CFG->block_online_users_timetosee * 60;
                }
                $now = time();

                // Calculate if we are in separate groups.
                $isseparategroups = ($PAGE->course->groupmode == SEPARATEGROUPS
                        && $PAGE->course->groupmodeforce
                        && !has_capability('moodle/site:accessallgroups', $PAGE->context));

                // Get the user current group.
                $thisgroup = $isseparategroups ? groups_get_course_group($PAGE->course) : null;

                $onlineusers = new fetcher($thisgroup, $now, $timetosee, $PAGE->context,
                        $PAGE->context->contextlevel, $PAGE->course->id);

                // Count online users.
                $usersonline = $onlineusers->count_users();
                $replace['/\{usersonline\}/i'] = $usersonline;
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

            // Tag: {coursecontextid}.
            if (stripos($text, '{coursecontextid}') !== false) {
                $context = context_course::instance($PAGE->course->id);
                $coursecontextid = isset($PAGE->course->id) ? $context->id : 1;
                $replace['/\{coursecontextid\}/i'] = $coursecontextid;
            }
            // Alternative Tag: %coursecontextid%7D (for encoded URLs).
            if (stripos($text, '%coursecontextid%7D') !== false) {
                $context = context_course::instance($PAGE->course->id);
                $coursecontextid = isset($PAGE->course->id) ? $context->id : 1;
                $replace['/%coursecontextid%7D/i'] = $coursecontextid;
            }

            // Tag: {courseidnumber}.
            if (stripos($text, '{courseidnumber}') !== false) {
                $replace['/\{courseidnumber\}/i'] = $PAGE->course->idnumber;
            }

            // Tag: {coursename}. The full name of this course.
            if (stripos($text, '{coursename}') !== false) {
                $course = $PAGE->course;
                if ($course->id == $SITE->id) { // Front page - use site name.
                    $replace['/\{coursename\}/i'] = format_string($SITE->fullname);
                } else { // In a course - use course full name.
                    $coursecontext = context_course::instance($course->id);
                    $replace['/\{coursename\}/i'] = format_string($course->fullname, true, ['context' => $coursecontext]);
                }
            }

            // Tag: {courseshortname}. The short name of this course.
            if (stripos($text, '{courseshortname}') !== false) {
                $course = $PAGE->course;
                if ($course->id == $SITE->id) { // Front page - use site name.
                    $replace['/\{courseshortname\}/i'] = format_string($SITE->fullname);
                } else { // In a course - use course full name.
                    $coursecontext = context_course::instance($course->id);
                    $replace['/\{courseshortname\}/i'] = format_string($course->shortname, true, ['context' => $coursecontext]);
                }
            }

            // Tag: {coursestartdate}. The name of this course.
            if (stripos($text, '{coursestartdate}') !== false) {
                if (empty($PAGE->course->startdate)) {
                    $PAGE->course->startdate = $DB->get_field_select('course', 'startdate', 'id = :id', ['id' => $course->id]);
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
                    $PAGE->course->enddate = $DB->get_field_select('course', 'enddate', 'id = :id', ['id' => $course->id]);
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
                    $ccompletion = new completion_completion(['userid' => $USER->id, 'course' => $PAGE->course->id]);
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

            // Tag: {coursecount}. The total number of courses.
            if (stripos($text, '{coursecount}') !== false) {
                // Count courses excluding front page.
                $cnt = $DB->count_records('course', array()) - 1;
                $replace['/\{coursecount\}/i'] = $cnt;
            }

            // Tag: {coursesactive}. The total visible courses.
            if (stripos($text, '{coursesactive}') !== false) {
                // Count visible courses excluding front page.
                $cnt = $DB->count_records('course', array('visible' => 1)) - 1;
                $replace['/\{coursesactive\}/i'] = $cnt;
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
                        $list .= '<li><a href="' . (new moodle_url('/course/view.php', ['id' => $mycourse->id])) . '">' .
                                $mycourse->fullname . '</a></li>';
                    }
                    // If not enrolled in any courses.
                    if (empty($list)) {
                        $list .= '<li>' . get_string(($CFG->branch >= 29 ? 'notenrolled' : 'nocourses'), 'grades') . '</li>';
                    }
                    // Add request a course link.
                    if (!empty($CFG->enablecourserequests)) {
                        $list .= '<li><a href="' . new moodle_url('/course/request.php') . '">' .
                                get_string('requestcourse') . '</a></li>';
                    }
                    $replace['/\{mycourses\}/i'] = '<ul class="mycourseslist">' . $list . '</ul>';
                }

                // Tag: {mycoursesmenu}. A custom menu list of enrolled course names with links.
                if (stripos($text, '{mycoursesmenu}') !== false) {
                    $list = '';
                    foreach ($mycourses as $mycourse) {
                        $list .= '-' . $mycourse->fullname . '|' .
                            (new moodle_url('/course/view.php', ['id' => $mycourse->id])) . PHP_EOL;
                    }
                    // If not enrolled in any courses.
                    if (empty($list)) {
                        $list .= '-' . get_string(($CFG->branch >= 29 ? 'notenrolled' : 'nocourses'), 'grades') . PHP_EOL;
                    }
                    // Add request a course link.
                    if (!empty($CFG->enablecourserequests)) {
                        $list .= '-' . get_string('requestcourse') . '|' . new moodle_url('/course/request.php');
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

        // Any {site*} tags.
        if (stripos($text, '{site') !== false) {

            // Tag: {siteyear}. Current 4 digit year.
            if (stripos($text, '{siteyear}') !== false) {
                $replace['/\{siteyear\}/i'] = date('Y');
            }
        }

        // Tag: {editingmode}. Is "off" if in edit page mode. Otherwise "on". Useful for creating Turn Editing On/Off links.
        if (stripos($text, '{editingtoggle}') !== false) {
            $replace['/\{editingtoggle\}/i'] = ($PAGE->user_is_editing() ? 'off' : 'on');
        }

        // Tag: {categories} and {categoriesmenu}.
        if (stripos($text, '{categories') !== false) {

            // Retrieve list of top categories.
            if ($CFG->branch >= 36) { // Moodle 3.6+.
                $categories = core_course_category::make_categories_list();
            } else {
                require_once($CFG->libdir. '/coursecatlib.php');
                $categories = coursecat::make_categories_list();
            }

            // Tag: {categories}. An unordered list of links to enrolled course.
            if (stripos($text, '{categories}') !== false) {
                $list = '';
                foreach ($categories as $id => $name) {
                    $list .= '<li><a href="' .
                            (new moodle_url('/course/index.php', ['categoryid' => $id])) . '">' . $name . '</a></li>';
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
        if (stripos($text, '{refer') !== false) {
            if (stripos($text, '{referer}') !== false) {
                if ($CFG->branch >= 28) {
                    $replace['/\{referer\}/i'] = get_local_referer(false);
                } else {
                    $replace['/\{referer\}/i'] = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
                }
            }
            if (stripos($text, '{referrer}') !== false) {
                if ($CFG->branch >= 28) {
                    $replace['/\{referrer\}/i'] = get_local_referer(false);
                } else {
                    $replace['/\{referrer\}/i'] = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
                }
            }
        }

        if (stripos($text, '{www') !== false) {
            // Tag: {wwwroot}.
            if (stripos($text, '{wwwroot}') !== false) {
                $replace['/\{wwwroot\}/i'] = $CFG->wwwroot;
            }

            // Tag: {wwwcontactform}.
            if (stripos($text, '{wwwcontactform') !== false) {
                $replace['/\{wwwcontactform\}/i'] = $CFG->wwwroot . '/local/contact/index.php';
            }
        }

        // Tag: {pagepath}.
        if (stripos($text, '{pagepath}') !== false) {
            $url = (is_object($PAGE->url) ? $PAGE->url->out_as_local_url() : '');
            if (strpos($url, '?') === false && strpos($url, '#') === false) {
                $url .= '?';
            }
            $replace['/\{pagepath\}/i'] = $url;
        }

        if (stripos($text, '{thisurl') !== false) {
            $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") .
                    "://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
            // Tag: {thisurl}.
            if (stripos($text, '{thisurl}') !== false) {
                $replace['/\{thisurl\}/i'] = $url;
            }
            // Tag: {thisurl_enc}.
            if (stripos($text, '{thisurl_enc}') !== false) {
                $replace['/\{thisurl_enc\}/i'] = urlencode($url);
            }
        }

        // Tag: {protocol}.
        if (stripos($text, '{protocol}') !== false) {
            $replace['/\{protocol\}/i'] = 'http' . ($this->ishttps() ? 's' : '');
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

        // Tag: {sectionid}.
        if (stripos($text, '{sectionid}') !== false) {
            $replace['/\{sectionid\}/i'] = @$PAGE->cm->sectionnum;
        }

        // Alternative Tag: %7Bsectionid%7D.
        if (stripos($text, '%7Bsectionid%7D') !== false) {
            $replace['/\%7Bsectionid%7D/i'] = @$PAGE->cm->sectionnum;
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

        // Tag: {getstring:component_name}stringidentifier{/getstring} or {getstring}stringidentifier{/getstring}.
        // If component_name (plugin) is not specified, will default to "moodle".
        if (stripos($text, '{/getstring}') !== false) {
            // Replace {getstring:} tag and parameters with retrieved content.
            $newtext = preg_replace_callback('/\{getstring:?(\w*)\}(\w+)\{\/getstring\}/is',
                function($matches) {
                    if (get_string_manager()->string_exists($matches[2], $matches[1])) {
                        return get_string($matches[2], $matches[1]);
                    } else {
                        return "{getstring" . (!empty($matches[1]) ? ":$matches[1]" : '') . "}$matches[2]{/getstring}";
                    }
                }, $text);
            if ($newtext !== false) {
                $text = $newtext;
                $changed = true;
            }
        }

        // Tag: {fa fa-icon-name}.
        if (stripos($text, '{fa') !== false) {
            // Replace {fa...} tag and parameters with FontAwesome HTML.
            $newtext = preg_replace_callback('/\{fa(s|r|l|b){0,1}\sfa-(.*?)\}/i',
                function ($matches) {
                    return '<span class="' . substr($matches[0], 1, -1) . '" aria-hidden="true"></span>';
                }, $text);
            if ($newtext !== false) {
                $text = $newtext;
                $changed = true;
            }
        }

        // Tag: {glyphicon glyphion-name}.
        if (stripos($text, '{glyphicon ') !== false) {
            // Replace {glyphicon glyphicon-...} tag and parameters with Glyphicons HTML.
            $newtext = preg_replace_callback('/\{glyphicon\sglyphicon-(.*?)\}/i',
                function ($matches) {
                    return '<span class="' . substr($matches[0], 1, -1) . '" aria-hidden="true"></span>';
                }, $text);
            if ($newtext !== false) {
                $text = $newtext;
                $changed = true;
            }
        }

        // Tag: {/highlight}{/highlight}.
        if (stripos($text, '{/highlight}') !== false) {
            $replace['/\{highlight\}/i'] = '<span style="background-color:#FFFF00;">';
            $replace['/\{\/highlight\}/i'] = '</span>';
        }

        //
        // HTML tagging.
        //

        // Tag: {nbsp}.
        if (stripos($text, '{nbsp}') !== false) {
            $replace['/\{nbsp\}/i'] = '&nbsp;';
        }

        // Tag: {langx xx}.
        if (stripos($text, '{langx ') !== false) {
            $replace['/\{langx\s+(.*?)\}(.*?)\{\/langx\}/ims'] = '<span lang="$1">$2</span>';
        }

        // Tag: {details}{/details}.
        // Tag: {summary}{/summary}.
        if (stripos($text, '{/details}') !== false) {
            $replace['/\{details\}/i'] = '<details>';
            $replace['/\{details open\}/i'] = '<details open>';
            $replace['/\{\/details\}/i'] = '</details>';
            $replace['/\{summary\}/i'] = '<summary>';
            $replace['/\{\/summary\}/i'] = '</summary>';
        }

        // Conditional block tags.

        if (strpos($text, '{if') !== false) { // If there are conditional tags.

            // Tags: {ifenrolled}. and {ifnotenrolled}.
            // Tags: {ifincourse} and {ifinsection}.
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
                // Remove the {ifincourse} strings if not in a course or on the Front Page.
                if (stripos($text, '{ifincourse}') !== false) {
                    $replace['/\{ifincourse\}(.*?)\{\/ifincourse\}/ims'] = '';
                }
                // Remove the {ifinsection} strings if not in a section of a course or are on the Front Page.
                if (stripos($text, '{ifinsection}') !== false) {
                    $replace['/\{ifinsection\}(.*?)\{\/ifinsection\}/ims'] = '';
                }
            } else {
                if ($this->hasarchetype('student')) { // If user is enrolled in the course.
                    // If enrolled, remove the {ifenrolled} tags.
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
                // Tag: {ifincourse}. If in a course other than the Front Page, remove the ifincourse tags.
                if (stripos($text, '{ifincourse}') !== false) {
                    $replace['/\{ifincourse\}/i'] = '';
                    $replace['/\{\/ifincourse\}/i'] = '';
                }
                // Tag: {ifinsection}. If in a section of a course other than the Front Page, remove the ifinsection tags.
                if (stripos($text, '{ifinsection}') !== false) {
                    if (!empty(@$PAGE->cm->sectionnum)) {
                        $replace['/\{ifinsection\}/i'] = '';
                        $replace['/\{\/ifinsection\}/i'] = '';
                    } else {
                        // Remove the ifinsection strings.
                        if (stripos($text, '{ifinsection}') !== false) {
                            $replace['/\{ifinsection\}(.*?)\{\/ifinsection\}/ims'] = '';
                        }
                    }
                }
            }
            // Tag: {ifnotinsection}. If not in a section of a course.
            if (stripos($text, '{ifnotinsection}') !== false) {
                if (empty(@$PAGE->cm->sectionnum)) {
                    $replace['/\{ifnotinsection\}/i'] = '';
                    $replace['/\{\/ifnotinsection\}/i'] = '';
                } else {
                    // Remove the ifnotinsection strings.
                    if (stripos($text, '{ifnotinsection}') !== false) {
                        $replace['/\{ifnotinsection\}(.*?)\{\/ifnotinsection\}/ims'] = '';
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

            // Tag: {ifdashboard}.
            if (stripos($text, '{ifdashboard}') !== false) {
                if ($PAGE->pagetype == 'my-index') { // If dashboard.
                    // Just remove the tags.
                    $replace['/\{ifdashboard\}/i'] = '';
                    $replace['/\{\/ifdashboard\}/i'] = '';
                } else {
                    // If not not on the front page, remove the ifdashboard text.
                    $replace['/\{ifdashboard}(.*?)\{\/ifdashboard\}/ims'] = '';
                }
            }

            // Tag: {ifhome}.
            if (stripos($text, '{ifhome}') !== false) {
                if ($PAGE->pagetype == 'site-index') { // If front page.
                    // Just remove the tags.
                    $replace['/\{ifhome\}/i'] = '';
                    $replace['/\{\/ifhome\}/i'] = '';
                } else {
                    // If not not on the front page, remove the ifhome text.
                    $replace['/\{ifhome}(.*?)\{\/ifhome\}/ims'] = '';
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
                // Tag: {note} - Used to add notes which appear when editing but not displayed.
                if (stripos($text, '{note}') !== false) {
                    // Remove the note content.
                    $replace['/\{note\}(.*?)\{\/note\}/ims'] = '';
                }

            }

        }

        // Apply all of the filtercodes at once.
        $newtext = null;
        if (count($replace) > 0) {
            $newtext = preg_replace(array_keys($replace), array_values($replace), $text);
        }
        if (!is_null($newtext)) {
            $text = $newtext;
            $changed = true;
        }

        // Tag: {urlencode}content{/urlencode}.
        if (stripos($text, '{urlencode}') !== false) {
            // Replace {urlencode} tags and content with encoded content.
            $newtext = preg_replace_callback('/\{urlencode}(.*?)\{\/urlencode\}/is',
                function($matches) {
                    return urlencode($matches[1]);
                }, $text);
            if ($newtext !== false) {
                $text = $newtext;
                $changed = true;
            }
        }

        // Handle escaped tags.

        // Replace double bracketed tags with single brackets.
        if ($doublesescapes) {
            $text = str_replace(chr(2), '{', $text);
            $text = str_replace(chr(3), '}', $text);
        }
        // Replace bracketed encoded tags with encoded tags.
        if ($escapesencoded) {
            $text = str_replace(chr(4), '%7B', $text);
            $text = str_replace(chr(5), '%7D', $text);
        }

        return $text;
    }
}
