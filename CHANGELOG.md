# Change Log
All notable changes to this project will be documented in this file.

## [2.0.5] dev-2021-02-02
### Added
- categoryid parameter for {coursecards categoryid=x} tag (ALPHA).

## [2.1.0] 2020-11-23
### Added
- New {ifingroup id|idnumber}{/ifingroup} tags.
- New {filtercodes} tag. Note: Only works for teachers and above.
- New {alert style}{/alert} tags (ALPHA).
- New {ifincohort idname|idnumber}{/ifincohort} tags.
- New {webpage} tag.
- New {ifnoteditmode} tag.
- New {iftenant idnumber|tenantid}{/iftenant} (ALPHA) tags. (Workplace only - in Moodle classic, tenant is assumed to be 1).
- New {ifworkplace}{/ifworkplace} (ALPHA) tags. (Workplace only - in Moodle classic, will not display tags or content).
- New {timezone} tag.
- New {preferredlanguage} tag.
- New {coursesummary} tag.
- New {firstaccessdate} tag.
- New {formsesskey} tag.
- New Moodle date/time format option for the {firstaccessdate} tag.
- New Moodle date/time format option for the {coursestartdate} tag.
- New Moodle date/time format option for the {courseenddate} tag.
- New Moodle date/time format option for the {coursecompletiondate} tag.
- New {now dateTimeFormat} tag.
- New {ifminsitemanager} tag.
### Updated
- {courseprogress} and {courseprogressbar} now show zero progress if progress is 0.
- {alert} to allow for optional contextual class stying.
- Reorganized and grouped list of tags and made some corrections in the documentation.
- FAQ: Information on how to patch Moodle to enable FilterCodes in the custom menu.
- FAQ: Search the README.md file for the word Troubleshooting to now find helpful information.
- Fixed {diskfreespace} and {diskfreespacedata} on very large/unlimited storage. Note: Greater than about 84,703.29 Yottabyte (YB) is now considered infinite.
- {profile_field_shortname} now supports textarea type custom fields.
- Re-enabled the %7Buserid%7D tag.
- Fixed {courseshortname} so that it displays the site shortname if you are not in a course.
- Should now be passing 100% of the PHPUnit Tests.
- Tested to be compatible up to and including Moodle 3.10.

## [2.0.0] 2020-07-01
### Added
- New configurable setting to enable/disable escaped [{braces}] (e.g. for creating documentation). Default is enabled.
- You can now escape tags so they are not processed by wrapping them in [{brackets}]. {{double-braces}} are no longer supported.
- New {diskfreespacedata} tag.
- New {diskfreespace} tag.
- New {help}{/help} tags.
- New {info}{/info} tags.
- New {ifcustomrole roleshortname}{/{ifcustomrole} tags.
- New {ifnotcustomrole roleshortname}{/{ifnotcustomrole} tags.
- New {userdescription} tag.
- New {categorycards} tag (ALPHA).
- New {coursecards} tag (ALPHA).
- New {courseprogress} tag (ALPHA).
- New {courseprogressbar} tag (ALPHA).
- New {-} tag (soft hyphen)
- New {profilefullname} tag.
- New {ifloggedinas}{/ifloggedinas} tags.
- New {ifnotloggedinas}{/ifnotloggedinas} tags.
- New {categories0} tag.
- New {categories0menu} tag.
- New {categoriesx} tag.
- New {categoriesxmenu} tag.
- New {courseparticipantcount} tag.
- New {course_fields} tag.
- New {course_field_...} tags.
- New {courseimage} tag.
- New {categorydescription} tag.
- New {categorynumber} tag.
- New {categoryname} tag.
- New {categoryid} tag.
- New {lang} tag.
- New {toggleeditingmenu} tag.
- New {ifeditmode}{/ifeditmode} set of tags.
- New {ifdev}{/ifdev} set of tags.
- New {ifcourserequests}{/ifcourserequests} set of tags.
- composer.json
- Separator in menu above Request a Course link (part of {mycoursesmenu} tag).
- New question to FAQ regarding setting filter priorities so that all enabled filters works together.
### Updated
- Tested to be compatible with PHP 7.3 and 7.4.
- Tested to be compatible with Moodle 3.9.
- Read-only name and email address fields are now also disabled in {form...} templates.
- Now checks moodle/course:request capability before creating Course Request link in {ifcourserequests}, {mycourses} and {mycoursemenu}
- No longer identifies Guest users as being logged-in.
- Documentation: FAQ info on how to translate built-in contact forms.
- Documentation to reflect new functionality.
- Updated FAQ.
- .travis.yml and fixed issues.
- Fixed example of Create Course menu item. Now creates a course in the current category.
- Fixed {note} tag which was not working.
### Deprecated (no longer inluded)
- You can no longer escape tags using {{double}} braces. This was causing issues with MathJAX. Bracket your [{tag}] instead.

### Important notes

Some tags, which are indicated in this documentation as ALPHA, may still require some development and are not guarantied to be implementaed or implemented in the same way in future releases. Please let us know if you think they are useful if they work for you or what changes you might like to see.

UI tags are compatible with most Bootrap 4 based themes for Moodle. They have been tested with:

Academi, Adaptable, Aigne, Bandeau, Boost, Classic, Eguru, Enlight Lite, Fordson, Foundation, GCWeb, Klass, Moove, Roshni Lite and Trema.

They were found to be incompatible with the following Moodle themes:

* Boost Campus
* Boost Learning
* Boost Mgnific
* Boost_Training

## [1.1.0] - 2019-11-17
### Added
- You can now escape tags so they are not processed by using a double set of braces {{ and }} around tags.
- If Request a Course is enabled, it will now be appended in {mycourses} and {mycoursesmenu}.
- New {wwwcontactform} tag.
- New {profile_field_...} tags.
- New {formcheckin} tag.
- New {formsupport} tag.
- New {formcourserequest} tag.
- New {formcontactus} tag.
- New {formquickquestion} tag.
- New {thisurl} tag.
- New {thisurl_enc} tag.
- New {urlencode}{/urlencode} set of tags.
- New {highlight}{/highlight} tags.
- New {note} tag.
- New {ifinsection} tag.
- New {ifnotinsection} tag.
- New {ifincourse} tag.
- New {courseidnumber} tag.
- New {coursecontextid} and %coursecontextid%7D tags.
- New {referrer} tag - alias of {referer} previously implemented.
- Missing $string['pluginname'] to language file.
- Added some unit tests.
### Updated
- Fixed some unit tests.
- Fix for {scrape} tag to better handle missing parameters.
- Fixed {langx} tag so that it works correctly with language and culture codes.
- {usersonline} tag now compatible with more than just MySQL/MariaDB.
- Most tags are compatible with Moodle 2.7, 2.8, 2.9, 3.0, 3.1, 3,2, 3.3, 3.4, 3.5, 3.6, 3.7 and now 3.8.
- Documentation to reflect new functionality.

## [1.0.1] - 2019-05-20
### Added
- New {pagepath} tag.
- New {editingtoggle} tag.
- New {idnumber} tag (from user profile).
- New {fa...} tag (for FontAwesome).
- New {glyphicon...} tag (for Glyphicons).
- New {sectionid} and %7Bsectionid%7D tags.
- New {details}, {summary}, {/summary}, {/details} tags (experimental).
- New .travis.yml configuration file for Travis.
- Expanded compatibility - now includes Moodle 2.7, 2.8, 2.9, 3.0, 3.1, 3,2, 3.3, 3.4, 3.5, 3.6 and now 3.7.
### Updated
- Fixed {categories} filter code compatibility with Moodle 2.7 to 3.5.

## [1.0.0] - 2018-11-26
### Added
- New settings page.
- New {getstring} tag.
- New {siteyear} tag - current 4 digit year - useful for copyright notices.
- New {lastname} tag (synonym of {surname}).
- New {courseshortname} tag.
- New {scrape url="..." tag="..." class="..." id="..." code="..."} tag. Must be enabled in FilterCodes settings.
- New {ifhome} tag.
- New {ifdashboard} tag.
- New {coursecount} tag.
- New {usercount} tag.
- New {usersactive} tag.
- New {usersonline} tag.
- New experimental support for Moodle Custom Menu filtering in Boost and Clean (bootstrapbase) themes. Must be enabled in FilterCodes settings and requires Moodle 3.2+.
- Expanded compatibility - now includes Moodle 2.7, 2.8, 2.9, 3.0, 3.1, 3,2, 3.3, 3.4, 3.5 and now 3.6.
### Updated
- No major issues in the last 12 months of BETA - Project status is now STABLE.

## [0.4.6] - 2018-05-22
### Added
- Added support for Privacy API.

## [0.4.5] - 2018-05-18
### Added
- New %7Bsesskey%7D tag as an alternative to {sesskey} for use with encoded URLs.

## [0.4.4] - 2018-05-08
### Added
- New %7Bcourseid%7D tag as an alternative to {courseid} for use with encoded URLs.
- New %7Buserid%7D tag as an alternative to {userid} for use with encoded URLs.
- New {coursestartdate} tag.
- New {courseenddate} tag.
- New {coursecompletiondate} tag.

## [0.4.3] - 2018-03-30
### Added
- Support for reCAPTCHA v2 in Moodle 3.1.11+, 3.2.8+, 3.3.5+, 3.4.5+ and 3.5+.
- FilterCodes upgrade notifications now works properly when a updates are available on Moodle.org.
- Expanded compatibility - now includes Moodle 2.7, 2.8, 2.9, 3.0, 3.1, 3,2, 3.3, 3.4 and 3.5.
### Updated
- Documentation - fixed errors and added FAQ for reCAPTCHA.
- Copyright notice to include 2018.
- Minor performance optimization.

## [0.4.2] - 2017-11-17
### Added
- Example of enabling filters in custom menu and custom user menu in boost based themes.
### Updated
- ReCAPTCHA will now work on https.
- Fixed example of enabling filters in custom menu and custom user menu in bootstrapbase based themes.

## [0.4.0] - 2017-11-11
### Added
Over a dozen new FilterCodes added including:
- New {alternatename} tag.
- New {city} tag.
- New {categories} tag.
- New {categoriesmenu} tag.
- New {country} tag.
- New {coursename} tag.
- New {department} tag.
- New {institution} tag.
- New {mycourses} tag.
- New {mycoursesmenu} tag.
- New {readonly} tag.
- New {sesskey} tag.
- New {userpictureimg X} tag.
- New {userpictureurl X} tag.
- Expanded compatibility now includes Moodle 2.7, 2.8, 2.9, 3.0, 3.1, 3,2, 3.3 and 3.4.
- Added new useful examples of using FilterCodes in custom menus (see Usage section).
- Added CONTRIBUTE.md.
### Updated
- Project status is now BETA.
- Reorganized README.md (New: logo, status badges, table of contents, contributing, etc).
- Default Moodle role IDs are no longer hard coded. {ifrolename} and {ifminrolename} type tags now use role archetypes instead of role shortnames. (thanks @FMCorz !)
- Fixed bug where no country was selected in user's profile.
- Support for Multiple occurences of same {if...}{/if...} tags.
- Support for Multiline spanning of {if...}{/if...} tags.
- {mycourses} and {mycoursesmenu} tags are no longer ever empty.
- {recaptcha} tag now officially supported. For use with Contact Form for Moodle.
- Updated documentation and FAQ.

## [0.3.0] - 2017-09-08
### Added
- Conditional role tags are now aware of switching roles.
- New {ifminassistant}{/ifminassistant} set of tags.
- New {ifminteacher}{/ifminteacher} set of tags.
- New {ifmincreator}{/ifmincreator} set of tags.
- New {ifminmanager}{/ifminmanager} set of tags.
### Updated
- {ifrolename} type tags will now only display content if you have been assigned that particular role.
- Identification of roles no longer depends on the verification of unique capabilities but by role assignment.
- Bug fix: {ifstudent}{/ifstudent} set of tags now work. (thanks @gemguardian !)
- Bug fix: Using {ifenrolled} and {ifnotenrolled} no longer cause a PHP error when used in a course. (thanks @gemguardian !)
- Updated documentation and FAQ.

## [0.2.0] - 2017-07-18
### Added
- New tag: {ifnotenrolled} - Exact logical opposite of {ifenrolled} tag.
### Updated
- Significant performance improvements.
- Language strings are now correctly named.

## [0.1.0] - 2017-07-07
### Added
- Initial public release on Moodle.org and GitHub.
- Plugin officially compatible and tested with Moodle 3.1, 3.2 and 3.3.
