<?php
require_once (t3lib_extMgm::extPath('cal').'controller/class.tx_cal_registry.php');
	
	/* 
	* @author: Mario Matzulla
	*/
	class tx_cal_ics_generator {
		
		var $info = '';
		var $pageIDForPlugin;
		var $extConf;
		
		function tx_cal_ics_generator($pageIDForPlugin) {
			$this->extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['cal']);
			$this->pageIDForPlugin = $pageIDForPlugin;
		}
		
		function getInfo() {
			return $this->info;
		}
		
		function check(){
			$select = '*';
			$table = 'tx_cal_event';
			$where = 'tx_caldav_data is null OR tx_caldav_data like "%no event model template file found%" OR tx_caldav_data like "%BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//TYPO3/NONSGML Calendar Base (cal) V1.4-dev//EN
METHOD:PUBLISH
END:VCALENDAR%"';
			
			$return = '';

			$results = $GLOBALS['TYPO3_DB']->exec_SELECTquery($select, $table, $where);
			if($results) {
				while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($results)){
					$return .= '<p>UID '.$row['uid'].' PID '.$row['pid'].' -> '.$row['tx_caldav_data'].'</p><br />';
				}
				$GLOBALS['TYPO3_DB']->sql_free_result($results);
			}
			if($return == ''){
				$return = 'no problems found';
			}
			return $return;
		}
				
		function countEventsWithoutIcs($eventPage=0) {
			$count = 0;
			$select = 'count(*)';
			$table = 'tx_cal_event';
			$where = '(tx_caldav_data is null) or (tx_caldav_data like "%no event model template file found%" OR tx_caldav_data like "%BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//TYPO3/NONSGML Calendar Base (cal) V1.4-dev//EN
METHOD:PUBLISH
END:VCALENDAR%")';
			if($eventPage > 0){
				$where = 'pid = '.$eventPage.' AND '.$where;
			}
			$results = $GLOBALS['TYPO3_DB']->exec_SELECTquery($select, $table, $where);
			if($results) {
				while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($results)){
					$count = $row['count(*)'];
				}
				$GLOBALS['TYPO3_DB']->sql_free_result($results);
			}
			
			return $count;
		}
		
		function generateIcs($eventPage=0) {
			require_once(t3lib_extMgm::extPath('cal').'controller/class.tx_cal_functions.php');
			require_once(t3lib_extMgm::extPath('cal').'/controller/class.tx_cal_api.php');
			
			$select = '*';
			$table = 'tx_cal_event';
			$where = '(tx_caldav_data is null) or (tx_caldav_data like "%no event model template file found%" OR tx_caldav_data like "%BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//TYPO3/NONSGML Calendar Base (cal) V1.4-dev//EN
METHOD:PUBLISH
END:VCALENDAR%")';
			if($eventPage > 0){
				$where = 'pid = '.$eventPage.' AND '.$where;
			}

			$results = $GLOBALS['TYPO3_DB']->exec_SELECTquery($select, $table, $where);
			if($results) {
				while ($event = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($results)){
					$eventArray[] = $event;
				}
				$GLOBALS['TYPO3_DB']->sql_free_result($results);
				
				$page = t3lib_BEfunc::getRecord('pages', intval($this->pageIDForPlugin), 'doktype');
				if($page['doktype'] != 254) {

					$tx_cal_api = t3lib_div :: makeInstance('tx_cal_api');
					$tx_cal_api = &$tx_cal_api->tx_cal_api_without($this->pageIDForPlugin);
					
					foreach($eventArray as $event){
						if($event['pid'] > 0) {
							/* Check Page TSConfig for a preview page that we should use */
						
							$eventObject = $tx_cal_api->modelObj->findEvent($event['uid'], 'tx_cal_phpicalendar', '', false, false, false, true, true);
							
							if(is_object($eventObject)){

								if ($eventObject->conf['view.']['event.']['phpicalendarEventTemplate']) {
									$oldPath = &$eventObject->conf['view.']['event.']['phpicalendarEventTemplate'];
								} else {
									$oldPath = &$eventObject->conf['view.']['event.']['eventModelTemplate'];
								}
	
								$oldView = $eventObject->conf['view'];
								$eventObject->conf['view'] = 'single_ics';
								$extPath=t3lib_extMgm::extPath('cal');
	
								$oldPath = str_replace('EXT:cal/', $extPath, $oldPath);
								//$oldPath = str_replace(PATH_site, '', $oldPath);
								$eventObject->conf['view.']['event.']['phpicalendarEventTemplate'] = $oldPath;
								$eventObject->conf['view.']['event.']['eventModelTemplate'] = $oldPath;
								$oldBackPath = $GLOBALS['TSFE']->tmpl->getFileName_backPath;
								$GLOBALS['TSFE']->tmpl->getFileName_backPath = '';
								$fileInfo = t3lib_div::split_fileref($oldPath);
								$GLOBALS['TSFE']->tmpl->allowedPaths[] = $fileInfo['path'];
						
								$viewObj = &tx_cal_registry::Registry('basic','viewcontroller');
								$masterArray = Array($eventObject);
								$drawnIcs = $viewObj->drawIcs($masterArray, '', false);
	
								$table = 'tx_cal_event';
								$where = 'uid = '.$event['uid'];
								$eventData = Array('tx_caldav_data'=>$drawnIcs);
								if($event['tx_caldav_uid']==null || $event['tx_caldav_uid']==''){
									$eventData['tx_caldav_uid'] = 'a1b2c3_'.$event['calendar_id'].'_'.$event['uid'];
								}
								$result = $GLOBALS['TYPO3_DB']->exec_UPDATEquery($table,$where,$eventData);
						
								$table = 'tx_cal_calendar';
								$where = 'uid = '.$event['calendar_id'];
								$eventData = Array('tstamp'=>time());
								$result = $GLOBALS['TYPO3_DB']->exec_UPDATEquery($table,$where,$eventData);
	
								$GLOBALS['TSFE']->tmpl->getFileName_backPath = $oldBackPath;
								$eventObject->conf['view'] = $oldView;
							}
						}
					}
				}
			}
			
			$this->info = 'Done.';
		}
	}
	
if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/caldav/mod1/class.tx_cal_ics_generator.php']) {
	require_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/caldav/mod1/class.tx_cal_ics_generator.php']);
}

?>