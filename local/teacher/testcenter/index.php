

<?php
// error_reporting(E_ALL);
// ini_set('display_errors', 1);
require_once('../../../config.php');
require_once('../../../mod/vpl/locallib.php');
require_once('../../../enrol/locallib.php');
require_once('testcenterutil.php');
// require_once('sub_list_head.php');
require_once('testcentercss.php');
require_once('testcenter_sublisthead.php');

require_once($CFG->dirroot.'/local/student/custom_tele.php');
// $PAGE->requires->css('local/css/custom.css');
$PAGE->set_pagelayout('standard');
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Tessellator 5.0-testcenter');


//retriving course id from url

$params = explode("-", $_GET['topics']);
// var_dump($params);
$cid=(int)$params[0];  // course id
$secid=(int)$params[1];   //section id or topicid inthe course

if(!$id){
    $cid = optional_param('cid',0, PARAM_INT);
    $secid = optional_param('secid',0, PARAM_INT);
}
if($cid){
    $course=get_course($cid);
}
// === JSH5P crossword code by chandrika===
$PAGE->requires->jquery();
$PAGE->requires->js_init_code('
require(["jquery"], function($) {
    var cid = ' . $cid . ';
    var secid = ' . $secid . ';

    $(document).on("click", ".students", function() {
        var actid = $(this).data("mid");
        var typeid = $(this).data("typeid") || 0;
        var secname = $("#hcstu-section").val() || "All";

        $("#sub_list").html(
            \'<div style="text-align:center;padding:20px;">\' +
            \'<img src="\' + M.cfg.wwwroot + \'/pix/loading.gif" alt="Loading...">\' +
            \'</div>\'
        );

        $.get("' . $CFG->wwwroot . '/local/teacher/testcenter/sub_list.php", {
            id: actid,
            typeid: typeid,
            secname: secname,
            cid: cid,
            secid: secid
        }, function(data) {
            $("#sub_list").html(data);
            $(".students").removeClass("text-primary");
            $(this).addClass("text-primary");
        }).fail(function(xhr, status, error) {
            console.error("AJAX Error:", status, error);
            $("#sub_list").html(\'<p style="color:red;">Error loading submissions: \' + error + \'</p>\');
        });
    });

    // Auto-refresh
    setInterval(function() {
        var $active = $(".students.text-primary");
        if ($active.length) $active.trigger("click");
    }, 15000);
});
');
// $PAGE->set_heading('Teacher dashboard');
require_login();
if (!(user_has_role_assignment($USER->id,3) ) ) {

    redirect($CFG->wwwroot);
}



else{
    //redirect($CFG->wwwroot.'/local/teacher/dashboard.php');
}
echo '<input type="hidden" id="courseid" value="'.$cid.'" />';

//live notification code by chandrika
$payload = [
    'userid'   => $USER->id,
    'courseid' => $cid,
    'role'     => 'teacher',
    'exp'      => time() + 300 // 5 minutes expiry
];

$secret = 'a8f3d92e8c4b9e0a7c6f1b9a4e72c93f8d21c90a6d5b1f2e8c7a9b0d1e3f4c5';
$json = json_encode($payload);
$payload_b64 = base64_encode($json);
$signature = hash_hmac('sha256', $payload_b64, $secret);
$token = $payload_b64 . '.' . $signature;
$wsToken = $token;
//end of live notification code by chandrika
// generating questions using chat gpt code by chandrika

// ================= Queue Summary (Evaluation Progress) for assessment by chandrika=================
$queuesummary = $DB->get_record_sql(
    "SELECT
        COUNT(ca.id) AS total,
        SUM(ca.status = 'evaluated')  AS evaluated,
        SUM(ca.status = 'processing') AS processing,
        SUM(ca.status = 'submitted')  AS submitted
     FROM {customassessment_attempt} ca
     JOIN {customassessment} c ON c.id = ca.assessmentid
     WHERE c.courseid = ?
       AND ca.status IN ('submitted','processing','evaluated')",
    [$cid]
);

$total      = (int)$queuesummary->total;
$evaluated  = (int)$queuesummary->evaluated;
$processing = (int)$queuesummary->processing;
$submitted  = (int)$queuesummary->submitted;

// current position
$current = $evaluated + ($processing > 0 ? 1 : 0);

$total      = (int)($queuesummary->total ?? 0);
$evaluated  = (int)($queuesummary->evaluated ?? 0);
$processing = (int)($queuesummary->processing ?? 0);

// Current evaluation position
$current = $evaluated + ($processing > 0 ? 1 : 0);
// =========================================================
//echo '<input type="hidden" id="userid" value="'.$USER->id.'" />';


/*initializing required parameters*/
$currentActivityId=0;
$currentActivityStatus=0;
$currentModTypeId=0;
$currentActivityName='--';


$modinfo = get_fast_modinfo($course);
$mods = $modinfo->get_cms();
$sections = $modinfo->get_section_info_all();
$secname=get_sections_name($secid,$sections);
$topicname=$secname;
$currentgroup ='';
$context_module=context_course::instance($cid);


$remoteFlag=0;
//preparing an array which contains sections and activities
foreach ($mods as $mod) {

    $gradeitemid=get_itemid_from_grade_table($mod->instance,$mod->modname,$cid);

    $modstatus=$DB->get_field('activity_status_tsl', 'status', array('activityid' => $mod->id));
    $modstopdate=$DB->get_field('activity_status_tsl', 'activity_stop_time', array('activityid' => $mod->id));
    $modstartdate=$DB->get_field('activity_status_tsl', 'activity_start_time', array('activityid' => $mod->id));



    $arr[$cnt++]=array('secid'=>$mod->section,'modid'=>$mod->id,'modname'=>$mod->name,'modcontent'=>$mod->content,'modtypeid'=>$mod->module,'itemid'=>$gradeitemid,'modstatus'=>$modstatus,'modstop'=>$modstopdate,'modstart'=>$modstartdate,'remoteactivity'=>$remoteactivity,'modinstanceid'=>$mod->instance);

}
$activities=get_activities($secid,$arr);

//get activities of a section
function get_activities($sectionid,$arr)
{

    $cnt=0;
    $sec_activity_array = array();
    foreach($arr as $a){

        if(($a['secid']==$sectionid)&&($a['modtypeid']!=7)&&($a['modtypeid']!=7)) {
            if ($a['secid'] == $sectionid) {

                $sec_activity_array[$cnt] = array('modid' => $a['modid'], 'modname' => $a['modname'], 'modcontent' => $a['modcontent'], 'modtypeid' => $a['modtypeid'], 'modstatus' => $a['modstatus'], 'itemid' => $a['itemid'], 'modstop' => $a['modstop'], 'modstart' => $a['modstart'], 'remoteactivity' => $a['remoteactivity'],'modinstanceid' => $a['modinstanceid']);
                $cnt++;
            }
        }
    }

    return $sec_activity_array;
}

 //get current section name among all sections in the course
function get_sections_name($sectionid,$sections)
{

    foreach ($sections as $sec) {
        if($sec->id==$sectionid){
            return $sec->name;
        }
    }

}



/*logic to get current logged in users */

$loggedinusers=get_loggedin_users_by_section('All');
$studentSections=get_student_sections($cid);


/*logic to get total number of participants in course*/

$totalenrolled=array_sum(array_column($studentSections, 'seccount'));
$baseUrl=$CFG->wwwroot;

/*page content display start*/

echo $OUTPUT->header();

echo '<div class="container container-demo">

                     <div class="report">';



// code to display loading image
echo '<div  class="pagecover-onload">
		    <div style="width: 600px; height: 45px; text-align: center; margin: 180px auto 0px;">
			<div>PLEASE WAIT</div><div><img src="'.$baseUrl.'/local/teacher/testcenter/images/loader.gif"></div>
		   </div>
		    <div style="width: 600px; margin: 10px auto; text-align: center; color: rgb(100, 100, 100);">
		    <div class="loading-msg"></div>
		    </div>
		</div>';


/*****************************************status bar start*********************************************/

if($remoteFlag){
    echo '
<div class="switch-button">
<input type="button" id="switch-label" value="showing teleuniv.net status"/>
<input type="button" id="switch" value="switch to teleuniv.net"/>
</div>
<div style="clear: both"></div>';
}

//topic name chnages and live notification code by chandrika
echo '<div class="current-activity row">
<div class="col-md-4 text-left" style="padding-left: 30px;padding-top: 7px;">
        <strong>TOPIC NAME:</strong>
        <span style="color: #4ecdc4; font-weight: bold; margin-left: 10px; ">' . format_string($topicname ? $topicname : 'No Topic') . '</span>
    </div>

           <div class="col-md-4 text-center" style="padding-top: 7px;">
           <strong>ACTIVITY NAME:</strong><span style="color: #4ecdc4; font-weight: bold; margin-left: 10px;" class="progress-activity">'.$currentActivityName.'</span></div>

           <div class="col-md-4 text-right" style="padding-right: 30px; padding-top: 7px;">
           <strong>STATUS:</strong>STATUS :<span style="color: #51f551; font-weight: bold; margin-left: 10px;" class="progress-activity-status">'.$currentActivityStatus.'</span></div>
       </div>


<div style="margin-top: 20px; margin-bottom: 20px; text-align: right; padding: 10px; background: #f0f0f0; border: 1px solid #ddd;">
    <button id="send-message" class="btn btn-primary" style="padding: 14px 28px !important; background: linear-gradient(135deg, #e67e22 0%, #d35400 100%) !important; color: white !important; border: none !important; border-radius: 12px !important; cursor: pointer; font-size: 16px !important; font-weight: 700 !important; box-shadow: 0 6px 16px rgba(230,126,34,0.4) !important; min-width: 200px;">
        📤 Send Notification
    </button>
</div>


<div id="notifyModal" style="display:none; position: fixed; top:0; left:0; width:100%; height:100%; background: rgba(0,0,0,0.6); justify-content:center; align-items:center; z-index:1000; backdrop-filter: blur(5px);">
    <div style="background: linear-gradient(135deg, #ffffff 0%, #fff9e6 100%); padding:40px; border-radius:20px; width:90%; max-width:600px; border:2px solid #f8e5c6; box-shadow:0 10px 30px rgba(230,126,34,0.2); position:relative;">
        <span onclick="closeModal()" style="position:absolute; top:20px; right:24px; font-size:32px; cursor:pointer; color:#a0826d;">×</span>
        
        <div style="text-align:center; margin-bottom:32px;">
            <h2 style="font-size:32px; font-weight:800; background:linear-gradient(135deg,#e67e22 0%,#d35400 100%); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; display:flex; align-items:center; justify-content:center; gap:12px;">
                Send Notification to Students
            </h2>
            <p style="color:#a0826d; font-size:16px;">Broadcast a real-time message to all connected students</p>
        </div>

        <!-- Quick Messages - FIXED WITH SINGLE QUOTES FOR ONCLICK -->
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:12px; margin-bottom:32px;">
            <button onclick="setQuickMessage(\'There will be a short system update in 10 minutes. Please save your work.\')" style="padding:14px 18px; background:white; border:2px solid #f8e5c6; color:#d35400; border-radius:10px; cursor:pointer; font-size:14px; font-weight:600;">
                System Update Soon
            </button>
            <button onclick="setQuickMessage(\'Quiz will start in 10 minutes. Get ready!\')" style="padding:14px 18px; background:white; border:2px solid #f8e5c6; color:#d35400; border-radius:10px; cursor:pointer; font-size:14px; font-weight:600;">
                Quiz Starting Soon
            </button>
            <button onclick="setQuickMessage(\'Urgent notice: Please check the latest instructions.\')" style="padding:14px 18px; background:white; border:2px solid #f8e5c6; color:#d35400; border-radius:10px; cursor:pointer; font-size:14px; font-weight:600;">
                Important Updates
            </button>
            <button onclick="setQuickMessage(\'Class starts in 5 minutes!\')" style="padding:14px 18px; background:white; border:2px solid #f8e5c6; color:#d35400; border-radius:10px; cursor:pointer; font-size:14px; font-weight:600;">
                Class Starting Soon
            </button>
        </div>

        <div style="margin-bottom:24px;">
            <label style="display:block; font-weight:700; color:#5d4037; margin-bottom:12px; font-size:15px; text-transform:uppercase; letter-spacing:0.5px;">Message Content</label>
            <textarea id="notifyMessage" placeholder="Type your notification message here..." style="width:100%; padding:16px; border:2px solid #f8e5c6; border-radius:12px; font-size:16px; min-height:150px; resize:vertical; background:white; color:#5d4037;"></textarea>
        </div>

    <div style="display:flex; gap:16px; justify-content:flex-end; margin-top:24px;">
    <button
    type="button"
    class="btn btn-primary"
    id="sendModalBtn"
    onclick="sendNotification()">
    Send to All Students
</button>

    <button
        type="button"
        class="btn btn-secondary"
        onclick="closeModal()">
        Cancel
    </button>
</div>

        <div id="responseMessage" style="padding:16px 20px; border-radius:10px; margin-top:20px; font-size:15px; font-weight:600; display:none;"></div>
    </div>
</div>


<div class="row status-row non-kmit-status">

	<div class="status-div">
          <div class="status-divs">
          <button  id="reset-logins" class="btn btn-color-primary btn-small">Reset Testcenter</button>
		  </div>

		  <div class="login-count-div status-divs " title="Logged In">
		  <div class="status-numbers logincount-status-numbers">
		  <div class="login-count-indicator indicator"><span class="status-label">L</span></div>
          <div class="statuscounts"><span class="loggedinCount">'.$loggedinusers.'</span> <span class="of-lable">of</span> <span class="studentCount">'.$totalenrolled.'</span></div>
          </div>
          </div>

         <div class="csubCount-div status-divs " title="Submited">
         <div class="status-numbers csubCount-status-numbers">
         <div class="csubCount-indicator indicator"><span class="status-label">S</span></div>
         <div class="statuscounts"><span class="csubCount" id="csubCount">0</span> <span class="of-lable">of</span> <span class="loggedinCount">'.$loggedinusers.'</span></div>
         </div>
         </div>

         <div class="cgradeCount-div status-divs " title="Graded">
         <div class="status-numbers cgradeCount-status-numbers">
         <div class="cgradeCount-indicator indicator"><span class="status-label">G</span></div>
         <div class="statuscounts"><span class="cgradeCount" id="cgradeCount">0</span> <span class="of-lable">of</span> <span class="loggedinCount">'.$loggedinusers.'</span></div>
         </div>
         </div>

         <div id="btngreenStars" class="cstarCount-div status-divs " title="GreenStars">
         <div class="status-numbers cstarCount-status-numbers">
         <div class="cstarCount-indicator indicator"><i aria-hidden="true" class="fa  fa-star star-img "></i></div>
         <div class="statuscounts"><span class="cstarCount" id="cstarCount">0</span> <span class="of-lable">of</span> <span class="loggedinCount">'.$loggedinusers.'</span></div>
         </div>
         </div>


        <div id="btnredStars" class="crstarCount-div status-divs" title="RedStars">
        <div class="status-numbers crstarCount-status-numbers">
        <div class="crstarCount-indicator indicator"><i aria-hidden="true" class="fa  fa-star star-img"></i></div>
        <div class="statuscounts"><span class="crstarCount" id="crstarCount">0</span> <span class="of-lable">of</span> <span id="watchCount">'.$loggedinusers.'</span></div>
        </div>
        </div>

          <div id="status-div" class="status-divs ">

            <div class="loading-img pagecover " style=" float: left;    width: 50%;">
            <img src="'.$CFG->wwwroot.'/pix/loading.gif" style="margin-left:6%;" title="filter">
            </div>
            <div class="refresh-icons ">
            <i  id="refresh" class="fa fa-1x fa-refresh" aria-hidden="true" title="REFRESH STUDENT PERFORMANCE"></i>
	        </div>

            </div>


     </div>

</div>';

// generating questions using chat gpt code by chandrika
$totalstudents = $DB->count_records_sql("
    SELECT COUNT(DISTINCT ue.userid)
    FROM {user_enrolments} ue
    JOIN {enrol} e ON e.id = ue.enrolid
    WHERE e.courseid = ?
", [$cid]);
if ($totalstudents  == 0) {
    // No submissions at all
    // Show nothing
}
else if ($processing > 0) {
    $current = $evaluated + 1;
    echo "
    <div class='alert alert-info' style='margin:10px 20px; text-align:right;'>
        <strong>Evaluation Status:</strong>
        Evaluating <b>{$current} / {$totalstudents}</b>
        <br><small>{$submitted} submitted so far</small>
    </div>";
}
else if ($evaluated == $submitted && $submitted > 0) {
    echo "
    <div class='alert alert-success' style='margin:10px 20px; text-align:right;'>
        <strong>Evaluation Status:</strong>
        Completed <b>{$evaluated} / {$totalstudents}</b>
    </div>";
}
else {
    $notattempted = $totalstudents - ($evaluated + $submitted);
    echo "
    <div class='alert alert-warning' style='margin:10px 20px; text-align:right;'>
        <strong>Evaluation Status:</strong>
        {$evaluated} / {$totalstudents} evaluated
        <br><small>{$notattempted} students yet to submit</small>
    </div>";
}
echo'<div style="margin: 12px 0; text-align:right; padding-right:20px;">
    <button type="button" id="evaluateAllBtn" class="btn btn-primary">
        Evaluate All 
    </button>
    <span id="evalProgress" style="margin-left:15px; font-weight:bold; color:#555; display:none;">
        Evaluating... <span id="evalCurrent">0</span>/<span id="evalTotal">?</span>
    </span>
</div>';

echo '<input type="hidden" id="sesskey" value="' . sesskey() . '">';
?>


<!-- /*****************************************status bar end*********************************************/ -->





<!-- tabs left side menu -->
<div class="tabbable tabs-left">

    <ul class="testcenter-tabs nav nav-tabs ">
           <li id="activities-info" class="active"><a href="#a" data-toggle="tab">Activities
                <i class="fa fa-angle-double-right" aria-hidden="true"></i></a>
           </li>

           <li id="students-status"><a href="#b" data-toggle="tab">Students
                <i class="fa fa-angle-double-right" aria-hidden="true"></i></a>
           </li>

           <li id="filterdiv">
              <?php echo '<span data-id="'.$totalenrolled.'" >All</span>';
              for($i=0;$i<count($studentSections);$i++){
                echo '<span data-id="'.$studentSections[$i]['seccount'].'" >'.$studentSections[$i]['secname'].'</span>';
                }  ?>
           </li>

           <li id="btngreenStars" ><a>Green Stars
                <i class="fa fa-angle-double-right" aria-hidden="true"></i></a>
           </li>

           <li id="btnredStars" ><a>Red Stars
                <i class="fa fa-angle-double-right" aria-hidden="true"></i></a>
            </li>

            <!-- <li id="btnSimilarity" ><a>Similarity
                <i class="fa fa-angle-double-right" aria-hidden="true"></i></a>
            </li> -->
      </ul>

    <div class="tab-content">
        <div class="tab-pane active" id="a">

            <ul class="nav nav-tabs course-detail-tabs">

                <li class="active"><a href="#foo" id="btnFoo" data-toggle="tab" class="current">Activity Status </a></li>
                <li><a href="#bar"  id="btnBar" data-toggle="tab">Current Activities</a></li>
                      <?php
                                            echo ' <li><a href="'.$CFG->wwwroot.'/local/teacher/testcenter/facelogin_enable.php?cid='.$cid.'&topicid='.$secid.'" target="_blank">Enable Facelogin</a></li>';

                        /* sonet feedback button start*/
                        if(in_array($_GET['cid'],$CFG->sonetcids)):
                       $sonettimetable=$CFG->sonettimetable;
                       $courseyear=$sonettimetable[date("D",time())];

                       foreach($courseyear as $ckey => $cval){
                         if($cval['cid']==$_GET['cid']){
                             $cyear=$cval['currentyear'];
                            }
                          }
                        if(!checkAskedForFeedback($_GET['cid'],$_GET['secid'])):
                       ?>
                        <button data-cyear="<?php echo $cyear ?>" data-cid="<?php echo $_GET['cid'] ?>" data-secid="<?php echo $_GET['secid'] ?>" style="float:right" id="askfeedback" class="btn btn-color-primary btn-small">Ask Feedback</button>
                        <button title="Already taken for this session" data-cyear="<?php echo $cyear ?>"  data-cid="<?php echo $_GET['cid'] ?>" data-secid="<?php echo $_GET['secid'] ?>"  style="float:right" id="askfeedback" class="btn btn-color-primary btn-small" disabled>Ask Feedback</button>
                    <?php endif;
                     endif;
                    /* sonet feedback button end*/
                    ?>
             </ul>   <!-- /.course-detail-tabs -->

              <div class="control-panel-data">

                <div class="example fade in active" id="foo">
                     <!-- <div id='panel'> -->
                   <div class="tright" >

                     <table id="t01" class="table table-hover course-list-table tablesorter topics-div activities-topics-div">
                      <thead>
                       <tr>
                         <th class="header">Select</th>
                         <th class="header"> Type</th>
                         <th  class="header" >Activity Name</th>
                         <th class="header">Status</th>
                         <th class="header">Actions</th>
                         <th class="header">Students</th>
                       </tr>
                      </thead>

              <?php
                 $aid_status=array();$flag=1;
                 for($i=0;$i<count($activities);$i++) {

                     $aid_status[$i]['id']=$activities[$i]['modid'];
                     $aid_status[$i]['status']=$activities[$i]['modstatus'];
                     $aid_status[$i]['start']=$activities[$i]['modstart'];
                     $aid_status[$i]['stop']=$activities[$i]['modstop'];
                     if($DB->get_field('course_modules', 'completionexpected', array('id' => $activities[$i]['modid']))){

                         $completiondate=userdate($DB->get_field('course_modules', 'completionexpected', array('id' => $activities[$i]['modid'])));
                         $completedactivity="markascomplete";
                     }
                     else{
                         $completedactivity="";$completiondate='';
                     }
                     $radioButton='';
                     if(($activities[$i]['modstatus']==1)&&($activities[$i]['modstatus']!=2)){
                         //var_dump($activities[$i]['modid']);print_r("<br/>");
                         if($flag){
                             $currentActivityId=$activities[$i]['modid'];
                             $currentActivityStatus=$activities[$i]['modstatus'];
                             $currentModTypeId=$activities[$i]['modtypeid'];
                             $currentActivityName=$activities[$i]['modname'];
                             $radioButton="checked=checked";
                             $flag=0;
                         }
                         else{
                             $radioButton='';
                         }

                     }

                     /*logic to check whether the activity is lab or quiz*/
                     if($activities[$i]['modtypeid']==$activityTypeIds['vpl']){
                         $activityType='LAB';
                     }
                     else{
                         if($activities[$i]['modtypeid']==$activityTypeIds['quiz']){
                             $activityType='QUIZ';
                         }
                         elseif($activities[$i]['modtypeid']==$activityTypeIds['adobeconnect']){
                             $activityType='ADOBE';
                             $adobeurl=getAdobeUrl($activities[$i]['modid'],$activities[$i]['modname']);
                         }
                         else if($activities[$i]['modtypeid']==$activityTypeIds['teleconnect']){
                             $activityType='WEBINAR';
                             $teleurl=getTeleUrl($activities[$i]['modid'],$activities[$i]['modname']);
                         }
                         else if($activities[$i]['modtypeid']==$activityTypeIds['feedback']){
                            $activityType='FEEDBACK';
                         }
                         else if($activities[$i]['modtypeid']==$activityTypeIds['assign']){
                            $activityType='ASSIGNMENT';
                         }
                            else if($activities[$i]['modtypeid']==$activityTypeIds['url']) {
                                $activityType='URL';
                        }
                        //hp5 crossword updated by chandrika
                        else if($activities[$i]['modtypeid'] == $activityTypeIds['h5pactivity']) {
                            $activityType = 'H5P';         
                        }
                        // manual questions adding and evaluate using api key code by chandrika
                        else if ($activities[$i]['modtypeid'] == $activityTypeIds['customactivity']) {

    $activityType = get_string('modulename', 'customactivity');
                    }
                    //generating questions by using chat gpt code by chandrika
                    else if ($activities[$i]['modtypeid'] == $activityTypeIds['customassessment']) {

    $activityType = get_string('modulename', 'customassessment');
                    } 
                                            else{
                            //  $activityType="--";
                            continue;
                         }
                     }
                     ?>
                     <tbody>
                     <?php
                    echo '<tr data-status="'.$activities[$i]['modstatus'].'" class="row' . $activities[$i]['modid'] . ' '.$completedactivity.'">
             <td ><span class="mod' . $activities[$i]['modid'] . ' '.$completedactivity.'"></span>
             <input type="hidden" id="adobe-' . $activities[$i]['modid'] . '" value="'.$adobeurl.'"/>
             <input type="hidden" id="tele-' . $activities[$i]['modid'] . '" value="'.$teleurl.'"/>
             <input data-status="'.$activities[$i]['modstatus'].'" ';

                     if($completiondate){
                         echo ' disabled="true" style="cursor:not-allowed" ';
                     }
                     echo 'name="radio-button" data-rmodid="' . $activities[$i]['remoteactivity'] . '"   data-mid="' . $activities[$i]['modtypeid'] . '" data-id="' . $activities[$i]['modid'] . '" type="radio" class="radio-activity radio-activity' . $activities[$i]['modid'] . '"  '.$radioButton.' />
             </td>
             <td style="text-align:center"><span class="actype" ><b>'.$activityType.'</b></span></td>
             <td style="width:30%;" ><span class="mod' . $activities[$i]['modid'] . ' activitymod' . $activities[$i]['modid'] . ' '.$completedactivity.'">' . $activities[$i]['modname'];

                     echo '</span></td>';
                     if($completiondate) {

                         echo '<td style="width:18%"><span class="status-text closed actstatus' . $activities[$i]['modid'] . '"><b>CLOSED</b><BR/><span style="line-height: 12px;"> on '.$completiondate .'</span></span></td>';

                     }
                     elseif(($aid_status[$i]['status']==0)&&(!empty($activities[$i]['modstatus']))){
                         echo '<td style="width:18%"><span class="status-text stopped actstatus' . $activities[$i]['modid'] . '"><b>STOPPED</b><BR/><span style="line-height: 12px;"> on '.userdate($aid_status[$i]['stop']) .'</span></span></td>';
                         $stopped='';
                     }
                     elseif($aid_status[$i]['status']==1){
                         echo '<td style="width:18%"><span class="status-text started actstatus' . $activities[$i]['modid'] . '"><b>STARTED</b><BR/><span style="line-height: 12px;"> on '.userdate($aid_status[$i]['start']) .'</span></span></td>';
                         $started='';
                     }
                     else{
                         echo '<td style="width:18%"><span   class="status-text actstatus' . $activities[$i]['modid'] . '"><b>NOT STARTED</b></span></td>';
                     }


                     echo '<td style="vertical-align: middle;" >
             <button data-rmodid="' . $activities[$i]['remoteactivity'] . '" data-mid="' . $activities[$i]['modtypeid'] . '" class="showhide show show' . $activities[$i]['modid'] . '" id="show" value=' . $activities[$i]['modid'];

                     if(($completiondate)||($aid_status[$i]['status'])){
                         echo ' disabled="true" style="cursor:not-allowed" ';
                     }
                     echo '>
                 Start</button>

             <button data-rmodid="' . $activities[$i]['remoteactivity'] . '"  data-mid="' . $activities[$i]['modtypeid'] . '" class="showhide stop hide' . $activities[$i]['modid'] . '" id="hide" value=' . $activities[$i]['modid'];

                     if($completiondate||!($aid_status[$i]['status'])){
                         echo ' disabled="true" style="cursor:not-allowed" ';
                     }

                     echo '>Stop</button>
                             <button data-rmodid="' . $activities[$i]['remoteactivity'] . '"  data-itemid="'.$activities[$i]['itemid'].'" data-mid="' . $activities[$i]['modtypeid'] . '" id="complete" class="stopclose  complete complete' . $activities[$i]['modid'] . '" value="' . $activities[$i]['modid'].'"';
                     if($completiondate||($aid_status[$i]['status'])){
                         echo ' disabled="true" style="cursor:not-allowed" ';
                     }
                     if(!($aid_status[$i]['status'])){
                         echo ' disabled="false" style="cursor:pointer" ';
                     }
                     echo '>
                             Close</button>
                             </td>';
                             if($activities[$i]['modtypeid']==$activityTypeIds['assign']) {
                            echo ' <td><a href="'.$CFG->wwwroot.'/local/teacher/assignments/index.php?name='.$activities[$i]['modname'].'&instanceid='.$activities[$i]['modinstanceid'].'&cid='.$cid.'&secname=All&actid='.$activities[$i]['modid'].'" class="fa fa-users fa-2x" style="color:black" target="_blank" aria-hidden="true"></a></td>';
                    }
                    // elseif($activities[$i]['modtypeid']==$activityTypeIds['quiz'] || ($activities[$i]['modtypeid']==$activityTypeIds['vpl'])) {
                    //         echo ' <td><i data-mid="' . $activities[$i]['modid'] . '" class="fa fa-users fa-2x students"  aria-hidden="true"></i></td>';
                    // }
                    // else{
                    //     echo ' <td><i data-mid="' . $activities[$i]['modid'] . '" class="fa fa-users fa-2x  no-submission" aria-hidden="true"></i></td>';

                    // }
// h5p crossword updated by chandrika
                            elseif(in_array($activities[$i]['modtypeid'], [
    $activityTypeIds['quiz'], 
    $activityTypeIds['vpl'], 
    $activityTypeIds['h5pactivity'], 
    $activityTypeIds['customactivity'], //manual questions adding and evaluate using api key code by chandrika
    $activityTypeIds['customassessment']
])) {
    echo '<td>
        <i 
            data-mid="'.$activities[$i]['modid'].'" 
            data-typeid="'.$activities[$i]['modtypeid'].'" 
            class="fa fa-users fa-2x students" 
            style="cursor:pointer; color:#007bff;" 
            title="Click to view submissions"
            aria-hidden="true">
        </i>
    </td>';
}
                           echo '  </tr>';
                            ?>
                         </tbody>
               <?php    } ?>
                 </table>

                     </div>   <!--end of tright   -->
                   </div>     <!-- end of foo -->





             <div class="example  fade" id="bar" >


   <?php
    echo '<div class="tright">
    <table id="t01" class="table table-hover course-list-table tablesorter topics-div">';
      ?>
    <thead>
   <tr>
    <th class="header">Select</th>
    <th class="header">Activity</th>
    <th  class="header" >Activity Name</th>
    <th class="header">Logged In</th>
    <th class="header">Submitted</th>
    <th class="header">Graded</th>
    <th class="header">Green Stars</th>
    <th class="header">Red Stars</th>
    <th class="header">Status</th>
    <th  class="header">Refresh</th>

   </tr>
   </thead>
                </tbody>
   <?php
    $aid_status=array();$pflag=1;
   for($i=0;$i<count($activities);$i++) {

    $aid_status[$i]['id']=$activities[$i]['modid'];
    $aid_status[$i]['status']=$activities[$i]['modstatus'];
    $aid_status[$i]['start']=$activities[$i]['modstart'];
    $aid_status[$i]['stop']=$activities[$i]['modstop'];
    $activitiesNotStarted="";
    if($DB->get_field('course_modules', 'completionexpected', array('id' => $activities[$i]['modid']))){

        $completiondate=userdate($DB->get_field('course_modules', 'completionexpected', array('id' => $activities[$i]['modid'])));
        $completedactivity="markascomplete";
    }
    else{
        $completedactivity="";$completiondate='';
    }
    $pradioButton='';
    if($activities[$i]['modstatus']&&$activities[$i]['modstatus']!=2){

        if($pflag==1){
            $currentActivityId=$activities[$i]['modid'];
            $currentActivityStatus=$activities[$i]['modstatus'];
            $currentModTypeId=$activities[$i]['modtypeid'];
            $currentActivityName=$activities[$i]['modname'];
            $pradioButton="checked=checked";$pflag=0;
        }
        else{
            $pradioButton='';
        }
    }

    /*logic to check whether the activity is lab or quiz*/
    if($activities[$i]['modtypeid']==$activityTypeIds['vpl']){
        $activityType='LAB';
    }
    else{
        if($activities[$i]['modtypeid']==$activityTypeIds['quiz']){
            $activityType='QUIZ';
        }
        elseif($activities[$i]['modtypeid']==$activityTypeIds['adobeconnect']){
            $activityType='ADOBE';
            $adobeurl=getAdobeUrl($activities[$i]['modid'],$activities[$i]['modname']);
        }
        else if($activities[$i]['modtypeid']==$activityTypeIds['teleconnect']){
            $activityType='WEBINAR';
            $teleurl=getTeleUrl($activities[$i]['modid'],$activities[$i]['modname']);
        }
        else if($activities[$i]['modtypeid']==$activityTypeIds['feedback']){
            $activityType='FEEDBACK';
        }
            else if($activities[$i]['modtypeid']==$activityTypeIds['assign']){
                $activityType='ASSIGNMENT';
        }
        else if($activities[$i]['modtypeid']==$activityTypeIds['url']){
            $activityType='URL';
        }
        //h5p crossword updated by chandrika
        else if($activities[$i]['modtypeid'] == $activityTypeIds['h5pactivity']) {
        $activityType = 'H5P';      
    }
    // manual questions adding and evaluate using api key code by chandrika
     else if ($activities[$i]['modtypeid'] == $activityTypeIds['customactivity']) {

    $activityType = get_string('modulename', 'customactivity');

}
// generating questions by using chat gpt code by chandrika
else if ($activities[$i]['modtypeid'] == $activityTypeIds['customassessment']) {

    $activityType = get_string('modulename', 'customassessment');

}
         else{
            //  $activityType="--";
            continue;
         }
    }
    if(!$completiondate){


        if($activities[$i]['modstatus']==''){
            $activitiesNotStarted="style='display:none'";
        }





      echo '<tr data-status="'.$activities[$i]['modstatus'].'" class="crow' . $activities[$i]['modid'] . ' row' . $activities[$i]['modid'] . ' '.$completedactivity.'" '.$activitiesNotStarted.'>
  <td class="course-title">
  <input data-rmodid="' . $activities[$i]['remoteactivity'] . '" data-status="'.$activities[$i]['modstatus'].'" name="ca-radio-button" data-mid="' . $activities[$i]['modtypeid'] . '" data-id="' . $activities[$i]['modid'] . '" type="radio" class="ca-radio-activity ca-radio-activity' . $activities[$i]['modid'] . '"  '.$pradioButton.'/>
  </td>
  <td class="course-title"><span class="actype"><b>'.$activityType.'</b></span></td>
  <td style="" ><a><span class="mod' . $activities[$i]['modid'] . ' '.$completedactivity.'">' . $activities[$i]['modname']."<a></td>";

        echo '<td style="font-weight:bold">
    <img class="activity-status-img" title="Logged In" src="'.$CFG->wwwroot.'/local/teacher/testcenter/images/flag-red-icon.png">
   <span class="loggedinCount">'.$loggedinusers.'</span> of <span class="studentCount">'.$totalenrolled.'</span>
   </td>';
        echo '<td style="font-weight:bold">
  <img class="activity-status-img" title="Submited"  src="'.$CFG->wwwroot.'/local/teacher/testcenter/images/flag-orange-icon.png">
  <span class="csubCount' . $activities[$i]['modid'] . '">0</span> of <span class="loggedinCount">'.$loggedinusers.'</span>
  </td>';
        echo '<td style="font-weight:bold">
  <img class="activity-status-img" title="Graded"  src="'.$CFG->wwwroot.'/local/teacher/testcenter/images/flag-green-icon.png">
  <span class="cgradeCount' . $activities[$i]['modid'] . '">0</span> of <span class="loggedinCount">'.$loggedinusers.'</span></td>';
        echo '<td style="font-weight:bold">
  <img class="activity-status-img" title="Greenstars"  src="'.$CFG->wwwroot.'/local/teacher/testcenter/images/green-star.png">
  <span class="cstarCount' . $activities[$i]['modid'] . '">0</span> of <span class="loggedinCount">'.$loggedinusers.'</span></td>';
        echo '<td style="font-weight:bold">
  <img class="activity-status-img" title="watchlistedstars"  src="'.$CFG->wwwroot.'/local/teacher/testcenter/images/red-star.png">
  <span class="crstarCount' . $activities[$i]['modid'] . '">0</span> of <span class="watchCount watchCount' . $activities[$i]['modid'] . '" >'.$loggedinusers.'</span></td>';

        if($completiondate) {

            echo '<td style="width:18%"><span class="status-text closed actstatus' . $activities[$i]['modid'] . '"><b>CLOSED</b><BR/><span style="line-height: 12px;"> on '.$completiondate .'</span></span></td>';

        }
        elseif(($aid_status[$i]['status']==0)&&(!empty($activities[$i]['modstatus']))){
            echo '<td style="width:18%"><span class="status-text stopped actstatus' . $activities[$i]['modid'] . '"><b>STOPPED</b><BR/><span style="line-height: 12px;"> on '.userdate($aid_status[$i]['stop']) .'</span></span></td>';
            $stopped='';
        }
        elseif($aid_status[$i]['status']==1){
            echo '<td style="width:18%"><span class="status-text started actstatus' . $activities[$i]['modid'] . '"><b>STARTED</b><BR/><span style="line-height: 12px;"> on '.userdate($aid_status[$i]['start']) .'</span></span></td>';
            $started='';
        }
        else{
            echo '<td style="width:18%"><span   class="status-text actstatus' . $activities[$i]['modid'] . '"><b>NOT STARTED</b></span></td>';
        }
        /*echo '<th ><span class="mod' . $activities[$i]['modid'] . '">' . $activities[$i]['modcontent'] .. '</span></th>*/

        echo '<td>
 <img data-mid="' . $activities[$i]['modtypeid'] . '" data-id="' . $activities[$i]['modid'] . '" class="act-refresh refresh' . $activities[$i]['modid'] . '"  title="REFRESH SUMMARY"  src="'.$CFG->wwwroot.'/pix/a/refresh.png"/>
 <img class="refresh-load-img' . $activities[$i]['modid'] . '"  title="filter" style="width:10px;display: none;" src="'.$CFG->wwwroot.'/pix/loading.gif"></td>';
        echo '</span></td>';
        echo '</tr>';
     }
  } ?>
    </tbody>
  </table>


        </div>

       </div> <!-- end of bar -->

    </div>  <!-- end of control panel -->

 </div>  <!-- end of tab-pane a -->


    <div class="tab-pane" id="b" style="display:inline-block; width:99%">


   <?php
    echo '<div style="float:right;margin-top: 8px; margin-right: 10px;"><i id="goto-back" class="fa fa-bars fa-2x" aria-hidden="true"></i>
            </div>';

                        echo '<div id="sub_list">';
                        require_once('enrolledstudent.php');
                        echo '</div>';
                    ?>

               </div> <!-- end tab b -->

          </div>   <!-- end of tab content    -->

      </div>   <!--  tabbable tabs-left -->





      </div>    <!-- end of report div -->

  </div>   <!-- end of container-demo divs -->

  <!-- /*page content display end*/ -->

    <!-- ///all hidden fields to store dynamic information for the page -->
 <!-- //hc in each id name represents hiddencurrent -->
 <?php
echo '<input type="hidden" id="current-lab" value="0" />';
echo '<input type="hidden" id="hctopic" value="'.$topicname.'" />';
echo '<input type="hidden" id="hccourse" value="'.$course->fullname.'" />';
echo '<input type="hidden" id="hcactivity" value="'.$currentActivityName.'" />';
echo '<input type="hidden" id="hcactivity-id" value="'.$currentActivityId.'" />';
echo '<input type="hidden" id="hcactivity-status" value="'.$currentActivityStatus.'" />';
echo '<input type="hidden" id="setinterval-id" value="0" />';
echo '<input type="hidden" id="hcstu-section" value="All" />';
echo '<input type="hidden" id="studentCount" value="'.$totalenrolled.'" />';
echo '<input type="hidden" id="modtypeid" value="'.$currentModTypeId.'" />';
echo '<input type="hidden" id="greenstarsetinterval-id" value="0" />';
echo '<input type="hidden" id="stu-section" value="All" />';
echo '<input type="hidden" id="remote-activity" value="0" />';

echo $OUTPUT->footer();



?>

            <style>
                .act-sorter-select {
                    height: auto !important;
                    width: 140px !important;
                }
                #t01 {
                    margin-top: 0px !important;
                }
                .page-header-headings{
                    display:none !important;
                }
                #page-header {
                    margin-left: 0px !important;
                    margin-top: 0px;
                    display:block !important;
                }
                .confirmation-modal.modal.fade.in {
                    display: table !important;
                    left: 48%;
                    top: 10% !important;
                    background: transparent;
                    box-shadow: none !important;
                    border: 0px !important;
                }
                .modal-title {

                    text-transform: capitalize;
                }
                #reset-logins {
                background-color:  #012951;
                 color: #fff;
                  border: 1px solid #fff;
                 padding: 5px;
                  border-radius: 0px;

                  }
                /* .container-demo{
            margin-right:0;
         margin-left:0;
             }
#region-main-box{
    margin-left:0;
} */
.container{
    padding-left:0;
    padding-right:0;
    margin-left:0px;
    margin-right:0;
}
#page.drawers {
    padding-left: 0;
    padding-right:0;
    margin-top:20px;
    /* margin-left:10;
    margin-right:10; */
}

#page-content{
    /* width: 1200px; */
    height:600px;
    margin-right:0;
    /* margin-left:-180px; */
    margin-top:0px;
    padding-left:0;
    padding-right:0;
}
#topofscroll {
max-width: 100vw!important;
}
            </style>


<script>
    //generating questions by using chat gpt code by chandrika
$(document).ready(function () {

 let refreshTimer = null;
    $("#evaluateAllBtn").on("click", function () {

        // Show progress bar and reset counters
        $("#evalProgress").show();
       $("#evalCurrent").text(<?php echo (int)$evaluated; ?>);

      var totalStudents = <?php echo (int)$totalstudents; ?>;
      $("#evalTotal").text(<?php echo (int)$totalstudents; ?>);

        var $btn = $(this);
        $btn.prop("disabled", true).text("Evaluating...");

        refreshTimer = setInterval(function () {
    location.reload();
}, 4000);

        $.ajax({
            url: M.cfg.wwwroot + "/local/teacher/testcenter/evaluate_now.php",
            type: 'POST',
            dataType: 'json',
            data: {
                courseid: <?php echo (int)$cid; ?>,
                sesskey: M.cfg.sesskey
            },
            success: function (response) {
                console.log("Server response:", response); // Check response in console
                clearInterval(refreshTimer);

                $("#evalProgress").hide();
                $btn.prop("disabled", false).text("Evaluate All");

                if (response.status === 'success') {
                    // ✅ Show a success message
                    $('<div class="alert alert-success mt-2">All students evaluated successfully!</div>')
                        .insertAfter($btn)
                        .delay(3000)
                        .fadeOut(500, function() { $(this).remove(); });
                } else if (response.status === 'info') {
                    $('<div class="alert alert-info mt-2">' + (response.message || "Nothing to evaluate.") + '</div>')
                        .insertAfter($btn)
                        .delay(3000)
                        .fadeOut(500, function() { $(this).remove(); });
                } else {
                    $('<div class="alert alert-danger mt-2">Problem: ' + (response.message || "Unknown error") + '</div>')
                        .insertAfter($btn)
                        .delay(3000)
                        .fadeOut(500, function() { $(this).remove(); });
                }
            },
            error: function (xhr, status, error) {
            clearInterval(refreshTimer);
                console.error("Evaluation Error:", error);

                $("#evalProgress").hide();
                $btn.prop("disabled", false).text("Evaluate All");

                $('<div class="alert alert-danger mt-2">Error during evaluation. Check console.</div>')
                    .insertAfter($btn)
                    .delay(3000)
                    .fadeOut(500, function() { $(this).remove(); });
            }
        });
    });

});
</script>


<script type="text/javascript">
    // live notification code by chandrika
document.addEventListener('DOMContentLoaded', function () {

    const sendBtn = document.getElementById('send-message');
    const modal = document.getElementById('notifyModal');
    const notifyMessage = document.getElementById('notifyMessage');
    const responseEl = document.getElementById('responseMessage');
    const sendModalBtn = document.getElementById('sendModalBtn');
    const courseEl = document.getElementById('courseid');

    if (!sendBtn || !modal || !notifyMessage || !courseEl || !sendModalBtn) {
        console.error('Required elements not found');
        return;
    }

    const COURSE_ID = courseEl.value.trim();
    const WS_TOKEN = "<?= $wsToken ?>";

    let socket = null;
    let reconnectAttempts = 0;
    const MAX_RETRIES = 5;

    function connectWebSocket() {
        if (socket && socket.readyState === WebSocket.OPEN) return;

        if (reconnectAttempts >= MAX_RETRIES) {
            showResponse('❌ Unable to connect to notification server after multiple attempts.', 'error');
            return;
        }

        const socketUrl = `ws://${window.location.hostname}:8081/?token=${encodeURIComponent(WS_TOKEN)}`;
        socket = new WebSocket(socketUrl);

        socket.onopen = () => {
            console.log('✅ WebSocket connected');
            reconnectAttempts = 0;
            showResponse('🟢 Connected to notification server', 'success');
        };

        socket.onclose = () => {
            reconnectAttempts++;
            console.warn(`Connection closed. Reconnect attempt ${reconnectAttempts}/${MAX_RETRIES}`);
            setTimeout(connectWebSocket, 3000);
        };

        socket.onerror = (err) => {
            console.error('WebSocket error:', err);
        };

        socket.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);

                if (data.type === 'notify_success') {
                    const count = data.sentTo;
                    const msg = count === 0
                        ? '⚠️ Message sent but no students are currently online'
                        : `✅ Message sent to ${count} student${count > 1 ? 's' : ''}`;

                    showResponse(`${msg}<br><strong>"${data.message}"</strong>`, 'success')
                     notifyMessage.value = '';           
             setTimeout(() => closeModal(), 1500);

                    // Properly reset button
                    sendModalBtn.disabled = false;
                    sendModalBtn.textContent = 'Send to All Students';
                }

                if (data.type === 'error') {
                    showResponse('❌ ' + data.message, 'error');
                    sendModalBtn.disabled = false;
                    sendModalBtn.textContent = 'Send to All Students';
                }

            } catch (e) {
                console.error('Invalid message from server:', event.data);
            }
        };
    }

    connectWebSocket();

    // Modal controls
    sendBtn.addEventListener('click', () => {
        modal.style.display = 'flex';
        notifyMessage.focus();
    });

    window.closeModal = function () {
        modal.style.display = 'none';
        notifyMessage.value = '';
        responseEl.style.display = 'none';
        responseEl.innerHTML = '';
        responseEl.className = '';
        if (sendModalBtn) {
            sendModalBtn.disabled = false;
            sendModalBtn.textContent = 'Send to All Students';
        }
    };

    window.setQuickMessage = function (msg) {
        notifyMessage.value = msg;
        notifyMessage.focus();
    };

    window.sendNotification = function () {
        const message = notifyMessage.value.trim();

        if (!message) {
            showResponse('Please enter a message', 'error');
            return;
        }

        if (!socket || socket.readyState !== WebSocket.OPEN) {
            showResponse('❌ Connection lost. Please refresh the page.', 'error');
            return;
        }

        sendModalBtn.disabled = true;
        sendModalBtn.textContent = 'Sending...';

        socket.send(JSON.stringify({
            type: 'notify',
            message: message
        }));
    };

    function showResponse(text, type) {
        responseEl.innerHTML = text;
        responseEl.className = type;
        responseEl.style.display = 'block';
        responseEl.style.backgroundColor = type === 'success' ? '#d4edda' : '#f8d7da';
        responseEl.style.color = type === 'success' ? '#155724' : '#721c24';
        responseEl.style.border = `1px solid ${type === 'success' ? '#c3e6cb' : '#f5c6cb'}`;
    }
});
</script>
















