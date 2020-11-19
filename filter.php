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
 * @copyright  2017-2020 TNG Consulting Inc. - www.tngconsulting.ca
 * @author     Michael Milette
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use block_online_users\fetcher;

require_once($CFG->dirroot . '/course/renderer.php');

/**
 * Extends the moodle_text_filter class to provide plain text support for new tags.
 *
 * @copyright  2017-2020 TNG Consulting Inc. - www.tngconsulting.ca
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filter_filtercodes extends moodle_text_filter {
    /** @var object $archetypes Object array of Moodle archetypes. */
    public $archetypes = [];
    /** @var array $customroles array of Roles key is shortname and value is the id */
    private static $customroles = [];
    /**
     * @var array $customrolespermissions array of Roles key is shortname + context_id and the value is a boolean showing if
     * user is allowed
     */
    private static $customrolespermissions = [];

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
     * Checks if a user has a custom role or not within the current context.
     *
     * @param string $roleshortname The role's shortname.
     * @param integer $contextid The context where the tag appears.
     * @return boolean True if user has custom role, otherwise, false.
     */
    private function hascustomrole($roleshortname, $contextid = 0) {
        $keytocheck = $roleshortname . '-' . $contextid;
        if (!isset(self::$customrolespermissions[$keytocheck])) {
            global $USER, $DB;
            if (!isset(self::$customroles[$roleshortname])) {
                self::$customroles[$roleshortname] = $DB->get_field('role', 'id', ['shortname' => $roleshortname]);
            }
            $hasrole = false;
            if (self::$customroles[$roleshortname]) {
                $hasrole = user_has_role_assignment($USER->id, self::$customroles[$roleshortname], $contextid);
            }
            self::$customrolespermissions[$keytocheck] = $hasrole;
        }

        return self::$customrolespermissions[$keytocheck];
    }

    /**
     * Retrieves the URL for the user's profile picture, if one is available.
     *
     * @param object $user The Moodle user object for which we want a photo.
     * @return string URL to the photo image file but with $1 for the size.
     */
    private function getprofilepictureurl($user) {
        if (isloggedin() && !isguestuser() && $user->picture > 0) {
            $usercontext = context_user::instance($user->id, IGNORE_MISSING);
            $url = moodle_url::make_pluginfile_url($usercontext->id, 'user', 'icon', null, '/', "f$1")
                    . '?rev=' . $user->picture;
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
            // If Moodle reCAPTCHA configured.
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
     * Convert a number of bytes (e.g. filesize) into human readable format.
     *
     * @param float $bytes Raw number of bytes.
     * @return string Bytes in human readable format.
     */
    private function humanbytes($bytes) {
        if ($bytes === false || $bytes < 0 || is_null($bytes) || $bytes > 1.0E+26) {
            // If invalid number of bytes, or value is more than about 84,703.29 Yottabyte (YB), assume it is infinite.
            $str = '&infin;'; // Could not determine, assume infinite.
        } else {
            static $unit;
            if (!isset($unit)) {
                $units = ['sizeb', 'sizekb', 'sizemb', 'sizegb', 'sizetb', 'sizeeb', 'sizezb', 'sizeyb'];
                $units = get_strings($units, 'filter_filtercodes');
                $units = array_values((array) $units);
            }
            $base = 1024;
            $factor = min((int) log($bytes, $base), count($units) - 1);
            $precision = [0, 2, 2, 1, 1, 1, 1, 0];
            $str = sprintf("%1.{$precision[$factor]}f", $bytes / pow($base, $factor)) . ' ' . $units[$factor];
        }
        return $str;
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

        // Handle escaped tags to be ignored.

        // Determine if the option to {escape braces}] is enabled.
        if (!empty(get_config('filter_filtercodes', 'escapebraces'))) {
            // Temporarily escaped tags these with non-printable character. Will be re-adjusted after processing tags.
            $escapedtags = (strpos($text, '[{') !== false && strpos($text, '}]') !== false);
            if ($escapedtags) {
                $text = str_replace('[{', chr(2), $text);
                $text = str_replace('}]', chr(3), $text);
            }
            // Temporarily escaped tags these with non-printable character. Will be re-adjusted after processing tags.
            $escapedtagsenc = (strpos($text, '[%7B') !== false && strpos($text, '%7D]') !== false);
            if ($escapedtagsenc) {
                $text = str_replace('[%7B', chr(4), $text);
                $text = str_replace('%7D]', chr(5), $text);
            }
        } else {
            $escapedtags = false;
            $escapedtagsenc = false;
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

            // Tag: {formsesskey}.
            if (stripos($text, '{formsesskey}') !== false) {
                $replace['/\{formsesskey\}/i'] = '<input type="hidden" id="sesskey" name="sesskey" value="">';
                $replace['/\{formsesskey\}/i'] .= '<script>document.getElementById(\'sesskey\').value = M.cfg.sesskey;</script>';
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

        //
        // FilterCodes extended (future feature).
        //
        if (file_exists(dirname(__FILE__) . '/filter-ext.php')) {
            include(dirname(__FILE__) . '/filter-ext.php');
        }

        // Tag: {filtercodes}. Show version of FilterCodes, but only if you have permission to add the tag.
        if (stripos($text, '{filtercodes}') !== false) {
            // If you have the ability to edit the content.
            if (has_capability('moodle/course:update', $PAGE->context)) {
                // Show the version of the FilterCodes plugin.
                $plugin = new stdClass();
                require($CFG->dirroot . '/filter/filtercodes/version.php');
                $replace['/\{filtercodes\}/i'] = "$plugin->release ($plugin->version)";
            } else {
                $replace['/\{filtercodes\}/i'] = '';
            }
        }

        if (stripos($text, '{profile') !== false) {

            // Tag: {profile_field_...}.
            // Custom Profile Fields.
            if (stripos($text, '{profile_field') !== false) {
                if (isloggedin() && !isguestuser()) {
                    // Cached the defined custom profile fields and data.
                    static $profilefields;
                    static $profiledata;
                    if (!isset($profilefields)) {
                        $profilefields = $DB->get_records('user_info_field', null, '', 'id, shortname, visible');
                        if (!empty($profilefields)) {
                            $profiledata = $DB->get_records_menu('user_info_data', ['userid' => $USER->id], '', 'fieldid, data');
                        }
                    }

                    foreach ($profilefields as $field) {
                        // If the tag exists and is not set to "Not visible" in the custom profile field's settings.
                        if (stripos($text, '{profile_field_' . $field->shortname . '}') !== false && $field->visible != '0') {
                            $data = !empty($profiledata[$field->id]) ? $profiledata[$field->id] : '';
                            $replace['/\{profile_field_' . $field->shortname . '\}/i'] = $data;
                        } else {
                            $replace['/\{profile_field_' . $field->shortname . '\}/i'] = '';
                        }
                    }
                }
            }

            // Tag: {profilefullname}.
            if (stripos($text, '{profilefullname}') !== false) {
                $fullname = '';
                if (isloggedin() && !isguestuser()) {
                    $fullname = $USER->firstname . ' ' . $USER->lastname;
                    if ($PAGE->pagelayout == 'mypublic' && $PAGE->pagetype == 'user-profile') {
                        $userid = optional_param('userid', optional_param('user',
                                optional_param('id', $USER->id, PARAM_INT), PARAM_INT), PARAM_INT);
                        if ($user = $DB->get_record('user', array('id' => $userid, 'deleted' => 0))) {
                            $fullname = $user->firstname . ' ' . $user->lastname;
                        }
                    }
                }
                $replace['/\{profilefullname\}/i'] = $fullname;
                unset($fullname);
            }
        }

        // Substitutions.

        if (isloggedin() && !isguestuser()) {
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
            if (isloggedin() && !isguestuser() && !empty(trim($USER->alternatename))) {
                $replace['/\{alternatename\}/i'] = $USER->alternatename;
            } else {
                $replace['/\{alternatename\}/i'] = $firstname;
            }
        }

        // Tag: {email}.
        if (stripos($text, '{email}') !== false) {
            $replace['/\{email\}/i'] = isloggedin() && !isguestuser() ? $USER->email : '';
        }

        // Tag: {city}.
        if (stripos($text, '{city}') !== false) {
            $replace['/\{city\}/i'] = isloggedin() && !isguestuser() ? $USER->city : '';
        }

        // Tag: {country}.
        if (stripos($text, '{country}') !== false) {
            $replace['/\{country\}/i'] = isloggedin() && !isguestuser() && !empty($USER->country)
                    ? get_string($USER->country, 'countries') : '';
        }

        // Tag: {timezone}.
        if (stripos($text, '{timezone}') !== false) {
            $replace['/\{timezone\}/i'] = isloggedin() && !isguestuser() && !empty($USER->timezone)
                    ? core_date::get_localised_timezone($USER->timezone) : '';
        }

        // Tag: {preferredlanguage}.
        if (stripos($text, '{preferredlanguage}') !== false) {
            if (isloggedin() && !isguestuser()) {
                if ('en' == $USER->lang) {
                    $langconfig = $CFG->dirroot . '/lang/en/langconfig.php';
                } else {
                    $langconfig = $CFG->dataroot . '/lang/' . $USER->lang . '/langconfig.php';
                }
                // Ignore parents here for now.
                $string = array();
                include($langconfig);
                if (!empty($string['thislanguage'])) {
                    $replace['/\{preferredlanguage\}/i'] = '<span lang="' . $string['iso6391'] . '">' . $string['thislanguage']
                            . '</span>';
                } else { // This should never happen since the known user already exists.
                    $replace['/\{preferredlanguage\}/i'] = get_string('unknown', 'notes');
                }
            } else {
                $replace['/\{preferredlanguage\}/i'] = '';
            }
        }

        // Tag: {institution}.
        if (stripos($text, '{institution}') !== false) {
            $replace['/\{institution\}/i'] = isloggedin() && !isguestuser() ? $USER->institution : '';
        }

        // Tag: {webpage}.
        if (stripos($text, '{webpage}') !== false) {
            $replace['/\{webpage\}/i'] = isloggedin() && !isguestuser() ? $USER->url : '';
        }

        // Tag: {department}.
        if (stripos($text, '{department}') !== false) {
            $replace['/\{department\}/i'] = isloggedin() && !isguestuser() ? $USER->department : '';
        }

        // Tag: {idnumber}.
        if (stripos($text, '{idnumber}') !== false) {
            $replace['/\{idnumber\}/i'] = isloggedin() && !isguestuser() ? $USER->idnumber : '';
        }

        // Tag: {firstaccessdate} or {firstaccessdate dateTimeFormat}.
        if (stripos($text, '{firstaccessdate') !== false) {
            if (isloggedin() && !isguestuser() && !empty($USER->firstaccess)) {
                // Replace {firstaccessdate} tag with formatted date.
                if (stripos($text, '{firstaccessdate}') !== false) {
                    $replace['/\{firstaccessdate\}/i'] = userdate($USER->firstaccess, get_string('strftimedatefullshort'));
                }
                // Replace {firstaccessdate dateTimeFormat} tag and parameters with formatted date.
                if (stripos($text, '{firstaccessdate ') !== false) {
                    $newtext = preg_replace_callback('/\{firstaccessdate\s+(.+)\}/i',
                        function ($matches) use ($USER) {
                            // Hack to remove everything after the closing }, if it is still there.
                            // TODO: Improve regex above to support PHP strftime strings.
                            $matches[1] = strtok($matches[1], '}');
                            // Check if this is a built-in Moodle date/time format.
                            if (get_string_manager()->string_exists($matches[1], 'langconfig')) {
                                // It is! Get the strftime string.
                                $matches[1] = get_string($matches[1], 'langconfig');
                            }
                            return userdate($USER->firstaccess, $matches[1]);
                        },
                        $text
                    );
                    if ($newtext !== false) {
                        $text = $newtext;
                        $changed = true;
                    }
                }
            } else {
                $replace['/\{firstaccessdate(.*)\}/i'] = get_string('never');
            }
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

        // Tag: {diskfreespace} - free space of Moodle application volume.
        if (stripos($text, '{diskfreespace}') !== false) {
            $bytes = @disk_free_space('.');
            $replace['/\{diskfreespace\}/i'] = $this->humanbytes($bytes);
        }

        // Tag: {diskfreespacedata} - free space of Moodledata volume.
        if (stripos($text, '{diskfreespacedata}') !== false) {
            $bytes = @disk_free_space($CFG->dataroot);
            $replace['/\{diskfreespacedata\}/i'] = $this->humanbytes($bytes);
        }

        // Any {user*} tags.
        if (stripos($text, '{user') !== false || stripos($text, '%7Buser') !== false) {

            // Tag: {username}.
            if (stripos($text, '{username}') !== false) {
                $replace['/\{username\}/i'] = isloggedin()
                        && !isguestuser() ? $USER->username : get_string('defaultusername', 'filter_filtercodes');
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

            // Tag: {userdescription}.
            if (stripos($text, '{userdescription}') !== false) {
                if (isloggedin() && !isguestuser()) {
                    $user = $DB->get_record('user', array('id' => $USER->id), 'description', MUST_EXIST);
                    $replace['/\{userdescription\}/i'] = format_text($user->description, $USER->descriptionformat);
                    unset($user);
                } else {
                    $replace['/\{userdescription\}/i'] = '';
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

            // Custom Course Fields - First implemented in Moodle 3.7.
            if ($CFG->branch >= 37) {
                // Tag: {course_field_shortname}.
                if (stripos($text, '{course_field_') !== false) {
                    // Cached the custom course field data.
                    static $coursefields;
                    if (!isset($coursefields)) {
                        $handler = core_course\customfield\course_handler::create();
                        $coursefields = $handler->export_instance_data_object($PAGE->course->id, true);
                        $fieldsvisible = $handler->export_instance_data_object($PAGE->course->id);
                        // Blank out the fields that should not be displayed.
                        foreach ($coursefields as $field => $value) {
                            if (empty($fieldsvisible->$field)) {
                                $coursefields->$field = '';
                            }
                        }
                    }
                    foreach ($coursefields as $field => $value) {
                        $shortname = strtolower($field);
                        // If the tag exists and it is not hidden in the custom course field's settings.
                        if (stripos($text, '{course_field_' . $shortname . '}') !== false) {
                            $replace['/\{course_field_' . $shortname . '\}/i'] = $value;
                        }
                    }
                }

                // Tag: {course_fields}.
                if (stripos($text, '{course_fields}') !== false) {
                    // Display all custom course fields.
                    $customfields = '';
                    if ($PAGE->course instanceof stdClass) {
                        $thiscourse = new \core_course_list_element($PAGE->course);
                    }
                    if ($thiscourse->has_custom_fields()) {
                        $handler = \core_course\customfield\course_handler::create();
                        $customfields = $handler->display_custom_fields_data($thiscourse->get_custom_fields());
                    }
                    $replace['/\{course_fields\}/i'] = $customfields;
                }

            }

            // Tag: {courseparticipantcount}.
            if (stripos($text, '{courseparticipantcount}') !== false) {
                require_once($CFG->dirroot . '/user/lib.php');
                $cnt = \user_get_total_participants($PAGE->course->id);
                $replace['/\{courseparticipantcount\}/i'] = $cnt;
            }

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
                    $replace['/\{courseshortname\}/i'] = format_string($SITE->shortname);
                } else { // In a course - use course full name.
                    $coursecontext = context_course::instance($course->id);
                    $replace['/\{courseshortname\}/i'] = format_string($course->shortname, true, ['context' => $coursecontext]);
                }
            }

            // Tag: {coursesummary}.
            if (stripos($text, '{coursesummary}') !== false) {
                $replace['/\{coursesummary\}/i'] = $PAGE->course->summary;
            }

            // Tag: {courseimage}. The course image.
            if (stripos($text, '{courseimage') !== false) {
                $course = $PAGE->course;
                $imgurl = '';
                $context = context_course::instance($course->id);
                if ($course instanceof stdClass) {
                    $course = new \core_course_list_element($course);
                }
                $coursefiles = $course->get_course_overviewfiles();
                foreach ($coursefiles as $file) {
                    if ($isimage = $file->is_valid_image()) {
                        $imgurl = file_encode_url("/pluginfile.php", '/' . $file->get_contextid() . '/' . $file->get_component()
                                . '/' . $file->get_filearea() . $file->get_filepath() . $file->get_filename() , !$isimage);
                        $imgurl = new moodle_url($imgurl);
                        break;
                    }
                }
                if (empty($imgurl)) {
                    global $OUTPUT;
                    $imgurl = $OUTPUT->get_generated_image_for_id($course->id);
                }
                $replace['/\{courseimage\}/i'] = '<img src="' . $imgurl . '" class="img-responsive">';
            }

            // Tag: {coursestartdate} or {coursestartdate dateTimeFormat}. The course start date.
            if (stripos($text, '{coursestartdate') !== false) {
                if (empty($PAGE->course->startdate)) {
                    $PAGE->course->startdate = $DB->get_field_select(
                            'course', 'startdate', 'id = :id', ['id' => $PAGE->course->id]
                    );
                }
                if (!empty($PAGE->course->startdate)) {
                    // Replace {coursestartdate} tag with formatted date.
                    if (stripos($text, '{coursestartdate}') !== false) {
                        $replace['/\{coursestartdate\}/i'] = userdate($PAGE->course->startdate,
                                get_string('strftimedatefullshort'));
                    }
                    // Replace {coursestartdate dateTimeFormat} tag and parameters with formatted date.
                    if (stripos($text, '{coursestartdate ') !== false) {
                        $newtext = preg_replace_callback('/\{coursestartdate\s+(.+)\}/i',
                            function ($matches) use ($PAGE) {
                                // Hack to remove everything after the closing }, if it is still there.
                                // TODO: Improve regex above to support PHP strftime strings.
                                $matches[1] = strtok($matches[1], '}');
                                // Check if this is a built-in Moodle date/time format.
                                if (get_string_manager()->string_exists($matches[1], 'langconfig')) {
                                    // It is! Get the strftime string.
                                    $matches[1] = get_string($matches[1], 'langconfig');
                                }
                                return userdate($PAGE->course->startdate, $matches[1]);
                            },
                            $text
                        );
                        if ($newtext !== false) {
                            $text = $newtext;
                            $changed = true;
                        }
                    }
                } else {
                    $replace['/\{coursestartdate(.*)\}/i'] = get_string('notyetstarted', 'completion');
                }
            }

            // Tag: {courseenddate} or {coursesenddate dateTimeFormat}. The course end date.
            if (stripos($text, '{courseenddate') !== false) {
                if (empty($PAGE->course->enddate)) {
                    $PAGE->course->enddate = $DB->get_field_select('course', 'enddate', 'id = :id', ['id' => $PAGE->course->id]);
                }
                if (!empty($PAGE->course->enddate)) {
                    // Replace {courseenddate} tag with formatted date.
                    if (stripos($text, '{courseenddate}') !== false) {
                        $replace['/\{courseenddate\}/i'] = userdate($PAGE->course->enddate, get_string('strftimedatefullshort'));
                    }
                    // Replace {courseenddate dateTimeFormat} tag and parameters with formatted date.
                    if (stripos($text, '{courseenddate ') !== false) {
                        $newtext = preg_replace_callback('/\{courseenddate\s+(.+)\}/i',
                            function ($matches) use ($PAGE) {
                                // Hack to remove everything after the closing }, if it is still there.
                                // TODO: Improve regex above to support PHP strftime strings.
                                $matches[1] = strtok($matches[1], '}');
                                // Check if this is a built-in Moodle date/time format.
                                if (get_string_manager()->string_exists($matches[1], 'langconfig')) {
                                    // It is! Get the strftime string.
                                    $matches[1] = get_string($matches[1], 'langconfig');
                                }
                                return userdate($PAGE->course->enddate, $matches[1]);
                            },
                            $text
                        );
                        if ($newtext !== false) {
                            $text = $newtext;
                            $changed = true;
                        }
                    }
                } else { // No end date has been set.
                    $replace['/\{courseenddate(.*)\}/i'] = get_string('none');
                }
            }

            // Tag: {coursecompletiondate} or {coursecompletiondate dateTimeFormat}. The course completion date.
            if (stripos($text, '{coursecompletiondate') !== false) {
                if ($PAGE->course
                        && isset($CFG->enablecompletion)
                        && $CFG->enablecompletion == COMPLETION_ENABLED
                        && $PAGE->course->enablecompletion) {
                    $ccompletion = new completion_completion(['userid' => $USER->id, 'course' => $PAGE->course->id]);
                    $incomplete = get_string('notcompleted', 'completion');
                } else { // Completion not enabled.
                    $incomplete = get_string('completionnotenabled', 'completion');
                }
                if (!empty($ccompletion->timecompleted)) {
                    // Replace {coursecompletiondate} tag with formatted date.
                    if (stripos($text, '{coursecompletiondate}') !== false) {
                        $replace['/\{coursecompletiondate\}/i'] = userdate($ccompletion->timecompleted,
                                get_string('strftimedatefullshort'));
                    }
                    // Replace {coursecompletiondate dateTimeFormat} tag and parameters with formatted date.
                    if (stripos($text, '{coursecompletiondate ') !== false) {
                        $newtext = preg_replace_callback('/\{coursecompletiondate\s+(.+)\}/i',
                            function($matches) use ($ccompletion) {
                                // Hack to remove everything after the closing }, if it is still there.
                                // TODO: Improve regex above to support PHP strftime strings.
                                $matches[1] = strtok($matches[1], '}');
                                // Check if this is a built-in Moodle date/time format.
                                if (get_string_manager()->string_exists($matches[1], 'langconfig')) {
                                    // It is! Get the strftime string.
                                    $matches[1] = get_string($matches[1], 'langconfig');
                                }
                                return userdate($ccompletion->timecompleted, $matches[1]);
                            }, $text);
                        if ($newtext !== false) {
                            $text = $newtext;
                            $changed = true;
                        }
                    }
                } else {
                    $replace['/\{coursecompletiondate(.*)\}/i'] = $incomplete;
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

            // Tag: {courseprogress} and {courseprogressbar}.
            // Course progress percentage as text.
            if (stripos($text, '{courseprogress') !== false) {
                $comppercent = -1; // Disabled: -1.
                if ($PAGE->course->enablecompletion == 1
                        && isloggedin()
                        && !isguestuser()
                        && context_system::instance() != 'page-site-index') {
                    $comppc = \core_completion\progress::get_course_progress_percentage($PAGE->course);
                    // Course completion progress percentage.
                    $comppercent = number_format($comppc, 0);
                    if (stripos($text, '{courseprogress}') !== false) {
                        $replace['/\{courseprogress\}/i'] = '<span class="sr-only">'
                                . get_string('aria:courseprogress', 'block_myoverview') . '</span> '
                                . get_string('completepercent', 'block_myoverview', $comppercent);
                    }
                    // Course completion progress bar.
                    if (stripos($text, '{courseprogressbar}') !== false) {
                        $replace['/\{courseprogressbar\}/i'] = '
                                <div class="progress">
                                    <div class="progress-bar bar" role="progressbar" aria-valuenow="' . $comppercent
                                        . '" style="width: ' . $comppercent . '%" aria-valuemin="0" aria-valuemax="100">
                                    </div>
                                </div>';
                    }
                } else {
                    $replace['/\{courseprogress\}/i'] = '';
                    $replace['/\{courseprogressbar\}/i'] = '';
                }
            }

            if (stripos($text, '{coursecards}') !== false) {
                global $CFG, $OUTPUT;

                $chelper = new coursecat_helper();
                $chelper->set_show_courses(20)->set_courses_display_options(array(
                    'recursive' => true,
                    'limit' => $CFG->frontpagecourselimit,
                    'viewmoreurl' => new moodle_url('/course/index.php'),
                    'viewmoretext' => new lang_string('fulllistofcourses')
                ));

                $chelper->set_attributes(array('class' => 'frontpage-course-list-all'));
                $courses = core_course_category::get(0)->get_courses($chelper->get_courses_display_options());

                $rcourseids = array_keys($courses);

                $header = '<div class="card-deck mr-0">';
                $footer = '</div>';
                $content = '';
                if (count($rcourseids) > 0) {
                    foreach ($rcourseids as $courseid) {
                        $course = get_course($courseid);

                        // Load image from course image. If none, generate a course image based on the course ID.
                        $context = context_course::instance($courseid);
                        if ($course instanceof stdClass) {
                            $course = new \core_course_list_element($course);
                        }
                        $coursefiles = $course->get_course_overviewfiles();
                        $imgurl = '';
                        foreach ($coursefiles as $file) {
                            if ($isimage = $file->is_valid_image()) {
                                $imgurl = file_encode_url("/pluginfile.php", '/' . $file->get_contextid() . '/'
                                        . $file->get_component() . '/' . $file->get_filearea() . $file->get_filepath()
                                        . $file->get_filename(), !$isimage);
                                $imgurl = new moodle_url($imgurl);
                                break;
                            }
                        }
                        if (empty($imgurl)) {
                            $imgurl = $OUTPUT->get_generated_image_for_id($courseid);
                        }
                        $courseurl = new moodle_url('/course/view.php', array('id' => $courseid ));
                        $content .= '
                            <div class="card shadow mr-4 mb-4 ml-1" style="min-width:300px;max-width:300px;">
                                <a href="' . $courseurl . '" class="text-normal h-100">
                                <div class="card-img-top" style="background-image:url(' . $imgurl
                                        . ');height:100px;max-width:300px;padding-top:50%;background-size:cover;'
                                        . 'background-repeat:no-repeat;background-position:center;"></div>
                                <div class="card-title pt-1 pr-3 pb-1 pl-3 m-0">' . $course->get_formatted_name() . '</div>
                                </a>
                            </div>
                        ';
                    }
                }
                $replace['/\{coursecards\}/i'] = !empty($header) ? $header . $content . $footer : '';
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
                    $context = context_system::instance();
                    if (!empty($CFG->enablecourserequests) && has_capability('moodle/course:request', $context)) {
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
                    $context = context_system::instance();
                    if (!empty($CFG->enablecourserequests) && has_capability('moodle/course:request', $context)) {
                        $list .= '-###' . PHP_EOL;
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

        // Tag: {now} or {now dateTimeFormat}.
        if (stripos($text, '{now') !== false) {
            // Replace {now} tag with formatted date.
            $now = time();
            if (stripos($text, '{now}') !== false) {
                $replace['/\{now\}/i'] = userdate($now, get_string('strftimedatefullshort'));
            }
            // Replace {now dateTimeFormat} tag and parameters with formatted date.
            if (stripos($text, '{now ') !== false) {
                $newtext = preg_replace_callback('/\{now\s+(.+)\}/im',
                    function ($matches) use ($now) {
                        // Hack to remove everything after the closing }, if it is still there.
                        // TODO: Improve regex above to support PHP strftime strings.
                        $matches[1] = strtok($matches[1], '}');
                        // Check if this is a built-in Moodle date/time format.
                        if (get_string_manager()->string_exists($matches[1], 'langconfig')) {
                            // It is! Get the strftime string.
                            $matches[1] = get_string($matches[1], 'langconfig');
                        }
                        return userdate($now, $matches[1]);
                    },
                    $text
                );
                if ($newtext !== false) {
                    $text = $newtext;
                    $changed = true;
                }
            }
            unset($now);
        }

        // Tag: {editingmode}. Is "off" if in edit page mode. Otherwise "on". Useful for creating Turn Editing On/Off links.
        if (stripos($text, '{editingtoggle}') !== false) {
            $replace['/\{editingtoggle\}/i'] = ($PAGE->user_is_editing() ? 'off' : 'on');
        }

        // Tag: {toggleeditingmenu}. Creates menu link to toggle editing on and off.
        if (stripos($text, '{toggleeditingmenu}') !== false) {
            $editmode = ($PAGE->user_is_editing() ? 'off' : 'on');
            $edittext = get_string('turnediting' . $editmode);
            if ($PAGE->bodyid == 'page-site-index' && $PAGE->pagetype == 'site-index') { // Front page.
                $replace['/\{toggleeditingmenu\}/i'] = $edittext . '|' . (new moodle_url('/course/view.php',
                        ['id' => $PAGE->course->id, 'sesskey' => sesskey(), 'edit' => $editmode]));
            } else { // All other pages.
                $replace['/\{toggleeditingmenu\}/i'] = $edittext . '|' . (new moodle_url($PAGE->url,
                        ['edit' => $editmode, 'adminedit' => $editmode, 'sesskey' => sesskey()])) . PHP_EOL;
            }
        }

        // Tags: {category...}.
        if (stripos($text, '{categor') !== false) {

            if (empty($PAGE->course->category)) {
                // If we are not in a course, check if categoryid is part of URL (ex: course lists).
                $catid = optional_param('categoryid', 0, PARAM_INT);
            } else {
                // Retrieve the category id of the course we are in.
                $catid = $PAGE->course->category;
            }

            // Tag: {categoryid}.
            if (stripos($text, '{categoryid}') !== false) {
                $replace['/\{categoryid\}/i'] = $catid;
            }

            if (!empty($catid)) {
                $category = $DB->get_record('course_categories', array('id' => $catid));
            }

            // Tag: {categoryname}.
            if (stripos($text, '{categoryname}') !== false) {
                if (!empty($catid)) {
                    // If category is not 0, get category name.
                    $replace['/\{categoryname\}/i'] = $category->name;
                } else {
                    // Otherwise, category has no name.
                    $replace['/\{categoryname\}/i'] = '';
                }
            }

            // Tag: {categorynumber}.
            if (stripos($text, '{categorynumber}') !== false) {
                if (!empty($catid)) {
                    // If category is not 0, get category number.
                    $replace['/\{categorynumber\}/i'] = $category->idnumber;
                } else {
                    // Otherwise, category has no number.
                    $replace['/\{categorynumber\}/i'] = '';
                }
            }

            // Tag: {categorydescription}.
            if (stripos($text, '{categorydescription}') !== false) {
                if (!empty($catid)) {
                    // If category is not 0, get category description.
                    $replace['/\{categorydescription\}/i'] = $category->description;
                } else {
                    // Otherwise, category has no description.
                    $replace['/\{categorydescription\}/i'] = '';
                }
            }

            // Tag: {categories}. An unordered list of links to categories.
            if (stripos($text, '{categories}') !== false) {
                // Retrieve list of all categories.
                if ($CFG->branch >= 36) { // Moodle 3.6+.
                    $categories = core_course_category::make_categories_list();
                } else {
                    require_once($CFG->libdir. '/coursecatlib.php');
                    $categories = coursecat::make_categories_list();
                }
                $list = '';
                foreach ($categories as $id => $name) {
                    $list .= '<li><a href="' .
                            (new moodle_url('/course/index.php', ['categoryid' => $id])) . '">' . $name . '</a></li>';
                }
                $list = !empty($list) ? '<ul class="categorylist">' . $list . '</ul>' : '';
                $replace['/\{categories\}/i'] = $list;
                unset($tag);
                unset($list);
            }

            // Tag: {categoriesmenu}. An unordered list of links to categories.
            if (stripos($text, '{categoriesmenu}') !== false) {
                // Retrieve list of all categories.
                if ($CFG->branch >= 36) { // Moodle 3.6+.
                    $categories = core_course_category::make_categories_list();
                } else {
                    require_once($CFG->libdir. '/coursecatlib.php');
                    $categories = coursecat::make_categories_list();
                }
                $list = '';
                foreach ($categories as $id => $name) {
                    $list .= '-' . $name . '|/course/index.php?categoryid=' . $id . PHP_EOL;
                }
                $replace['/\{categoriesmenu\}/i'] = $list;
                unset($tag);
                unset($list);
            }

            // Tag: {categories0}. An unordered list of links to top level categories.
            if (stripos($text, '{categories0}') !== false) {
                $sql = "SELECT cc.id, cc.sortorder, cc.name, cc.visible, cc.parent
                        FROM {course_categories} cc
                        WHERE cc.parent = 0 AND cc.visible = 1
                        ORDER BY cc.sortorder";
                $list = '';
                $categories = $DB->get_recordset_sql($sql, array('contextcoursecat' => CONTEXT_COURSECAT));
                foreach ($categories as $category) {
                    $list .= '<li><a href="' . new moodle_url('/course/index.php', ['categoryid' => $category->id])
                            . '">' . $category->name . '</a></li>' . PHP_EOL;
                }
                $list = !empty($list) ? '<ul>' . $list . '</ul>' : '';
                $categories->close();
                $replace['/\{categories0\}/i'] = $list;
                unset($list);
            }

            // Tag: {categories0menu}. A custom menu list of top level categories with links.
            if (stripos($text, '{categories0menu}') !== false) {
                $sql = "SELECT cc.id, cc.sortorder, cc.name, cc.visible, cc.parent
                        FROM {course_categories} cc
                        WHERE cc.parent = 0 AND cc.visible = 1
                        ORDER BY cc.sortorder";
                $list = '';
                $categories = $DB->get_recordset_sql($sql, array('contextcoursecat' => CONTEXT_COURSECAT));
                foreach ($categories as $category) {
                    $list .= '-' . $category->name . '|/course/index.php?categoryid=' . $category->id . PHP_EOL;
                }
                $categories->close();
                $replace['/\{categories0menu\}/i'] = $list;
                unset($list);
            }

            // Tag: {categoriesx}. An unordered list of links to current level categories.
            if (stripos($text, '{categoriesx}') !== false) {
                $sql = "SELECT cc.id, cc.sortorder, cc.name, cc.visible, cc.parent
                        FROM {course_categories} cc
                        WHERE cc.parent = $catid AND cc.visible = 1
                        ORDER BY cc.sortorder";
                $list = '';
                $categories = $DB->get_recordset_sql($sql, array('contextcoursecat' => CONTEXT_COURSECAT));
                foreach ($categories as $category) {
                    $list .= '<li><a href="' . new moodle_url('/course/index.php', ['categoryid' => $category->id]) . '">'
                            . $category->name . '</a></li>' . PHP_EOL;
                }
                $list = !empty($list) ? '<ul>' . $list . '</ul>' : '';
                $categories->close();
                $replace['/\{categoriesx\}/i'] = $list;
                unset($list);
            }

            // Tag: {categoriesxmenu}. A custom menu list of current categories with links.
            if (stripos($text, '{categoriesxmenu}') !== false) {
                $sql = "SELECT cc.id, cc.sortorder, cc.name, cc.visible, cc.parent
                        FROM {course_categories} cc
                        WHERE cc.parent = $catid AND cc.visible = 1
                        ORDER BY cc.sortorder";
                $list = '';
                $categories = $DB->get_recordset_sql($sql, array('contextcoursecat' => CONTEXT_COURSECAT));
                foreach ($categories as $category) {
                    $list .= '-' . $category->name . '|/course/index.php?categoryid=' . $category->id . PHP_EOL;
                }
                $categories->close();
                $replace['/\{categoriesxmenu\}/i'] = $list;
                unset($list);
            }

            // Tag: {categorycards}. Course categories presented as card tiles.
            if (stripos($text, '{categorycards}') !== false) {
                global $DB, $OUTPUT;

                if (empty($PAGE->course->category)) {
                    // If we are not in a course, check if categoryid is part of URL (ex: course lists).
                    $catid = optional_param('categoryid', 0, PARAM_INT);
                } else {
                    // Retrieve the category id of the course we are in.
                    $catid = $PAGE->course->category;
                }
                $sql = "SELECT cc.id, cc.sortorder, cc.name, cc.visible, cc.parent
                        FROM {course_categories} cc
                        WHERE cc.parent = $catid
                        ORDER BY cc.sortorder";
                $categories = $DB->get_recordset_sql($sql, array('contextcoursecat' => CONTEXT_COURSECAT));

                $list = '';
                foreach ($categories as $category) {
                    if (!core_course_category::can_view_category($category)) {
                        continue;
                    }

                    $dimmed = '';
                    if (!$category->visible) {
                        $dimmed = 'opacity: 0.5;';
                    }

                    $imgurl = $OUTPUT->get_generated_image_for_id($category->id + 65535);
                    $list .= '
                        <li class="card shadow mr-4 mb-4 ml-0" style="min-width:290px;max-width:290px;' . $dimmed . '">
                            <a href="' . new moodle_url('/course/index.php', ['categoryid' => $category->id])
                                . '" class="text-white h-100">
                            <div class="card-img" style="background-image: url(' . $imgurl . ');height:100px;"></div>
                            <div class="card-img-overlay card-title pt-1 pr-3 pb-1 pl-3 m-0" '
                                . 'style="height:fit-content;top:auto;background-color: rgba(0,0,0,.4);'
                                . 'text-shadow:-1px -1px 0 #767676, 1px -1px 0 #767676, -1px 1px 0 #767676, 1px 1px 0 #767676">'
                                . $category->name . '</div>
                            </a>
                        </li>' . PHP_EOL;
                }
                $categories->close();
                $replace['/\{categorycards\}/i'] = !empty($list) ? '<ul class="card-deck mr-0">' . $list . '</ul>' : '';
                unset($categories);
                unset($list);
            }

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

        // Tag: {highlight}{/highlight}.
        if (stripos($text, '{/highlight}') !== false) {
            $replace['/\{highlight\}/i'] = '<span style="background-color:#FFFF00;">';
            $replace['/\{\/highlight\}/i'] = '</span>';
        }

        // Tag: {note} - Used to add notes which appear when editing but not displayed.
        if (stripos($text, '{note}') !== false) {
            // Remove the note content.
            $replace['/\{note\}(.*?)\{\/note\}/ims'] = '';
        }

        //
        // HTML tagging.
        //

        // Tag: {nbsp}.
        if (stripos($text, '{nbsp}') !== false) {
            $replace['/\{nbsp\}/i'] = '&nbsp;';
        }

        // Tag: {lang}.
        if (stripos($text, '{lang}') !== false) {
            // Replace with 2-letter current primary language.
            $replace['/\{lang\}/i'] = substr(current_language(), 0, 2);
        }

        // Tag: {langx xx}.
        if (stripos($text, '{langx ') !== false) {
            $replace['/\{langx\s+(.*?)\}(.*?)\{\/langx\}/ims'] = '<span lang="$1">$2</span>';
        }

        // Tag: {-} - Soft hyphen.
        if (stripos($text, '{-}') !== false) {
            $replace['/\{-\}/i'] = '&shy;';
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

            // Tag: {ifloggedinas}.
            if (stripos($text, '{ifloggedinas}') !== false) {
                // If logged-in-as another user...
                if (\core\session\manager::is_loggedinas()) {
                    // Just remove the tags.
                    $replace['/\{ifloggedinas\}/i'] = '';
                    $replace['/\{\/ifloggedinas\}/i'] = '';
                } else {
                    // If logged in as another user, remove the ifloggedinas tags and contained content.
                    $replace['/\{ifloggedinas}(.*?)\{\/ifloggedinas\}/ims'] = '';
                }
            }

            // Tag: {ifnotloggedinas}.
            if (stripos($text, '{ifnotloggedinas}') !== false) {
                // If not logged-in-as another user...
                if (!\core\session\manager::is_loggedinas()) {
                    // Just remove the tags.
                    $replace['/\{ifnotloggedinas\}/i'] = '';
                    $replace['/\{\/ifnotloggedinas\}/i'] = '';
                } else {
                    // If logged in as another user, remove the if not loggedinas tags and contained content.
                    $replace['/\{ifnotloggedinas}(.*?)\{\/ifnotloggedinas\}/ims'] = '';
                }
            }

            // Tag: {ifincohort idname|idnumber}.
            if (stripos($text, '{ifincohort ') !== false) {
                static $mycohorts;
                if (empty($mycohorts)) { // Cache list of cohorts.
                    require_once($CFG->dirroot.'/cohort/lib.php');
                    $mycohorts = cohort_get_user_cohorts($USER->id);
                }
                $newtext = preg_replace_callback('/\{ifincohort (\w*)\}(.*?)\{\/ifincohort\}/is',
                    function ($matches) use($mycohorts) {
                        foreach ($mycohorts as $cohort) {
                            if ($cohort->idnumber == $matches[1] || $cohort->id == $matches[1]) {
                                return ($matches[2]);
                            };
                        }
                        return '';
                    }, $text
                );
                if ($newtext !== false) {
                    $text = $newtext;
                    $changed = true;
                }
            }

            // Tag: {ifeditmode}.
            if (stripos($text, '{ifeditmode}') !== false) {
                // If editing mode is activated...
                if ($PAGE->user_is_editing()) {
                    // Just remove the tags.
                    $replace['/\{ifeditmode\}/i'] = '';
                    $replace['/\{\/ifeditmode\}/i'] = '';
                } else {
                    // If editing mode is not enabled, remove the ifeditmode tags and contained content.
                    $replace['/\{ifeditmode}(.*?)\{\/ifeditmode\}/ims'] = '';
                }
            }

            // Tag: {ifnoteditmode}.
            if (stripos($text, '{ifnoteditmode}') !== false) {
                // If editing mode is activated...
                if ($PAGE->user_is_editing()) {
                    // If editing mode is enabled, remove the ifnoteditmode tags and contained content.
                    $replace['/\{ifnoteditmode}(.*?)\{\/ifnoteditmode\}/ims'] = '';
                } else {
                    // Just remove the tags.
                    $replace['/\{ifnoteditmode\}/i'] = '';
                    $replace['/\{\/ifnoteditmode\}/i'] = '';
                }
            }

            // Tag: {ifcourserequests}.
            if (stripos($text, '{ifcourserequests}') !== false) {
                // If Request a course is enabled...
                $context = context_system::instance();
                if (empty($CFG->enablecourserequests) || !has_capability('moodle/course:request', $context)) {
                    // Just remove the tags.
                    $replace['/\{ifcourserequests\}/i'] = '';
                    $replace['/\{\/ifcourserequests\}/i'] = '';
                } else {
                    // If Request a Course is not enabled, remove the ifcourserequests tags and contained content.
                    $replace['/\{ifcourserequests}(.*?)\{\/ifcourserequests\}/ims'] = '';
                }
            }

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
            // but must be logged-in and must not have additional higher level roles.
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
                    // If not on the dashboard page, remove the ifdashboard text.
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
                    // If not on the front page, remove the ifhome text.
                    $replace['/\{ifhome}(.*?)\{\/ifhome\}/ims'] = '';
                }
            }

            // Tag: {ifdev}.
            if (stripos($text, '{ifdev}') !== false) {
                // If an administrator with debugging is set to DEVELOPER mode...
                if ($CFG->debugdisplay == 1 && is_siteadmin() && !is_role_switched($PAGE->course->id)) {
                    // Just remove the tags.
                    $replace['/\{ifdev\}/i'] = '';
                    $replace['/\{\/ifdev\}/i'] = '';
                } else {
                    // If not a developer with debugging set to DEVELOPER mode, remove the ifdev tags and contained content.
                    $replace['/\{ifdev}(.*?)\{\/ifdev\}/ims'] = '';
                }
            }

            // Tag: {ifingroup id|idnumber}.
            if (stripos($text, '{ifingroup') !== false) {
                static $mygroups;
                if (!isset($mygroups)) { // Fetch my groups.
                    $mygroups = groups_get_all_groups($PAGE->course->id, $USER->id);
                }
                $re = '/{ifingroup\s+(.*?)\}(.*?)\{\/ifingroup\}/ims';
                $found = preg_match_all($re, $text, $matches);
                if ($found > 0) {
                    foreach ($matches[1] as $groupid) {
                        $key = '/{ifingroup\s+' . $groupid . '\}(.*?)\{\/ifingroup\}/ims';
                        $ismember = false;
                        foreach ($mygroups as $group) {
                            if ($groupid == $group->id || $groupid == $group->idnumber) {
                                $ismember = true;
                                break;
                            }
                        }
                        if ($ismember) { // Just remove the tags.
                            $replace[$key] = '$1';
                        } else { // Remove the ifingroup tags and content.
                            $replace[$key] = '';
                        }
                    }
                }
            }

            // Tag: {iftenant idnumber|tenantid}. Only for Moodle Workplace.
            if (stripos($text, '{iftenant') !== false) {
                if (class_exists('tool_tenant\tenancy')) {
                    // Moodle Workplace.
                    $tenants = \tool_tenant\tenancy::get_tenants();
                    // Get current tenantid.
                    $currenttenantid = \tool_tenant\tenancy::get_tenant_id();
                } else {
                    // Moodle Classic - Just simulate functionality as tenant 1.
                    // This allows a course to work in both Moodle Classic and Workplace.
                    $tenants[0] = new stdClass();
                    $tenants[0]->idnumber = 1;
                    $tenants[0]->id = 1;
                    $currenttenantid = 1;
                }
                // We will use tenant's idnumber if it is set. If not, default to tenant id.
                $currenttenantidnumber = 1;
                foreach ($tenants as $tenant) {
                    if ($tenant->id == $currenttenantid) {
                        $currenttenantidnumber = $tenant->idnumber ? $tenant->idnumber : $tenant->id;
                    }
                }
                $re = '/{iftenant\s+(.*?)\}(.*?)\{\/iftenant\}/ims';
                $found = preg_match_all($re, $text, $matches);
                if ($found > 0) {
                    foreach ($matches[1] as $tenantid) {
                        $key = '/{iftenant\s+' . $tenantid . '\}(.*?)\{\/iftenant\}/ims';
                        if ($tenantid == $currenttenantidnumber) {
                            // Just remove the tags.
                            $replace[$key] = '$1';
                        } else {
                            // Remove the iftenant strings.
                            $replace[$key] = '';
                        }
                    }
                }
            }

            // Tag: {ifworkplace}. Only for Moodle Workplace.
            if (stripos($text, '{ifworkplace}') !== false) {
                if (class_exists('tool_tenant\tenancy')) {
                    // Moodle Workplace - Just remove the tags.
                    $replace['/\{ifworkplace\}/i'] = '';
                    $replace['/\{\/ifworkplace\}/i'] = '';
                } else {
                    // If Moodle Classic, remove the ifworkplace tags and text.
                    $replace['/\{ifworkplace}(.*?)\{\/ifworkplace\}/ims'] = '';
                }
            }

            // Tag: {iftenant idnumber|tenantid}. Only for Moodle Workplace.
            if (stripos($text, '{ifworkplace}') !== false) {
                if (class_exists('tool_tenant\tenancy')) {
                    // Moodle Workplace.
                    // Just remove the tags.
                    $replace['/\{ifworkplace\}/i'] = '';
                    $replace['/\{\/ifworkplace\}/i'] = '';
                } else {
                    // If not Moodle Workplace, remove the ifworkplace tags and text.
                    $replace['/\{ifworkplace}(.*?)\{\/ifworkplace\}/ims'] = '';
                }
            }

            // Tag: {ifcustomrole rolename}.
            if (stripos($text, '{ifcustomrole') !== false) {
                $re = '/{ifcustomrole\s+(.*?)\}(.*?)\{\/ifcustomrole\}/ims';
                $found = preg_match_all($re, $text, $matches);
                if ($found > 0) {
                    foreach ($matches[1] as $roleshortname) {
                        $key = '/{ifcustomrole\s+' . $roleshortname . '\}(.*?)\{\/ifcustomrole\}/ims';
                        $contextid = ($PAGE->course->id == SITEID) ? 0 : context_course::instance($PAGE->course->id)->id;
                        if ($this->hascustomrole($roleshortname, $contextid)) {
                            // Just remove the tags.
                            $replace[$key] = '$1';
                        } else {
                            // Remove the ifcustomrole strings.
                            $replace[$key] = '';
                        }
                    }
                }
            }

            // Tag: {ifnotcustomrole rolename}.
            if (stripos($text, '{ifnotcustomrole') !== false) {
                $re = '/{ifnotcustomrole\s+(.*?)\}(.*?)\{\/ifnotcustomrole\}/ims';
                $found = preg_match_all($re, $text, $matches);
                if ($found > 0) {
                    foreach ($matches[1] as $roleshortname) {
                        $key = '/{ifnotcustomrole\s+' . $roleshortname . '\}(.*?)\{\/ifnotcustomrole\}/ims';
                        $contextid = ($PAGE->course->id == SITEID) ? 0 : context_course::instance($PAGE->course->id)->id;
                        if (!$this->hascustomrole($roleshortname, $contextid)) {
                            // Just remove the tags.
                            $replace[$key] = '$1';
                        } else {
                            // Remove the ifnotcustomrole strings.
                            $replace[$key] = '';
                        }
                    }
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

                // Tag: {ifminsitemanager}.
                if (stripos($text, '{ifminsitemanager}') !== false) {
                    static $issitemanager;
                    // If a manager or above.
                    if (!isset($issitemanager) && $issitemanager = $this->hasminarchetype('manager')) {
                        if (!is_siteadmin()) {
                            // Is at least a manager, but a site manager? Let's see.
                            $syscontext = context_system::instance();
                            $role = $DB->get_record('role', array('shortname' => 'manager'), '*', MUST_EXIST);
                            $userfields = 'u.id, u.username, u.firstname, u.lastname';
                            $roleusers = get_role_users($role->id, $syscontext, false, $userfields);
                            $issitemanager = array_key_exists($USER->id, array_column($roleusers, null, 'id'));
                        }
                    }
                    if ($issitemanager) {
                        // Just remove the tags.
                        $replace['/\{ifminsitemanager\}/i'] = '';
                        $replace['/\{\/ifminsitemanager\}/i'] = '';
                    } else {
                        // Remove the ifminsitemanager strings.
                        $replace['/\{ifminsitemanager\}(.*?)\{\/ifminsitemanager\}/ims'] = '';
                    }
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

        // Tag: {alert}{/alert}.
        if (stripos($text, '{/alert}') !== false) {
            $newtext = preg_replace_callback('/\{alert(\s\w*)?\}(.*?)\{\/alert\}/is',
            function($matches) {
                // If alert <style> parameter is not included, default to alert-warning.
                $matches[1] = trim($matches[1]);
                $matches[1] = empty($matches[1]) ? 'warning' : $matches[1];
                return '<div class="alert alert-' . $matches[1] . '" role="alert"><p>' . $matches[2] . '</p></div>';
            }, $text);
            if ($newtext !== false) {
                $text = $newtext;
                $changed = true;
            }
        }

        // Tag: {help}{/help}.
        if (stripos($text, '{/help}') !== false) {
            static $help;
            static $helpwrapper = [];
            if (!isset($help)) {
                $help = get_string('help');
                $helpwrapper[0] = '<a class="btn btn-link p-0" role="button" data-container="body" data-toggle="popover"'
                        . ' data-placement="right" data-content="<div class=&quot;no-overflow&quot;><p>';
                $helpwrapper[1] = '</p></div>" data-html="true" tabindex="0" data-trigger="focus"><i class="icon'
                        . ' fa fa-question-circle text-info fa-fw " title="' . $help . '" aria-label="' . $help . '"></i></a>';
            }
            $newtext = preg_replace_callback('/\{help}(.*?)\{\/help\}/is',
                function($matches) use($helpwrapper) {
                    return $helpwrapper[0] . htmlspecialchars($matches[1]) . $helpwrapper[1];
                }, $text);
            if ($newtext !== false) {
                $text = $newtext;
                $changed = true;
            }
        }

        // Tag: {info}{/info}.
        if (stripos($text, '{/info}') !== false) {
            static $info;
            static $infowrapper = [];
            if (!isset($info)) {
                $info = get_string('info');
                $infowrapper[0] = '<a class="btn btn-link p-0" role="button" data-container="body" data-toggle="popover"'
                        . ' data-placement="right" data-content="<div class=&quot;no-overflow&quot;><p>';
                $infowrapper[1] = '</p></div>" data-html="true" tabindex="0" data-trigger="focus"><i class="icon'
                        . ' fa fa-info-circle text-info fa-fw " title="' . $info . '" aria-label="' . $info . '"></i></a>';
            }
            $newtext = preg_replace_callback('/\{info}(.*?)\{\/info\}/is',
                function($matches) use($infowrapper) {
                    return $infowrapper[0] . htmlspecialchars($matches[1]) . $infowrapper[1];
                }, $text);
            if ($newtext !== false) {
                $text = $newtext;
                $changed = true;
            }
        }

        // Handle escaped tags.

        // Complete the process of replacing escaped tags with single braces.
        if ($escapedtags) {
            $text = str_replace(chr(2), '{', $text);
            $text = str_replace(chr(3), '}', $text);
        }
        // Complete the process of replacing escaped tags with single escaped braces.
        if ($escapedtagsenc) {
            $text = str_replace(chr(4), '%7B', $text);
            $text = str_replace(chr(5), '%7D', $text);
        }

        return $text;
    }
}
