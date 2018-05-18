<img src="pix/logo.png" align="right" />

FilterCodes filter plugin for Moodle
====================================
![PHP](https://img.shields.io/badge/PHP-v5.6%20%2F%20v7.0%20%2F%20v7.1-blue.svg)
![Moodle](https://img.shields.io/badge/Moodle-v2.7%20to%20v3.5-orange.svg)
[![GitHub Issues](https://img.shields.io/github/issues/michael-milette/moodle-filter_filtercodes.svg)](https://github.com/michael-milette/moodle-filter_filtercodes/issues)
[![Contributions welcome](https://img.shields.io/badge/contributions-welcome-green.svg)](#contributing)
[![License](https://img.shields.io/badge/License-GPL%20v3-blue.svg)](#license)

# Table of Contents

- [Basic Overview](#basic-overview)
- [Requirements](#requirements)
- [Download FilterCodes for Moodle](#download-filtercodes-for-moodle)
- [Installation](#installation)
- [Usage](#usage)
- [Updating](#updating)
- [Uninstallation](#uninstallation)
- [Limitations](#limitations)
- [Language Support](#language-support)
- [Frequently Asked Questions (FAQ)](#faq)
- [Contributing](#contributing)
- [Motivation for this plugin](#motivation-for-this-plugin)
- [Further information](#further-information)
- [License](#license)

# Basic Overview

FilterCodes filter for Moodle enables content creators to easily customize
and personalize site and course content using plain text tags.

In addition, it also enables you to:
* Inserting non-breaking spaces;
* Tagging text as being in a different language;

Usage of the {FilterCodes} tags requires no knowledge of HTML but might be
important for sites wishing to comply with accessibility requirements.

IMPORTANT: Although we expect everything to work, this BETA release has not been fully tested in every situation. If you find a problem, please help by reporting it in the [Bug Tracker](http://github.com/michael-milette/moodle-filter_filtercodes/issues).

[(Back to top)](#table-of-contents)

# Requirements

This plugin requires Moodle 2.7+ from http://moodle.org/

[(Back to top)](#table-of-contents)

# Download FilterCodes for Moodle

The most recent STABLE release of FilterCodes for Moodle is available from:
https://moodle.org/plugins/filter_filtercodes

The most recent DEVELOPMENT release can be found at:
https://github.com/michael-milette/moodle-filter_filtercodes

[(Back to top)](#table-of-contents)

# Installation

Install the plugin, like any other plugin, to the following folder:

    /filter/filtercodes

See http://docs.moodle.org/34/en/Installing_plugins for details on installing Moodle plugins.

In order for the filters to work, the plugin must be installed and activated.

To activate, go to Site Administration > Plugins > Filters > Manage filters" and set the FilterCodes plugin to "On". Make sure it is set to Apply To: Content or optionally "Content and headings" if you also want the tags to affect headings.

[(Back to top)](#table-of-contents)

# Usage

IMPORANT: Although we expect everything to work, this BETA release has not been fully tested in every situation. If you find a problem, please help by reporting it in the [Bug Tracker](http://github.com/michael-milette/moodle-filter_filtercodes/issues).

There are no configurable settings for this plugin at this time.

{FilterCodes} are meant to be entered as regular text in the Moodle WYSIWYG editor through they will work equally well if entered in the code view.

Moodle metadata filters

* {firstname} : Display the user's first name.
* {surname} : Display the user's surname (family/last name).
* {fullname} : Display the user's first name and surname.
* {alternatename} : Display the user's alternate name. If blank, will display user's first name instead.
* {city} : Display the user's city.
* {country} : Display the user's country.
* {email} : Display the user's email address.
* {userid} or %7Buserid%7D : Display the user's ID.
* {username} : Display the user's username.
* {userpictureurl X} : Display the user's profile picture URL. X indicates the size and can be **sm** (small), **md** (medium) or **lg** (large). If the user does not have a profile picture or is logged out, the default faceless profile photo URL will be shown instead.
* {userpictureimg X} : Generates an <img> html tag containing the user's profile picture. X indicates the size and can be **sm** (small), **md** (medium) or **lg** (large). If the user does not have profile picture or is logged out, the default faceless profile photo will be used instead.
* {coursename} : Display the name of the current course or the site name if not in a course.
* {coursestartdate} : Course start date. Will display "Open event" if there is no start date.
* {courseenddate} : Course end date. Will display "Open event" if there is no end date.
* {coursecompletiondate} : Course completion date. If not completed, will display "Not completed". Will also detect if completion is not enabled.
* {mycourses} : Display an unordered list of links to all my enrolled courses.
* {mycoursesmenu} : A second level list of courses with links for use in custom menus (filtering must be supported by the theme).
* {categories} : Display an unordered list of links to all course categores.
* {categoriesmenu} : A second level list of categories with links for use in custom menus (filtering must be supported by the theme).
* {institution} : Display the name of the institution from the user's profile.
* {department} : Display the name of the department from the user's profile.
* {courseid} or %7Bcourseid%7D : Display a course's ID.
* {wwwroot} : Display the root URL of the Moodle site.
* {protocol} : http or https
* {referrer} : Referring URL
* {ipaddress} : User's IP Address.
* {sesskey} or %7Bsesskey%7D : Moodle session key.
* {recaptcha} : Display the ReCAPTCHA field - for use with Contact Form for Moodle. Note: Will be blank if user is logged-in using a non-guest account.
* {readonly} : To be used within form input fields to make them read-only if the user is logged-in.

Conditionally display content filters

Note: {if`rolename`} and {ifmin`rolename`} type tags are based on role archetypes, not role shortnames. For example, you could have a role called `students` but, if the archetype for the role is `teacher`, the role will be identified as a `teacher`. Roles not based on archetypes will not with these tags.

* {ifenrolled}{/ifenrolled} : Will display the enclosed content only if the user **is** enrolled in the current course.
* {ifnotenrolled}{/ifnotenrolled} : Will display the enclosed content only if the user is **not** enrolled in the current course.
* {ifloggedin}{/ifloggedin} : Will display the enclosed content only if the user is logged in as non-guest.
* {ifloggedout}{/ifloggedout} : Will display the enclosed content only if the user is logged out or is loggedin as guest.
* {ifguest}{/ifguest} : Will display the enclosed content only if the user is logged-in as guest.
* {ifstudent}{/ifstudent} : Will display the enclosed content only if the user is logged-in and enrolled in the course (no other roles).
* {ifassistant}{/ifassistant} : Will display the enclosed content only if the user is logged-in as a non-editing teacher in the current course.
* {ifminassistant}{/ifminassistant} : Will display the enclosed content only if the user is logged-in as a non-editing teacher or above in the current course.
* {ifteacher}{/ifteacher} : Will display the enclosed content only if the user is logged-in as a teacher in the current course.
* {ifminteacher}{/ifminteacher} : Will display the enclosed content only if the user is logged-in as a teacher or above in the current course.
* {ifcreator}{/ifcreator} : Will display the enclosed content only if the user is logged-in as a course creator.
* {ifmincreator}{/ifmincreator} : Will display the enclosed content only if the user is logged-in as a course creator or above.
* {ifmanager}{/ifmanager} : Will display the enclosed content only if the user is logged-in as a manager.
* {ifminmanager}{/ifminmanager} : Will display the enclosed content only if the user is logged-in as a manager or above.
* {ifadmin}{/ifadmin} : Will display the enclosed content only if the user is logged-in as an administrator.

If the condition is not met in the particular context, the specified tag and it's content will be removed.

HTML and "lang" tagging

* {nbsp} : Is substituted for a non-breaking space when displayed.
* {langx xx}{/langx} : Tag specific text in a particular language by wrapping the text in a plain text pair of {langx xx} {/langx} tags. This makes no visible changes to the content but wraps the content in an HTML <span lang="xx"></span> inline tag. As a result, screen readers will make use of this information to use a particular kind of pronunciation if the text is in a different language than the language of the rest of the page. This is required for compliance with W3C Web Content Accessibility Guidelines (WCAG 2.0)

The opening {langx xx} tag should also include two [ISO 639-1](https://en.wikipedia.org/wiki/List_of_ISO_639-1_codes) language code abbreviation letters in lowercase associated with language's name. French, for example, has the code **fr**:

    {langx fr}Contenu{/langx}

The {langx fr}{/langx} filter will convert this into the following HTML

    <span lang="fr">Contenu</span>

## FilterCodes in a custom menu

Here are a couple of examples of how to create really useful custom menus using FilterCodes. Just copy and paste the code into the **Custom menu items** field (Site administration > Appearance > Theme settings)

Feel free to customize these for your own needs and to make more of these for other roles like Course creators, Teachers, Teacher assistants and even Students.

**Doesn't work?** If FilterCodes doesn't work with your theme, contact the developer / maintainer of the theme and refer them to the [FAQ](#faq) section of this documentation to provide them with an easy way for them to enable support for Moodle filters.

### General menu

This will add a Home link, a listing of categories, a listing of courses in which you are currently enrolled, and a Logout link, but only if you are currently logged in.

    Home|\
    Course catalogue
    {categoriesmenu}
    {ifloggedin}
    My Courses
    {mycoursesmenu}
    Logout|/login/logout.php?sesskey={sesskey}
    {/ifloggedin}

### Admin menu

This menu can be handy for Moodle administrators and managers.

    {ifminmanager}
    Admin
    {ifadmin}
    -Moodle Settings
    --Additional HTML|/admin/settings.php?section=additionalhtml
    --Advanced features|/admin/settings.php?section=optionalsubsystems
    --Frontpage|/admin/settings.php?section=frontpagesettings
    --Notifications|/admin/index.php
    --Plugin overview|/admin/plugins.php
    --Profile fields|/user/profile/index.php
    --Support contact|/admin/settings.php?section=supportcontact
    --Theme settings|/admin/settings.php?section=themesettings
    -Install
    --Plugin|https://moodle.org/plugins
    --Theme|https://moodle.org/plugins/browse.php?list=category&id=3
    {/ifadmin}
    -This course
    --Turn editing on|/course/view.php?id={courseid}&sesskey={sesskey}&edit=on
    --Course Backup|/backup/backup.php?id={courseid}
    --Enrolled users|/enrol/users.php?id={courseid}
    --Manage badges|/badges/index.php?type={courseid}
    --Reset course|/course/reset.php?id={courseid}
    -Add new course|/course/edit.php?category=1&returnto=topcat
    -Course management|/course/management.php
    -Restore Course|/backup/restorefile.php?contextid=1
    -System reports|/admin/category.php?category=reports
    -User management|/admin/user.php
    -###
    -Moodle support|https://moodle.org/course/view.php?id=5
    {/ifminmanager}

In this extensive example, part of the custom menu will appear only to users with a manager role while everything will appear to administrators. Nothing will appear for everyone else.

### Developer menu

If you are a developer, this little menu is worth installing FilterCodes alone.

Together with the Administration menu above, these can be a real productivity boost for developers who are tired of always digging through the **Site administration** block to find the options they are looking for. Tailor it to your particular projects with links to any page you need regularly:

    {ifadmin}
    Dev tools
    -Configure debugging|/admin/settings.php?section=debugging
    -Code checker|/local/codechecker
    -Moodle PHPdoc check|/local/moodlecheck
    -Purge cache|/admin/purgecaches.php?confirm=1&sesskey={sesskey}
    -###
    -Adminer|/local/local/adminer
    -PHP Info|/admin/phpinfo.php
    -###
    -Developer docs|https://moodle.org/development
    -Developer forum|https://moodle.org/mod/forum/view.php?id=55
    -Tracker|https://tracker.moodle.org/
    -AMOS|https://lang.moodle.org/
    {/ifadmin}

Tip: Are you a theme developers? Add a direct link to your theme's settings page.

Notes:

- **Enrolled users**, in the **This course** submenu, will only work in a course.
- **[Code checker](https://moodle.org/plugins/local_codechecker)**, **[Moodle PHPdoc check](https://moodle.org/plugins/local_moodlecheck)** and [Moodle Adminer](https://moodle.org/plugins/local_adminer) are add-on plugins that need to be installed in order for the links to work.

[(Back to top)](#table-of-contents)

# Updating

There are no special considerations required for updating the plugin.

The first public ALPHA version was released on 2017-07-07.

For more information on releases since then, see
[CHANGELOG.md](https://github.com/michael-milette/moodle-filter_filtercodes/blob/master/CHANGELOG.md).

[(Back to top)](#table-of-contents)

# Uninstallation

Uninstalling the plugin by going into the following:

Home > Administration > Site Administration > Plugins > Manage plugins > FilterCodes

...and click Uninstall. You may also need to manually delete the following folder:

    /filter/filtercodes

Note that, once uninstalled, any tags and content normally handled by this plugin will become visible to all users.

# Limitations

* The {langx xx}{/langx} tag only supports inline text, not blocks of text.
* Unpredictable results may occur if you interweave HTML code with {FilterCodes} tags.

Incorrect example:

    <strong>{FilterCode}Content</strong>{/FilterCode}

Correct example:

    {FilterCode}<strong>Content</strong>{/FilterCode}

# Language Support

This plugin includes support for the English language.

If you need a different language that is not yet supported, please feel free
to contribute using the Moodle AMOS Translation Toolkit for Moodle at

https://lang.moodle.org/

This plugin has not been tested for right-to-left (RTL) language support.
If you want to use this plugin with a RTL language and it doesn't work as-is,
feel free to prepare a pull request and submit it to the project page at:

http://github.com/michael-milette/moodle-filter_filtercodes

# FAQ

## Frequently Asked Questions

IMPORANT: Although we expect everything to work, this ALPHA release has not been fully tested in every situation. If you find a problem, please help by reporting it in the [Bug Tracker](http://github.com/michael-milette/moodle-filter_filtercodes/issues).

### {FilterCodes} Why are tags displayed as entered instead of being converted to data?

Here are a few things you can check:
* Make sure the plugin is enabled. See installation instructions.
* If the tag is in a heading, make sure you have enabled the plugin for both content and headings.
* For the {langx} tag, make sure you included the 2 letter language code in the opening tag. The closing tag must not contain any language code.
* If the tags required a closing tag, make sure that it includes a forward slash. Example: {/ifenrolled}.
* Try a different tag like {protocol}. If it still doesn't get replaced with http or https either, chances are that this part of Moodle doesn't support filters yet. Please report the part of Moodle that doesn't support filters in the Moodle Tracker. If the problem is with a 3rd party plugin, please report the issue to the developer of that plugin using the Bug Tracker link on the plugin's page on moodle.org/plugins.

### Can I nest tags? For example, {ifloggedin}{ifenrolled}Message to appear if enrolled and loggedin.{/ifenrolled}{/ifloggedin}

Yes. In this case, both conditions must be met for the message to appear.

### How can I use this to pre-populate one or more fields in a Contact Form for Moodle?

Just put the tag in the input's value parameter. Here are a couple of examples:

    <input id="email" name="email" type="email" required="required" value="{email}">
    <input id="name" name="name" type="text" required="required" value="{fullname}">

Pro Tip: You can pre-populate a field and make it non-editable for logged-in users using a conditional tag:

    <input id="email" name="email" type="email" required="required" {ifloggedin}readonly{/ifloggedin} value="{email}">
    <input id="name" name="name" type="text" required="required" {ifloggedin}readonly{/ifloggedin} value="{fullname}">

### Why do administrators see the text of all other roles when using {ifminxxxx}Content{/ifminxxxx} tags?

This is normal as the administrator has the permission of all other roles. the {ifmin...} tags will display content if the user has a minimum of the specified role or above. For example, {ifminteacher}Content here!{/ifminteacher} will display "Content here!" whether the user is a teacher, course creator, manager or administrator even if they are not a teacher.

### Is there a tag to display...?

Only the tags listed in this [documentation](#usage) are currently supported. We are happy to add new functionality in future releases of FilterCodes. Please post all requests in the [Bug Tracker](http://github.com/michael-milette/moodle-filter_filtercodes/issues). You'll find a link for this on the plugin's page. The subject line should start with "Feature Request: ". Please provide as much detail as possible on what you are trying to accomplish and, if possible, where in Moodle the information would come from. Be sure to check back on your issue as we may have further questions for you.

### How can I test to see if all of the tags are working?

Create a Page on your Moodle site and include the following code:
* First name: {firstname}
* Surname: {surname}
* Fullname: {fullname}
* Alternate name: {alternatename}
* City: {city}
* Country: {country}
* Email: {email}
* User ID: {userid}
* User ID (encoded): %7Buserid%7D
* Username: {username}
* User profile picture URL (small): {userpictureurl sm}
* User profile picture URL (medium): {userpictureurl md}
* User profile picture URL (large): {userpictureurl lg}
* User profile picture URL (small): {userpictureimg sm}
* User profile picture URL (medium): {userpictureimg md}
* User profile picture URL (large): {userpictureimg lg}
* Course or Site name: {coursename}
* Course start date: {coursestartdate}
* Course start date: {courseenddate}
* Completion date: {coursecompletiondate}
* Institution: {institution}
* Department: {department}
* Course ID: {courseid}
* Course ID (encoded): %7Bcourseid%7D
* My Enrolled Courses: {mycourses}
* My Enrolled Courses menu: {mycoursesmenu}
* Course categories: {categories}
* Course categories menu: {categoriesmenu}
* WWWroot: {wwwroot}
* Protocol: {protocol}
* IP Address: {ipaddress}
* Moodle session key: {sesskey}
* Moodle session key: %7Bsesskey%7D
* Referer: {referer}
* ReCAPTCHA: {recaptcha}
* Readonly (for form fields when logged-in): {readonly}
* Non-breaking space: This{nbsp}: Is it! (view source code to see the non-breaking space)
* English: {langx en}Content{/langx}
* Enrolled: {ifenrolled}You are enrolled in this course.{/ifenrolled}
* Not Enrolled: {ifnotenrolled}You are not enrolled in this course.{/ifnotenrolled}
* LoggedIn: {ifloggedin}You are logged-in.{/ifloggedin}
* LoggedOut: {ifloggedout}You are logged-out.{/ifloggedout}
* Guest: {ifguest}You are a guest.{/ifguest}
* Student: {ifstudent}You are student who is logged-in and enrolled in this course and have no other roles.{/ifstudent}
* Non-editing Teacher: {ifassistant}You are an assistant teacher.{/ifassistant}
* Non-editing Teacher (minimum): {ifminassistant}You are an assistant teacher or above.{/ifminassistant}
* Teacher: {ifteacher}You are a teacher.{/ifteacher}
* Teacher (minimum): {ifminteacher}You are a teacher or above.{/ifminteacher}
* Course Creator: {ifcreator}You are a course creator.{/ifcreator}
* Course Creator (minimum): {ifmincreator}You are a course creator or above.{/ifmincreator}
* Manager: {ifmanager}You are a manager.{/ifmanager}
* Manager (minimum): {ifminmanager}You are a manager or administrator.{/ifminmanager}
* Admin: {ifadmin}You are an administrator.{/ifadmin}

You can switch to different roles to see how each will affect the content being displayed.

### When a user is logged out, the First name, Surname, Full Name, Email address and Username are empty. How can I set default values for these tags?

You can do this using the language editor built into Moodle. There is currently support for the following defaults: defaultfirstname, defaultsurname, defaultusername, defaultemail. By default, these are blank. As for the Full Name, it is made up of the firstname and surname separated by a space and is therefore not settable.

### I added the "{mycoursesmenu}" to my custom menu. How can I hide it if the user is not logged in?

You can use the {ifloggedin}{/ifloggedin} tags to conditionally hide it when users are not logged in. Example:

{ifloggedin}My Courses
{mycoursesmenu}{/ifloggedin}

### How can I add a "Logout" link in my custom menu?

Just add the following line to your custom menu (under Appearance > Theme settings)

{ifloggedin}Logout|/login/logout.php?sesskey={sesskey}{/ifloggedin}

Bonus: This is also how you would hide it for users who are not logged-in.

### How can I create a menu that is just for administrators or some other roles?

Building on the previous two questions, see the [usage](#usage) section for some examples. Feel free to share your own ideas in the discussion forum.

### Can I use FilterCodes in custom menus?

Technically for sure! But only if the theme supports it. If it doesn't, contact the theme's developer and request that they add support for Moodle filters.

### I am a Moodle theme developer. How do I add support for Moodle filters, including this FilterCodes plugin, to my theme?

#### For themes based on **bootstrapbase**

Add the following code to core_renderer code section of your theme. Be sure to replace "themename" with the name of the theme's directory. Note: Your theme may even already have such a class (they often do):

    class theme_themename_core_renderer extends theme_bootstrapbase_core_renderer {
        /**
         * Applies Moodle filters to custom menu and custom user menu.
         *
         * Copyright: 2017 TNG Consulting Inc.
         * License:   GNU GPL v3+.
         *
         * @param string $custommenuitems Current custom menu object.
         * @return Rendered custom_menu that has been filtered.
         */
        public function custom_menu($custommenuitems = '') {
            global $CFG, $PAGE;

            // Don't apply auto-linking filters.
            $filtermanager = filter_manager::instance();
            $filteroptions = array('originalformat' => FORMAT_HTML, 'noclean' => true);
            $skipfilters = array('activitynames', 'data', 'glossary', 'sectionnames', 'bookchapters');

            // Filter custom user menu.
            // Don't filter custom user menu on the theme settings page. Otherwise it ends up
            // filtering the edit field itself resulting in a loss of tags.
            if ($PAGE->pagetype != 'admin-setting-themesettings' && stripos($CFG->customusermenuitems, '{') !== false) {
                $CFG->customusermenuitems = $filtermanager->filter_text($CFG->customusermenuitems, $PAGE->context,
                        $filteroptions, $skipfilters);
            }

            // Filter custom menu.
            if (empty($custommenuitems) && !empty($CFG->custommenuitems)) {
                $custommenuitems = $CFG->custommenuitems;
            }
            if (stripos($custommenuitems, '{') !== false) {
                $custommenuitems = $filtermanager->filter_text($custommenuitems, $PAGE->context, $filteroptions, $skipfilters);
            }
            $custommenu = new custom_menu($custommenuitems, current_language());
            return $this->render_custom_menu($custommenu);
        }
    }

#### For themes based on **boost**

Add the following code to core_renderer code section of your theme. Note: Your theme may even already have such a class (they often do):

    use filter_manager;

    class core_renderer extends \theme_boost\output\core_renderer {
        /**
         * Applies Moodle filters to custom menu and custom user menu.
         *
         * @param string $custommenuitems Current custom menu object.
         * @return Rendered custom_menu that has been filtered.
         */
        public function custom_menu($custommenuitems = '') {
            global $CFG, $PAGE;

            // Don't apply auto-linking filters.
            $filtermanager = filter_manager::instance();
            $filteroptions = array('originalformat' => FORMAT_HTML, 'noclean' => true);
            $skipfilters = array('activitynames', 'data', 'glossary', 'sectionnames', 'bookchapters');

            // Filter custom user menu.
            // Don't filter custom user menu on the settings page. Otherwise it ends up
            // filtering the edit field itself resulting in a loss of the tag.
            if ($PAGE->pagetype != 'admin-setting-themesettings' && stripos($CFG->customusermenuitems, '{') !== false) {
                $CFG->customusermenuitems = $filtermanager->filter_text($CFG->customusermenuitems, $PAGE->context,
                        $filteroptions, $skipfilters);
            }

            // Filter custom menu.
            if (empty($custommenuitems) && !empty($CFG->custommenuitems)) {
                $custommenuitems = $CFG->custommenuitems;
            }
            if (stripos($custommenuitems, '{') !== false) {
                $custommenuitems = $filtermanager->filter_text($custommenuitems, $PAGE->context, $filteroptions, $skipfilters);
            }
            $custommenu = new custom_menu($custommenuitems, current_language());
            return $this->render_custom_menu($custommenu);
        }

        /**
         * We want to show the custom menus as a list of links in the footer on small screens.
         * Just return the menu object exported so we can render it differently.
         */
        public function custom_menu_flat() {
            global $CFG, $PAGE;
            $custommenuitems = '';

            // Don't apply auto-linking filters.
            $filtermanager = filter_manager::instance();
            $filteroptions = array('originalformat' => FORMAT_HTML, 'noclean' => true);
            $skipfilters = array('activitynames', 'data', 'glossary', 'sectionnames', 'bookchapters');

            if (empty($custommenuitems) && !empty($CFG->custommenuitems)) {
                $custommenuitems = $CFG->custommenuitems;
            }
            if (stripos($custommenuitems, '{') !== false) {
                $custommenuitems = $filtermanager->filter_text($custommenuitems, $PAGE->context, $filteroptions, $skipfilters);
            }
            $custommenu = new custom_menu($custommenuitems, current_language());
            $langs = get_string_manager()->get_list_of_translations();
            $haslangmenu = $this->lang_menu() != '';

            if ($haslangmenu) {
                $strlang = get_string('language');
                $currentlang = current_language();
                if (isset($langs[$currentlang])) {
                    $currentlang = $langs[$currentlang];
                } else {
                    $currentlang = $strlang;
                }
                $this->language = $custommenu->add($currentlang, new moodle_url('#'), $strlang, 10000);
                foreach ($langs as $langtype => $langname) {
                    $this->language->add($langname, new moodle_url($this->page->url, array('lang' => $langtype)), $langname);
                }
            }

            return $custommenu->export_for_template($this);
        }
    }

### Why is the IP Address listed as 0:0:0:0:0:0:0:1?

0:0:0:0:0:0:0:1 is the same as localhost and it means that your web browser is probably on the same computer as your web server. This shouldn't happen with users accessing your Moodle site from their own desktop or mobile device.

### Can I combine conditional tags?

Yes. However you can only combine (AND) them so that two or more tags must be true in order for the content to be displayed. For example:

{ifloggedin}{ifenrolled}You are logged-in and enrolled in this course.{/ifenrolled}{/ifloggedin}

This plugin does not support {IF this OR that} type conditions at this time. Depending on your requirement, the {ifmin...} tags might help you achieve this. These tags enable you to display content to users with a minimum role level. This could be useful if you wanted to only display a message to faculty such as (teacher or above).

### Why does it show me as enrolled on the front page?

The Front Page is a course in Moodle. All users are enrolled by default in this course.

### I added the {recaptcha} tag in my webform. Why doesn't the reCAPTCHA show up?

First, the reCAPTCHA is only made to work with forms processed by the Contact Form for Moodle plugin. That said, it is 100% generated by Moodle API so, if you have some other purpose, it will probably work as well as long as the receiving form is made to process it.

In order for reCAPTCHA to work, you need to configure the site and secret keys in Moodle. For more information, log into your Moodle site as an Administrator and the navigate to Home > Site Administration > Authentication > Manage Authentication and configure the ReCAPTCHA site key and ReCAPTCHA secret key. You will also need to enable ReCAPTCHA in the settings of the Contact Form plugin.

If you are using older versions of Moodle before 3.1.11+, 3.2.8+, 3.3.5+, 3.4.5+ and 3.5+, ReCAPTCHA is no longer supported.

### Are there any security considerations?

There are no known security considerations at this time.

## Other questions

Got a burning question that is not covered here? If you can't find your answer, submit your question in the Moodle forums or open a new issue on Github at:

http://github.com/michael-milette/moodle-filter_filtercodes/issues

[(Back to top)](#table-of-contents)

# Contributing

If you are interested in helping, please take a look at our [contributing](https://github.com/michael-milette/moodle-filter_filtercodes/blob/master/CONTRIBUTING.md) guidelines for details on our code of conduct and the process for submitting pull requests to us.

## Contributors

Michael Milette - Author and Lead Developer

## Pending Features

Some of the features we are considering for future releases include:

* Finish unit testing script.
* Add ability to access additional information from profile fields.
* Add ability to access information in custom profile fields.
* Add ability to access course meta information. Example, teacher's name.
* Add ability to list courses in the current course's category.
* Add ability to list subcategories of the current category.
* Add ability to define custom code blocks - useful for creating global content blocks that can be centrally updated.
* Add settings page with option to disable unused or unwanted filters in order to optimize performance or simply disable features.
* Create an Atto add-on (separate plugin) to make it easier to insert FilterCodes tags.

If you could use any of these features, or have other requirements, consider contributing or hiring us to accelerate development.

[(Back to top)](#table-of-contents)

# Motivation for this plugin

The development of this plugin was motivated through our own experience in Moodle development and topics discussed in the Moodle forums. The project is sponsored and supported by TNG Consulting Inc.

[(Back to top)](#table-of-contents)

# Further Information

For further information regarding the filter_filtercodes plugin, support or to
report a bug, please visit the project page at:

http://github.com/michael-milette/moodle-filter_filtercodes

[(Back to top)](#table-of-contents)

# License

Copyright © 2017-2018 TNG Consulting Inc. - http://www.tngconsulting.ca/

This file is part of FilterCodes for Moodle - http://moodle.org/

FilterCodes is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

FilterCodes is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with FilterCodes.  If not, see <http://www.gnu.org/licenses/>.

[(Back to top)](#table-of-contents)