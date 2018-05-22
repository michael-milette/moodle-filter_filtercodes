# Change Log
All notable changes to this project will be documented in this file.

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
