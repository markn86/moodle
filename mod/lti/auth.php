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
 * This file returns an array of available public keys
 *
 * @package    mod_lti
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/lti/locallib.php');

$scope = optional_param('scope', '', PARAM_TEXT);
$responsetype = optional_param('response_type', '', PARAM_TEXT);
$clientid = optional_param('client_id', '', PARAM_TEXT);
$redirecturi = optional_param('redirect_uri', '', PARAM_TEXT);
$loginhint = optional_param('login_hint', '', PARAM_TEXT);
$ltimessagehint = optional_param('lti_message_hint', '', PARAM_TEXT);
$state = optional_param('state', '', PARAM_TEXT);
$responsemode = optional_param('response_mode', '', PARAM_TEXT);

$ok = !empty($scope) && !empty($responsetype) && !empty($clientid) && !empty($redirecturi) && !empty($loginhint) && !empty($scope) &&
      !empty($ltimessagehint) && !empty($SESSION->lti_message_hint);

if (!$ok) {
    $error = 'invalid_request';
}
if ($ok) {
    $scopes = explode(' ', $scope);
    $ok = in_array('openid', $scopes);
    if (!$ok) {
        $error = 'invalid_scope';
    }
}
if ($ok && ($responsetype !== 'code')) {
    $ok = false;
    $error = 'unsupported_response_type';
}
if ($ok) {
    list($typeid, $id) = explode(',', $SESSION->lti_message_hint, 2);
    $config = lti_get_type_type_config($typeid);
    $ok = ($clientid === $config->lti_clientid);
    if (!$ok) {
        $error = 'unauthorized_client';
    }
}
if ($ok && ($loginhint !== $USER->id)) {
    $ok = false;
    $error = 'access_denied';
}
if ($ok) {
    $uris = explode("\n", $config->lti_redirectionuris);
    $ok = in_array($redirecturi, $uris);
    if (!$ok) {
        $error = 'invalid_request';
        $desc = 'Unregistered redirect_uri';
    }
}
if ($ok) {
    if (isset($responsemode)) {
        $ok = ($responsemode === 'form_post') || ($responsemode === 'query');
        if (!$ok) {
            $error = 'invalid_request';
            $desc = 'Invalid response_mode';
        }
    } else {
        $responsemode = 'query';
    }
}

if ($ok) {
    $cm = get_coursemodule_from_id('lti', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $context = context_module::instance($cm->id);

    require_login($course, true, $cm);
    require_capability('mod/lti:view', $context);

    $lti = $DB->get_record('lti', array('id' => $cm->instance), '*', MUST_EXIST);
    list($endpoint, $params) = lti_get_launch_data($lti);
} else {
    $params['error'] = $error;
    if (!empty($desc)) {
        $params['error_description'] = $desc;
    }
}
if (isset($state)) {
    $params['state'] = $state;
}
if ($responsemode !== 'query') {
    $r = '<form action="' . $redirecturi . "\" name=\"ltiAuthForm\" id=\"ltiAuthForm\" method=\"post\" enctype=\"application/x-www-form-urlencoded\">\n";
    if (!empty($params)) {
        foreach ($params as $key => $value) {
            $key = htmlspecialchars($key);
            $value = htmlspecialchars($value);
            $r .= "  <input type=\"hidden\" name=\"{$key}\" value=\"{$value}\"/>\n";
        }
    }
    $r .= "</form>\n";
    $r .= "<script type=\"text/javascript\">\n" .
        "//<![CDATA[\n" .
        "document.ltiAuthForm.submit();\n" .
        "//]]>\n" .
        "</script>\n";
    echo $r;
} else {
    $url = new \moodle_url(redirecturi, $params);
    redirect($url->out(false));
}

?>