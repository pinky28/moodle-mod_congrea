<?php

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
 * Congrea external functions and service definitions.
 *
 * @package		mod_congrea
 * @copyright  	2020 Pinky Sharma
 * @license    	http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later 
 * @ since		Moodle 3.8	
 */

// We defined the web service functions to install.
$functions = array(

         'mod_congrea_quiz_list' => array(
                'classname'   => 'mod_congrea_external',
                'methodname'  => 'quiz_list',
                'classpath'   => 'mod/congrea/externallib.php',
                'description' => 'Return list of all multiplechoice quiz of given course',
                'type'        => 'read',
                'capabilities'=> 'mod/congrea:view',
                'services'	  => array('Moodle Congrea Service')		
        ),
        'mod_congrea_add_quiz' => array(
                'classname'   => 'mod_congrea_external',
                'methodname'  => 'add_quiz',
                'classpath'   => 'mod/congrea/externallib.php',
                'description' => 'Attached a quiz with conrea activity',
                'type'        => 'write',
                'capabilities'=> 'mod/congrea:view',
                'services'	  => array('Moodle Congrea Service')		
        ),
        'mod_congrea_quiz_result' => array(
                'classname'   => 'mod_congrea_external',
                'methodname'  => 'quiz_result',
                'classpath'   => 'mod/congrea/externallib.php',
                'description' => 'Save quiz grade in moodle database',
                'type'        => 'write',
                'capabilities'=> 'mod/congrea:view',
                'services'	  => array('Moodle Congrea Service')		
        ),
        'mod_congrea_get_quizdata' => array(
                'classname'   => 'mod_congrea_external',
                'methodname'  => 'get_quizdata',
                'classpath'   => 'mod/congrea/externallib.php',
                'description' => 'Return quiz data from moodle database',
                'type'        => 'write',
                'capabilities'=> 'mod/congrea:view',
                'services'	  => array('Moodle Congrea Service')		
        ),
        'mod_congrea_poll_save' => array(
                'classname'   => 'mod_congrea_external',
                'methodname'  => 'poll_save',
                'classpath'   => 'mod/congrea/externallib.php',
                'description' => 'Save the poll data in moodle created via congrea interface',
                'type'        => 'write',
                'capabilities'=> 'mod/congrea:view',
                'services'	  => array('Moodle Congrea Service')		
        ),
        'mod_congrea_poll_data_retrieve' => array(
                'classname'   => 'mod_congrea_external',
                'methodname'  => 'poll_data_retrieve',
                'classpath'   => 'mod/congrea/externallib.php',
                'description' => 'Retrive poll data saved in moodle database',
                'type'        => 'read',
                'capabilities'=> 'mod/congrea:view',
                'services'	  => array('Moodle Congrea Service')		
        ),
        'mod_congrea_poll_delete' => array(
                'classname'   => 'mod_congrea_external',
                'methodname'  => 'poll_delete',
                'classpath'   => 'mod/congrea/externallib.php',
                'description' => 'Delete poll data saved in moodle database',
                'type'        => 'write',
                'capabilities'=> 'mod/congrea:view',
                'services'	  => array('Moodle Congrea Service')		
        ),
        'mod_congrea_poll_option_drop' => array(
                'classname'   => 'mod_congrea_external',
                'methodname'  => 'poll_option_drop',
                'classpath'   => 'mod/congrea/externallib.php',
                'description' => 'Delete a poll option saved in moodle database',
                'type'        => 'write',
                'capabilities'=> 'mod/congrea:view',
                'services'	  => array('Moodle Congrea Service')		
        ),
        'mod_congrea_poll_result' => array(
                'classname'   => 'mod_congrea_external',
                'methodname'  => 'poll_result',
                'classpath'   => 'mod/congrea/externallib.php',
                'description' => 'Save poll result in moodle database',
                'type'        => 'write',
                'capabilities'=> 'mod/congrea:view',
                'services'	  => array('Moodle Congrea Service')		
        ),
        'mod_congrea_poll_update' => array(
                'classname'   => 'mod_congrea_external',
                'methodname'  => 'poll_update',
                'classpath'   => 'mod/congrea/externallib.php',
                'description' => 'Update poll data in moodle database',
                'type'        => 'write',
                'capabilities'=> 'mod/congrea:addinstance',
                'services'	  => array('Moodle Congrea Service')		
        ),
);

//We define the services to install as pre-build services. A pre-build service is not editable by administrator.
$services = array(
         'Congrea service' => array(
                 'functions' => array ('core_course_get_enrolled_users_by_cmid','mod_congrea_quiz_list',
                 	'mod_congrea_add_quiz','mod_congrea_quiz_result','mod_congrea_get_quizdata',
                 	'mod_congrea_poll_data_retrieve','mod_congrea_poll_save','mod_congrea_poll_delete',
                 	'mod_congrea_poll_option_drop', 'mod_congrea_poll_result', 'mod_congrea_poll_update'),
                 'restrictedusers' => 0,
                 'enabled'=>1,
         	)
          );
