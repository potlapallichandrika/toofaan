<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_customassessment_mod_form extends moodleform_mod {

    public function definition() {
        global $CFG, $DB, $PAGE;

        $mform = $this->_form;

        /* ================= CONTEXT ================= */
        if (!empty($this->current->instance)) {
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
        $mform->addElement(
            'text',
            'name',
            get_string('assessmentname', 'customassessment'),
            ['size' => '64']
        );
        $mform->setType('name', PARAM_TEXT);
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
            '<button type="button" class="btn btn-secondary ml-2" id="addsubjectbtn">'
                . get_string('addnewsubject', 'customassessment') .
            '</button>'
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
            '<button type="button" class="btn btn-secondary ml-2" id="addtopicbtn">'
                . get_string('addnewtopic', 'customassessment') .
            '</button>'
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
        $mform->addElement(
            'text',
            'numquestions',
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
        $mform->setType('bloomslevels', PARAM_RAW);
        $mform->addElement('static', 'blooms-msg', '', '<span id="blooms-msg" style="color: green;"></span>');

        /* ================= SUBJECT MODAL ================= */
        $mform->addElement('html', '
<div class="modal fade" id="addSubjectModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">' . get_string('addnewsubject', 'customassessment') . '</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="' . s(get_string('closebuttontitle', 'moodle')) . '">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <div id="subject-success-msg" class="alert alert-success d-none"></div>
                <div id="subject-error-msg" class="alert alert-danger d-none"></div>

                <input type="text" id="newsubjectname" class="form-control"
                    placeholder="' . get_string('subjectname', 'customassessment') . '">
                <div class="invalid-feedback">' . get_string('required') . '</div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    ' . get_string('cancel') . '
                </button>
                <button type="button" class="btn btn-primary" id="savesubjectbtn">
                    ' . get_string('add') . '
                </button>
            </div>

        </div>
    </div>
</div>');

        /* ================= TOPIC MODAL ================= */
        $mform->addElement('html', '
<div class="modal fade" id="addTopicModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">' . get_string('addnewtopic', 'customassessment') . '</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="' . s(get_string('closebuttontitle', 'moodle')) . '">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <div id="topic-success-msg" class="alert alert-success d-none"></div>
                <div id="topic-error-msg" class="alert alert-danger d-none"></div>

                <input type="text" id="newtopicname" class="form-control"
                    placeholder="' . get_string('topicname', 'customassessment') . '">
                <div class="invalid-feedback">' . get_string('required') . '</div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">
                    ' . get_string('cancel') . '
                </button>
                <button type="button" class="btn btn-primary" id="savetopicbtn">
                    ' . get_string('save') . '
                </button>
            </div>

        </div>
    </div>
</div>');

        /* ================= JAVASCRIPT ================= */
        $PAGE->requires->js_init_code("
(function() {
    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    ready(function() {
        var addsubjectbtn    = document.getElementById('addsubjectbtn');
        var savesubjectbtn   = document.getElementById('savesubjectbtn');
        var addtopicbtn      = document.getElementById('addtopicbtn');
        var savetopicbtn     = document.getElementById('savetopicbtn');
        var subjectselect    = document.getElementById('id_subjectid');
        var topicselect      = document.getElementById('id_topicid');
        var bloomSelect      = document.getElementById('id_bloomslevels');
        var bloomMsg         = document.getElementById('blooms-msg');
        var numQuestionsInput = document.getElementById('id_numquestions');

        /* ================= SUBJECT MODAL ================= */
        if (addsubjectbtn) {
            addsubjectbtn.onclick = function() {
                var input = document.getElementById('newsubjectname');
                var successMsg = document.getElementById('subject-success-msg');
                var errorMsg = document.getElementById('subject-error-msg');

                if (!input || !successMsg || !errorMsg) {
                    return;
                }

                input.value = '';
                input.classList.remove('is-invalid');
                successMsg.classList.add('d-none');
                errorMsg.classList.add('d-none');

                if (typeof $ !== 'undefined' && $('#addSubjectModal').length) {
                    $('#addSubjectModal').modal('show');
                }
            };
        }

        if (savesubjectbtn) {
            savesubjectbtn.onclick = function() {
                var input = document.getElementById('newsubjectname');
                var successMsg = document.getElementById('subject-success-msg');
                var errorMsg = document.getElementById('subject-error-msg');

                if (!input || !successMsg || !errorMsg || !subjectselect) {
                    return;
                }

                var name = input.value.trim();

                successMsg.classList.add('d-none');
                errorMsg.classList.add('d-none');
                input.classList.remove('is-invalid');

                if (!name) {
                    input.classList.add('is-invalid');
                    return;
                }

                fetch(M.cfg.wwwroot + '/mod/customassessment/ajax_add_subject.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'sesskey=' + encodeURIComponent(M.cfg.sesskey) +
                          '&subjectname=' + encodeURIComponent(name)
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data.success) {
                        errorMsg.textContent = data.error || 'Unable to add subject';
                        errorMsg.classList.remove('d-none');
                        return;
                    }

                    subjectselect.add(new Option(data.name, data.id, true, true));

                    successMsg.textContent = 'Subject added successfully';
                    successMsg.classList.remove('d-none');

                    setTimeout(function() {
                        if (typeof $ !== 'undefined' && $('#addSubjectModal').length) {
                            $('#addSubjectModal').modal('hide');
                        }
                    }, 1200);
                })
                .catch(function() {
                    errorMsg.textContent = 'Unable to add subject';
                    errorMsg.classList.remove('d-none');
                });
            };
        }

        if (typeof $ !== 'undefined' && $('#addSubjectModal').length) {
            $('#addSubjectModal').on('hidden.bs.modal', function() {
                var input = document.getElementById('newsubjectname');
                if (input) {
                    input.value = '';
                    input.classList.remove('is-invalid');
                }
            });
        }

        /* ================= TOPIC MODAL ================= */
        if (addtopicbtn) {
            addtopicbtn.onclick = function() {
                if (!subjectselect) {
                    return;
                }

                var subjectid = subjectselect.value;
                if (!subjectid || parseInt(subjectid, 10) <= 0) {
                    alert('Please select a subject first');
                    return;
                }

                var input = document.getElementById('newtopicname');
                var successMsg = document.getElementById('topic-success-msg');
                var errorMsg = document.getElementById('topic-error-msg');

                if (!input || !successMsg || !errorMsg) {
                    return;
                }

                input.value = '';
                input.classList.remove('is-invalid');
                successMsg.classList.add('d-none');
                errorMsg.classList.add('d-none');

                if (typeof $ !== 'undefined' && $('#addTopicModal').length) {
                    $('#addTopicModal').modal('show');
                }
            };
        }

        if (savetopicbtn) {
            savetopicbtn.onclick = function() {
                var input = document.getElementById('newtopicname');
                var successMsg = document.getElementById('topic-success-msg');
                var errorMsg = document.getElementById('topic-error-msg');

                if (!input || !successMsg || !errorMsg || !subjectselect || !topicselect) {
                    return;
                }

                var topicname = input.value.trim();
                var subjectid = subjectselect.value;

                successMsg.classList.add('d-none');
                errorMsg.classList.add('d-none');
                input.classList.remove('is-invalid');

                if (!subjectid || parseInt(subjectid, 10) <= 0) {
                    errorMsg.textContent = 'Please select a subject first';
                    errorMsg.classList.remove('d-none');
                    return;
                }

                if (!topicname) {
                    input.classList.add('is-invalid');
                    return;
                }

                fetch(M.cfg.wwwroot + '/mod/customassessment/ajax_add_topic.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'sesskey=' + encodeURIComponent(M.cfg.sesskey) +
                          '&topicname=' + encodeURIComponent(topicname) +
                          '&subjectid=' + encodeURIComponent(subjectid)
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data.success) {
                        errorMsg.textContent = data.error || 'Unable to add topic';
                        errorMsg.classList.remove('d-none');
                        return;
                    }

                    topicselect.add(new Option(data.name, data.id, true, true));

                    successMsg.textContent = 'Topic added successfully';
                    successMsg.classList.remove('d-none');

                    setTimeout(function() {
                        if (typeof $ !== 'undefined' && $('#addTopicModal').length) {
                            $('#addTopicModal').modal('hide');
                        }
                    }, 1200);
                })
                .catch(function() {
                    errorMsg.textContent = 'Unable to add topic';
                    errorMsg.classList.remove('d-none');
                });
            };
        }

        if (typeof $ !== 'undefined' && $('#addTopicModal').length) {
            $('#addTopicModal').on('hidden.bs.modal', function() {
                var input = document.getElementById('newtopicname');
                if (input) {
                    input.value = '';
                    input.classList.remove('is-invalid');
                }
            });
        }

        /* ================= BLOOM'S LEVEL MULTISELECT ================= */
        if (bloomSelect) {
            Array.from(bloomSelect.options).forEach(function(option) {
                option.addEventListener('mousedown', function(e) {
                    e.preventDefault();
                    option.selected = !option.selected;
                    bloomSelect.dispatchEvent(new Event('change'));
                });
            });
        }

        function updateBloomMsg() {
            if (!bloomSelect || !bloomMsg || !numQuestionsInput) {
                return;
            }

            var selected = Array.from(bloomSelect.selectedOptions).map(function(o) {
                return o.value;
            });

            var numLevels = selected.length;
            var numQuestions = parseInt(numQuestionsInput.value, 10) || 0;

            if (numLevels > 0 && numQuestions > 0) {
                var perLevel = Math.ceil(numQuestions / numLevels);
                bloomMsg.textContent =
                    'You selected ' + numLevels + ' level' + (numLevels > 1 ? 's' : '') +
                    ' → ' + numLevels + ' × ' + perLevel + ' = ' + (perLevel * numLevels) + ' questions';
            } else {
                bloomMsg.textContent = '';
            }
        }

        if (bloomSelect) {
            bloomSelect.addEventListener('change', updateBloomMsg);
        }

        if (numQuestionsInput) {
            numQuestionsInput.addEventListener('input', updateBloomMsg);
        }

        updateBloomMsg();
    });
})();
");

        /* ================= STANDARD ================= */
        $this->standard_coursemodule_elements();

        if (!empty($this->current->instance)) {
            $toform = (object) [
                'name'         => $this->current->name,
                'numquestions' => $this->current->numquestions ?? 10,
                'subjectid'    => $this->current->subjectid ?? 0,
                'topicid'      => $this->current->topicid ?? 0,
                'visible'      => $this->current->visible ?? 1,
                'groupmode'    => $this->current->groupmode ?? 0,
            ];

            if (!empty($this->current->bloomslevels)) {
                $toform->bloomslevels = explode(',', $this->current->bloomslevels);
            } else {
                $toform->bloomslevels = [];
            }

            $this->set_data($toform);
        }

        $this->add_action_buttons();
    }
}