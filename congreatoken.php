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
 * Return token
 * @package    moodlemod congrea
 * @copyright  2020 Pinky Sharma
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('AJAX_SCRIPT', true);
define('REQUIRE_CORRECT_ACCESS', true);
//define('NO_MOODLE_COOKIES', false);

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->libdir . '/externallib.php');
//pass sesskey from calling function sesskey(); 
//https://192.168.43.20/moodle38/mod/congrea/congreatoken.php?sesskey=MKGMdwxcjj&wsid=3

//$serviceid = required_param('wsid', PARAM_INT);
$sesskey = required_param('sesskey',PARAM_ALPHANUMEXT);

require_login();
require_sesskey();

/**
 * Generate or return an existing token for the current authenticated user.
 * This function is used for creating a valid token for logged in users for congrea
 *
 * @param stdClass $service external service object
 * @return stdClass token object
 * @since Moodle 3.8
 * @throws moodle_exception
 */
function congrea_generate_token_for_current_user($service) {
    global $DB, $USER, $CFG;

    core_user::require_active_user($USER, true, true);

    // Check if there is any required system capability.
    if ($service->requiredcapability and !has_capability($service->requiredcapability, context_system::instance())) {
        throw new moodle_exception('missingrequiredcapability', 'webservice', '', $service->requiredcapability);
    }

    // Specific checks related to user restricted service.
    if ($service->restrictedusers) {
        $authoriseduser = $DB->get_record('external_services_users',
            array('externalserviceid' => $service->id, 'userid' => $USER->id));

        if (empty($authoriseduser)) {
            throw new moodle_exception('usernotallowed', 'webservice', '', $service->name);
        }

        if (!empty($authoriseduser->validuntil) and $authoriseduser->validuntil < time()) {
            throw new moodle_exception('invalidtimedtoken', 'webservice');
        }

        if (!empty($authoriseduser->iprestriction) and !address_in_subnet(getremoteaddr(), $authoriseduser->iprestriction)) {
            throw new moodle_exception('invalidiptoken', 'webservice');
        }
    }

    // Check if a token has already been created for this user and this service.
    $conditions = array(
        'userid' => $USER->id,
        'externalserviceid' => $service->id,
        'tokentype' => EXTERNAL_TOKEN_EMBEDDED
    );
    $tokens = $DB->get_records('external_tokens', $conditions, 'timecreated ASC');

    // A bit of sanity checks.
    foreach ($tokens as $key => $token) {

        // Checks related to a specific token. (script execution continue).
        $unsettoken = false;
        // If sid is set then there must be a valid associated session no matter the token type.
        if (!empty($token->sid)) {
            if (!\core\session\manager::session_exists($token->sid)) {
                // This token will never be valid anymore, delete it.
                $DB->delete_records('external_tokens', array('sid' => $token->sid));
                $unsettoken = true;
            }
        }

        // Remove token is not valid anymore.
        if (!empty($token->validuntil) and $token->validuntil < time()) {
            $DB->delete_records('external_tokens', array('token' => $token->token, 'tokentype' => EXTERNAL_TOKEN_EMBEDDED));
            $unsettoken = true;
        }

        // Remove token if its ip not in whitelist.
        if (isset($token->iprestriction) and !address_in_subnet(getremoteaddr(), $token->iprestriction)) {
            $unsettoken = true;
        }

        if ($unsettoken) {
            unset($tokens[$key]);
        }
    }

    // If some valid tokens exist then use the most recent.
    if (count($tokens) > 0) {
        $token = array_pop($tokens);
    } else {
        $context = context_system::instance();
        //$isofficialservice = $service->shortname == 'moodle_congrea';

        /*if (($isofficialservice and has_capability('moodle/webservice:createmobiletoken', $context)) or
                (!is_siteadmin($USER) && has_capability('moodle/webservice:createtoken', $context))) {*/
        if ( !is_siteadmin($USER) && has_capability('moodle/webservice:createtoken', $context)) {

            // Create a new token.
            $token = new stdClass;
            $token->token = md5(uniqid(rand(), 1));
            $token->userid = $USER->id;
            $token->tokentype = EXTERNAL_TOKEN_EMBEDDED;
            $token->contextid = context_system::instance()->id;
            $token->creatorid = $USER->id;
            $token->timecreated = time();
            $token->externalserviceid = $service->id;
            // By default tokens are valid for 12 weeks.
            //$token->validuntil = $token->timecreated + $CFG->tokenduration;
            $token->validuntil = 0;
            $token->iprestriction = null;
            $token->sid = session_id();
            $token->lastaccess = null;
            // Generate the private token, it must be transmitted only via https.
            $token->privatetoken = random_string(64);
            $token->id = $DB->insert_record('external_tokens', $token);

            $eventtoken = clone $token;
            $eventtoken->privatetoken = null;
            $params = array(
                'objectid' => $eventtoken->id,
                'relateduserid' => $USER->id,
                'other' => array(
                    'auto' => true
                )
            );
            $event = \core\event\webservice_token_created::create($params);
            $event->add_record_snapshot('external_tokens', $eventtoken);
            $event->trigger();
        } else {
            throw new moodle_exception('cannotcreatetoken', 'webservice', '', $service->shortname);
        }
    }
    return $token;
}


//echo $OUTPUT->header();
if (!$CFG->enablewebservices) {
    throw new moodle_exception('enablewsdescription', 'webservice');
}
//$usercontext = context_user::instance($USER->id);

//check if the service exists and is enabled
$service = $DB->get_record('external_services', array('name' => 'Congrea service', 'enabled' => 1));
if (empty($service)) {
	// will throw exception if no token found
	throw new moodle_exception('servicenotavailable', 'webservice');
}
if ($USER->id) {
    // Get an existing token or create a new one.
    $token = congrea_generate_token_for_current_user($service);
    //$token = external_generate_token(EXTERNAL_TOKEN_EMBEDDED, $serviceid, $USER->id, $context);
    //print_r($token );exit;
    $privatetoken = $token->privatetoken;
    external_log_token_request($token);

    $systemcontext = context_system::instance();
    $siteadmin = has_capability('moodle/site:config', $systemcontext, $USER->id);
 	$usertoken = new stdClass;
    $usertoken->token = $token->token;
    // Private token, only transmitted to https sites and non-admin users.
    if (is_https() and !$siteadmin) {
        $usertoken->privatetoken = $privatetoken;
    } else {
        $usertoken->privatetoken = null;
    }
    echo json_encode($usertoken);

}else{
	throw new moodle_exception('invalidlogin');
}


