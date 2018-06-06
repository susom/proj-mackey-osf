<?php
/**
 * Created by PhpStorm.
 * User: jael
 * Date: 4/11/18
 * Time: 11:52 AM
 */

namespace Stanford\OSF;

use \Plugin as Plugin;
use \REDCap as REDCap;
use Project;


class DataMirror {

    /**
     *
     * @param $parent_project_id - Project ID of parent
     * @param $target_project_id - Project ID of child project
     * @param $target_event_id   - Event ID migration form of child
     * @param $target_naming_prefix - For child new record, prefix of record id (optional)
     * @param $target_naming_padding - For child new record, padding of record id (optional)
     * @param $parent_record_id  - Parent ID to save in child project
     * @param $target_field_for_parent_record_id - Field where to save parent id in child project
     *
     * @return array - returns status field with key "success" or "errors". If success is returned, new field is saved in key 'migrated_id'
     *
     */
    public static function handleChildProject($parent_project_id, $target_project_id,
                                              $target_event_id,
                                              $target_naming_prefix, $target_naming_padding,
                                              $parent_record_id, $target_field_for_parent_record_id) {
        $status = array();

        //check if the migration has already been done for originating parent record for this project
        // Verify record is not already in child project
        if (!empty($target_field_for_parent_record_id)) {
            $filter = "[" . $target_field_for_parent_record_id . "]='" . $parent_record_id . "'";
            $q = REDCap::getData($target_project_id, 'array', NULL, array($target_field_for_parent_record_id), $target_event_id, NULL, FALSE, FALSE, FALSE, $filter);
            if (count($q) > 0) {
                // We have an existing record
                //Plugin::log($q, "DEBUG", "existing record filter search: ".$filter);

                $msg = "Project $target_project_id already has a record with $target_field_for_parent_record_id = $parent_record_id";
                $status['errors'] = $msg;
                Plugin::log($msg);
                return $status;
            }
        }

        //instantiate Projects for both target and parent
        $parent_proj = new Project($parent_project_id);
        $parent_pk = $parent_proj->table_pk;

        $target_proj = new Project($target_project_id);
        $target_pk = $target_proj->table_pk;

        //1. get intersect data
        $arr_fields = self::getIntersectFields($target_project_id, $parent_project_id);

        //Plugin::log($arr_fields, "DEBUG", "INTERSECTION");

        if (empty($arr_fields)) {
            //Log msg in Notes field
            $msg = "There were no intersect fields between the parent (" . $parent_project_id .
                ") and child projects (" .  $target_project_id . ").";
            $status["errors"] = $msg;
            Plugin::log($status);
            return $status;
        }

         //2. get intersect data from parent
         $results = \REDCap::getData('json', $parent_record_id, $arr_fields);
         $results = json_decode($results, true);
         $newData = current($results);

         if (!$newData) {
             $msg = "There were no data to migrate in the parent (" . $parent_project_id .
                 ") for record (" .  $parent_record_id . ").";
             $status["errors"] = $msg;
             Plugin::log($msg);
             return $status;
         }

        //3. Get Next ID from target project
        $next_id = self::getNextId($target_project_id, $target_event_id, $target_naming_prefix, $target_naming_padding);
        Plugin::log($next_id, "DEBUG","Next ID for $target_project_id is $next_id");

        //4. SET UP CHILD PROJECT TO SAVE DATA

        //add additional fields to be added to the SaveData call
        $newData[$target_pk] = $next_id;

        if (!empty($target_event_id)) {
            $target_event_name = REDCap::getEventNames($target_event_id);
            $newData['redcap_event_name'] = ($target_event_name);
        }

        if (!empty($parent_record_id)) {
            $newData[$target_field_for_parent_record_id] = $parent_record_id;
        }

        //5. UPDATE CHILD: Upload the data to child project
        $result = \REDCap::saveData($target_project_id,'json',json_encode(array($newData)));

         // Check for upload errors
         $parent_data = array();
         if (!empty($result['errors'])) {
             $msg = "Error creating record in CHILD project ".$target_project_id." - ask administrator to review logs: " . print_r(($result['errors']));
             $status["errors"] = $msg;
             $status["error_message"] = $result['errors'];
             Plugin::log($result, "ERROR", "Error creating record in TARGET project");
             return $status;
         } else {
             $msg = "Successfully migrated parent data to $next_id in project $target_project_id ";
             //update parent project with local id
             $status["success"]["msg"] = $msg;

             $parent_data = array(
                 $parent_pk => $parent_record_id,
                 "migrated_id" => $next_id,
             );
             $status["success"]["data"] = $parent_data;

             return $status;
         }

    }

    /**
     * @param $project_1
     * @param $project_2
     * @return array|null
     */
    public static function getIntersectFields($project_1, $project_2) {
        if (empty($project_1) || empty($project_2)) {
            \Plugin::log("Either child and parent pids was missing.". intval($project_1) . "a and " .$project_2);
            return null;
        }

        //todo: update with using Project metadata rather than sql lookup
        $sql = "select field_name from redcap_metadata a where a.project_id = " . intval($project_1) .
            " and field_name in (select b.field_name from redcap_metadata b where b.project_id = " . $project_2 . ");";
        $q = db_query($sql);
        //\Plugin::log($sql, "DEBUG", "SQL");

        $arr_fields = array();
        while ($row = db_fetch_assoc($q)) {
            $arr_fields[] = $row['field_name'];
        }

        return ($arr_fields);

    }

    /**
     * For example, following parameters should yield next id in form : 2151-0001
     * $target_project_pid = STUDY_PID
     * $prefix = "2151"
     * $delilimiter = "-"
     * $padding = 4
     *
     */
    public static function getNextID($target_project_pid, $event_id, $prefix='', $padding = 0) {
        $delimiter='';
        $thisProj = new Project($target_project_pid);
        $pk = $thisProj->table_pk;

        // Determine next record in child project
        $all_ids = \REDCap::getData($target_project_pid, 'array', NULL, array($pk),$event_id);
        //\Plugin::log($all_ids, "DEBUG", "ALL IDS for ".$target_project_pid);

        //if empty then last_id is 0
        if (empty($all_ids)) {
            $last_id = $prefix.$delimiter.'0';
            //\Plugin::log($last_id, "DEBUG","there is no existing, set last to ".$last_id);
        } else {
            ksort($all_ids);
            end($all_ids);
            $last_id = key($all_ids);
        }

        $re = '/'.$prefix.$delimiter.'(?\'candidate\'\d*)/';
        preg_match_all($re, $last_id, $matches, PREG_SET_ORDER, 0);
        $candidate = $matches[0]['candidate'];
        //\Plugin::log($matches,"DEBUG","matches with candidate: ".$candidate);

        $incremented = intval($candidate) + 1;

        if (($padding > 0) && ($padding > strlen((string)$incremented))) {
            $padded = str_pad($incremented, $padding, '0', STR_PAD_LEFT);
        } else {
            $padded = $incremented;
        }

        $next_id = $prefix.$delimiter.$padded;

        //return value
        return $next_id;

    }

}