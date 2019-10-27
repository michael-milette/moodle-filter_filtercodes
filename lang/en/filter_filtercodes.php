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
 * @copyright  2017-2019 TNG Consulting Inc. - www.tngconsulting.ca
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
Note: Is known to be compatible with Clean and Boost based themes. Does not filter tags on the Moodle Theme Settings page.';
$string['enable_scrape'] = 'Scrape tag support';
$string['enable_scrape_description'] = 'Enable the scrape tag.';

$string['formquickquestion'] = '
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
</fieldset>
<div>
    <input type="submit" name="submit" id="submit" value="Send">
</div>
';
$string['formcontactus'] = '
<form action="{wwwroot}/local/contact/index.php" method="post" class="cf contact-us">
    <fieldset>
        <div class="form-group">
            <label for="name" id="namelabel" class="d-block">Your name <strong class="required">(required)</strong></label>
            <input id="name" name="name" type="text" size="57" maxlength="45" pattern="[A-zÀ-ž]([A-zÀ-ž\s]){2,}"
                    title="Minimum 3 letters/spaces." required="required" {readonly} value="{fullname}">
        </div>
        <div class="form-group">
            <label for="email" id="emaillabel" class="d-block">Email address <strong class="required">(required)</strong></label>
            <input id="email" name="email" type="email" size="57" maxlength="60" 
                    required="required" {readonly} value="{email}">
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
        <div class="form-group">
        <input type="hidden" id="sesskey" name="sesskey" value="">
        <script>document.getElementById("sesskey").value = M.cfg.sesskey;</script>
        {recaptcha}
    </fieldset>
    <div>
        <input type="submit" name="submit" id="submit" value="Send">
    </div>
</form>';
