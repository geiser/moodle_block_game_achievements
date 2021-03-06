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
 
/**
 * Achievements block event observer implementation.
 *
 * @package    block_game_achievements
 * @copyright  2016 Loys Henrique Saccomano Gibertoni
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/blocks/game_achievements/lib.php');

class block_game_achievements_helper {

	public static function observer(\core\event\base $event)
	{
		global $DB;
		
        if(!is_student($event->userid))
		{
            return;
        }
				
		$achievements = $DB->get_records_sql("SELECT * FROM {achievements} WHERE deleted = ? AND ".$DB->sql_compare_text('event')." = ". $DB->sql_compare_text('?'), array('deleted' => 0, 'event' => $event->eventname));
		
		$achievementids_reached = array();
		$achievementuserids_reached = array();
		
		foreach($achievements as $achievement)
		{
			if(!(satisfies_conditions($achievement->conditions, $event->courseid, $event->userid) && satisfies_advanced_conditions($achievement, $event) && satisfies_block_conditions($achievement, $event->courseid, $event->userid)))
			{
				continue;
			}
				
			$blockcontextid = $DB->get_field('block_instances', 'parentcontextid', array('id' => $achievement->blockinstanceid));
			if(!$blockcontextid) // If block was deleted
			{
				continue;
			}
			
			// Descobrir se precisa verificar o courseid
			$blockcontext = context::instance_by_id($blockcontextid);
			$context = context::instance_by_id($event->contextid);
			if(strpos($context->path, $blockcontext->path) !== 0  && $blockcontext->instanceid != SITEID) // Se o o contexto atual não estiver na hierarquia do contexto do bloco
			{
				continue;
			}
			
			$unlocked_achievement = $DB->record_exists('achievements_log', array('userid' => $event->userid, 'achievementid' => $achievement->id));
			if($unlocked_achievement)
			{
				continue;
			}
			
			$manager = get_log_manager();
			$selectreaders = $manager->get_readers('\core\log\sql_reader');
			if ($selectreaders) {
				$reader = reset($selectreaders);
			}
			$selectwhere = "eventname = :eventname
				AND component = :component
				AND action = :action
				AND target = :target
				AND crud = :crud
				AND edulevel = :edulevel
				AND contextid = :contextid
				AND contextlevel = :contextlevel
				AND contextinstanceid = :contextinstanceid
				AND userid = :userid 
				AND anonymous = :anonymous
				AND timecreated = :timecreated";
			$params['eventname'] = $event->eventname;
			$params['component'] = $event->component;
			$params['action'] = $event->action;
			$params['target'] = $event->target;
			$params['crud'] = $event->crud;
			$params['edulevel'] = $event->edulevel;
			$params['contextid'] = $event->contextid;
			$params['contextlevel'] = $event->contextlevel;
			$params['contextinstanceid'] = $event->contextinstanceid;
			$params['userid'] = $event->userid;
			$params['anonymous'] = $event->anonymous;
			$params['timecreated'] = $event->timecreated;

			$logid = $reader->get_events_select($selectwhere, $params, '', 0, 0);
			$logid = array_keys($logid)[0];
			
			$record = new stdClass();
			$record->logid = $logid;
			$record->achievementid = $achievement->id;
			$DB->insert_record('achievements_events_log', $record);
			
			$sql = 'SELECT count(*)
						FROM {achievements_events_log} a
							INNER JOIN {logstore_standard_log} l ON l.id = a.logid
						WHERE l.userid = :userid
							AND a.achievementid = :achievementid';
			$params['userid'] = $event->userid;
			$params['achievementid'] = $achievement->id;
			
			$times = $DB->count_records_sql($sql, $params);
			if($times == $achievement->times)
			{
				$record = new stdClass();
				$record->achievementid = $achievement->id;
				$record->userid = $event->userid;
				$DB->insert_record('achievements_log', $record);
				if(!$achievement->groupmode)
				{
					$achievementids_reached[] = $achievement->id;
					$achievementuserids_reached[] = array($event->userid);
				}
				
				
				if($achievement->groupmode)
				{
					$user_groups = groups_get_all_groups($event->courseid, $event->userid, $achievement->groupingid);
					foreach($user_groups as $user_group)
					{
						$group_unlocked_achievement = $DB->record_exists('achievements_groups_log', array('groupid' => $user_group->id, 'achievementid' => $achievement->id));
						if(!$group_unlocked_achievement)
						{
							$group_members = groups_get_members($user_group->id);
							
							if($achievement->allmembers)
							{
								$all_members_unlocked_achievement = true;
								foreach($group_members as $group_member)
								{
									if($group_member->id != $event->userid)
									{
										$unlocked_achievement = $DB->record_exists('achievements_log', array('userid' => $group_member->id, 'achievementid' => $achievement->id));
										if(!$unlocked_achievement)
										{
											$all_members_unlocked_achievement = false;
											break;
										}
									}
								}
								
								if($all_members_unlocked_achievement)
								{
									$record = new stdClass();
									$record->achievementid = $achievement->id;
									$record->groupid = $user_group->id;
									$DB->insert_record('achievements_groups_log', $record);
									
									$achievementids_reached[] = $achievement->id;
									$achievementuserids_reached[] = array_keys($group_members);
								}
							}
							else
							{
								$record = new stdClass();
								$record->achievementid = $achievement->id;
								$record->groupid = $user_group->id;
								$DB->insert_record('achievements_groups_log', $record);
								
								$achievementids_reached[] = $achievement->id;
								$achievementuserids_reached[] = array_keys($group_members);
							}
						}
					}
				}
			}
		}
		
		if(!empty($achievementids_reached))
		{
			$context = context_course::instance($event->courseid);
			$params = array(
				'context' => $context,
				'other' => array(
					'achievementids' => $achievementids_reached,
					'achievementuserids' => $achievementuserids_reached
				)
			);
			$event = \block_game_achievements\event\achievement_reached::create($params);
			$event->trigger();
		}
    }
}
