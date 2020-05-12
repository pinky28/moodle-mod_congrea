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
 * Congrea module internal API.
 *
 *
 * @package   mod_congrea
 * @copyright 2020 vidyamantra.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require('weblib.php');

// The function list is avaible in weblib.php.
define('FUNCTIONS_LIST', serialize(array('mod_congrea_poll_save', 'mod_congrea_poll_data_retrieve',
      'mod_congrea_poll_delete', 'mod_congrea_poll_update', 'mod_congrea_poll_result', 'mod_congrea_poll_option_drop', 'core_course_get_enrolled_users_by_cmid',
      'mod_congrea_quiz_list', 'mod_congrea_get_quizdata', 'mod_congrea_add_quiz', 'mod_congrea_quiz_result')));

/** Exit when there is request is happend by options method
 * This generally happens when the request is coming from other domain
 * eg:- if the request is coming from l.vidya.io and main domain suman.moodlehub.com
 * it also know as preflight request
 * serving for virtual class
 *
 */
function exit_if_request_is_options() {
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        exit;
    }
}

/**
 * Function to get data by get method
 * serving for virtual class
 */
function received_get_data() {
    return (isset($_GET)) ? $_GET : false;
}

/**
 * Function to get data by post method
 * serving for virtual class
 */
function received_post_data() {
    return (isset($_POST)) ? $_POST : false;
}

/**
 * The function is executed which is passed by get
 * serving for virtual class
 *
 * @param array $validparameters
 */

function execute_action() {
    $getdata = received_get_data();
    if ($getdata && isset($getdata['methodname'])) {
            $functionlist = unserialize(FUNCTIONS_LIST);
            if (in_array($getdata['methodname'], $functionlist)) {           	
                return $getdata['methodname'];                
            } else {
                throw new Exception('There is no ' . $getdata['methodname'] . ' method to execute.');
            }
    } else {
        throw new Exception('There is no method to execute.');
    }
}


//----------------web service call---------//

$cmid = required_param('cmid', PARAM_INT);
$userid = required_param('user', PARAM_INT);
$token = required_param('token', PARAM_ALPHANUMEXT);

///// XML-RPC CALL
header('Content-Type: text/plain');
require_once($CFG->libdir. "/filelib.php");

$serverurl = $CFG->wwwroot . '/webservice/xmlrpc/server.php'. '?wstoken=' . $token;
$curl = new curl;

exit_if_request_is_options();
$postdata = received_post_data();
/// FUNCTION NAME
$functionname = execute_action();

/// PARAMETERS
switch ($functionname) {
	case 'core_course_get_enrolled_users_by_cmid':        
		$params = array($cmid);
		$post = xmlrpc_encode_request($functionname, $params);
		$resp = xmlrpc_decode($curl->post($serverurl, $post));
        echo json_encode($resp['users']);
        break;          
	case 'mod_congrea_quiz_list':  
		$cmid = (int) $cmid;
		$params = array($cmid);
		$post = xmlrpc_encode_request($functionname, $params);
		$resp = xmlrpc_decode($curl->post($serverurl, $post));

		if(!empty($resp['quizdata'])) {
			$quizdataarray = $resp['quizdata'];
			//changing formate of quiz data for congrea
			$quizdata = array();
			foreach ($quizdataarray as $key => $data){
				$quizdata[$data['id']] = $data;
			}
			echo (json_encode($quizdata));
		} else {
			echo json_encode(array('status' => 0, 'message' => 'Quiz not found'));
		}
		break;
            
	case 'mod_congrea_add_quiz':   
		$cmid = (int) $cmid;
		$qzid = (int) $postdata['qzid'];
		$params = array($cmid, $qzid);
		$post = xmlrpc_encode_request($functionname, $params);
		$resp = xmlrpc_decode($curl->post($serverurl, $post));			
		// Encoded to retain boolean value in js
		echo json_encode($resp['status']);
		break;           
	case 'mod_congrea_quiz_result': 
		$result = array();
		$result['cmid'] = $postdata['cmid'];
		$result['congreaquiz'] = $postdata['qzid'];
		$result['userid'] = $postdata['user'];
		$result['grade'] = $postdata['grade'];
		$result['timetaken'] = $postdata['timetaken'];
		$result['questionattempted'] = $postdata['qusattempted'];
		$result['correctanswer'] = $postdata['currectans'];

		$params = array($result);
		$post = xmlrpc_encode_request($functionname, $params);
		$resp = xmlrpc_decode($curl->post($serverurl, $post));
		// Encoded to retain boolean value in js
		echo json_encode($resp['status']);
		break;          
	case 'mod_congrea_get_quizdata':
		//TODO: change datatype to int, string comming
		$data = array('cmid' => (int) $postdata['cmid'], 'user' => (int) $postdata['user'], 'qid' => (int) $postdata['qid']);
		$params = array($data);
		$post = xmlrpc_encode_request($functionname, $params);
		$resp = xmlrpc_decode($curl->post($serverurl, $post));
		echo (json_encode($resp));
		break;        	
	case 'mod_congrea_poll_data_retrieve' :        
		$category = (int) $postdata['category'];
		$user = (int) $postdata['user'];
		
		$params = array($category, $user);
		$post = xmlrpc_encode_request($functionname, $params);
		$resp = xmlrpc_decode($curl->post($serverurl, $post));
		//var_dump($resp);exit;
		//TODO: nopoll return redefine
		if (array_key_exists('faultString', $resp)) {
			echo json_encode(array("true"));
		} else {
			$resp['responsearray'][] = $resp['admin'];
			echo json_encode($resp['responsearray']);
		}
		break;           
	case 'mod_congrea_poll_save':        
		$cmid = (int) $cmid;
		//$data = array('dataToSave'=>'{"question":"sfsdaa","options":["1","2"],"action":"newPollSave","category":"3","copied":false}', 'user'=>4);
		$data = array('dataToSave'=> $postdata['dataToSave'], 'user' => $postdata['user']);
		$params = array($cmid, $data);
		$post = xmlrpc_encode_request($functionname, $params);
		$resp = xmlrpc_decode($curl->post($serverurl, $post));
		//print_r($resp);exit;
		$return  = (!empty($resp['pollobject'])) ? json_encode($resp['pollobject']): get_string('nopollsave', 'congrea');
		echo $return;
		break;           
	case 'mod_congrea_poll_delete':       
		$qid = (int) $postdata['qid'];
		$user = (int) $postdata['user'];
		$params = array($qid, $user);
		$post = xmlrpc_encode_request($functionname, $params);
		$resp = xmlrpc_decode($curl->post($serverurl, $post));
		echo $resp['category'];
		break;
	case 'mod_congrea_poll_option_drop':
		//TODO: Not working- Fix option id 
		$cmid = (int) $cmid;      
		$polloptionid = (int) $postdata['id'];
		print_r($postdata);exit;
		$params = array($cmid, $polloptionid);
		$post = xmlrpc_encode_request($functionname, $params);
		$resp = xmlrpc_decode($curl->post($serverurl, $post));
		$return = $resp['status'] ? json_encode($resp['status']) : get_string('deletepolloption', 'congrea');
		echo $return;
		break;
	case 'mod_congrea_poll_update':
		//TODO: NOT WORKING- option missing       
		$cmid = (int) $cmid;
		//print_r($postdata);exit;
		$data = array('dataToUpdate'=> $postdata['editdata'], 'user' => $postdata['user']);
		$params = array($cmid, $data);
		$post = xmlrpc_encode_request($functionname, $params);
		$resp = xmlrpc_decode($curl->post($serverurl, $post));
		//print_r($resp);exit;			
		$return  = (!empty($resp['pollobject'])) ? json_encode($resp['pollobject']): get_string('nopollsave', 'congrea');
		echo $return;
		break;
	case 'mod_congrea_poll_result':        
		$cmid = (int) $cmid;
		$data = array('resultdata'=> $postdata['saveResult'], 'user' => $postdata['user']);
		$params = array($cmid, $data);
		$post = xmlrpc_encode_request($functionname, $params);
		$resp = xmlrpc_decode($curl->post($serverurl, $post));
		echo $resp['category'];
		break;
    }
