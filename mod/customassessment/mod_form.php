<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_customassessment_mod_form extends moodleform_mod {

    public function definition() {
        global $CFG, $DB, $PAGE;

        $mform = $this->_form;

        /* ================= CONTEXT ================= */
        if ($this->current->instance) {
            $context = context_module::instance($this->current->coursemodule);
        } else {
            $context = context_course::instance($this->_course->id);
        }

        $PAGE->set_context($context);
        $PAGE->set_url('/course/modedit.php', [
            'add' => 'customassessment',
            'course' => $this->_course->id
        ]);

        /* ================= NAME ================= */
        $mform->addElement('text', 'name',
            get_string('assessmentname', 'customassessment'),
            ['size' => '64']
        );
        $mform->setType('name', PARAM_RAW);
        $mform->addRule('name', null, 'required', null, 'client');

        /* ================= SUBJECT ================= */
        $subjects = [0 => get_string('choosesubject', 'customassessment')];
        if ($DB->record_exists('customassessment_subjects', [])) {
            $subjects += $DB->get_records_menu(
                'customassessment_subjects',
                null,
                'subjectname ASC',
                'id, subjectname'
            );
        }

        $subjectgroup = [];
        $subjectgroup[] = $mform->createElement('select', 'subjectid', '', $subjects);
        $subjectgroup[] = $mform->createElement(
            'html',
            '<button type="button" class="btn btn-secondary ml-2" id="addsubjectbtn">
                '.get_string('addnewsubject', 'customassessment').'
             </button>'
        );

        $mform->addGroup(
            $subjectgroup,
            'subjectgroup',
            get_string('subject', 'customassessment'),
            ' ',
            false
        );

        /* ================= TOPIC ================= */
        $topics = [0 => get_string('choosetopic', 'customassessment')];
        if ($DB->record_exists('customassessment_topics', [])) {
            $topics += $DB->get_records_menu(
                'customassessment_topics',
                null,
                'topicname ASC',
                'id, topicname'
            );
        }

        $topicgroup = [];
        $topicgroup[] = $mform->createElement('select', 'topicid', '', $topics);
        $topicgroup[] = $mform->createElement(
            'html',
            '<button type="button" class="btn btn-secondary ml-2" id="addtopicbtn">
                '.get_string('addnewtopic', 'customassessment').'
             </button>'
        );

        $mform->addGroup(
            $topicgroup,
            'topicgroup',
            get_string('topic', 'customassessment'),
            ' ',
            false
        );

        $mform->disabledIf('topicid', 'subjectid', 'eq', 0);




        /* ================= QUESTIONS ================= */
        $mform->addElement('text', 'numquestions',
            get_string('numquestions', 'customassessment'),
            ['size' => 3]
        );
        $mform->setType('numquestions', PARAM_INT);
        $mform->setDefault('numquestions', 10);
        $mform->addRule('numquestions', null, 'required', null, 'client');

/* ================= BLOOM'S LEVEL ================= */
$bloomslevels = [
    'Remembering'   => get_string('remembering', 'customassessment'),
    'Understanding' => get_string('understanding', 'customassessment'),
    'Applying'      => get_string('applying', 'customassessment'),
    'Analyzing'     => get_string('analyzing', 'customassessment'),
    'Evaluating'    => get_string('evaluating', 'customassessment'),
    'Creating'      => get_string('creating', 'customassessment'),
];

$mform->addElement(
    'select',
    'bloomslevels',                        
    get_string('bloomslevel', 'customassessment'),
    $bloomslevels,
    [
        'multiple' => 'multiple',
        'size'     => 6,                       
    ]
);
$mform->addElement('static', 'blooms-msg', '', '<span id="blooms-msg" style="color: green;"></span>');

// Optional but recommended:
$mform->setType('bloomslevels', PARAM_RAW);




        
        /* ================= SUBJECT MODAL ================= */
        $mform->addElement('html', '
<div class="modal fade" id="addSubjectModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">'.get_string('addnewsubject', 'customassessment').'</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>

      <div class="modal-body">

  <!-- SUCCESS MESSAGE -->
  <div id="subject-success-msg" class="alert alert-success d-none"></div>

  <!-- ERROR MESSAGE -->
  <div id="subject-error-msg" class="alert alert-danger d-none"></div>

  <input type="text" id="newsubjectname" class="form-control"
         placeholder="'.get_string('subjectname', 'customassessment').'">
  <div class="invalid-feedback">'.get_string('required').'</div>
</div>


      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">
            '.get_string('cancel').'
        </button>
        <button type="button" class="btn btn-primary" id="savesubjectbtn">
            '.get_string('add').'
        </button>
      </div>

    </div>
  </div>
</div>
');
             //topic modal

$mform->addElement('html', '
<div class="modal fade" id="addTopicModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">'.get_string('addnewtopic', 'customassessment').'</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>

      <div class="modal-body">

  <div id="topic-success-msg" class="alert alert-success d-none"></div>
  <div id="topic-error-msg" class="alert alert-danger d-none"></div>

  <input type="text" id="newtopicname" class="form-control"
         placeholder="'.get_string('topicname', 'customassessment').'">
  <div class="invalid-feedback">'.get_string('required').'</div>
</div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">
            '.get_string('cancel').'
        </button>
        <button type="button" class="btn btn-primary" id="savetopicbtn">
            '.get_string('save').'
        </button>
      </div>

    </div>
  </div>
</div>
');


        /* ================= JAVASCRIPT ================= */
 $PAGE->requires->js_init_code("
document.addEventListener('DOMContentLoaded', function() {

    /* ================= ADD SUBJECT ================= */

    document.getElementById('addsubjectbtn').onclick = function () {

        var input = document.getElementById('newsubjectname');
        var successMsg = document.getElementById('subject-success-msg');
        var errorMsg   = document.getElementById('subject-error-msg');

        input.value = '';
        input.classList.remove('is-invalid');
        successMsg.classList.add('d-none');
        errorMsg.classList.add('d-none');

        $('#addSubjectModal').modal('show');
    };

    document.getElementById('savesubjectbtn').onclick = function () {

        var input = document.getElementById('newsubjectname');
        var name  = input.value.trim();
        var successMsg = document.getElementById('subject-success-msg');
        var errorMsg   = document.getElementById('subject-error-msg');

        successMsg.classList.add('d-none');
        errorMsg.classList.add('d-none');

        if (!name) {
            input.classList.add('is-invalid');
            return;
        }

        fetch(M.cfg.wwwroot + '/mod/customassessment/ajax_add_subject.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'sesskey=' + M.cfg.sesskey + '&subjectname=' + encodeURIComponent(name)
        })
        .then(r => r.json())
        .then(data => {

            if (!data.success) {
                errorMsg.textContent = data.error;
                errorMsg.classList.remove('d-none');
                return;
            }

            var sel = document.getElementById('id_subjectid');
            sel.add(new Option(data.name, data.id, true, true));

            successMsg.textContent = 'Subject added successfully';
            successMsg.classList.remove('d-none');

            setTimeout(() => $('#addSubjectModal').modal('hide'), 1200);
        });
    };

    $('#addSubjectModal').on('hidden.bs.modal', function () {
        document.getElementById('newsubjectname').value = '';
    });

    /* ================= ADD TOPIC ================= */

    document.getElementById('addtopicbtn').onclick = function () {

        var subjectid = document.getElementById('id_subjectid').value;
        if (!subjectid || subjectid <= 0) {
            alert('Please select a subject first');
            return;
        }

        var input = document.getElementById('newtopicname');
        input.value = '';
        input.classList.remove('is-invalid');

        document.getElementById('topic-success-msg').classList.add('d-none');
        document.getElementById('topic-error-msg').classList.add('d-none');

        $('#addTopicModal').modal('show'); // ✅ now works
    };

    document.getElementById('savetopicbtn').onclick = function () {

        var input = document.getElementById('newtopicname');
        var topicname = input.value.trim();
        var subjectid = document.getElementById('id_subjectid').value;

        var successMsg = document.getElementById('topic-success-msg');
        var errorMsg   = document.getElementById('topic-error-msg');

        successMsg.classList.add('d-none');
        errorMsg.classList.add('d-none');

        if (!topicname) {
            input.classList.add('is-invalid');
            return;
        }

        fetch(M.cfg.wwwroot + '/mod/customassessment/ajax_add_topic.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body:
                'sesskey=' + M.cfg.sesskey +
                '&topicname=' + encodeURIComponent(topicname) +
                '&subjectid=' + subjectid
        })
        .then(r => r.json())
        .then(data => {

            if (!data.success) {
                errorMsg.textContent = data.error || 'Unable to add topic';
                errorMsg.classList.remove('d-none');
                return;
            }

            var sel = document.getElementById('id_topicid');
            sel.add(new Option(data.name, data.id, true, true));

            successMsg.textContent = 'Topic added successfully';
            successMsg.classList.remove('d-none');

            setTimeout(function () {
                $('#addTopicModal').modal('hide');
            }, 1200);
        });
    };

    $('#addTopicModal').on('hidden.bs.modal', function () {
        document.getElementById('newtopicname').value = '';
    });

});

 /* ================= BLOOM'S LEVEL MULTISELECT ================= */
    var bloomSelect = document.getElementById('id_bloomslevels');
    var bloomMsg    = document.getElementById('blooms-msg');
    var numQuestionsInput = document.getElementById('id_numquestions');

    Array.from(bloomSelect.options).forEach(function(option) {
    option.addEventListener('mousedown', function(e) {
        e.preventDefault();          // prevent default multi-select behavior
        option.selected = !option.selected;
        bloomSelect.dispatchEvent(new Event('change'));
    });
});
    function updateBloomMsg() {
        var selected = Array.from(bloomSelect.selectedOptions).map(o => o.value);
        var numLevels = selected.length;
        var numQuestions = parseInt(numQuestionsInput.value) || 0;
        if (numLevels > 0 && numQuestions > 0) {
            var perLevel = Math.ceil(numQuestions / numLevels);
            bloomMsg.textContent = 'You selected ' + numLevels + ' level' + (numLevels > 1 ? 's' : '') +
                                   ' → ' + numLevels + ' × ' + perLevel + ' = ' + (perLevel * numLevels) + ' questions';
        } else {
            bloomMsg.textContent = '';
        }
    }

    bloomSelect.addEventListener('change', updateBloomMsg);
    numQuestionsInput.addEventListener('input', updateBloomMsg);

    // initialize on page load
    updateBloomMsg();

    
");




        /* ================= STANDARD ================= */
        $this->standard_coursemodule_elements();

if ($this->current->instance) {
            // Prepare data object matching form field names
            $toform = (object) [
                'name'         => $this->current->name,
                'numquestions' => $this->current->numquestions ?? 10,
            ];

            //$toform->subjectid = [0 => ($this->current->subjectid ?? 0)];
           // $toform->topicid   = [0 => ($this->current->topicid ?? 0)];

            $toform->subjectid = $this->current->subjectid ?? 0;
            $toform->topicid   = $this->current->topicid ?? 0;

             if (!empty($this->current->bloomslevels)) {
        $toform->bloomslevels = explode(',', $this->current->bloomslevels);
    } else {
        $toform->bloomslevels = [];
    }

            $toform->visible = $this->current->visible ?? 1;
            $toform->groupmode = $this->current->groupmode ?? 0;
            
            $this->set_data($toform);
        }

        $this->add_action_buttons();
    }
}

