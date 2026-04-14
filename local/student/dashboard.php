<?php

$start_time = microtime(true);

require_once('../../config.php');
$PAGE->set_url('/local/student/dashboard.php');
require_login();
// Load the page_requirements_manager
$PAGE->requires->css('/local/student/styles/custom.css');

require_once('custom_tele.php');
require_once($CFG->dirroot . '/my/lib.php');
//course lib for getting activites
require_once($CFG->dirroot . '/course/lib.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title('Dashboard');
echo $OUTPUT->header();

$PAGE->requires->js('/local/student/customchanges.js');
$PAGE->requires->js('/local/student/jquery.js', true);
global $USER;
if (!user_has_role_assignment($USER->id, 5)) {
    redirect($CFG->wwwroot);
}

echo '<div style="text-align: right; margin-right: 4%; position:relative; top:-6px; text-transform: capitalize;">';
echo '<a href="javascript:document.location.reload()" class="btn btn-primary" style="border:none"><i class="icon-refresh"></i> Refresh</a>';
echo '</div>';
echo "<div id='paged-content-container-1' data-region='paged-content-container'>";
echo "<div id='page-container-1' data-region='page-container' class='paged-content-page-container' aria-live='polite'>";
echo "<div data-region='paged-content-page' data-page='1' class=''>";
echo "<ul class='list-group'>";

global $DB;
$now = strtotime('now');

$sortedActivities = array(); // Create an array to store activities with start times

// Get student enrolled courses
if (user_has_role_assignment($USER->id, 5)) {
    $enrolledcourses = enrol_get_users_courses($USER->id);
    $no_courses = count($enrolledcourses);

    foreach ($enrolledcourses as $key => $value) {
        if (is_object($value)) {
            $studentenrolledcourses[$value->id] = $value->fullname;
            $cids .= $value->id . ","; //added
        }
    }

    foreach ($studentenrolledcourses as $courseid => $coursename) {
        $activities = get_array_of_activities($courseid);

        // Filtering activities (only Labs And Quizes) with status = 1 and started
        foreach ($activities as $key1 => $value1) {
            if (is_object($value1)) {
                if (
                    $value1->visible == true &&
                    (($value1->mod == 'vpl') || ($value1->mod == 'quiz') || ($value1->mod == 'teleconnect') || ($value1->mod == 'url')  || ($value1->mod == 'h5pactivity') ) &&
                    is_activity_started_and_has_status($value1->cm, $now, 1)
                ) {
                    $sortedActivities[] = array(
                        'name' => $value1->name,
                        'start_time' => $DB->get_field('activity_status_tsl', 'activity_start_time', array('activityid' => $value1->cm)),
                        'mod' => $value1->mod,
                        'coursename' => $coursename,
                        'cm' => $value1->cm
                    );
                }
            }
        }
    }

    // Sort activities based on start time in descending order
    usort($sortedActivities, function ($a, $b) {
        return $b['start_time'] - $a['start_time'];
    });

    // Loop through sorted activities to display
    foreach ($sortedActivities as $activity) {
        $im = $activity['mod'];
        $course = $activity['coursename'];
        $activityname = $activity['name'];
        $activitycm = $activity['cm'];

        echo "<li class='list-group-item block course-listitem border-left-0 border-right-0 border-top-0 px-2 rounded-0' data-region='course-content' data-course-id='$courseid' style='background-color: #f0f0f0; margin: 10px 0;'>";
        echo '<div class="col-md-3"><div class="imagecours"><div class="tleft">';
        echo '<div class="coursename"><div>' . ucfirst($course) . '</div></div><div class="topicname"><span>';
        if ($im == 'vpl') {
            echo "LAB";
        } else if ($im == 'quiz') {
            echo "QUIZ";
        } else if ($im == 'adobeconnect') {
            echo "VIRTUAL CLASS";
        } else if ($im == 'teleconnect') {
            echo "VIRTUAL SEMINAR";
        }
        else if ($im == 'assign') {
            echo "ASSIGNMENT";
        }
        else if ($im == 'url') {
            echo "URL";
        }
        // h5p crossword updated by chandrika
        else if ($im == 'h5pactivity') {
    echo "H5P";
}
        global $DB;
        $startdate = userdate($DB->get_field('activity_status_tsl', 'activity_start_time', array('activityid' => $activitycm)));

        echo '</span></div></div></div></div><div class="col-md-9 s2 inner"><header><h2 class="course-date" style="margin-bottom:5px !important;">' . ucfirst($activityname) . '</h2><span class="pull-right course-joined" style="text-align:right;"><i class="fa fa-check-circle-o" style="margin-right: 5px;color:#ea6645"></i>Started<br><div class="pull-right" style="font-size:0.7em;"><i class="fa fa-clock-o" style="color:rgb(197, 197, 197);"></i> <span style="color:rgb(118, 118, 118);">on ' . $startdate . '<span class="count-descrition">  </span></span><span><span class="count-descriptio"> </span></span></div>
        </span></header><br><hr>';

        $descact = ''; // Initialize description

        if ($im == 'vpl') {
            $vplid = $DB->get_field('course_modules', 'instance', array('id' => $activitycm));
            // $descact = $DB->get_field_sql("SELECT  `intro` FROM `mdl_vpl` WHERE `id`='" . $vplid . "'");
            // if ($descact == "") {
                $descact = $DB->get_field_sql("SELECT  `shortdescription` FROM `mdl_vpl` WHERE `id` = '" . $vplid . "'");
            // }
        } else if ($im == 'quiz') {
            $quizid = $DB->get_field('course_modules', 'instance', array('id' => $activitycm));
            $descact = $DB->get_field_sql("SELECT `intro` FROM `mdl_quiz` WHERE `id` = '" . $quizid . "'");
        } else if ($im == 'teleconnect') {
            $tcid = $DB->get_field('course_modules', 'instance', array('id' => $activitycm));
            $descact = $DB->get_field_sql("SELECT `intro` FROM `mdl_teleconnect` WHERE `id` = '$tcid'");
        }
//h5p crossword updated by chandrika
        else if ($im == 'h5pactivity') {
    $id = $DB->get_field('course_modules', 'instance', ['id' => $activitycm]);
    $descact = $DB->get_field('h5pactivity', 'intro', ['id' => $id]);
}

        else if ($im == 'url') {
            $tcid = $DB->get_field('course_modules', 'instance', array('id' => $activitycm));
            $descact = $DB->get_field_sql("SELECT `intro` FROM `mdl_url` WHERE `id` = '$tcid'");
            // var_dump($desc);
        }
        echo '<p class="description"><span>' . ucfirst(strip_tags($descact)) . '</span></p>';

        $courseid = $DB->get_field_sql("SELECT `id` FROM `mdl_course` WHERE `fullname` = '$course'");
        $getvplinstance = $DB->get_field_sql("SELECT `instance` FROM `mdl_course_modules` WHERE `id` ='$activitycm' AND `course` ='$courseid'");

        $reflink = "";
        $adobeloginformaction = $CFG->wwwroot . '/local/student/adobelogin.php';

        if (strcasecmp($im, "vpl") == 0) {
            $reflink = $CFG->wwwroot . "/mod/" . $im . "/forms/edit.php?id=" . $activitycm . "&userid=" . $USER->id;
        } else if (strcasecmp($im, "teleconnect") == 0) {
            $teleconnecturl = getTeleUrl($activitycm, $activityname);
            $teleconnect_flag = 1;
            $teleactid = $activitycm;
            $reflink = $teleconnecturl;
        }
        //h5p crossword updated by chandrika
               else if ($im == 'h5pactivity') {
    $reflink = $CFG->wwwroot . "/mod/h5pactivity/view.php?id=" . $activitycm;
}
        
        elseif(strcasecmp($im, "url") == 0) {
            $tci = $DB->get_field('course_modules', 'instance', array('id' => $activitycm));
            $desc = $DB->get_field_sql("SELECT `externalurl` FROM `mdl_url` WHERE `id` = '$tci'");
            $reflink = $desc;
        }

        else {
            $reflink = $CFG->wwwroot . "/mod/" . $im . "/view.php?id=" . $activitycm;
        }

        if (strcasecmp($im, "teleconnect") == 0) {
            if ($reflink == null) {
                $reflink = 'javascript:void(0)';
                echo '<a class="teleconnect btn btn-primary btn-lg pull-right" id="logged" href=' . $reflink . ' value="submit"><span>JOINED</span></a>';
            } else {
                echo '<form data-userid=' . $USER->id . ' data-cid=' . $courseid . ' data-aid=' . $teleactid . '  id="teleform" class="teleform" method="post" action=' . $adobeloginformaction . ' target="_blank">';
                echo '<input type="hidden" name="mlink" value=' . $reflink . '  />';
                echo '<input type="hidden" name="connect-name" value="teleconnect" />';
                echo '<a class="teleconnect btn btn-primary btn-lg pull-right" id="logged" data-href=' . $reflink . ' value="submit">
                <span>JOIN</span></a>';
                echo '</form>';
            }
        } 
        elseif(strcasecmp($im, "url") == 0) {
            echo '<a class="btn btn-primary btn-lg pull-right" target="_blank" href=' . $reflink . ' value="submit" ><span>Download</span></a>';
        }
        else {

            echo '<a class="btn btn-primary btn-lg pull-right" target="_blank" href=' . $reflink . ' value="submit" ><span>Attempt</span></a>';

        }
        echo '</div>';
        echo '</li>';
    }
}

// End of activity listing

echo "</ul>";
echo "</div>";
echo "</div>";
echo "</div>";
echo "</div>";
echo $OUTPUT->footer();


function is_activity_started_and_has_status($activity_id, $current_time, $status_value)
{
    global $DB;
    $activity_start_time = $DB->get_field('activity_status_tsl', 'activity_start_time', array('activityid' => $activity_id));
    $status = $DB->get_field('activity_status_tsl', 'status', array('activityid' => $activity_id));

    // Check if the activity has status = $status_value and the start time is before or equal to the current time
    return ($status == $status_value && $activity_start_time && $activity_start_time <= $current_time);
}


?>

<script>
//    document.addEventListener('DOMContentLoaded', function () {
//     // Find the "Preferences" node
//     var preferencesNode = document.querySelector('a.dropdown-item[href="http://172.20.36.171/moodle413/user/preferences.php"]');

//     if (preferencesNode.nextElementSibling) {
//             preferencesNode.nextElementSibling.remove();
//         }

//         // Remove the "Preferences" node
//         preferencesNode.parentNode.removeChild(preferencesNode);
// });

</script>
<!-- <script src="/var/www/html/tessellator51/local/student/jquery.js"></script> -->
<script src="<?php echo $CFG->wwwroot;?>/local/student/jquery.js"></script>

<script>
    // $=jQuery.noConflict();
            <?php

 if ((user_has_role_assignment($USER->id, 5)) && ( strtotime("now") < strtotime("18-Dec-2016"))&& (!isset($_SESSION['userprofileupdate']))){
 $_SESSION['userprofileupdate']='set';
 echo '
             $( document ).ready(function() {

                     $(".iframe").fancybox({
                         type: \'iframe\',
                         preload   : true,
                         helpers     : {
                             overlay : {
                                 closeClick: false
                             }
                         },
                     }).trigger(\'click\');

             });';
 }
 ?>
            $("#loading").hide();
            var baseUrl=$('#baseurl').val();
            var baseUrl='<?php echo $CFG->wwwroot; ?>';

            console.log("url",baseUrl);
            var portal='<?php echo $CFG->portal?>';

            $("#loading").hide();
            //  var baseUrl=$('#baseurl').val();
            var url= baseUrl+'/local/student/dashboard.php';
            console.log("url2",url);
            var purl=portal+'/user/enrolled_user/dashboard.php';
            $("#page-header").show();
            $(".page-context-header").hide();
            // $('#page-navbar').append("<div id='navbar' style='padding: 2% 5% 2% 6%;'> <a id='dlink'>Dashboard</a> <span>/</span>  <b>Current Tasks</b></div>");
            $('#dlink').attr("href",url);


        $(document).delegate(".teleconnect","click",function(){
        //alert("clicked");

        if($(this).data('href')!='javascript:void(0)'){
        $(this).text('JOINED');
        $(this).data('href','javascript:void(0)');
        //alert($("#adobeform").data("cid"));
        var cid=$(this).closest("form").data("cid");
        // console.log(cid);
        var aid=$(this).closest("form").data("aid");
        // console.log(aid);
        var userid=$(this).closest("form").data("userid");
        // console.log(userid);
        updateWebinarAttendance(cid,aid,userid);
        $(this).closest("form").submit();
        }

        if($(this.id)=='logged'){
        alert("user is already logged in teleconnect");
        }

        });
        //});

        function updateWebinarAttendance(cid,aid,userid){
        $.ajax({
        url: baseUrl+"/local/teacher/sms-notification.php",
        data: {
        "smsmid": 9,
        "cid":cid,
        "aid":aid,
        "userid":userid
        },
        type: "GET",
        dataType: "html",
        success: function (data) {
        //$("#teleform-"+linkid).submit();
        },
        error: function (xhr, status) {
            // console.log("Error response:", xhr.responseText);
        alert("Sorry, there was a problem!");
        },
        complete: function (xhr, status) {

        }
        });


        }

/* Pop Up Script START*/
        function openPopup() {
  window.location.hash = 'openModal';
}

window.onload = openPopup;
/* Pop Up Script End*/


// websocket
var websoc = '<?php echo $CFG->websocket; ?>';
    const socket = new WebSocket(websoc);

    socket.addEventListener('message', function (event) {
        const message = JSON.parse(event.data);

        // Check if the user has the role with PHP (server-side logic)
        <?php if (user_has_role_assignment($USER->id, 5)) { ?>
            var enrolledCourseIds = <?php echo json_encode($cids); ?>;
            if (enrolledCourseIds.includes(message.userId)) {
                // Display the content in the popup
                // alert(message.content);
                location.reload();
            }
        <?php } ?>
    });

        </script>




<?php

$end_time = microtime(true);
$execution_time = ($end_time - $start_time);
// echo "Page loaded in $execution_time seconds";

?>



