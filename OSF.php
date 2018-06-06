<?php
namespace Stanford\OSF;


include_once "Util.php";
include_once "classes/DataMirror.php";
use REDCap;
use Project;

use Stanford\OSF\DataMirror as DataMirror;

/**
 * Class OSF
 *
 * https://uit.stanford.edu/developers/apis/person
 *
 * @package Stanford\SPL
 */
class OSF extends \ExternalModules\AbstractExternalModule
{
    public $config;
    private $osf_library = null;  //Project with the OSF Library

    public function __construct()
    {
        parent::__construct();

        // Load the config
        global $project_id;
        if (!empty($project_id)) {
            $this->config = json_decode($this->getConfigAsString(),true);
        }

    }

    public function redcap_survey_page_top() {
        self::log(__METHOD__);
    }



    public function redcap_survey_complete ($project_id, $record = NULL, $instrument, $event_id, $group_id = NULL, $survey_hash, $response_id = NULL, $repeat_instance = 1 ) {
        self::log(__METHOD__);

        //NEW WORK FLOW - NO LONGER MIGRATING AT END OF LAST SURVEY
        // See if it is the end of the survey and if so determine where to redirect the user..
        if ($instrument == $this->config['last_survey']) {
            $repeat_form = $this->config['admin_review_page'];

            //eligibility form is a repeating form, so calculate the next instance number to create.
            $next_instance = self::getNextRepeatingInstance($record, $event_id, $repeat_form);

            //1. Get all the available studies from study library
            $studies = $this->getOSFLibrary();

            //2. Pick study by selecting
            //   a. inclusion logic
            //   b. priority
            //   ??

            //get the referral_hash, referral_campaign
            $campaign = $this->getCampaign($record, $event_id);

            //Go through OSF Study Library and filter out for studies that fit on basis of inclusion logic
            $candidates = array();
            foreach ($studies as $study_id=>$event) {
                $study = current($event);
                $inclusion_logic = $study['include_logic'];

                if (!empty($inclusion_logic)) {
                    $result = REDCap::evaluateLogic($inclusion_logic,$project_id,$record, $event_id, $repeat_instance);
                    if ($result !== true)  {
                        self::log("Record $record failed inclusion logic for hash [$study_id]");

                        continue;
                    } else {
                        $priority = $study['priority'];
                        $candidates[$priority] = $study;
                    }
                }
            }


            //just take the top priority one and create it?
            //how about the other ones
            $top_priority = min(array_keys($candidates));

            $candidate_study = $candidates[$top_priority];

            //todo: check if this candidate_study already exists (and not released for this record?

            //create eligibility review form for each of the candidates? or just the top one?
            //\Plugin::log($candidate_study, "DEBUG", "priority is ".$candidate_study['priority']);
            $save_date = array(
                REDCap::getRecordIdField() => $record,
                "redcap_repeat_instrument" => 'eligibility_review',
                "redcap_repeat_instance"   => $next_instance,
                "referral_hash"            => $campaign['study_id'],
                "referral_campaign"        => $campaign['campaign_name'],
                "study_id"                 => $candidate_study['study_id'],
                "priority"                 => $candidate_study['priority'],
                "inclusion_logic"          => $candidate_study['include_logic']
            );

            $q = REDCap::saveData('json', json_encode(array($save_date)));
            self::log("save eligibility review form", $q);


        }
    }

    public function redcap_save_record($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance) {
        self::log(__METHOD__);

        // if end of eligibility_review check if migration checkbox is checked. if so migrate intersection to target pid
        if ($instrument == $this->config['admin_review_page']) {
            //check if migration checkbox is checked
            $get_data = array('study_id', 'target_migrate');
            $q = REDCap::getData($project_id, 'json', $record, $get_data);
            $result = json_decode($q, true);

            //get the study_id and the target_migrate status  for this $repeat_instance
            $study_id = $result[$repeat_instance - 1]['study_id'];
            $target_migrate = $result[$repeat_instance - 1]['target_migrate___1'];
//            self::log("JSON getdata: ", $repeat_instance - 1, $study_id, $target_migrate, json_decode($q, true));

            //the migrate checkbox has been checked so start migration
            if ($target_migrate) {
                $study_data = $this->getStudyFromLibrary($study_id);
                //self::log("study_data", $study_data);

                //migrate the intersection of data into the target study
                $res = DataMirror::handleChildProject($project_id, $study_data['target_pid'],
                    $study_data['target_event_id'],
                    $study_data['target_naming_prefix'], $study_data['target_naming_padding'],
                    $record,$study_data['target_osf_id_field']);
                //\Plugin::log($res, "DEBUG", "Return from handleChild");

                $update_data = null;

                //migration had errors, log the status
                if (!empty($res['errors'])) {

                    //use more detailed log?
                    $this->logStatus($this->config['log_field'],$record,$res,"Error migrating data");
                    self::log("ERROR", $res);
                }

                //migration worked
                if (!empty($res['success'])) {

                    //update migrated_id with local field
                    $local_field = $study_data['local_field_for_target_id'];

                    if (!empty($local_field)) {
                        //updata status
                        $update_data = $res['success']["data"];
                        $msg = $res['success']["msg"];

                        $update_data[$local_field] = $update_data["migrated_id"];
                        unset($update_data['migrated_id']);

                        //add the repeating instance field
                        $update_data['redcap_repeat_instance'] = $repeat_instance;
                        self::log('SUCCESS', $msg, $update_data);

                        //add the log message or log it separately?
                        //$update_data[$this->config['log_field']] = $msg;

                        $result = \REDCap::saveData('json', json_encode(array($update_data)));
                        if (!empty($result['errors'])) {
                            $msg = "Error updating record in Parent project " . $project_id . " - ask administrator to review logs: " . json_encode($result);
                            self::log($msg);
                            //log to logging or log field??
                        }
                    }

                    //update the log separately
                    $this->logStatus($this->config['log_field'],$record,$res['success']['data'],$res['success']['msg']);
                }

            }

        }
    }



    /**
     * Log the status to the log field
     * @param $detail
     * @param string $header
     */
    public function logStatus($log_field, $record_id, $detail, $header = '')
    {
        $data = array();

        $msg = array();
        $msg[] = "---- " . date("Y-m-d H:i:s") . " ----";
        $msg[] = " $header";
        foreach ($detail as $k => $v) $msg[] = "  $k: $v";
        if (!empty($data['sms_log'])) $msg[] = "\n" . $data['sms_log'];

        $data = array(
            REDCap::getRecordIdField() => $record_id,
            //'redcap_event_name' => ?
            $log_field => implode("\n", $msg)
        );
        //print "<pre>LOG SAVE result" . print_r($data, true) . "</pre>";

        $response = REDCap::saveData('json', json_encode(array($data)));

        if (!empty($response['errors'])) {
            $msg = "Error creating record - ask administrator to review logs: " . json_encode($response);
            Plugin::log($msg, "ERROR");
            return ($response);
        }
        return null;

    }

    private static function getNextRepeatingInstance($record, $event_id, $repeat_form) {
        $get_data = array('referral_hash', 'study_id');
        $all_ids = \REDCap::getData( 'array', $record, $get_data);

        if (!empty($all_ids)) {
            $repeat_ids = $all_ids[$record]['repeat_instances'][$event_id][$repeat_form];

            $max_id = max(array_keys($repeat_ids));
            //\Plugin::log($repeat_ids, "DEBUG", "MAX IDS for repeating form " . $max_id);
            return ($max_id + 1);
        } else {
            //\Plugin::log("nothin so return 1");
            return 1;
        }

    }

    private static function getNextId($pid, $id_field, $event_id, $prefix = '', $padding = false) {
        $thisProj = new Project($pid);
        $recordIdField = $thisProj->table_pk;
        $q = REDCap::getData($pid,'array',NULL,array($id_field), $event_id);

        //self::log("Found records in project $pid using $recordIdField", $q);

        $i = 1;
        do {
            // Make a padded number
            if ($padding) {
                // make sure we haven't exceeded padding, pad of 2 means
                $max = 10^$padding;
                if ($i >= $max) {
                    self::log("Error - $i exceeds max of $max permitted by padding of $padding characters");
                    return false;
                }
                $id = str_pad($i, $padding, "0", STR_PAD_LEFT);
                self::log("Padded to $padding for $i is $id");
            } else {
                $id = $i;
            }

            // Add the prefix
            $id = $prefix . $id;
            self::log("Prefixed id for $i is $id");

            $i++;
        } while (!empty($q[$id][$event_id][$id_field]));

        self::log("New ID in project $pid is $id");
        return $id;
    }


    public function getCampaign($record, $event_id) {
        //get the hash from the field
        $hashes = REDCap::getData('array', $record, array($this->config['hash_field']), $event_id);

        $hash = $hashes[$record][$event_id][$this->config['hash_field']];

        $campaigns = $this->config['campaigns'];

        return $campaigns[$hash];

    }

    public function getStudyFromLibrary($study_id) {
        //use the new version with the OSF Study library held in project
        $osf_library = $this->getOSFLibrary();

        if (isset($osf_library[$study_id])) {
            return current($osf_library[$study_id]);
        } else {
            return null;
        }

//        $library_pid = $this->config['library_pid'];
//
//        //self::log("library_ pid", $library_pid);
//        $q = REDCap::getData($library_pid, 'json', $study_id);
//        $result = current(json_decode($q, true));
//        //self::log("get library : JSON getdata: ",$study_id, $library_pid,$result);
//
//        return $result;
    }

    public function setHash($record, $hash) {
        // Save the hash to the record
        $data = array(
            REDCap::getRecordIdField()=> $record,
            $this->config['hash_field'] => $hash
        );
        $q = REDCap::saveData('json', json_encode(array($data)));
        if (!empty($q['errors'])) {
            // Error occurred setting the hash
            self::log("Error saving hash", $q, $data, "ERROR");
        }
        // self::log("Saved Hash", $this->config, $data, $q);
    }

    public function getHash($record) {
        $fields = array($this->config['hash_field']);
        $q = REDCap::getData('json',$record, $fields);
        $results = json_decode($q,true);
        $hash = $results[0][$this->config['hash_field']];
        return $hash;
    }

    public function setNewUser($data) {
        global $Proj;

        //save data from the new user login page
        //create new record so get a new id
        $next_id = self::getNextId($Proj->project_id, REDCap::getRecordIdField(),$Proj->firstEventId);

        $data[REDCap::getRecordIdField()] = $next_id;

        $q = REDCap::saveData('json', json_encode(array($data)));
        self::log("Saved New User", $this->config, $data, $q);

        //if save was a success, return the new id
        if (!empty($q['errors'])) {
            self::log("Errors in " . __FUNCTION__ . ": data=" . json_encode($data) . " / Response=" . json_encode($q), "ERROR");
            return false;
        } else {
            return $next_id;
        }
    }



    public function getHashParams($hash) {
        if (isset($this->config['hashes'][$hash])) {
            return $this->config['hashes'][$hash];
        } else {
            return false;
        }
    }

    public function getOSFLibrary() {
        //lazy load the Library project and get the hash
        if(!isset($this->osf_library)) {
            $this->osf_library = $this->setOSFLibrary();
        }
        return $this->osf_library;


    }

    public function setOSFLibrary() {
        $library_pid = $this->config['library_pid'];

        $q = REDCap::getData($library_pid, 'array');

        self::log("get library : JSON getdata: ", $library_pid,$q);

        return $q;
    }


    // CONFIG EDITOR START //
    /**
     * Read the current config from a single key-value pair in the external module settings table
     */
    function getConfigAsString() {
        global $project_id;
        if ($project_id) {
            $string_config = $this->getProjectSetting($this->PREFIX . '-config');
        } else {
            $string_config = $this->getSystemSetting($this->PREFIX . '-config');
        }
        // SurveyDashboard::log($string_config);
        return is_null($string_config) ? "" : $string_config;
    }

    /**
     * Set the current config to the redcap_exteral_modules_settings table as a single key-value pair
     * @param $string_config
     */
    function setConfigAsString($string_config) {
        global $project_id;
        if ($project_id) {
            $this->setProjectSetting($this->PREFIX . '-config', $string_config);
        } else {
            $this->setSystemSetting( $this->PREFIX . '-config', $string_config);
        }
    }

    function getConfigDirections() {
        return "Please enter your configuration as a json file here";
    }
    // CONFIG EDITOR END //










    // defines criteria to judge someone is on a development box or not
    public static function isDev()
    {
        $is_localhost  = ( @$_SERVER['HTTP_HOST'] == 'localhost' );
        $is_dev_server = ( isset($GLOBALS['is_development_server']) && $GLOBALS['is_development_server'] == '1' );
        $is_dev = ( $is_localhost || $is_dev_server ) ? 1 : 0;
        return $is_dev;
    }

    // Log Wrapper
    public static function log() {
        if (self::isDev()) {
            if (class_exists("Stanford\OSF\Util")) {
                call_user_func_array("Stanford\OSF\Util::log", func_get_args());
            }
        } else {
            error_log("NA");
        }
    }

}