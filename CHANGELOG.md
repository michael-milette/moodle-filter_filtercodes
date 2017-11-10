# Change Log
All notable changes to this project will be documented in this file.

## [0.3.0] - 2017-11-11
### Added
- Conditional role tags are now aware of switching roles.
- New {ifminassistant}{/ifminassistant} set of tags.
- New {ifminteacher}{/ifminteacher} set of tags.
- New {ifmincreator}{/ifmincreator} set of tags.
- New {ifminmanager}{/ifminmanager} set of tags.
- New {alternatename} tag.
- New {city} tag.
- New {country} tag.
- New {coursename} tag.
- New {institution} tag.
- New {department} tag.
- New {userpictureurl X} tag.
- New {userpictureimg X} tag.
- New {mycourses} tag.
- New {mycoursesmenu} tag.
- New {sesskey} tag.
- New {categories} tag.
- New {categoriesmenu} tag.
- New {readonly} tag.
- Added CONTRIBUTE.md.
- Is now compatible with Moodle 3.4.
- Is now compatible with Moodle 3.0.
- Is now compatible with Moodle 2.9.
- Is now compatible with Moodle 2.8.
- Is now compatible with Moodle 2.7.
- Support for Multiple occurences of same {if...}{/if...} tags.
- Multiline spanning of {if...}{/if...} tags.
- Useful examples of using FilterCodes in custom menus (see Usage section).
### Updated
- {ifrolename} type tags will now only display content if you have been assigned that particular role.
- Identification of roles no longer depends on the verification of unique capabilities but by role assignment.
- Bug fix: {ifstudent}{/ifstudent} set of tags now work. (thanks @gemguardian !)
- Bug fix: Using {ifenrolled} and {ifnotenrolled} no longer cause a PHP error when used in a course. (thanks @gemguardian !)
- Updated documentation and FAQ.
- Reorganized README.md (New: logo, status badges, table of contents, contributing, etc).
- Default Moodle role IDs are no longer hard coded. {ifrolename} and {ifminrolename} type tags now use role archetypes instead of role shortnames. (thanks @FMCorz !)
- Fixed bug where no country was selected in user's profile.
- Project status is now BETA.
- {mycourses} and {mycoursesmenu} tags are no longer ever empty.
- {recaptcha} tag now officially supported. For use with Contact Form for Moodle.

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
