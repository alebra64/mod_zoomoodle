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

defined('MOODLE_INTERNAL') || die();

// Nome del modulo e aiuti.
$string['modulename']               = 'ZooMoodle';
$string['modulenameplural']         = 'ZoomMoodles';
$string['modulename_help']          = "Il modulo attività ZooMoodle ti permette di integrare un webinar Zoom nel corso.

Quando uno studente viene iscritto al corso, viene automaticamente registrato al webinar Zoom e riceve un'email con le istruzioni di accesso.
Lo studente può aggiungere la data/ora al proprio calendario e vedere il link personale per partecipare.

Al termine del webinar, la percentuale di partecipazione viene calcolata automaticamente e il completamento dell’attività viene segnato in base alla soglia configurata.

L’insegnante può consultare il report ufficiale di Zoom e correggere manualmente eventuali anomalie.";
$string['pluginname']               = 'ZooMoodle';
$string['pluginadministration']     = 'Gestisci ZooMoodle';

// Impostazioni webinar.
$string['webinar']                  = 'Webinar';
$string['topic']                    = 'Argomento';
$string['webinar_id']               = 'ID Webinar';
$string['webinar_id_help']          = 'Inserisci l’ID del webinar Zoom.';
$string['start_time']               = 'Ora di inizio';
$string['start_time_help']          = 'Inserisci data e ora di inizio del webinar.';
$string['duration']                 = 'Durata (minuti)';
$string['duration_help']            = 'Inserisci la durata totale di tutte le sessioni in minuti.';
$string['recurring']                = 'Ricorrente';
$string['end_time']                 = 'Ora di fine ultima sessione';
$string['end_time_help']            = 'Se il webinar è ricorrente, abilita e inserisci data/ora di fine.';
$string['calendariconalt']          = 'Icona calendario';
$string['add_to_calendars']         = 'Aggiungi ai calendari';
$string['calendaraddtogoogle']      = 'Google';
$string['calendaraddtoyahoo']       = 'Yahoo';
$string['calendaraddtooutlook']     = 'Outlook';
$string['webinarjoin']              = 'Partecipa al webinar';

// Sincronizzazione iscrizioni.
$string['syncsettings']             = 'Sincronizzazione iscrizioni';
$string['syncenrolments']           = 'Sincronizza iscrizioni Zoom';
$string['syncenrolments_help']      = 'Se abilitata, ogni iscrizione al corso verrà inviata automaticamente al webinar Zoom configurato.';

// Credenziali OAuth.
$string['clientid']                 = 'Client ID';
$string['clientid_desc']            = 'Client ID per OAuth Server-to-Server di Zoom.';
$string['clientsecret']             = 'Client Secret';
$string['clientsecret_desc']        = 'Client Secret per OAuth Server-to-Server di Zoom.';
$string['accountid']                = 'Account ID';
$string['accountid_desc']           = 'Account ID per OAuth Server-to-Server di Zoom.';

// Token API statico (legacy).
$string['apitoken']                 = 'Token API (legacy)';
$string['apitoken_desc']            = 'Token API statico per integrazione Zoom legacy (ignorato se si usa OAuth).';

// Pagina impostazioni admin.
$string['modsettingzoomoodle']      = 'Impostazioni ZooMoodle';
$string['apiurl']                   = 'URL API';
$string['apiurl_desc']              = 'URL base per le API Zoom, es. https://api.zoom.us/v2';

// Votazione.
$string['grade']                    = 'Voto massimo';
$string['grade_help']               = 'Punteggio massimo per questa attività.';
$string['gradepass']                = 'Punteggio di superamento';
$string['gradepass_help']           = 'Punteggio minimo per considerare superata l’attività.';

// Errori.
$string['err_duration_nonpositive'] = 'La durata deve essere un numero positivo.';
$string['err_duration_too_long']    = 'La durata non può superare 150 ore.';
$string['err_end_time_past']        = 'L’ora di fine deve essere successiva all’inizio.';

// Permessi.
$string['zoomoodle:addinstance']    = 'Aggiungi un’attività ZooMoodle';
$string['zoomoodle:view']           = 'Visualizza attività ZooMoodle';

// Task pianificati.
$string['webinar_graduation_task']  = 'Task di chiusura webinar';
$string['tasksyncenrolments']       = 'Sincronizza iscrizioni Zoom';

// Quiz basati su frequenza.
$string['attendancethreshold']      = 'Soglia di frequenza (%)';
$string['attendancethreshold_help'] = 'Percentuale minima di partecipazione per abilitare il quiz.';
$string['quiztorelease']            = 'Quiz da rilasciare';
$string['quiztorelease_help']       = 'Seleziona il quiz da rendere visibile al superamento della soglia.';
$string['errthresholdrange']        = 'Inserisci un valore tra 0 e 100 per la soglia.';

// API Privacy.
$string['privacy:metadata:zoomoodle']                       = 'Memorizza impostazioni e iscrizioni Zoom.';
$string['privacy:metadata:zoomoodle:attendance_threshold'] = 'Percentuale minima di partecipazione.';
$string['privacy:metadata:zoomoodle:quiztorelease']        = 'ID del quiz da rilasciare.';
