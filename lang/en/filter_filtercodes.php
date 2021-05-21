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
 * English strings for FilterCodes plugin.
 *
 * @package    filter_filtercodes
 * @copyright  2017-2021 TNG Consulting Inc. - www.tngconsulting.ca
 * @author     Michael Milette
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Filter Codes';
$string['filtername'] = 'Filter Codes';
$string['privacy:metadata'] = 'The Filter Codes plugin does not store any personal data about any user.';
$string['contentmissing'] = '<h1>Content is missing.</h1><p>Please notify the webmaster.</p>';
$string['defaultfirstname'] = '';
$string['defaultsurname'] = '';
$string['defaultusername'] = '';
$string['defaultemail'] = '';
$string['enable_customnav'] = 'Custom navigation support';
$string['enable_customnav_description'] = '<strong>Experimental</strong>: Enable support for FilterCode tags in Moodle custom navigation menu.
Note: Is known to be compatible with Clean and Boost based themes in Moodle 3.2 to 3.4 only. Does not filter tags on the Moodle Theme Settings page.';
$string['disabled_customnav_description'] = '<strong>Note regarding support for custom menu</strong> - To enable support for FilterCodes in your Moodle site\'s custom menu, you may need to customize your theme or Moodle core. <a href="https://github.com/michael-milette/moodle-filter_filtercodes#can-i-use-filtercodes-in-moodles-custom-menus">Information on how to add FilterCodes support in custom menus</a>.';
$string['enable_scrape'] = 'Scrape tag support';
$string['enable_scrape_description'] = 'Enable the scrape tag.';
$string['escapebraces'] = 'Escape tags';
$string['escapebraces_desc'] = 'When this option is checked, you will be able to display FilterCode tags without them being interpreted by this filter by wrapping your tag in [ brackets ]. This can be very useful when creating FilterCodes documentation for the teachers and course creators on your Moodle site.<br><br>Example: [{fullname}] will not display the user\'s full name but display the {fullname} tag instead without the brackets.';
$string['hidecompletedcourses'] = 'Hide completed courses';
$string['hidecompletedcourses_desc'] = 'Enable to filter out completed courses in {mycoursesmenu} tag listings.';
$string['courseteachershowpic'] = 'Show teacher picture';
$string['courseteachershowpic_desc'] = 'If enabled, will display the teacher\'s profile picture in {courseteachers} tags.';
$string['courseteacherlinktype'] = 'Teacher link type';
$string['courseteacherlinktype_desc'] = 'Choose the type of link for the teacher\s link in the {courseteachers} tags.';
$string['ifprofilefiedonlyvisible'] = '{ifprofile_field_} only visible.';
$string['ifprofilefiedonlyvisible_desc'] = 'Restrict the {ifprofile_field_...} tag to only access visible profile fields. Hidden fields will behave as if the field was empty. If unchecked, this tag will be able to check hidden user fields.';

$string['sizeb'] = 'B';
$string['sizekb'] = 'KB';
$string['sizemb'] = 'MB';
$string['sizegb'] = 'GB';
$string['sizetb'] = 'TB';
$string['sizeeb'] = 'EB';
$string['sizezb'] = 'ZB';
$string['sizeyb'] = 'YB';

$string['globaltagheadingtitle'] = 'Global custom tags';
$string['globaltagheadingdesc'] = 'Define your own global tags, sometimes also called global blocks.';
$string['globaltagcount'] = 'Number of global tags.';
$string['globaltagcountdesc'] = 'Select the number of tags you want to define. For optional performance, only select the the number you will need.';
$string['globaltagnametitle'] = 'Tag: global_';
$string['globaltagnamedesc'] = 'This will be part of your tag name, prefixed with "global_". Example: If you enter "address" here, your tag will be called {global_address}". Must be a single string of letters only, no spaces, numbers or special characters are permitted.';
$string['globaltagcontenttitle'] = 'Content';
$string['globaltagcontentdesc'] = 'This is the content that your global tag will replace. Example: If your tag is called "{global_address}", that tag will be replaced by the content entered into this field.';
$string['pagebuilder'] = 'Page builder';
$string['pagebuilderlink'] = 'https://www.layoutit.com/build';
$string['photoeditor'] = 'Photo editor';
$string['photoeditorname'] = 'Pixlr';
$string['photoeditorlink'] = 'https://pixlr.com/editor/';
$string['screenrec'] = 'Screen recorder';
$string['screenreclink'] = 'https://screenapp.io/#/recording';

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
