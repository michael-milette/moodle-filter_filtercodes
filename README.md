<img src="pix/logo.png" align="right" />

FilterCodes filter plugin for Moodle
====================================
![PHP](https://img.shields.io/badge/PHP-v5.6%20%2F%20v7.0%20%2F%20v7.1%2F%20v7.2%2F%20v7.3%2F%20v7.4-blue.svg)`
![Moodle](https://img.shields.io/badge/Moodle-v2.7%20to%20v3.10.x-orange.svg)
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

Usage of the FilterCodes tags requires no knowledge of HTML but might be
important for sites wishing to comply with accessibility requirements.

IMPORTANT: This STABLE release has been tested on many Moodle sites. Although we expect everything to work, if you find a problem, please help by reporting it in the [Bug Tracker](https://github.com/michael-milette/moodle-filter_filtercodes/issues).

HOWEVER: There may be some tags identified as ALPHA in this documentation. These may still require some development and are not guarantied to be implementaed or implemented in the same way in future releases. Please let us know if you think they are useful if they work for you or what changes you might like to see.

[(Back to top)](#table-of-contents)

# Requirements

This plugin requires Moodle 2.7+ from https://moodle.org/ . Note that some tags may require more recent versions of Moodle.

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

See https://docs.moodle.org/en/Installing_plugins for details on installing Moodle plugins.

In order for the filters to work, the plugin must be installed and activated.

To activate, go to Site Administration > Plugins > Filters > Manage filters" and set the FilterCodes plugin to "On". Make sure it is set to Apply To: Content or optionally "Content and headings" if you also want the tags to affect headings.

[(Back to top)](#table-of-contents)

# Usage

IMPORTANT: This STABLE release has been tested on many Moodle sites. Although we expect everything to work, if you find a problem, please help by reporting it in the [Bug Tracker](https://github.com/michael-milette/moodle-filter_filtercodes/issues).

FilterCodes are meant to be entered as regular text in the Moodle WYSIWYG editor through they will work equally well if entered in the HTML code view.

## List of FilterCode tags

### Creating FilterCodes Documentation

* [{ }] : You can escape tags so they are not processed by adding [brackets] around the tag. Can be disabled in the plugin's settings if it causes you problems.
* [%7B %7D] : You can escape %7Bencoded%7D tags too so they are not processed by adding [brackets] around them.

### Profile

* {firstname} : Display the user's first name.
* {surname} or {lastname} : Display the user's surname (family/last name).
* {fullname} : Display the user's first name and surname.
* {alternatename} : Display the user's alternate name. If blank, will display user's first name instead.
* {city} : Display the user's city.
* {country} : Display the user's country.
* {timezone} : Display the user's preferred timezone.
* {preferredlanguage} : Display the user's preferred language in that language. Because of this, it will be encapulated in an HTML span tag with an appropriately set lang attribute.
* {email} : Display the user's email address.
* {userid} or %7Buserid%7D : Display the user's Moodle ID.
* {idnumber} : Display the user's idnumber from their profile.
* {username} : Display the user's username.
* {userdescription} : Display the user's description.
* {webpage} : Display the user's webpage as seen in their profile.
* {institution} : Display the name of the institution from the user's profile.
* {department} : Display the name of the department from the user's profile.
* {userpictureurl X} : Display the user's profile picture URL. X indicates the size and can be **sm** (small), **md** (medium) or **lg** (large). If the user does not have a profile picture or is logged out, the default faceless profile photo URL will be shown instead.
* {userpictureimg X} : Generates an <img> html tag containing the user's profile picture. X indicates the size and can be **sm** (small), **md** (medium) or **lg** (large). If the user does not have profile picture or is logged out, the default faceless profile photo will be used instead.
* {profile_field_shortname} : Display's custom profile field. Replace "shortname" with the shortname of a custom profile field all in lowercase. NOTE: Will not display if custom profile field's settings are set to **Not Visible**.
* {profilefullname}: Similar to {fullname} except that it displays a profile owner's name when placed on the Profile page.
* {firstaccessdate dateTimeFormat} : Date that the user first accessd the site. For information on the optional dateTimeFormat format, see Supported dateTimeFormats Formats in the [FAQ](#faq) section of this documentation.

### System Information

* {filtercodes} : Will display version and release of FilterCodes plugin. Not that this information is only available to those who can edit the tag.
* {usercount} : Count total number of registered users on the site. Does not included deleted users, primary admin or guest.
* {usersactive} : Count total number of registered users on the site. Does not included deleted users, disabled users, primary admin or guest.
* {usersonline} : Total number of users who were online in the last 5 minutes.
* {siteyear} : 4-digit current year.
* {now dateTimeFormat} : Display's the current date. For information on the optional dateTimeFormat format, see Supported dateTimeFormats Formats in the [FAQ](#faq) section of this documentation.
* {coursecount} : Total number of courses on this Moodle site (not including Front Page).
* {diskfreespace} : Display amount of free disk space for application folder. Infinite symbol will be displayed if greater than about 84,703.29 Yottabyte (YB) or failure to get size from operating system.
* {diskfreespacedata} : Display amount of free disk space for moodledata folder. Infinite symbol will be displayed if greater than about 84,703.29 Yottabyte (YB) or failure to get size from operating system.
* {wwwroot} : Root URL of the Moodle site.

### UI Elements

* {coursecards}: (ALPHA) Display available courses as cards. Has only been tested on Front Page.
* {courseprogress}: (ALPHA) Displays course progress status in words. Only works within a course.
* {courseprogressbar}: (ALPHA) Displays course progress status as a status bar. Only works within a course.
* {categorycards}: (ALPHA) Display top level categories as cards. Has only been tested on Front Page.
* {mycourses} : Display an unordered list of links to all my enrolled courses.

### For use in courses

* {coursename} : Display the full name of the current course or the site name if not in a course.
* {courseshortname} : Display the short name of the current course or the site short name if not in a course.
* {coursestartdate dateTimeFormat} : Course start date. Will display "Open event" if there is no start date. For information on the optional dateTimeFormat format, see Supported dateTimeFormats Formats in the [FAQ](#faq) section of this documentation.
* {courseenddate dateTimeFormat} : Course end date. Will display "Open event" if there is no end date. For information on the optional dateTimeFormat format, see Supported dateTimeFormats Formats in the [FAQ](#faq) section of this documentation.
* {coursecompletiondate dateTimeFormat} : Course completion date. If not completed, will display "Not completed". Will also detect if completion is not enabled. For information on the optional dateTimeFormat format, see Supported dateTimeFormats Formats in the [FAQ](#faq) section of this documentation.
* {courseprogress}: (ALPHA) Displays course progress status in words. Only works within a course.
* {courseprogressbar}: (ALPHA) Displays course progress status as a status bar. Only works within a course.
* {course_fields}: Displays the custom course fields. NOTE: Respects a custom course field's Visible To setting.
* {course_field_shortname} : Display's custom course field. Replace "shortname" with the shortname of a custom course field all in lowercase. NOTE: Respects a custom course field's Visible To setting.
* {coursesummary} : Display's the course summary. If placed in a site page, displays the site summary.
* {courseimage} : Display's the course image.
* {courseparticipantcount} : Displays the number of students enrolled in the current course.
* {courseid} or %7Bcourseid%7D : Display a course's ID.
* {coursecontextid} or %coursecontextid%7D : Display a course's context ID.
* {courseidnumber} : Display a course's ID number.
* {sectionid} : Display the section ID (not to be confused with the section number).

Also see Courses section below.

### Categories

* {categoryid} : If in a course, the ID of the course's parent category, the category ID of a course category page, otherwise 0.
* {categoryname} : If in a course, the name of the course's parent category, otherwise blank.
* {categorynumber} : If in a course, the number of the course's parent category, otherwise blank.
* {categorydescription} : If in a course, the number of the description of a course's parent category, otherwise blank.
* {categories} : Display an unordered list of links to all course categories.
* {categories0} : Display an unordered list of just top level links to all course categories.
* {categoriesx} : Display an unordered list of other categories in the current category.

### Custom menu

* {categoriesmenu} : A second level list of categories with links for use in custom menus (filtering must be supported by the theme).
* {categories0menu} : A second level list of just top level categories with links for use in custom menus (filtering must be supported by the theme).
* {categoriesxmenu} : A second level list of other categories in the current category with links for use in custom menus (filtering must be supported by the theme).
* {toggleeditingmenu} : A Turn Editing On or Turn Editing Off custom menu item. Note that you need to add your own dash(es).
* {mycoursesmenu} : A second level list of courses with links for use in custom menus (filtering must be supported by the theme).

### URL

* {pagepath} : Path of the current page without wwwroot.
* {thisurl} : The complete URL of the current page.
* {thisurl_enc} : The complete encoded URL of the current page.
* {urlencode}{/urlencode} : URL encodes any content between the tages.
* {referer} : Referring URL
* {protocol} : http or https
* {referrer} : Alias of {referer}
* {ipaddress} : User's IP Address.
* {sesskey} or %7Bsesskey%7D : Moodle session key.
* {wwwcontactform} : Action URL for Contact Form forms. (requires Contact Form plugin).

### Content

* {note}content{/note} : Enables you to include a note which will not be displayed.
* {help}content{/help} : Enables you to create popup help icons just like Moodle does.
* {info}content{/info} : Enables you to create popup help icons just like the popup Help icons but with an "i" information icon.
* {alert style}content{/alert}: (ALPHA) Creates an alert box containing the specified content. You can change the style by specifying an optional parameter. Example: `{alert primary}` or `{alert success}`. [List of styles](https://getbootstrap.com/docs/4.0/components/alerts/)
* {highlight}{/highlight} : Highlight text. NOTE: Must only be used within a paragraph.
* {scrape url="..." tag="..." class="..." id="..." code="..."} : Scrapes the content from another web page. Must be enabled in FilterCodes settings.
* {getstring:component_name}stringidentifier{/getstring} or {getstring}stringidentifier{/getstring}: Display a Moodle language string in the current language. If no component name (plugin) is specified, will default to "moodle".
* {fa/fas/far/fal fa-...} : Insert FontAwesome icon. Note: FontAwesome Font/CSS must be loaded as part of your theme.
* {glyphicon glyphicon-...} : Insert Glyphicons icon. Note: Glyphicons Font/CSS must be loaded as part of your theme.

### Contact Form templates

The following tags are replaced by Contact Form templates and therefore require that you have the Contact Form for Moodle plugin installed.

* {formquickquestion} : Adds a "quick question" form to your course. Form includes Subject and Message fields. Note: User must be logged in or the form will not be displayed.
* {formcontactus} : Adds a "Contact Us" form to your site (example: in a page). Form includes Name, Email address, Subject and Message fields.
* {formcourserequest} : Adds a "Course Request" form to your site (example: in a page). Unlike Moodle's request-a-course feature where you can request to create your own course, this tag allows users to request that a course they are interested in be created. Could also be used to request to take a course. Form includes Name, Email address, Course name, Course Description.
* {formsupport} : Adds a "Support Request" form to your site (example: in a page). Form includes Name, Email address, pre-determined Subject, specific Subject, URL and Message fields.
* {formcheckin} : Adds a "I'm here!" button to your to your course. Form does not include any other fields. Note: User must be logged in or the button will not be displayed.

### Useful for creating Custom Contact Forms and Links

* {lang} : 2-letter language code of current Moodle language.
* {recaptcha} : Display the ReCAPTCHA field - for use with Contact Form for Moodle. Note: Will be blank if user is logged-in using a non-guest account.
* {readonly} : To be used within form input fields to make them read-only if the user is logged-in.
* {editingtoggle} : "off" if in edit page mode. Otherwise "on". Useful for creating Turn Editing On/Off links.
* {wwwcontactform} : Action URL for Contact Form forms. (requires Contact Form plugin).
* {formsesskey} : Not a form. This can be used instead of having to insert the required hidden input field and JavaScript Snippet.

### Conditionally display content filters (All versions of Moodle)

Note: {if`rolename`} and {ifmin`rolename`} type tags are based on role archetypes, not role shortnames. For example, you could have a role called `students` but, if the archetype for the role is `teacher`, the role will be identified as a `teacher`. Roles not based on archetypes will not with these tags.

#### Logged in/out

* {ifloggedin}{/ifloggedin} : Will display the enclosed content only if the user is logged in as non-guest.
* {ifloggedout}{/ifloggedout} : Will display the enclosed content only if the user is logged out or is loggedin as guest.
* {ifloggedinas}{/ifloggedinas} : Will display the enclosed content only if you are logged-in-as (loginas) a different user.
* {ifnotloggedinas}{/ifnotloggedinas} : Will display the enclosed content only if you are logged-in as yourself and not a different user.

#### Courses

* {ifenrolled}{/ifenrolled} : Will display the enclosed content only if the user **is** enrolled in the current course.
* {ifnotenrolled}{/ifnotenrolled} : Will display the enclosed content only if the user is **not** enrolled in the current course.
* {ifincourse}{/ifincourse} : Will display the enclosed content only if the user is in a course other than the Front page.
* {ifinsection}{/ifinsection} : Will display the enclosed content only if the user is in a section of a course which is not the Front Page.
* {ifnotinsection}{/ifnotinsection} : Will display the enclosed content only if the user is not in a section of a course.
* {ifingroup id|idnumber}{/ifingroup} : Display content if the user is part of the specified course group ID or group ID number.

#### Roles

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
* {ifminsitemanager}{/ifminsitemanager} : Will display the enclosed content only if the user is logged-in as a site manager or above.
* {ifadmin}{/ifadmin} : Will display the enclosed content only if the user is logged-in as an administrator.
* {ifcustomrole roleshortname}{/ifcustomrole} : Will display enclosed content only if the user has the custom role specified by its shortname within the current context.
* {ifnotcustomrole roleshortname}{/ifnotcustomrole} : Will display enclosed content only if the user does not have the custom role specified by its shortname within the current context.
* {ifincohort CohortID|idnumber}{/ifincohort} : Will display enclosed content only if user is a member of the specified cohort. You can specify the Cohort ID in your cohort settings or its ID number. Cohort ID can contain a combination of letters from a to z, A to Z, numbers 0 to 9 and underscores. It will not work if it contains spaces, dashes or other special characters.

#### Miscellanious

* {ifdev}{/ifdev} : Will display the enclosed content only if the user is logged-in as an administrator and developer debugging mode is enabled.
* {ifhome}{/ifhome} : Will display the enclosed content only if the user is on the Moodle Home Front Page.
* {ifdashboard}{/ifdashboard} : Will display the enclosed content only if the user is on the Moodle Dashboard.
* {ifcourserequests}{/ifcourserequests} : Will display enclosed contents only if the Request a Course feature is enabled.
* {ifeditmode}{/ifeditmode} : Will display the enclosed content only if editing mode is turned on.

If the condition is not met in the particular context, the specified tag and it's content will be removed.

#### Conditionally display content filters (For Moodle Workplace)

* {iftenant idnumber|tenantid}{/iftenant} : (ALPHA) Will display the content if a tenant idnumber or tenant id is specified. Only {iftenant 1} will work in Moodle classic.
* {ifworkplace}{/ifworkplace} : (ALPHA) Will display the content only if displayed in Moodle Workplace.

### HTML and "lang" tagging

* {-} : Is substituted for &shy;, a soft hyphen that only appears when needed.
* {nbsp} : Is substituted for a non-breaking space when displayed.
* {langx xx}{/langx} : Tag specific text in a particular language by wrapping the text in a plain text pair of {langx xx} {/langx} or {langx xx-XX} {/langx} tags. This makes no visible changes to the content but wraps the content in an HTML <span lang="xx"></span> inline tag. As a result, screen readers will make use of this localization information to apply a particular pronunciation if the text is in a different language than the language of the rest of the page. This is required for compliance with W3C Web Content Accessibility Guidelines (WCAG 2.0)
* {details}{summary}{/summary}{/details} : An easy way to create an HTML 5 Details/Summary expandable section in your page. IMPORTANT: {details}{summary}{/summary} must all be on one line (it is ok if the line wraps). The rest of the details can be on multiple lines followed by the {/details}. This is an experimental feature which may result in invalid HTML but it works.

The opening {langx xx} tag should include two [ISO 639-1](https://en.wikipedia.org/wiki/List_of_ISO_639-1_codes) language code abbreviation letters in lowercase associated with language's name. French, for example, has the code **fr**:

    {langx fr}Contenu{/langx}

The {langx fr}{/langx} filter will convert this into the following HTML

    <span lang="fr">Contenu</span>

The opening {langx xx} may also include a [culture code](https://en.wikipedia.org/wiki/Language_localisation) used in countries and regions. This includes an additional dash and two uppercase letters associated with language's region or country. French Canadian, for example, has the code **fr-CA**:

    {langx fr-CA}Contenu{/langx}

The {langx fr-CA}{/langx} filter will convert this into the following HTML

    <span lang="fr-CA">Contenu</span>

## FilterCodes in a custom menu

Here are a couple of examples of how to create really useful custom menus using FilterCodes. Just copy and paste the code into the **Custom menu items** field (Site administration > Appearance > Theme settings)

Feel free to customize these for your own needs and to make more of these for other roles like Course creators, Teachers, Teacher assistants and even Students.

**Doesn't work?** If FilterCodes doesn't work with your theme's custom menu, contact the developer / maintainer of the theme and refer them to the [FAQ](#faq) section of this documentation which will provide them with an easy way for them to enable support for Moodle filters.

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
    -Add new course|/course/edit.php?category={categoryid}&returnto=topcat
    -Course management|/course/management.php
    -Restore Course|/backup/restorefile.php?contextid={coursecontextid}
    -System reports|/admin/category.php?category=reports
    {ifminsitemanager}
    -User management|/admin/user.php
    {/ifminsitemanager}
    -###
    -Moodle support|https://moodle.org/course/view.php?id=5
    {/ifminmanager}

In this extensive example, part of the custom menu will appear only to users with a manager role while everything will appear to administrators. Nothing will appear for everyone else.

### Developer menu

If you are a developer, this little menu is worth installing FilterCodes alone.

Together with the Administration menu above, these can be a real productivity boost for developers who are tired of always digging through the **Site administration** block to find the options they are looking for. Tailor it to your particular projects with links to any page you need regularly:

    {ifdev}
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
    {/ifdev}

Tip: Are you a theme developers? Add a direct link to your theme's settings page.

Notes:

- **Enrolled users**, in the **This course** submenu, will only work in a course.
- **[Code checker](https://moodle.org/plugins/local_codechecker)**, **[Moodle PHPdoc check](https://moodle.org/plugins/local_moodlecheck)** and [Moodle Adminer](https://moodle.org/plugins/local_adminer) require add-on 3rd party plugins which need to be installed in order for the links to work.

## Scrape'ing content

Note: This feature must be enabled in FilterCodes settings.

As of version 0.4.7, you can now use FileterCodes to scrape content from another web page. Your mileage may vary and depends a lot on your configuration, the website from which you are scraping content and more.

{scrape url="..." tag="..." class="..." id="..." code="..."}

Tip: When adding this tag in one of Moodle's WYSIWYG editors like Atto or TinyMCE, the tag may end up embedded in a set of HTML paragraph tags. If this happens, the content you are scraping may not result in valid HTML. To fix the problem, you will need to go into the source code view of the editor and replace the P (paragraph) tags with div and then save. Alternatively, if there is nothing else in the editor, you can remove everything before and after the tag and save.

Parameters:

* url = The URL of the webpage from which you want to grab its content.
* tag = The HTML tag you want to capture.
* class = Optional. Default is blank (class is irrelevant). Class attribute of the HTML tag you want to capture. Must be an exact match for everything between the quotation marks.
* id = Optional. Default is blank (id is irrelevant). id tag of the HTML tag you want to capture.
* code = Optional. Default is blank (no code). This is URL encoded code that you want to insert after the content. Will be decoded before being inserted into the page. Can be things like JavaScript for example. Be careful with this one. If not encoded, will result in error.

If the URL fails to produce any content (broken link for example), a message will be displayed on the page encouraging the visitor to contact the webmaster. This message can be customized through the Moodle Language editor.

If a matching tag, class and/or id can't be found, will return all of the page content without being filtered.

## Back to section / Back to course

Help students navigate your Moodle site by implementing this handy-dandy BACK button. Works at both the section and activity level.

    <p style="float:right;"><a href="{wwwroot}/course/view.php?id={courseid}&amp;section={sectionid}" class="btn btn-outline" style="font-size:14px;">Go Back</a></p>

If you are in a section and want to go directly back to the main course outline but scroll down to the current section, try this:

    <p style="float:right;"><a href="{wwwroot}/course/view.php?id={courseid}#section-{sectionid}" class="btn btn-outline" style="font-size:14px;">Back to course outline</a></p>

## Optional FilterCodes for Moodle settings

FilterCodes for Moodle includes the following settings. These are available on the plugin's `Settings` page by going to:

Site administration > Plugins > Filters > Filter Codes

### Custom navigation support

Experimental: Enable support for FilterCode tags in Moodle custom navigation menu. Note: Is known to be compatible with Clean and Boost based themes.

NOTE: Does not filter tags on the Moodle Theme Settings page. This is not a bug. It is just the way it has to be for now.

### Scrape tag support

Enable or disable the {scrape} tag.

[(Back to top)](#table-of-contents)

# Updating

There are no special considerations required for updating the plugin.

The first public ALPHA version was released on 2017-07-07, BETA on 2017-11-11 and STABLE as of 2018-11-26.

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
* Unpredictable results may occur if you interweave HTML code with {FilterCodesTag} tags.

Incorrect example:

    <strong>{FilterCodesTag}Content</strong>{/FilterCodesTag}

Correct example:

    {FilterCodesTag}<strong>Content</strong>{/FilterCodeTag}

# Language Support

This plugin includes support for the English language.

If you need a different language that is not yet supported, please feel free to contribute using the Moodle AMOS Translation Toolkit for Moodle at

https://lang.moodle.org/

If the content replacing the tag contains language filtering tags, be sure to have FilterCodes above the language filter in the Moodle filter settings.

This plugin has not been tested for right-to-left (RTL) language support. If you want to use this plugin with a RTL language and it doesn't work as-is, feel free to prepare a pull request and submit it to the project page at:

https://github.com/michael-milette/moodle-filter_filtercodes

# FAQ

## Frequently Asked Questions

IMPORANT: Although we expect everything to work, this release has not been fully tested in every situation. If you find a problem, please help by reporting it in the [Bug Tracker](https://github.com/michael-milette/moodle-filter_filtercodes/issues).

### Troubleshooting: Why are tags displayed as entered instead of being converted to expected content/data?

Here are a few things you can check:

* Make sure the plugin is enabled (**On**) for both **Headings and Content** in Site Administration > Plugins > Filters > Manage Filters. See installation instructions.
* Make sure that the tag you are trying to use is supported in the version of FilterCodes you currently have installed on your Moodle site. See the CHANGELOG.md for the history of when tags were added.
* Try testing using the {firstname} tag. It was one of the very first (it's not even in the history). If it works with this tags, it should work with others.
* Make sure you specified any required parameters in your tag. In the case of the {langx} tag for example, make sure you included the 2 letter language code in the opening tag. Example: {langx fr}.
* If the tags required a closing tag, make sure that it includes a forward slash and does not include any parameters. Example: {/ifenrolled}.
* If the tag requires a closing tag, Make sure that the closing tag does not contain any parameters. Only opening tags may contain parameters.
* If it doesn't work with a particular 3rd party theme (i.e. not included with Moodle), try it using it in the Boost theme.
* If it doesn't work with a particular 3rd party plugin (i.e. not included with Moodle), try using the tag in an HTML block or label.
* If you have determined that the problem is with a 3rd party plugin or theme, please report the issue to its developer using the **Bug Tracker** link on the plugin's page located at moodle.org/plugins. Provide as much information as you can. It may be helpful to point them to the [Moodle Output API documentation](https://docs.moodle.org/dev/Output_functions), specifically the format_text() and format_string() functions.
* If it still doesn't work, chances are that this some part of Moodle doesn't support filters yet. It is rare but it happens (example: Badges). Please report the part of Moodle that doesn't support filters in the [Moodle Tracker](https://tracker.moodle.org).
* Read the rest of this FAQ section.
* If all else fails, ask questions. There are links on the [FilterCodes](https://moodle.org/plugins/filter_filtercodes) plugin page to the [Discussion](https://moodle.org/mod/forum/discuss.php?d=359252) forum for getting help and the [Bug Tracker](https://github.com/michael-milette/moodle-filter_filtercodes/issues) on GitHub for reporting bugs.

### Can I combine/nest conditional tags?

Yes. You can only combine (AND) them. The two or more tags must be true in order for the content to be displayed. For example:

{ifloggedin}{ifenrolled}You are logged-in and enrolled in this course.{/ifenrolled}{/ifloggedin}

This plugin does not support {IF this OR that} type conditions at this time. Depending on your requirement, the {ifmin...} tags might help you achieve this. These tags enable you to display content to users with a minimum role level. This could be useful if you wanted to only display a message to faculty such as (teacher or above).

### I am using FilterCodes on a multi-language site. Some of my non-FilterCode tags are not being processed. How can I fix this?

This is actually a pretty common question. Simply move FilterCodes to the top of the list in your Moodle Filter Management. The only exception to this would be if one of your other filters were generating content that included FilterCode tags. In that case, place that plugin above FilterCodes.

### How can I use this to pre-populate one or more fields in a Contact Form for Moodle?

Just put the tag in the input's value parameter. Here are a couple of examples:

    <input id="email" name="email" type="email" required="required" value="{email}">
    <input id="name" name="name" type="text" required="required" value="{fullname}">

Pro Tip: You can pre-populate a field and make it non-editable for logged-in users using a conditional tag:

    <input id="email" name="email" type="email" required="required" {ifloggedin}readonly{/ifloggedin} value="{email}">
    <input id="name" name="name" type="text" required="required" {ifloggedin}readonly{/ifloggedin} value="{fullname}">

### Why do administrators see the text of all other roles when using {ifminxxxx}Content{/ifminxxxx} tags?

This is normal as the administrator has the permission of all other roles. The {ifmin...} tags will display content if the user has a minimum of the specified role or above. For example, {ifminteacher}Content here!{/ifminteacher} will display "Content here!" whether the user is a teacher, course creator, manager or administrator even if they are not a teacher.

### Is there a tag to display...?

Only the tags listed in this [documentation](#usage) are currently supported (version on GitHub is often newer than on Moodle.org). We are happy to add new functionality in future releases of FilterCodes. Please post all requests in the [Bug Tracker](https://github.com/michael-milette/moodle-filter_filtercodes/issues). You'll also find a link for this on the plugin's page. The subject line should start with "Feature Request: ".

When requesting a new tag, please provide:

* As much detail as possible on what you are trying to accomplish.
* An example of how it would be used.
* If possible, where in Moodle the information would come from.

Be sure to check back on your issue as we may have further questions for you.

### Do you have examples, samples and ways to which tags are working in my version of FilterCodes?

Create a Page on your Moodle site, preferably in a course, so that those tags work too, and include the following code:

* First name [{firstname}]: {firstname}
* Surname [{surname}]: {surname}
* Last name [{lastname}]: {lastname}
* Full name [{fullname}]: {fullname}
* Alternate name [{alternatename}]: {alternatename}
* City [{city}]: {city}
* Country [{country}]: {country}
* Preferred timezone [{timezone}]: {timezone}
* Preferred language [{preferredlanguage}]: {preferredlanguage}
* Email [{email}]: {email}
* User ID [{userid}]: {userid}
* User ID (encoded) [%7Buserid%7D]: %7Buserid%7D
* ID Number [{idnumber}]: {idnumber}
* User name [{username}]: {username}
* User description [{userdescription}] : {userdescription}
* Website URL [{website}] : {website}
* Scrape h1 from example.com: {scrape url="https://example.com/" tag="h1"}
* User profile picture URL (small) [{userpictureurl sm}]: {userpictureurl sm}
* User profile picture URL (medium) [{userpictureurl md}]: {userpictureurl md}
* User profile picture URL (large) [{userpictureurl lg}]: {userpictureurl lg}
* User profile picture URL (small) [{userpictureimg sm}]: {userpictureimg sm}
* User profile picture URL (medium) [{userpictureimg md}]: {userpictureimg md}
* User profile picture URL (large) [{userpictureimg lg}]: {userpictureimg lg}
* Total number of registered users [{usercount}]: {usercount}
* Total number of active users [{usersactive}]: {usersactive}
* Total number of online users [{usersonline}]: {usersonline}
* Current 4-digit year [{siteyear}]: {siteyear}
* You first accessed the site on [{firstaccessdate strftimedatetime}] : {firstaccessdate strftimedatetime}
* Course or Site full name [{coursename}]: {coursename}
* Course or Site short name [{courseshortname}]: {courseshortname}
* Course start date [{coursestartdate strftimedatetime}]: {coursestartdate strftimedatetime}
* Course end date [{courseenddate strftimedatetime}]: {courseenddate strftimedatetime}
* Completion date [{coursecompletiondate strftimedatetime}]: {coursecompletiondate strftimedatetime}
* Course progress (ALPHA) [{courseprogress}]: {courseprogress}
* Course progress bar (ALPHA) [{courseprogressbar}]: {courseprogressbar}
* Course cards (ALPHA) [{coursecards}]: {coursecards}
* Category cards (ALPHA) [{categorycards}]: {categorycards}
* Total courses [{coursecount}]: {coursecount}
* Institution [{institution}]: {institution}
* Department [{department}]: {department}
* Course ID [{courseid}]: {courseid}
* Course ID (encoded) [%7Bcourseid%7D]: %7Bcourseid%7D
* Course Context ID [{coursecontextid}]: {coursecontextid}
* Course Context ID (encoded) {%coursecontextid%7D]: %coursecontextid%7D
* Course ID number [{courseidnumber}]: {courseidnumber}
* Course custom fields [{coursefields}]: {coursefields}
* Section ID [{sectionid}]: {sectionid}
* Section ID (encoded) [%7Bsectionid%7D]: %7Bsectionid%7D
* Available free application disk space [{diskfreespace}]: {diskfreespace}
* Available free moodledata disk space [{diskfreespacedata}]: {diskfreespacedata}
* My Enrolled Courses [{mycourses}]: {mycourses}
* My Enrolled Courses menu [{mycoursesmenu}]: {mycoursesmenu}
* Course category ID (0 if not in a course or category list of course) [{categoryid}]: {categoryid}
* Course category name (blank if not in a course) [{categoryname}]: {categoryname}
* Course category number (blank if not in a course) [{categorynumber}]: {categorynumber}
* Course category description (blank if not in a course) [{categorydescription}]: {categorydescription}
* Course categories [{categories}]: {categories}
* Course categories menu [{categoriesmenu}]: {categoriesmenu}
* Top level course categories [{categories0}]: {categories0}
* Top level course categories menu [{categories0menu}]: {categories0menu}
* Other course categories in this category [{categoriesx}]: {categoriesx}
* Other course categories in this categories menu [{categoriesxmenu}]: {categoriesxmenu}
* List of custom course fields [{course_fields}]: {course_fields}
* Course custom fields [{course_field_location}] (assumes you have created a custom course field called "location"): {course_field_location}
* Course description [{coursesummary}] : {coursesummary}
* [{courseimage}] : {courseimage}
* Number of participants in the course [{courseparticipantcount}] : {courseparticipantcount}
* The base (root) URL of your Moodle site [{wwwroot}]: {wwwroot}
* WWW for Contact Form [{wwwcontactform}]: {wwwcontactform}
* Page path [{pagepath}]: {pagepath}
* This URL [{thisurl}]: {thisurl}
* This URL encoded [{thisurl_enc}]: {thisurl_enc}
* Double encode this URL (useful for whatsurl parameters) [{urlencode}][{thisurl_enc}][{/urlencode}]: {urlencode}{thisurl_enc}{/urlencode}
* Protocol [{protocol}]: {protocol}
* IP Address [{ipaddress}]: {ipaddress}
* Moodle session key [{sesskey}]: {sesskey}
* Moodle session key [%7Bsesskey%7D]: %7Bsesskey%7D
* Referer [{referer}]: {referer}
* Referrer [{referrer}]: {referrer}
* ReCAPTCHA [{recaptcha}]: {recaptcha}
* Readonly (for form fields when logged-in) [{readonly}]: {readonly}
* Soft hyphen [{-}]: AHyphenWilloOlyAppearHere{-}WhenThereIsNoMoreSpace.
* Non-breaking space [{nbsp}]: This{nbsp}: Is it! (view source code to see the non-breaking space)
* English [{langx en}]Content[{/langx}]: {langx en}Content{/langx}
* String with component [{getstring:filter_filtercodes}]filtername[{/getstring}]: {getstring:filter_filtercodes}filtername{/getstring}
* String [{getstring}]Help[{/getstring}]: {getstring}Help{/getstring}
* Toggle editing menu [{toggleeditingmenu}]: {toggleeditingmenu}
* Editing Toggle [{editingtoggle}]: <a href="{wwwroot}/course/view.php?id={courseid}&sesskey={sesskey}&edit={editingtoggle}">Toggle editing</a>
* FontAwesome "fa-globe": v4.x [{fa fa-globe}] {fa fa-globe}, v5.x [{fas fa-globe}] {fas fa-globe}. Must be supported by your theme.
* Glyphicons "glyphicon-envelope": Glyphicons [{glyphicon glyphicon-envelope}] {glyphicon glyphicon-envelope}. Must be supported by your theme.
* Details/summary [{details}][{summary}]This is the summary[{/summary}] followed by the details.[{/details}]: {details}{summary}This is the summary{/summary} followed by the details.{/details}
* You should not see the following note [{note}]This could be a comment, todo or reminder.[{/note}]: {note}This could be a comment, todo or reminder.{/note}
* Click for [{help}content{/help}] : {help}Enables you to create popup help icons and bubbles just like Moodle does.{/help}
* Click for [{info}content{/info}] : {Info}Enables you to create popup info icons and bubbles just like the Help popup but with an info icon. Useful for adding extra information or hidden tips in your content.{/info}
* {alert}This is an example of an alert box.{/info}
* [{highlight}]This text is highlighted in yellow.[{/highlight}] : {highlight}This text is highlighted in yellow.{/highlight}
* Current language [{lang}] : {lang}
* Display content of custom profile field [{profile_field_shortname}]: Location: {profile_field_location} - assuming you had created a custom profile field with a shortname called 'location'.
* Display profile owner's full name on profile pages [{profilefullname}]: This is the profile of {profilefullname}.
* If you are logged-in as a different user [{ifloggedinas}] : {ifloggedinas}You are logged-in as a different user.{/ifloggedinas}
* If you are NOT logged-in as a different user [{ifloggedinas}] : {ifnotloggedinas}You are logged-in as yourself.{/ifnotloggedinas}
* If Editing mode activated (on) [{ifeditmode}]Don't forget to turn off editing mode![{/ifeditmode}]: {ifeditmode}Don't forget to turn off editing mode!{/ifeditmode}
* If Editing mode is deactivated (off) [{ifnoteditmode}]&lt;a href="{wwwroot}/course/view.php?id={courseid}&sesskey={sesskey}&edit=on"&gt;Turn edit mode on&lt;a/&gt;[{/ifnoteditmode}]: {ifnoteditmode}<a href="{wwwroot}/course/view.php?id={courseid}&sesskey={sesskey}&edit=on">Turn edit mode on</a>{/ifnoteditmode};
* If Enrolled [{ifenrolled}]You are enrolled in this course.[{/ifenrolled}]: {ifenrolled}You are enrolled in this course.{/ifenrolled}
* If Not Enrolled [{ifnotenrolled}]You are not enrolled in this course.[{/ifnotenrolled}]: {ifnotenrolled}You are not enrolled in this course.{/ifnotenrolled}
* If LoggedIn [{ifloggedin}]You are logged-in.[{/ifloggedin}]: {ifloggedin}You are logged-in.{/ifloggedin}
* If LoggedOut [{ifloggedout}]You are logged-out.[{/ifloggedout}]: {ifloggedout}You are logged-out.{/ifloggedout}
* If Guest [{ifguest}]You are a guest.[{/ifguest}]: {ifguest}You are a guest.{/ifguest}
* If Student [{ifstudent}]You are student who is logged-in and enrolled in this course and have no other roles.[{/ifstudent}]: {ifstudent}You are student who is logged-in and enrolled in this course and have no other roles.{/ifstudent}
* If Non-editing Teacher [{ifassistant}]You are an assistant teacher.[{/ifassistant}]: {ifassistant}You are an assistant teacher.{/ifassistant}
* If Non-editing Teacher (minimum) [{ifminassistant}]You are an assistant teacher or above.[{/ifminassistant}]: {ifminassistant}You are an assistant teacher or above.{/ifminassistant}
* If Teacher [{ifteacher}You are a teacher.{/ifteacher}]: {ifteacher}You are a teacher.{/ifteacher}
* If Teacher (minimum) [{ifminteacher}]You are a teacher or above.[{/ifminteacher}]: {ifminteacher}You are a teacher or above.{/ifminteacher}
* If Course Creator [{ifcreator}]You are a course creator.[{/ifcreator}]: {ifcreator}You are a course creator.{/ifcreator}
* If Course Creator (minimum) [{ifmincreator}]You are a course creator or above.[{/ifmincreator}]: {ifmincreator}You are a course creator or above.{/ifmincreator}
* If Manager [{ifmanager}]You are a manager.[{/ifmanager}]: {ifmanager}You are a manager.{/ifmanager}
* If Manager (minimum) [{ifminmanager}]You are a manager or administrator.[{/ifminmanager}]: {ifminmanager}You are a manager or administrator.{/ifminmanager}
* If Site Manager (minimum) [{ifminsitemanager}]You are a site manager or administrator.[{/ifminsitemanager}]: {ifminsitemanager}You are a site manager or administrator.{/ifminsitemanager}
* If Admin [{ifadmin}]You are an administrator.[{/ifadmin}]: {ifadmin}You are an administrator.{/ifadmin}
* If Developer [{ifdev}]You are an administrator with debugging set to developer mode.[{/ifdev}]: {ifdev}You are an administrator.{/ifdev}
* If user has a parent custom role [{ifcustomrole parent}]You have a parent custom role in this context[{/ifcustomrole}]: {ifcustomrole parent}You have a parent custom role in this context{/ifcustomrole}.
* If user does not have a parent custom role [{ifnotcustomrole parent}]You do not have a parent custom role in this context[{/ifnotcustomrole}]: {ifnotcustomrole parent}You do not have a parent custom role in this context{/ifnotcustomrole}.
* If on Home page: {ifhome}You are on the Home Front page.{/ifhome}
* If on Dashboard [{ifdashboard}]You are on the Home Front page.[{/ifdashboard}]: {ifdashboard}You are on the Home Front page.{/ifdashboard}
* If in a course [{ifincourse}]Yes[{/ifincourse}]? {ifincourse}Yes{/ifincourse}
* If in a section of a course [{ifinsection}]Yes[{/ifinsection}][{ifnotinsection}]No[{/ifnotinsection}]? {ifinsection}Yes{/ifinsection}{ifnotinsection}No{/ifnotinsection}
* If Request a course is enabled [{ifcourserequests}]Yes[{/ifcourserequests}]? {ifcourserequests}Yes{/ifcourserequests}
* Are you a member of the "moodlers" cohort [{ifincohort moodlers}]Yes[{/ifincohort}]? {ifincohort moodlers}Yes{/ifincohort} (will be blank of not a member of cohort)
* Is your tenant id 1? [{iftenant 1}]Yes[{/iftenant}] : {iftenant 1}Yes{/iftenant} Note: In Moodle classic, tenant id is assumed to be 1.
* Is this Moodle Workplace? [{ifworkplace}]Yes[{/ifworkplace}] : {ifworkplace}Yes{/ifworkplace}
* This is FilterCodes version [{filtercodes}] : {filtercodes} Note: Will be blank if you do not have the ability to edit this tag.
* Are you a member of the ATEAM group [{ifingroup ATEAM}]Yes[{/ifingroup}] ? : {ifingroup ATEAM}Yes{/ifingroup} Note: Only works in courses.
* It is now [{now}] : {now}
* It is now [{now backupnameformat}] : {now backupnameformat}
* It is now [{now strftimedate}] : {now strftimedate}
* It is now [{now strftimedatemonthabbr}] : {now strftimedatemonthabbr}
* It is now [{now strftimedatefullshort}] : {now strftimedatefullshort}
* It is now [{now strftimedateshort}] : {now strftimedateshort}
* It is now [{now strftimedateshortmonthabbr}] : {now strftimedateshortmonthabbr}
* It is now [{now strftimedatetime}] : {now strftimedatetime}
* It is now [{now strftimedaydate}] : {now strftimedaydate}
* It is now [{now strftimedaydatetime}] : {now strftimedaydatetime}
* It is now [{now strftimedayshort}] : {now strftimedayshort}
* It is now [{now strftimedaytime}] : {now strftimedaytime}
* It is now [{now strftimemonthyear}] : {now strftimemonthyear}
* It is now [{now strftimerecent}] : {now strftimerecent}
* It is now [{now strftimerecentfull}] : {now strftimerecentfull}
* It is now [{now strftimetime}] : {now strftimetime}
* It is now [{now strftimetime12}] : {now strftimetime12}
* It is now [{now strftimetime24}] : {now strftimetime24}

You can switch to different roles to see how each will affect the content being displayed.

### When a user is logged out, the First name, Surname, Full Name, Email address and Username are empty. How can I set default values for these tags?

You can do this using the language editor built into Moodle. There is currently support for the following defaults: defaultfirstname, defaultsurname, defaultusername, defaultemail. By default, these are blank. As for the Full Name, it is made up of the first name and surname separated by a space and is therefore not settable.

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

### Can I use FilterCodes in Moodle's custom menus?

Technically for sure, but only if the theme supports it. If it doesn't, contact the theme's developer and request that they add support for Moodle filters. See instructions in the next question.

**Note:** As of version 1.0.0 of FilterCodes, experimental support was added for Clean and Boost themes in Moodle 3.2 to 3.4. In order to work, this had to be enabled in FilterCodes settings and Filtering will not be applied to the Moodle Theme Settings page. Unfortunately things changed in Moodle 3.5 and it has since not been possible for FilterCodes to do this on its own.

However, there are two ways you can make this work.

1. Encourage Moodle HQ to enable this functionality into future versions of Moodle. For more information and to vote for this functionality, see
   https://github.com/michael-milette/moodle-filter_filtercodes/issues/67 .
2. Encourage developers to enable this in their themes or add it yourself. See the next question for details.

### I am a Moodle theme developer. How do I add support for Moodle filters, including FilterCodes, into my theme?

You will need to pass all output (except user content) through the format_text() or format_string() functions of the [Moodle Output API documentation](https://docs.moodle.org/dev/Output_functions).

### I am a Moodle theme developer. How do I add support for FilterCodes in the Custom Menu?

#### For themes based on **boost**

Add the following code to core_renderer section of your theme. Note: Your theme may even already have such a class (they often do):

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

#### For themes based on the older **bootstrapbase**

Add the following code to core_renderer section of your theme for Moodle 2.7 to 3.6. Be sure to replace "themename" with the name of the theme's directory. Note: Your theme may even already have such a class (they often do):

    class theme_themename_core_renderer extends theme_bootstrapbase_core_renderer {
        /**
         * Applies Moodle filters to custom menu and custom user menu.
         *
         * Copyright: 2017-2020 TNG Consulting Inc.
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
            if (empty($custommenuitems) && !emty($CFG->custommenuitems)) {
                $custommenuitems = $CFG->custommenuitems;
            }
            if (stripos($custommenuitems, '{') !== false) {
                $custommenuitems = $filtermanager->filter_text($custommenuitems, $PAGE->context, $filteroptions, $skipfilters);
            }
            $custommenu = new custom_menu($custommenuitems, current_language());
            return $this->render_custom_menu($custommenu);
        }
    }

### My theme does not support filters in the menu and the developer does not want to fix the theme. What can I do?

You can patch Moodle handle this properly in the first place. Applying the following patch to Moodle will fix the issue in most themes including the default Boost theme.

* Moodle 3.7: https://github.com/michael-milette/moodle/tree/MDL-63219-M37
* Moodle 3.8: https://github.com/michael-milette/moodle/tree/MDL-63219-M38
* Moodle 3.9: https://github.com/michael-milette/moodle/tree/MDL-63219-M39
* Moodle 3.10: https://github.com/michael-milette/moodle/tree/MDL-63219-M310

### Why is the IP Address listed as 0:0:0:0:0:0:0:1?

0:0:0:0:0:0:0:1 is the same as localhost and it means that your web browser is probably on the same computer as your web server. This shouldn't happen with users accessing your Moodle site from their own desktop or mobile device.

### Why does it show me as enrolled on the front page?

The Front Page is a course in Moodle. All users are enrolled by default in this course.

### I added the {recaptcha} tag in my web form. Why doesn't the reCAPTCHA show up?

First, the reCAPTCHA is only made to work with forms processed by the Contact Form for Moodle plugin. That said, it is 100% generated by Moodle API so, if you have some other purpose, it will probably work as well as long as the receiving form is made to process it.

In order for reCAPTCHA to work, you need to configure the site and secret keys in Moodle. For more information, log into your Moodle site as an Administrator and the navigate to Home > Site Administration > Authentication > Manage Authentication and configure the ReCAPTCHA site key and ReCAPTCHA secret key. You will also need to enable ReCAPTCHA in the settings of the Contact Form plugin.

If you are using older versions of Moodle before 3.1.11+, 3.2.8+, 3.3.5+, 3.4.5+ and 3.5+, ReCAPTCHA is no longer supported.

### How can I get the {scrape} tag to work?

You need to enable this feature in the FilterCodes settings in Moodle.

### How can I scrape content from more than one web page or more than one website?

Use multiple {scrape} tags.

### How can I scrape content based on a pattern of HTML tags instead of just one HTML tag with a class or id? Example, an h1 tag inside the div class="content" tag.

That is not possible at this time. This is a very simple scraper. With some funding or contributions, this feature can be enhanced.

### How can I get the {getstring} tag to work? It doesn't seem to be replaced with the correct text.

Verify that the component (plugin) name and/or the string key are correct. If a component name is not specified, it will default to "moodle". If you recently modified a language file manually in Moodle, you may need to refresh the Moodle cache.

### How can I customize or translate the forms generated by the {form...} tags?

You can translate or customize the forms in Moodle's language editor. Note that using these tags are optional. You can also simply insert the HTML for the form in the Atto editor. These {form...} tags are just provided to quickly create generic forms on your Moodle site.

### What are the Supported dateTimeFormat formats?

The date and time formats, defined in Moodle (langconfig.php)[https://github.com/moodle/moodle/blob/master/lang/en/langconfig.php], control how dates and times will be displayed.

At this time, only the following formats are supported:

* backupnameformat
* strftimedate
* strftimedatemonthabbr
* strftimedatefullshort
* strftimedateshort
* strftimedateshortmonthabbr
* strftimedatetime
* strftimedaydate
* strftimedaydatetime
* strftimedayshort
* strftimedaytime
* strftimemonthyear
* strftimerecent
* strftimerecentfull
* strftimetime
* strftimetime12
* strftimetime24

Note: the displayed date and/or time format can vary depending on the language pack in use. While you can customize these using the Moodle language customization tool included with Moodle, be aware that this will have affect displayed dates that use the modified format throughout your Moodle site. Pro tip: Standardize the date format used throughout your site as much as possible in order to minimize the chance of potentially confusing your learners.

ALPHA WARNING: You may discover that you can also use (strftime)[https://www.php.net/manual/en/function.strftime.php] formats. However, in some situations, strftime may produce undesirable results - missing unrelated content in your page for example. The strftime formats are officially unsupported at this time. This is a known issue and is not recommended at this time. Please do not report issues with it. Use strftime formats at your own risk.

### Are there any security considerations?

There are no known security considerations at this time.

## Other questions

Got a burning question that is not covered here? If you can't find your answer, submit your question in the Moodle forums or open a new issue on Github at:

https://github.com/michael-milette/moodle-filter_filtercodes/issues

[(Back to top)](#table-of-contents)

# Contributing

If you are interested in helping, please take a look at our [contributing](https://github.com/michael-milette/moodle-filter_filtercodes/blob/master/CONTRIBUTING.md) guidelines for details on our code of conduct and the process for submitting pull requests to us.

## Contributors

Michael Milette - Author and Lead Developer

comete-upn - {getstring} tag (2018-11-21 - Thanks!).

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

https://github.com/michael-milette/moodle-filter_filtercodes

[(Back to top)](#table-of-contents)

# License

Copyright © 2017-2020 TNG Consulting Inc. - https://www.tngconsulting.ca/

This file is part of FilterCodes for Moodle - https://moodle.org/

FilterCodes is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

FilterCodes is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with FilterCodes.  If not, see <https://www.gnu.org/licenses/>.

[(Back to top)](#table-of-contents)
