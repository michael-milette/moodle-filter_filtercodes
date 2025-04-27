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
 * English strings for FilterCodes plugin.
 *
 * @package    filter_filtercodes
 * @copyright  2017-2025 TNG Consulting Inc. - www.tngconsulting.ca
 * @author     Michael Milette
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['brief'] = 'Brief';
$string['categorycardshowpic'] = 'Show {categorycards} background';
$string['categorycardshowpic_desc'] = 'If enabled, will display a background colour/pattern for {categorycards} tag similar to the course images when no image was specified.';
$string['chartprogressbarlabel'] = '{$a->label}: {$a->value}';
$string['contentmissing'] = '<h1>Content is missing.</h1><p>Please notify the webmaster.</p>';
$string['coursecardsbyenrol'] = 'Maximum {coursecardsbyenrol} cards.';
$string['coursecardsbyenrol_desc'] = 'Maximum number of course cards to display for {coursecardsbyenrol} tag. Set to zero for unlimited (not recommended).';
$string['coursecardsformat'] = 'Course cards layout';
$string['coursecardsformat_desc'] = 'Display {coursecards}, {coursecardsbyenrol} and {mycoursescards} either:<br>
<ul>
<li><strong>Vertical</strong>: Course image above course name.</li>
<li><strong>Horizontal</strong>: Course image to the left of course name, category and summary; or</li>
<li><strong>List</strong> in a table: Course name, category and summary.</li>
</ul>';
$string['coursecontactlinktype'] = 'Contact link type';
$string['coursecontactlinktype_desc'] = 'Choose the type of link for the contact\'s link in the {coursecontacts} tags.';
$string['coursecontactshowdesc'] = 'Show contact\'s profile description.';
$string['coursecontactshowdesc_desc'] = 'If enabled, will display the contact\'s profile description in {coursecontacts} tags.';
$string['coursecontactshowpic'] = 'Show contact picture';
$string['coursecontactshowpic_desc'] = 'If enabled, will display the contact\'s profile picture in {coursecontacts} tags.';
$string['defaultemail'] = '';
$string['defaultfirstname'] = '';
$string['defaultsurname'] = '';
$string['defaultusername'] = '';
$string['disabled_customnav_description'] = '<strong>Note regarding support for custom menu</strong> - To enable support for FilterCodes in your Moodle site\'s custom menu, you may need to customize your theme or Moodle core. <a href="https://github.com/michael-milette/moodle-filter_filtercodes#filtercodes-in-a-custom-menu">Information on how to add FilterCodes support in custom menus</a>.';
$string['enable_customnav'] = 'Custom navigation support';
$string['enable_customnav_description'] = '<strong>Experimental</strong>: Enable support for FilterCode tags in Moodle custom navigation menu.
Note: Is known to be compatible with Clean and Boost based themes in Moodle 3.2 to 3.4 only. Does not filter tags on the Moodle Theme Settings page.';
$string['enable_scrape'] = 'Scrape tag support';
$string['enable_scrape_description'] = 'Enable the scrape tag.';
$string['enable_sesskey'] = 'Sesskey tag support';
$string['enable_sesskey_description'] = 'Enable the sesskey tag globally. This feature is disabled in forums even when enabled globally.';
$string['escapebraces'] = 'Escape tags';
$string['escapebraces_desc'] = 'When this option is checked, you will be able to display FilterCode tags without them being interpreted by this filter by wrapping your tag in [ brackets ]. This can be very useful when creating FilterCodes documentation for the teachers and course creators on your Moodle site.<br><br>Example: [{fullname}] will not display the user\'s full name but display the {fullname} tag instead without the brackets.';
$string['filtername'] = 'Filter Codes';
$string['formcheckin'] = '
<form action="{wwwroot}/local/contact/index.php" method="post" class="cf check-in">
    <fieldset>
        <input type="hidden" id="subject" name="subject" value="Present!">
        <input type="hidden" id="sesskey" name="sesskey" value="">
        <script>document.getElementById("sesskey").value = M.cfg.sesskey;</script>
        {recaptcha}
    </fieldset>
    <div>
        <input type="submit" name="submit" id="submit" value="I\'m here!">
    </div>
</form>';
$string['formcontactus'] = '
<form action="{wwwroot}/local/contact/index.php" method="post" class="cf contact-us">
    <fieldset>
        <div class="form-group">
            <label for="name" id="namelabel" class="d-block">Your name <strong class="required">(required)</strong></label>
            <input id="name" name="name" type="text" size="57" maxlength="45" pattern="[A-zÀ-ž]([A-zÀ-ž\s]){2,}"
                    title="Minimum 3 letters/spaces." required="required" {readonly}{ifloggedin} disabled{/ifloggedin} value="{fullname}">
        </div>
        <div class="form-group">
            <label for="email" id="emaillabel" class="d-block">Email address <strong class="required">(required)</strong></label>
            <input id="email" name="email" type="email" size="57" maxlength="60"
                    required="required" {readonly}{ifloggedin} disabled{/ifloggedin} value="{email}">
        </div>
        <div class="form-group">
            <label for="subject" id="subjectlabel" class="d-block">Subject <strong class="required">(required)</strong></label>
            <input id="subject" name="subject" type="text" size="57" maxlength="80" minlength="5"
                    title="Minimum 5 characters." required="required">
        </div>
        <div class="form-group">
            <label for="message" id="messagelabel" class="d-block">Message <strong class="required">(required)</strong></label>
            <textarea id="message" name="message" rows="5" cols="58" minlength="5"
                    title="Minimum 5 characters." required="required"></textarea>
        </div>
        <input type="hidden" id="sesskey" name="sesskey" value="">
        <script>document.getElementById("sesskey").value = M.cfg.sesskey;</script>
        {recaptcha}
    </fieldset>
    <div>
        <input type="submit" name="submit" id="submit" value="Send">
    </div>
</form>';
$string['formcourserequest'] = '
<form action="{wwwroot}/local/contact/index.php" method="post" class="cf new-course-request">
    <fieldset>
        <div class="form-group">
            <label for="name" id="namelabel" class="d-block">Your name <strong class="required">(required)</strong></label>
            <input id="name" name="name" type="text" size="57" maxlength="45" pattern="[A-zÀ-ž]([A-zÀ-ž\s]){2,}"
                    title="Minimum 3 letters/spaces." required="required" {readonly}{ifloggedin} disabled{/ifloggedin} value="{fullname}">
        </div>
        <div class="form-group">
            <label for="email" id="emaillabel" class="d-block">Email address <strong class="required">(required)</strong></label>
            <input id="email" name="email" type="email" size="57" maxlength="60" required="required" {readonly}{ifloggedin} disabled{/ifloggedin} value="{email}">
        </div>
        <div class="form-group">
            <label for="new_course_name" id="new_course_namelabel" class="d-block">Proposed name of the new course <strong class="required">(required)</strong></label>
            <input id="new_course_name" name="new_course_name" type="text" size="57" maxlength="80" minlength="5"
                    title="Minimum 5 characters." required="required">
        </div>
        <div class="form-group">
            <label for="description" id="descriptionlabel" class="d-block">Course description <strong class="required">(required)</strong></label>
            <textarea id="description" name="description" rows="5" cols="58" minlength="5"
                    title="Minimum 5 characters." required="required"></textarea>
        </div>
        <input type="hidden" id="sesskey" name="sesskey" value="">
        <script>document.getElementById("sesskey").value = M.cfg.sesskey;</script>
        {recaptcha}
    </fieldset>
    <div>
        <input type="submit" name="submit" id="submit" value="Submit request for this course">
    </div>
</form>
';
$string['formquickquestion'] = '
<form action="{wwwroot}/local/contact/index.php" method="post" class="cf contact-us">
    <fieldset>
        <div class="form-group">
            <label for="subject" id="subjectlabel" class="d-block">Subject <strong class="required">(required)</strong></label>
            <input class="block" id="subject" name="subject" type="text" size="57" maxlength="80" minlength="5"
                    title="Minimum 5 characters." required="required">
        </div>
        <div class="form-group">
            <label for="message" id="messagelabel" class="d-block">Message <strong class="required">(required)</strong></label>
            <textarea id="message" name="message" rows="5" cols="58" minlength="5"
                    title="Minimum 5 characters." required="required"></textarea>
        </div>
        <input type="hidden" id="sesskey" name="sesskey" value="">
        <script>document.getElementById("sesskey").value = M.cfg.sesskey;</script>
        {recaptcha}
    </fieldset>
    <div>
        <input type="submit" name="submit" id="submit" value="Send">
    </div>
</form>
';
$string['formsupport'] = '
<form action="{wwwroot}/local/contact/index.php" method="post" class="cf support-request">
    <fieldset>
        <div class="form-group">
            <label for="name" id="namelabel" class="d-block">Your name <strong class="required">(required)</strong></label>
            <input id="name" name="name" type="text" size="57" maxlength="45" pattern="[A-zÀ-ž]([A-zÀ-ž\s]){2,}"
                    title="Minimum 3 letters/spaces." required="required" {readonly}{ifloggedin} disabled{/ifloggedin} value="{fullname}">
        </div>
        <div class="form-group">
            <label for="email" id="emaillabel" class="d-block">Email address <strong class="required">(required)</strong></label>
            <input id="email" name="email" type="email" size="57" maxlength="60" required="required" {readonly}{ifloggedin} disabled{/ifloggedin} value="{email}">
        </div>
        <div class="form-group">
            <label for="subject" id="subjectlabel" class="d-block">Subject <strong class="required">(required)</strong></label>
            <select id="subject" name="subject" required="required">
                <option label="Choose a subject"></option>
                <option>I can\'t change my password</option>
                <option>I can\'t login</option>
                <option value="Suggestion">I have a suggestion</option>
                <option value="Error message">I am getting an error message</option>
                <option value="System error">Something is not working the way it is supposed to</option>
                <option value="Course">I am having difficulty accessing a course or some course content</option>
                <option value="Other reason">Other (please specify)</option>
            </select>
        </div>
        <div class="form-group">
            <label for="specific_subject" id="specifylabel" class="d-block">Specific subject or the name of the course&nbsp;<strong class="required">(required)</strong></label>
            <input type="text" id="specific_subject" name="specific_subject" size="57" maxlength="80" required="required">
        </div>
        <div class="form-group">
            <label for="url" id="urllabel" class="d-block">Specify the URL address</label>
            <input type="url" id="url" name="url" size="57" maxlength="80" value="{referer}">
        </div>
        <div class="form-group">
            <label for="description" id="descriptionlabel" class="d-block">Description and step-by-step details on how to reproduce the issue&nbsp;<strong class="required">(required)</strong></label>
            <textarea id="description" name="description" rows="5" cols="58" minlength="5"
                    title="Minimum 5 characters." required="required"></textarea>
        </div>
        <input type="hidden" id="sesskey" name="sesskey" value="">
        <script>document.getElementById("sesskey").value = M.cfg.sesskey;</script>
        {recaptcha}
    </fieldset>
    <div>
        <input type="submit" name="submit" id="submit" value="Submit request for help">
    </div>
</form>';
$string['globaltagcontentdesc'] = 'This is the content that your global tag will replace. Example: If your tag is called "{global_address}", that tag will be replaced by the content entered into this field.';
$string['globaltagcontenttitle'] = 'Content';
$string['globaltagcount'] = 'Number of global tags.';
$string['globaltagcountdesc'] = 'Select the number of tags you want to define. For optimal performance, only select the the number you will need.';
$string['globaltagheadingdesc'] = 'Define your own global tags, sometimes also called global blocks.';
$string['globaltagheadingtitle'] = 'Global custom tags';
$string['globaltagnamedesc'] = 'This will be part of your tag name, prefixed with "global_". Example: If you enter "address" here, your tag will be called {global_address}". Must be a single string of letters only. Spaces, numbers and special characters are not permitted.';
$string['globaltagnametitle'] = 'Tag: global_';
$string['hidecompletedcourses'] = 'Hide completed courses';
$string['hidecompletedcourses_desc'] = 'Enable to filter out completed courses in {mycoursesmenu} tag listings.';
$string['ifprofilefiedonlyvisible'] = '{ifprofile_field_} only visible.';
$string['ifprofilefiedonlyvisible_desc'] = 'When checked, restrict the {ifprofile_field_...} tag to only access visible user profile fields. Hidden fields will behave as if they were empty. If unchecked, this tag will be also able to check hidden fields.';
$string['moremenu'] = 'More';
$string['narrowpage'] = 'Narrow page';
$string['narrowpage_desc'] = 'Enable this option to optimize display of information if Moodle is using a theme with limited page width (e.g., Boost in Moodle 4.0+).';
$string['nocompletedcourses'] = 'None of the courses in which you are enrolled have been marked as completed.';
$string['notavailable'] = 'Not available';
$string['pagebuilder'] = 'Page builder';
$string['pagebuilderlink'] = 'https://www.layoutit.com/build';
$string['photoeditor'] = 'Photo editor';
$string['photoeditorlink'] = 'https://pixlr.com/editor/';
$string['pluginname'] = 'Filter Codes';
$string['privacy:metadata'] = 'The Filter Codes plugin does not store any personal data about any user.';
$string['screenrec'] = 'Screen recorder';
$string['screenreclink'] = 'https://screenapp.io/#/recording';
$string['showhiddenprofilefields'] = 'Show hidden profile fields';
$string['showhiddenprofilefields_desc'] = 'Enable the {profile_field_...} tag to process all profile fields including ones hidden from the user.';
$string['sizeb'] = 'B';
$string['sizeeb'] = 'EB';
$string['sizegb'] = 'GB';
$string['sizekb'] = 'KB';
$string['sizemb'] = 'MB';
$string['sizetb'] = 'TB';
$string['sizeyb'] = 'YB';
$string['sizezb'] = 'ZB';
$string['teamcardsformat'] = 'Team cards format';
$string['teamcardsformat_desc'] = 'Choose how the team members will appear in the {teamcards} tag.<br>
<ul>
<li><strong>None</strong>: Displays just the picture and name as a card without the user description.</li>
<li><strong>Icon</strong>: Same as none except that the user description appears in an information popup bubble.</li>
<li><strong>Brief</strong>: Same as none but displays the description below the user picture and name.</li>
<li><strong>Verbose</strong>: List format. Recommended if your team members tends to have long user descriptions.</li>
</ul>';
$string['teamcardslinktype'] = 'Team link type';
$string['teamcardslinktype_desc'] = 'Choose the type of link for the team member\'s link in the {teamcards} tag. Note: Photo will automatically be linked to profile when the user is logged-in regardless of your choice here.';
$string['unenrolme'] = 'Unenrol me from this course';
$string['verbose'] = 'Verbose';
$string['wishlist'] = 'Wishlist';
$string['wishlist_add'] = 'Add this course to the list';
$string['wishlist_nocourses'] = 'No courses in the list';
$string['wishlist_remove'] = 'Remove this course from the list';
