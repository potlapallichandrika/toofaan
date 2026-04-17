
<?php
/**
 * this will display the list of students with submitted status and graded status
 * this code is taken from moodle vpl submission list as a reference
 */
?>



<?php

class vpl_submissionlist_order{

    static $field;   //field to compare
    static $ascending; //value to return when ascending or descending order
    static $corder = null; //usort of old PHP versions don't call static class functions

            static public function cpm_userid($a,$b){ //Compare two submission fields
                if($a->userinfo->id < $b->userinfo->id ){
                    return self::$ascending;
                }else{
                    return -self::$ascending;
                }
            }
            static public function cpm_userinfo($a,$b){ //Compare two userinfo fields
                $field = self::$field;
                $adata = $a->userinfo->$field;
                $bdata = $b->userinfo->$field;
                if($adata == $bdata) {
                    return self::cpm_userid($a,$b);
                }
                if(is_string($adata) && function_exists('collatorlib::compare')){
                    return (collatorlib::compare($adata, $bdata))*(self::$ascending);
                }
                if($adata < $bdata){
                    return self::$ascending;
                }else{
                    return -self::$ascending;
                }
            }
            static public function cpm_submission($a,$b){ //Compare two submission fields
                $field = self::$field;
                $submissiona = $a->submission;
                $submissionb = $b->submission;
                if($submissiona == $submissionb){
                    return self::cpm_userid($a,$b);
                }
                if($submissiona == null){
                    return self::$ascending;
                }
                if($submissionb == null){
                    return -self::$ascending;
                }
                $adata = $submissiona->get_instance()->$field;
                $bdata = $submissionb->get_instance()->$field;
                if($adata === null){
                    return self::$ascending;
                }
                if($bdata === null){
                    return -self::$ascending;
                }
                if($adata == $bdata) {
                    return self::cpm_userid($a,$b);
                }elseif($adata < $bdata){
                    return self::$ascending;
                }else{
                    return -self::$ascending;
                }
            }

            /**
             * Check and set data to sort return comparation function
             * $field field to compare
             * $descending order
             * @return function
             */
            static public function set_order($field,$ascending = true){
                if(self::$corder === null){
                    self::$corder = new vpl_submissionlist_order;
                }
                $userinfofields = array('firstname'=>0,'lastname'=>0);

                $submissionfields = array('datesubmitted'=>0,'gradesortable'=>0,'grader'=>0,'dategraded'=>0,'nsubmissions'=>0);

                self::$field = $field;
                if($ascending){
                    self::$ascending = -1;
                }else{
                    self::$ascending = 1;
                }
                //usort of old PHP versions don't call static class functions
                if(isset($userinfofields[$field])){
                    return array(self::$corder,'cpm_userinfo');
                }elseif(isset($submissionfields[$field])){
                    return array(self::$corder,'cpm_submission');
                }else{
                    self::$field = 'firstname';
                    return array(self::$corder,'cpm_userinfo');
                }
            }
}
            function vpl_evaluate($vpl,$all_data,$userinfo,$nevaluation,$groups_url){
                global $OUTPUT;
                $nevaluation++;
                try{
                    echo '<h2>'.s(get_string('evaluating',VPL)).'</h2>';
                    $text =  $nevaluation.'/'.count($all_data);
                    $text .= ' '.$vpl->user_picture($userinfo);
                    $text .= ' '.fullname($userinfo);
                    $text .= ' <a href="'.$groups_url.'">'.get_string('cancel').'</a>';
                    echo $OUTPUT->box($text);
                    $id=$vpl->get_course_module()->id;
                    $userid=$userinfo->id;
                    $ajaxurl="../forms/edit.json.php?id={$id}&userid={$userinfo->id}&action=";
                    $url=vpl_url_add_param($groups_url,'evaluate',optional_param('evaluate', 0, PARAM_INT));
                    $url=vpl_url_add_param($url,'nevaluation',$nevaluation);
                    $nexturl=str_replace('&amp;','&',urldecode($url));
                    vpl_editor_util::generateEvaluateScript($ajaxurl,$nexturl);
                }catch(Exception $e){
                    echo $OUTPUT->box($e->getMessage());
                }
                $vpl->print_footer();
                die;
            }

            function vpl_submissionlist_arrow($burl, $sort, $selsort, $seldir){
                global $OUTPUT;
                $newdir = 'down';
                $url = vpl_url_add_param($burl,'sort',$sort);
                if($sort == $selsort){
                    $sortdir = $seldir;
                    if($sortdir == 'up'){
                        $newdir = 'down';
                    }elseif($sortdir == 'down'){
                        $newdir = 'up';
                    }
                }else{
                    $sortdir = 'move';
                }
                $url = vpl_url_add_param($url,'sortdir',$newdir);
                return ' <a href="'.$url.'">'.($OUTPUT->pix_icon('t/'.$sortdir,get_string($sortdir))).'</a>';
            }

require_once('../../../config.php');
//var_dump("hello1");
//exit(0);
require_once('../../../mod/vpl/locallib.php');
require_once('../../../mod/vpl/vpl.class.php');
require_once('../../../mod/vpl/vpl_submission_CE.class.php');
// var_dump("hello1");
require_once("../../watchlist/lib.php");
require_once('testcenterutil.php');


//require_login();

$actid = required_param('id', PARAM_INT);   //activityid
$typeid = required_param('typeid', PARAM_INT);

$secname = optional_param('secname', 'All',PARAM_TEXT);

$acivitystatus=0;
$acivitystopdate=0;
// var_dump($typeid);
// var_dump($activityTypeIds);
// exit;



		if($DB->get_field('activity_status_tsl','status',  array('activityid' => $actid))){
                        $result=$DB->get_field('activity_status_tsl','status',  array('activityid' => $actid));
			$acivitystatus= $result;

        }
        else{
            $acivitystopdate=userdate($DB->get_field('activity_status_tsl','activity_stop_time',  array('activityid' => $actid)));
        }

if($typeid==$activityTypeIds['vpl']){
echo '<html>
<body>';
$subselection = vpl_get_set_session_var('subselection','allsubmissions','selection');
$options = array('height' => 550, 'width' => 780, 'directories' =>0, 'location' =>0, 'menubar'=>0,
    'personalbar'=>0,'status'=>0,'toolbar'=>0);
$group = optional_param('group', -1, PARAM_INT);
$sort = 'dategraded';
$sortdir = 'up';

//print_r($secname);

//$loggedinusers=get_loggedin_users_by_section($secname);
    $loggedinstudents=0;
//print_r($acivitystatus);

$vpl = new mod_vpl($actid);
$vpl->prepare_page('views/submissionslist.php',array('id' => $actid));

$course = $vpl->get_course();
$cm = $vpl->get_course_module();
$context_module = $vpl->get_context();

//get students
$currentgroup = groups_get_activity_group($cm, true);
if(!$currentgroup){
    $currentgroup='';
}
$list = $vpl->get_students($currentgroup);
   // var_dump (count($list));
$submissions = $vpl->all_last_user_submission();
$submissions_number = $vpl->get_submissions_number();

//Get all information
$all_data = array();
$subCount=0;
$gradeCount=0;
$starCount=0;
$redstarCount=0;
$watchCount=0;
$section_flag=0;
$studentCount=0;
    $statusNum=0;
    $greenstar=0;

    $rollfield=$DB->get_field('user_info_field', 'id', array('shortname'=>'rollno'));
    $sectionfield=$DB->get_field('user_info_field', 'id', array('shortname'=>'section'));

foreach ($list as $userinfo) {

    if($vpl->is_group_activity() && $userinfo->id != $vpl->get_group_leaderid($userinfo->id)){
        continue;
    }
    $submission = null;
    if(!isset($submissions[$userinfo->id])){
        /*if($subselection != 'all'){
            continue;
        }*/
        $submission = null;
    }
    else{
        $subinstance = $submissions[$userinfo->id];
        $submission = new mod_vpl_submission_CE($vpl,$subinstance);
        $subid=$subinstance->id;
        $subinstance->gradesortable = null;
        if($subinstance->dategraded>0){
            if($subselection == 'notgraded'){
                continue;
            }
            if($subselection == 'gradedbyuser' && $subinstance->grader != $USER->id){
                continue;
            }
            //TODO REUSE showing
            $subinstance->gradesortable = $subinstance->grade;
        }else{
            $subinstance->grade = null;
            if($subselection == 'graded' ||$subselection == 'gradedbyuser'){
                continue;
            }
            //TODO REUSE showing
            $result=$submission->getCE();
            if($result['executed']!==0){
                $prograde=$submission->proposedGrade($result['execution']);
                if($prograde>''){
                    $subinstance->gradesortable=$prograde;
                }
            }
        }
        //I know that subinstance isn't the correct place to put nsubmissions but is the easy
        if(isset($submissions_number[$userinfo->id])){
            $subinstance->nsubmissions = $submissions_number[$userinfo->id]->submissions;
        }else{
            $subinstance->nsubmissions = ' ';
        }

    }
    $data = new stdClass();
    $data->userinfo = $userinfo;

    $data->submission = $submission;
    //When group activity => change leader object lastname to groupname for order porpouse
    if($vpl->is_group_activity()){
        $data->userinfo->firstname = '';
        $data->userinfo->lastname = $vpl->fullname($userinfo);
    }
    $all_data[] = $data;
}

usort($all_data,vpl_submissionlist_order::set_order($sort,$sortdir != 'up'));

// Playwright code update By Ram Mohan
$vpl_instanceid=$vpl->get_instance()->id;
//var_dump($vpl_instanceid);
$xmlFile = $CFG->dataroot."/vpl_data/".$vpl_instanceid."/required_files/url.xml";
$playwright=0;
// Check if the file exists
if (file_exists($xmlFile)) {
    $playwright=1;
}
// Playwright code update By Ram Mohan


//content display start
echo '<div class="repo">
	<div id="container" class="table-responsive" style="padding-bottom:30px;" >


		<table id="myTable"  class="CSSTableGenerator table table-hover course-list-table tablesorter" >
			<thead>
			<tr>
				<th style="text-align:center" class="header">Status</th>
				<th class="header">Roll No</th>
				<th class="header">Full Name</th>
				<th class="header" style="text-align:center">Section</th>
				<th class="header" style="text-align:center">IP Address</th>
				<th class="header">Last Submission</th>';
				if($playwright!=1){
				echo '<th class="header">Lang</th>';
				} 
				echo '<th class="header" style="text-align:center">Submissions</th>';
                //tle code updated by chandrika

                echo'<th class="header" style="text-align:center" title="Evaluation Time(in Micro Seconds)">Time(µ sec)</th>';

                if ($vpl->get_instance()->tlelimit > 0) {

    echo '<th class="header" style="text-align:center" title="Time Limit Exceeded">TLE</th>';

}

                if($vpl->get_instance()->algorithm!=''){
                    echo '<th class="header">Algorithm</th>';
                    echo '<th class="header">Time Complexity</th>';

                }
				echo '<th class="header" style="text-align:center">Grade</th>
				<th class="header" style="text-align:center">Watch</th>
			</tr>
			</thead>
			<tbody >';

	if($secname=='All'||$secnmae=='0')
	{
	$section_flag=0;
	}
	else{
	$section_flag=1;
	}
	$display_flag=1;
    foreach ($all_data as $data) {
    $userinfo = $data->userinfo;$greenstar=0;$sip=''; $slang=''; 
    $userparticipation=$DB->get_field('userinfo_tsl', 'loginstatus', array('userid' => $userinfo->id));

    if(($userparticipation==2)||($userparticipation==4)){
        $loginstatus=1;
    }
    else{
        $loginstatus=0;
    }
	//$section_name=get_complete_user_data(id,$userinfo->id)->profile['section'];
        $section_name=getTCStudentData($userinfo->id,$sectionfield);

	if($section_flag){

	if(($secname==$section_name))
	{
	$display_flag=1;
	}
	else{
	$display_flag=0;
	}
	}

    if($data->submission == null){
        $subtime = get_string('nosubmission',VPL);
        //tle code updated by chandrika
                $exectime="--";
              $istle="--";

        $hrefview=vpl_mod_href('forms/submissionview.php','id',$actid,
            'userid',$userinfo->id,'inpopup',1);
        //TODO clean comment
        $action = new popup_action('click', $hrefview,'viewsub'.$userinfo->id,$options);
        //$subtime = $OUTPUT->action_link($hrefview, $text,$action);

        $prev = '';
        $grade ='NS';//not graded person treated as
        $gradeStatus=-2;
        $grader ='';
        $gradedon ='';
        $nsub=0;
        $hrefprev="javascript:void(0)";
        $statusImag='flag-red-icon.png';
        $statusNum=0;
    }
    else{
	if($display_flag){
        if($loginstatus){
            $subCount++;
        }

	}
    // var_dump($data);
        $submission = $data->submission;
        $subinstance = $submission->get_instance();
        $slang='';    //  get Student Submitted Language   Ram Mohan
        $slang=$submission->print_student_language();     //  get Student Submitted Language Ram Mohan

        // var_dump($subinstance);
        $hrefview=vpl_mod_href('forms/submissionview.php','id',$actid,
            'userid',$subinstance->userid,'inpopup',1);
        /*$hrefprev=vpl_mod_href('views/previoussubmissionslist.php','id',$id,
            'userid',$subinstance->userid,'inpopup',1);*/
        $hrefprev=$CFG->wwwroot.'/local/teacher/testcenter/studentsubmissionview.php?id='.$actid.'&userid='.$subinstance->userid.'&submissionid='.$submission->id;
        $hrefgrade=vpl_mod_href('forms/gradesubmission.php','id',$actid,
            'userid',$subinstance->userid,'inpopup',1);
        $subtime=userdate($subinstance->datesubmitted);

                //tle code by chandrika
        $exectime=$subinstance->evaluationtime;
        $istle=($subinstance->istle)?"Yes":"No";
        //end

         $sip=$subinstance->subip;

        // var_dump($sip);


        if($subinstance->nsubmissions>0){
            $prev = $OUTPUT->action_link($hrefprev,
                $subinstance->nsubmissions);
	        $nsub=$subinstance->nsubmissions;
        }else{
            $prev='';
        }
        if($subinstance->dategraded>0){
            $text = $submission->print_grade_custom();
            $greenstar=$text;

            //print_r("<br/>result-".$prograde."<br/>");
            //graded person treated as graded with value
            $grade = '<span id="g'.$subid.'">'.$text.'</span>';
            $gradeStatus=$grade;
            //print_r("<br/>result-".$text."<br/>");

            $graderid=$subinstance->grader;
            $graderuser = $submission->get_grader($graderid);

            $grader = fullname($graderuser);
            $gradedon = userdate($subinstance->dategraded);
        }
        else{
            $result=$submission->getCE();
            $text='';
            if(($evaluate == 1 && $result['compilation'] === 0)||
                ($evaluate == 2 && $result['executed'] === 0 && $nevaluation <= $usernumber) ||
                ($evaluate == 3 && $nevaluation <= $usernumber)){ //Need evaluation
                vpl_evaluate($vpl,$all_data,$userinfo,$usernumber,$groups_url);
            }
            if($result['executed']!==0){
                $prograde=$submission->proposedGrade($result['execution']);
                if($prograde>''){
                    $text=$submission->print_grade_custom($prograde);

                }
            }
            //not graded person treated as NG
            if($text ==''){
                //$text=-1;//get_string('nograde');
                $text='NG';
                $gradeStatus=-1;
            }
	    else{
                $greenstar=$text;
		}

            $grades=$text;
            $action = new popup_action('click', $hrefgrade,'gradesub'.$userinfo->id,$options);
            $text = '<span id="g'.$subid.'">'.$text.'</span>';



            $grade = $text;//$OUTPUT->action_link($hrefgrade,$text,$action);
            $grader = '&nbsp;';
            $gradedon = '&nbsp;';
            //Add new next user
            if($last_id){
                $next_ids[$last_id]=$userinfo->id;
            }
            $last_id=$subid; //Save submission id as next index
        }
//Add span id to submission info
        $grader ='<span id="m'.$subid.'">'.$grader.'</span>';
        $gradedon ='<span id="o'.$subid.'">'.$gradedon.'</span>';
        if($grades!=='NG'){
            if($display_flag){if($loginstatus){$gradeCount++;}}
		    $statusImag='flag-green-icon.png';
            $statusNum=2;
        }
        else{
            $statusImag='flag-orange-icon.png';
            $statusNum=1;
        }

    }

$usersubcount=$hrefprev;
    $rollno=getTCStudentData($userinfo->id,$rollfield);
    //$rollno=get_complete_user_data(id,$userinfo->id)->profile['rollno'];
        $target='';
if($nsub){
    $target='target="_blank"';
}

//$sip=get_complete_user_data(id,$userinfo->id)->lastip;


    if($display_flag){
        if($loginstatus) {

            //code to check whether the student is watchlisted or not
            $watchliststatus=getStatus($userinfo->id,$course->id);
            if($watchliststatus){
                $watchlist_icon='eye-24-512.png';
                $watchCount++;
            }
            else{
                $watchlist_icon='unwatch-512.png';
            }


	    if($greenstar==100){
		$statusImag='green-star.png';
		$starCount++;
            if($watchliststatus){
                $statusImag='red-star.png';
                $redstarCount++;
            }

	    }

            $loggedinstudents++;

             // playwright code rammohan  start
             $xmlFile = $CFG->dataroot."/vpl_data/".$vpl_instanceid."/usersdata/".$userinfo->id."/".$subinstance->id."/submittedfiles/url.xml";
             //echo $xmlFile;
             try {
    // Check if the file exists
    if (file_exists($xmlFile)) {
        // Load the XML file
        $xml = simplexml_load_file($xmlFile);
        
        // Check if the XML loading was successful
        if ($xml === false) {
            throw new Exception('Error loading XML file.');
        }

        // Check if the 'value' attribute exists
        if (isset($xml->url->attributes()->value)) {
            // Retrieve the URL value
            $urlValue = (string)$xml->url->attributes()->value;

            // Parse the URL to get the host
            $parsed_url = parse_url($urlValue);
            $url_ip = $parsed_url['host'] ?? ''; // Default to empty string if 'host' is not present

            // Determine the color based on the comparison
            $color = ($url_ip == $sip) ? 'green' : 'red';

            // Create the anchor tag with the URL and apply the color
            $url = '<a href="' . htmlspecialchars($urlValue, ENT_QUOTES, 'UTF-8') . '" target="_blank" style="font-weight:bold;color:' . htmlspecialchars($color, ENT_QUOTES, 'UTF-8') . ';">View App</a>';
            if($urlValue==''){
             $url = "<a href='#' >NA</a>";
}
        } else {
            // Handle the case where the 'value' attribute is not present
            $url = "<a href='#' >NA</a>";
        }
    } else {
        // Handle the case where the file does not exist
        $url = "<a href='#' >NA</a>";
    }
} catch (Exception $e) {
    // Handle the exception and provide a fallback URL
    $url = '<a href="' . $usersubcount . '" '.$target.'>' . $nsub . ' </a>';
}

             // playwright code rammohan  end 
$username = $DB->get_field('user', 'username', array('id' => $userinfo->id));

            echo '<tr>
				<td><span  style="display:none">'.$greenstar.'-'.$statusNum.'</span>
				<img src=" ' . $CFG->wwwroot . '/local/teacher/testcenter/images/' . $statusImag . '" width="  16px" /></td>
				<td><a target="_blank" href="'.$CFG->wwwroot.'/report/outline/user.php?id='. $userinfo->id.'&course='.$course->id.'&mode=outline'.'">' . $username . '</a></td>
				<td><a target="_blank" href="'.$CFG->wwwroot.'/report/outline/user.php?id='. $userinfo->id.'&course='.$course->id.'&mode=outline'.'">' . $userinfo->firstname.' ' .$userinfo->lastname . '</a></td>
				<td style="text-align:center">' . $section_name . '</td>
				<td style="text-align:center"  class="ip" >' . $sip . '</td>
				<td style="text-transform: none !important;">' . $subtime . '</td>';
               

				 // playwright new code for webapp check rammohan start
                 if($playwright==1){ 
                    //echo $url;
                    echo '<td style="text-align:center" class="url">'.$url.'</td>';
                }
                else{// new code for webapp check
                     echo '<td style="text-align:center">' . $slang . '</td>';
				 echo '<td style="text-align:center"><a href="' . $usersubcount . '" '.$target.'>' . $nsub . ' </a> </td>';  // previous code before playwright
                }
                // playwright new code for webapp check rammohan  end
                                //tle code updated by chandrika
              echo '<td style="text-align:center" title="Evaluation Time(in Seconds)">'.$exectime.'</td>';
                	if($vpl->get_instance()->tlelimit>0){
                    		echo '<td style="text-align:center" title="Time Limit Exceeded">'.$istle.'</td>';                    
                	}



                if($vpl->get_instance()->algorithm!=''){
                    $subinstance = $submissions[$userinfo->id];

                    if($subinstance->algorithm==1){
                       $algoicon='<i class="fa fa-check-circle fa-lg" style="color:green;"></i>';
                    }
                    else if($vpl->get_instance()->algorithm=='TimeComplexity'){
                        $algoicon=$subinstance->timecomplexity;
                     }
                    else{
                        $algoicon='<i class="fa fa-times-circle fa-lg"  style="color:red;" ></i>';
                    }
                    echo '<td style="text-align:center">' .  $algoicon . '</td>';
                    echo '<td style="text-align:center">' . $subinstance->timecomplexity . '</td>';
                }

				echo '<td style="text-align:center"><span style="display:none">'.$gradeStatus.'</span>' . $grade . '</td>
				<td>
				<span class="watchlist-status'.$userinfo->id.'" style="display:none">'.$watchliststatus.'</span>
				<img data-ref="' . $watchliststatus . '" id="' . $userinfo->id . '" class="watchlist" src=" ' . $CFG->wwwroot . '/local/teacher/testcenter/images/' . $watchlist_icon . '" width="  16px"/></td>

			</tr>';
        }//if student is logged in
   }

}

echo '</tbody>
		</table>
	</div>
		<div id="scrollable"></div>
	</div>';

echo '<span style="display:none" id="subCount">'.($subCount-$gradeCount).'</span>';
echo '<span style="display:none" id="gradeCount">'.($gradeCount-$starCount).'</span>';
echo '<span style="display:none" id="acivitystatus">'.$acivitystatus.'</span>';
echo '<span style="display:none" id="loggedinusers">'.$loggedinstudents.'</span>';
echo '<span style="display:none" id="statusstopdate">'.$acivitystopdate.'</span>';
echo '<span style="display:none" id="cstarCount">'.($starCount-$redstarCount).'</span>';
echo '<span style="display:none" id="crstarCount">'.$redstarCount.'</span>';
echo '<span style="display:none" id="watchCount">'.$watchCount.'</span>';

}//if activity is vpl $acivitystopdate


// h5p code by chandrika
elseif ($typeid == $activityTypeIds['h5pactivity']) {

    //echo "<div style='background:yellow;padding:5px;'>H5P REPORT BLOCK EXECUTED</div>";

    // ==================== H5P ACTIVITY ====================
    $cm     = get_coursemodule_from_id('h5pactivity', $actid, 0, false, MUST_EXIST);
    $h5p    = $DB->get_record('h5pactivity', ['id' => $cm->instance], '*', MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

    // Get students (role 5 = student)
    $context  = context_course::instance($cm->course);
    $students = get_role_users(5, $context);

    // Counters
    $loggedin = $submitted = $graded = $star = $redstar = $watchCount = 0;

    // Field IDs
    $rollfield    = $DB->get_field('user_info_field', 'id', ['shortname' => 'rollno']);
    $sectionfield = $DB->get_field('user_info_field', 'id', ['shortname' => 'section']);

    echo '
    <div class="repo">
        <table id="myTable" class="CSSTableGenerator table table-hover course-list-table tablesorter">
            <thead>
                <tr>
                    <th style="text-align:center">Status</th>
                    <th>Roll No</th>
                    <th>Full Name</th>
                    <th style="text-align:center">Section</th>
                    <th style="text-align:center">IP Address</th>
                    <th>Last Submission</th>
                    <th style="text-align:center">Submissions</th>
                    <th style="text-align:center">Grade</th>
                    <th style="text-align:center">Watch</th>
                </tr>
            </thead>
            <tbody>';

    foreach ($students as $student) {

        $roll    = getTCStudentData($student->id, $rollfield);
        $section = getTCStudentData($student->id, $sectionfield);

        // Filter by section
        if ($secname !== 'All' && $section !== $secname) {
            continue;
        }

        // Login status
        $loginstatus = $DB->get_field('userinfo_tsl', 'loginstatus', ['userid' => $student->id]);
        if (in_array($loginstatus, [2, 4])) {
            $loggedin++;
        }

        // Watchlist
        $watch = getStatus($student->id, $cm->course);
        if ($watch) {
            $watchCount++;
        }

        // ================= H5P Attempts =================
        $attempts = $DB->get_records_sql("
    SELECT a.id, a.timemodified, a.ipaddress, a.rawscore, a.maxscore
    FROM {h5pactivity_attempts} a
    WHERE a.h5pactivityid = ? AND a.userid = ?
    ORDER BY a.timemodified DESC
", [$h5p->id, $student->id]);

        // Default values
        $statusImg  = 'flag-red-icon.png';
        $statusNum  = 0;
        $lastsub    = '--';
        $subcount   = '--';
        $grade      = 'NS';
        $gradeNum   = -2;
        $attemptip  = '-';

        if ($attempts) {

            $latest    = current($attempts);
            $attemptip = $latest->ipaddress ?: '-';

            $lastsub  = userdate($latest->timemodified);
            $subcount = count($attempts);
            $submitted++;

            $pct = ($latest->maxscore > 0)
                ? round(($latest->rawscore / $latest->maxscore) * 100)
                : 0;

            $grade    = "$pct / 100";
            $gradeNum = $pct;

            if ($pct >= 100) {
                $graded++;
                $star++;
                $statusImg = 'green-star.png';
                $statusNum = 2;

                if ($watch) {
                    $redstar++;
                    $statusImg = 'red-star.png';
                }

            } elseif ($pct > 0) {
                $graded++;
                $statusImg = 'flag-green-icon.png';
                $statusNum = 2;

            } else {
                $statusImg = 'flag-orange-icon.png';
                $statusNum = 1;
            }
        }

        $watchIcon = $watch ? 'eye-24-512.png' : 'unwatch-512.png';

        echo '
        <tr>
            <td>
                <span style="display:none">'.$statusNum.'</span>
                <img src="'.$CFG->wwwroot.'/local/teacher/testcenter/images/'.$statusImg.'" width="16px"/>
            </td>

            <td>
                <a target="_blank" href="'.$CFG->wwwroot.'/report/outline/user.php?id='.$student->id.'&course='.$course->id.'&mode=outline">
                    '.$roll.'
                </a>
            </td>

            <td>
                <a target="_blank" href="'.$CFG->wwwroot.'/report/outline/user.php?id='.$student->id.'&course='.$course->id.'&mode=outline">
                    '.$student->firstname.' '.$student->lastname.'
                </a>
            </td>

            <td style="text-align:center">'.$section.'</td>
            <td style="text-align:center">'.htmlspecialchars($attemptip).'</td>
            <td>'.$lastsub.'</td>
            <td style="text-align:center">'.$subcount.'</td>

            <td style="text-align:center">
                <span style="display:none">'.$gradeNum.'</span>'.$grade.'
            </td>

            <td>
                <span class="watchlist-status'.$student->id.'" style="display:none">'.($watch ? 1 : 0).'</span>
                <img data-ref="'.($watch ? 1 : 0).'" 
                     id="'.$student->id.'" 
                     class="watchlist" 
                     src="'.$CFG->wwwroot.'/local/teacher/testcenter/images/'.$watchIcon.'" 
                     width="16px"/>
            </td>
        </tr>';
    }

    echo '</tbody></table></div>';

    // Hidden counters
    echo '<span style="display:none" id="loggedinusers">'.$loggedin.'</span>';
    echo '<span style="display:none" id="csubCount">'.$submitted.'</span>';
    echo '<span style="display:none" id="cgradeCount">'.($graded - $star).'</span>';
    echo '<span style="display:none" id="cstarCount">'.($star - $redstar).'</span>';
    echo '<span style="display:none" id="crstarCount">'.$redstar.'</span>';
    echo '<span style="display:none" id="watchCount">'.$watchCount.'</span>';
}
// manual questions adding and evaluate using api key code by chandrika
elseif ($typeid == $activityTypeIds['customactivity']) {  
    // ==================== CUSTOM ACTIVITY (e.g., Addition) ====================

    $cm = get_coursemodule_from_id('customactivity', $actid, 0, false, MUST_EXIST);
$ca = $DB->get_record('customactivity', ['id' => $cm->instance], '*', MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        
      // Get students (role 5 = student)
     $context = context_course::instance($cm->course);
    $students = get_role_users(5, $context); 


     // Counters
    $loggedin = $submitted = $graded = $star = $redstar = $watchCount = 0;

    // Custom fields
     $rollfield = $DB->get_field('user_info_field', 'id', ['shortname' => 'rollno']);
    $sectionfield = $DB->get_field('user_info_field', 'id', ['shortname' => 'section']);

   

    echo '<div class="repo">
        <table id="myTable" class="CSSTableGenerator table table-hover course-list-table tablesorter">
            <thead>
                <tr>
                    <th style="text-align:center" class="header">Status</th>
                    <th class="header">Roll No</th>
                    <th class="header">Full Name</th>
                    <th class="header" style="text-align:center">Section</th>
                    <th class="header" style="text-align:center">IP Address</th>
                    <th class="header">Last Submission</th>
                    <th class="header" style="text-align:center">Grade</th>
                    <th class="header" style="text-align:center">Watch</th>
                </tr>
            </thead>
            <tbody>';

    foreach ($students as $student) {
        $roll = getTCStudentData($student->id, $rollfield);
        $section = getTCStudentData($student->id, $sectionfield);

        // Section filter
        if ($secname !== 'All' && $section !== $secname) continue;

        // Login status
       $loginstatus = $DB->get_field('userinfo_tsl', 'loginstatus', ['userid' => $student->id]);
        if (in_array($loginstatus, [2, 4])) $loggedin++;

        // Watchlist
        $watch = getStatus($student->id, $course->id);
        if ($watch) $watchCount++;

        // Get latest submission for this student + activity
        $submission = $DB->get_record_sql("
            SELECT cs.*, cs.grade as finalgrade
            FROM {customactivity_submissions} cs
            WHERE cs.customactivityid = ? AND cs.userid = ?
            ORDER BY cs.timecreated DESC
            LIMIT 1
        ", [$cm->instance, $student->id]);

        // Default values
        $statusImg = 'flag-red-icon.png';
        $statusNum = 0;
        $lastsub = '--';
        $ipaddr = '-';
        $grade = 'NS';
        $gradeNum = -2;

        if ($submission) {
            $submitted++;
            $lastsub = userdate($submission->timecreated);
            $ipaddr = $submission->ipaddress ?: '-';

            // Determine grade
            if ($submission->grade !== null && $submission->grade >= 0) {
                $grade = $submission->grade . "/100";
                $gradeNum = $submission->grade;

                if ($submission->grade >= 100) {
                    $graded++; $star++;
                    $statusImg = $watch ? 'red-star.png' : 'green-star.png';
                    $statusNum = 2;
                    if ($watch) $redstar++;
                } elseif ($submission->grade > 0) {
                    $graded++;
                    $statusImg = 'flag-green-icon.png';
                    $statusNum = 2;
                } else {
                    $statusImg = 'flag-orange-icon.png';
                    $statusNum = 1;
                }
            } else {
                $statusImg = 'flag-orange-icon.png';
                $statusNum = 1;
                $grade = 'NG';
                $gradeNum = -1;
            }
        }

        $watchIcon = $watch ? 'eye-24-512.png' : 'unwatch-512.png';

        echo '<tr>
            <td><span style="display:none">' . $statusNum . '</span>
                <img src="' . $CFG->wwwroot . '/local/teacher/testcenter/images/' . $statusImg . '" width="16px" /></td>
            <td><a target="_blank" href="' . $CFG->wwwroot . '/report/outline/user.php?id=' . $student->id . '&course=' . $course->id . '&mode=outline">' . htmlspecialchars($roll) . '</a></td>
            <td><a target="_blank" href="' . $CFG->wwwroot . '/report/outline/user.php?id=' . $student->id . '&course=' . $course->id . '&mode=outline">' . fullname($student) . '</a></td>
            <td style="text-align:center">' . htmlspecialchars($section) . '</td>
            <td style="text-align:center" class="ip">' . htmlspecialchars($ipaddr) . '</td>
            <td>' . $lastsub . '</td>
            <td style="text-align:center"><span style="display:none">' . $gradeNum . '</span>' . $grade . '</td>
            <td>
                <span class="watchlist-status' . $student->id . '" style="display:none">' . ($watch ? 1 : 0) . '</span>
                <img data-ref="' . ($watch ? 1 : 0) . '" id="' . $student->id . '" class="watchlist" src="' . $CFG->wwwroot . '/local/teacher/testcenter/images/' . $watchIcon . '" width="16px"/>
            </td>
        </tr>';
    }

    echo '</tbody></table></div>';

    // Hidden counters (for live status bar)
    echo '<span style="display:none" id="loggedinusers">' . $loggedin . '</span>';
    echo '<span style="display:none" id="csubCount">' . $submitted . '</span>';
    echo '<span style="display:none" id="cgradeCount">' . ($graded - $star) . '</span>';
    echo '<span style="display:none" id="cstarCount">' . ($star - $redstar) . '</span>';
    echo '<span style="display:none" id="crstarCount">' . $redstar . '</span>';
    echo '<span style="display:none" id="watchCount">' . $watchCount . '</span>';
}

//generating questions using chat gpt code update by chandrika
elseif ($typeid == $activityTypeIds['customassessment']) {

    // ==================== CUSTOM ASSESSMENT ====================

    $cm = get_coursemodule_from_id('customassessment', $actid, 0, false, MUST_EXIST);
    $ca = $DB->get_record('customassessment', ['id' => $cm->instance], '*', MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

    // Context & students
    $context  = context_course::instance($cm->course);
    $students = get_role_users(5, $context); // roleid 5 = student

    // Counters
    $loggedin = $submitted = $evaluated = $star = $redstar = $watchCount = 0;

    $rollfield    = $DB->get_field('user_info_field', 'id', ['shortname' => 'rollno']);
    $sectionfield = $DB->get_field('user_info_field', 'id', ['shortname' => 'section']);

    echo '<div class="repo">
        <table id="myTable" class="CSSTableGenerator table table-hover course-list-table tablesorter">
            <thead>
                <tr>
                    <th style="text-align:center">Status</th>
                    <th>Roll No</th>
                    <th>Full Name</th>
                    <th style="text-align:center">Section</th>
                    <th style="text-align:center">Last Submission</th>
                    <th style="text-align:center">Grade</th>
                    <th style="text-align:center">Actions</th>
                    <th style="text-align:center">Watch</th>
                </tr>
            </thead>
            <tbody>';

    foreach ($students as $student) {

        $roll    = getTCStudentData($student->id, $rollfield);
        $section = getTCStudentData($student->id, $sectionfield);

        // Section filter
        if ($secname !== 'All' && $section !== $secname) {
            continue;
        }

        // Login status
        $loginstatus = $DB->get_field('userinfo_tsl', 'loginstatus', ['userid' => $student->id]);
        if (in_array($loginstatus, [2, 4])) {
            $loggedin++;
        }

        // Watchlist
        $watch = getStatus($student->id, $course->id);
        if ($watch) {
            $watchCount++;
        }

        // Latest attempt
        $attempts = $DB->get_records(
            'customassessment_attempt',
            [
                'assessmentid' => $ca->id,
                'userid'       => $student->id
            ],
            'id DESC',
            '*',
            0,
            1
        );
$attempt = $attempts ? reset($attempts) : null;
        // Defaults
        $statusImg   = 'flag-red-icon.png';
        $statusNum   = 0;
        $lastsub     = '--';
        $grade       = 'NS';
        $gradeNum    = -2;
        $eye_visible = false;
        $flag_class  = '';

        if ($attempt) {

            // Count pending answers
            $pendingAnswers = $DB->count_records('customassessment_answer', [
                'attemptid' => $attempt->id,
                'evaluated' => 0
            ]);

            $lastsub = userdate(
                $attempt->submitted_at ?: $attempt->started_at,
                '%d %b %Y %H:%M'
            );

            // 🟠 In progress
            if ($attempt->status === 'processing') {
                $statusImg = 'flag-orange-icon.png';
                $statusNum = 1;
                $grade     = 'In Progress';
            }

            // 🔴 Submitted but pending evaluation
           else if (
                $attempt->status === 'submitted'
                || ($attempt->status === 'evaluated' && $pendingAnswers > 0)
            ) {

                $submitted++;

                $statusImg   = 'flag-red-icon.png';
                $statusNum   = 1;
                $grade       = 'Submitted';
                $flag_class  = 'bg-danger';
                $eye_visible = true;
            }

            // 🟢 Fully evaluated
            else if ($attempt->status === 'evaluated' && $pendingAnswers == 0) {

                $submitted++;
                $evaluated++;

                $gradeNum = (int)($attempt->score ?? 0);
                $grade    = $gradeNum . ' / 100';

                $statusImg   = 'flag-green-icon.png';
                $statusNum   = 2;
                $eye_visible = true;
            }
        }

        $watchIcon = $watch ? 'eye-24-512.png' : 'unwatch-512.png';

        echo '<tr>';

        // Status
        echo '<td style="text-align:center">
                <span style="display:none">' . $statusNum . '</span>
                <img src="' . $CFG->wwwroot . '/local/teacher/testcenter/images/' . $statusImg . '" width="16">
              </td>';

        // Roll
        echo '<td>
                <a target="_blank" href="' . $CFG->wwwroot . '/report/outline/user.php?id=' .
                $student->id . '&course=' . $course->id . '&mode=outline">' .
                htmlspecialchars($roll) . '</a>
              </td>';

        // Name
        echo '<td>
                <a target="_blank" href="' . $CFG->wwwroot . '/report/outline/user.php?id=' .
                $student->id . '&course=' . $course->id . '&mode=outline">' .
                fullname($student) . '</a>
              </td>';

        // Section
        echo '<td style="text-align:center">' . htmlspecialchars($section) . '</td>';

        // Last submission
        echo '<td>' . $lastsub . '</td>';

        // Grade
        echo '<td style="text-align:center">
                <span style="display:none">' . $gradeNum . '</span>' . $grade;

        if ($flag_class) {
            echo ' <span class="badge ' . $flag_class . ' ms-1">Pending</span>';
        }

        echo '</td>';

        // Actions
        echo '<td style="text-align:center">';
        if ($eye_visible) {
            echo '<button class="btn btn-sm btn-outline-primary view-attempt"
                    data-attemptid="' . $attempt->id . '"
                    data-student="' . fullname($student) . '">
                    <i class="fa fa-eye"></i>
                  </button>';
        }
        echo '</td>';

        // Watch
        echo '<td style="text-align:center">
                <span class="watchlist-status' . $student->id . '" style="display:none">' . ($watch ? 1 : 0) . '</span>
                <img class="watchlist"
                     data-ref="' . ($watch ? 1 : 0) . '"
                     id="' . $student->id . '"
                     src="' . $CFG->wwwroot . '/local/teacher/testcenter/images/' . $watchIcon . '"
                     width="16">
              </td>';

        echo '</tr>';
    }

    echo '</tbody></table></div>';

    // Hidden counters
    echo '<span id="loggedinusers" style="display:none">' . $loggedin . '</span>';
    echo '<span id="csubCount" style="display:none">' . $submitted . '</span>';
    echo '<span id="cgradeCount" style="display:none">' . $evaluated . '</span>';
    echo '<span id="cstarCount" style="display:none">' . ($star - $redstar) . '</span>';
    echo '<span id="crstarCount" style="display:none">' . $redstar . '</span>';
    echo '<span id="watchCount" style="display:none">' . $watchCount . '</span>';
}

else{
require_once('quizsubmissions.php');
}
//generating questions using chat gpt code update by chandrika
echo '
<div class="modal fade" id="submissionModal" tabindex="-1" aria-labelledby="submissionModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title" id="submissionModalLabel">
          Student Submission Details
        </h5>
        <!-- Works in Bootstrap 4 AND 5 -->
        <button type="button" class="btn-close" 
                data-dismiss="modal" data-bs-dismiss="modal" 
                aria-label="Close"></button>
      </div>

      <div class="modal-body" id="modal-body-content">
        <div class="text-center py-5">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
          <p class="mt-3">Loading submission details...</p>
        </div>
      </div>

      <div class="modal-footer">
        <!-- Also works in both versions -->
        <button type="button" class="btn btn-secondary" 
                data-dismiss="modal" data-bs-dismiss="modal">
          Close
        </button>
        <button type="button" id="evaluateThisStudentBtn" 
                class="btn btn-primary" style="display:none;">
          Evaluate This Student
        </button>
      </div>

    </div>
  </div>
</div>';
?>
</body>
</html>

<script>
    //generating questions using chat gpt code update by chandrika
$(document).on('click', '.view-attempt', function () {

    var attemptid   = $(this).data('attemptid');
    var studentName = $(this).data('student');

    if (!attemptid) {
        alert("No submission found.");
        return;
    }

    $('#submissionModalLabel').text('Submission by ' + studentName);

    $('#modal-body-content').html(
        '<div class="text-center py-5">' +
        '<div class="spinner-border text-primary" role="status">' +
        '<span class="visually-hidden">Loading...</span>' +
        '</div>' +
        '<p class="mt-3">Loading submission details...</p>' +
        '</div>'
    );

    $('#evaluateThisStudentBtn').hide();

    $('#submissionModal').modal('show');

    $.ajax({
        url: M.cfg.wwwroot + '/local/teacher/testcenter/student_attempt_details.php',
        type: 'GET',
        data: { attemptid: attemptid },
        dataType: 'html',
        cache: false,
        timeout: 10000
    })
    .done(function (html) {
        // Step 1: Remove any leading BOM / invisible chars / whitespace that breaks parsing
        html = html.replace(/^\s*[\uFEFF\u200B\uFEFF\u2028\u2029]+/, '').trim();

        // Step 2: Insert as HTML
        $('#modal-body-content').html(html);

        // Step 3: Check for the flag (with fallback)
        var isEvaluated = $('#modal-body-content').find('#is-evaluated').val() || '0';
        if (isEvaluated !== '1') {
            $('#evaluateThisStudentBtn').show();
        }

        // Optional debug: confirm insertion
        console.log('HTML inserted successfully. Found evaluated flag:', isEvaluated);
        console.log('First 200 chars after clean:', html.substring(0, 200));
    })
    .fail(function (jqXHR, textStatus, errorThrown) {
        console.error('AJAX failed:', textStatus, errorThrown);
        console.error('Status code:', jqXHR.status);
        console.error('Response preview:', jqXHR.responseText ? jqXHR.responseText.substring(0, 300) : 'No response');

        $('#modal-body-content').html(
            '<div class="alert alert-danger">Error loading details: ' + textStatus + '</div>'
        );
    });
});
</script>