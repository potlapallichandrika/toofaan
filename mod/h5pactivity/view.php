<?php
require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use mod_h5pactivity\local\manager;
use core_h5p\factory;
use core_h5p\player;

$id = required_param('id', PARAM_INT);
list($course, $cm) = get_course_and_cm_from_cmid($id, 'h5pactivity');

require_login($course, true, $cm);
$manager        = manager::create_from_coursemodule($cm);
$moduleinstance = $manager->get_instance();
$context        = $manager->get_context();

$manager->set_module_viewed($course);

$fs    = get_file_storage();
$files = $fs->get_area_files($context->id, 'mod_h5pactivity', 'package', 0, 'id', false);
$file  = reset($files);
$fileurl = moodle_url::make_pluginfile_url(
    $file->get_contextid(), $file->get_component(), $file->get_filearea(),
    $file->get_itemid(), $file->get_filepath(), $file->get_filename(), false
);

$PAGE->set_url('/mod/h5pactivity/view.php', ['id' => $cm->id]);
$PAGE->set_title($moduleinstance->name);
$PAGE->set_heading($course->fullname);
$PAGE->set_context($context);

require_login();
global $USER;

if (is_siteadmin($USER->id)) {
    // ✅ Admin: keep breadcrumb normal
    echo "
    <style>
        /* Show breadcrumb inline with slashes */
        .breadcrumb {
            display: flex !important;
            flex-wrap: nowrap;
            list-style: none;
            padding: 0;
            margin: 0 0 10px 0;
            font-size: 15px;
        }

        .breadcrumb li + li::before {
            content: ' / ';
            padding: 0 6px;
            color: #555;
        }

        .breadcrumb li {
            display: inline;
            white-space: nowrap;
        }
    </style>";
} else {
    echo "
    <style>
        /* 1. Hide breadcrumb (Dashboard > teledemo > test-crossword) */
        .breadcrumb,
        nav[aria-label='breadcrumb'] { display:none !important; }

        /* Hide H5P tab bar (H5P / Attempts report) */
        .secondary-navigation .nav.more-nav,
        .secondary-navigation .moremenu {
            display: none !important;
        }

        /* Hide Activity Information (Done: View / Receive a grade) */
        [data-region='activity-information'] {
            display: none !important;
        }

        /* Hide attempts/report links */
        a[href*='attempts'],
        a[href*='report.php'] {
            display: none !important;
        }

        /* Adjust layout */
        #region-main {
            margin-top: 0 !important;
            padding-top: 0 !important;
        }
    </style>";
}




echo $OUTPUT->header();

$factory = new factory();
global $DB, $USER;

$activityid = $moduleinstance->id;
$userid     = $USER->id;

/* === ATTEMPT LIMIT LOGIC === */
$attemptlimit = $moduleinstance->enabletracking ? ($moduleinstance->attempts ?? 1) : 999;
$hasattempt   = $DB->record_exists('h5pactivity_attempts', ['h5pactivityid'=>$activityid, 'userid'=>$userid]);
$block        = $hasattempt && $attemptlimit == 1;   // final state
$showPolling  = !$block;                            // only when still playing

/* -------------------------------------------------
   1. FINAL STATE – ONE ATTEMPT USED
   ------------------------------------------------- */
if ($block) {
    $sql = "SELECT ar.rawscore, ar.maxscore
              FROM {h5pactivity_attempts} a
              JOIN {h5pactivity_attempts_results} ar ON ar.attemptid = a.id
             WHERE a.h5pactivityid = ? AND a.userid = ?
             ORDER BY a.timemodified DESC LIMIT 1";
    $rec = $DB->get_record_sql($sql, [$activityid, $userid]);
    $percentage = ($rec && $rec->maxscore) ? round(($rec->rawscore / $rec->maxscore) * 100) : 0;

    echo '<div style="text-align:center;margin:40px;padding:30px;background:#d4edda;border:1px solid #c3e6cb;border-radius:12px;">
            <h3 style="color:#155724;margin:0 0 10px 0;">Activity Submitted!</h3>
            <p style="font-size:2.8em;font-weight:bold;color:#155724;margin:15px 0;">
                '.$percentage.' / 100
            </p>
            <p style="color:#155724;"><strong>No further attempts allowed.</strong></p>
          </div>';
}

/* -------------------------------------------------
   2. ACTIVE STATE – SHOW H5P + (optional) POLLING
   ------------------------------------------------- */
else {
    if (!$manager->is_tracking_enabled()) {
        echo $OUTPUT->notification(get_string('previewmode', 'mod_h5pactivity'), 'warning');
    }

    $config = core_h5p\helper::decode_display_options($factory->get_core(), $moduleinstance->displayoptions);
    echo player::display($fileurl, $config, true, 'mod_h5pactivity', true);
}

/* -------------------------------------------------
   3. OPTIONAL LIVE SCORE BOX (only when polling)
   ------------------------------------------------- */
if ($showPolling) {
    echo '<div id="score-box" style="text-align:center;margin:40px;padding:30px;background:#d4edda;border:1px solid #c3e6cb;border-radius:12px;display:none;">
            <p style="color:#155724;font-size:1.2em;margin:0;">Loading your score...</p>
          </div>';
}

/* -------------------------------------------------
   4. POLLING SCRIPT – ONLY WHEN STILL PLAYING
   ------------------------------------------------- */
if ($showPolling) {
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const scoreBox   = document.getElementById('score-box');
        let intervalId   = null;
        let lastScore    = 0;

        const fetchScore = () => {
            fetch('getscore.php?id=<?php echo $cm->id; ?>', {credentials: 'same-origin'})
                .then(r => {
                    if (!r.ok) throw new Error('HTTP '+r.status);
                    return r.json();
                })
                .then(data => {
                    // data = {score: 38, max: 100}
                    if (data.score > 0 && data.score !== lastScore) {
                        lastScore = data.score;
                        scoreBox.style.display = 'block';
                        scoreBox.innerHTML = `
                            <h3 style="color:#155724;margin:0 0 10px 0;">Activity Submitted!</h3>
                            <p style="font-size:2.8em;font-weight:bold;color:#155724;margin:15px 0;">
                                ${data.score} / ${data.max}
                            </p>
                            <p style="color:#155724;"><strong>No further attempts allowed.</strong></p>`;
                        // Stop polling once we have a final score
                        clearInterval(intervalId);
                    }
                })
                .catch(err => console.error('[LIVE SCORE] ERROR →', err));
        };

        // Start polling
        console.log('[LIVE SCORE] Polling started');
        intervalId = setInterval(fetchScore, 2500);
        fetchScore(); // immediate first call
    });
    </script>
    <?php
}

echo $OUTPUT->footer();
?>