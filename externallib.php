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
 * Congrea module external functions
 *
 * @package    mod_congrea
 * @copyright  2020 Pinky Sharma
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since	   Moodle 3.8
 */
require_once($CFG->libdir . "/externallib.php");
require_once($CFG->dirroot . '/mod/congrea/locallib.php');

class mod_congrea_external extends external_api {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function quiz_list_parameters() {
        return new external_function_parameters(
            array(
                'cmid' => new external_value(PARAM_INT, 'course module id')
            )
        );
    }

    /**
     * Return the quizes of a course.
     *
     * @param int $cmid the course module id
     * @return array of warnings and status result
     * @since Moodle 3.8
     * @throws moodle_exception
     */
    public static function quiz_list($cmid) {
        global $CFG, $DB;

        $params = self::validate_parameters(self::quiz_list_parameters(),
                                            array(
                                                'cmid' => $cmid
                                            ));
        $warnings = array();

        // Request and permission validation.
		if (!$cm = get_coursemodule_from_id('congrea', $params['cmid'], 0, false, MUST_EXIST)) {
            throw new moodle_exception("invalidcoursemodule", "error");
        }
        //Context validation
        $context = context_module::instance($cm->id);
        self::validate_context($context);

        //Capability checking
        
        if (!has_capability('mod/congrea:managequiz', $context)) {
             throw new required_capability_exception($context, 'mod/congrea:managequiz', 'nopermissions', '');
         }

    	$quizes = $DB->get_records(
            'quiz', array('course' => $cm->course), null, 'id, name, course, timelimit, preferredbehaviour, questionsperpage'
    	);         
		$result = array();

		if(!$quizes){
			throw new moodle_exception("noquize", "error");
		}
    	
		foreach ($quizes as $data) {
			$questiontype = congrea_question_type($data->id); // Check quiz question type is multichoce or not.
			if ($questiontype) {
				$quizcm = get_coursemodule_from_instance('quiz', $data->id, $data->course, false, MUST_EXIST);
				if ($quizcm->id && $quizcm->visible) {
					$quizstatus = 0;
					if ($CFG->version >= 2016120500) { // Compare with moodle32 version.
						$quizstatus = $DB->get_field(
							'course_modules', 'deletioninprogress',
							array('id' => $quizcm->id, 'instance' => $data->id, 'course' => $data->course)
						);
					}
					$quizdata[$data->id] = (object) array(
							'id' => $data->id,
							'name' => $data->name,
							'timelimit' => $data->timelimit,
							'preferredbehaviour' => $data->preferredbehaviour,
							'questionsperpage' => $data->questionsperpage,
							'quizstatus' => $quizstatus
					);
					
				}
			}
		}
		
        //return (json_encode($result));
        $result['quizdata'] = $quizdata;
        $result['warnings'] = $warnings;
        return $result;
    }
    
   /**
	* Describes the mod_congrea_quiz_list return value.
	*
	* @return external_single_structure
	* @since Moodle 3.8
	*/
    public static function quiz_list_returns() {
    	//return new external_value(PARAM_RAW, 'Description');
        return new external_single_structure(
            array(
                'quizdata' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Quiz id'),
                            'name' => new external_value(PARAM_RAW, 'Quiz name', VALUE_OPTIONAL),
                            'timelimit' => new external_value(PARAM_INT, 'Quiz time limit', VALUE_OPTIONAL),
                            'preferredbehaviour' => new external_value(PARAM_RAW, 'Question type', VALUE_OPTIONAL),
                            'questionsperpage' => new external_value(PARAM_INT, 'Question per page', VALUE_OPTIONAL),
                            'quizstatus' => new external_value(PARAM_BOOL, 'Allow multiple choices', VALUE_OPTIONAL),                 
                        )
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }
    
   /**
	* Returns description of method parameters
	*
	* @return external_function_parameters
	* @since Moodle 3.0
	*/
    public static function add_quiz_parameters() {
        return new external_function_parameters(
            array(
                'cmid' => new external_value(PARAM_INT, 'course module id'),
                'qzid' => new external_value(PARAM_INT, 'quiz id')
            )
        );
    }
    
    /**
     * Attached quiz with Congrea activity.
     *
     * @param int $cmid the course module id
     * @param int $qzid the quiz id
     * @return array of warnings and status result
     * @since Moodle 3.8
     * @throws moodle_exception
     */
	public static function add_quiz($cmid, $qzid) {
	   global $DB;

	   $params = self::validate_parameters(self::add_quiz_parameters(),
										   array(
											   'cmid' => $cmid,
											   'qzid' => $qzid
										   ));
	   $warnings = array();

	   // Request and permission validation.
	   if (!$cm = get_coursemodule_from_id('congrea', $params['cmid'], 0, false, MUST_EXIST)) {
		   throw new moodle_exception("invalidcoursemodule", "error");
	   }
	   //Context validation
	   $context = context_module::instance($cm->id);
	   self::validate_context($context);

	   //Capability checking
	   if (!has_capability('mod/congrea:managequiz', $context)) {
			throw new required_capability_exception($context, 'mod/congrea:managequiz', 'nopermissions', '');
		}
	
	   $status =  false;
	   if ($DB->record_exists('congrea_quiz', array('congreaid' => $cm->instance, 'quizid' => $params['qzid']))) {
		   $status =  true;
	   } else {
		// Quiz not linked with congrea.
		   $data = new stdClass();
		   $data->congreaid = $cm->instance;
		   $data->quizid = $params['qzid'];
		   if ($DB->insert_record('congrea_quiz', $data)) {
			   $status =  true;
		   }
	   }
	   
	   //return (json_encode($result));
	   return array(
		   'status' => $status,
		   'warnings' => $warnings
	   );
	}
    /**
     * Describes the add_quiz return value.
     *
     * @return external_multiple_structure
     * @since Moodle 3.8
     */
    public static function add_quiz_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status, true if quiz attached with congrea'),
                'warnings' => new external_warnings(),
            )
        );
    }    

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function quiz_result_parameters() {
        return new external_function_parameters(
            array(
                'quizresult' => new external_single_structure(
                	array(
                		'cmid' => new external_value(PARAM_INT, 'course module id'),
    					'congreaquiz' => new external_value(PARAM_INT, 'Congrea quiz id'),
    					'userid' => new external_value(PARAM_INT, 'User id'),
    					'grade' => new external_value(PARAM_FLOAT, 'Total grade'),
    					'timetaken' => new external_value(PARAM_INT, 'Congrea quiz id'),
    					'questionattempted' => new external_value(PARAM_INT, 'Congrea quiz id'),
    					'correctanswer' => new external_value(PARAM_INT, 'Correct answer'),
                	)
                )                
            )
        );
    }
    
    /**
     * Save congrea quiz result.
     *
     * @param int $cmid the course module id
     * @param int $qzid the quiz id
     * @return array of warnings and status result
     * @since Moodle 3.8
     * @throws moodle_exception
     */
    public static function quiz_result($quizresult) {
        global $DB;

        $params = self::validate_parameters(self::quiz_result_parameters(),
                                            array(
                                                'quizresult' => $quizresult
                                            ));
        $warnings = array();
		
        // Request and permission validation.
		if (!$cm = get_coursemodule_from_id('congrea', $params['quizresult']['cmid'], 0, false, MUST_EXIST)) {
            throw new moodle_exception("invalidcoursemodule", "error");
        }
        //Context validation
        $context = context_module::instance($cm->id);
        self::validate_context($context);

        //Capability checking
        if (!has_capability('mod/congrea:managequiz', $context)) {
             throw new required_capability_exception($context, 'mod/congrea:managequiz', 'nopermissions', '');
         }

        $conquizid = $DB->get_field('congrea_quiz', 'id', array('congreaid' => $cm->instance, 'quizid' => $params['quizresult']['congreaquiz']));
    	
    	if ($conquizid) {
        	// Save grade.
        	$params['quizresult']['timecreated']= time(); 
        	$resultobject = (object)$params['quizresult'];
        	
        	if ($DB->insert_record('congrea_quiz_grade', $resultobject)) {
            	$status =  true;
        	} else {
        		$status =  false;
            	//echo 'Grade not saved';
        	}
    	}
          	
        return array(
            'status' => $status,
            'warnings' => $warnings
        );
    }
 
     /**
     * Describes the add_quiz return value.
     *
     * @return external_multiple_structure
     * @since Moodle 3.8
     */
    public static function quiz_result_returns() {
    	//return new external_value(PARAM_RAW, 'Description');
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status, true if quiz result saved'),
                'warnings' => new external_warnings(),
            )
        );
    } 
    
    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function get_quizdata_parameters() {
    
		return new external_function_parameters(
			array(
                'data' => new external_single_structure(
                	array(
                		'cmid' => new external_value(PARAM_INT, 'course module id'),
    					'user' => new external_value(PARAM_INT, 'User id'),
                		'qid' => new external_value(PARAM_INT, 'Congrea quiz id')
                	)
                )                
            )
        );
    } 
    
	
    /**
     * Get the quizjson object from given quiz instance
     *
     * @param int $cmid the course module id
     * @param array $data 
     * @return array of warnings and status result
     * @since Moodle 3.8
     * @throws moodle_exception
     */
    public static function get_quizdata($data) {
        global $DB, $CFG;
		
        $params = self::validate_parameters(self::get_quizdata_parameters(),
                                            array(
                                                'data' => $data
                                            ));
        $warnings = array();
		
        // Request and permission validation.
		if (!$cm = get_coursemodule_from_id('congrea', $params['data']['cmid'], 0, false, MUST_EXIST)) {
            throw new moodle_exception("invalidcoursemodule", "error");
        }
        //Context validation
        $context = context_module::instance($cm->id);
        self::validate_context($context);

        //Capability checking        
        if (!has_capability('mod/congrea:managequiz', $context)) {
             throw new required_capability_exception($context, 'mod/congrea:managequiz', 'nopermissions', '');
        }
         
        if (!$qzcm = get_coursemodule_from_instance('quiz', $params['data']['qid'], $cm->course)) {
         	throw new moodle_exception("invalidcoursemodule", "error");
    	}
    	
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        $quizobj = quiz::create($qzcm->instance, $params['data']['user']);
        
        //Check questions
        if (!$quizobj->has_questions()) {
        	throw new Moodle_quiz_exception($quizobj, 'noquestionsfound');
    	}
    	 
		$quizjson = array();
    	$questions = array();    	
        
		if (empty($quizjson)) {
			$quizgrade = $DB->get_field('quiz', 'grade', array('id' => $params['data']['qid'], 'course' => $cm->course));
			$quizobj->preload_questions();
			$quizobj->load_questions();
			$quizname = $quizobj->get_quiz_name();
			
			$info = array(
				'quiz' => $params['data']['qid'], 'results' => $quizgrade, "name" => $quizname,"main" => ""
				);
			//return (json_encode($info));
			foreach ($quizobj->get_questions() as $questiondata) {
				$options = array();
				$selectany = true;
				$forcecheckbox = false;
				if ($questiondata->qtype == 'multichoice') {
					foreach ($questiondata->options->answers as $ans) {
						$correct = false;
						// Get score if 100% answer correct if only one answer allowed.
						$correct = $ans->fraction > 0.9 ? true : false;
						if (!empty($questiondata->options->single) && $questiondata->options->single < 1) {
							$selectany = false;
							$forcecheckbox = true;
							// Get score if all option selected in multiple answer.
							$correct = $ans->fraction > 0 ? true : false;
						}
						$answer = congrea_formate_text(
								$cm->id, $questiondata, $ans->answer, $ans->answerformat, 'question', 'answer', $ans->id
						);
						$options[] = array("option" => $answer, "correct" => $correct);
					}
					$questiontext = congrea_formate_text(
							$cm->id, $questiondata, $questiondata->questiontext,
							$questiondata->questiontextformat, 'question', 'questiontext', $questiondata->id
					);
					$questions[] = array(
						"q" => $questiontext, "a" => $options,
						"qid" => $questiondata->id,
						"correct" => !empty($questiondata->options->correctfeedback) ?
						$questiondata->options->correctfeedback : "Your answer is correct.",
						"incorrect" => !empty($questiondata->options->incorrectfeedback) ?
						$questiondata->options->incorrectfeedback : "Your answer is incorrect.",
						"select_any" => $selectany,
						"force_checkbox" => $forcecheckbox
					);
				}
			}
		}	      
   		$result = array();
        $result['questions'] = $questions;
        $result['info'] = $info;
        $result['warnings'] = $warnings;
        return $result;
    }
	
	
    /**
     * Describes the mod_congrea_get_quizdata return value.
     *
     * @return external_single_structure
     * @since Moodle 3.8
     */
    public static function get_quizdata_returns() {
    
    	//return new external_value(PARAM_RAW, 'Description');
    	return new external_single_structure(
            array(
                'questions' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'q' => new external_value(PARAM_RAW, 'question text'),
                            'a' => new external_multiple_structure(
                            	new external_single_structure(
                            		array(
                            			'option' => new external_value(PARAM_RAW, 'option text'),
                            			'correct' => new external_value(PARAM_BOOL, 'correct answer',VALUE_OPTIONAL)
                            		)
                            	)
                            ),
                            'qid' => new external_value(PARAM_INT, 'question id'),
                            'correct' => new external_value(PARAM_RAW, 'text for correct answer'),
                            'incorrect' => new external_value(PARAM_RAW, 'text for incorrect answer'),
                            'select_any' => new external_value(PARAM_BOOL, 'option for selection',VALUE_OPTIONAL),
                            'force_checkbox' => new external_value(PARAM_BOOL, 'if mandatory checkbox selection', VALUE_OPTIONAL)  
                        )
                    )
                ),
                'info' => new external_single_structure(
                		array(
                			'quiz' => new external_value(PARAM_INT, 'quiz id'),
                			'results' => new external_value(PARAM_RAW, 'quiz grade'),
                			'name' => new external_value(PARAM_RAW, 'Quiz Header name',VALUE_OPTIONAL),
                			'main' => new external_value(PARAM_RAW, 'Quiz Description Text',VALUE_OPTIONAL),
                		)
                ),
                'warnings' => new external_warnings(),
            )
        );

    }
	    
    /**
     * Describes the parameters for poll_save.
     * @return external_function_parameters
     */
    public static function poll_save_parameters() {
         return new external_function_parameters (
            array(
                'cmid' => new external_value(PARAM_INT, 'course module id'),
                'data' => new external_single_structure(
                	array( 'dataToSave' => new external_value(PARAM_RAW, 'poll data in encoded form'),
                			'user'	=> new external_value(PARAM_INT, 'user id'),
                	)
            	)
            )
        );
    }   


    /**
     * Save poll question
     *
     * @param int $cmid the congrea course module id
     * @param array $data the qustion data as json encoded string
     * and userid 
     * @return array question information and warnings
     * @since Moodle 3.8
     */
    public static function poll_save($cmid, $data) {
    	global $DB;
		$warnings = array();
        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(self::poll_save_parameters(),
                                            array(
                                                'cmid' => $cmid,
                                                'data' => $data
                                            ));

		if (!$cm = get_coursemodule_from_id('congrea', $cmid, 0, false, MUST_EXIST)) {
            throw new moodle_exception("invalidcoursemodule", "error");
        }
        //Context validation
        $context = context_module::instance($cm->id);
        self::validate_context($context);

        //Capability checking        
        if (!has_capability('mod/congrea:managepoll', $context)) {
             throw new required_capability_exception($context, 'mod/congrea:managepoll', 'nopermissions', '');
         }
        $userid =  $params['data']['user'];
        $datatosave = json_decode($params['data']['dataToSave']); 
        $question = new stdClass();
        if (!empty($datatosave->category)) { // Course poll.
            $question->courseid = $cm->course;
        } else { // Site poll.
            $question->courseid = 0;
        }
        $question->instanceid = $cm->instance;
        $question->pollquestion = $datatosave->question;
        $question->createdby = $userid;
        $question->timecreated = time();
        $questionid = $DB->insert_record('congrea_poll', $question);
        $username = $DB->get_field('user', 'username', array('id' => $userid));
        if ($questionid) {
        	$responsearray = array();
            foreach ($datatosave->options as $optiondata) {
                $options = new stdClass();
                $options->options = $optiondata;
                $options->qid = $questionid;
                $id = $DB->insert_record('congrea_poll_question_option', $options);
                $options->optid = $id;
                $responsearray[] = $options;
            }
        }
        $obj = new stdClass();
        $obj->qid = $questionid;
        $obj->question = $question->pollquestion;
        $obj->createdby = $question->createdby;
        $obj->category = $datatosave->category; // To do.
        $obj->creatorname = $username;
        $obj->options = $responsearray;        
        $obj->copied = $datatosave->copied;

         return array(
            'pollobject' => $obj,
            'warnings' => $warnings
        );   
    }

    /**
     * Describes the poll_save return value.
     * @return external_single_structure
     */
    public static function poll_save_returns() {
    	//return new external_value(PARAM_RAW, 'Description');
        return new external_single_structure(
            array(
                'pollobject' => new external_single_structure(
                        array(
                            'qid' => new external_value(PARAM_INT, 'poll questiong id'),
                            'question' => new external_value(PARAM_RAW, 'question text'),
                            'createdby' => new external_value(PARAM_INT, 'userid of creator'),
                            'category' => new external_value(PARAM_INT, 'category id for course poll or site poll', VALUE_OPTIONAL),
                            'creatorname' => new external_value(PARAM_NOTAGS, 'creator full name'),
                            'options' => new external_multiple_structure(
                                 new external_single_structure(
                                     array(
                                        'options' => new external_value(PARAM_RAW, 'Option data'),
                                        'qid' => new external_value(PARAM_INT, 'question id'),
                                        'optid' => new external_value(PARAM_INT, 'option id'),
                                     )
                                 )
                            ),
                            'copied' => new external_value(PARAM_BOOL, 'true if copied'),
                        ) 
                	),
                'warnings' => new external_warnings(),
            )
        );
    }
    
    /**
     * Describe the parameter for poll_data_retrieve
     *
     * @return external_function_parameters
     * @since Moodle 3.8
     */
    public static function poll_data_retrieve_parameters() {  
         return new external_function_parameters(        	
             	array(
                 	'categoryid' => new external_value(PARAM_INT, 'category id'),
                 	'userid' => new external_value(PARAM_INT, 'id of user'),
                 )            
         );
    }
    
    
        /**
     * Save poll question
     *
     * @param int $categoryid category id for course/site poll
     * @param int $userid user id 
     * @return array question information and warnings
     * @since Moodle 3.8
     */
    public static function poll_data_retrieve($categoryid, $userid) {
    	global $DB;
		$warnings = array();
		//return (json_encode($categoryid));
        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(self::poll_data_retrieve_parameters(),
                                            array(
                                                'categoryid' => $categoryid,
                                                'userid' => $userid
                                            ));
		
		if ($params['categoryid'] > 0) {
			//course poll
			if (!$cm = get_coursemodule_from_id('congrea', $params['categoryid'], 0, false, MUST_EXIST)) {
            	throw new moodle_exception("invalidcoursemodule", "error");
        	}
        	//Context validation
        	$context = context_module::instance($cm->id);
        } else {
        	// site poll 
        	$context = context_system::instance();			
        }
		self::validate_context($context);

        //Capability checking
        //require_capability('mod/congrea:addinstance', $context);
        
        if (!has_capability('mod/congrea:managepoll', $context)) {
             throw new required_capability_exception($context, 'mod/congrea:managepoll', 'nopermissions', '');
         }
         
         $userid = $params['userid'];
         $category = ($params['categoryid'] > 0) ? $cm->course : 0;
         $questiondata = $DB->get_records('congrea_poll', array('courseid' => $category));
          
         if ($questiondata) {
         
            foreach ($questiondata as $data) {
            	
                $userdeatils = $DB->get_record('user', array('id' => $data->createdby));
                if (!empty($userdeatils)) {
                    $userfullname = $userdeatils->firstname . ' ' . $userdeatils->lastname; // Todo-for function.
                    $username = $userdeatils->username;
                } else {
                    $userfullname = get_string('nouser', 'mod_congrea');
                    $username = get_string('nouser', 'mod_congrea');
                }
                $result = $DB->record_exists('congrea_poll_attempts', array('qid' => $data->id));
                $optiondata = $DB->get_records('congrea_poll_question_option', array('qid' => $data->id), '', 'id, options');
              
                if ($data->courseid > 0) { // Category not zero.
                    $getcm = get_coursemodule_from_instance('congrea', $data->instanceid, $data->courseid, false, MUST_EXIST);
                    $datacategory = $getcm->id;
                } else {
                    $datacategory = 0;
                }
                
                $polllist = array(
                    'questionid' => $data->id,
                    'category' => $datacategory,
                    'createdby' => $data->createdby,
                    'questiontext' => $data->pollquestion,
                    'options' => $optiondata,
                    'creatorname' => $username,
                    'creatorfname' => $userfullname,
                    'isPublished' => $result
                );
                $responsearray[] = $polllist;
            }
        }else{
        	throw new moodle_exception("nopoll", "error");
        }
        //return $responsearray;
        $admin = 'false';
        $admins = get_admins(); // Check user is site admin.
        //return (json_encode($responsearray));
        if (!empty($admins) && !empty($admins[$userid]->id)) {
            if ($admins[$userid]->id == $userid) {
                $admin = 'true';
            } 
        }

        $result = array();
        $result['responsearray'] = $responsearray;
        $result['admin'] = $admin;
        $result['warnings'] = $warnings;
        return $result;
         
    }
    
    
        /**
     * Describes the mod_congrea_poll_data_retrieve return value.
     *
     * @return external_single_structure
     * @since Moodle 3.8
     */
    public static function poll_data_retrieve_returns() {
    
    	//return new external_value(PARAM_RAW, 'Description');
    	return new external_single_structure(
            array(
                'responsearray' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'questionid' => new external_value(PARAM_INT, 'question id'),
                            'category' => new external_value(PARAM_INT, 'category id'),
                            'createdby' => new external_value(PARAM_RAW, 'userid cteated the poll'),
                            'questiontext' => new external_value(PARAM_RAW, 'question text'),
                            'options' => new external_multiple_structure(
                            	new external_single_structure(
                            		array(
                            			'id' => new external_value(PARAM_INT, 'option id'),
                            			'options' => new external_value(PARAM_RAW, 'option text'),
                            		)
                            	)
                            ),
                            'creatorname' => new external_value(PARAM_RAW, 'Username who created poll'),
                            'creatorfname' => new external_value(PARAM_RAW, 'Full name who created poll'),
                            'isPublished' => new external_value(PARAM_BOOL, 'If poll is published', VALUE_OPTIONAL),
                            
                        )
                    )
                ),
                'admin' => new external_value(PARAM_RAW, 'if user is admin'),
                'warnings' => new external_warnings(),
            )
        );

    }


    /**
     * Describe the parameter for poll_delete
     *
     * @return external_function_parameters
     * @since Moodle 3.8
     */
    public static function poll_delete_parameters() {  
         return new external_function_parameters(  	
             	array(
                 	'qid' => new external_value(PARAM_INT, 'question id'),
                 	'userid' => new external_value(PARAM_INT, 'id of user'),
                 )            
         );
    }


    /**
     * Delete poll question
     *
     * @param int $categoryid category id for course/site poll
     * @param int $userid user id 
     * @return array question information and warnings
     * @since Moodle 3.8
     */
    public static function poll_delete($qid, $userid) {
    	global $DB;
		$warnings = array();
		
        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(self::poll_delete_parameters(),
                                            array(
                                                'qid' => $qid,
                                                'userid' => $userid
                                            ));
		
		//$pollcategory = $DB->get_record_sql("SELECT courseid, instanceid FROM {congrea_poll} WHERE id = $id");
		$pollcategory = $DB->get_record('congrea_poll',array('id' => $params['qid']),'courseid, instanceid');
		
		if ($pollcategory->courseid > 0) {
			//course poll
			if (!$cm = get_coursemodule_from_instance('congrea', $pollcategory->instanceid, $pollcategory->courseid, false, MUST_EXIST)) {
            	throw new moodle_exception("invalidcoursemodule", "error");
        	}
        	//Context validation
        	$context = context_module::instance($cm->id);
        	$category = $cm->id;
        } else {
        	// site poll 
        	$context = context_system::instance();
        	$category = 0;			
        }
		self::validate_context($context);

        //Capability checking
        
        if (!has_capability('mod/congrea:managepoll', $context)) {
             throw new required_capability_exception($context, 'mod/congrea:managepoll', 'nopermissions', '');
         }
         
         $userid = $params['userid'];
         $delresult = $DB->delete_records('congrea_poll_attempts', array('qid' => $params['qid']));
         $deloptions = $DB->delete_records('congrea_poll_question_option', array('qid' => $params['qid']));
         if ($deloptions) {
            $DB->delete_records('congrea_poll', array('id' => $params['qid']));
         }
        //var_dump($category);die;
        
        $result = array();
        $result['category'] = $category;
        $result['warnings'] = $warnings;
        return $result;
         
    }
    

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.8
     */
    public static function poll_delete_returns() {
    	//return new external_value(PARAM_RAW, 'Description');
        return new external_single_structure(
            array(
                'category' => new external_value(PARAM_INT, 'poll category'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Describe the parameter for poll_option delete
     *
     * @return external_function_parameters
     * @since Moodle 3.8
     */
    public static function poll_option_drop_parameters() {  
         return new external_function_parameters(  	
             	array(
             		'cmid' => new external_value(PARAM_INT, 'course module id'),
                 	'polloptionid' => new external_value(PARAM_INT, 'Poll option id require to delete'),	
                 )            
         );
    }

    /**
     * Delete poll question
     *
     * @param int $categoryid category id for course/site poll
     * @param int $userid user id 
     * @return array question information and warnings
     * @since Moodle 3.8
     */
    public static function poll_option_drop($cmid, $polloptionid) {
    	global $DB;
		$warnings = array();
		
        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(self::poll_option_drop_parameters(),
                                            array(
                                            	'cmid' => $cmid,
                                                'polloptionid' => $polloptionid
                                            ));
		
		// Request and permission validation.
	   if (!$cm = get_coursemodule_from_id('congrea', $params['cmid'], 0, false, MUST_EXIST)) {
		   throw new moodle_exception("invalidcoursemodule", "error");
	   }
	   //Context validation
	   $context = context_module::instance($cm->id);
	   self::validate_context($context);

	   //Capability checking
	   if (!has_capability('mod/congrea:managepoll', $context)) {
			throw new required_capability_exception($context, 'mod/congrea:managepoll', 'nopermissions', '');
		}
	
	   $status =  true;
	   if (!$DB->delete_records('congrea_poll_question_option', array('id' => $id))) {
		   $status = false;
	   }
        
        $result = array();
        $result['status'] = $status;
        $result['warnings'] = $warnings;
        return $result;         
    }
    
    /**
     * Returns description of method poll_option_drop
     *
     * @return external_description
     * @since Moodle 3.8
     */
    public static function poll_option_drop_returns() {
    	//return new external_value(PARAM_RAW, 'Description');
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status, true if poll option deleted'),
                'warnings' => new external_warnings()
            )
        );
    }
    
		
    /**
     * Describes the parameters for poll_update.
     * @return external_function_parameters
     */
    public static function poll_update_parameters() {
         return new external_function_parameters (
            array(
                'cmid' => new external_value(PARAM_INT, 'course module id'),
                'data' => new external_single_structure(
                	array( 'dataToUpdate' => new external_value(PARAM_RAW, 'poll data in encoded form'),
                			'user'	=> new external_value(PARAM_INT, 'user id'),
                	)
            	)
            )
        );
    }   


    /**
     * Update question and options of a poll
     *
     * @param int $cmid the congrea course module id
     * @param array $data the qustion data as json encoded string
     * and userid 
     * @return array question information and warnings
     * @since Moodle 3.8
     */
    public static function poll_update($cmid, $data) {
    	global $DB;
		$warnings = array();
        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(self::poll_update_parameters(),
                                            array(
                                                'cmid' => $cmid,
                                                'data' => $data
                                            ));

		if (!$cm = get_coursemodule_from_id('congrea', $cmid, 0, false, MUST_EXIST)) {
            throw new moodle_exception("invalidcoursemodule", "error");
        }
        //Context validation
        $context = context_module::instance($cm->id);
        self::validate_context($context);

        //Capability checking        
        if (!has_capability('mod/congrea:managepoll', $context)) {
             throw new required_capability_exception($context, 'mod/congrea:managepoll', 'nopermissions', '');
         }
        $userid =  $params['data']['user'];
        $datatoupdate = json_decode($params['data']['dataToUpdate']); 
        //return json_encode($datatoupdate->questionid);
        
		$pollcategory = $DB->get_record('congrea_poll', array ('id' => $datatoupdate->questionid), 'courseid, instanceid');
		if(!$pollcategory) {
			throw new dml_exception("pollquestionidnotexist", "congrea_poll");
		}
		
		// if no courseid its site poll
		$category = $pollcategory->courseid ? $cm->id : 0;
		
		$dataobject = new stdClass();
		$dataobject->id = $datatoupdate->questionid;
        $dataobject->pollquestion = $datatoupdate->question;       
		if(!$quesiontext = $DB->update_record('congrea_poll', $dataobject)){
			throw new dml_exception("recordnotupdated", 'congrea_poll');
		}
		$responsearray = array();
		
		$DB->delete_records('congrea_poll_question_option', array ('qid' => $datatoupdate->questionid));
		foreach ($datatoupdate->options as $key => $value) {
			$newoptions = new stdClass();
			$newoptions->options = $value;
			$newoptions->qid = $datatoupdate->questionid;
			$optid = $DB->insert_record('congrea_poll_question_option', $newoptions);
			$newoptions->id = $optid;
			$responsearray[] = $newoptions;		
		}
		/*foreach ($datatoupdate->options as $key => $value) {
			$newoptions = new stdClass();
			if (is_numeric($key)) { // Ensures Question and options are old.
				$newoptions->id = $key;
				$newoptions->options = $value;
				if(!$DB->update_record('congrea_poll_question_option', $newoptions)){
					throw new dml_exception("recordnotupdated", "congrea_poll_question_option");
				}
				$newoptions->qid = $datatoupdate->questionid;
			} else { // Add new Options.
				$newoptions->options = $value;
				$newoptions->qid = $datatoupdate->questionid;
				$optid = $DB->insert_record('congrea_poll_question_option', $newoptions);
				$newoptions->id = $optid;
			}
			$responsearray[] = $newoptions;
        }*/
        //return json_encode($newoptions);	
		$obj = new stdClass();
        $obj->qid = $datatoupdate->questionid;
        $obj->question = $datatoupdate->question;
        $obj->createdby = $datatoupdate->createdby;
        $obj->category = $category;
        $obj->options = $responsearray;
		
		//return json_encode($category);		      
        
        return array(
            'pollobject' => $obj,
            'warnings' => $warnings
        );   
    }

    /**
     * Describes the poll_save return value.
     * @return external_single_structure
     */
    public static function poll_update_returns() {
    	//return new external_value(PARAM_RAW, 'Description');
        return new external_single_structure(
            array(
                'pollobject' => new external_single_structure(
                        array(
                            'qid' => new external_value(PARAM_INT, 'poll questiong id'),
                            'question' => new external_value(PARAM_RAW, 'question text'),
                            'createdby' => new external_value(PARAM_INT, 'userid of creator'),
                            'category' => new external_value(PARAM_INT, 'category id for course poll or site poll', VALUE_OPTIONAL),
                            'options' => new external_multiple_structure(
                                 new external_single_structure(
                                     array(
                                        'options' => new external_value(PARAM_RAW, 'Option data'),
                                        'id' => new external_value(PARAM_INT, 'option id'),
                                        'qid' => new external_value(PARAM_INT, 'question id'),
                                     )
                                 )
                            ),
                        ) 
                	),
                'warnings' => new external_warnings(),
            )
        );
    }    

    /**
     * Describes the parameters for poll_result.
     * @return external_function_parameters
     */
    public static function poll_result_parameters() {
         return new external_function_parameters (
            array(
                'cmid' => new external_value(PARAM_INT, 'course module id'),
                'data' => new external_single_structure(
                	array( 'resultdata' => new external_value(PARAM_RAW, 'Result data of poll'),
                			'user'	=> new external_value(PARAM_INT, 'user id'),
                	)
            	)
            )
        );
    }   

    /**
     * Save result of Poll
     *
     * @param int $cmid the congrea course module id
     * @param array $data result detail as json encoded string
     * and userid 
     * @return array question information and warnings
     * @since Moodle 3.8
     */
    public static function poll_result($cmid, $data) {
    	global $DB;
		$warnings = array();
        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(self::poll_result_parameters(),
                                            array(
                                                'cmid' => $cmid,
                                                'data' => $data
                                            ));

		if (!$cm = get_coursemodule_from_id('congrea', $cmid, 0, false, MUST_EXIST)) {
            throw new moodle_exception("invalidcoursemodule", "error");
        }
        //Context validation
        $context = context_module::instance($cm->id);
        self::validate_context($context);

        //Capability checking        
        if (!has_capability('mod/congrea:managepoll', $context)) {
             throw new required_capability_exception($context, 'mod/congrea:managepoll', 'nopermissions', '');
         }
        $userid =  $params['data']['user'];
        $dataresult = json_decode($params['data']['resultdata']); 
        if(!$dataresult->qid) {
        	throw new moodle_exception("missingpollresult", "error");
        }
        
		$pollcategory = $DB->get_record('congrea_poll', array ('id' => $dataresult->qid), 'courseid, instanceid');
		if(!$pollcategory) {
			throw new dml_exception("pollquestionidnotexist", "congrea_poll");
		}
		
		// if no courseid its site poll
		$category = $pollcategory->courseid ? $cm->id : 0;
		if ($dataresult->list) {
			foreach ($dataresult->list as $optiondata) {
				foreach ($optiondata as $userid => $optionid) {
					if (is_numeric($userid) && is_numeric($optionid)) {
						$attempt = new stdClass();
						$attempt->userid = $userid;
						$attempt->qid = $dataresult->qid;
						$attempt->optionid = $optionid;
						$DB->insert_record('congrea_poll_attempts', $attempt);
					}
				}
			}
		}
				              
        return array(
            'category' => $category,
            'warnings' => $warnings
        );   
    }


    /**
     * Returns description of method poll_result
     *
     * @return external_description
     * @since Moodle 3.8
     */
    public static function poll_result_returns() {
    	//return new external_value(PARAM_RAW, 'Description');
        return new external_single_structure(
            array(
                'category' => new external_value(PARAM_INT, 'poll category'),
                'warnings' => new external_warnings()
            )
        );
    }          
}