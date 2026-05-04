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
 * Version information for FilterCodes.
 *
 * @package    filter_filtercodes
 * @copyright  2017-2026 TNG Consulting Inc. - {@link https://www.tngconsulting.ca/}
 * @author     Michael Milette
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version      = 2026050200;            // The current plugin version (Date: YYYYMMDDXX).
$plugin->requires     = 2014051200;            // Requires Moodle version 2.7 or later.
$plugin->component    = 'filter_filtercodes';  // Full name of the plugin (used for diagnostics).
$plugin->release      = '2.7.3';
$plugin->supported    = [27, 311];             // Explicitly supports Moodle 2.7 to 3.11.
$plugin->incompatible = 400;                   // Incompatible with Moodle 4.0 and above.
$plugin->maturity     = MATURITY_STABLE;
