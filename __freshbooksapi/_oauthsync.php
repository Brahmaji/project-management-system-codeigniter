<?php
error_reporting(E_ALL);
error_reporting(2047);
ini_set("display_errors",1);
require_once("_oauthtests.php");
require_once("__freshbooksinit.php");
$db = new PDO("mysql:host=localhost;dbname=admin_freelancer", "freelancer_db", "t8Z6MxzvB?1bipjc");	
foreach (getWorkLogs($db) as $worklog) {
	addUpdateTimeEntry($db, $worklog);
	 echo '<br>ten zostal przerobiony<br>';
	 var_dump($worklog);
	 echo '<br>';
}

function getWorkLogs($db){
	
	$date = date("Y-m-d");
	$companyID = 306864;
	$row = $db->query("SELECT access_token FROM oauth WHERE user_id = 12")->fetch(PDO::FETCH_ASSOC);
	$access_token = $row['access_token'];
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, "https://webapi.timedoctor.com/v1.1/companies/".$companyID."/worklogs?access_token=".$access_token."&_format=json&start_date=".$date."&end_date=".$date);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$response = json_decode(curl_exec($ch), true);
	print_r($response);
	//echo "https://webapi.timedoctor.com/v1.1/companies/".$companyID."/worklogs?access_token=".$access_token."&_format=json&start_date=".$date."&end_date=".$date;
	//die();
	return $response['worklogs']['items'];
}

function addUpdateTimeEntry($db, $worklog){
	
	$date =date("Y-m-d");
	$from = "'".$date." 00:00:00'";
	$to = "'".$date." 23:59:59'";
	$totalseconds = $worklog['length'];
	$currentTask = getCurrentTask($worklog['task_id']);

	if(empty($currentTask)) { return; }
	echo 'point';
	$taskid = $currentTask['tasks_id'];
	$userid = $currentTask['tasks_assigned_to_id'];
	$projectid = getCurrentProject($worklog['project_id']);
	$action = 'start';

	$hours = floatval( ($totalseconds / 60) / 60 );

	$freshbooksprojectid = getFreshbooksProjectIdFromId($projectid);
	$freshbooksstaffid = getFreshbooksStaffIdFromId($userid);
	$freshbookstaskid = getFreshbooksTaskIdFromId($taskid); 

	$prepared = $db->prepare("SELECT * FROM timer 
				WHERE timer_project_id = $projectid 
				AND timer_taskid = $taskid 
				AND timer_team_member_id = $userid 
				AND timer_fromTimeDoctor = 1
				AND timer_start_datetime >= $from
				AND timer_start_datetime <= $to
				ORDER BY timer_id DESC LIMIT 1");
	$prepared->execute();
	$row = $prepared->fetch(PDO::FETCH_ASSOC);
	print_r($prepared->rowCount());
	print_r($row['timer_status']);
	if($prepared->rowCount() == 0 || $row['timer_status'] == 'billed'){

		

		$prepared = $db->prepare("INSERT INTO timer (timer_project_id, timer_taskid, timer_start_datetime, timer_seconds, timer_team_member_id, timer_status, timer_fromTimeDoctor, counted) 
															VALUES ( ?, ?, ?, ?, ?, ?, 1,0)");
		$prepared->execute(array($projectid, $taskid, date("Y-m-d h:i:s"),$totalseconds, $userid, 'offsite'));
		print_r(array($projectid, $taskid, date("Y-m-d h:i:s"),$totalseconds, $userid, 'offsite'));
		echo ' <br>do insert<br>';
	}else{

		$prepared = $db->prepare("UPDATE timer SET timer_seconds = ?, counted = 0 WHERE timer_id = ?");
		$prepared->execute(array($totalseconds, $row['timer_id']));
		print_r(array($totalseconds, $row['timer_id']));
		echo ' <br>do update<br>';
		//$prepared = $db->prepare("UPDATE tasks SET hourslogged = hourslogged + ? WHERE tasks_id = ?");
		//$prepared->execute(array($hours, $row['timer_taskid']));

		

	}
}

?>