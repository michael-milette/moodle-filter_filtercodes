<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Render and display a QR link.
 *
 * @package    local_qrlinks
 * @copyright  2016 Nicholas Hoobin <nicholashoobin@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

//header('Content-Type: image/png');

use Endroid\QrCode\QrCode;
require_once("thirdparty/QrCode/src/QrCode.php");

$data = new moodle_url('/');

function qrcode($text, $label = '') {
    if (empty($text)) {
        return '';
    }
    $code = new QrCode();
    $code->setText($text);
    $code->setErrorCorrection('high');
    $code->setPadding(6);
    $code->setSize(480);
    $code->setLabelFontSize(16);
    $code->setLabel($label);
    $src = 'data:image/png;base64,' . base64_encode($code->get('png'));
    return $src;
}
$text = "œil du garçon héroïque";

$src = qrcode($text, $text);
$src = '<div style="width:300px"><img src="' . $src . '" style="width:100%;height:auto;background-color:red;"></div>';
echo $src;
