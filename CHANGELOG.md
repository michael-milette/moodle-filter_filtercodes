# Change Log
All notable changes to this project will be documented in this file.

## [0.3.0] - 2017-10-30
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
- Added CONTRIBUTE.md.
### Updated
- {ifrolename} type tags will now only display content if you have been assigned that particular role.
- Identification of roles no longer depends on the verification of unique capabilities but by role assignment.
- Bug fix: {ifstudent}{/ifstudent} set of tags now work. (thanks @gemguardian !)
- Bug fix: Using {ifenrolled} and {ifnotenrolled} no longer cause a PHP error when used in a course. (thanks @gemguardian !)
- Updated documentation and FAQ.
- Reorganized README.md (New: logo, status badges, table of contents, contributing, etc).
- Default Moodle role IDs are no longer hard coded but must exist including: 'manager', 'coursecreator', 'editingteacher', 'teacher', 'student'.
- Has been tested with Moodle 3.4.

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
