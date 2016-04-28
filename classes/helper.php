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
 * @copyright  20016 Loys Henrique Saccomano Gibertoni
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/blocks/game_achievements/lib.php');

class block_game_achievements_helper {

	public static function observer(\core\event\base $event)
	{
        /*global $DB;
		
        if(!is_student($event->userid))
		{
            return;
        }
				
		$achievements = $DB->get_records_sql("SELECT * FROM {achievements} WHERE deleted = ? AND ".$DB->sql_compare_text('event')." = ". $DB->sql_compare_text('?'), array('deleted' => 0, 'event' => $event->eventname));
		
		foreach($achievements as $achievement)
		{
			print_object('Aqui');
		}*/
    }
}
