<?php
// This file is part of FilterCodes for Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Main filter code for FilterCodes.
 *
 * @package    filter_filtercodes
 * @copyright  2017-2025 TNG Consulting Inc. - www.tngconsulting.ca
 * @author     Michael Milette
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace filter_filtercodes;

defined('MOODLE_INTERNAL') || die;

use block_online_users\fetcher;
use core_table\local\filter\integer_filter;
use core_user\table\participants_filterset;
use core_user\table\participants_search;
use Endroid\QrCode\QrCode;

require_once($CFG->dirroot . '/course/renderer.php');

if (class_exists('\core_filters\text_filter')) {
    class_alias('\core_filters\text_filter', 'filtercodes_base_text_filter');
} else {
    class_alias('\moodle_text_filter', 'filtercodes_base_text_filter');
}

/**
 * Extends the moodle_text_filter class to provide plain text support for new tags.
 *
 * @copyright  2017-2025 TNG Consulting Inc. - www.tngconsulting.ca
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class text_filter extends \filtercodes_base_text_filter {
    /** @var array $archetyperoles Object array of Moodle archetypes. */
    private static $archetyperoles = null;
    /** @var array $customroles array of Roles key is shortname and value is the id */
    private static $customroles = [];
    /**
     * @var array $customrolespermissions array of Roles key is shortname + context_id and the value is a boolean showing if
     * user is allowed
     */
    private static $customrolespermissions = [];

    /** @var bool $infiltercodes Flag to track if a filter is being called recursively */
    private static $infiltercodes = false;

    /**
     * Constructor: Get the role IDs associated with each of the archetypes.
     */
    public function __construct() {

        // Note: This array must correspond to the one in function hasminarchetype.
        $archetypelist = ['manager' => 1, 'coursecreator' => 2, 'editingteacher' => 3, 'teacher' => 4, 'student' => 5];
        if (self::$archetyperoles === null) {
            self::$archetyperoles = [];
            foreach ($archetypelist as $archetype => $level) {
                $roleids = [];
                // Build array of roles.
                foreach (get_archetype_roles($archetype) as $role) {
                    $roleids[] = $role->id;
                }
                self::$archetyperoles[$archetype] = (object)['level' => $level, 'roleids' => $roleids];
            }
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
            $context = \context_course::instance($PAGE->course->id);
            $id = $USER->access['rsw'][$context->path];
            $archetypes[$archetype] = in_array($id, self::$archetyperoles[$archetype]->roleids);
        } else {
            // For each of the roles associated with the archetype, check if the user has one of the roles.
            foreach (self::$archetyperoles[$archetype]->roleids as $roleid) {
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
            $archetypes = array_keys(self::$archetyperoles);
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
                return !is_siteadmin();
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
        for ($level = self::$archetyperoles[$minarchetype]->level; $level >= 1; $level--) {
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
     * Determine if the specified user has the specified role anywhere in the system.
     *
     * @param string $roleshortname Role shortname.
     * @param integer $userid The user's ID.
     * @return boolean True if the user has the role, false if they do not.
     */
    private function hasarole($roleshortname, $userid) {
        // Cache list of user's roles.
        static $list;

        if (!isset($list)) {
            // Not cached yet? We can take care of that.
            $list = [];
            if ($this->isauthenticateduser()) {
                // We only track logged-in roles.
                global $DB;
                // Retrieve list of role names.
                $rolenames = $DB->get_records('role');
                // Retrieve list of my roles across all contexts.
                $userroles = $DB->get_records('role_assignments', ['userid' => $userid]);
                // For each of my roles, add the roll name to the list.
                foreach ($userroles as $role) {
                    if (!empty($rolenames[$role->roleid]->shortname)) {
                        // There should always be a role name for each role id but you can't be too careful these days.
                        $list[] = $rolenames[$role->roleid]->shortname;
                    }
                }
                $list = array_unique($list);
                if (is_siteadmin()) {
                    // Admin is not an actual role, but we can use our imagination for convenience.
                    $list[] = 'administrator';
                }
            }
        }
        return in_array(strtolower($roleshortname), $list);
    }

    /**
     * Returns the URL of a blank Avatar as a square image.
     *
     * @param integer $size Width of desired image in pixels.
     * @return MOODLE_URL URL to image of avatar image.
     */
    private function getblankavatarurl($size) {
        global $PAGE, $CFG;
        $img = 'u/' . ($size > 100 ? 'f3' : ($size > 35 ? 'f1' : 'f2'));
        $renderer = $PAGE->get_renderer('core');
        if ($CFG->branch >= 33) {
            $url = $renderer->image_url($img);
        } else {
            $url = $renderer->pix_url($img); // Deprecated as of Moodle 3.3.
        }
        return (new \moodle_url($url))->out();
    }

    /**
     * Retrieves the URL for the user's profile picture, if one is available.
     *
     * @param object $user The Moodle user object for which we want a photo.
     * @param mixed $size Can be sm|md|lg or an integer 2|1|3 or an integer size in pixels > 3.
     * @return string URL to the photo image file but with $1 for the size.
     */
    private function getprofilepictureurl($user, $size = 'md') {
        global $PAGE;

        $sizes = ['sm' => 35, '2' => 35, 'md' => 100, '1' => 100, 'lg' => 512, '3' => 512];
        if (isset($sizes[$size])) {
            $px = $sizes[$size];
        } else if (is_numeric($size)) {
            $px = $size; // Size was specified in pixels.
        } else {
            $px = 100; // Default size.
        }

        $userpicture = new \user_picture($user);
        $userpicture->size = $px; // Size in pixels.
        $url = $userpicture->get_url($PAGE);
        return $url;
    }

    /**
     * Retrieves specified profile fields for a user.
     *
     * This method fetches and caches user profile fields from the database. If the fields have
     * already been retrieved for the current request, the cached values are returned. This method
     * supports fetching both core and custom user profile fields.
     *
     * @param object $user The user object whose profile fields are being retrieved.
     * @param array $fields optional An array of field names to retrieve. If empty/not specified, only custom fields are retrieved.
     * @return array An associative array of field names and their values for the specified user.
     */
    private function getuserprofilefields($user, $fields = []) {
        global $DB;

        static $profilefields;
        static $lastfields;

        // If we have already cached the profile fields and data, return them.
        if (isset($profilefields) && $lastfields == $fields) {
            return $profilefields;
        }

        $profilefields = [];
        if (!isloggedin()) {
            return $profilefields;
        }

        // Get custom user profile fields, their value and visibilit. Only works for authenticated users.
        $sql = "SELECT f.shortname, f.visible, f.datatype, COALESCE(d.data, '') AS value
            FROM {user_info_field} f
            LEFT JOIN {user_info_data} d ON f.id = d.fieldid AND d.userid = :userid
            ORDER BY f.shortname;";
        $params = ['userid' => $user->id];
        // Determine if restricted to only visible fields.
        if (!empty(get_config('filter_filtercodes', 'ifprofilefiedonlyvisible'))) {
            $params['visible'] = 1;
        }
        $profilefields = $DB->get_records_sql($sql, $params);

        // Add core user profile fields.
        foreach ($fields as $field) {
            // Skip fields that don't exist (likely a typo).
            if (isset($user->$field)) {
                $profilefields[$field] = (object)['shortname' => $field, 'visible' => '1',
                    'datatype' => 'text', 'value' => $user->$field];
            }
        }
        $lastfields = $fields;

        return $profilefields;
    }

    /**
     * Retrieves the user's groupings for a course.
     *
     * @param integer $courseid The course ID.
     * @param integer $userid The user ID.
     * @return array An array of groupings for the specified user in the specified course.
     */
    private function getusergroupings($courseid, $userid) {
        global $DB;

        return $DB->get_records_sql('SELECT gp.id, gp.name, gp.idnumber
            FROM {user} u
                INNER JOIN {groups_members} gm ON u.id = gm.userid
                INNER JOIN {groups} g ON g.id = gm.groupid
                INNER JOIN {groupings_groups} gg ON gm.groupid = gg.groupid
                INNER JOIN {groupings} gp ON gp.id = gg.groupingid
            WHERE g.courseid = ? AND u.id = ?
            GROUP BY gp.id
            ORDER BY gp.name ASC', [$courseid, $userid]);
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
            $ishttps = (filter_input(INPUT_SERVER, 'HTTPS') === 'on');
        }
        return $ishttps;
    }

    /**
     * Determine if access is from a web service.
     *
     * @return boolean true if a web service, false if web browser.
     */
    private function iswebservice() {
        global $ME;
        // If this is a web service or the Moodle mobile app...
        $isws = (WS_SERVER || (strstr($ME, "webservice/") !== false && optional_param('token', '', PARAM_ALPHANUM)));
        return $isws;
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
        $dom = new \DOMDocument();
        $dom->loadHTML($content);

        // Clear suppressed warnings.
        libxml_clear_errors();
        libxml_use_internal_errors($libxmlpreviousstate);

        // Scrape out the content we want. If not found, return everything.
        $xpath = new \DOMXPath($dom);

        // If a tag was not specified.
        if (empty($tag)) {
            $tag .= '*'; // Match any tag.
        }
        $query = "//{$tag}";

        // If a class was specified.
        if (!empty($class)) {
            $query .= "[@class=\"{$class}\"]";
        }

        // If an id was specified.
        if (!empty($id)) {
            $query .= "[@id=\"{$id}\"]";
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
     * Correctly format a list as "A, B and C".
     *
     * @param array $list An array of numbers or strings.
     * @return string The formatted string.
     */
    private function formatlist($list) {
        // Save and remove last item in list from array.
        $last = array_pop($list);
        if ($list) {
            // Combine list using language list separator.
            $list = implode(get_string('listsep', 'langconfig') . ' ', $list);
            // Add last item separated by " and ".
            $string = get_string('and', 'moodle', ['one' => $list, 'two' => $last]);
        } else {
            // Only one item in the list. No formatting required.
            $string = $last;
        }
        return $string;
    }

    /**
     * Convert string containg one or more attribute="value" pairs into an associative array.
     *
     * @param string $attrs One or more attribute="value" pairs.
     * @return array Associative array of attributes and values.
     */
    private function attribstoarray($attrs) {
        $arr = [];

        if (preg_match_all('/\s*(?:([a-z0-9-]+)\s*=\s*"([^"]*)")|(?:\s+([a-z0-9-]+)(?=\s*|>|\s+[a..z0-9]+))/i', $attrs, $matches)) {
            // For each attribute in the string, add associated value to the array.
            for ($i = 0; $i < count($matches[0]); $i++) {
                if ($matches[3][$i]) {
                    $arr[$matches[3][$i]] = null;
                } else {
                    $arr[$matches[1][$i]] = $matches[2][$i];
                }
            }
        }
        return $arr;
    }

    /**
     * Render cards for provided category.
     *
     * @param object $category Category object.
     * @param boolean $categoryshowpic Set to true to display a category image. False displays no image.
     * @return string HTML rendering of category cars.
     */
    private function rendercategorycard($category, $categoryshowpic) {
        global $OUTPUT;

        if (!$category->visible) {
            $dimmed = 'opacity: 0.5;';
        } else {
            $dimmed = '';
        }

        $category->name = format_string($category->name);

        $url = (new \moodle_url('/course/index.php', ['categoryid' => $category->id]))->out();
        if ($categoryshowpic) {
            $imgurl = $OUTPUT->get_generated_image_for_id($category->id + 65535);
            $html = '<li class="card shadow mr-4 mb-4 ml-0" style="min-width:290px;max-width:290px;' . $dimmed . '">
                    <a href="' . $url . '" class="text-white h-100">
                    <div class="card-img" style="background-image: url(' . $imgurl . ');height:100px;"></div>
                    <div class="card-img-overlay card-title pt-1 pr-3 pb-1 pl-3 m-0" '
                        . 'style="height:fit-content;top:auto;background-color:rgba(0,0,0,.4);color:#ffffff;'
                        . 'text-shadow:-1px -1px 0 #767676, 1px -1px 0 #767676, -1px 1px 0 #767676, 1px 1px 0 #767676">'
                        . $category->name . '</div>';
        } else {
            $html = '<li class="card shadow mr-4 mb-4 ml-0 fc-categorycard-' . $category->id .
                    '" style="min-width:350px;max-width:350px;' . $dimmed . '">' .
                    '<a href="' . $url . '" class="text-decoration-none h-100 p-4">' . $category->name;
        }
        $html .= '</a></li>' . PHP_EOL;
        return $html;
    }

    /**
     * Check if the current user is authenticated and not a guest user.
     *
     * @return bool True if the user is logged in and not a guest user, false otherwise.
     */
    private function isauthenticateduser() {
        static $isauthenticateduser;

        if (!isset($isauthenticateduser)) {
            $isauthenticateduser = isloggedin() && !isguestuser();
        }

        return $isauthenticateduser;
    }

    /**
     * Render course cards for list of course ids. Not visible for hidden courses or if it has expired.
     *
     * @param array $rcourseids Array of course ids.
     * @param string $format orientation/layout of course cards.
     * @return string HTML of course cars.
     */
    private function rendercoursecards($rcourseids, $format = 'vertical') {
        global $CFG, $OUTPUT, $PAGE, $SITE;

        $content = '';
        $isadmin = (is_siteadmin() && !is_role_switched($PAGE->course->id));

        foreach ($rcourseids as $courseid) {
            if ($courseid == $SITE->id) { // Skip site.
                continue;
            }
            $course = get_course($courseid);
            $context = \context_course::instance($course->id);
            // Course will be displayed if its visibility is set to Show AND (either has no end date OR a future end date).
            $visible = ($course->visible && (empty($course->enddate) || time() < $course->enddate));
            // Courses not visible will be still visible to site admins or users with viewhiddencourses capability.
            if (!$visible && !($isadmin || has_capability('moodle/course:viewhiddencourses', $context))) {
                // Skip if the course is not visible to user or course is the "site".
                continue;
            }

            // Load image from course image. If none, generate a course image based on the course ID.
            $context = \context_course::instance($courseid);
            $course = new \core_course_list_element($course);
            $coursefiles = $course->get_course_overviewfiles();
            $imgurl = '';
            if ($CFG->branch >= 311) {
                $imgurl = \core_course\external\course_summary_exporter::get_course_image($course);
            } else { // Previous to Moodle 3.11.
                foreach ($coursefiles as $file) {
                    if ($isimage = $file->is_valid_image()) {
                            // The file_encode_url() function is deprecated as per MDL-31071 but still in wide use.
                            $imgurl = file_encode_url("/pluginfile.php", '/' . $file->get_contextid() . '/'
                                    . $file->get_component() . '/' . $file->get_filearea() . $file->get_filepath()
                                    . $file->get_filename(), !$isimage);
                            $imgurl = (new \moodle_url($imgurl))->out();
                            break;
                    }
                }
            }
            if (empty($imgurl)) {
                $imgurl = $OUTPUT->get_generated_image_for_id($courseid);
            }
            $courseurl = (new \moodle_url('/course/view.php', ['id' => $courseid]))->out();

            switch ($format) {
                case 'vertical':
                    $content .= '
                    <div class="card shadow mr-4 mb-4 ml-1  fc-coursecard-card" style="min-width:300px;max-width:300px;">
                        <a href="' . $courseurl . '" class="text-normal h-100">
                        <div class="card-img-top" style="background-image:url(' . $imgurl
                                . ');height:100px;max-width:300px;padding-top:50%;background-size:cover;'
                                . 'background-repeat:no-repeat;background-position:center;"></div>
                        <div class="card-title pt-1 pr-3 pb-1 pl-3 m-0"><span class="sr-only">' . get_string('course') . ': </span>'
                                . $course->get_formatted_name() . '</div>
                        </a>
                    </div>
                    ';
                    break;
                case 'horizontal':
                    global $DB;
                    $category = $DB->get_record('course_categories', ['id' => $course->category]);
                    $category = format_string($category->name);

                    $summary = $course->summary == null ? '' : format_string($course->summary, true, ['context' => $context]);
                    $summary = substr($summary, -4) == '<br>' ? substr($summary, 0, strlen($summary) - 4) : $summary;

                    $content .= '
                    <div class="card mb-3 fc-coursecard-list">
                        <div class="row no-gutter">
                            <div class="col-md-4">
                                <a href="' . $courseurl . '" aria-hidden="true" tabindex="-1">
                                    <img src="' . $imgurl . '" class="card-img" alt="">
                                </a>
                            </div>
                            <div class="col-md-8">
                                <div class="card-body">
                                    <p class="card-text text-category" style="float:right">
                                        <small class="text-muted"><span class="sr-only">'
                                            . get_string('category') . ': </span>' . $category .
                                        '</small>
                                    </p>
                                    <h3 class="card-title">
                                        <a href="' . $courseurl . '" class="text-normal h-100">
                                            <span class="sr-only">' . get_string('course') . ': </span>'
                                            . $course->get_formatted_name() .
                                        '</a>
                                    </h3>
                                    <div class="card-text text-summary"><span class="sr-only">'
                                            . get_string('summary') . ': </span>' . $summary .
                                    '</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    ';
                    break;
                case 'table':
                    global $DB;
                    $category = $DB->get_record('course_categories', ['id' => $course->category]);
                    $category = format_string($category->name);

                    $summary = $course->summary == null ? '' : format_string($course->summary, true, ['context' => $context]);
                    $summary = substr($summary, -4) == '<br>' ? substr($summary, 0, strlen($summary) - 4) : $summary;

                    $content .= '
                    <tr class="fc-coursecard-table">
                    <td class="text-coursename col-sm-12 col-md-3 d-block d-md-table-cell"><a href="' . $courseurl . '">'
                        . $course->get_formatted_name() . '</a></td>
                    <td class="text-coursecategory col-sm-12 col-md-2 d-block d-md-table-cell">' . $category . '</td>
                    <td class="text-coursename col-sm-12 col-md-7 d-block d-md-table-cell" style="word-wrap:break-word;">'
                        . $summary . '</td>
                    </tr>
                    ';
                    break;
            }
        }
        return $content;
    }

    /**
     * Get course card including format, header and footer.
     *
     * @param string $format card format.
     * @return object $cards->format, $cards->header, $cards->footer
     */
    private function getcoursecardinfo($format = null) {
        static $cards;
        if (is_object($cards)) {
            return $cards;
        }
        $cards = new \stdClass();
        if (empty($format)) {
            $cards->format = get_config('filter_filtercodes', 'coursecardsformat');
        } else {
            $cards->format = $format;
        }
        switch ($cards->format) {
            case 'table':
                $cards->header = '
                    <table class="table table-hover table-responsive">
                        <thead>
                            <tr>
                                <th scope="col" class="col-12 col-md-3 d-block d-md-table-cell">'
                                    . get_string('course') . '</th>
                                <th scope="col" class="col-12 col-md-2 d-block d-md-table-cell">'
                                    . get_string('category') . '</th>
                                <th scope="col" class="col-12 col-md-7 d-block d-md-table-cell">'
                                    . get_string('description') . '</th>
                            </tr>
                        </thead>
                        <tbody>
                ';
                $cards->footer = '
                        </tbody>
                    </table>';
                break;
            case 'horizontal':
                $cards->header = '<div class="d-flex"><div>';
                $cards->footer = '</div></div>';
                break;
            default:
                $cards->format = 'vertical';
                $cards->header = '<div class="card-deck mr-0">';
                $cards->footer = '</div>';
        }
        return $cards;
    }

    /**
     * Generate a user link of a specified type if logged-in.
     *
     * @param string $clinktype Type of link to generate. Options include: email, message, profile, phone1.
     * @param object $user A user object.
     * @param string $name The name to be displayed.
     *
     * @return string Generated link.
     */
    private function userlink($clinktype, $user, $name) {
        if (!isloggedin() || isguestuser()) {
            $clinktype = ''; // No link, only name.
        }
        switch ($clinktype) {
            case 'email':
                $link = '<a href="mailto:' . $user->email . '">'  . $name . '</a>';
                break;
            case 'message':
                $link = '<a href="' . (new \moodle_url('/message/index.php', ['id' => $user->id]))->out() . '">' . $name . '</a>';
                break;
            case 'profile':
                $link = '<a href="' . (new \moodle_url('/user/profile.php', ['id' => $user->id]))->out() . '">' . $name . '</a>';
                break;
            case 'phone1':
                if (!empty($user->phone1)) {
                    $link = '<a href="tel:' . $user->phone1 . '">' . $name . '</a>';
                } else {
                    $link = $name;
                }
                break;
            default:
                $link = $name;
        }
        return $link;
    }

    /**
     * Generate base64 encoded data img of QR Code.
     *
     * @param string $text Text to be encoded.
     * @param string $label Label to display below QR code.
     * @return string Base64 encoded data image.
     */
    private function qrcode($text, $label = '') {
        if (empty($text)) {
            return '';
        }
        global $CFG;
        require_once($CFG->dirroot . '/filter/filtercodes/thirdparty/QrCode/src/QrCode.php');
        $code = new QrCode();
        $code->setText($text);
        $code->setErrorCorrection('high');
        $code->setPadding(0);
        $code->setSize(480);
        $code->setLabelFontSize(16);
        $code->setLabel($label);
        $src = 'data:image/png;base64,' . base64_encode($code->get('png'));
        return $src;
    }

    /**
     * Course completion progress percentage.
     *
     * @return int completion progress percentage
     */
    private function completionprogress() {
        static $progresspercent;
        if (!isset($progresspercent)) {
            global $PAGE;
            $course = $PAGE->course;
            $progresspercent = -1; // Disabled: -1.
            if (
                    $course->enablecompletion == 1
                    && isloggedin()
                    && !isguestuser()
                    && \context_system::instance() != 'page-site-index'
            ) {
                $progresspercent = (int) \core_completion\progress::get_course_progress_percentage($course);
            }
        }

        return $progresspercent;
    }


    /**
     * Format a custom menu item text
     *
     * This function ensures that text used in custom menu items is properly formatted,
     * specifically by replacing pipe characters (|) with HTML entity representation
     * to prevent them from being interpreted as menu separators.
     *
     * @param string $text The menu item text to be formatted
     * @return string The formatted menu item text with pipes replaced with HTML entities
     */
    private function format_custommenuitem($text): string {
        return str_replace('|', '&#124;', format_string($text));
    }

    /**
     * Generator Tags
     *
     * This function processes tags that generate content that could potentially include additional tags.
     *
     * @param string $text The unprocessed text. Passed by refernce.
     * @return boolean True of there are more tags to be processed, otherwise false.
     */
    private function generatortags(&$text) {
        global $CFG, $PAGE, $DB;

        $replace = []; // Array of key/value filterobjects.

        // If there are {menu...} tags.
        if (stripos($text, '{menu') !== false) {
            // Tag: {menuadmin}.
            // Description: Displays a menu of useful links for site administrators when added to the custom menu.
            // Parameters: None.
            if (stripos($text, '{menuadmin}') !== false) {
                $theme = $PAGE->theme->name;
                $menu = '';
                if ($this->hasminarchetype('editingteacher')) {
                    $menu .= '{getstring}admin{/getstring}' . PHP_EOL;
                }
                if ($this->hasminarchetype('coursecreator')) { // If a course creator or above.
                    $menu .= '-{getstring}administrationsite{/getstring}|/admin/search.php' . PHP_EOL;
                    $menu .= '-{toggleeditingmenu}' . PHP_EOL;
                    $menu .= '-Moodle Academy|https://moodle.academy/' . PHP_EOL;
                    $menu .= '-###' . PHP_EOL;
                }
                if ($this->hasminarchetype('manager')) { // If a manager or above.
                    $menu .= '-{getstring}user{/getstring}: {getstring:admin}usermanagement{/getstring}|/admin/user.php' . PHP_EOL;
                    $menu .= '-{getstring}user{/getstring}: {getstring}addnewuser{/getstring}'
                        . '|/user/editadvanced.php?id=-1' . PHP_EOL;
                    $menu .= '-{getstring}user{/getstring}: {getstring:tool_uploaduser}uploadusers{/getstring}'
                        . '|/admin/tool/uploaduser/index.php' . PHP_EOL;
                    if (is_siteadmin() && !is_role_switched($PAGE->course->id)) {
                        $menu .= '-{getstring}user{/getstring}: {getstring:mnet}profilefields{/getstring}|/user/profile/index.php' .
                            PHP_EOL;
                    }
                    $menu .= '-###' . PHP_EOL;
                    $menu .= '-{getstring}course{/getstring}: {getstring:admin}coursemgmt{/getstring}|/course/management.php' .
                            '?categoryid={categoryid}' . PHP_EOL;
                    $menu .= '-{getstring}course{/getstring}: {getstring}new{/getstring}|/course/edit.php' .
                            '?category={categoryid}&returnto=topcat' . PHP_EOL;
                    $menu .= '-{getstring}course{/getstring}: {getstring}searchcourses{/getstring}|/course/search.php' . PHP_EOL;
                }
                if ($this->hasminarchetype('editingteacher')) {
                    $menu .= '-{getstring}course{/getstring}: {getstring}restore{/getstring}|/backup/restorefile.php' .
                        '?contextid={coursecontextid}' . PHP_EOL;
                    $menu .= '{ifincourse}' . PHP_EOL;
                    $menu .= '-{getstring}course{/getstring}: {getstring}backup{/getstring}|/backup/backup.php?id={courseid}' .
                            PHP_EOL;
                    if (stripos($text, '{menucoursemore}') === false) {
                        $menu .= '-{getstring}course{/getstring}: {getstring}participants{/getstring}|/user/index.php?id={courseid}'
                            . PHP_EOL;
                        $menu .= '-{getstring}course{/getstring}: {getstring:badges}badges{/getstring}|/badges/view.php' .
                            '?type=2&id={courseid}' . PHP_EOL;
                        $menu .= '-{getstring}course{/getstring}: {getstring}reports{/getstring}|/course/admin.php' .
                            '?courseid={courseid}#linkcoursereports' . PHP_EOL;
                    }
                    $menu .= '-{getstring}course{/getstring}: {getstring:enrol}enrolmentinstances{/getstring}|/enrol/instances.php'
                        . '?id={courseid}' . PHP_EOL;
                    $menu .= '-{getstring}course{/getstring}: {getstring}reset{/getstring}|/course/reset.php?id={courseid}'
                        . PHP_EOL;
                    $menu .= '-Course: Layoutit|https://www.layoutit.com/build" target="popup" ' .
                        'onclick="window.open(\'https://www.layoutit.com/build\',\'popup\',\'width=1340,height=700\');' .
                        ' return false;|Bootstrap Page Builder' . PHP_EOL;
                    $menu .= '{/ifincourse}' . PHP_EOL;
                    $menu .= '-###' . PHP_EOL;
                }
                if ($this->hasminarchetype('manager')) { // If a manager or above.
                    $menu .= '-{getstring}site{/getstring}: {getstring}reports{/getstring}|/admin/category.php?category=reports' .
                        PHP_EOL;
                }
                if (is_siteadmin() && !is_role_switched($PAGE->course->id)) { // If an administrator.
                    $menu .= '-{getstring}site{/getstring}: {getstring:admin}additionalhtml{/getstring}|/admin/settings.php' .
                            '?section=additionalhtml' . PHP_EOL;
                    $menu .= '-{getstring}site{/getstring}: {getstring:admin}frontpage{/getstring}|/admin/settings.php' .
                            '?section=frontpagesettings|Including site name' . PHP_EOL;
                    $menu .= '-{getstring}site{/getstring}: {getstring:admin}plugins{/getstring}|/admin/search.php#linkmodules' .
                            PHP_EOL;
                    $menu .= '-{getstring}site{/getstring}: {getstring:admin}supportcontact{/getstring}|/admin/settings.php' .
                            '?section=supportcontact' . PHP_EOL;

                    if ($CFG->branch >= 404) {
                        $label = 'themesettingsadvanced';
                        $section = 'themesettingsadvanced';
                    } else {
                        $label = 'themesettings';
                        $section = 'themesettings';
                    }
                    $menu .= '-{getstring}site{/getstring}: {getstring:admin}' . $label . '{/getstring}|/admin/settings.php' .
                        '?section=' . $section . '|Including custom menus, designer mode, theme in URL' . PHP_EOL;

                    if (!file_exists($CFG->dirroot . '/mod/hvp/version.php')) { // Not compatible with mod_hvp.
                        if (file_exists($CFG->dirroot . '/theme/' . $theme . '/settings.php')) {
                            require_once($CFG->libdir . '/adminlib.php');
                            if (admin_get_root()->locate('theme_' . $theme)) {
                                // Settings use categories interface URL.
                                $url = '/admin/category.php?category=theme_' . $theme . PHP_EOL;
                            } else {
                                // Settings use tabs interface URL.
                                $url = '/admin/settings.php?section=themesetting' . $theme . PHP_EOL;
                            }
                            $menu .= '-{getstring}site{/getstring}: {getstring:admin}currenttheme{/getstring}|' . $url;
                        }
                    }
                    $menu .= '-{getstring}site{/getstring}: {getstring}notifications{/getstring} ({getstring}admin{/getstring})' .
                            '|/admin/index.php' . PHP_EOL;
                }
                $replace['/\{menuadmin\}/i'] = $menu;
            }

            // Tag: {menucoursemore}.
            // Description: Show a "More" menu containing most of 4.x secondary menu. Useful if theme with pre-4.x style navigation.
            // Parameters: None.
            if (stripos($text, '{menucoursemore}') !== false) {
                $menu = '';
                $menu .= '{ifincourse}' . PHP_EOL;
                if ($CFG->branch >= 400) {
                    $menu .= '{getstring}moremenu{/getstring}' . PHP_EOL;
                } else {
                    $menu .= '{getstring:filter_filtercodes}moremenu{/getstring}' . PHP_EOL;
                }
                $menu .= '-{getstring}course{/getstring}|/course/view.php?id={courseid}' . PHP_EOL;
                if ($this->hasminarchetype('editingteacher')) {
                    $menu .= '-{getstring}settings{/getstring}|/course/edit.php?id={courseid}' . PHP_EOL;
                }
                $menu .= '-{getstring}participants{/getstring}|/user/index.php?id={courseid}' . PHP_EOL;
                $menu .= '-{getstring}grades{/getstring}|/grade/report/index.php?id={courseid}' . PHP_EOL;
                if ($this->hasminarchetype('editingteacher')) {
                    $menu .= '-{getstring}reports{/getstring}|/report/view.php?courseid={courseid}' . PHP_EOL;
                    $menu .= '-###' . PHP_EOL;
                    $menu .= '-{getstring:question}questionbank{/getstring}|/question/edit.php?courseid={courseid}' . PHP_EOL;
                    if ($CFG->branch >= 39) {
                        $menu .= '-{getstring}contentbank{/getstring}|/contentbank/index.php?contextid={coursecontextid}' . PHP_EOL;
                    }
                    $menu .= '-{getstring:completion}coursecompletion{/getstring}|/course/completion.php?id={courseid}' . PHP_EOL;
                    $menu .= '-{getstring:badges}badges{/getstring}|/badges/view.php?type=2&amp;id={courseid}' . PHP_EOL;
                }
                $pluginame = '{getstring:competency}competencies{/getstring}';
                $menu .= '-' . $pluginame . '|/admin/tool/lp/coursecompetencies.php?courseid={courseid}' . PHP_EOL;
                if ($this->hasminarchetype('editingteacher')) {
                    $menu .= '-{getstring:admin}filters{/getstring}|/filter/manage.php?contextid={coursecontextid}' . PHP_EOL;
                }
                if ($CFG->branch >= 402) {
                    $menu .= '-{getstring:enrol}unenrolme{/getstring}|{courseunenrolurl}' . PHP_EOL;
                } else {
                    $menu .= '-{getstring:filter_filtercodes}unenrolme{/getstring}|{courseunenrolurl}' . PHP_EOL;
                }
                if ($this->hasminarchetype('editingteacher')) {
                    if ($CFG->branch >= 403) {
                        $menu .= '-{getstring:mod_lti}courseexternaltools{/getstring}|/mod/lti/coursetools.php?id={courseid}'
                            . PHP_EOL;
                    }
                    if ($CFG->branch >= 311) {
                        $pluginame = '{getstring:tool_brickfield}pluginname{/getstring}';
                        $menu .= '-' . $pluginame . '|/admin/tool/brickfield/index.php?courseid={courseid}' . PHP_EOL;
                    }
                    $menu .= '-{getstring}coursereuse{/getstring}|/backup/import.php?id={courseid}' . PHP_EOL;
                }
                $menu .= '{/ifincourse}' . PHP_EOL;
                $replace['/\{menucoursemore\}/i'] = $menu;
            }

            // Tag: {menudev}.
            // Description: Displays a menu of useful links for site administrators when added to the custom menu.
            // Parameters: None.
            if (stripos($text, '{menudev}') !== false) {
                $menu = '';
                if (is_siteadmin() && !is_role_switched($PAGE->course->id)) { // If a site administrator.
                    $menu .= '-{getstring:tool_installaddon}installaddons{/getstring}|/admin/tool/installaddon' . PHP_EOL;
                    $menu .= '-###' . PHP_EOL;
                    $menu .= '-{getstring:admin}debugging{/getstring}|/admin/settings.php?section=debugging' . PHP_EOL;
                    $menu .= '-{getstring:admin}purgecachespage{/getstring}|/admin/purgecaches.php' . PHP_EOL;
                    $menu .= '-###' . PHP_EOL;
                    if (file_exists($CFG->dirroot . '/local/adminer/index.php')) {
                        $menu .= '-{getstring:local_adminer}pluginname{/getstring}|/local/adminer' . PHP_EOL;
                    }
                    if (file_exists($CFG->dirroot . '/local/codechecker/index.php')) {
                        $menu .= '-{getstring:local_codechecker}pluginname{/getstring}|/local/codechecker' . PHP_EOL;
                    }
                    if (file_exists($CFG->dirroot . '/local/moodlecheck/index.php')) {
                        $menu .= '-{getstring:local_moodlecheck}pluginname{/getstring}|/local/moodlecheck' . PHP_EOL;
                    }
                    if (file_exists($CFG->dirroot . '/admin/tool/pluginskel/index.php')) {
                        $menu .= '-{getstring:tool_pluginskel}pluginname{/getstring}|/admin/tool/pluginskel' . PHP_EOL;
                    }
                    if (file_exists($CFG->dirroot . '/local/tinyfilemanager/index.php')) {
                        $menu .= '-{getstring:local_tinyfilemanager}pluginname{/getstring}|/local/tinyfilemanager' . PHP_EOL;
                    }
                    $menu .= '-{getstring}phpinfo{/getstring}|/admin/phpinfo.php' . PHP_EOL;
                    $menu .= '-###' . PHP_EOL;
                    $menu .= '-{getstring:filter_filtercodes}pagebuilder{/getstring}|'
                        . '{getstring:filter_filtercodes}pagebuilderlink{/getstring}"'
                        . ' target="popup" onclick="window.open(\'{getstring:filter_filtercodes}pagebuilderlink{/getstring}\''
                        . ',\'popup\',\'width=1340,height=700\'); return false;' . PHP_EOL;
                    $menu .= '-{getstring:filter_filtercodes}photoeditor{/getstring}|'
                        . '{getstring:filter_filtercodes}photoeditorlink{/getstring}"'
                        . ' target="popup" onclick="window.open(\'{getstring:filter_filtercodes}photoeditorlink{/getstring}\''
                        . ',\'popup\',\'width=1340,height=700\'); return false;' . PHP_EOL;
                    $menu .= '-{getstring:filter_filtercodes}screenrec{/getstring}|'
                        . '{getstring:filter_filtercodes}screenreclink{/getstring}"'
                        . ' target="popup" onclick="window.open(\'{getstring:filter_filtercodes}screenreclink{/getstring}\''
                        . ',\'popup\',\'width=1340,height=700\'); return false;' . PHP_EOL;
                    $menu .= '-###' . PHP_EOL;
                    $menu .= '-Dev docs|https://moodle.org/development|Moodle.org ({getstring}english{/getstring})' . PHP_EOL;
                    $menu .= '-Dev forum|https://moodle.org/mod/forum/view.php?id=55|Moodle.org ({getstring}english{/getstring})' .
                            PHP_EOL;
                    $menu .= '-Tracker|https://tracker.moodle.org/|Moodle.org ({getstring}english{/getstring})' . PHP_EOL;
                    $menu .= '-AMOS|https://lang.moodle.org/|Moodle.org ({getstring}english{/getstring})' . PHP_EOL;
                    $menu .= '-WCAG 2.1|https://www.w3.org/WAI/WCAG21/quickref/|W3C ({getstring}english{/getstring})' . PHP_EOL;
                    $menu .= '-###' . PHP_EOL;
                    $menu .= '-DevTuts|https://www.youtube.com/watch?v=UY_pcs4HdDM|{getstring}english{/getstring}' . PHP_EOL;
                    $menu .= '-Moodle Development School|https://moodledev.moodle.school/|{getstring}english{/getstring}' . PHP_EOL;
                    $menuurl = 'https://moodle.academy/course/index.php?categoryid=4';
                    $menu .= '-Moodle Dev Academy|' . $menuurl . '|{getstring}english{/getstring}' . PHP_EOL;
                }
                $replace['/\{menudev\}/i'] = $menu;
            }

            // Tag: {menuthemes}.
            // Description: Theme switcher for custom menu. Only for administrators. Not available after POST.
            // Parameters: None.
            // Allow Theme Changes on URL must be enabled for this to have any effect.
            if (stripos($text, '{menuthemes}') !== false) {
                $menu = '';
                if (empty($_POST) && is_siteadmin() && !is_role_switched($PAGE->course->id)) { // If a site administrator.
                    if (get_config('core', 'allowthemechangeonurl')) {
                        $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http")
                            . "://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
                        $url .= (strpos($url, '?') ? '&' : '?');
                        $themeslist = \core_component::get_plugin_list('theme');
                        $menu = '';
                        foreach ($themeslist as $theme => $themedir) {
                            $themename = ucfirst(get_string('pluginname', 'theme_' . $theme));
                            $menu .= '-' . $themename . '|' . $url . 'theme=' . $theme . PHP_EOL;
                        }

                        // Add links to Advanced Theme Settings and to Current theme settings.
                        $theme = $PAGE->theme->name;
                        $menu = 'Themes' . PHP_EOL . $menu;
                        if ($CFG->branch >= 404) {
                            $label = 'themesettingsadvanced';
                            $section = 'themesettingsadvanced';
                        } else {
                            $label = 'themesettings';
                            $section = 'themesettings';
                        }

                        $menu .= '-###' . PHP_EOL;
                        $menu .= '-{getstring:admin}' . $label . '{/getstring}|/admin/settings.php' .
                            '?section=' . $section . '|Including custom menus, designer mode, theme in URL' . PHP_EOL;

                        if (!file_exists($CFG->dirroot . '/mod/hvp/version.php')) { // Not compatible with mod_hvp.
                            if (file_exists($CFG->dirroot . '/theme/' . $theme . '/settings.php')) {
                                require_once($CFG->libdir . '/adminlib.php');
                                if (admin_get_root()->locate('theme_' . $theme)) {
                                    // Settings use categories interface URL.
                                    $url = '/admin/category.php?category=theme_' . $theme . PHP_EOL;
                                } else {
                                    // Settings use tabs interface URL.
                                    $url = '/admin/settings.php?section=themesetting' . $theme . PHP_EOL;
                                }
                                $menu .= '-{getstring:admin}currenttheme{/getstring}|' . $url;
                            }
                        }
                    }
                }
                $replace['/\{menuthemes\}/i'] = $menu;
            }

            // Tag: {menulanguages}.
            // Description: Language switcher for custom menu. Not available after POST.
            // Parameters: None.
            if (stripos($text, '{menulanguages}') !== false) {
                $menu = '';
                if (empty($_POST)) {
                    $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http")
                    . "://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
                    $url .= (strpos($url, '?') ? '&' : '?');

                    // Get list of available languages.
                    $availablelanguages = get_string_manager()->get_list_of_translations();
                    if (count($availablelanguages) > 1) {
                        foreach ($availablelanguages as $langcode => $langname) {
                            // Create a link for each language.
                            $menu .= '-' . $this->format_custommenuitem($langname) . '|' . $url . 'lang=' . $langcode . PHP_EOL;
                        }
                        if (!empty($menu)) {
                            $menu = get_string('language') . '||' . get_string('languageselector') . PHP_EOL . $menu;
                        }
                    }
                }
                $replace['/\{menulanguages\}/i'] = $menu;
            }

            // Tag: {menuwishlist}.
            // Description: Displays a list of wishlisted courses for the Primary (Custom) menu with an option to add or remove
            // the current course from the wishlist. The list will be sorted alphabetically. If there are no courses in the
            // wishlist or we are on a site page, a message will be displayed.
            // Parameters: None.
            if (stripos($text, '{menuwishlist}') !== false) {
                // If not logged in, or guest user, do not display the Wishlist.
                if (!isloggedin() || isguestuser()) {
                    $menu = '';
                } else {
                    global $USER, $DB, $PAGE;

                    // Get the user's wishlist from the user_preference table.
                    $wishlist = $DB->get_record('user_preferences', [
                        'userid' => $USER->id,
                        'name' => 'filter_filtercodes_wishlist',
                    ]);
                    $wishlist = $wishlist ? explode(',', $wishlist->value) : [];

                    // Generate the list of wishlisted courses.
                    $menu = '';
                    foreach ($wishlist as $courseid) {
                        $course = $DB->get_record('course', ['id' => $courseid]);
                        if ($course) {
                            $courseurl = (new \moodle_url('/course/view.php', ['id' => $course->id]))->out();
                            $menu .= '-' . $this->format_custommenuitem($course->fullname) . '|' . $courseurl . "\n";
                        }
                    }
                    if (!empty($menu)) {
                        // Sort course names.
                        $menu = explode("\n", $menu);
                        $menu = array_filter($menu, 'strlen');
                        usort($menu, 'strnatcasecmp');
                        $menu = trim(implode("\n", $menu));
                    }

                    // Check if the current course is in the wishlist.
                    if ($PAGE->course->id === SITEID) {
                        if (empty($menu)) {
                            $menu = '-' . get_string('wishlist_nocourses', 'filter_filtercodes') . "\n";
                        }
                    } else {
                        if (!empty($menu)) {
                            $menu .= "\n-###\n";
                        }
                        $action = in_array($PAGE->course->id, $wishlist) ? 'remove' : 'add';
                        $url = (new \moodle_url('/filter/filtercodes/action.php', [
                            'courseid' => $PAGE->course->id,
                            'action' => $action,
                        ]))->out();
                        $menu .= '-' . get_string('wishlist_' . $action, 'filter_filtercodes') . '|' . $url . "\n";
                    }
                    $menu = get_string('wishlist', 'filter_filtercodes') . "\n" . $menu;
                }

                // Replace the {menuwishlist} tag with the generated wishlist output, if any.
                $replace['/\{menuwishlist\}/i'] = $menu;
            }
        }

        // Check if any {course*} or %7Bcourse*%7D tags. Note: There is another course tags section further down.
        $coursetagsexist = (stripos($text, '{course') !== false || stripos($text, '%7Bcourse') !== false);
        if ($coursetagsexist) {
            // Tag: {coursesummary} or {coursesummary courseid}.
            // Description: Course summary as defined in the course settings.
            // Optional parameters: Course id. Default is to use the current course, or site summary if not in a course.
            if (stripos($text, '{coursesummary') !== false) {
                if (stripos($text, '{coursesummary}') !== false) {
                    // No course ID specified.
                    $coursecontext = \context_course::instance($PAGE->course->id);
                    $PAGE->course->summary == null ? '' : $PAGE->course->summary;
                    $summary = file_rewrite_pluginfile_urls(
                        $PAGE->course->summary,
                        'pluginfile.php',
                        $coursecontext->id,
                        'course',
                        'section',
                        0
                    );
                    $replace['/\{coursesummary\}/i'] = format_text(
                        $summary,
                        FORMAT_HTML,
                        ['context' => $coursecontext]
                    );
                }
                if (stripos($text, '{coursesummary ') !== false) {
                    // Course ID was specified.
                    preg_match_all('/\{coursesummary ([0-9]+)\}/', $text, $matches);
                    // Eliminate course IDs.
                    $courseids = array_unique($matches[1]);
                    $coursecontext = \context_course::instance($PAGE->course->id);
                    foreach ($courseids as $id) {
                        $course = $DB->get_record('course', ['id' => $id]);
                        if (!empty($course)) {
                            $course->summary == null ? '' : $course->summary;
                            $replace['/\{coursesummary ' . $course->id . '\}/isuU'] = format_text(
                                $course->summary,
                                FORMAT_HTML,
                                ['context' => $coursecontext]
                            );
                        }
                    }
                    unset($matches, $course, $courseids, $id);
                }
            }
        }

        // Tag: {formquickquestion}
        // Tag: {formcheckin}
        // Tag: {formcontactus}
        // Tag: {formcourserequest}
        // Tag: {formsupport}
        // Tag: {formsesskey}
        //
        // Description: Tags used to generate pre-define forms for use with ContactForm plugin.
        // Parameters: None.
        if (stripos($text, '{form') !== false) {
            $pre = '<form action="{wwwcontactform}" method="post" class="cf ';
            $post = '</form>';
            $options = ['noclean' => true, 'para' => false, 'newlines' => false];
            // These require that you already be logged-in.
            foreach (['formquickquestion', 'formcheckin'] as $form) {
                if (stripos($text, '{' . $form . '}') !== false) {
                    if ($this->isauthenticateduser()) {
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

        // Tag: {global_[custom]}.
        // Description: Global Custom tags as defined in plugin settings.
        // Parameters: custom: Name of custom global tag from FilterCodes settings.
        if (stripos($text, '{global_') !== false) {
            // Get total number of defined global block tags.
            $globaltagcount = get_config('filter_filtercodes', 'globaltagcount');
            for ($i = 1; $i <= $globaltagcount; $i++) {
                // Get name of tag.
                $tag = get_config('filter_filtercodes', 'globalname' . $i);
                // If defined and tag exists in the content.
                if (!empty($tag) && stripos($text, '{global_' . $tag . '}') !== false) {
                    // Replace the tag with new content.
                    $content = get_config('filter_filtercodes', 'globalcontent' . $i);
                    $content = format_text($content, FORMAT_HTML, ['noclean' => true, 'para' => false, 'newlines' => false]);
                    $replace['/\{global_' . $tag . '\}/i'] = $content;
                }
            }
            unset($i);
            unset($globaltagcount);
            unset($tag);
            unset($content);
        }

        // Tag: {teamcards}.
        // Description: Displays a series of card for each contact on the site. Configurable in FilterCodes settings.
        // Note: Included selected roles in Site Administration > Appearance > Course > Course Contacts.
        // Parameters: None.
        if (stripos($text, '{teamcards}') !== false) {
            global $OUTPUT, $DB;

            $sql = 'SELECT DISTINCT u.id, u.username, u.firstname, u.lastname, u.email, u.picture, u.imagealt, u.firstnamephonetic,
                    u.lastnamephonetic, u.middlename, u.alternatename, u.description, u.phone1
                    FROM {course} c, {role_assignments} ra, {user} u, {context} ct
                    WHERE c.id = ct.instanceid AND ra.roleid in (?) AND ra.userid = u.id AND ct.id = ra.contextid
                        AND u.suspended = 0 AND u.deleted = 0
                    ORDER BY u.lastname desc, u.firstname';
            $users = $DB->get_records_sql($sql, [$CFG->coursecontact]);

            $cards = '';
            if (count($users)) {
                $clinktype = get_config('filter_filtercodes', 'teamcardslinktype');
                $cardformat = get_config('filter_filtercodes', 'teamcardsformat');
                $narrowpage = get_config('filter_filtercodes', 'narrowpage');

                switch ($cardformat) { // Show as info icon.
                    case 'infoicon':
                        $info = get_string('info');
                        $prewrap = '<a class="btn btn-link p-0 m-0 align-baseline" role="button" data-container="body"'
                                . ' data-toggle="popover" data-placement="right" data-content="<div class=&quot;no-overflow&quot;>'
                                . '<p>';
                        $postwrap = '</p></div>" data-html="true" tabindex="0" data-trigger="focus"><i class="icon'
                                . ' fa fa-info-circle text-info fa-fw " title="' . $info . '" aria-label="' . $info . '"></i></a>';
                        break;
                    case 'brief': // Show as text.
                        $prewrap = '<br><p class="smaller">';
                        $postwrap = '</p>';
                        break;
                    case 'verbose': // Show as text.
                        break;
                    default: // Don't show user description.
                        $cardformat = '';
                }

                // Prepare some strings.
                $linksr = [
                        '' => '',
                        'email' => get_string('issueremail', 'badges'),
                        'message' => get_string('message', 'message'),
                        'profile' => get_string('profile'),
                        'phone' => get_string('phone'),
                ];
                if ($cardformat == 'verbose') {
                    if (empty($CFG->enablegravatar)) {
                        $blankavatarurl = $this->getblankavatarurl(150);
                    }
                    foreach ($users as $user) {
                        $cards .= '<div class="clearfix mb-4">';
                        $name = '<h3 class="h4">' . get_string('fullnamedisplay', null, $user) . '</h3>';
                        $cards .= $this->userlink($clinktype, $user, $name);
                        if (empty($user->picture) && empty($CFG->enablegravatar)) {
                            $cards .= '<img src="' . $blankavatarurl . '" class="img-fluid" width="150" height="150" alt="">';
                        } else {
                            $cards .= $OUTPUT->user_picture($user, [
                                    'size' => '150',
                                    'class' => 'img-fluid pull-left p-1 border mr-4',
                                    'link' => false, 'visibletoscreenreaders' => false,
                            ]);
                        }
                        $cards .= format_string($user->description);
                        $cards .= '</div><hr>';
                    }
                } else {
                    if (empty($CFG->enablegravatar)) {
                        $blankavatarurl = $this->getblankavatarurl(250);
                    }
                    $cards .= '<div class="row" id="fc_teamcards" style="width:99%;">';
                    foreach ($users as $user) {
                        $cards .= '<div class="col-sm-6 col-md-4 col-lg-3 col-xl-' . (empty($narrowpage) ? 4 : 3) . ' mt-3">';
                        if (empty($user->picture) && empty($CFG->enablegravatar)) {
                            $cards .= '<img src="' . $blankavatarurl . '" class="img-fluid" width="250" height="250" alt="">';
                        } else {
                            $cards .= $OUTPUT->user_picture($user, [
                                    'size' => '250',
                                    'class' => 'img-fluid',
                                    'link' => false,
                                    'visibletoscreenreaders' => false,
                                ]);
                        }
                        $name = '<br><h3 class="h5 font-weight-bold d-inline">' . get_string('fullnamedisplay', null, $user) .
                                '</h3>';
                        $cards .= $this->userlink($clinktype, $user, $name);
                        if (!empty($user->description) && !empty($cardformat)) {
                            $cards .= $prewrap . format_string($user->description) . $postwrap;
                        }
                        $cards .= '</div>';
                    }
                    $cards .= '</div>';
                }
            }
            $replace['/\{teamcards\}/i'] = $cards;
            unset($cards, $users, $sql, $info, $prewrap, $postwrap, $cardformat);
        }

        // Custom Course Fields - First implemented in Moodle 3.7.
        if ($CFG->branch >= 37) {
            // Tag: {course_field_shortname}.
            // Description: Content from the custom course field specified by its shortname.
            // Required Parameters: shortname of a custom course field.
            if (stripos($text, '{course_field_') !== false) {
                // Cached the custom course field data.
                static $coursefields;
                if (!isset($coursefields)) {
                    $handler = \core_course\customfield\course_handler::create();
                    $coursefields = $handler->export_instance_data_object($PAGE->course->id, true);
                    $fieldsvisible = $handler->export_instance_data_object($PAGE->course->id);
                    // Blank out the fields that should not be displayed.
                    foreach ($coursefields as $field => $value) {
                        if (empty($fieldsvisible->$field)) {
                            $coursefields->$field = '';
                        }
                    }
                }
                $coursecontext = \context_course::instance($PAGE->course->id);
                foreach ($coursefields as $field => $value) {
                    $shortname = strtolower($field);
                    // If the tag exists and it is not hidden in the custom course field's settings.
                    if (stripos($text, '{course_field_' . $shortname . '}') !== false) {
                        $replace['/\{course_field_' . $shortname . '\}/i'] = format_text(
                            $value,
                            FORMAT_HTML,
                            ['context' => $coursecontext]
                        );
                    }
                }
            }

            // Tag: {course_fields}.
            // Description: All content from the custom user profile fields specified by shortname as set in the user's profile.
            // Parameters: None.
            if (stripos($text, '{course_fields}') !== false) {
                // Display all custom course fields.
                $customfields = '';
                $thiscourse = new \core_course_list_element($PAGE->course);
                if ($thiscourse->has_custom_fields()) {
                    $handler = \core_course\customfield\course_handler::create();
                    $customfields = $handler->display_custom_fields_data($thiscourse->get_custom_fields());
                }
                $coursecontext = \context_course::instance($PAGE->course->id);
                $replace['/\{course_fields\}/i'] = format_text($customfields, FORMAT_HTML, ['context' => $coursecontext]);
            }
        }

        if (stripos($text, '{dashboard_siteinfo}') !== false) {
            if (is_siteadmin() && !is_role_switched($PAGE->course->id)) { // If an administrator.
                $appbytes = @disk_free_space('.');
                $databytes = @disk_free_space($CFG->dataroot);
                $disktxt = $this->humanbytes($databytes);
                if ($appbytes != $databytes) {
                    $disktxt = 'app: ' . $disktxt . ' | data: ' . $this->humanbytes($databytes);
                }

                $cards = [];
                $cards[] = [
                    'icon' => 'fa-database',
                    'label' => 'Available disk space',
                    'info' => $disktxt,
                ];
                $cards[] = [
                    'icon' => 'fa-graduation-cap',
                    'label' => get_string('courses'),
                    'info' => get_string('total') . ' {coursecount}',
                ];
                $cards[] = [
                    'icon' => 'fa-users',
                    'label' => get_string('users'),
                    'info' => get_string('active') . ' {usersonline} | ' . get_string('total') . ' {usersactive}',
                ];
                $totalcards = count($cards);

                $content = '
                    <div class="fcdashboard-siteinfo container-fluid">
                        <h3 class="sr-only">Site info dashboard</h2>
                        <div class="row">
                ';
                for ($card = 0; $card < $totalcards; $card++) {
                    $content .= '
                            <div class="col-12 col-md-6 col-lg-3">
                                <div class="card-body">
                                    <i class="fa fa-3x ' . $cards[$card]['icon'] . ' float-left pr-3"></i>
                                    <h3 class="h5 pt-1">' . $cards[$card]['label'] . '</h3>
                                    <p>' . $cards[$card]['info'] . '</p>
                                </div>
                            </div>
                    ';
                }
                $content .= '
                        </div>
                    </div>
                ';
                $coursecontext = \context_course::instance($PAGE->course->id);
                $replace['/\{dashboard_siteinfo\}/i'] = format_text($content, FORMAT_HTML, ['context' => $coursecontext]);
            } else {
                $replace['/\{dashboard_siteinfo\}/i'] = '';
            }
        }

        /* ---------------- Apply all of the filtercodes so far. ---------------*/

        return $this->replacetags($text, $replace);
    }

    /**
     * Handle escaped tags.
     *
     * @param string $text Content to be processed.
     * @return string Processed text.
     *
     * Note: First time this function is called, it will escape all tags that should not be processed.
     *       The second time it is called, it will turn escaped tags back into unprocessed plain text tags.
     */
    private function escapedtags($text) {
        static $escapedtags;
        static $escapedtagsenc;
        static $escapebraces;

        // Don't process if this feature is disabled.
        if (!isset($escapebraces)) {
            $escapebraces = !empty(get_config('filter_filtercodes', 'escapebraces'));
            if (!$escapebraces) {
                return $text;
            }
        }

        if (!isset($escapedtags) || !isset($escapedtagsenc)) {
            // First time called, temporarily replace the escaped tags so they will not be processed by FilterCodes.

            // Regular tags.
            $escapedtags = (strpos($text, '[{') !== false && strpos($text, '}]') !== false);
            if ($escapedtags) {
                $text = str_replace('[{', chr(2), $text);
                $text = str_replace('}]', chr(3), $text);
            }

            // Encoded tags.
            $escapedtagsenc = (strpos($text, '[%7B') !== false && strpos($text, '%7D]') !== false);
            if ($escapedtagsenc) {
                $text = str_replace('[%7B', chr(4), $text);
                $text = str_replace('%7D]', chr(5), $text);
            }
        } else {
            // Second time called, complete the process of putting back the tags, but not escaped.

            // Regular tags.
            if ($escapedtags) {
                $text = str_replace(chr(2), '{', $text);
                $text = str_replace(chr(3), '}', $text);
            }
            $escapedtags = null;

            // Encoded tags.
            if ($escapedtagsenc) {
                $text = str_replace(chr(4), '%7B', $text);
                $text = str_replace(chr(5), '%7D', $text);
            }
            $escapedtagsenc = null;
        }

        return $text;
    }

    /**
     * Applies all filters defined in $replace to the $text.
     *
     * @param string $text Content to be processed. Passed by reference.
     * @param array $replace Array in the format Key=Regex, Value=To be applied. Passed by reference.
     * @return boolean True of there are more changes, otherwise false.
     */
    private function replacetags(&$text, &$replace) {
        $newtext = null;
        $moretags = true;
        if (count($replace) > 0) {
            $newtext = preg_replace(array_keys($replace), array_values($replace), $text);
            if (!is_null($newtext)) {
                $text = $newtext;
                if (strpos($text, '{') === false && strpos($text, '%7B') === false) {
                    $moretags = false;
                }
            }
            $replace = [];
        }
        return $moretags;
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

        if (strpos($text, '{') === false && strpos($text, '%7B') === false || self::$infiltercodes) {
            return $text;
        }

        // Declare some of the static variables.
        static $profilefields;
        static $profiledata;
        static $mygroupslist;
        static $mygroupingslist;
        static $mycohorts;

        $replace = []; // Array of key/value filterobjects.

        // Handle escaped tags to be ignored. Remove them so they don't get processed if the option to [{escape braces}] is enabled.
        $text = $this->escapedtags($text);
        self::$infiltercodes = true; // Prevent recursive calls to this function.

        // START: Process tags that may end up containing other tags first.

        // ...===================================================================================================================.
        // Tags that may create more content which could possibly include tags. These need to be processed first.
        // ...===================================================================================================================.

        // Loop through the tags that may have embedded tags until these generator tags have all been proceseed.

        $loop = 0; // We only support tags nested up to 3 deep - to handle circular references.
        do {
            $moretags = $this->generatortags($text);
        } while ($loop++ < 3 && $moretags);

        // We can now process all other tags including ones added by the code above.

        // ...===================================================================================================================.
        // Tags that may be used as parameters by other tags should be processed before the tags that may include them.
        // ...===================================================================================================================.

        // Tag: {lang}.
        // Description: First 2-letters, in lowercase, of current language of user interface.
        // Parameters: None.
        if (stripos($text, '{lang}') !== false) {
            // Replace with 2-letter current primary language.
            $replace['/\{lang\}/i'] = substr(current_language(), 0, 2);
        }

        // Tag: {preferredlanguage}.
        // Description: First 2-letters, in lowercase, of the user's preferred language as set in their profile.
        // Parameters: None.
        if (stripos($text, '{preferredlanguage}') !== false) {
            if ($this->isauthenticateduser()) {
                // If user does not have a preferred language, default to the system default language.
                $preflang = empty($USER->lang) ? $CFG->lang : $USER->lang;
                if ($preflang == 'en') {
                    $langconfig = $CFG->dirroot . '/lang/en/langconfig.php';
                } else {
                    $langconfig = $CFG->dataroot . '/lang/' . $preflang . '/langconfig.php';
                }
                // Ignore parents here for now.
                $string = [];
                include($langconfig);
                if (!empty($string['thislanguage'])) {
                    $replace['/\{preferredlanguage\}/i'] = '<span lang="' . $preflang . '">' . $string['thislanguage'] . '</span>';
                } else { // This should never happen since the known user already exists.
                    $replace['/\{preferredlanguage\}/i'] = get_string('unknown', 'notes');
                }
            } else {
                $replace['/\{preferredlanguage\}/i'] = '';
            }
            unset($preflang, $langconfig, $string);
        }

        // Tag: %7Buserid%7D.
        // Description: Alias for {userid}. Useful for encoded urls.
        // Parameters: None.
        if (stripos($text, '%7Buserid%7D') !== false) {
            $text = str_replace('%7Buserid%7D', '{userid}', $text);
        }

        // Tag: {userid}.
        // Description: User's user ID.
        // Parameters: None.
        if (stripos($text, '{userid}') !== false) {
            $replace['/\{userid\}/i'] = $USER->id;
        }

        // Tags: {courseid...
        if (stripos($text, '{course') !== false || stripos($text, '%7Bcourseid') !== false) {
            $courseid = 1; // Default to site.
            if ($PAGE->pagetype == 'enrol-index') {
                // Make it work, even when we are on the enrolment page.
                $courseid = optional_param('id', $courseid, PARAM_INT);
            } else {
                $courseid = $PAGE->course->id;
            }

            // Tag: %7Bcourseid%7D.
            // Description: An alias for {courseid}. Useful for encoded URLs.
            // Parameters: None.
            if (stripos($text, '%7Bcourseid%7D') !== false) {
                $text = str_replace('%7Bcourseid%7D', '{courseid}', $text);
            }

            // Tag: {courseid}.
            // Description: Course ID. Will be 1 (SITE) if not in a course.
            // Parameters: None.
            if (stripos($text, '{courseid}') !== false) {
                $replace['/\{courseid\}/i'] = $courseid;
            }

            // Tag: {coursegradepercent}.
            // Description: Current overall course grade as a percentage.
            // Parameters:  None.
            if (version_compare(PHP_VERSION, '7.0.0') >= 0 && stripos($text, '{coursegradepercent}') !== false) {
                require_once($CFG->libdir . '/gradelib.php');
                require_once($CFG->dirroot . '/grade/querylib.php');
                $gradeobj = grade_get_course_grade($USER->id, $PAGE->course->id);
                if (!empty($grademax = floatval($gradeobj->item->grademax))) {
                    // Avoid divide by 0 error if no grades have been defined.
                    $grade = floatval($grademax) > 0 ? (int) ($gradeobj->grade / floatval($grademax) * 100) : 0;
                } else {
                    $grade = 0;
                }
                $replace['/\{coursegradepercent\}/i'] = $grade;
            }

            // Tag: {courseprogresspercent}.
            // Description: Course completion progress percentage as a number.
            // Parameters: None.
            if (stripos($text, '{courseprogresspercent}') !== false) {
                $progress = $this->completionprogress();
                if ($progress != -1) { // Is enabled.
                    $replace['/\{courseprogresspercent\}/i'] = $progress;
                } else {
                    $replace['/\{courseprogresspercent\}/i'] = '';
                }
                unset($progress);
            }

            // Tag: %7Bcoursecontextid%7D.
            // Description: Alias for {coursecontextid}. Useful for encoded URLs.
            // Parameters: None.
            if (stripos($text, '%7Bcoursecontextid%7D') !== false) {
                $text = str_replace('%7Bcoursecontextid%7D', '{coursecontextid}', $text);
            }

            // Tag: {coursecontextid}.
            // Description: Course context id.
            // Parameters: None.
            if (stripos($text, '{coursecontextid}') !== false) {
                $context = \context_course::instance($PAGE->course->id);
                $coursecontextid = isset($PAGE->course->id) ? $context->id : 1;
                $replace['/\{coursecontextid\}/i'] = $coursecontextid;
            }

            // Tag: %7Bcoursemoduleid%7D.
            // Description: Alias for {coursemoduleid}. Useful for encoded URLs.
            // Parameters: None.
            if (stripos($text, '%7Bcoursemoduleid%7D') !== false) {
                $text = str_replace('%7Bcoursemoduleid%7D', '{coursemoduleid}', $text);
            }

            // Tag: {coursemoduleid}.
            // Description: Course module id.
            // Parameters: None.
            // Note: %7Bcoursemoduleid%7D is an alias for {coursemoduleid}. Useful for encoded URLs.
            if (stripos($text, '{coursemoduleid}') !== false) {
                if (isset($PAGE->cm->id)) {
                    $replace['/\{coursemoduleid\}/isu'] = $PAGE->cm->id;
                }
            }

            // Tag: {courseshortname}.
            // Description: The short name of this course. If not in a course, will use the site's shortname.
            // Parameters: None.
            if (stripos($text, '{courseshortname}') !== false) {
                $course = $PAGE->course;
                if ($course->id == $SITE->id) { // Front page - use site name.
                    $replace['/\{courseshortname\}/i'] = $SITE->shortname;
                } else { // In a course - use course full name.
                    $replace['/\{courseshortname\}/i'] = $course->shortname;
                }
            }
        }

        // Tag: {categoryid}.
        // Description: Category ID in which the current course is located.
        // Parameters: None.
        if (stripos($text, '{categoryid}') !== false) {
            if (empty($PAGE->course->category)) {
                // If we are not in a course, check if categoryid is part of URL (ex: course lists).
                $catid = optional_param('categoryid', 0, PARAM_INT);
            } else {
                // Retrieve the category id of the course we are in.
                $catid = $PAGE->course->category;
            }
            $replace['/\{categoryid\}/i'] = $catid;
        }

        if (stripos($text, '{refer') !== false) {
            // Tag: {referer}.
            // Description: Alias for {referrer} tag. For backwards compatibility with original incorrect spelling of the tag.
            // Parameters: None.
            if (stripos($text, '{referer}') !== false) {
                $text = str_replace('{referer}', '{referrer}', $text);
            }

            // Tag: {referrer}.
            // Description: URL that brought the user to the current page.
            // Parameters: None.
            if (stripos($text, '{referrer}') !== false) {
                if ($CFG->branch >= 28) {
                    $replace['/\{referrer\}/i'] = get_local_referer(false);
                } else {
                    $replace['/\{referrer\}/i'] = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
                }
            }
        }

        // Tag: %7Bwwwroot%7D.
        // Description: Alias for {wwwroot}.
        // Parameters: None.
        if (stripos($text, '%7Bwwwroot%7D') !== false) {
            $text = str_replace('%7Bwwwroot%7D', '{wwwroot}', $text);
        }

        // Tag: {wwwroot}.
        // Description: URL of the site's webroot.
        // Parameters: None.
        if (stripos($text, '{wwwroot}') !== false) {
            $replace['/\{wwwroot\}/i'] = $CFG->wwwroot;
        }

        // Tag: {pagepath}.
        // Description: Path of the current page without wwwroot.
        // Parameters: None.
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
            // Description: Complete URL of the current page.
            // Parameters: None.
            if (stripos($text, '{thisurl}') !== false) {
                $replace['/\{thisurl\}/i'] = $url;
            }
            // Tag: {thisurl_enc}.
            // Description: Complete URL of the current page - URL encoded for use as a parameter of a URL.
            // Parameters: None.
            if (stripos($text, '{thisurl_enc}') !== false) {
                $replace['/\{thisurl_enc\}/i'] = rawurlencode($url);
            }
        }

        // Tag: {protocol}.
        // Description: Protocol used to access the website (http or https).
        // Parameters: None.
        if (stripos($text, '{protocol}') !== false) {
            $replace['/\{protocol\}/i'] = 'http' . ($this->ishttps() ? 's' : '');
        }

        // Tag: {ipaddress}.
        // Description: IP Address of the web client accessing the page.
        // Parameters: None.
        if (stripos($text, '{ipaddress}') !== false) {
            $replace['/\{ipaddress\}/i'] = getremoteaddr();
        }

        // Tag: {sesskey}.
        // Description: Moodle Session key. Does not work in forums. May be disabled in FilterCodes settings.
        // Parameters: None.
        if (get_config('filter_filtercodes', 'enable_sesskey')) {
            if ((!isset($PAGE->cm->modname) || $PAGE->cm->modname != 'forum') && $PAGE->pagetype != 'admin-cron') {
                if (stripos($text, '{sesskey}') !== false) {
                    // Tag: {sesskey}.
                    $replace['/\{sesskey\}/i'] = sesskey();
                }
                // Tag: %7Bsesskey%7D (for encoded URLs).
                if (stripos($text, '%7Bsesskey%7D') !== false) {
                    $replace['/%7Bsesskey%7D/i'] = sesskey();
                }
            }
        }

        // Tag: %7Bsectionid%7D.
        // Description: Alias of {sectionid}.
        // Parameters: None.
        if (stripos($text, '%7Bsectionid%7D') !== false) {
            $text = str_replace('%7Bsectionid%7D', '{sectionid}', $text);
        }

        // Tag: {sectionid}.
        // Description: The course section id in which the current activity is located.
        // Parameters: None.
        if (stripos($text, '{sectionid}') !== false) {
            $replace['/\{sectionid\}/i'] = @$PAGE->cm->sectionnum;
        }

        // Tag: {getstring:component_name}stringidentifier{/getstring} or {getstring}stringidentifier{/getstring}.
        // Description: Retrieves Moodle string.
        // Optional Parameter: Component name. If component_name (plugin) is not specified, will default to "moodle".
        // Required Content: The string identifier.
        if (stripos($text, '{/getstring}') !== false) {
            // Replace {getstring:} tag and parameters with retrieved content.
            $newtext = preg_replace_callback(
                '/\{getstring:?(\w*)\}(\w+)\{\/getstring\}/isuU',
                function ($matches) use ($CFG) {
                    if ($strexists = get_string_manager()->string_exists($matches[2], $matches[1]) && $CFG->branch >= 28) {
                        $strexists = !get_string_manager()->string_deprecated($matches[2], $matches[1]);
                    }
                    if ($strexists) {
                        return get_string($matches[2], $matches[1]);
                    } else {
                        return "{getstring" . (!empty($matches[1]) ? ":$matches[1]" : '') . "}$matches[2]{/getstring}";
                    }
                },
                $text
            );
            if ($newtext !== false) {
                $text = $newtext;
            }
        }

        // Tag: {courseunenrolurl}.
        // Description: URL to unenrol from a course.
        // Parameters: None.
        if (stripos($text, '{courseunenrolurl}') !== false) {
            require_once($CFG->libdir . '/enrollib.php');
            $course = $PAGE->course;
            $coursecontext = \context_course::instance($course->id);
            $replace['/\{courseunenrolurl\}/i'] = '';
            if ($course->id != SITEID && $this->isauthenticateduser() && is_enrolled($coursecontext)) {
                $plugins   = enrol_get_plugins(true);
                $instances = enrol_get_instances($course->id, true);
                foreach ($instances as $instance) {
                    if (!isset($plugins[$instance->enrol])) {
                        continue;
                    }
                    $plugin = $plugins[$instance->enrol];
                    if ($unenrollink = $plugin->get_unenrolself_link($instance)) {
                        $replace['/\{courseunenrolurl\}/i'] = $unenrollink->out();
                        break;
                    }
                }
            }
        }

        // Tag: {fa fa-icon-name}
        // Description: FontAwesome 4.7 and 6.0 icons.
        // Required Parameter: 'fa' can be fa|fas|fa-solid|fab|fa-brands. Additional options available if using FontAwesome Pro.
        // Required Parameter: icon-name. See full list at https://fontawesome.com/v4/icons/ or https://fontawesome.com/v6/icons/.
        // Note that FontAwesome 6.x icons are only included with Moodle 4.2+.
        if (stripos($text, '{fa') !== false) {
            // Replace {fa...} tag and parameters with FontAwesome HTML.
            $regex = '/\{fa(';
            $regex .= 's|-solid|'; // Solid - included with Moodle.
            $regex .= 'b|-brands|'; // Brands - included with Moodle.
            // The rest require the FontAwesome Pro.
            $regex .= 'r|-regular|';
            $regex .= 'l|-light|';
            $regex .= 't|-thin|';
            $regex .= 'd|-duotone|';
            $regex .= 'ss|-sharp\s+fa-solid|';
            $regex .= 'sr|-sharp\s+fa-regular|';
            $regex .= 'sl|-sharp\s+fa-light|';
            $regex .= 'st|-sharp\s+fa-thin|';
            $regex .= 'sd|-sharp\s+fa-duotone';
            $regex .= '){0,1}\s+fa-([a-z0-9 -]+)\}/isuU';
            $newtext = preg_replace_callback(
                $regex,
                function ($matches) {
                    $matches[0] = $matches[0] == null ? '' : $matches[0];
                    return '<span class="' . substr($matches[0], 1, -1) . '" aria-hidden="true"></span>';
                },
                $text
            );
            if ($newtext !== false) {
                $text = $newtext;
            }
        }

        // Tag: {glyphicon glyphion-name}.
        // Description: Glyphicon icons.
        // Required Parameter: name.
        // Note: Glyphicons Font/CSS must be loaded as part of your theme.
        if (stripos($text, '{glyphicon ') !== false) {
            // Replace {glyphicon glyphicon-...} tag and parameters with Glyphicons HTML.
            $newtext = preg_replace_callback(
                '/\{glyphicon\sglyphicon-([a-z0-9 -]+)\}/isuU',
                function ($matches) {
                    $matches[0] = $matches[0] == null ? '' : $matches[0];
                    return '<span class="' . substr($matches[0], 1, -1) . '" aria-hidden="true"></span>';
                },
                $text
            );
            if ($newtext !== false) {
                $text = $newtext;
            }
        }

        // Tag: {multilang xx}...{/multilang}.
        // Description: Works just like the Moodle's Multi-Language filter except it's a plain text tag. No more HTML editing!
        // Required Parameter: xx: The language.
        // Requires content between tags.
        // Note: This tag has a dependency on Moodle's Multi-Language Content filter being enabled. That filter
        // must be below FilterCodes in Site Administration > Plugins > Manage Filters. This does not do any filtering of its own.
        // For more information on the Multi-Language Content filter see https://docs.moodle.org/en/Multi-language_content_filter.
        if (stripos($text, '{/multilang}') !== false) {
            // This is specifically to make it easier to use Moodle's own multi-language filter.
            $replace['/\{multilang\s+([a-z-]+)\}(.*)\{\/multilang\}/isuU'] = '<span lang="$1" class="multilang">$2</span>';
        }

        // Tag: {firstaccessdate} or {firstaccessdate dateTimeFormat}.
        // Description: Date that the user first accessed the site.
        // Optional parameters: dateTimeFormat - either one of Moodle's built-in data/time formats or php's strftime.
        if (stripos($text, '{firstaccessdate') !== false) {
            if ($this->isauthenticateduser() && !empty($USER->firstaccess)) {
                // Replace {firstaccessdate} tag with formatted date.
                if (stripos($text, '{firstaccessdate}') !== false) {
                    $replace['/\{firstaccessdate\}/i'] = userdate($USER->firstaccess, get_string('strftimedatefullshort'));
                }
                // Replace {firstaccessdate dateTimeFormat} tag and parameters with formatted date.
                if (stripos($text, '{firstaccessdate ') !== false) {
                    $newtext = preg_replace_callback(
                        '/\{firstaccessdate\s+(.+)\}/isuU',
                        function ($matches) use ($USER) {
                            // Check if this is a built-in Moodle date/time format.
                            if (!empty($matches[1]) && get_string_manager()->string_exists($matches[1], 'langconfig')) {
                                // It is! Get the strftime string.
                                $matches[1] = get_string($matches[1], 'langconfig');
                            }
                            return userdate($USER->firstaccess, $matches[1]);
                        },
                        $text
                    );
                    if ($newtext !== false) {
                        $text = $newtext;
                    }
                }
            } else {
                $replace['/\{firstaccessdate(.*)\}/i'] = get_string('never');
            }
        }

        // Tag: {lastlogin} or {lastlogin dateTimeFormat}.
        // Description: Date that the user last logged in to the site.
        // Optional parameters: dateTimeFormat - either one of Moodle's built-in data/time formats or php's strftime.
        if (stripos($text, '{lastlogin') !== false) {
            if ($this->isauthenticateduser() && !empty($USER->lastlogin)) {
                // Replace {lastlogin} tag with formatted date.
                if (stripos($text, '{lastlogin}') !== false) {
                    $replace['/\{lastlogin\}/i'] = userdate($USER->lastlogin, get_string('strftimedatetimeshort'));
                }
                // Replace {lastlogin dateTimeFormat} tag and parameters with formatted date.
                if (stripos($text, '{lastlogin ') !== false) {
                    $newtext = preg_replace_callback(
                        '/\{lastlogin\s+(.+)\}/isuU',
                        function ($matches) use ($USER) {
                            // Check if this is a built-in Moodle date/time format.
                            if (!empty($matches[1]) && get_string_manager()->string_exists($matches[1], 'langconfig')) {
                                // It is! Get the strftime string.
                                $matches[1] = get_string($matches[1], 'langconfig');
                            }
                            return userdate($USER->lastlogin, $matches[1]);
                        },
                        $text
                    );
                    if ($newtext !== false) {
                        $text = $newtext;
                    }
                }
            } else {
                $replace['/\{lastlogin(.*)\}/i'] = get_string('never');
            }
        }

        // Tag: {coursestartdate} or {coursestartdate dateTimeFormat courseid}.
        // Description: The course start date.
        // Optional Parameters: dateTimeFormat - either in a Moodle datetime format or a PHP strftime format.
        // Optional Parameters: id - id of a course.
        if (stripos($text, '{coursestartdate') !== false) {
            // Replace {coursestartdate} tag with formatted date.
            if (stripos($text, '{coursestartdate}') !== false) {
                if (!empty($PAGE->course->startdate)) {
                    $startdate = $PAGE->course->startdate;
                } else {
                    $startdate = $DB->get_field_select('course', 'startdate', 'id = :id', ['id' => $PAGE->course->id]);
                }
                if (!empty($startdate)) {
                    $replace['/\{coursestartdate\}/i'] = userdate($startdate, get_string('strftimedatefullshort'));
                } else {
                    $replace['/\{coursestartdate(.*)\}/isuU'] = get_string('notyetstarted', 'completion');
                }
            }

            // Replace {coursestartdate dateTimeFormat} tag and parameters with formatted date.
            if (stripos($text, '{coursestartdate ') !== false) {
                $newtext = preg_replace_callback(
                    '/\{coursestartdate\s(.*)(\s\d+)?\}/isuU',
                    function ($matches) use ($PAGE, $DB) {

                        // Optional date/time format.
                        if (is_numeric($matches[1])) {
                            // Only the course ID was specified.
                            $matches[2] = trim($matches[1]); // Course ID.
                            $matches[1] = ''; // Date/time format.
                        } else {
                            $matches[2] = empty($matches[2]) ? $PAGE->course->id : trim($matches[2]); // Course ID.
                            $matches[1] = trim($matches[1]);
                        }

                        // Optional course ID.
                        if (empty($matches[2])) { // No course ID, use current course.
                            if (!empty($PAGE->course->startdate)) {
                                $startdate = $PAGE->course->startdate;
                            } else {
                                $startdate = $DB->get_field_select(
                                    'course',
                                    'startdate',
                                    'id = :id',
                                    ['id' => $PAGE->course->id]
                                );
                            }
                        } else { // Course ID was specifed.
                            $course = $DB->get_record('course', ['id' => $matches[2]]);
                            if (!empty($course)) {
                                $startdate = $course->startdate;
                                if (!empty($course->startdate)) {
                                    $startdate = $course->startdate;
                                } else {
                                    $startdate = $DB->get_field_select(
                                        'course',
                                        'startdate',
                                        'id = :id',
                                        ['id' => $course->id]
                                    );
                                }
                            } else {
                                // Should only happen if course does not exist.
                                $startdate = 1; // December 31, 1969.
                            }
                        }

                        // Check if this is a built-in Moodle date/time format.
                        if (!empty($matches[1]) && get_string_manager()->string_exists($matches[1], 'langconfig')) {
                            // It is! Get the strftime string.
                            $matches[1] = get_string($matches[1], 'langconfig');
                        }

                        // Format the date.
                        if (!empty($startdate)) {
                            $startdate = userdate($startdate, $matches[1]);
                        } else {
                            $startdate = get_string('notyetstarted', 'completion');
                        }

                        return $startdate;
                    },
                    $text
                );
                if ($newtext !== false) {
                    $text = $newtext;
                }
            } else {
                $replace['/\{coursestartdate(.*)\}/isuU'] = get_string('notyetstarted', 'completion');
            }
        }

        // Tag: {courseenddate} or {coursesenddate dateTimeFormat courseid}.
        // Description: The course end date.
        // Optional Parameters: dateTimeFormat - either in a Moodle datetime format or a PHP strftime format.
        // Optional Parameters: id - id of a course.
        if (stripos($text, '{courseenddate') !== false) {
            // Replace {courseenddate} tag with formatted date.
            if (stripos($text, '{courseenddate}') !== false) {
                if (empty($PAGE->course->enddate)) {
                    $enddate = $PAGE->course->enddate;
                } else {
                    $enddate = $DB->get_field_select('course', 'enddate', 'id = :id', ['id' => $PAGE->course->id]);
                }
                if (!empty($enddate)) {
                    $replace['/\{courseenddate\}/i'] = userdate($enddate, get_string('strftimedatefullshort'));
                } else {
                    $replace['/\{courseenddate(.*)\}/isuU'] = get_string('none');
                }
            }

            // Replace {courseenddate dateTimeFormat} tag and parameters with formatted date.
            if (stripos($text, '{courseenddate ') !== false) {
                $newtext = preg_replace_callback(
                    '/\{courseenddate\s(.*)(\s\d+)?\}/isuU',
                    function ($matches) use ($PAGE, $DB) {

                        // Optional date/time format.
                        if (is_numeric($matches[1])) {
                            // Only the course ID was specified.
                            $matches[2] = trim($matches[1]); // Course ID.
                            $matches[1] = ''; // Date/time format.
                        } else {
                            $matches[2] = empty($matches[2]) ? $PAGE->course->id : trim($matches[2]); // Course ID.
                            $matches[1] = trim($matches[1]);
                        }

                        // Optional course ID.
                        if (empty($matches[2])) { // No course ID, use current course.
                            if (!empty($PAGE->course->enddate)) {
                                $enddate = $PAGE->course->enddate;
                            } else {
                                $enddate = $DB->get_field_select(
                                    'course',
                                    'enddate',
                                    'id = :id',
                                    ['id' => $PAGE->course->id]
                                );
                            }
                        } else { // Course ID was specifed.
                            $course = $DB->get_record('course', ['id' => $matches[2]]);
                            if (!empty($course)) {
                                $enddate = $course->enddate;
                                if (!empty($course->enddate)) {
                                    $enddate = $course->enddate;
                                } else {
                                    $enddate = $DB->get_field_select(
                                        'course',
                                        'enddate',
                                        'id = :id',
                                        ['id' => $course->id]
                                    );
                                }
                            } else {
                                // Should only happen if course does not exist.
                                $enddate = 1; // December 31, 1969.
                            }
                        }

                        // Check if this is a built-in Moodle date/time format.
                        if (!empty($matches[1]) && get_string_manager()->string_exists($matches[1], 'langconfig')) {
                            // It is! Get the strftime string.
                            $matches[1] = get_string($matches[1], 'langconfig');
                        }

                        // Format the date.
                        if (!empty($enddate)) {
                            $enddate = userdate($enddate, $matches[1]);
                        } else {
                            $enddate = get_string('none');
                        }

                        return $enddate;
                    },
                    $text
                );
                if ($newtext !== false) {
                    $text = $newtext;
                }
            } else {
                $replace['/\{courseenddate(.*)\}/isuU'] = get_string('none');
            }
        }

        // Tag: {coursecompletiondate} or {coursecompletiondate dateTimeFormat}.
        // Description: The course completion date.
        // Optional Parameters: dateTimeFormat - either in a Moodle datetime format or a PHP strftime format.
        if (stripos($text, '{coursecompletiondate') !== false) {
            if (
                $PAGE->course
                && isset($CFG->enablecompletion)
                && $CFG->enablecompletion == 1 // COMPLETION_ENABLED.
                && $PAGE->course->enablecompletion
            ) {
                $ccompletion = new \completion_completion(['userid' => $USER->id, 'course' => $PAGE->course->id]);
                $incomplete = get_string('notcompleted', 'completion');
            } else { // Completion not enabled.
                $incomplete = get_string('completionnotenabled', 'completion');
            }
            if (!empty($ccompletion->timecompleted)) {
                // Replace {coursecompletiondate} tag with formatted date.
                if (stripos($text, '{coursecompletiondate}') !== false) {
                    $replace['/\{coursecompletiondate\}/i'] = userdate(
                        $ccompletion->timecompleted,
                        get_string('strftimedatefullshort')
                    );
                }
                // Replace {coursecompletiondate dateTimeFormat} tag and parameters with formatted date.
                if (stripos($text, '{coursecompletiondate ') !== false) {
                    $newtext = preg_replace_callback(
                        '/\{coursecompletiondate\s+(.+)\}/isuU',
                        function ($matches) use ($ccompletion) {
                            // Check if this is a built-in Moodle date/time format.
                            if (!empty($matches[1]) && get_string_manager()->string_exists($matches[1], 'langconfig')) {
                                // It is! Get the strftime string.
                                $matches[1] = get_string($matches[1], 'langconfig');
                            }
                            return userdate($ccompletion->timecompleted, $matches[1]);
                        },
                        $text
                    );
                    if ($newtext !== false) {
                        $text = $newtext;
                    }
                }
            } else {
                $replace['/\{coursecompletiondate(.*)\}/isuU'] = $incomplete;
            }
        }

        // Tag: {courseenrolmentdate} or {courseenrolmentdate dateTimeFormat}.
        // Description: The course enrolment date.
        // Optional Parameters: dateTimeFormat - either in a Moodle datetime format or a PHP strftime format.
        if (stripos($text, '{courseenrolmentdate') !== false) {
            $sql = '
                SELECT ue.timecreated
                FROM {user} u
                JOIN {user_enrolments} ue ON ue.userid = u.id
                JOIN {enrol} e ON ue.enrolid = e.id
                WHERE ue.userid = :userid AND e.courseid = :courseid
            ';
            $thisuser = $DB->get_records_sql($sql, ['userid' => $USER->id, 'courseid' => $PAGE->course->id]);
            if (count($thisuser)) {
                // Gets the first key of the array.
                reset($thisuser);
                $datecreated = key($thisuser);
                // Replace {courseenrolmentdate} tag with formatted date.
                if (stripos($text, '{courseenrolmentdate}') !== false) {
                    $replace['/\{courseenrolmentdate\}/i'] = userdate($datecreated, get_string('strftimedatefullshort'));
                }
                // Replace {courseenrolmentdate dateTimeFormat} tag and parameters with formatted date.
                if (stripos($text, '{courseenrolmentdate ') !== false) {
                    $newtext = preg_replace_callback(
                        '/\{courseenrolmentdate\s+(.+)\}/isuU',
                        function ($matches) use ($datecreated) {
                            // Check if this is a built-in Moodle date/time format.
                            if (!empty($matches[1]) && get_string_manager()->string_exists($matches[1], 'langconfig')) {
                                // It is! Get the strftime string.
                                $matches[1] = get_string($matches[1], 'langconfig');
                            }
                            return userdate($datecreated, $matches[1]);
                        },
                        $text
                    );
                    if ($newtext !== false) {
                        $text = $newtext;
                    }
                }
            } else {
                $replace['/\{courseenrolmentdate(.*)\}/isuU'] = '';
            }
        }

        // Tag: {now} or {now dateTimeFormat}.
        // Description: Current year, 4 digits.
        // Optional parameter: dateTimeFormat - either one of Moodle's built-in data/time formats or php's strftime.
        if (stripos($text, '{now') !== false) {
            // Replace {now} tag with formatted date.
            $now = time();
            if (stripos($text, '{now}') !== false) {
                $replace['/\{now\}/i'] = userdate($now, get_string('strftimedatefullshort'));
            }
            // Replace {now dateTimeFormat} tag and parameters with formatted date.
            if (stripos($text, '{now ') !== false) {
                $newtext = preg_replace_callback(
                    '/\{now\s+(.+)\}/isuU',
                    function ($matches) use ($now) {
                        // Check if this is a built-in Moodle date/time format.
                        if (!empty($matches[1]) && get_string_manager()->string_exists($matches[1], 'langconfig')) {
                            // It is! Get the strftime string.
                            $matches[1] = get_string($matches[1], 'langconfig');
                        }
                        return userdate($now, $matches[1]);
                    },
                    $text
                );
                if ($newtext !== false) {
                    $text = $newtext;
                }
            }
            unset($now);
        }

        // Tag: {keyboard}text{/keyboard}.
        // Description: Wraps the text inside a set of HTML <keyb> tags.
        // Parameters: Any text.
        if (stripos($text, '{/keyboard}') !== false) {
            $replace['/\{keyboard\}(.*)\{\/keyboard\}/isuU'] = '<kbd>$1</kbd>';
        }

        /* ---------------- Apply all of the filtercodes so far. ---------------*/

        if ($this->replacetags($text, $replace) == false) {
            // No more tags? Put back the escaped tags, if any, and return the string.
            $text = $this->escapedtags($text);
            self::$infiltercodes = false;
            return $text;
        }

        // ...===================================================================================================================.
        // The rest of the tags below. Put tags above if they generate more tags or will be used as parameters for other tags.
        // ...===================================================================================================================.

        // Simple tags that don't ever have parameters.

        // Substitutions.

        $u = clone $USER;
        if (!isloggedin() || isguestuser()) {
            $u->firstname = get_string('defaultfirstname', 'filter_filtercodes');
            $u->lastname = get_string('defaultsurname', 'filter_filtercodes');
        }
        $u->fullname = trim(get_string('fullnamedisplay', null, $u));

        // Tag: {firstname}.
        // Description: User's first name as set in their profile.
        // Parameters: None.
        if (stripos($text, '{firstname}') !== false) {
            $replace['/\{firstname\}/i'] = $u->firstname;
        }

        // Tag: {surname}.
        // Description: Alias for {lastname}.
        // Parameters: None.
        if (stripos($text, '{surname}') !== false) {
            $text = str_replace('{surname}', '{lastname}', $text);
        }

        // Tag: {lastname}.
        // Description: User's last name as set in their profile.
        // Parameters: None.
        if (stripos($text, '{lastname}') !== false) {
            $replace['/\{lastname\}/i'] = $u->lastname;
        }

        // Tag: {fullname}.
        // Description: User's full name as set in their profile.
        // Parameters: None.
        if (stripos($text, '{fullname}') !== false) {
            $replace['/\{fullname\}/i'] = $u->fullname;
        }

        // Tag: {alternatename}.
        // Description: User's alternate name as set in their profile.
        // Parameters: None.
        if (stripos($text, '{alternatename}') !== false) {
            // If alternate name is empty, use firstname instead.
            if ($this->isauthenticateduser() && (!is_null($USER->alternatename) && !empty(trim($USER->alternatename)))) {
                $replace['/\{alternatename\}/i'] = $USER->alternatename;
            } else {
                $replace['/\{alternatename\}/i'] = $u->firstname;
            }
        }

        // Tags: {firstnamephonetic}, {lastnamephonetic}, {middlename}.
        // Description: User's first name phonetic, last name phonetic and middle name as set in their profile.
        // Parameters: None.
        foreach (['firstnamephonetic', 'lastnamephonetic', 'middlename'] as $field) {
            if (stripos($text, '{' . $field . '}') !== false) {
                $replace['/\{' . $field . '\}/i'] = $this->isauthenticateduser() ? trim($USER->{$field}) : '';
            }
        }

        // Tag: {email}.
        // Description: User's email address as set in their profile.
        // Parameters: None.
        if (stripos($text, '{email}') !== false) {
            $replace['/\{email\}/i'] = $this->isauthenticateduser() ? $USER->email : '';
        }

        // Tag: {city}.
        // Description: User's city as set in their profile.
        // Parameters: None.
        if (stripos($text, '{city}') !== false) {
            $replace['/\{city\}/i'] = $this->isauthenticateduser() ? $USER->city : '';
        }

        // Tag: {country}.
        // Description: User's country as set in their profile.
        // Parameters: None.
        if (stripos($text, '{country}') !== false) {
            if ($this->isauthenticateduser() && !empty($USER->country)) {
                $replace['/\{country\}/i'] = get_string($USER->country, 'countries');
            } else {
                $replace['/\{country\}/i'] = '';
            }
        }
        // Tag: {timezone}.
        // Description: User's time zone as set in their profile.
        // Parameters: None.
        if (stripos($text, '{timezone}') !== false) {
            if ($this->isauthenticateduser() && !empty($USER->timezone)) {
                if ($USER->timezone == '99') { // Default is system timezone.
                    $replace['/\{timezone\}/i'] = \core_date::get_default_php_timezone();
                } else {
                    $replace['/\{timezone\}/i'] = \core_date::get_localised_timezone($USER->timezone);
                }
            }
        }

        // Tag: {institution}.
        // Description: User's institution as set in their profile.
        // Parameters: None.
        if (stripos($text, '{institution}') !== false) {
            $replace['/\{institution\}/i'] = $this->isauthenticateduser() ? $USER->institution : '';
        }

        // Tag: {department}.
        // Description: User's department as set in their profile.
        // Parameters: None.
        if (stripos($text, '{department}') !== false) {
            $replace['/\{department\}/i'] = $this->isauthenticateduser() ? $USER->department : '';
        }

        // Tag: {idnumber}.
        // Description: idnumber as specified in the user's profile.
        // Parameters: None.
        if (stripos($text, '{idnumber}') !== false) {
            $replace['/\{idnumber\}/i'] = $this->isauthenticateduser() ? $USER->idnumber : '';
        }

        // Tag: {webpage}
        // Description: Social field in user's profile. This migrates from pre-Moodle 3.11 - for backwards compatibility.
        // Parameters: None.
        if (stripos($text, '{webpage}') !== false) {
            if ($CFG->branch >= 311) {
                $text = str_replace('{webpage}', '{profile_field_webpage}', $text);
            } else {
                $replace['/\{webpage\}/i'] = $this->isauthenticateduser() ? $USER->url : '';
            }
        }

        // Tag: {diskfreespace}.
        // Description: Free space of Moodle application volume.
        // Parameters: None.
        if (stripos($text, '{diskfreespace}') !== false) {
            $bytes = @disk_free_space('.');
            $replace['/\{diskfreespace\}/i'] = $this->humanbytes($bytes);
        }

        // Tag: {diskfreespacedata}.
        // Description: Free space of Moodle data volume.
        // Parameters: None.
        if (stripos($text, '{diskfreespacedata}') !== false) {
            $bytes = @disk_free_space($CFG->dataroot);
            $replace['/\{diskfreespacedata\}/i'] = $this->humanbytes($bytes);
        }

        // Tags starting with: {support...}.
        if (stripos($text, '{support') !== false) {
            // Tag: {supportname}.
            // Description: Support name for the site from Moodle settings.
            // None.
            if (stripos($text, '{supportname}') !== false) {
                if (empty($CFG->supportname)) {
                    $replace['/\{supportname\}/i'] = get_string('notavailable', 'filter_filtercodes');
                } else {
                    $replace['/\{supportname\}/i'] = $CFG->supportname;
                }
            }

            // Tag: {supportemail}.
            // Description: Support email address for the site from Moodle settings.
            // None.
            if (stripos($text, '{supportemail}') !== false) {
                if (empty($CFG->supportname)) {
                    $replace['/\{supportemail\}/i'] = get_string('notavailable', 'filter_filtercodes');
                } else {
                    $replace['/\{supportemail\}/i'] = $CFG->supportemail;
                }
            }

            // Tag: {supportpage}.
            // Description: URL of Support for the site from Moodle settings.
            // None.
            if (stripos($text, '{supportpage}') !== false) {
                if (empty($CFG->supportname)) {
                    $replace['/\{supportpage\}/i'] = '';
                } else {
                    $replace['/\{supportpage\}/i'] = $CFG->supportpage;
                }
            }

            // Tag: {supportservicespage}.
            // Description: URL of support services page from Moodle settings.
            // None.
            if ($CFG->branch >= 402 && stripos($text, '{supportservicespage}') !== false) {
                $replace['/\{supportservicespage\}/i'] = $CFG->servicespage;
            }
        }

        if (stripos($text, '{site') !== false) {
            // Tag: {sitename}.
            // Description: The full name of the site name.
            // Parameters: None.
            if (stripos($text, '{sitename}') !== false) {
                $sitecontext = \context_system::instance();
                $replace['/\{sitename\}/i'] = format_string($SITE->fullname, true, ['context' => $sitecontext]);
            }

            // Tag: {sitesummary}.
            // Description: Site summary as defined in the Front Page/Site Home Settings.
            // Parameters: None.
            if (stripos($text, '{sitesummary}') !== false) {
                $sitecontext = \context_system::instance();
                $replace['/\{sitesummary\}/i'] = format_string($SITE->summary, true, ['context' => $sitecontext]);
            }

            // Tag: {siteyear}.
            // Description: Current year, 4 digits.
            // Parameters: None.
            if (stripos($text, '{siteyear}') !== false) {
                $replace['/\{siteyear\}/i'] = date('Y');
            }
            // Tag: {sitelogourl} or %7Bsitelogourl%7D.
            // Description: URL of site logo.
            // Parameters: None.
            if (stripos($text, '{sitelogourl}') !== false) {
                global $OUTPUT;
                $replace['/\{sitelogourl\}/i'] = '' . $OUTPUT->get_logo_url();
            }
            if (stripos($text, '%7Bsitelogourl%7D') !== false) {
                global $OUTPUT;
                $replace['/\%7Bsitelogourl\%7D/i'] = '' . $OUTPUT->get_logo_url();
            }
        }

        /* ---------------- Apply all of the filtercodes so far. ---------------*/

        if ($this->replacetags($text, $replace) == false) {
            // No more tags? Put back the escaped tags, if any, and return the string.
            $text = $this->escapedtags($text);
            self::$infiltercodes = false;
            return $text;
        }

        if (stripos($text, '{profile') !== false) {
            // Tag: {profile_field_shortname}.
            // Description: Contents of the custom user profile field. Will apply formating to datetime and checkbox type fields.
            // Required Parameters: shortname of a custom profile field.
            if (stripos($text, '{profile_field') !== false) {
                $isuser = ($this->isauthenticateduser());
                // Cached the defined custom profile fields and data.
                if (!isset($profilefields)) {
                    $profilefields = $DB->get_records('user_info_field', null, '', 'id, datatype, shortname, visible, param3');
                    if ($isuser && !empty($profilefields)) {
                        $profiledata = $DB->get_records_menu('user_info_data', ['userid' => $USER->id], '', 'fieldid, data');
                    }
                }
                $showhidden = get_config('filter_filtercodes', 'showhiddenprofilefields');
                foreach ($profilefields as $field) {
                    // If the tag exists and is not set to "Not visible" in the custom profile field's settings.
                    if (
                        $isuser
                        && stripos($text, '{profile_field_' . $field->shortname . '}') !== false
                        && ($field->visible != '0' || !empty($showhidden))
                    ) {
                        $data = isset($profiledata[$field->id]) ? trim($profiledata[$field->id]) : '' . PHP_EOL;
                        switch ($field->datatype) { // Format data for some field types.
                            case 'datetime':
                                // Include date and time or just date?
                                $datetimeformat = !empty($field->param3) ? 'strftimedaydatetime' : 'strftimedate';
                                $data = empty($data) ? '' : userdate($data, get_string($datetimeformat, 'langconfig'));
                                break;
                            case 'checkbox':
                                // 1 = Yes, 0 = No
                                $data = empty($data) ? get_string('no') : get_string('yes');
                                break;
                        }
                        $replace['/\{profile_field_' . $field->shortname . '\}/i'] = $data;
                    } else {
                        $replace['/\{profile_field_' . $field->shortname . '\}/i'] = '';
                    }
                }
            }

            // Tag: {profilefullname}.
            // Description: Full name of current user.
            // Parameters: None.
            if (stripos($text, '{profilefullname}') !== false) {
                $fullname = '';
                if ($this->isauthenticateduser()) {
                    $fullname = get_string('fullnamedisplay', null, $USER);
                    if ($PAGE->pagelayout == 'mypublic' && $PAGE->pagetype == 'user-profile') {
                        $userid = optional_param('userid', optional_param(
                            'user',
                            optional_param('id', $USER->id, PARAM_INT),
                            PARAM_INT
                        ), PARAM_INT);
                        if ($user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0])) {
                            $fullname = get_string('fullnamedisplay', null, $user);
                        }
                    }
                }
                $replace['/\{profilefullname\}/i'] = $fullname;
                unset($fullname);
            }
        }

        // Tag: {scrape url="" <optional parameters>}.
        // Description: Scrapes content from an external HTML page. Cannot scrape secure pages from sites that requires login.
        // Optional parameters: You may use any combination of the following: tag="..." class="..." id="..." code="...".
        if (get_config('filter_filtercodes', 'enable_scrape') && stripos($text, '{scrape ') !== false) {
            // Replace {scrape} tag and its attributes with retrieved content.
            $newtext = preg_replace_callback(
                '/\{scrape\s+(.*)\}/isuU',
                function ($matches) {
                    // Parse the scrape tag's atributes.
                    $matches[0] = $matches[0] == null ? '' : strip_tags($matches[0]);
                    $attribs = substr($matches[0], 1, -1);
                    $scrape = $this->attribstoarray($attribs);
                    $url = isset($scrape['url']) ? $scrape['url'] : '';
                    $tag = isset($scrape['tag']) ? $scrape['tag'] : '';
                    $class = isset($scrape['class']) ? $scrape['class'] : '';
                    $id = isset($scrape['id']) ? $scrape['id'] : '';
                    $code = isset($scrape['code']) ? $scrape['code'] : '';
                    // If nothing else, we must have a URL parameter.
                    if (empty($url)) {
                        return "SCRAPE error: Missing or invalid required URL parameter.";
                    }
                    // Replace {scrape} tag and its attributes with retrieved content.
                    return $this->scrapehtml($url, $tag, $class, $id, $code);
                },
                $text
            );
            if ($newtext !== false) {
                $text = $newtext;
            }
        }

        // Any {user*} tags.
        if (stripos($text, '{user') !== false || stripos($text, '%7Buser') !== false) {
            // Tag: {username}.
            // Description: User's username as defined in their profile. When not logged in, uses predefined name in language file.
            // Parameters: None.
            if (stripos($text, '{username}') !== false) {
                $replace['/\{username\}/i'] = isloggedin()
                        && !isguestuser() ? $USER->username : get_string('defaultusername', 'filter_filtercodes');
            }

            // These tags: {userpictureurl} and {userpictureimg}.
            if (stripos($text, '{userpicture') !== false) {
                // Tag: {userpictureurl size}.
                // Description: URL of user's picture as set in their profile.
                // Parameters: Sizes: sm|md|lg or an integer 2|1|3 or an integer size in pixels > 3.
                if (stripos($text, '{userpictureurl ') !== false) {
                    $newtext = preg_replace_callback(
                        '/\{userpictureurl\s+(\w+)\}/isuU',
                        function ($matches) use ($USER) {
                            return $this->getprofilepictureurl($USER, $matches[1]);
                        },
                        $text
                    );
                    if ($newtext !== false) {
                        $text = $newtext;
                    }
                }

                // Tag: {userpictureimg size}.
                // Description: URL of user's picture as set in their profile, wrapped in an HTML img tag.
                // Parameters: Sizes: sm|md|lg or an integer 2|1|3 or an integer size in pixels > 3.
                if (stripos($text, '{userpictureimg ') !== false) {
                    $newtext = preg_replace_callback(
                        '/\{userpictureimg\s+(\w+)\}/isuU',
                        function ($matches) use ($USER) {
                            $url = $this->getprofilepictureurl($USER, $matches[1]);
                            $fullname = get_string('fullnamedisplay', null, $USER);
                            $tag = '<img src="' . $url . '" alt="' . $fullname . '" class="userpicture">';
                            return $tag;
                        },
                        $text
                    );
                    if ($newtext !== false) {
                        $text = $newtext;
                    }
                }
            }

            // Tag: {userdescription}.
            // Description: Description as set in user's profile.
            // Parameters: None.
            if (stripos($text, '{userdescription}') !== false) {
                if ($this->isauthenticateduser()) {
                    $user = $DB->get_record('user', ['id' => $USER->id], 'description', MUST_EXIST);
                    $replace['/\{userdescription\}/i'] = format_text($user->description, $USER->descriptionformat);
                    unset($user);
                } else {
                    $replace['/\{userdescription\}/i'] = '';
                }
            }

            // Tag: {usercount}.
            // Description: A count of the total number of users on the site. Includes suspended and unconfirmed users.
            // Parameters: None.
            if (stripos($text, '{usercount}') !== false) {
                // Count total number of current users on the site.
                // Exclude deleted users, admin and guest.
                $cnt = $DB->count_records('user', ['deleted' => 0]) - 2;
                $replace['/\{usercount\}/i'] = $cnt;
            }

            // Tag: {usersactive}.
            // Description: A count of the total number of active users on the site.
            // Parameters: None.
            if (stripos($text, '{usersactive}') !== false) {
                // Count total number of current users on the site.
                // Exclude deleted, suspended and unconfirmed users, admin and guest.
                $cnt = $DB->count_records('user', ['deleted' => 0, 'suspended' => 0, 'confirmed' => 1]) - 2;
                $replace['/\{usersactive\}/i'] = $cnt;
            }

            // Tag: {usersonline}.
            // Description: A count of the total number of users currently online on the site within the last 5 minutes.
            // Parameters: None.
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

                $onlineusers = new fetcher(
                    $thisgroup,
                    $now,
                    $timetosee,
                    $PAGE->context,
                    $PAGE->context->contextlevel,
                    $PAGE->course->id
                );

                // Count online users.
                $usersonline = $onlineusers->count_users();
                $replace['/\{usersonline\}/i'] = $usersonline;
            }

            // Tag: {userscountrycount}.
            // Description: A count of the total number countries that users are in as set in their user profile.
            // Parameters: None.
            if (stripos($text, '{userscountrycount}') !== false) {
                $count = $DB->count_records_sql('SELECT COUNT(DISTINCT country) FROM {user} WHERE id > 2');
                $replace['/\{userscountrycount\}/i'] = $count;
            }
        }

        // Check if any {course*} or %7Bcourse*%7D tags. Note: There is another course tags section further up.
        $coursetagsexist = (stripos($text, '{course') !== false || stripos($text, '%7Bcourse') !== false);
        if ($coursetagsexist) {
            // Tag: {coursecontacts}.
            // Description: Get list of course contacts based on settings in Site Administration > Appearances > Courses.
            // Parameters: None.
            if (stripos($text, '{coursecontacts}') !== false) {
                $contacts = '';
                // If course (not site pages) with contacts.
                if ($PAGE->course->id) {
                    $course = new \core_course_list_element($PAGE->course);
                    if ($course->has_course_contacts()) {
                        // Get tag settings.
                        $cshowpic = get_config('filter_filtercodes', 'coursecontactshowpic');
                        $cshowdesc = get_config('filter_filtercodes', 'coursecontactshowdesc');
                        $clinktype = get_config('filter_filtercodes', 'coursecontactlinktype');

                        // Prepare some strings.
                        $linksr = ['' => '',
                            'email' => get_string('issueremail', 'badges'),
                            'message' => get_string('message', 'message'),
                            'profile' => get_string('profile'),
                            'phone' => get_string('phone'),
                        ];
                        $iconclass = ['' => '',
                            'email' => 'fa fa-envelope-o',
                            'message' => 'fa fa-comment-o',
                            'profile' => 'fa fa-user-o',
                            'phone' => 'fa fa-mobile',
                        ];

                        $cnt = 0;
                        foreach ($course->get_course_contacts() as $coursecontact) {
                            $icon = '<i class="' . $iconclass[$clinktype] . '" aria-hidden="true"></i> ';

                            $contacts .= '<li>';

                            // Get list of course contacts based on settings in Site Administration > Appearances > Courses.
                            // Get list of user's roles in the course.
                            $rolenames = array_map(function ($role) {
                                return $role->displayname;
                            }, $coursecontact['roles']);

                            // Retrieve contact's profile information.
                            $user = $DB->get_record(
                                'user',
                                ['id' => $coursecontact['user']->id],
                                $fields = '*',
                                $strictness = IGNORE_MULTIPLE
                            );
                            $fullname = get_string('fullnamedisplay', null, $user);
                            if ($cshowpic) {
                                $imgurl = $this->getprofilepictureurl($user, 3);
                                $contacts .= '<img src="' . $imgurl . '" alt="' . $fullname
                                    . '" class="img-fluid img-thumbnail' . (!empty($cnt) ? ' mt-4' : '') . '">';
                                $cnt++;
                            }

                            $contactsclose = '<span class="sr-only">' . $linksr[$clinktype] . ': </span>';
                            $contactsclose .= $fullname . '</a>';

                            $contacts .= '<span class="fc-coursecontactroles">' . implode(", ", $rolenames) . ': </span>';

                            switch ($clinktype) {
                                case 'email':
                                    $contacts .= $icon . '<a href="mailto:' . $user->email . '">';
                                    $contacts .= $contactsclose;
                                    break;
                                case 'message':
                                    $contacts .= $icon . '<a href="' . (new \moodle_url(
                                        '/message/index.php',
                                        ['id' => $coursecontact['user']->id]
                                    ))->out() . '">';
                                    $contacts .= $contactsclose;
                                    break;
                                case 'profile':
                                    $contacts .= $icon . '<a href="' . (new \moodle_url(
                                        '/user/profile.php',
                                        ['id' => $coursecontact['user']->id, 'course' => $PAGE->course->id]
                                    ))->out() . '">';
                                    $contacts .= $contactsclose;
                                    break;
                                case 'phone1' && !empty($user->phone1):
                                    $contacts .= $icon . '<a href="tel:' . $user->phone1 . '">';
                                    $contacts .= $contactsclose;
                                    break;
                                default: // Default is no-link.
                                    $contacts .= $fullname;
                                    break;
                            }
                            if ($cshowdesc && !empty($user->description)) {
                                $contacts .= '<div' . (empty($cshowpic) ? ' class="mb-4"' : '') . '>' .
                                        $user->description . '</div>';
                            }
                            $contacts .= '</li>';
                        }
                    }
                }

                if (empty($contacts)) {
                    $replace['/\{coursecontacts\}/i'] = get_string('nocontacts', 'message');
                } else {
                    $replace['/\{coursecontacts\}/i'] = '<ul class="fc-coursecontacts list-unstyled ml-0 pl-0">' .
                            $contacts . '</ul>';
                }
                unset($contacts, $contactsclose, $fullname, $url, $user, $rolenames, $icon, $iconclass);
                unset($linksr, $clinktype, $cshowpic);
            }

            // Tag: {courseparticipantcount}.
            // Description: Get a the number of participants in the course. This includes anyone registered in the course.
            // Parameters: None.
            if (stripos($text, '{courseparticipantcount}') !== false) {
                static $courseparticipantcount;
                require_once($CFG->dirroot . '/user/lib.php');
                if (!isset($courseparticipants)) {
                    $sql = "SELECT COUNT(1)
                        FROM {user_enrolments} ue
                        JOIN {enrol} e ON e.id = ue.enrolid
                        WHERE e.courseid = :courseid";
                    $params = ['courseid' => $PAGE->course->id];
                    $courseparticipantcount = $DB->count_records_sql($sql, $params);
                }
                $replace['/\{courseparticipantcount\}/i'] = $courseparticipantcount;
            }

            // Tag: {coursecount students|students:active}.
            // Requires one of two parameters:
            // Optional Parameters: "students" - Filter limiting to just users with the role of student; or
            // Optional Parameters: "students:active" - Filter limiting to student who have not been suspended.
            // Description: Get just the number of "students" in the course.
            if (stripos($text, '{coursecount students}') !== false) {
                if ($CFG->branch >= 32) {
                    $coursecontext = \context_course::instance($PAGE->course->id);
                    $role = $DB->get_record('role', ['shortname' => 'student']);
                    $students = get_role_users($role->id, $coursecontext);
                    $cnt = count($students);
                    unset($students);
                } else {
                    $cnt = '';
                }
                $replace['/\{coursecount students\}/i'] = $cnt;
            }
            if (stripos($text, '{coursecount students:active}') !== false) {
                $sql = "SELECT COUNT(DISTINCT ue.userid)
                        FROM {user_enrolments} ue
                        JOIN {enrol} e ON e.id = ue.enrolid
                        JOIN {course} c ON c.id = e.courseid
                        JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
                        JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = ue.userid
                        JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
                        WHERE ue.status = 0 AND e.courseid = :courseid";
                $cnt = $DB->count_records_sql($sql, ['courseid' => $PAGE->course->id]);
                $replace['/\{coursecount students:active\}/i'] = $cnt;
            }

            // Tag: {coursecount}.
            // Description: The total number of courses.
            // Parameters: None.
            // Note that there are parametered vesions above giving it a completely different purpose.
            if (stripos($text, '{coursecount}') !== false) {
                // Count courses excluding front page.
                $cnt = $DB->count_records('course', []) - 1;
                $replace['/\{coursecount\}/i'] = $cnt;
            }

            // Tag: {courseidnumber}.
            // Description: The course idnumber as set in the course settings.
            // Parameters: None.
            if (stripos($text, '{courseidnumber}') !== false) {
                $replace['/\{courseidnumber\}/i'] = $PAGE->course->idnumber;
            }

            // Tag: {coursename}.
            // Description: The full name of a course, or the site name if not in a course.
            // Parameters: None.
            if (stripos($text, '{coursename') !== false) {
                if (stripos($text, '{coursename}') !== false) {
                    // No course ID was specified.
                    $course = $PAGE->course;
                    if ($course->id == $SITE->id) { // If not in a course, use the site name.
                        $coursecontext = \context_system::instance();
                        $replace['/\{coursename\}/i'] = format_string(
                            $SITE->fullname,
                            true,
                            ['context' => $coursecontext]
                        );
                    } else { // If in a course - use course full name.
                        $coursecontext = \context_course::instance($course->id);
                        $replace['/\{coursename\}/i'] = format_string(
                            $course->fullname,
                            true,
                            ['context' => $coursecontext]
                        );
                    }
                }
                if (stripos($text, '{coursename ') !== false) {
                    // Course ID was specified.
                    preg_match_all('/\{coursename ([0-9]+)\}/', $text, $matches);
                    // Eliminate course IDs.
                    $courseids = array_unique($matches[1]);
                    $coursecontext = \context_system::instance();
                    foreach ($courseids as $id) {
                        $course = $DB->get_record('course', ['id' => $id]);
                        if (!empty($course)) {
                            $replace['/\{coursename ' . $course->id . '\}/isuU'] = format_string(
                                $course->fullname,
                                true,
                                ['context' => $coursecontext]
                            );
                        }
                    }
                    unset($matches, $course, $courseids, $id);
                }
            }

            if (stripos($text, '{courseimage') !== false) {
                $course = $PAGE->course;
                if ($CFG->branch >= 33) {
                    $imgurl = \core_course\external\course_summary_exporter::get_course_image($course);
                } else { // Previous to Moodle 3.3.
                    $imgurl = '';
                    $context = \context_course::instance($course->id);
                    if ($course instanceof stdClass) {
                        $course = new \core_course_list_element($course);
                    }
                    $coursefiles = $course->get_course_overviewfiles();
                    foreach ($coursefiles as $file) {
                        if ($isimage = $file->is_valid_image()) {
                            $filename = '/' . $file->get_contextid() . '/' . $file->get_component()
                                . '/' . $file->get_filearea() . $file->get_filepath() . $file->get_filename();
                            $imgurl = file_encode_url("/pluginfile.php", $filename, !$isimage);
                            break;
                        }
                    }
                }
                if (empty($imgurl)) {
                    global $OUTPUT;
                    $imgurl = $OUTPUT->get_generated_image_for_id($course->id);
                }

                // Tag: {courseimage}.
                // Description: Course image as rendeable HTML img tag.
                // Parameters: None.
                if (stripos($text, '{courseimage}') !== false) {
                    $replace['/\{courseimage\}/i'] = '<img src="' . $imgurl . '" class="img-responsive">';
                }

                // Tag: {courseimage-url}.
                // Description: Course image URL.
                // Parameters: none.
                if (stripos($text, '{courseimage-url}') !== false) {
                    $replace['/\{courseimage-url\}/i'] = $imgurl;
                }
            }

            // Tag: {coursecount}.
            // Description: The total number of courses.
            // Parameters:  None.
            if (stripos($text, '{coursecount}') !== false) {
                // Count courses excluding front page.
                $cnt = $DB->count_records('course', []) - 1;
                $replace['/\{coursecount\}/i'] = $cnt;
            }

            // Tag: {coursesactive}.
            // Description: The total number of active visible courses: visibility set to Show, started, not ended.
            // Parameters:  None.
            if (stripos($text, '{coursesactive}') !== false) {
                // Count current courses (between start and end date, if any) set to Show - excluding front page.
                $today = time();
                $sql = "SELECT COUNT(id)
                        FROM {course}
                        WHERE visible = 1
                            AND startdate <= :today
                            AND (enddate > :today2 OR enddate = 0);";
                // Subtract one for site course, where id = 1.
                $cnt = $DB->count_records_sql($sql, ['today' => $today, 'today2' => $today]) - 1;
                $replace['/\{coursesactive\}/i'] = $cnt;
            }

            // Tag: {coursegrade}.
            // Description: Overall grade in a courses, with percentage symbol.
            // Parameters:  None.
            if (version_compare(PHP_VERSION, '7.0.0') >= 0 && stripos($text, '{coursegrade}') !== false) {
                require_once($CFG->libdir . '/gradelib.php');
                require_once($CFG->dirroot . '/grade/querylib.php');
                $gradeobj = grade_get_course_grade($USER->id, $PAGE->course->id);
                $grade = 0;
                if (!empty($grademax = floatval($gradeobj->item->grademax))) {
                    // Avoid divide by 0 error if no grades have been defined.
                    $grade = floatval($grademax) > 0 ? (int) ($gradeobj->grade / floatval($grademax) * 100) : 0;
                }
                $replace['/\{coursegrade\}/i'] = get_string('percents', '', $grade);
            }

            if (stripos($text, '{courseprogress') !== false) {
                $progress = $this->completionprogress();

                // Tag: {courseprogress}.
                // Description: Course completion progress percentage as formatted text.
                // Parameters:  None.
                if (stripos($text, '{courseprogress}') !== false) {
                    if ($progress != -1) { // Is enabled.
                        $replace['/\{courseprogress\}/i'] = '<span class="sr-only">'
                                . get_string('aria:courseprogress', 'block_myoverview') . '</span> '
                                . get_string('completepercent', 'block_myoverview', $progress);
                    } else {
                        $replace['/\{courseprogress\}/i'] = '';
                    }
                }

                // Tag: {courseprogressbar}.
                // Description: Course completion progress bar.
                // Parameters:  None.
                if (stripos($text, '{courseprogressbar}') !== false) {
                    if ($progress != -1) { // Is enabled.
                        $replace['/\{courseprogressbar\}/i'] = '
                            <div class="progress">
                                <div class="progress-bar bar" role="progressbar" aria-valuenow="' . $progress
                                    . '" style="width: ' . $progress . '%" aria-valuemin="0" aria-valuemax="100">
                                </div>
                            </div>';
                    } else {
                        $replace['/\{courseprogressbar\}/i'] = '';
                    }
                }
                unset($progress);
            }

            // Tag: {coursecards} and {coursecards <categoryid>}.
            // Description: Courses in a category branch as cards
            // Optional Parameters: The category ID number.
            if (stripos($text, '{coursecards') !== false) {
                global $OUTPUT;

                $chelper = new \coursecat_helper();
                $chelper->set_show_courses(20)->set_courses_display_options([
                    'recursive' => true,
                    'limit' => $CFG->frontpagecourselimit,
                    'viewmoreurl' => (new \moodle_url('/course/index.php'))->out(),
                    'viewmoretext' => new \lang_string('fulllistofcourses'),
                ]);

                $chelper->set_attributes(['class' => 'frontpage-course-list-all']);
                // Find all coursecards tags where category ID was specified.
                preg_match_all('/\{coursecards ([0-9]+)\}/', $text, $matches);
                // Check if tag with no category.
                $nocat = (stripos($text, '{coursecards}') !== false);
                if ($nocat) {
                    $matches[1][] = 0;
                }
                // Eliminate duplicate categories.
                $categories = array_unique($matches[1]);

                $card = $this->getcoursecardinfo();

                foreach ($categories as $catid) {
                    try {
                        $coursecat = \core_course_category::get($catid);
                        // Get list of courses in this category.
                        $courses = $coursecat->get_courses($chelper->get_courses_display_options());
                    } catch (Exception $e) {
                        // Course category not found or not accessible.
                        // No courses available.
                        $courses = [];
                    }

                    $rcourseids = array_keys($courses);
                    if (count($rcourseids) > 0) {
                        $content = $this->rendercoursecards($rcourseids, $card->format);
                    } else {
                        $content = '';
                    }
                    if ($catid == 0 && $nocat) {
                        $replace['/\{coursecards\}/i'] = !empty($content) ? $card->header . $content . $card->footer : '';
                    }
                    $replace['/\{coursecards ' . $catid . '\}/isuU'] =
                            !empty($content) ? $card->header . $content . $card->footer : '';
                }
            }

            // Tag: {coursecard courseid}.
            // Description: Display a course card for the specified course id.
            // Optional Parameters: a courseid number. If not specified, will use the current course's id or the site id (1).
            if (stripos($text, '{coursecard ') !== false) {
                $re = '/\{coursecard\s([\s\d]+)\}/isuU';
                $found = preg_match_all($re, $text, $matches);
                $matches = array_combine(array_values($matches[0]), array_values($matches[1]));
                $card = $this->getcoursecardinfo();
                foreach ($matches as $key => $match) {
                    $courseids = explode(' ', $match);

                    // Only keep valid course ids.
                    $courseids = array_map('trim', $courseids); // Remove extra spaces.
                    $courseids = array_filter($courseids); // Remove empty elements.
                    $courseids = array_unique($courseids); // Remove duplicates.
                    foreach ($courseids as $key => $courseid) {
                        $course = $DB->get_record('course', ['id' => $courseid]);
                        if ($course === false) {
                            // Course not found. Remove it from the list.
                            unset($courseids[$key]);
                        }
                    }
                    // Create cards for existing courses that are visible to user.
                    $content = $this->rendercoursecards($courseids, $card->format);
                    $replace['/\{coursecard ' . $match . '\}/isuU'] =
                            !empty($content) ? $card->header . $content . $card->footer : '';
                }
            }

            // Tag: {coursecardsbyenrol}.
            // Description: Display list of 10 most popular courses by enrolment count (tested with MySQL and PostgreSQL).
            // Parameters:  None.
            if (stripos($text, '{coursecardsbyenrol}') !== false) {
                $sql = "SELECT c.id, c.fullname, COUNT(*) AS enrolments
                        FROM {course} c
                        JOIN (SELECT DISTINCT e.courseid, ue.id AS userid
                                FROM {user_enrolments} ue
                                JOIN {enrol} e ON e.id = ue.enrolid) ue ON ue.courseid = c.id
                        GROUP BY c.id, c.fullname
                        ORDER BY 3 DESC, c.fullname";
                $courses = $DB->get_records_sql($sql, [], 0, get_config('filter_filtercodes', 'coursecardsbyenrol'));
                $rcourseids = array_keys($courses);
                if (count($rcourseids) > 0) {
                    $card = $this->getcoursecardinfo();
                    $content = $this->rendercoursecards($rcourseids, $card->format);
                } else {
                    $card = new \stdClass();
                    $card->header = '';
                    $card->footer = '';
                    $content = '';
                }
                $replace['/\{coursecardsbyenrol\}/i'] = !empty($content) ? $card->header . $content . $card->footer : '';
            }

            // Tag: {courserequest}.
            // Description: Link to Request a Course form.
            // Parameters:  None.
            if (stripos($text, '{courserequest}') !== false) {
                // Add request a course link.
                $context = \context_system::instance();
                if (!empty($CFG->enablecourserequests) && has_capability('moodle/course:request', $context)) {
                    $link = '<a href="' . (new \moodle_url('/course/request.php'))->out() . '">'
                        . get_string('requestcourse') . '</a>';
                } else {
                    $link = '';
                }
                $replace['/\{courserequest\}/i'] = $link;
            }

            if (stripos($text, '{courserequestmenu') !== false) {
                // Add request a course link.
                $context = \context_system::instance();
                if (!empty($CFG->enablecourserequests) && has_capability('moodle/course:request', $context)) {
                    // Tag: {courserequestmenu0}.
                    // Description: Link to Request a Course form formatted for use as a top level custom menu item.
                    // Parameters:  None.
                    if (stripos($text, '{courserequestmenu0}') !== false) {
                        // Top level menu.
                        $link = get_string('requestcourse') . '|' . (new \moodle_url('/course/request.php'))->out();
                        $replace['/\{courserequestmenu0\}/i'] = $link;
                    }

                    // Tag: {courserequestmenu}.
                    // Description: Link to Request a Course form formatted for use as a second level custom menu item.
                    // Parameters:  None.
                    if (stripos($text, '{courserequestmenu}') !== false) {
                        // Not top level menu.
                        $link = '-###' . PHP_EOL;
                        $link .= '-' . get_string('requestcourse') . '|' . (new \moodle_url('/course/request.php'))->out();
                        $replace['/\{courserequestmenu\}/i'] = $link;
                    }
                } else {
                    $replace['/\{courserequestmenu\}/i'] = '';
                }
            }
        }

        // These tags: {mycourses} and {mycoursesmenu} and {mycoursescards}.
        if (stripos($text, '{mycourse') !== false || stripos($text, '{myccourse') !== false) {
            if ($this->isauthenticateduser()) {
                // Retrieve list of user's enrolled courses.
                $sortorder = 'visible DESC';
                // Prevent undefined $CFG->navsortmycoursessort errors.
                if (empty($CFG->navsortmycoursessort)) {
                    $CFG->navsortmycoursessort = 'sortorder';
                }
                // Append the chosen sortorder.
                $sortorder = $sortorder . ',' . $CFG->navsortmycoursessort . ' ASC';
                $mycourses = enrol_get_my_courses('fullname,id', $sortorder);
                $myccourses = [];

                // Save and remove completed courses from the list.
                if (
                    isset($CFG->enablecompletion) && $CFG->enablecompletion == 1 // COMPLETION_ENABLED.
                    && get_config('filter_filtercodes', 'hidecompletedcourses')
                ) {
                    foreach ($mycourses as $key => $mycourse) {
                        $ccompletion = new \completion_completion(['userid' => $USER->id, 'course' => $mycourse->id]);
                        if (!empty($ccompletion->timecompleted)) {
                            // Save course to list of completed courses.
                            $myccourses[] = $mycourses[$key];
                            // Remove completed course from the list.
                            unset($mycourses[$key]);
                        }
                    }
                }

                // Messages to display if not enrolled in any courses or have not yet completed some courses.
                // Start by assuming that we are not enrolled in any courses.
                $emptylist = get_string(($CFG->branch >= 29 ? 'notenrolled' : 'nocourses'), 'grades');
                $emptycclist = $emptylist;
                if (!empty($mycourses)) { // Enrolled in some courses.
                    $emptylist = '';
                }
                if (empty($myccourses)) { // Not completed any courses.
                    $emptycclist = get_string('nocompletedcourses', 'filter_filtercodes');
                }

                // Tag: {mycourses}.
                // Description: An unordered list of links to enrolled courses.
                // Parameters: None.
                if (stripos($text, '{mycourses}') !== false) {
                    $list = '';
                    foreach ($mycourses as $mycourse) {
                        $list .= '<li><a href="' . (new \moodle_url('/course/view.php', ['id' => $mycourse->id]))->out() . '">' .
                            format_string($mycourse->fullname) . '</a></li>';
                    }
                    $replace['/\{mycourses\}/i'] = '<ul>' . (empty($list) ? "<li>$emptylist</li>" : $list) . '</ul>';
                    unset($list);
                }

                // Tag: {myccourses}.
                // Description: An unordered list of links to completed courses.
                // Parameters: None.
                if (stripos($text, '{myccourses}') !== false) {
                    $list = '';
                    foreach ($myccourses as $mycourse) {
                        $list .= '<li><a href="' . (new \moodle_url('/course/view.php', ['id' => $mycourse->id]))->out() . '">' .
                            format_string($mycourse->fullname) . '</a></li>';
                    }
                    $replace['/\{myccourses\}/i'] = '<ul>' . (empty($list) ? "<li>$emptycclist</li>" : $list) . '</ul>';
                    unset($list);
                }

                // Tag: {mycoursesmenu}.
                // Description: A custom menu list of enrolled course names with links.
                // Parameters: None.
                if (stripos($text, '{mycoursesmenu}') !== false) {
                    $list = '';
                    foreach ($mycourses as $mycourse) {
                        $list .= '-' . $this->format_custommenuitem($mycourse->fullname) . '|' .
                            (new \moodle_url('/course/view.php', ['id' => $mycourse->id]))->out() . PHP_EOL;
                    }
                    $replace['/\{mycoursesmenu\}/i'] = '-' . (empty($list) ? $emptylist : $list);
                    unset($list);
                }

                // Tag: {mycoursescards}.
                // Description: Generates a course card for each enrolled course.
                // Parameters: None.
                if (stripos($text, '{mycoursescards}') !== false) {
                    $list = '';
                    $courseids = [];
                    foreach ($mycourses as $mycourse) {
                        $courseids[] = $mycourse->id;
                    }
                    // If enrolled in at least one course, generate cards.
                    if (!empty($courseids)) {
                        $card = $this->getcoursecardinfo();
                        $list = $card->header . $this->rendercoursecards($courseids, $card->format) . $card->footer;
                    }
                    $replace['/\{mycoursescards\}/i'] = (empty($list) ? $emptylist : $list);
                    unset($list);
                }

                // Tag: {mycoursescards <categoryid(s)>}.
                // Description: Generates a course card for each enrolled course in the specified category.
                // Optional Parameters: One or more category ids separated by a space.
                if (stripos($text, '{mycoursescards ') !== false) {
                    // Get the card format.
                    $card = $this->getcoursecardinfo();
                    // Find all of the mycoursescards tags where category ID was specified.
                    preg_match_all('/{mycoursescards ([^}]*)}/', $text, $matches);
                    // For each tag.
                    foreach ($matches[0] as $key => $tag) {
                        $catids = array_map('intval', array_filter(explode(' ', $matches[1][$key]), 'is_numeric'));
                        // For each category in each tag.
                        $content = '';
                        foreach ($catids as $catid) {
                            // Get all the enrolled courses in the specified category for the user.
                            $courses = $DB->get_records_sql(
                                "SELECT c.*
                                    FROM {course} c
                                    JOIN {enrol} e ON e.courseid = c.id
                                    JOIN {user_enrolments} ue ON ue.enrolid = e.id
                                    WHERE ue.userid = ? AND c.category = ?
                                    ORDER BY c.shortname",
                                [$USER->id, $catid]
                            );
                            // Make an array of the course ids and render the course cards.
                            $courseids = array_column($courses, 'id');
                            $content .= $this->rendercoursecards($courseids, $card->format);
                        }
                        if (!empty($content)) {
                            $replace['/' . $tag . '/isuU'] = $card->header . $content . $card->footer;
                        }
                    }
                    unset($card);
                    unset($matches);
                    unset($catids);
                    unset($catid);
                    unset($content);
                    unset($courses);
                    unset($courseids);
                }
            } else { // Not logged in.
                // Replace tags with message indicating that you need to be logged in.
                $replace['/\{mycourses\}/i'] = '<ul class="mycourseslist"><li>' . get_string('loggedinnot') . '</li></ul>';
                $replace['/\{myccourses\}/i'] = '<ul class="mycourseslist"><li>' . get_string('loggedinnot') . '</li></ul>';
                $replace['/\{mycoursesmenu\}/i'] = '-' . get_string('loggedinnot') . PHP_EOL;
                $replace['/\{mycoursescards[^}]*\}/i'] = '<p>' . get_string('loggedinnot') . '</p>';
            }
        }

        // Tag: {editingtoggle}.
        // Description: Is "off" if in edit page mode. Otherwise "on". Useful for creating Turn Editing On/Off links.
        // Parameters: None.
        if (stripos($text, '{editingtoggle}') !== false) {
            $replace['/\{editingtoggle\}/i'] = ($PAGE->user_is_editing() ? 'off' : 'on');
        }

        // Tag: {toggleeditingmenu}.
        // Description: Creates menu link to toggle editing on and off.
        // Parameters: None.
        if (stripos($text, '{toggleeditingmenu}') !== false) {
            $editmode = ($PAGE->user_is_editing() ? 'off' : 'on');
            $edittext = get_string('turnediting' . $editmode);
            if ($PAGE->bodyid == 'page-site-index' && $PAGE->pagetype == 'site-index') { // Front page.
                $replace['/\{toggleeditingmenu\}/i'] = $edittext . '|' . (new \moodle_url(
                    '/course/view.php',
                    ['id' => $PAGE->course->id, 'sesskey' => sesskey(), 'edit' => $editmode]
                ))->out();
            } else { // All other pages.
                $replace['/\{toggleeditingmenu\}/i'] = $edittext . '|' . (new \moodle_url(
                    $PAGE->url,
                    ['edit' => $editmode, 'adminedit' => $editmode, 'sesskey' => sesskey()]
                ))->out() . PHP_EOL;
            }
        }

        // Tags starting with: {categor...}.
        if (stripos($text, '{categor') !== false) {
            if (empty($PAGE->course->category)) {
                // If we are not in a course, check if categoryid is part of URL (ex: course lists).
                $catid = optional_param('categoryid', 0, PARAM_INT);
            } else {
                // Retrieve the category id of the course we are in.
                $catid = $PAGE->course->category;
            }

            if (!empty($catid)) {
                $category = $DB->get_record('course_categories', ['id' => $catid]);
            }

            // Tag: {categoryname}.
            // Description: Name of category in which the current course is located.
            // Parameters: None.
            if (stripos($text, '{categoryname}') !== false) {
                if (!empty($catid)) {
                    // If category is not 0, get category name.
                    $replace['/\{categoryname\}/i'] = format_string($category->name);
                } else {
                    // Otherwise, category has no name.
                    $replace['/\{categoryname\}/i'] = '';
                }
            }

            // Tag: {categorynumber}.
            // Description: categorynumber of the category in which the current course is located, as set in the category settings.
            // Parameters: None.
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
            // Description: Description of the category in which the current course is located, as set in the category settings.
            // Parameters: None.
            if (stripos($text, '{categorydescription}') !== false) {
                if (!empty($catid)) {
                    // If category is not 0, get category description.
                    $catcontext = \context_coursecat::instance($category->id);
                    // Resolve embedded URLs that might be in the description.
                    $description = file_rewrite_pluginfile_urls(
                        $category->description,
                        'pluginfile.php',
                        $catcontext->id,
                        'coursecat',
                        'description',
                        0
                    );
                    $replace['/\{categorydescription\}/i'] = $description;
                } else {
                    // Otherwise, category has no description.
                    $replace['/\{categorydescription\}/i'] = '';
                }
            }

            // Tag: {categories}.
            // Description: An unordered list of links to categories.
            // Parameters: None.
            if (stripos($text, '{categories}') !== false) {
                // Retrieve list of all categories.
                if ($CFG->branch >= 36) { // Moodle 3.6+.
                    $categories = \core_course_category::make_categories_list();
                } else {
                    require_once($CFG->libdir . '/coursecatlib.php');
                    $categories = coursecat::make_categories_list();
                }
                $list = '';
                foreach ($categories as $id => $name) {
                    $list .= '<li><a href="' .
                            (new \moodle_url('/course/index.php', ['categoryid' => $id]))->out() . '">' . $name . '</a></li>';
                }
                $list = !empty($list) ? '<ul class="categorylist">' . $list . '</ul>' : '';
                $replace['/\{categories\}/i'] = $list;
                unset($tag);
                unset($list);
            }

            // Tag: {categoriesmenu}.
            // Description: A list of categories with links - for use in the custom menu as a submenu.
            // Parameters: None.
            if (stripos($text, '{categoriesmenu}') !== false) {
                // Retrieve list of all categories.
                if ($CFG->branch >= 36) { // Moodle 3.6+.
                    $categories = \core_course_category::make_categories_list();
                } else {
                    require_once($CFG->libdir . '/coursecatlib.php');
                    $categories = coursecat::make_categories_list();
                }
                $list = '';
                foreach ($categories as $id => $name) {
                    $list .= '-' . $this->format_custommenuitem($name) . '|/course/index.php?categoryid=' . $id . PHP_EOL;
                }
                $replace['/\{categoriesmenu\}/i'] = $list;
                unset($tag);
                unset($list);
            }

            // Tag: {categories0}.
            // Description: An unordered list of links to top level categories.
            // Parameters: None.
            if (stripos($text, '{categories0}') !== false) {
                // Display hidden categories if visibility user is siteadmin or role has moodle/category:viewhiddencategories.
                $context = \context_system::instance();
                $isadmin = (is_siteadmin() && !is_role_switched($PAGE->course->id));
                $viewhidden = has_capability('moodle/category:viewhiddencategories', $context, $USER, $isadmin);

                // Categories not visible will be still visible to site admins or users with viewhiddencourses capability.
                $sql = 'SELECT cc.id, cc.sortorder, cc.name, cc.visible, cc.parent
                        FROM {course_categories} cc
                        WHERE cc.parent = 0' . (!$viewhidden ? ' AND cc.visible = 1' : '') . '
                        ORDER BY cc.sortorder';
                $list = '';
                $categories = $DB->get_recordset_sql($sql, ['contextcoursecat' => CONTEXT_COURSECAT]);
                foreach ($categories as $category) {
                    if (!$category->visible && !$viewhidden) {
                        // Skip if the category is not visible to the user.
                        continue;
                    }
                    $dimmed = $category->visible ? '' : ' class="dimmed"';
                    $link = new \moodle_url('/course/index.php', ['categoryid' => $category->id]);
                    $link = $link->out();
                    $list .= '<li' . $dimmed . '><a href="' . $link . '">' . format_string($category->name) . '</a></li>' . PHP_EOL;
                }
                $list = !empty($list) ? '<ul>' . $list . '</ul>' : '';
                $categories->close();
                $replace['/\{categories0\}/i'] = $list;
                unset($list);
            }

            // Tag: {categories0menu}.
            // Description: A list of top level categories with links - for use in the custom menu as a top level menu.
            // Parameters: None.
            if (stripos($text, '{categories0menu}') !== false) {
                // Display hidden categories if visibility user is siteadmin or role has moodle/category:viewhiddencategories.
                $context = \context_system::instance();
                $isadmin = (is_siteadmin() && !is_role_switched($PAGE->course->id));
                $viewhidden = has_capability('moodle/category:viewhiddencategories', $context, $USER, $isadmin);

                // Categories not visible will be still visible to site admins or users with viewhiddencourses capability.
                $sql = 'SELECT cc.id, cc.sortorder, cc.name, cc.visible, cc.parent
                        FROM {course_categories} cc
                        WHERE cc.parent = 0' . (!$viewhidden ? ' AND cc.visible = 1' : '') . '
                        ORDER BY cc.sortorder';
                $list = '';
                $categories = $DB->get_recordset_sql($sql, ['contextcoursecat' => CONTEXT_COURSECAT]);
                foreach ($categories as $category) {
                    if (!$category->visible && !$viewhidden) {
                        // Skip if the category is not visible to the user.
                        continue;
                    }
                    $list .= '-' . $this->format_custommenuitem($category->name)
                         . '|/course/index.php?categoryid=' . $category->id . PHP_EOL;
                }
                $categories->close();
                $replace['/\{categories0menu\}/i'] = $list;
                unset($list);
            }

            // Tag: {categoriesx}.
            // Description: An unordered list of links to categories in the same level as the current course.
            // Parameters: None.
            if (stripos($text, '{categoriesx}') !== false) {
                $sql = "SELECT cc.id, cc.sortorder, cc.name, cc.visible, cc.parent
                        FROM {course_categories} cc
                        WHERE cc.parent = $catid AND cc.visible = 1
                        ORDER BY cc.sortorder";
                $list = '';
                $categories = $DB->get_recordset_sql($sql, ['contextcoursecat' => CONTEXT_COURSECAT]);
                foreach ($categories as $category) {
                    $list .= '<li><a href="' . (new \moodle_url('/course/index.php', ['categoryid' => $category->id]))->out() . '">'
                            . format_string($category->name) . '</a></li>' . PHP_EOL;
                }
                $list = !empty($list) ? '<ul>' . $list . '</ul>' : '';
                $categories->close();
                $replace['/\{categoriesx\}/i'] = $list;
                unset($list);
            }

            // Tag: {categoriesxmenu}.
            // Description: A list of links to categories in the same level as the current course - for use in the custom menu.
            // Parameters: None.
            if (stripos($text, '{categoriesxmenu}') !== false) {
                $sql = "SELECT cc.id, cc.sortorder, cc.name, cc.visible, cc.parent
                        FROM {course_categories} cc
                        WHERE cc.parent = $catid AND cc.visible = 1
                        ORDER BY cc.sortorder";
                $list = '';
                $categories = $DB->get_recordset_sql($sql, ['contextcoursecat' => CONTEXT_COURSECAT]);
                foreach ($categories as $category) {
                    $list .= '-' . $this->format_custommenuitem($category->name)
                        . '|/course/index.php?categoryid=' . $category->id . PHP_EOL;
                }
                $categories->close();
                $replace['/\{categoriesxmenu\}/i'] = $list;
                unset($list);
            }

            // Tag: {categorycards} and {categorycards categoryid}.
            // Description: Course sub-categories of the current level presented as card tiles.
            // Optional Parameter: You can specify a category id to display categories under that category. 0: Top level categories.
            if (stripos($text, '{categorycards') !== false) {
                $categoryids = [];
                $thiscategorycard = null;
                $categoryshowpic = get_config('filter_filtercodes', 'categorycardshowpic');

                // If category ID is not specified in the tag, figure it out from context.
                if (stripos($text, '{categorycards}') !== false) {
                    if (empty($PAGE->course->category)) {
                        // If we are not in a course, check if categoryid is part of URL (ex: course lists).
                        $thiscategorycard = optional_param('categoryid', 0, PARAM_INT);
                    } else {
                        // Retrieve the category id of the course we are in.
                        $thiscategorycard = $PAGE->course->category;
                    }
                    $categoryids[] = $thiscategorycard;
                }

                // If category ID was specified in the tag, use it.
                if (stripos($text, '{categorycards ') !== false) {
                    // Find all categorycards tags where category ID was specified.
                    preg_match_all('/\{categorycards ([0-9]+)\}/isuU', $text, $matches);
                    if (!empty($matches)) {
                        $categoryids = array_merge($categoryids, array_unique($matches[1]));
                    }
                }

                // For each tag's category ID.
                foreach ($categoryids as $catid) {
                    $sql = "SELECT cc.id, cc.sortorder, cc.name, cc.visible, cc.parent
                            FROM {course_categories} cc
                            WHERE cc.parent = $catid
                            ORDER BY cc.sortorder";
                    $subcategories = $DB->get_recordset_sql($sql);

                    $html = '';
                    foreach ($subcategories as $category) {
                        // Skip if user does not have permissions to view.
                        if (!\core_course_category::can_view_category($category)) {
                            continue;
                        }

                        // Render HTML category cards for the category.
                        $html .= $this->rendercategorycard($category, $categoryshowpic);
                    }
                    $subcategories->close();

                    if (!empty($html)) {
                        $html = '<ul class="fc-categorycards card-deck mr-0">' . $html . '</ul>';
                    }

                    // If this is the tag with no category ID.
                    if ($catid == $thiscategorycard) {
                        $replace['/\{categorycards\}/i'] = $html;
                        // If a tag with this category ID was also specified, replace it too.
                        if (stripos($text, '{categorycards ' . $catid . '}') !== false) {
                            $replace['/\{categorycards ' . $catid . '\}/isuU'] = $html;
                        }
                    } else {
                        $replace['/\{categorycards ' . $catid . '\}/isuU'] = $html;
                    }
                }
            }
            unset($categories, $catid, $thiscategorycard, $catids, $categoryids, $matches, $html, $categoryshowpic);
        }

        // Tag {mygroups}.
        // Description: List of groups that the user is in.
        // Parameters: None.
        if (stripos($text, '{mygroups}') !== false) {
            static $mygroups;

            if (!isset($mygroups)) {
                // Fetch my groups.
                $context = \context_course::instance($PAGE->course->id);
                $groups = groups_get_all_groups($PAGE->course->id, $USER->id);
                // Process group names through Moodle filters in case they are multi-language.
                $mygroups = [];
                foreach ($groups as $group) {
                    $mygroups[] = format_string($group->name, true, ['context' => $context]);
                }
                // Format groups into a language string.
                $mygroups = $this->formatlist($mygroups);
            }
            $replace['/\{mygroups\}/i'] = $mygroups;
        }

        // Tag {mygroupings}.
        // Description: List of groupings that the user is in.
        // Parameters: None.
        if (stripos($text, '{mygroupings}') !== false) {
            static $mygroupings;

            if (!isset($mygroupings)) {
                // Fetch my groups.
                $context = \context_course::instance($PAGE->course->id);
                if (!isset($mygroupingslist)) {
                    $mygroupingslist = $this->getusergroupings($PAGE->course->id, $USER->id);
                }
                // Process group names through Moodle filters in case they are multi-language.
                $mygroupings = [];
                foreach ($mygroupingslist as $grouping) {
                    $mygroupings[] = format_string($grouping->name, true, ['context' => $context]);
                }
                // Format groups into a language string.
                $mygroupings = $this->formatlist($mygroupings);
            }
            $replace['/\{mygroupings\}/i'] = $mygroupings;
        }

        // Tag: {wwwcontactform}.
        // Description: Action URL for ContactForm form submissions.
        // Parameters: None.
        if (stripos($text, '{wwwcontactform}') !== false) {
            $replace['/\{wwwcontactform\}/i'] = $CFG->wwwroot . '/local/contact/index.php';
        }

        // Tag: {sectionname}.
        // Description: The name of the section in which the current activity is located. Blank if not in course or on course page.
        // Parameters: None.
        if (stripos($text, '{sectionname}') !== false) {
            // If in a course and section name.
            if ($PAGE->course->id != $SITE->id && isset($PAGE->cm->sectionnum)) {
                $replace['/\{sectionname\}/i'] = get_section_name($PAGE->course->id, $PAGE->cm->sectionnum);
            } else {
                $replace['/\{sectionname\}/i'] = '';
            }
        }

        // Tag: {recaptcha}.
        // Description: Recaptcha. If used, you will need a way to process it as this just displays it. Used by ContactForms.
        // Parameters: None.
        if (stripos($text, '{recaptcha}') !== false) {
            $replace['/\{recaptcha\}/i'] = $this->getrecaptcha();
        }

        // Tag: {readonly}.
        // Description: For use in forms to make a field read-only when user is logged-in as non-guest.
        // Parameters: None.
        if (stripos($text, '{readonly}') !== false) {
            if ($this->isauthenticateduser()) {
                $replace['/\{readonly\}/i'] = 'readonly="readonly"';
            } else {
                $replace['/\{readonly\}/i'] = '';
            }
        }

        // Tag: {highlight}...{/highlight}.
        // Description: Applies a yellow background to the text, like a yellow highlighter.
        // Parameters: None.
        // Requires content between tags.
        if (stripos($text, '{/highlight}') !== false) {
            $replace['/\{highlight\}/i'] = '<mark style="background-color:#FFFF00;">';
            $replace['/\{\/highlight\}/i'] = '</mark>';
        }

        // Tag: {marktext}...{/marktext}.
        // Description: Applies a custom style defined by the fc-marktext CSS class.
        // Parameters: None.
        // Requires content between tags.
        if (stripos($text, '{/marktext}') !== false) {
            $replace['/\{marktext\}/i'] = '<mark class="fc-marktext">';
            $replace['/\{\/marktext\}/i'] = '</mark>';
        }

        // Tag: {markborder}...{/markborder}.
        // Description: Applies a red border around content. You can customize the style using the fc-markborder CSS class.
        // Parameters: None.
        // Requires content between tags.
        if (stripos($text, '{/markborder}') !== false) {
            $replace['/\{markborder\}/i'] = '<mark class="fc-markborder" style="border:2px dashed red;padding:0.03em 0.25em;">';
            $replace['/\{\/markborder\}/i'] = '</mark>';
        }

        // Tag: {showmore}...{/showmore}.
        // Description: Place part of your content in show more and it will initially appear collapsed with the words "show more".
        // Parameters: None.
        // Requires content between tags.
        if (stripos($text, '{/showmore}') !== false) {
            $newtext = str_replace('{showmore}', '<span id="fc-showmore-tmp" class="fc-showmore hidden">', $text);
            if (stripos($newtext, 'fc-showmore-tmp') !== false) {
                $newtext = preg_replace_callback('/fc-showmore-tmp/', function ($matches) {
                        static $count = 0;
                        return 'showmore-' . $count++;
                }, $newtext);
                $text = $newtext;
            }
            $newtext = str_replace('{/showmore}', '</span> <a href="#" class="fc-showmore" style="white-space: nowrap;" ' .
                    'onclick="m=document.getElementById(\'fc-showmore-tmp\').classList;m.toggle(\'hidden\');' .
                    'this.text=(m.contains(\'hidden\')?\'' . get_string('showmore', 'form') . '\':\'' .
                    get_string('showless', 'form') . '\');return false;">' . get_string('showmore', 'form') . '</a>', $newtext);
            if (stripos($newtext, 'fc-showmore-tmp') !== false) {
                $newtext = preg_replace_callback('/fc-showmore-tmp/', function ($matches) {
                        static $count = 0;
                        return 'showmore-' . $count++;
                }, $newtext);
                $text = $newtext;
            }
        }

        //
        // HTML tagging.
        //

        // Tag: {nbsp}.
        // Description: Will be replaced by an HTML non-breaking space (&nbsp).
        // Parameters: None.
        if (stripos($text, '{nbsp}') !== false) {
            $replace['/\{nbsp\}/i'] = '&nbsp;';
        }

        // Tag: {hr}.
        // Description: Will be replaced by an HTML horizontal rule (<hr>).
        // Parameters: None.
        if (stripos($text, '{hr}') !== false) {
            $replace['/\{hr\}/i'] = '<hr>';
        }

        // Tag: {-}.
        // Description: Will be replaced by an HTML soft hyphen (&shy;).
        // Parameters: None.
        if (stripos($text, '{-}') !== false) {
            $replace['/\{-\}/i'] = '&shy;';
        }

        // Tag: {langx xx}...{/langx}.
        // Description: Tag text as being in a particular language.
        // Parameters: None.
        // Requires content between tags.
        if (stripos($text, '{langx ') !== false) {
            $replace['/\{langx\s+([a-z-]+)\}(.*)\{\/langx\}/isuU'] = '<span lang="$1">$2</span>';
        }

        // Tag: {note}...{/note}
        // Description: Used to add notes that will appear when editing but not when displayed.
        // Parameters: None.
        // Requires content between tags.
        if (stripos($text, '{note}') !== false) {
            // Remove the note tags and its content.
            $replace['/\{note\}(.*)\{\/note\}/isuU'] = '';
        }

        // Tag: {details open|cssClass}{summary}...{/summary}...{/details}.
        // Description: Used to create collapsable sections of content. See HTML details/summary for usage.
        // Optional Parameter: 'open' if you want the content to be expanded by default. Alternatively, you can specify a CSS class.
        // Requires content between tags.
        if (stripos($text, '{/details}') !== false) {
            $replace['/\{details\}/i'] = '<details>';
            $replace['/\{details open\}/i'] = '<details open>';
            $replace['/\{\/details\}/i'] = '</details>';
            $replace['/\{summary\}/i'] = '<summary>';
            $replace['/\{\/summary\}/i'] = '</summary>';
            if (preg_match_all('/\{details ([a-zA-Z0-9-_ ]+)\}/', $text, $matches) !== 0) {
                foreach ($matches[1] as $cssclass) {
                    $replace['/\{details ' . $cssclass . '\}/i'] = '<details class="' . $cssclass . '">';
                }
            }
        }

        // Conditional block tags.

        if (strpos($text, '{if') !== false) { // If there are conditional tags.
            require_once($CFG->libdir . '/completionlib.php');

            // Tag: {ifinactivity}...{/ifinactivity}.
            // Description: Will display content if the tag is in an activity.
            // Parameters: None.
            // Requires content between tags.
            if (stripos($text, '{/ifinactivity}') !== false) {
                if (substr($PAGE->pagetype, 0, 4) == 'mod-') {
                    $replace['/\{ifinactivity\}/isu'] = '';
                    $replace['/\{\/ifinactivity\}/isu'] = '';
                } else {
                    $replace['/\{ifinactivity\}(.*){\/ifinactivity\}/isuU'] = '';
                }
            }

            // Tag: {ifnotinactivity}...{/ifnotinactivity}.
            // Description: Will display content if the tag is not in an activity.
            // Parameters: None.
            // Requires content between tags.
            if (stripos($text, '{/ifnotinactivity}') !== false) {
                if (substr($PAGE->pagetype, 0, 4) != 'mod-') {
                    $replace['/\{ifnotinactivity\}/isu'] = '';
                    $replace['/\{\/ifnotinactivity\}/isu'] = '';
                } else {
                    $replace['/\{ifnotinactivity\}(.*){\/ifnotinactivity\}/isuU'] = '';
                }
            }

            // Tag: {ifactivitycompleted coursemoduleid}...{/ifactivitycompleted}.
            // Description: Will display content if the specified activity has been completed.
            // Required Parameter: coursemoduleid is the id of the instance of the content module.
            // Requires content between tags.
            if (stripos($text, '{/ifactivitycompleted}') !== false) {
                $completion = new \completion_info($PAGE->course);

                if ($completion->is_enabled_for_site() && $completion->is_enabled() == COMPLETION_ENABLED) {
                    // Get a list of the the instances of this tag.
                    $re = '/{ifactivitycompleted\s+([0-9]+)\}(.*)\{\/ifactivitycompleted\}/isuU';
                    $found = preg_match_all($re, $text, $matches);

                    if ($found > 0) {
                        // Check if the activity is in the list.
                        foreach ($matches[1] as $cmid) {
                            $iscompleted = false;

                            // Only process valid IDs.
                            if (($cm = \get_coursemodule_from_id('', $cmid, 0)) !== false) {
                                // Get the completion data for this activity if it exists.
                                try {
                                    $data = $completion->get_data($cm, false, $USER->id);
                                    $iscompleted = ($data->completionstate > COMPLETION_INCOMPLETE); // A completed state.
                                } catch (\moodle_exception $e) {
                                    // Handle Moodle-specific exceptions.
                                    unset($e);
                                    continue;
                                } catch (\Exception $e) {
                                    unset($e);
                                    continue;
                                }
                            }

                            // If the activity has been completed, remove just the tags. Otherwise remove tags and content.
                            $key = '/{ifactivitycompleted\s+' . $cmid . '\}(.*)\{\/ifactivitycompleted\}/isuU';
                            if ($iscompleted) {
                                // Completed. Keep the text and remove the tags.
                                $replace[$key] = "$1";
                            } else {
                                // Activity not completed. Remove tags and content.
                                $replace[$key] = '';
                            }
                        }
                    }
                }
            }

            // Tag: {ifnotactivitycompleted coursemoduleid}...{/ifnotactivitycompleted}.
            // Description: Will display content if the specified activity has been completed.
            // Required Parameter: coursemoduleid is the id of the instance of the content module.
            // Requires content between tags.
            if (stripos($text, '{/ifnotactivitycompleted}') !== false) {
                $completion = new \completion_info($PAGE->course);

                if ($completion->is_enabled_for_site() && $completion->is_enabled() == COMPLETION_ENABLED) {
                    // Get a list of the the instances of this tag.
                    $re = '/{ifnotactivitycompleted\s+([0-9]+)\}(.*)\{\/ifnotactivitycompleted\}/isuU';
                    $found = preg_match_all($re, $text, $matches);

                    if ($found > 0) {
                        // Check if the activity is in the list.
                        foreach ($matches[1] as $cmid) {
                            $iscompleted = false;

                            // Only process valid IDs.
                            if (($cm = \get_coursemodule_from_id('', $cmid, 0)) !== false) {
                                // Get the completion data for this activity if it exists.
                                try {
                                    $data = $completion->get_data($cm, false, $USER->id);
                                    $iscompleted = ($data->completionstate > COMPLETION_INCOMPLETE); // A completed state.
                                } catch (\moodle_exception $e) {
                                    // Handle Moodle-specific exceptions.
                                    unset($e);
                                    continue;
                                } catch (\Exception $e) {
                                    unset($e);
                                    continue;
                                }
                            }

                            // If the activity has been completed, remove just the tags. Otherwise remove tags and content.
                            $key = '/{ifnotactivitycompleted\s+' . $cmid . '\}(.*)\{\/ifnotactivitycompleted\}/isuU';
                            if (!$iscompleted) {
                                // Completed. Keep the text and remove the tags.
                                $replace[$key] = "$1";
                            } else {
                                // Activity not completed. Remove tags and content.
                                $replace[$key] = '';
                            }
                        }
                    }
                }
            }

            // Tag: {ifprofile_field_shortname}...{ifprofile_field_shortname}.
            // Description: Will display content if specified the Custom User Profile Fields is not empty.
            // Required Parameter: Replace shortname with the shortname of the user profile field. Note that this is in both tags.
            // Requires content between tags.
            if (stripos($text, '{ifprofile_field_') !== false) {
                $isuser = ($this->isauthenticateduser());

                // Cached the defined custom profile fields and data.
                if (!isset($profilefields)) {
                    $profilefields = $DB->get_records('user_info_field', null, '', 'id, datatype, shortname, visible, param3');
                    if ($isuser && !empty($profilefields)) {
                        $profiledata = $DB->get_records_menu('user_info_data', ['userid' => $USER->id], '', 'fieldid, data');
                    }
                }

                // Determine if allowed to evaluate "Not visible" fields.
                $allowall = empty(get_config('filter_filtercodes', 'ifprofilefiedonlyvisible'));

                // Process each custom of the available profile fields.
                foreach ($profilefields as $field) {
                    $tag = 'ifprofile_field_' . $field->shortname;

                    // If the tag exists, user is logged-in and we are allowed to evaluate this field.
                    if (!empty($field->id) && isset($profiledata[$field->id]) && $isuser && ($field->visible != '0' || $allowall)) {
                        $data = trim($profiledata[$field->id]);
                    } else {
                        $data = '';
                    }
                    // If the value is empty or zero, remove the all of the tags and their contents for that field shortname.
                    if (empty($data)) {
                        $replace['/\{' . $tag . '(.*)\}(.*)\{\/' . $tag . '\}/isuU'] = '';
                        continue;
                    }

                    // If no comparison value is specified.
                    if (stripos($text, '{' . $tag . '}') !== false) {
                        // Just remove the tags.
                        $replace['/\{' . $tag . '\}/isu'] = '';
                        $replace['/\{\/' . $tag . '\}/isu'] = '';
                    }
                }
            }

            // Tag: {ifprofile_shortname is|not|contains|in "value"}...{/ifprofile}.
            // Description: Will display content if specified the User Profile Fields meets the specified condition.
            //
            // Parameter: shortname: Shortname of the custom user profile fields plus auth, idnumber, email, institution,
            // department, city, country, timezone and lang.
            // Parameter: contains|is|in: The comparison operator. Use:
            // 'is' to check if the field is an exact match for the value.
            // 'not' to check if the field does not contain the value.
            // 'contains' to check if the field contains the text.
            // 'in' to check if the value is in the fields content.
            // Parameters: "value": The text to compare the field against.
            // Requires content between tags.
            if (stripos($text, '{/ifprofile}') !== false) {
                // Retrieve all custom profile fields and specified core fields.
                $corefields = ['id', 'username', 'auth', 'idnumber', 'email', 'institution',
                    'department', 'city', 'country', 'timezone', 'lang'];
                $profilefields = $this->getuserprofilefields($USER, $corefields);

                // Find all ifprofile tags.
                $re = '/{ifprofile\s+(\w+)\s+(is|not|contains|in)\s+"([^}]*)"}(.*){\/ifprofile}/isuU';
                $found = preg_match_all($re, $text, $matches);
                if ($found > 0) {
                    foreach ($matches[1] as $key => $match) {
                        $fieldname = $matches[1][$key];
                        $string = $matches[0][$key]; // String found in $text.
                        $operator = $matches[2][$key];
                        $value = $matches[3][$key];

                        // Do not process tag if the specified profile field name does not exist or user is not logged in.
                        if (!array_key_exists($fieldname, $profilefields) || !isloggedin() || isguestuser()) {
                            if ($operator == 'not') {
                                // It will always meet criteria of a "not" if the user doesn't have a profile.
                                $replace['/' . preg_quote($string, '/') . '/isuU'] = $matches[4][$key];
                            } else {
                                // It will never match the criteria "is", "contains" or "in" if the user doesn't have a profile.
                                $replace['/' . preg_quote($string, '/') . '/isuU'] = '';
                            }
                            continue;
                        }

                        if (!empty($value)) {
                            $value = trim($value, '"'); // Trim quotation marks.
                        }

                        $content = '';
                        switch ($operator) {
                            case 'is':
                                // If the specified field is exactly the specified value.
                                // Example: {ifprofile country is "CA"}...{/ifprofile}.
                                // Example: {ifprofile city is ""}...{/ifprofile}.
                                if ($profilefields[$fieldname]->value === $value) {
                                    $content = $matches[4][$key];
                                }
                                break;
                            case 'not':
                                // Example: {ifprofile country not "CA"}...{/ifprofile}.
                                // Example: {ifprofile institution not ""}...{/ifprofile}.
                                if ($profilefields[$fieldname]->value !== $value) {
                                    $content = $matches[4][$key];
                                }
                                break;
                            case 'contains':
                                // If the specified field contains the specified value.
                                // Example:{ifprofile email contains "@yoursite.com"}...{/ifprofile}.
                                if (strpos($profilefields[$fieldname]->value, $value) !== false) {
                                    $content = $matches[4][$key];
                                }
                                break;
                            case 'in':
                                // If the specified value contains the value specified in the field.
                                // Example: {ifprofile country in "CA,US,UK,AU,NZ"}...{/ifprofile}.
                                if (strpos($value, $profilefields[$fieldname]->value) !== false) {
                                    $content = $matches[4][$key];
                                }
                                break;
                        }
                        $replace['/' . preg_quote($string, '/') . '/isuU'] = $content;
                    }
                }
            }

            // Tag: {ifmobile}...{/ifmobile}.
            // Description: Will display content if accessed from the mobile app.
            // Parameters: None.
            // Requires content between tags.
            if (stripos($text, '{/ifmobile}') !== false) {
                // If this is a web service or the Moodle mobile app...
                if ($this->iswebservice()) {
                    // Yes, just remove the tags.
                    $replace['/\{ifmobile\}/i'] = '';
                    $replace['/\{\/ifmobile\}/i'] = '';
                } else {
                    // Not from web services, remove tags and content.
                    $replace['/\{ifmobile\}(.*)\{\/ifmobile\}/isuU'] = '';
                }
            }

            // Tag: {ifnotmobile}...{/ifnotmobile}.
            // Description: Will display content if NOT accessed from the mobile app.
            // Parameters: None.
            // Requires content between tags.
            if (stripos($text, '{/ifnotmobile}') !== false) {
                // If this is a web service or the Moodle mobile app...
                if (!$this->iswebservice()) {
                    // Yes, just remove the tags.
                    $replace['/\{ifnotmobile\}/i'] = '';
                    $replace['/\{\/ifnotmobile\}/i'] = '';
                } else {
                    // Not from web services, remove tags and content.
                    $replace['/\{ifnotmobile\}(.*)\{\/ifnotmobile\}/isuU'] = '';
                }
            }

            // Tag: {ifloggedinas}...{/ifloggedinas}.
            // Description: Will display content if logged in as a different user. See https://docs.moodle.org/en/Log_in_as.
            // Parameters: None.
            // Requires content between tags.
            if (stripos($text, '{ifloggedinas}') !== false) {
                // If logged-in-as another user...
                if (\core\session\manager::is_loggedinas()) {
                    // Just remove the tags.
                    $replace['/\{ifloggedinas\}/i'] = '';
                    $replace['/\{\/ifloggedinas\}/i'] = '';
                } else {
                    // If logged in as another user, remove the ifloggedinas tags and contained content.
                    $replace['/\{ifloggedinas\}(.*)\{\/ifloggedinas\}/isuU'] = '';
                }
            }

            // Tag: {ifnotloggedinas}...{/ifnotloggedinas}.
            // Description: Will display content if NOT logged in as a different user. See https://docs.moodle.org/en/Log_in_as.
            // Parameters: None.
            // Requires content between tags.
            if (stripos($text, '{ifnotloggedinas}') !== false) {
                // If not logged-in-as another user...
                if (!\core\session\manager::is_loggedinas()) {
                    // Just remove the tags.
                    $replace['/\{ifnotloggedinas\}/i'] = '';
                    $replace['/\{\/ifnotloggedinas\}/i'] = '';
                } else {
                    // If logged in as another user, remove the if not loggedinas tags and contained content.
                    $replace['/\{ifnotloggedinas\}(.*)\{\/ifnotloggedinas\}/isuU'] = '';
                }
            }

            // Tag: {ifvisible}...{/ifvisible}.
            // Description: Will display content if the current course visibility is set to 'Show'.
            // Parameters: None.
            // Requires content between tags.
            if (stripos($text, '{ifvisible}') !== false) {
                global $COURSE;
                // If the course visibility is set to Show...
                if ($COURSE->id != 1 && !empty($COURSE->visible)) {
                    // Just remove the tags and leave the content.
                    $replace['/\{ifvisible\}/i'] = '';
                    $replace['/\{\/ifvisible\}/i'] = '';
                } else { // Visibility set to Hide.
                    // Remove the if visible tags and their content.
                    $replace['/\{ifvisible\}(.*)\{\/ifvisible\}/isuU'] = '';
                }
            }

            // Tag: {ifnotvisible}...{/ifnotvisible}.
            // Description: Will display content if the current course visibility is set to 'Hide'.
            // Parameters: None.
            // Requires content between tags.
            if (stripos($text, '{ifnotvisible}') !== false) {
                global $COURSE;
                // If the course visibility is set to hide...
                if ($COURSE->id != 1 && empty($COURSE->visible)) { // Visibility set to Hide.
                    // Just remove the tags.
                    $replace['/\{ifnotvisible\}/i'] = '';
                    $replace['/\{\/ifnotvisible\}/i'] = '';
                } else { // Visibility set to Show.
                    // Remove the if not visible tags and contained content.
                    $replace['/\{ifnotvisible\}(.*)\{\/ifnotvisible\}/isuU'] = '';
                }
            }

            // Tag: {ifincohort idname|idnumber}...{/ifincohort}.
            // Description: Will display content if the user is part of the specified cohort.
            // Parameters: id name or id number of the cohort.
            // Requires content between tags.
            if (stripos($text, '{ifincohort ') !== false) {
                if (empty($mycohorts)) { // Cache list of cohorts.
                    require_once($CFG->dirroot . '/cohort/lib.php');
                    $mycohorts = cohort_get_user_cohorts($USER->id);
                }
                $newtext = preg_replace_callback(
                    '/\{ifincohort ([\w\-]*)\}(.*)\{\/ifincohort\}/isuU',
                    function ($matches) use ($mycohorts) {
                        foreach ($mycohorts as $cohort) {
                            if ($cohort->idnumber == $matches[1] || $cohort->id == $matches[1]) {
                                return ($matches[2]);
                            };
                        }
                        return '';
                    },
                    $text
                );
                if ($newtext !== false) {
                    $text = $newtext;
                }
            }

            // Tag: {ifnotincohort idname|idnumber}...{/ifnotincohort}.
            // Description: Will display content if the user is not part of the specified cohort.
            // Parameters: id name or id number of the cohort.
            // Requires content between tags.
            if (stripos($text, '{ifnotincohort ') !== false) {
                if (empty($mycohorts)) { // Cache list of cohorts.
                    require_once($CFG->dirroot . '/cohort/lib.php');
                    $mycohorts = cohort_get_user_cohorts($USER->id);
                }
                $newtext = preg_replace_callback(
                    '/\{ifnotincohort ([\w\-]*)\}(.*)\{\/ifnotincohort\}/isuU',
                    function ($matches) use ($mycohorts) {
                        foreach ($mycohorts as $cohort) {
                            if ($cohort->idnumber == $matches[1] || $cohort->id == $matches[1]) {
                                return ''; // User is in the cohort, so return an empty string.
                            }
                        }
                        return $matches[2]; // User is not in the cohort, so return the content.
                    },
                    $text
                );
                if ($newtext !== false) {
                    $text = $newtext;
                }
            }

            // Tag: {ifeditmode}...{/ifeditmode}.
            // Description: Will display content if edit mode is turned on.
            // Parameters: None.
            // Requires content between tags.
            if (stripos($text, '{ifeditmode}') !== false) {
                // If editing mode is activated...
                if ($PAGE->user_is_editing()) {
                    // Just remove the tags.
                    $replace['/\{ifeditmode\}/i'] = '';
                    $replace['/\{\/ifeditmode\}/i'] = '';
                } else {
                    // If editing mode is not enabled, remove the ifeditmode tags and contained content.
                    $replace['/\{ifeditmode\}(.*)\{\/ifeditmode\}/isuU'] = '';
                }
            }

            // Tag: {ifnoteditmode}...{/ifnoteditmode}.
            // Description: Will display content if edit mode is turned off.
            // Parameters: None.
            // Requires content between tags.
            if (stripos($text, '{ifnoteditmode}') !== false) {
                // If editing mode is activated...
                if ($PAGE->user_is_editing()) {
                    // If editing mode is enabled, remove the ifnoteditmode tags and contained content.
                    $replace['/\{ifnoteditmode\}(.*)\{\/ifnoteditmode\}/isuU'] = '';
                } else {
                    // Just remove the tags.
                    $replace['/\{ifnoteditmode\}/i'] = '';
                    $replace['/\{\/ifnoteditmode\}/i'] = '';
                }
            }

            // Tag: {ifcourserequests}...{/ifcourserequests}.
            // Description: Will display content if the 'Request a course' feature is enabled.
            // Parameters: None.
            // Requires content between tags.
            if (stripos($text, '{ifcourserequests}') !== false) {
                // If Request a course is enabled...
                $context = \context_system::instance();
                if (empty($CFG->enablecourserequests) || !has_capability('moodle/course:request', $context)) {
                    // Just remove the tags.
                    $replace['/\{ifcourserequests\}/i'] = '';
                    $replace['/\{\/ifcourserequests\}/i'] = '';
                } else {
                    // If Request a Course is not enabled, remove the ifcourserequests tags and contained content.
                    $replace['/\{ifcourserequests\}(.*)\{\/ifcourserequests\}/isuU'] = '';
                }
            }

            // Tags: {ifenrolpage}...{/ifenrolpage}.
            // Description: Will display content if you are viewing the enrolment page of a course.
            // Parameters: None.
            // Requires content between tags.
            if (stripos($text, '{ifenrolpage}') !== false) {
                // If on a course enrolment page.
                if ($PAGE->pagetype == 'enrol-index') {
                    // Remove the ifenrolpage tags.
                    $replace['/\{ifenrolpage\}/i'] = '';
                    $replace['/\{\/ifenrolpage\}/i'] = '';
                } else {
                    // Remove the ifenrolpage strings.
                    $replace['/\{ifenrolpage\}(.*)\{\/ifenrolpage\}/isuU'] = '';
                }
            }

            // Tags: {ifnotenrolpage}...{/ifnotenrolpage}.
            // Description: Will display content if you are not viewing the enrolment page of a course.
            // Parameters: None.
            // Requires content between tags.
            if (stripos($text, '{ifnotenrolpage}') !== false) {
                // If on a course enrolment page.
                if ($PAGE->pagetype == 'enrol-index') {
                    // Remove the ifnotenrolpage strings.
                    $replace['/\{ifnotenrolpage\}(.*)\{\/ifnotenrolpage\}/isuU'] = '';
                } else {
                    // Remove the ifenrolled tags.
                    $replace['/\{ifnotenrolpage\}/i'] = '';
                    $replace['/\{\/ifnotenrolpage\}/i'] = '';
                }
            }

            // Tag: {ifenrolled}..{/ifenrolled}.
            // Description: Will display content if you are enrolled in the current course.
            // Parameters: None.
            // Requires content between tags.

            // Tag: {ifnotenrolled}...{/ifnotenrolled}.
            // Description: Will display content if you are not enrolled in the current course.
            // Parameters: None.
            // Requires content between tags.

            // Tag: {ifincourse}...{/ifincourse}.
            // Description: Will display content if you are anywhere in a course.
            // Parameters: None.
            // Requires content between tags.

            // Tag: {ifnotincourse}...{/ifnotincourse}.
            // Description: Will display content if you are not anywhere in a course.
            // Parameters: None.
            // Requires content between tags.

            // Tag: {ifinsection}...{/ifinsection}.
            // Description: Will display content if you are in a section of a course.
            // Parameters: None.
            // Requires content between tags.

            if ($PAGE->course->id == $SITE->id) { // If frontpage course.
                // Everyone is automatically enrolled in the Front Page course.
                // Remove the ifenrolled tags.
                if (stripos($text, '{ifenrolled}') !== false) {
                    $replace['/\{ifenrolled\}/i'] = '';
                    $replace['/\{\/ifenrolled\}/i'] = '';
                }
                // Remove the ifnotenrolled strings.
                if (stripos($text, '{ifnotenrolled}') !== false) {
                    $replace['/\{ifnotenrolled\}(.*)\{\/ifnotenrolled\}/isuU'] = '';
                }
                // Remove the {ifincourse} strings if not in a course or on the Front Page.
                if (stripos($text, '{ifincourse}') !== false) {
                    $replace['/\{ifincourse\}(.*)\{\/ifincourse\}/isuU'] = '';
                }
                // If not in a course, remove the {ifnotincourse} tags.
                if (stripos($text, '{ifnotincourse}') !== false) {
                    $replace['/\{ifnotincourse\}/i'] = '';
                    $replace['/\{\/ifnotincourse\}/i'] = '';
                }
                // Remove the {ifinsection} strings if not in a section of a course or are on the Front Page.
                if (stripos($text, '{ifinsection}') !== false) {
                    $replace['/\{ifinsection\}(.*)\{\/ifinsection\}/isuU'] = '';
                }
            } else {
                if ($this->hasarchetype('student')) { // If user is enrolled in the course.
                    // Remove the {ifnotincourse} strings if in a course.
                    if (stripos($text, '{ifnotincourse}') !== false) {
                        $replace['/\{ifnotincourse\}(.*)\{\/ifnotincourse\}/isuU'] = '';
                    }
                    // If enrolled, remove the {ifenrolled} tags.
                    if (stripos($text, '{ifenrolled}') !== false) {
                        $replace['/\{ifenrolled\}/i'] = '';
                        $replace['/\{\/ifenrolled\}/i'] = '';
                    }
                    // Remove the ifnotenrolled strings.
                    if (stripos($text, '{ifnotenrolled}') !== false) {
                        $replace['/\{ifnotenrolled\}(.*)\{\/ifnotenrolled\}/isuU'] = '';
                    }
                } else {
                    // Otherwise, remove the ifenrolled strings.
                    if (stripos($text, '{ifenrolled}') !== false) {
                        $replace['/\{ifenrolled\}(.*)\{\/ifenrolled\}/isuU'] = '';
                    }
                    // And remove the ifnotenrolled tags.
                    if (stripos($text, '{ifnotenrolled}') !== false) {
                        $replace['/\{ifnotenrolled\}/i'] = '';
                        $replace['/\{\/ifnotenrolled\}/i'] = '';
                    }
                }
                // Tag: {ifincourse}...{/ifincourse}. // phpcs:ignore .
                if (stripos($text, '{ifincourse}') !== false) {
                    $replace['/\{ifincourse\}/i'] = '';
                    $replace['/\{\/ifincourse\}/i'] = '';
                }
                // Tag: {ifinsection}...{/ifinsection}. // phpcs:ignore .
                if (stripos($text, '{ifinsection}') !== false) {
                    if (!empty(@$PAGE->cm->sectionnum)) {
                        $replace['/\{ifinsection\}/i'] = '';
                        $replace['/\{\/ifinsection\}/i'] = '';
                    } else {
                        // Remove the ifinsection strings.
                        if (stripos($text, '{ifinsection}') !== false) {
                            $replace['/\{ifinsection\}(.*)\{\/ifinsection\}/isuU'] = '';
                        }
                    }
                }
            }

            // Tag: {ifnotinsection}...{/ifnotinsection}.
            // Description: Display content if not in a section of a course.
            // Parameters: None.
            // Requires content between tags.
            if (stripos($text, '{ifnotinsection}') !== false) {
                if (empty(@$PAGE->cm->sectionnum)) {
                    $replace['/\{ifnotinsection\}/i'] = '';
                    $replace['/\{\/ifnotinsection\}/i'] = '';
                } else {
                    // Remove the ifnotinsection strings.
                    if (stripos($text, '{ifnotinsection}') !== false) {
                        $replace['/\{ifnotinsection\}(.*)\{\/ifnotinsection\}/isuU'] = '';
                    }
                }
            }

            // Tag: {ifstudent}...{/ifstudent}.
            // Description: This is similar to {ifenrolled} but only displays if user is enrolled as just a student in the course.
            // Must be logged-in and must not have additional higher level roles.
            // Example: Student but not Administrator, or Student but not Teacher.
            // Parameters: None.
            // Requires content between tags.
            if ($this->hasonlyarchetype('student')) {
                if (stripos($text, '{ifstudent}') !== false) {
                    // Just remove the tags.
                    $replace['/\{ifstudent\}/i'] = '';
                    $replace['/\{\/ifstudent\}/i'] = '';
                }
            } else {
                // And remove the ifstudent strings.
                if (stripos($text, '{ifstudent}') !== false) {
                    $replace['/\{ifstudent\}(.*)\{\/ifstudent\}/isuU'] = '';
                }
            }

            // Tag: {ifminstudent}...{/ifminstudent}.
            // Description: This is similar to {ifstudent} but will displays if user's list of roles includes student.
            // Example: Student but may also be teacher or administrator.
            // Parameters: None.
            // Requires content between tags.
            if (stripos($text, '{ifminstudent}') !== false) {
                // If an assistant (non-editing teacher).
                if ($this->hasarchetype('student')) {
                    // Just remove the tags.
                    $replace['/\{ifminstudent\}/i'] = '';
                    $replace['/\{\/ifminstudent\}/i'] = '';
                } else {
                    // Remove the ifminstudent strings.
                    $replace['/\{ifminstudent\}(.*)\{\/ifminstudent\}/isuU'] = '';
                }
            }

            // Tag: {ifloggedin}...{/ifloggedin}
            // Description: Display content if logged-in but not if logged-in as guest.
            // Parameters: None.
            // Requires content between tags.

            // Tag: {ifloggedout}...{/ifloggedout}.
            // Description: Display content if NOT logged-in. Guest is not considered logged in.
            // Parameters: None.
            // Requires content between tags.

            if ($this->isauthenticateduser()) { // If logged-in but not just as guest.
                // Just remove ifloggedin tags.
                if (stripos($text, '{ifloggedin}') !== false) {
                    $replace['/\{ifloggedin\}/i'] = '';
                    $replace['/\{\/ifloggedin\}/i'] = '';
                }
                // Remove the ifloggedout strings.
                if (stripos($text, '{ifloggedout}') !== false) {
                    $replace['/\{ifloggedout\}(.*)\{\/ifloggedout\}/isuU'] = '';
                }
            } else { // If logged-out.
                // Remove the ifloggedout tags.
                if (stripos($text, '{ifloggedout}') !== false) {
                    $replace['/\{ifloggedout\}/i'] = '';
                    $replace['/\{\/ifloggedout\}/i'] = '';
                }
                // Remove ifloggedin strings.
                if (stripos($text, '{ifloggedin}') !== false) {
                    $replace['/\{ifloggedin\}(.*)\{\/ifloggedin\}/isuU'] = '';
                }
            }

            // Tag: {ifguest}...{/ifguest}.
            // Description: Display content if logged-in as a guest user.
            // Parameters: None.
            // Requires content between tags.
            if (stripos($text, '{ifguest}') !== false) {
                if (isguestuser()) { // If logged-in as guest.
                    // Just remove the tags.
                    $replace['/\{ifguest\}/i'] = '';
                    $replace['/\{\/ifguest\}/i'] = '';
                } else {
                    // If not logged-in as guest, remove the ifguest text.
                    $replace['/\{ifguest\}(.*)\{\/ifguest\}/isuU'] = '';
                }
            }

            // Tag: {ifassistant}...{/ifassistant}.
            // Description: Display content if a non-editing teacher in the course.
            // Parameters: None.
            // Requires content between tags.
            if (stripos($text, '{ifassistant}') !== false) {
                // If an assistant (non-editing teacher).
                if ($this->hasarchetype('teacher') && stripos($text, '{ifassistant}') !== false) {
                    // Just remove the tags.
                    $replace['/\{ifassistant\}/i'] = '';
                    $replace['/\{\/ifassistant\}/i'] = '';
                } else {
                    // Remove the ifassistant strings.
                    $replace['/\{ifassistant\}(.*)\{\/ifassistant\}/isuU'] = '';
                }
            }

            // Tag: {ifteacher}...{/ifteacher}.
            // Description: Display content if an editing teacher in the course.
            // Parameters: None.
            // Requires content between tags.
            if (stripos($text, '{ifteacher}') !== false) {
                if ($this->hasarchetype('editingteacher')) { // If a teacher.
                    // Just remove the tags.
                    $replace['/\{ifteacher\}/i'] = '';
                    $replace['/\{\/ifteacher\}/i'] = '';
                } else {
                    // Remove the ifteacher strings.
                    $replace['/\{ifteacher\}(.*)\{\/ifteacher\}/isuU'] = '';
                }
            }

            // Tag: {ifcreator}...{/ifcreator}.
            // Description: Display content if the user has the role of course creator.
            // Parameters: None.
            // Requires content between tags.
            if (stripos($text, '{ifcreator}') !== false) {
                if ($this->hasarchetype('coursecreator')) { // If a course creator.
                    // Just remove the tags.
                    $replace['/\{ifcreator\}/i'] = '';
                    $replace['/\{\/ifcreator\}/i'] = '';
                } else {
                    // Remove the iscreator strings.
                    $replace['/\{ifcreator\}(.*)\{\/ifcreator\}/isuU'] = '';
                }
            }

            // Tag: {ifmanager}...{/ifmanager}.
            // Description: Display content if the user has the role of a manager.
            // Parameters: None.
            // Requires content between tags.
            if (stripos($text, '{ifmanager}') !== false) {
                if ($this->hasarchetype('manager')) { // If a manager.
                    // Just remove the tags.
                    $replace['/\{ifmanager\}/i'] = '';
                    $replace['/\{\/ifmanager\}/i'] = '';
                } else {
                    // Remove the ifmanager strings.
                    $replace['/\{ifmanager\}(.*)\{\/ifmanager\}/isuU'] = '';
                }
            }

            // Tag: {ifadmin}...{/ifadmin}.
            // Description: Display content if the user is a site administrator.
            // Parameters: None.
            // Requires content between tags.
            if (stripos($text, '{ifadmin}') !== false) {
                if (is_siteadmin() && !is_role_switched($PAGE->course->id)) { // If an administrator.
                    // Just remove the tags.
                    $replace['/\{ifadmin\}/i'] = '';
                    $replace['/\{\/ifadmin\}/i'] = '';
                } else {
                    // Remove the ifadmin strings.
                    $replace['/\{ifadmin\}(.*)\{\/ifadmin\}/isuU'] = '';
                }
            }

            // Tag: {ifdashboard}...{/ifdashboard}.
            // Description: Display content if the user is viewing the dashboard.
            // Parameters: None.
            // Requires content between tags.
            if (stripos($text, '{ifdashboard}') !== false) {
                if ($PAGE->pagetype == 'my-index') { // If dashboard.
                    // Just remove the tags.
                    $replace['/\{ifdashboard\}/i'] = '';
                    $replace['/\{\/ifdashboard\}/i'] = '';
                } else {
                    // If not on the dashboard page, remove the ifdashboard text.
                    $replace['/\{ifdashboard\}(.*)\{\/ifdashboard\}/isuU'] = '';
                }
            }

            // Tag: {ifhome}...{/ifhome}.
            // Description: Display content if the user is viewing the Front page.
            // Parameters: None.
            // Requires content between tags.
            if (stripos($text, '{ifhome}') !== false) {
                if ($PAGE->pagetype == 'site-index') { // If front page.
                    // Just remove the tags.
                    $replace['/\{ifhome\}/i'] = '';
                    $replace['/\{\/ifhome\}/i'] = '';
                } else {
                    // If not on the front page, remove the ifhome text.
                    $replace['/\{ifhome\}(.*)\{\/ifhome\}/isuU'] = '';
                }
            }
            // Tag: {ifnothome}...{/ifnothome}.
            // Description: Display content if the user is not viewing any other page than the Front page.
            // Parameters: None.
            // Requires content between tags.
            if (stripos($text, '{ifnothome}') !== false) {
                if ($PAGE->pagetype != 'site-index') { // If front page.
                    // Just remove the tags.
                    $replace['/\{ifnothome\}/i'] = '';
                    $replace['/\{\/ifnothome\}/i'] = '';
                } else {
                    // If not on the front page, remove the ifhome text.
                    $replace['/\{ifnothome\}(.*)\{\/ifnothome\}/isuU'] = '';
                }
            }

            // Tag: {ifdev}...{/ifdev}.
            // Description: Display content if the user is a site admnistrator and has debugging set to DEVELOPER mode.
            // Parameters: None.
            // Requires content between tags.
            if (stripos($text, '{ifdev}') !== false) {
                // If an administrator with debugging is set to DEVELOPER mode...
                if ($CFG->debugdisplay == 1 && is_siteadmin() && !is_role_switched($PAGE->course->id)) {
                    // Just remove the tags.
                    $replace['/\{ifdev\}/i'] = '';
                    $replace['/\{\/ifdev\}/i'] = '';
                } else {
                    // If not a developer with debugging set to DEVELOPER mode, remove the ifdev tags and contained content.
                    $replace['/\{ifdev\}(.*)\{\/ifdev\}/isuU'] = '';
                }
            }

            // Tag: {ifingroup id|idnumber}...{/ifingroup}.
            // Description: Display content if the user is a member of the specified group.
            // Required Parameters: group id or idnumber.
            // Requires content between tags.
            if (stripos($text, '{ifingroup') !== false) {
                if (!isset($mygroupslist)) { // Fetch my groups.
                    $mygroupslist = groups_get_all_groups($PAGE->course->id, $USER->id);
                }
                $re = '/{ifingroup\s+(.*)\}(.*)\{\/ifingroup\}/isuU';
                $found = preg_match_all($re, $text, $matches);
                if ($found > 0) {
                    foreach ($matches[1] as $groupid) {
                        $key = '/{ifingroup\s+' . $groupid . '\}(.*)\{\/ifingroup\}/isuU';
                        $ismember = false;
                        foreach ($mygroupslist as $group) {
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

            // Tag: {ifnotingroup...}...{/ifnotingroup} with and without parameters.
            if (stripos($text, '{ifnotingroup') !== false) {
                // Tag: {ifnotingroup}...{/ifnotingroup}.
                // Description: Display content if the user is NOT a member of any group.
                // Required Parameters: None.
                // Requires content between tags.
                if (stripos($text, '{ifnotingroup}') !== false) {
                    if (!isset($mygroupslist)) { // Fetch my groups.
                        $mygroupslist = groups_get_all_groups($PAGE->course->id, $USER->id);
                    }
                    if (empty($mygroupslist)) {
                        // User is not in any group, just remove the tags.
                        $replace['/\{ifnotingroup\}/i'] = '';
                        $replace['/\{\/ifnotingroup\}/i'] = '';
                    } else {
                        // User is in at least one group, remove tags and content.
                        $replace['/\{ifnotingroup\}(.*)\{\/ifnotingroup\}/isuU'] = '';
                    }
                }

                // Tag: {ifnotingroup id|idnumber}...{/ifnotingroup}.
                // Description: Display content if the user is NOT a member of the specified group.
                // Required Parameters: group id or idnumber.
                // Requires content between tags.
                if (stripos($text, '{ifnotingroup') !== false) {
                    if (!isset($mygroupslist)) { // Fetch my groups.
                        $mygroupslist = groups_get_all_groups($PAGE->course->id, $USER->id);
                    }
                    $re = '/{ifnotingroup\s+(.*)\}(.*)\{\/ifnotingroup\}/isuU';
                    $found = preg_match_all($re, $text, $matches);
                    if ($found > 0) {
                        foreach ($matches[1] as $groupid) {
                            $key = '/{ifnotingroup\s+' . $groupid . '\}(.*)\{\/ifnotingroup\}/isuU';
                            $ismember = false;
                            foreach ($mygroupslist as $group) {
                                if ($groupid == $group->id || $groupid == $group->idnumber) {
                                    $ismember = true;
                                    break;
                                }
                            }
                            if ($ismember) { // Remove the ifnotingroup tags and content.
                                $replace[$key] = '';
                            } else { // Just remove the tags and keep the content.
                                $replace[$key] = '$1';
                            }
                        }
                    }
                }
            }

            // Tag: {ifingrouping id|idnumber}...{/ifingrouping}.
            // Description: Display content if the user is a member of the specified grouping.
            // Required Parameters: group id or idnumber.
            // Requires content between tags.
            if (stripos($text, '{ifingrouping') !== false) {
                if (!isset($mygroupingslist)) {
                    $mygroupingslist = $this->getusergroupings($PAGE->course->id, $USER->id);
                }
                $re = '/{ifingrouping\s+(.*)\}(.*)\{\/ifingrouping\}/isuU';
                $found = preg_match_all($re, $text, $matches);
                if ($found > 0) {
                    foreach ($matches[1] as $groupingid) {
                        $key = '/{ifingrouping\s+' . $groupingid . '\}(.*)\{\/ifingrouping\}/isuU';
                        $ismember = false;
                        foreach ($mygroupingslist as $grouping) {
                            if ($groupingid == $grouping->id || $groupingid == $grouping->idnumber) {
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

            // Tag: {ifnotingrouping id|idnumber}...{/ifnotingrouping}.
            // Description: Display content if the user is NOT a member of the specified grouping.
            // Required Parameters: group id or idnumber.
            // Requires content between tags.
            if (stripos($text, '{ifnotingrouping') !== false) {
                if (!isset($mygroupingslist)) {
                    $mygroupingslist = $this->getusergroupings($PAGE->course->id, $USER->id);
                }
                $re = '/{ifnotingrouping\s+(.*)\}(.*)\{\/ifnotingrouping\}/isuU';
                $found = preg_match_all($re, $text, $matches);
                if ($found > 0) {
                    foreach ($matches[1] as $groupingid) {
                        $key = '/{ifnotingrouping\s+' . $groupingid . '\}(.*)\{\/ifnotingrouping\}/isuU';
                        $ismember = false;
                        foreach ($mygroupingslist as $grouping) {
                            if ($groupingid == $grouping->id || $groupingid == $grouping->idnumber) {
                                $ismember = true;
                                break;
                            }
                        }
                        if ($ismember) { // Remove the ifnotingroup tags and content.
                            $replace[$key] = '';
                        } else { // Just remove the tags and keep the content.
                            $replace[$key] = '$1';
                        }
                    }
                }
            }

            // Tag: {iftenant idnumber|tenantid}...{/iftenant}.
            // Description: Display content only if the user is part of the specified tenant on Moodle Workplace.
            // Required Parameter: tenant idnumber or tenantid.
            // Requires content between tags.
            if (stripos($text, '{iftenant') !== false) {
                if (class_exists('tool_tenant\tenancy')) {
                    // Moodle Workplace.
                    $tenants = \tool_tenant\tenancy::get_tenants();
                    // Get current tenantid.
                    $currenttenantid = \tool_tenant\tenancy::get_tenant_id();
                } else {
                    // Moodle Classic - Just simulate functionality as tenant 1.
                    // This allows a course to work in both Moodle Classic and Workplace.
                    $tenants[0] = new \stdClass();
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
                $re = '/{iftenant\s+(.*)\}(.*)\{\/iftenant\}/isuU';
                $found = preg_match_all($re, $text, $matches);
                if ($found > 0) {
                    foreach ($matches[1] as $tenantid) {
                        $key = '/{iftenant\s+' . $tenantid . '\}(.*)\{\/iftenant\}/isuU';
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

            // Tag: {ifworkplace}...{/ifworkplace}.
            // Description: Display content only if using Moodle Workplace.
            // Parameters: None.
            // Requires content between tags.
            if (stripos($text, '{ifworkplace}') !== false) {
                if (class_exists('tool_tenant\tenancy')) {
                    // Moodle Workplace - Just remove the tags.
                    $replace['/\{ifworkplace\}/i'] = '';
                    $replace['/\{\/ifworkplace\}/i'] = '';
                } else {
                    // If Moodle Classic, remove the ifworkplace tags and text.
                    $replace['/\{ifworkplace\}(.*)\{\/ifworkplace\}/isuU'] = '';
                }
            }

            // Tag: {ifcustomrole shortrolename}...{/ifcustomrole}.
            // Description: Display content only if user has the role specified by shortrolename in the current context.
            // Parameters: Short role name.
            // Requires content between tags.
            if (stripos($text, '{ifcustomrole') !== false) {
                $re = '/{ifcustomrole\s+(.*)\}(.*)\{\/ifcustomrole\}/isuU';
                $found = preg_match_all($re, $text, $matches);
                if ($found > 0) {
                    $context = $PAGE->context;
                    if ($context->contextlevel == CONTEXT_COURSE) {
                        // We are in a course.
                        $context = \context_course::instance($context->instanceid);
                    } else if ($context->contextlevel == CONTEXT_MODULE) {
                        // We are in an activity.
                        $cm = get_coursemodule_from_id('', $context->instanceid, 0, false, MUST_EXIST);
                        $context = \context_module::instance($cm->id);
                        unset($cm);
                    }

                    // Get roles within this context.
                    $roles = get_user_roles($context, $USER->id, true);
                    $roles = array_column($roles, 'shortname');
                    unset($context);

                    // Replace all instances of a given ifcustomrole tag.
                    foreach ($matches[1] as $roleshortname) {
                        $key = '/{ifcustomrole\s+' . $roleshortname . '\}(.*)\{\/ifcustomrole\}/isuU';
                        // We have a role that matches this tag.
                        if (in_array($roleshortname, $roles)) {
                            // Just remove the tags.
                            $replace[$key] = '$1';
                        } else {
                            // Otherwise, remove the ifcustomrole tags and the string inside it.
                            $replace[$key] = '';
                        }
                        unset($key);
                    }
                }
                unset($re);
                unset($found);
            }

            // Tag: {ifnotcustomrole shortrolename}...{/ifnotcustomrole}.
            // Description: Display content only if user does NOT have the role specified by shortrolename in the current context.
            // Required Parameters: Short role name.
            // Requires content between tags.
            if (stripos($text, '{ifnotcustomrole') !== false) {
                $re = '/{ifnotcustomrole\s+(.*)\}(.*)\{\/ifnotcustomrole\}/isuU';
                $found = preg_match_all($re, $text, $matches);
                if ($found > 0) {
                    $context = $PAGE->context;
                    if ($context->contextlevel == CONTEXT_COURSE) {
                        // We are in a course.
                        $context = \context_course::instance($context->instanceid);
                    } else if ($context->contextlevel == CONTEXT_MODULE) {
                        // We are in an activity.
                        $cm = get_coursemodule_from_id('', $context->instanceid, 0, false, MUST_EXIST);
                        $context = \context_module::instance($cm->id);
                        unset($cm);
                    }

                    // Get roles within this context.
                    $roles = get_user_roles($context, $USER->id, true);
                    $roles = array_column($roles, 'shortname');
                    unset($context);

                    // Replace all instances of a given ifnotcustomrole tag.
                    foreach ($matches[1] as $roleshortname) {
                        $key = '/{ifnotcustomrole\s+' . $roleshortname . '\}(.*)\{\/ifnotcustomrole\}/isuU';
                        // We do not have a role that matches this tag.
                        if (!in_array($roleshortname, $roles)) {
                            // Just remove the tags.
                            $replace[$key] = '$1';
                        } else {
                            // Otherwise, remove the ifnotcustomrole strings.
                            $replace[$key] = '';
                        }
                        unset($key);
                    }
                }
                unset($re);
                unset($found);
            }

            // Tag: {ifhasarolename roleshortname}...{/ifhasarolename}.
            // Description: Display content only if user has the role specified by shortrolename ANYWHERE on the site.
            // Parameters: Short role name.
            // Requires content between tags.
            if (stripos($text, '{ifhasarolename') !== false) {
                $re = '/{ifhasarolename\s+(.*)\}(.*)\{\/ifhasarolename\}/isuU';
                $found = preg_match_all($re, $text, $matches);
                if ($found > 0) {
                    foreach ($matches[1] as $roleshortname) {
                        $key = '/{ifhasarolename\s+' . $roleshortname . '\}(.*)\{\/ifhasarolename\}/isuU';
                        if ($this->hasarole($roleshortname, $USER->id)) {
                            // Just remove the tags.
                            $replace[$key] = '$1';
                        } else {
                            // Remove the ifhasarolename strings.
                            $replace[$key] = '';
                        }
                    }
                }
            }

            // Tag: {iftheme themename}...{/iftheme}.
            // Description: Display content only if the current theme matches the one specified.
            // Parameters: The name of the directory in which the theme is located.
            // Requires content between tags.
            if (stripos($text, '{/iftheme') !== false) {
                $theme = strtolower($PAGE->theme->name);
                $re = '/{iftheme\s+(.*)\}(.*)\{\/iftheme\}/isuU';
                $found = preg_match_all($re, $text, $matches);
                if ($found > 0) {
                    foreach ($matches[1] as $themename) {
                        $key = '/{iftheme\s+' . $themename . '\}(.*)\{\/iftheme\}/isuU';
                        if (strtolower($theme == strtolower($themename))) {
                            // Just remove the tags.
                            $replace[$key] = '$1';
                        } else {
                            // Remove the iftheme strings.
                            $replace[$key] = '';
                        }
                    }
                }
            }

            // Tag: {ifnottheme themename}...{/ifnottheme}.
            // Description: Display content only if the current theme does not match the one specified.
            // Parameters: The name of the directory in which the theme is located.
            // Requires content between tags.
            if (stripos($text, '{ifnottheme ') !== false) {
                $theme = strtolower($PAGE->theme->name);
                $re = '/{ifnottheme\s+(.*)\}(.*)\{\/ifnottheme\}/isuU';
                $found = preg_match_all($re, $text, $matches);
                if ($found > 0) {
                    foreach ($matches[1] as $themename) {
                        $key = '/{ifnottheme\s+' . $themename . '\}(.*)\{\/ifnottheme\}/isuU';
                        if (strtolower($theme) != strtolower($themename)) {
                            // Just remove the tags.
                            $replace[$key] = '$1';
                        } else {
                            // Remove the ifnottheme strings.
                            $replace[$key] = '';
                        }
                    }
                }
            }

            if (strpos($text, '{ifmin') !== false) { // If there are conditional ifmin tags.
                // Tag: {ifminassistant}...{/ifminassistant}.
                // Description: Display content only if user has the role of a non-editing teacher or higher.
                // Parameters: None.
                // Requires content between tags.
                if (stripos($text, '{ifminassistant}') !== false) {
                    // If an assistant (non-editing teacher) or above.
                    if ($this->hasminarchetype('teacher') && stripos($text, '{ifminassistant}') !== false) {
                        // Just remove the tags.
                        $replace['/\{ifminassistant\}/i'] = '';
                        $replace['/\{\/ifminassistant\}/i'] = '';
                    } else {
                        // Remove the ifminassistant strings.
                        $replace['/\{ifminassistant\}(.*)\{\/ifminassistant\}/isuU'] = '';
                    }
                }

                // Tag: {ifminteacher}...{/ifminteacher}.
                // Description: Display content only if user has the role of a editing teacher or higher.
                // Parameters: None.
                // Requires content between tags.
                if (stripos($text, '{ifminteacher}') !== false) {
                    if ($this->hasminarchetype('editingteacher')) { // If a teacher or above.
                        // Just remove the tags.
                        $replace['/\{ifminteacher\}/i'] = '';
                        $replace['/\{\/ifminteacher\}/i'] = '';
                    } else {
                        // Remove the ifminteacher strings.
                        $replace['/\{ifminteacher\}(.*)\{\/ifminteacher\}/isuU'] = '';
                    }
                }

                // Tag: {ifmincreator}...{/ifmincreator}.
                // Description: Display content only if user has the role of a course creator or higher.
                // Parameters: None.
                // Requires content between tags.
                if (stripos($text, '{ifmincreator}') !== false) {
                    if ($this->hasminarchetype('coursecreator')) { // If a course creator or above.
                        // Just remove the tags.
                        $replace['/\{ifmincreator\}/i'] = '';
                        $replace['/\{\/ifmincreator\}/i'] = '';
                    } else {
                        // Remove the iscreator strings.
                        $replace['/\{ifmincreator\}(.*)\{\/ifmincreator\}/isuU'] = '';
                    }
                }

                // Tag: {ifminmanager}...{/ifminmanager}.
                // Description: Display content only if user has the role of a manager or higher.
                // Parameters: None.
                // Requires content between tags.
                if (stripos($text, '{ifminmanager}') !== false) {
                    if ($this->hasminarchetype('manager')) { // If a manager or above.
                        // Just remove the tags.
                        $replace['/\{ifminmanager\}/i'] = '';
                        $replace['/\{\/ifminmanager\}/i'] = '';
                    } else {
                        // Remove the ifminmanager strings.
                        $replace['/\{ifminmanager\}(.*)\{\/ifminmanager\}/isuU'] = '';
                    }
                }

                // Tag: {ifminsitemanager}...{/ifminsitemanager}.
                // Description: Display content if user has the role of a site manager (not just course/category manager) or admin.
                // Parameters: None.
                // Requires content between tags.
                if (stripos($text, '{ifminsitemanager}') !== false) {
                    static $issitemanager;
                    // If a manager or above.
                    if (!isset($issitemanager) && $issitemanager = $this->hasminarchetype('manager')) {
                        if (!is_siteadmin()) {
                            // Is at least a manager, but a site manager? Let's see.
                            $syscontext = \context_system::instance();
                            $role = $DB->get_record('role', ['shortname' => 'manager'], '*', MUST_EXIST);
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
                        $replace['/\{ifminsitemanager\}(.*)\{\/ifminsitemanager\}/isuU'] = '';
                    }
                }
            }
        }

        // Tag: {filtercodes}.
        // Description: Show version of FilterCodes, but only if you have permission to add the tag.
        // Parameters: None.
        if (stripos($text, '{filtercodes}') !== false) {
            // If you have the ability to edit the content.
            if (has_capability('moodle/course:update', $PAGE->context)) {
                // Show the version of the FilterCodes plugin.
                $plugin = new \stdClass();
                require($CFG->dirroot . '/filter/filtercodes/version.php');
                $replace['/\{filtercodes\}/i'] = "$plugin->release ($plugin->version)";
            } else {
                $replace['/\{filtercodes\}/i'] = '';
            }
        }

        // Tag: {chart <type> <value> <title>}
        // Description: Easily display a chart in one of several styles.
        // Required Parameters: type=radial|pie|progressbar|progresspie, value=0-100, title=Title of the chart.
        if ($CFG->branch >= 32 && version_compare(PHP_VERSION, '7.0.0') >= 0 && stripos($text, '{chart ') !== false) {
            global $OUTPUT;
            preg_match_all('/\{chart\s(\w+)\s([0-9]+)((?:\s)(.*))?\}/isuU', $text, $matches, PREG_SET_ORDER);
            $matches = array_unique($matches, SORT_REGULAR);
            foreach ($matches as $match) {
                $type = $match[1]; // Chart type: radial, pie, progressbar or progresspie.
                $value = $match[2]; // Value between 0 and 100.
                $match[3] = $match[3] == null ? '' : $match[3];
                $title = trim($match[3]); // Optional text label.
                $percent = get_string('percents', '', $value);
                switch ($type) { // Type of chart.
                    case 'radial': // Tag: {chart radial 99 Label to be displayed} - Display a radial (circle) chart.
                        $chart = new \core\chart_pie();
                        $chart->set_doughnut(true); // Calling set_doughnut(true) we display the chart as a doughnut.
                        if (!empty($title)) {
                            $chart->set_title($title);
                        }
                        $series = new \core\chart_series('Percentage', [min($value, 100), 100 - min($value, 100)]);
                        $chart->add_series($series);
                        $chart->set_labels(['Completed', 'Remaining']);
                        if ($CFG->branch >= 39) {
                            $chart->set_legend_options(['display' => false]);  // Hide chart legend.
                        }
                        $html = '<div class="fc-chart-pie">' . $OUTPUT->render_chart($chart, false) . '</div>';
                        break;
                    case 'pie': // Tag: {chart pie 99 Label to be displayed} - Display a pie chart.
                        $chart = new \core\chart_pie();
                        $chart->set_doughnut(false); // Calling set_doughnut(true) we display the chart as a doughnut.
                        if (!empty($title)) {
                            $chart->set_title($title);
                        }
                        $series = new \core\chart_series('Percentage', [min($value, 100), 100 - min($value, 100)]);
                        $chart->add_series($series);
                        $chart->set_labels(['Completed', 'Remaining']);
                        if ($CFG->branch >= 39) {
                            $chart->set_legend_options(['display' => false]);  // Hide chart legend.
                        }
                        $html = '<div class="fc-chart-pie">' . $OUTPUT->render_chart($chart, false) . '</div>';
                        break;
                    case 'progressbar': // Tag: {chart progressbar 99 Label to be displayed} - Display a horizontal progress bar.
                        $html = '
                        <div class="progress mb-0">
                            <div class="fc-progress progress-bar bar" role="progressbar" aria-valuenow="' . $value
                                . '" style="width: ' . $value . '%" aria-valuemin="0" aria-valuemax="100">
                            </div>
                        </div>';
                        if (!empty($title)) {
                            $html .= '<div class="small">' . get_string(
                                'chartprogressbarlabel',
                                'filter_filtercodes',
                                ['label' => $title, 'value' => $percent]
                            ) . '</div>';
                        }
                        break;
                    case 'progresspie': // Tag: {chart progresspie 99 Label to display} - Display a progress pie.
                        $styles = '--percent:' . $value . ';';
                        $params = explode(' --', ' ' . $title);
                        $title = '';
                        foreach ($params as $param) {
                            if (in_array(strtolower(strtok($param, ':')), ['color', 'size', 'border', 'bgcolor'])) {
                                $styles .= '--' . $param . ';';
                            } else if (stripos($param, 'title:') === 0) {
                                $title = substr($param, 6);
                            }
                        }
                        $html = '<div class="fc-progress-pie" style="' . $styles . '">' . $percent . '</div>';
                        if (!empty($title)) {
                            $html .= '<div class="small">' . $title . '</div>';
                        }
                        break;
                    default:
                        $html = '';
                }
                $replace['/\{chart ' . $type . ' ' . $value . preg_quote($match[3]) . '\}/isuU'] = $html;
                $newtext = preg_replace(array_keys($replace), array_values($replace), $text);
                if (!is_null($newtext)) {
                    $text = $newtext;
                }
            }
            unset($chart, $matches, $html, $value, $title);
        }

        // Tag: {alert stylename}...{/alert}.
        // Description: Wraps content between the tags into a Bootstrap Alert box.
        // Optional Parameters: Stylenames: primary|secondary|success|danger|warning|info|light|dark. Default is 'warning'.
        // Requires content between tags.
        // Note: Support of styles is theme dependant. Not all themes support these styles and some will support other styles.
        if (stripos($text, '{/alert}') !== false) {
            $newtext = preg_replace_callback(
                '/\{alert(\s\w*)?\}(.*)\{\/alert\}/isuU',
                function ($matches) {
                    // If alert <style> parameter is not included, default to alert-warning.
                    $matches[1] = trim($matches[1]);
                    $matches[1] = empty($matches[1]) || $matches[1] == 'border' ? 'border' : 'alert-' . $matches[1];
                    return '<div class="alert ' . $matches[1] . '" role="alert"><p>' . $matches[2] . '</p></div>';
                },
                $text
            );
            if ($newtext !== false) {
                $text = $newtext;
            }
        }

        // Tag: {label stylename}{/label}.
        // Description: Wraps content between the tags into a Bootstrap inline label box.
        // Optional Parameters: Style names: default|primary|success|info|danger|warning. Default is 'info'.
        // Requires content between tags.
        // Note: Support of styles is theme dependant. Not all themes support these styles and some will support other styles.
        if (stripos($text, '{/label}') !== false) {
            $newtext = preg_replace_callback(
                '/\{label(\s\w*)?\}(.*)\{\/label\}/isuU',
                function ($matches) {
                    // If alert <style> parameter is not included, default to alert-info.
                    $matches[1] = trim($matches[1]);
                    $matches[1] = empty($matches[1]) ? 'info' : $matches[1];
                    return '<span class="label label-' . $matches[1] . '">' . $matches[2] . '</span>';
                },
                $text
            );
            if ($newtext !== false) {
                $text = $newtext;
            }
        }

        // Tag: {help}...{/help}.
        // Description: Creates a Moodle (?) icon that displays a help bubble when clicked.
        // Requires content between tags.
        // Parameters: None.
        if (stripos($text, '{/help}') !== false) {
            static $help;
            static $helpwrapper = [];
            if (!isset($help)) {
                $help = get_string('help');
                if ($CFG->branch >= 500) {
                    $helpwrapper[0] = '<a class="btn btn-link p-0" role="button" data-bs-container="body" data-bs-toggle="popover"'
                            . ' data-bs-placement="right" data-bs-content="<div class=&quot;no-overflow&quot;><p>';
                    $helpwrapper[1] = '</p></div>" data-bs-html="true" tabindex="0" data-bs-trigger="focus"><i class="icon'
                            . ' fa fa-circle-question text-info fa-fw " title="' . $help . '" aria-label="' . $help . '"></i></a>';
                } else {
                    $helpwrapper[0] = '<a class="btn btn-link p-0" role="button" data-container="body" data-toggle="popover"'
                        . ' data-placement="right" data-content="<div class=&quot;no-overflow&quot;><p>';
                    $helpwrapper[1] = '</p></div>" data-html="true" tabindex="0" data-trigger="focus"><i class="icon'
                        . ' fa fa-question-circle text-info fa-fw " title="' . $help . '" aria-label="' . $help . '"></i></a>';
                }
            }

            $newtext = preg_replace_callback(
                '/\{help\}(.*)\{\/help\}/isuU',
                function ($matches) use ($helpwrapper) {
                    return $helpwrapper[0] . htmlspecialchars($matches[1], ENT_COMPAT) . $helpwrapper[1];
                },
                $text
            );
            if ($newtext !== false) {
                $text = $newtext;
            }
        }

        // Tag: {info}...{/info}.
        // Description: Creates a Moodle (!) icon that displays an information bubble when clicked.
        // Parameters: None.
        // Requires content between tags.
        if (stripos($text, '{/info}') !== false) {
            static $info;
            static $infowrapper = [];
            if (!isset($info)) {
                $info = get_string('info');
                if ($CFG->branch >= 500) {
                    $infowrapper[0] = '<a class="btn btn-link p-0" role="button" data-bs-container="body" data-bs-toggle="popover"'
                        . ' data-bs-placement="right" data-bs-content="<div class=&quot;no-overflow&quot;><p>';
                    $infowrapper[1] = '</p></div>" data-bs-html="true" tabindex="0" data-bs-trigger="focus"><i class="icon'
                        . ' fa fa-circle-info text-info fa-fw " title="' . $info . '" aria-label="' . $info . '"></i></a>';
                } else {
                    $infowrapper[0] = '<a class="btn btn-link p-0" role="button" data-container="body" data-toggle="popover"'
                        . ' data-placement="right" data-content="<div class=&quot;no-overflow&quot;><p>';
                    $infowrapper[1] = '</p></div>" data-html="true" tabindex="0" data-trigger="focus"><i class="icon'
                        . ' fa fa-info-circle text-info fa-fw " title="' . $info . '" aria-label="' . $info . '"></i></a>';
                }
            }
            $newtext = preg_replace_callback(
                '/\{info\}(.*)\{\/info\}/isuU',
                function ($matches) use ($infowrapper) {
                    return $infowrapper[0] . htmlspecialchars($matches[1], ENT_COMPAT) . $infowrapper[1];
                },
                $text
            );
            if ($newtext !== false) {
                $text = $newtext;
            }
        }

        /* ---------------- Apply all of the filtercodes so far. ---------------*/

        if ($this->replacetags($text, $replace) == false) {
            // No more tags? Put back the escaped tags, if any, and return the string.
            $text = $this->escapedtags($text);
            self::$infiltercodes = false;
            return $text;
        }

        // Tag: {urlencode}...{/urlencode}.
        // Description: URL Encodes the content between the tags for use as a parameter of a URL.
        // Parameters: None.
        // Requires content between tags.
        if (stripos($text, '{urlencode}') !== false) {
            // Replace {urlencode} tags and content with encoded content.
            $newtext = preg_replace_callback(
                '/\{urlencode\}(.*)\{\/urlencode\}/isuU',
                function ($matches) {
                    return urlencode($matches[1]);
                },
                $text
            );
            if ($newtext !== false) {
                $text = $newtext;
            }
        }

        // Tag: {rawurlencode}...{/rawurlencode}.
        // Description: URL Encodes the content between the tags for use as a parameter of a URL in RFC 3986.
        // Parameters: None.
        // Requires content between tags.
        if (stripos($text, '{rawurlencode}') !== false) {
            // Replace {urlencode} tags and content with encoded content.
            $newtext = preg_replace_callback(
                '/\{rawurlencode\}(.*)\{\/rawurlencode\}/isuU',
                function ($matches) {
                    return rawurlencode($matches[1]);
                },
                $text
            );
            if ($newtext !== false) {
                $text = $newtext;
            }
        }

        // Tag: {qrcode}...{/qrcode}.
        // Description: Encodes the content between the tags into a an HTML image tag containing a QR Code of the content.
        // Parameters: None.
        // Requires content between tags.
        if (stripos($text, '{qrcode}') !== false) {
            // Remove {qrcode}{/qrcode} tags and turn content between the tags into a QR code.
            $newtext = preg_replace_callback(
                '/\{qrcode\}(.*)\{\/qrcode\}/isuU',
                function ($matches) {
                    $text = html_to_text($matches[1]);
                    $src = $this->qrcode($text);
                    $src = '<img src="' . $src . '" style="width:100%;max-width:480px;height:auto;" class="fc-qrcode" alt="'
                        . $text . '">';
                    return $src;
                },
                $text
            );
            if ($newtext !== false) {
                $text = $newtext;
            }
        }

        // Tag: {button URL}...{/button}.
        // Description: Creates a button that displays the content and links to the specified URL.
        // Required Parameter: URL. You also need to specify the content which will become the text in the button.
        // Requires content between tags.
        if (stripos($text, '{button ') !== false) {
            $newtext = preg_replace_callback(
                '/\{button\s+(.*)\}(.*)\{\/button\}/isuU',
                function ($matches) {
                    // Remove HTML tags created by filters like Activity Name Auto-Linking and Convert URLs Into Links.
                    $url = strip_tags($matches[1]);
                    if (strpos($url, '&amp;') === 0) {
                        $url = s($url);
                    }
                    $label = $matches[2];
                    return '<a href="' . $url . '" class="btn btn-primary">' . $label . '</a>';
                },
                $text
            );
            if ($newtext !== false) {
                $text = $newtext;
            }
        }

        /* ---------------- Apply the rest of the FilterCodes tags. ---------------*/

        $this->replacetags($text, $replace);
        // Put back the escaped tags.
        $text = $this->escapedtags($text);
        self::$infiltercodes = false;
        return $text;
    }
}
