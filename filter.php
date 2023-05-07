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
 * @copyright  2017-2023 TNG Consulting Inc. - www.tngconsulting.ca
 * @author     Michael Milette
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use block_online_users\fetcher;
use core_table\local\filter\integer_filter;
use core_user\table\participants_filterset;
use core_user\table\participants_search;
use Endroid\QrCode\QrCode;

require_once($CFG->dirroot . '/course/renderer.php');

/**
 * Extends the moodle_text_filter class to provide plain text support for new tags.
 *
 * @copyright  2017-2023 TNG Consulting Inc. - www.tngconsulting.ca
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
            if (isloggedin() && !isguestuser()) {
                // We only track logged-in roles.
                global $DB;
                // Retrieve list of role names.
                $rolenames = $DB->get_records('role');
                // Retrieve list of my roles across all contexts.
                $userroles = $DB->get_records('role_assignments', array('userid' => $userid));
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
        return new moodle_url($url);
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
     * Determine if access is from a web service.
     *
     * @return boolean true if a web service, false if web browser.
     */
    private function iswebservice() {
        global $ME;
        // If this is a web service or the Moodle mobile app...
        $isws = (WS_SERVER || (strpos($ME, "webservice/") !== false && optional_param('token', '', PARAM_ALPHANUM)));
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

        if ($categoryshowpic) {
            $imgurl = $OUTPUT->get_generated_image_for_id($category->id + 65535);
            $html = '<li class="card shadow mr-4 mb-4 ml-0" style="min-width:290px;max-width:290px;' . $dimmed . '">
                    <a href="' . new moodle_url('/course/index.php', ['categoryid' => $category->id]);
            $html .= '" class="text-white h-100">
                    <div class="card-img" style="background-image: url(' . $imgurl . ');height:100px;"></div>
                    <div class="card-img-overlay card-title pt-1 pr-3 pb-1 pl-3 m-0" '
                        . 'style="height:fit-content;top:auto;background-color:rgba(0,0,0,.4);color:#ffffff;'
                        . 'text-shadow:-1px -1px 0 #767676, 1px -1px 0 #767676, -1px 1px 0 #767676, 1px 1px 0 #767676">'
                        . $category->name . '</div>';
        } else {
            $html = '<li class="card shadow mr-4 mb-4 ml-0 fc-categorycard-' . $category->id .
                    '" style="min-width:350px;max-width:350px;' . $dimmed . '">' .
                    '<a href="' . new moodle_url('/course/index.php', ['categoryid' => $category->id]);
            $html .= '" class="text-decoration-none h-100 p-4">' . $category->name;
        }
        $html .= '</a></li>' . PHP_EOL;
        return $html;
    }

    /**
     * Render course cards for list of course ids.
     *
     * @param array $rcourseids Array of course ids.
     * @return string HTML of course cars.
     */
    private function rendercoursecards($rcourseids) {
        global $OUTPUT, $PAGE;
        $content = '';
        $isadmin = (is_siteadmin() && !is_role_switched($PAGE->course->id));
        foreach ($rcourseids as $courseid) {
            $course = get_course($courseid);
            // Skip this course if end-date is past or course is not visible, unless you are an admin.
            if (!$isadmin && !empty($course->enddate) && $course->enddate < time() && empty($course->visible)) {
                continue;
            }

            // Load image from course image. If none, generate a course image based on the course ID.
            $context = context_course::instance($courseid);
            if ($course instanceof stdClass) {
                $course = new \core_course_list_element($course);
            }
            $coursefiles = $course->get_course_overviewfiles();
            $imgurl = '';
            foreach ($coursefiles as $file) {
                if ($isimage = $file->is_valid_image()) {
                    // The file_encode_url() function is deprecated as per MDL-31071 but still in wide use.
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
            $courseurl = new moodle_url('/course/view.php', ['id' => $courseid]);
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
        return $content;
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
                $link = '<a href="mailto:'. $user->email . '">'  . $name . '</a>';
                break;
            case 'message':
                $link = '<a href="' . new moodle_url('/message/index.php', ['id' => $user->id]) . '">' . $name . '</a>';
                break;
            case 'profile':
                $link = '<a href="' . new moodle_url('/user/profile.php', ['id' => $user->id]) . '">' . $name . '</a>';
                break;
            case 'phone1' && !empty($user->phone1):
                $link = '<a href="tel:' . $user->phone1 . '">' . $name . '</a>';
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
            if ($course->enablecompletion == 1
                    && isloggedin()
                    && !isguestuser()
                    && context_system::instance() != 'page-site-index') {
                $progresspercent = (int) \core_completion\progress::get_course_progress_percentage($course);
            }
        }

        return $progresspercent;
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

        // This tag: {form...}.
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

        // Tag: {global_[custom]}. Global Custom tags as defined in plugin settings.
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
                    $replace['/\{global_' . $tag . '\}/i'] = $content;
                }
            }
            unset($i);
            unset($globaltagcount);
            unset($tag);
            unset($content);
        }

        // Tag: {coursesummary}.
        if (stripos($text, '{coursesummary') !== false) {
            if (stripos($text, '{coursesummary}') !== false) {
                // No course ID specified.
                $coursecontext = context_course::instance($PAGE->course->id);
                $replace['/\{coursesummary\}/i'] = format_text($PAGE->course->summary, FORMAT_HTML,
                        ['context' => $coursecontext]);
            }
            if (stripos($text, '{coursesummary ') !== false) {
                // Course ID was specified.
                preg_match_all('/\{coursesummary ([0-9]+)\}/', $text, $matches);
                // Eliminate course IDs.
                $courseids = array_unique($matches[1]);
                $coursecontext = context_course::instance($PAGE->course->id);
                foreach ($courseids as $id) {
                    $course = $DB->get_record('course', ['id' => $id]);
                    if (!empty($course)) {
                        $replace['/\{coursesummary ' . $course->id . '\}/isuU'] = format_text($course->summary, FORMAT_HTML,
                                ['context' => $coursecontext]);;
                    }
                }
                unset($matches, $course, $courseids, $id);
            }
        }

        // This tag: {menuadmin}.
        if (stripos($text, '{menuadmin}') !== false) {
            $theme = $PAGE->theme->name;
            $menu = '';
            if ($this->hasminarchetype('editingteacher')) {
                $menu .= '{fa fa-wrench} {getstring}admin{/getstring}' . PHP_EOL;
            }
            if ($this->hasminarchetype('coursecreator')) { // If a course creator or above.
                $menu .= '-{getstring}administrationsite{/getstring}|/admin/search.php' . PHP_EOL;
                $menu .= '-{toggleeditingmenu}' . PHP_EOL;
                $menu .= '-Moodle Academy|https://moodle.academy/' . PHP_EOL;
                $menu .= '-###' . PHP_EOL;
            }
            if ($this->hasminarchetype('manager')) { // If a manager or above.
                $menu .= '-{getstring}user{/getstring}: {getstring:admin}usermanagement{/getstring}|/admin/user.php' . PHP_EOL;
                $menu .= '{ifminsitemanager}' . PHP_EOL;
                $menu .= '-{getstring}user{/getstring}: {getstring:mnet}profilefields{/getstring}|/user/profile/index.php' .
                        PHP_EOL;
                $menu .= '-###' . PHP_EOL;
                $menu .= '{/ifminsitemanager}' . PHP_EOL;
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
                $menu .= '-{getstring}course{/getstring}: {getstring}participants{/getstring}|/user/index.php?id={courseid}' .
                        PHP_EOL;
                $menu .= '-{getstring}course{/getstring}: {getstring:badges}badges{/getstring}|/badges/index.php' .
                        '?type={courseid}' . PHP_EOL;
                $menu .= '-{getstring}course{/getstring}: {getstring}reports{/getstring}|/course/admin.php' .
                        '?courseid={courseid}#linkcoursereports' . PHP_EOL;
                $menu .= '-{getstring}course{/getstring}: {getstring:enrol}enrolmentinstances{/getstring}|/enrol/instances.php' .
                        '?id={courseid}' . PHP_EOL;
                $menu .= '-{getstring}course{/getstring}: {getstring}reset{/getstring}|/course/reset.php?id={courseid}' . PHP_EOL;
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
                $menu .= '-{getstring}site{/getstring}: {getstring:admin}themesettings{/getstring}|/admin/settings.php' .
                        '?section=themesettings|Including custom menus, designer mode, theme in URL' . PHP_EOL;
                if (file_exists($CFG->dirroot . '/theme/' . $theme . '/settings.php')) {
                    $menu .= '-{getstring}site{/getstring}: {getstring:admin}currenttheme{/getstring}|/admin/settings.php' .
                            '?section=themesetting' . $theme . PHP_EOL;
                }
                $menu .= '-{getstring}site{/getstring}: {getstring}notifications{/getstring} ({getstring}admin{/getstring})' .
                        '|/admin/index.php' . PHP_EOL;
            }
            $replace['/\{menuadmin\}/i'] = $menu;
        }

        // This tag: {menudev}.
        if (stripos($text, '{menudev}') !== false) {
            $menu = '';
            if (is_siteadmin() && !is_role_switched($PAGE->course->id)) { // If a site administrator.
                $menu .= '-{getstring:tool_installaddon}installaddons{/getstring}|/admin/tool/installaddon' . PHP_EOL;
                $menu .= '-###' . PHP_EOL;
                $menu .= '-{getstring:admin}debugging{/getstring}|/admin/settings.php?section=debugging' . PHP_EOL;
                $menu .= '-{getstring:admin}purgecachespage{/getstring}|/admin/purgecaches.php' . PHP_EOL;
                $menu .= '-###' . PHP_EOL;
                if (file_exists(dirname(__FILE__) . '/../../local/adminer/index.php')) {
                    $menu .= '-{getstring:local_adminer}pluginname{/getstring}|/local/adminer' . PHP_EOL;
                }
                if (file_exists(dirname(__FILE__) . '/../../local/codechecker/index.php')) {
                    $menu .= '-{getstring:local_codechecker}pluginname{/getstring}|/local/codechecker' . PHP_EOL;
                }
                if (file_exists(dirname(__FILE__) . '/../../local/moodlecheck/index.php')) {
                    $menu .= '-{getstring:local_moodlecheck}pluginname{/getstring}|/local/moodlecheck' . PHP_EOL;
                }
                if (file_exists(dirname(__FILE__) . '/../../admin/tool/pluginskel/index.php')) {
                    $menu .= '-{getstring:tool_pluginskel}pluginname{/getstring}|/admin/tool/pluginskel' . PHP_EOL;
                }
                if (file_exists(dirname(__FILE__) . '/../../local/tinyfilemanager/index.php')) {
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
                $menu .= '-Moodle Dev Academy|https://moodle.academy/course/index.php?categoryid=4|{getstring}english{/getstring}' .
                        PHP_EOL;
            }
            $replace['/\{menudev\}/i'] = $menu;
        }

        // This tag: {teamcards}.
        if (stripos($text, '{teamcards}') !== false) {
            global $DB, $OUTPUT;

            $sql = 'SELECT DISTINCT u.id, u.username, u.firstname, u.lastname, u.email, u.picture, u.imagealt, u.firstnamephonetic,
                    u.lastnamephonetic, u.middlename, u.alternatename, u.description, u.phone1
                    FROM {course} c, {role_assignments} ra, {user} u, {context} ct
                    WHERE c.id = ct.instanceid AND ra.roleid = 3 AND ra.userid = u.id AND ct.id = ra.contextid
                        AND u.suspended = 0 AND u.deleted = 0
                    ORDER BY u.lastname desc, u.firstname';
            $users = $DB->get_records_sql($sql);

            $cards = '';
            if (count($users)) {
                $clinktype = get_config('filter_filtercodes', 'teamcardslinktype');
                $cardformat = get_config('filter_filtercodes', 'teamcardsformat');
                $narrowpage = get_config('filter_filtercodes', 'narrowpage');

                switch($cardformat) { // Show as info icon.
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
                $linksr = ['' => '',
                        'email' => get_string('issueremail', 'badges'),
                        'message' => get_string('message', 'message'),
                        'profile' => get_string('profile'),
                        'phone' => get_string('phone')
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
                            $cards .= $OUTPUT->user_picture($user, ['size' => '150',
                                    'class' => 'img-fluid pull-left p-1 border mr-4',
                                    'link' => false, 'visibletoscreenreaders' => false]);
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
                            $cards .= $OUTPUT->user_picture($user, ['size' => '250', 'class' => 'img-fluid',
                                    'link' => false, 'visibletoscreenreaders' => false]);
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

        // Tag: {lang}.
        if (stripos($text, '{lang}') !== false) {
            // Replace with 2-letter current primary language.
            $replace['/\{lang\}/i'] = substr(current_language(), 0, 2);
        }

        // Tag: {idnumber}.
        if (stripos($text, '{idnumber}') !== false) {
            $replace['/\{idnumber\}/i'] = isloggedin() && !isguestuser() ? $USER->idnumber : '';
        }

        // Tag: {coursegradepercent} - Calculate and display current overall course grade as a percentage.
        if (stripos($text, '{coursegradepercent}') !== false) {
            require_once($CFG->libdir . '/gradelib.php');
            require_once($CFG->dirroot . '/grade/querylib.php');
            $gradeobj = grade_get_course_grade($USER->id, $PAGE->course->id);
            if (!empty($grademax = floatval($gradeobj->item->grademax))) {
                // Avoid divide by 0 error if no grades have been defined.
                $grade = (int) ($gradeobj->grade / floatval($grademax) * 100) ?? 0;
            } else {
                $grade = 0;
            }
            $replace['/\{coursegradepercent\}/i'] = $grade;
        }

        // Course completion progress percentage as a number.
        if (stripos($text, '{courseprogresspercent}') !== false) {
            $progress = $this->completionprogress();
            if ($progress != -1) { // Is enabled.
                $replace['/\{courseprogresspercent\}/i'] = $progress;
            } else {
                $replace['/\{courseprogresspercent\}/i'] = '';
            }
            unset($progress);
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

        // Social field migrated from pre-Moodle 3.11 - for backwards compatibility.
        if (stripos($text, '{webpage}') !== false) {
            if ($CFG->branch >= 311) {
                $text = str_replace('{webpage}', '{profile_field_webpage}', $text);
            } else {
                $replace['/\{webpage\}/i'] = isloggedin() && !isguestuser() ? $USER->url : '';
            }
        }

        if (stripos($text, '{profile') !== false) {

            // Tag: {profile_field_...}.
            // Custom Profile Fields.
            if (stripos($text, '{profile_field') !== false) {
                $isuser = (isloggedin() && !isguestuser());
                // Cached the defined custom profile fields and data.
                static $profilefields;
                static $profiledata;
                if (!isset($profilefields)) {
                    $profilefields = $DB->get_records('user_info_field', null, '', 'id, datatype, shortname, visible, param3');
                    if ($isuser && !empty($profilefields)) {
                        $profiledata = $DB->get_records_menu('user_info_data', ['userid' => $USER->id], '', 'fieldid, data');
                    }
                }
                $showhidden = get_config('filter_filtercodes', 'showhiddenprofilefields');
                foreach ($profilefields as $field) {
                    // If the tag exists and is not set to "Not visible" in the custom profile field's settings.
                    if ($isuser
                            && stripos($text, '{profile_field_' . $field->shortname . '}') !== false
                            && ($field->visible != '0' || !empty($showhidden))) {
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
            if (stripos($text, '{profilefullname}') !== false) {
                $fullname = '';
                if (isloggedin() && !isguestuser()) {
                    $fullname = get_string('fullnamedisplay', null, $USER);
                    if ($PAGE->pagelayout == 'mypublic' && $PAGE->pagetype == 'user-profile') {
                        $userid = optional_param('userid', optional_param('user',
                                optional_param('id', $USER->id, PARAM_INT), PARAM_INT), PARAM_INT);
                        if ($user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0])) {
                            $fullname = get_string('fullnamedisplay', null, $user);
                        }
                    }
                }
                $replace['/\{profilefullname\}/i'] = $fullname;
                unset($fullname);
            }
        }

        // Substitutions.

        $u = $USER;
        if (!isloggedin() || isguestuser()) {
            $u->firstname = get_string('defaultfirstname', 'filter_filtercodes');
            $u->lastname = get_string('defaultsurname', 'filter_filtercodes');
        }
        $u->fullname = trim(get_string('fullnamedisplay', null, $u));

        // Tag: {firstname}.
        if (stripos($text, '{firstname}') !== false) {
            $replace['/\{firstname\}/i'] = $u->firstname;
        }

        // Tag: {surname}.
        if (stripos($text, '{surname}') !== false) {
            $replace['/\{surname\}/i'] = $u->lastname;
        }

        // Tag: {lastname} (same as surname... just easier to remember).
        if (stripos($text, '{lastname}') !== false) {
            $replace['/\{lastname\}/i'] = $u->lastname;
        }

        // Tag: {fullname}.
        if (stripos($text, '{fullname}') !== false) {
            $replace['/\{fullname\}/i'] = $u->fullname;
        }

        // Tag: {alternatename}.
        if (stripos($text, '{alternatename}') !== false) {
            // If alternate name is empty, use firstname instead.
            if (isloggedin() && !isguestuser() && !empty(trim($USER->alternatename))) {
                $replace['/\{alternatename\}/i'] = $USER->alternatename;
            } else {
                $replace['/\{alternatename\}/i'] = $u->firstname;
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
                $string = [];
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

        // Tag: {department}.
        if (stripos($text, '{department}') !== false) {
            $replace['/\{department\}/i'] = isloggedin() && !isguestuser() ? $USER->department : '';
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
                    $newtext = preg_replace_callback('/\{firstaccessdate\s+(.+)\}/isuU',
                        function ($matches) use ($USER) {
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

        // Tag: {lastlogin} or {lastlogin dateTimeFormat}.
        if (stripos($text, '{lastlogin') !== false) {
            if (isloggedin() && !isguestuser() && !empty($USER->lastlogin)) {
                // Replace {lastlogin} tag with formatted date.
                if (stripos($text, '{lastlogin}') !== false) {
                    $replace['/\{lastlogin\}/i'] = userdate($USER->lastlogin, get_string('strftimedatetimeshort'));
                }
                // Replace {lastlogin dateTimeFormat} tag and parameters with formatted date.
                if (stripos($text, '{lastlogin ') !== false) {
                    $newtext = preg_replace_callback('/\{lastlogin\s+(.+)\}/isuU',
                        function ($matches) use ($USER) {
                            // Check if this is a built-in Moodle date/time format.
                            if (get_string_manager()->string_exists($matches[1], 'langconfig')) {
                                // It is! Get the strftime string.
                                $matches[1] = get_string($matches[1], 'langconfig');
                            }
                            return userdate($USER->lastlogin, $matches[1]);
                        },
                        $text
                    );
                    if ($newtext !== false) {
                        $text = $newtext;
                        $changed = true;
                    }
                }
            } else {
                $replace['/\{lastlogin(.*)\}/i'] = get_string('never');
            }
        }

        // Tags starting with: {support...}.
        if (stripos($text, '{support') !== false) {
            // Tag: {supportname}.
            if (stripos($text, '{supportname}') !== false) {
                $replace['/\{supportname\}/i'] = $CFG->supportname;
            }

            // Tag: {supportemail}.
            if (stripos($text, '{supportemail}') !== false) {
                $replace['/\{supportemail\}/i'] = $CFG->supportemail;
            }

            // Tag: {supportpage}.
            if (stripos($text, '{supportpage}') !== false) {
                $replace['/\{supportpage\}/i'] = $CFG->supportpage;
            }
        }

        if (get_config('filter_filtercodes', 'enable_scrape')) { // Must be enabled in FilterCodes settings.
            // Tag: {scrape url="" tag="" class="" id="" code=""}.
            if (stripos($text, '{scrape ') !== false) {
                // Replace {scrape} tag and its attributes with retrieved content.
                $newtext = preg_replace_callback('/\{scrape\s+(.*)\}/isuU',
                    function ($matches) {
                        // Parse the scrape tag's atributes.
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

            // These tags: {userpictureurl} and {userpictureimg}.
            if (stripos($text, '{userpicture') !== false) {
                // Tag: {userpictureurl size}. User photo URL.
                // Sizes: 2 or sm (small), 1 or md (medium), 3 or lg (large).
                if (stripos($text, '{userpictureurl ') !== false) {
                    $url = $this->getprofilepictureurl($USER);
                    // Substitute the $1 in URL with value of (\w+), making sure to substitute text versions into numbers.
                    $newtext = preg_replace_callback('/\{userpictureurl\s+(\w+)\}/isuU',
                        function ($matches) {
                            $sublist = ['sm' => '2', '2' => '2', 'md' => '1', '1' => '1', 'lg' => '3', '3' => '3'];
                            return '{userpictureurl ' . $sublist[$matches[1]] . '}';
                        }, $text);
                    if ($newtext !== false) {
                        $text = $newtext;
                        $changed = true;
                    }
                    $replace['/\{userpictureurl\s+(\w+)\}/isuU'] = $url;
                }

                // Tag: {userpictureimg size}. User photo URL wrapped in HTML image tag.
                // Sizes: 2 or sm (small), 1 or md (medium), 3 or lg (large).
                if (stripos($text, '{userpictureimg ') !== false) {
                    $url = $this->getprofilepictureurl($USER);
                    $tag = '<img src="' . $url . '" alt="' . $u->fullname . '" class="userpicture">';
                    // Will substitute the $1 in URL with value of (\w+).
                    $newtext = preg_replace_callback('/\{userpictureimg\s+(\w+)\}/isuU',
                        function ($matches) {
                            $sublist = ['sm' => '2', '2' => '2', 'md' => '1', '1' => '1', 'lg' => '3', '3' => '3'];
                            return '{userpictureimg ' . $sublist[$matches[1]] . '}';
                        }, $text);
                    if ($newtext !== false) {
                        $text = $newtext;
                        $changed = true;
                    }
                    $replace['/\{userpictureimg\s+(\w+)\}/isuU'] = $tag;
                }
            }

            // Tag: {userdescription}.
            if (stripos($text, '{userdescription}') !== false) {
                if (isloggedin() && !isguestuser()) {
                    $user = $DB->get_record('user', ['id' => $USER->id], 'description', MUST_EXIST);
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
                $cnt = $DB->count_records('user', ['deleted' => 0]) - 2;
                $replace['/\{usercount\}/i'] = $cnt;
            }

            // Tag: {usersactive}.
            if (stripos($text, '{usersactive}') !== false) {
                // Count total number of current users on the site.
                // Exclude deleted, suspended and unconfirmed users, admin and guest.
                $cnt = $DB->count_records('user', ['deleted' => 0, 'suspended' => 0, 'confirmed' => 1]) - 2;
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

            // Tag: {userscountrycount}.
            if (stripos($text, '{userscountrycount}') !== false) {
                $count = $DB->count_records_sql('SELECT COUNT(DISTINCT country) FROM {user} WHERE id > 2');
                $replace['/\{userscountrycount\}/i'] = $count;
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
                    $coursecontext = context_course::instance($PAGE->course->id);
                    foreach ($coursefields as $field => $value) {
                        $shortname = strtolower($field);
                        // If the tag exists and it is not hidden in the custom course field's settings.
                        if (stripos($text, '{course_field_' . $shortname . '}') !== false) {
                            $replace['/\{course_field_' . $shortname . '\}/i'] = format_text($value, FORMAT_HTML,
                                    ['context' => $coursecontext]);
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
                    $coursecontext = context_course::instance($PAGE->course->id);
                    $replace['/\{course_fields\}/i'] = format_text($customfields, FORMAT_HTML, ['context' => $coursecontext]);
                }

            }

            // Tag: {coursecontacts}.
            if (stripos($text, '{coursecontacts') !== false) {
                $contacts = '';
                // If course (not site pages) with contacts.
                if ($PAGE->course->id) {

                    $course = new core_course_list_element($PAGE->course);
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
                                'phone' => get_string('phone')];
                        $iconclass = ['' => '',
                                'email' => 'fa fa-envelope-o',
                                'message' => 'fa fa-comment-o',
                                'profile' => 'fa fa-user-o',
                                'phone' => 'fa fa-mobile'];

                        $cnt = 0;
                        foreach ($course->get_course_contacts() as $coursecontact) {
                            $icon = '<i class="' . $iconclass[$clinktype] . '" aria-hidden="true"></i> ';

                            $contacts .= '<li>';

                            // Get list of course contacts based on settings in Site Administration > Appearances > Courses.
                            // Get liset of user's roles in the course.
                            $rolenames = array_map(function ($role) {
                                return $role->displayname;
                            }, $coursecontact['roles']);

                            // Retrieve contact's profile information.
                            $user = $DB->get_record('user', ['id' => $coursecontact['user']->id],
                                    $fields = '*', $strictness = IGNORE_MULTIPLE);
                            $imgurl = str_replace('$1', '3', $this->getprofilepictureurl($user));
                            $fullname = get_string('fullnamedisplay', null, $user);
                            if ($cshowpic) {
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
                                    $contacts .= $icon . '<a href="' . new moodle_url('/message/index.php',
                                            ['id' => $coursecontact['user']->id]
                                            ) . '">';
                                    $contacts .= $contactsclose;
                                    break;
                                case 'profile':
                                    $contacts .= $icon . '<a href="' . new moodle_url('/user/profile.php',
                                            ['id' => $coursecontact['user']->id, 'course' => $PAGE->course->id]
                                            ) . '">';
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
                unset($contacts, $contactsclose, $fullname, $url, $user, $rolenames, $icon, $iconclass,
                        $linksr, $clinktype, $cshowpic);
            }

            // Tag: {courseparticipantcount}.
            if (stripos($text, '{courseparticipantcount}') !== false) {
                require_once($CFG->dirroot . '/user/lib.php');
                if ($CFG->branch >= 39) {
                    $coursecontext = context_course::instance($PAGE->course->id);
                    $filterset = new participants_filterset();
                    $filterset->add_filter(new integer_filter('courseid', null, [(int) $PAGE->course->id]));
                    $search = new participants_search($PAGE->course, $coursecontext, $filterset);
                    $cnt = $search->get_total_participants_count();
                } else {
                    $cnt = \user_get_total_participants($PAGE->course->id);
                }
                $replace['/\{courseparticipantcount\}/i'] = $cnt;
            }

            // Tag: {coursecount students}.
            if (stripos($text, '{coursecount students}') !== false) {
                if ($CFG->branch >= 32) {
                    $coursecontext = context_course::instance($PAGE->course->id);
                    $role = $DB->get_record('role', array('shortname' => 'student'));
                    $students = get_role_users($role->id, $coursecontext);
                    $cnt = count($students);
                    unset($students);
                } else {
                    $cnt = '';
                }
                $replace['/\{coursecount students\}/i'] = $cnt;
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
            // Alternative Tag:  %7Bcoursecontextid%7D (for encoded URLs).
            if (stripos($text, '%7Bcoursecontextid%7D') !== false) {
                $context = context_course::instance($PAGE->course->id);
                $coursecontextid = isset($PAGE->course->id) ? $context->id : 1;
                $replace['/%7Bcoursecontextid%7D/i'] = $coursecontextid;
            }

            // Tag: %7Bcoursemoduleid%7D (escaped).
            if (stripos($text, '%7Bcoursemoduleid%7D') !== false) {
                $replace['/\%7Bcoursemoduleid%7D/i'] = @$PAGE->cm->id;
            }

            // Tag: {courseidnumber}.
            if (stripos($text, '{courseidnumber}') !== false) {
                $replace['/\{courseidnumber\}/i'] = $PAGE->course->idnumber;
            }

            // Tag: {coursename}. The full name of a course or the site name.
            if (stripos($text, '{coursename') !== false) {
                if (stripos($text, '{coursename}') !== false) {
                    // No course ID was specified.
                    $course = $PAGE->course;
                    if ($course->id == $SITE->id) { // If not in a course, use the site name.
                        $coursecontext = context_system::instance();
                        $replace['/\{coursename\}/i'] = format_string($SITE->fullname, true,
                                ['context' => $coursecontext]);
                    } else { // If in a course - use course full name.
                        $coursecontext = context_course::instance($course->id);
                        $replace['/\{coursename\}/i'] = format_string($course->fullname, true,
                                ['context' => $coursecontext]);
                    }
                }
                if (stripos($text, '{coursename ') !== false) {
                    // Course ID was specified.
                    preg_match_all('/\{coursename ([0-9]+)\}/', $text, $matches);
                    // Eliminate course IDs.
                    $courseids = array_unique($matches[1]);
                    $coursecontext = context_system::instance();
                    foreach ($courseids as $id) {
                        $course = $DB->get_record('course', ['id' => $id]);
                        if (!empty($course)) {
                            $replace['/\{coursename ' . $course->id . '\}/isuU'] = format_string($course->fullname, true,
                                    ['context' => $coursecontext]);
                        }
                    }
                    unset($matches, $course, $courseids, $id);
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
                        $newtext = preg_replace_callback('/\{coursestartdate\s+(.*)\}/isuU',
                            function ($matches) use ($PAGE) {
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
                    $replace['/\{coursestartdate(.*)\}/isuU'] = get_string('notyetstarted', 'completion');
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
                        $newtext = preg_replace_callback('/\{courseenddate\s+(.*)\}/isuU',
                            function ($matches) use ($PAGE) {
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
                    $replace['/\{courseenddate(.*)\}/isuU'] = get_string('none');
                }
            }

            // Tag: {coursecompletiondate} or {coursecompletiondate dateTimeFormat}. The course completion date.
            if (stripos($text, '{coursecompletiondate') !== false) {
                if ($PAGE->course
                        && isset($CFG->enablecompletion)
                        && $CFG->enablecompletion == 1 // COMPLETION_ENABLED.
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
                        $newtext = preg_replace_callback('/\{coursecompletiondate\s+(.+)\}/isuU',
                            function($matches) use ($ccompletion) {
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
                    $replace['/\{coursecompletiondate(.*)\}/isuU'] = $incomplete;
                }
            }

            // Tag: {coursecount}. The total number of courses.
            if (stripos($text, '{coursecount}') !== false) {
                // Count courses excluding front page.
                $cnt = $DB->count_records('course', []) - 1;
                $replace['/\{coursecount\}/i'] = $cnt;
            }

            // Tag: {coursesactive}. Display's the total visible courses.
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

            // Tag: {coursegrade}. Overall grade in a courses.
            if (stripos($text, '{coursegrade}') !== false) {
                require_once($CFG->libdir . '/gradelib.php');
                require_once($CFG->dirroot . '/grade/querylib.php');
                $gradeobj = grade_get_course_grade($USER->id, $PAGE->course->id);
                $grade = 0;
                if (!empty($grademax = floatval($gradeobj->item->grademax))) {
                    $grade = (int)($gradeobj->grade / floatval($grademax) * 100) ?? 0;
                }
                $replace['/\{coursegrade\}/i'] = $grade;
            }

            // Tag: {courseprogress} and {courseprogressbar}.
            // Course progress percentage as text.
            if (stripos($text, '{courseprogress') !== false) {
                $progress = $this->completionprogress();

                // Course completion progress percentage as formatted text.
                if (stripos($text, '{courseprogress}') !== false) {
                    if ($progress != -1) { // Is enabled.
                        $replace['/\{courseprogress\}/i'] = '<span class="sr-only">'
                                . get_string('aria:courseprogress', 'block_myoverview') . '</span> '
                                . get_string('completepercent', 'block_myoverview', $progress);
                    } else {
                        $replace['/\{courseprogress\}/i'] = '';
                    }
                }

                // Course completion progress bar.
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
            // Display courses in a category branch as cards.
            if (stripos($text, '{coursecards') !== false) {
                global $CFG, $OUTPUT;

                $chelper = new coursecat_helper();
                $chelper->set_show_courses(20)->set_courses_display_options([
                    'recursive' => true,
                    'limit' => $CFG->frontpagecourselimit,
                    'viewmoreurl' => new moodle_url('/course/index.php'),
                    'viewmoretext' => new lang_string('fulllistofcourses')
                ]);

                $chelper->set_attributes(['class' => 'frontpage-course-list-all']);
                // Find all coursecards tags where category ID was specified.
                preg_match_all('/\{coursecards ([0-9]+)\}/', $text, $matches);
                // Check if tag with no cateogry.
                $nocat = (stripos($text, '{coursecards}') !== false);
                if ($nocat) {
                    $matches[1][] = 0;
                }
                // Eliminate duplicate categories.
                $categories = array_unique($matches[1]);

                $header = '<div class="card-deck mr-0">';
                $footer = '</div>';
                foreach ($categories as $catid) {
                    try {
                        $coursecat = core_course_category::get($catid);
                        // Get list of courses in this category.
                        $courses = $coursecat->get_courses($chelper->get_courses_display_options());
                    } catch (Exception $e) {
                        // Course category not found or not accessible.
                        // No courses available.
                        $courses = [];
                    }

                    $rcourseids = array_keys($courses);
                    if (count($rcourseids) > 0) {
                        $content = $this->rendercoursecards($rcourseids);
                    } else {
                        $content = '';
                    }
                    if ($catid == 0 && $nocat) {
                        $replace['/\{coursecards\}/i'] = !empty($content) ? $header . $content . $footer : '';
                    }
                    $replace['/\{coursecards ' . $catid . '\}/isuU'] = !empty($content) ? $header . $content . $footer : '';
                }
            }

            // Tag: {coursecardsbyenrol}.
            // Display list of 10 most popular courses by enrolment count (tested with MySQL and PostgreSQL).
            if (stripos($text, '{coursecardsbyenrol}') !== false) {
                $sql = "SELECT c.id, c.fullname, COUNT(*) AS enrolments
                        FROM {course} c
                        JOIN (SELECT DISTINCT e.courseid, ue.id AS userid
                                FROM {user_enrolments} ue
                                JOIN {enrol} e ON e.id = ue.enrolid) ue ON ue.courseid = c.id
                        GROUP BY c.id, c.fullname
                        ORDER BY 3 DESC, c.fullname";
                $courses = $DB->get_records_sql($sql, array(), 0, get_config('filter_filtercodes', 'coursecardsbyenrol'));
                $rcourseids = array_keys($courses);
                if (count($rcourseids) > 0) {
                    $header = '<div class="card-deck mr-0">';
                    $footer = '</div>';
                    $content = $this->rendercoursecards($rcourseids);
                } else {
                    $content = '';
                }
                $replace['/\{coursecardsbyenrol\}/i'] = !empty($content) ? $header . $content . $footer : '';
            }

            // Tag: {courserequest}. Link to Request a Course form.
            if (stripos($text, '{courserequest}') !== false) {
                // Add request a course link.
                $context = context_system::instance();
                if (!empty($CFG->enablecourserequests) && has_capability('moodle/course:request', $context)) {
                    $link = '<a href="' . new moodle_url('/course/request.php') . '">' . get_string('requestcourse') . '</a>';
                } else {
                    $link = '';
                }
                $replace['/\{courserequest\}/i'] = $link;
            }

            if (stripos($text, '{courserequestmenu') !== false) {
                // Add request a course link.
                $context = context_system::instance();
                if (!empty($CFG->enablecourserequests) && has_capability('moodle/course:request', $context)) {
                    if (stripos($text, '{courserequestmenu0}') !== false) {
                        // Top level menu.
                        $link = get_string('requestcourse') . '|' . new moodle_url('/course/request.php');
                        $replace['/\{courserequestmenu0\}/i'] = $link;
                    }
                    if (stripos($text, '{courserequestmenu}') !== false) {
                        // Not top level menu.
                        $link = '-###' . PHP_EOL;
                        $link .= '-' . get_string('requestcourse') . '|' . new moodle_url('/course/request.php');
                        $replace['/\{courserequestmenu\}/i'] = $link;
                    }
                } else {
                    $replace['/\{courserequestmenu\}/i'] = '';
                }

            }
        }

        // These tags: {mycourses} and {mycoursesmenu} and {mycoursescards}.
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

                // Remove completed courses from the list.
                if (isset($CFG->enablecompletion) && $CFG->enablecompletion == 1 // COMPLETION_ENABLED.
                        && get_config('filter_filtercodes', 'hidecompletedcourses')) {
                    foreach ($mycourses as $key => $mycourse) {
                        $ccompletion = new completion_completion(['userid' => $USER->id, 'course' => $mycourse->id]);
                        if (!empty($ccompletion->timecompleted)) {
                            // Remove completed course from the list.
                            unset($mycourses[$key]);
                        }
                    }
                }

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
                    $replace['/\{mycourses\}/i'] = $list;
                    unset($list);
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
                    $replace['/\{mycoursesmenu\}/i'] = $list;
                    unset($list);
                }

                // Tag: {mycoursescards}. Generates course cards for each enrolled course.
                if (stripos($text, '{mycoursescards}') !== false) {
                    $courseids = [];
                    foreach ($mycourses as $mycourse) {
                        $courseids[] = $mycourse->id;
                    }
                    // If not enrolled in any courses.
                    if (empty($courseids)) {
                        $list = '';
                    } else { // Otherwise, generate cards.
                        $list = '<div class="card-deck mr-0 fc-mycoursescards">' . $this->rendercoursecards($courseids) . '</div>';
                    }
                    $replace['/\{mycoursescards\}/i'] = $list;
                    unset($list);
                }
                unset($mycourses);
            } else { // Not logged in.
                // Replace tags with message indicating that you need to be logged in.
                $replace['/\{mycourses\}/i'] = '<ul class="mycourseslist"><li>' . get_string('loggedinnot') . '</li></ul>';
                $replace['/\{mycoursesmenu\}/i'] = '-' . get_string('loggedinnot') . PHP_EOL;
                $replace['/\{mycoursescards\}/i'] = '';
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
                $newtext = preg_replace_callback('/\{now\s+(.+)\}/isuU',
                    function ($matches) use ($now) {
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

        // Tags starting with: {categor...}.
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
                $category = $DB->get_record('course_categories', ['id' => $catid]);
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
                $categories = $DB->get_recordset_sql($sql, ['contextcoursecat' => CONTEXT_COURSECAT]);
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
                $categories = $DB->get_recordset_sql($sql, ['contextcoursecat' => CONTEXT_COURSECAT]);
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
                $categories = $DB->get_recordset_sql($sql, ['contextcoursecat' => CONTEXT_COURSECAT]);
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
                $categories = $DB->get_recordset_sql($sql, ['contextcoursecat' => CONTEXT_COURSECAT]);
                foreach ($categories as $category) {
                    $list .= '-' . $category->name . '|/course/index.php?categoryid=' . $category->id . PHP_EOL;
                }
                $categories->close();
                $replace['/\{categoriesxmenu\}/i'] = $list;
                unset($list);
            }

            // Tag: {categorycards} and {categorycards id}. Course categories presented as card tiles.
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
                        if (!core_course_category::can_view_category($category)) {
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

        // Tag: {mygroups}.
        if (stripos($text, '{mygroups}') !== false) {
            static $mygroups;

            if (!isset($mygroups)) {
                // Fetch my groups.
                $context = context_course::instance($PAGE->course->id);
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

        // Tag: {sesskey}.
        if (get_config('filter_filtercodes', 'enable_sesskey')) {
            global $PAGE;
            if (@$PAGE->cm->modname != 'forum' && $PAGE->pagetype != 'admin-cron') {
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

        // Tag: {sectionid}.
        if (stripos($text, '{sectionid}') !== false) {
            $replace['/\{sectionid\}/i'] = @$PAGE->cm->sectionnum;
        }
        // Alternative Tag: %7Bsectionid%7D.
        if (stripos($text, '%7Bsectionid%7D') !== false) {
            $replace['/\%7Bsectionid%7D/i'] = @$PAGE->cm->sectionnum;
        }
        // Tag: {sectionname}.
        if (stripos($text, '{sectionname}') !== false) {
            // If in a course and section name.
            if ($PAGE->course->id != $SITE->id && isset($PAGE->cm->sectionnum)) {
                $replace['/\{sectionname\}/i'] = get_section_name($PAGE->course->id, $PAGE->cm->sectionnum);
            } else {
                $replace['/\{sectionname\}/i'] = '';
            }
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
            $newtext = preg_replace_callback('/\{getstring:?(\w*)\}(\w+)\{\/getstring\}/isuU',
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

        // Tag: {courseunenrolurl}.
        if (stripos($text, '{courseunenrolurl}') !== false) {
            require_once($CFG->libdir . '/enrollib.php');
            $course = $PAGE->course;
            $coursecontext = context_course::instance($course->id);
            $replace['/\{courseunenrolurl\}/i'] = '';
            if ($course->id != SITEID && isloggedin() && !isguestuser() && is_enrolled($coursecontext)) {
                $plugins   = enrol_get_plugins(true);
                $instances = enrol_get_instances($course->id, true);
                foreach ($instances as $instance) {
                    if (!isset($plugins[$instance->enrol])) {
                        continue;
                    }
                    $plugin = $plugins[$instance->enrol];
                    if ($unenrollink = $plugin->get_unenrolself_link($instance)) {
                        $replace['/\{courseunenrolurl\}/i'] = $unenrollink;
                        break;
                    }
                }
            }
        }

        // Tag: {button}{/button}.
        if (stripos($text, '{button ') !== false) {
            $replace['/\{button\s+(.*)\}(.*)\{\/button\}/isuU'] = '<a href="$1" class="btn btn-primary">$2</a>';
        }

        // Tag: {fa fa-icon-name}.
        if (stripos($text, '{fa') !== false) {
            // Replace {fa...} tag and parameters with FontAwesome HTML.
            $newtext = preg_replace_callback('/\{fa(s|r|l|b){0,1}\sfa-(.*)\}/isuU',
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
            $newtext = preg_replace_callback('/\{glyphicon\sglyphicon-(.*)\}/isuU',
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
            $replace['/\{highlight\}/i'] = '<mark style="background-color:#FFFF00;">';
            $replace['/\{\/highlight\}/i'] = '</mark>';
        }

        // Tag: {marktext}{/marktext}.
        if (stripos($text, '{/marktext}') !== false) {
            $replace['/\{marktext\}/i'] = '<mark class="fc-marktext">';
            $replace['/\{\/marktext\}/i'] = '</mark>';
        }

        // Tag: {markborder}{/markborder}.
        if (stripos($text, '{/markborder}') !== false) {
            $replace['/\{markborder\}/i'] = '<mark class="fc-markborder" style="border:2px dashed red;padding:0.03em 0.25em;">';
            $replace['/\{\/markborder\}/i'] = '</mark>';
        }

        // Tag: {showmore}{/showmore}.
        if (stripos($text, '{/showmore}') !== false) {
            $newtext = str_replace('{showmore}', '<span id="fc-showmore-tmp" class="fc-showmore hidden">', $text);
            if (stripos($newtext, 'fc-showmore-tmp') !== false) {
                $newtext = preg_replace_callback('/fc-showmore-tmp/', function($matches) {
                        static $count = 0;
                        return 'showmore-' . $count++;
                }, $newtext);
                $text = $newtext;
                $changed = true;
            }
            $newtext = str_replace('{/showmore}', '</span> <a href="#" class="fc-showmore" style="white-space: nowrap;" ' .
                    'onclick="m=document.getElementById(\'fc-showmore-tmp\').classList;m.toggle(\'hidden\');' .
                    'this.text=(m.contains(\'hidden\')?\'' . get_string('showmore', 'form') . '\':\'' .
                    get_string('showless', 'form') . '\');return false;">' . get_string('showmore', 'form') . '</a>', $newtext);
            if (stripos($newtext, 'fc-showmore-tmp') !== false) {
                $newtext = preg_replace_callback('/fc-showmore-tmp/', function($matches) {
                        static $count = 0;
                        return 'showmore-' . $count++;
                }, $newtext);
                $text = $newtext;
                $changed = true;
            }
        }

        // Tag: {note} - Used to add notes which appear when editing but not displayed.
        if (stripos($text, '{note}') !== false) {
            // Remove the note content.
            $replace['/\{note\}(.*)\{\/note\}/isuU'] = '';
        }

        //
        // HTML tagging.
        //

        // Tag: {nbsp}.
        if (stripos($text, '{nbsp}') !== false) {
            $replace['/\{nbsp\}/i'] = '&nbsp;';
        }

        // Tag: {hr}.
        if (stripos($text, '{hr}') !== false) {
            $replace['/\{hr\}/i'] = '<hr>';
        }

        // Tag: {langx xx}.
        if (stripos($text, '{langx ') !== false) {
            $replace['/\{langx\s+(.*)\}(.*)\{\/langx\}/isuU'] = '<span lang="$1">$2</span>';
        }

        // Tag: {-} - Soft hyphen.
        if (stripos($text, '{-}') !== false) {
            $replace['/\{-\}/i'] = '&shy;';
        }

        // Tag: {details}{/details}.
        // Tag: {details style1}{/details}.
        // Tag: {summary}{/summary}.
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

            require_once($CFG->libdir.'/completionlib.php');

            // Tag: {ifinactivity}.
            if (stripos($text, '{/ifinactivity}') !== false) {
                global $PAGE;
                if (substr($PAGE->pagetype, 0, 4) == 'mod-') {
                    $replace['/\{ifinactivity\}/isu'] = '';
                    $replace['/\{\/ifinactivity\}/isu'] = '';
                } else {
                    $replace['/\{ifinactivity\}(.*){\/ifinactivity\}/isuU'] = '';
                }
            }

            // Tag: {ifnotinactivity}.
            if (stripos($text, '{/ifnotinactivity}') !== false) {
                global $PAGE;
                if (substr($PAGE->pagetype, 0, 4) != 'mod-') {
                    $replace['/\{ifnotinactivity\}/isu'] = '';
                    $replace['/\{\/ifnotinactivity\}/isu'] = '';
                } else {
                    $replace['/\{ifnotinactivity\}(.*){\/ifnotinactivity\}/isuU'] = '';
                }
            }

            // Tag: {ifactivitycompleted id}{/ifactivitycompleted}.
            if (stripos($text, '{/ifactivitycompleted}') !== false) {
                global $PAGE;
                $course = $PAGE->course;
                $completion = new completion_info($course);
                if ($completion->is_enabled_for_site() && $completion->is_enabled() == COMPLETION_ENABLED) {
                    $activities = $completion->get_activities();

                    $re = '/{ifactivitycompleted\s+([0-9]+)\}(.*)\{\/ifactivitycompleted\}/isuU';
                    $found = preg_match_all($re, $text, $matches);
                    if ($found > 0) {
                        foreach ($matches[1] as $modid) {
                            if (array_key_exists($modid, $activities)) {
                                $mod = $completion->get_data($activities[$modid], true, $USER->id);
                                $key = '/{ifactivitycompleted\s+' . $modid . '\}(.*)\{\/ifactivitycompleted\}/isuU';
                                if ($mod->completionstate) { // Activity completed by user. Just remove the tags, keep content.
                                    $replace[$key] = "$1";
                                } else { // Activity not completed. Remove tags and content.
                                    $replace[$key] = '';
                                }
                            }
                        }
                    }
                }
            }

            // Tag: {ifnotactivitycompleted id}{/ifnotactivitycompleted}.
            if (stripos($text, '{/ifnotactivitycompleted}') !== false) {
                global $PAGE;
                $course = $PAGE->course;
                $completion = new completion_info($course);
                if ($completion->is_enabled_for_site() && $completion->is_enabled() == COMPLETION_ENABLED) {
                    $activities = $completion->get_activities();

                    $re = '/{ifnotactivitycompleted\s+([0-9]+)\}(.*)\{\/ifnotactivitycompleted\}/isuU';
                    $found = preg_match_all($re, $text, $matches);
                    if ($found > 0) {
                        foreach ($matches[1] as $modid) {
                            if (array_key_exists($modid, $activities)) {
                                $mod = $completion->get_data($activities[$modid], true, $USER->id);
                                $key = '/{ifnotactivitycompleted\s+' . $modid . '\}(.*)\{\/ifnotactivitycompleted\}/isuU';
                                if ($mod->completionstate) { // Activity completed. Remove tags and content.
                                    $replace[$key] = '';
                                } else { // Activity NOT completed by user. Just remove the tags, keep content.
                                    $replace[$key] = "$1";
                                }
                            }
                        }
                    }
                }
            }

            // Tag: {ifprofile_field_...}.
            // If Custom User Profile Fields is not empty.
            if (stripos($text, '{ifprofile_field_') !== false) {
                $isuser = (isloggedin() && !isguestuser());
                // Cached the defined custom profile fields and data.
                static $profilefields;
                static $profiledata;
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
                    if (isset($profiledata[$field->id]) && $isuser && ($field->visible != '0' || $allowall)) {
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

            // Tag: {ifmobile}.
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

            // Tag: {ifnotmobile}.
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

            // Tag: {ifloggedinas}.
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

            // Tag: {ifnotloggedinas}.
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

            // Tag: {ifnotvisible}.
            if (stripos($text, '{ifnotvisible}') !== false) {
                // If the course visibility is set to hide...
                global $COURSE;
                if ($COURSE->id != 1 && empty($COURSE->visible)) { // Visibility set to Hide.
                    // Just remove the tags.
                    $replace['/\{ifnotvisible\}/i'] = '';
                    $replace['/\{\/ifnotvisible\}/i'] = '';
                } else { // Visibility set to Show.
                    // Remove the if not visible tags and contained content.
                    $replace['/\{ifnotvisible\}(.*)\{\/ifnotvisible\}/isuU'] = '';
                }
            }

            // Tag: {ifincohort idname|idnumber}.
            if (stripos($text, '{ifincohort ') !== false) {
                static $mycohorts;
                if (empty($mycohorts)) { // Cache list of cohorts.
                    require_once($CFG->dirroot.'/cohort/lib.php');
                    $mycohorts = cohort_get_user_cohorts($USER->id);
                }
                $newtext = preg_replace_callback('/\{ifincohort (\w*)\}(.*)\{\/ifincohort\}/isuU',
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
                    $replace['/\{ifeditmode\}(.*)\{\/ifeditmode\}/isuU'] = '';
                }
            }

            // Tag: {ifnoteditmode}.
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
                    $replace['/\{ifcourserequests\}(.*)\{\/ifcourserequests\}/isuU'] = '';
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
                    $replace['/\{ifnotenrolled\}(.*)\{\/ifnotenrolled\}/isuU'] = '';
                }
                // Remove the {ifincourse} strings if not in a course or on the Front Page.
                if (stripos($text, '{ifincourse}') !== false) {
                    $replace['/\{ifincourse\}(.*)\{\/ifincourse\}/isuU'] = '';
                }
                // Remove the {ifinsection} strings if not in a section of a course or are on the Front Page.
                if (stripos($text, '{ifinsection}') !== false) {
                    $replace['/\{ifinsection\}(.*)\{\/ifinsection\}/isuU'] = '';
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
                            $replace['/\{ifinsection\}(.*)\{\/ifinsection\}/isuU'] = '';
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
                        $replace['/\{ifnotinsection\}(.*)\{\/ifnotinsection\}/isuU'] = '';
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
                    $replace['/\{ifstudent\}(.*)\{\/ifstudent\}/isuU'] = '';
                }
            }

            // These tags: {ifloggedin} and {ifloggedout}.
            if (isloggedin() && !isguestuser()) { // If logged-in but not just as guest.
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

            // Tag: {ifguest}.
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

            // Tag: {ifassistant}.
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

            // Tag: {ifteacher}.
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

            // Tag: {ifcreator}.
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

            // Tag: {ifmanager}.
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

            // Tag: {ifadmin}.
            global $PAGE;
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

            // Tag: {ifdashboard}.
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

            // Tag: {ifhome}.
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
            // Tag: {ifnothome}.
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

            // Tag: {ifdev}.
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

            // Tag: {ifingroup id|idnumber}.
            if (stripos($text, '{ifingroup') !== false) {
                static $mygroupslist;
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

            // Tag: {ifnotingroup id|idnumber}.
            if (stripos($text, '{ifnotingroup') !== false) {
                static $mygroupslist;
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

            // Tag: {ifworkplace}. Only for Moodle Workplace.
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

            // Tag: {ifcustomrole rolename}.
            if (stripos($text, '{ifcustomrole') !== false) {
                $re = '/{ifcustomrole\s+(.*)\}(.*)\{\/ifcustomrole\}/isuU';
                $found = preg_match_all($re, $text, $matches);
                if ($found > 0) {
                    foreach ($matches[1] as $roleshortname) {
                        $key = '/{ifcustomrole\s+' . $roleshortname . '\}(.*)\{\/ifcustomrole\}/isuU';
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
                $re = '/{ifnotcustomrole\s+(.*)\}(.*)\{\/ifnotcustomrole\}/isuU';
                $found = preg_match_all($re, $text, $matches);
                if ($found > 0) {
                    foreach ($matches[1] as $roleshortname) {
                        $key = '/{ifnotcustomrole\s+' . $roleshortname . '\}(.*)\{\/ifnotcustomrole\}/isuU';
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

            // Tag: {ifhasarolename roleshortname}{/ifhasarolename}.
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
                        $replace['/\{ifminassistant\}(.*)\{\/ifminassistant\}/isuU'] = '';
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
                        $replace['/\{ifminteacher\}(.*)\{\/ifminteacher\}/isuU'] = '';
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
                        $replace['/\{ifmincreator\}(.*)\{\/ifmincreator\}/isuU'] = '';
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
                        $replace['/\{ifminmanager\}(.*)\{\/ifminmanager\}/isuU'] = '';
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

        //
        // Apply all of the filtercodes at once.
        //

        $newtext = null;
        if (count($replace) > 0) {
            $newtext = preg_replace(array_keys($replace), array_values($replace), $text);
            $replace = [];
        }
        if (!is_null($newtext)) {
            $text = $newtext;
            $changed = true;
        }

        // Tag: {chart <type> <value> <title>} - Easily display a chart in one of several styles.
        if (stripos($text, '{chart ') !== false && $CFG->branch >= 32) {
            global $OUTPUT;
            preg_match_all('/\{chart\s(\w+)\s([0-9]+)\s(.*)\}/isuU', $text, $matches, PREG_SET_ORDER);
            $matches = array_unique($matches, SORT_REGULAR);
            foreach ($matches as $match) {
                $type = $match[1]; // Chart type: radial, pie or progressbar.
                $value = $match[2]; // Value between 0 and 100.
                $title = $match[3]; // Text label.
                $percent = get_string('percents', '', $value);
                switch($type) { // Type of chart.
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
                    case 'progressbar': // Tag: {chart progressbar 99 Label to be displayed} - Display a horizontal progres bar.
                        $html = '
                        <div class="progress mb-0">
                            <div class="fc-progress progress-bar bar" role="progressbar" aria-valuenow="' . $value
                                . '" style="width: ' . $value . '%" aria-valuemin="0" aria-valuemax="100">
                            </div>
                        </div>';
                        if (!empty($title)) {
                            $html .= '<div class="small">' . get_string('chartprogressbarlabel', 'filter_filtercodes',
                                    ['label' => $title, 'value' => $percent]) . '</div>';
                        }
                        break;
                    default:
                        $html = '';
                }
                $replace['/\{chart ' . $type . ' ' . $value . ' ' . preg_quote($title) . '\}/isuU'] = $html;
                $newtext = preg_replace(array_keys($replace), array_values($replace), $text);
                if (!is_null($newtext)) {
                    $text = $newtext;
                    $changed = true;
                }
            }
            unset($chart, $matches, $html, $value, $title);
        }

        // Tag: {urlencode}content{/urlencode}.
        if (stripos($text, '{urlencode}') !== false) {
            // Replace {urlencode} tags and content with encoded content.
            $newtext = preg_replace_callback('/\{urlencode\}(.*)\{\/urlencode\}/isuU',
                function($matches) {
                    return urlencode($matches[1]);
                }, $text);
            if ($newtext !== false) {
                $text = $newtext;
                $changed = true;
            }
        }

        // Tag: {qrcode}{/qrcode}.
        if (stripos($text, '{qrcode}') !== false) {
            // Remove {qrcode}{/qrcode} tags and turn content between the tags into a QR code.
            $newtext = preg_replace_callback('/\{qrcode\}(.*)\{\/qrcode\}/isuU',
                function($matches) {
                    $src = $this->qrcode(html_to_text($matches[1]));
                    $src = '<img src="' . $src . '" style="width:100%;max-width:480px;height:auto;" class="fc-qrcode">';
                    return $src;
                }, $text);
            if ($newtext !== false) {
                $text = $newtext;
                $changed = true;
            }
        }

        // Tag: {alert}{/alert}.
        if (stripos($text, '{/alert}') !== false) {
            $newtext = preg_replace_callback('/\{alert(\s\w*)?\}(.*)\{\/alert\}/isuU',
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

        // Tag: {label}{/label}.
        if (stripos($text, '{/label}') !== false) {
            $newtext = preg_replace_callback('/\{label(\s\w*)?\}(.*)\{\/label\}/isuU',
            function($matches) {
                // If alert <style> parameter is not included, default to alert-info.
                $matches[1] = trim($matches[1]);
                $matches[1] = empty($matches[1]) ? 'info' : $matches[1];
                return '<span class="label label-' . $matches[1] . '">' . $matches[2] . '</span>';
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
            $newtext = preg_replace_callback('/\{help\}(.*)\{\/help\}/isuU',
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
            $newtext = preg_replace_callback('/\{info\}(.*)\{\/info\}/isuU',
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
