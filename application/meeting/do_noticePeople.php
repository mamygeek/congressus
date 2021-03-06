<?php /*
	Copyright 2015 Cédric Levieux, Parti Pirate

	This file is part of Congressus.

    Congressus is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Congressus is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Congressus.  If age, see <http://www.gnu.org/licenses/>.
*/

if (!isset($api)) exit();

$meetingId = $arguments["meetingId"];
$memcacheKey = "do_getPeople_$meetingId";

$memcache = openMemcacheConnection();

$connection = openConnection();

$locationBo = LocationBo::newInstance($connection);
$meetingBo = MeetingBo::newInstance($connection);
$noticeBo = NoticeBo::newInstance($connection);

$groupBo = GroupBo::newInstance($connection, $config);
$themeBo = ThemeBo::newInstance($connection, $config);
$fixationBo = FixationBo::newInstance($connection, $config);

$galetteBo = GaletteBo::newInstance($connection, $config["galette"]["db"]);

$meeting = $meetingBo->getById($meetingId);

if (!$meeting) {
	echo json_encode(array("ko" => "ko", "message" => "meeting_does_not_exist"));
	exit();
}

// TODO Compute the key // Verify the key

if (false) {
	echo json_encode(array("ko" => "ko", "message" => "meeting_not_accessible"));
	exit();
}

$meeting_date = "inconnu";
$meeting_time = "inconnu";
$start = new DateTime($meeting["mee_datetime"]);

$meeting_date = @$start->format(lang("date_format"));
$meeting_time = @$start->format(lang("time_format"));

$meeting_date = str_replace("{date}", $meeting_date, utf8_decode(lang("datetime_format", false)));
$meeting_date = str_replace("{time}", $meeting_time, $meeting_date);

$notices = $noticeBo->getByFilters(array("not_meeting_id" => $meeting[$meetingBo->ID_FIELD]));

$data = array();
$membersToNotice = array();

foreach($notices as $notice) {
	if ($notice["not_noticed"] == 1) continue;

	if ($notice["not_target_type"] == "galette_groups") {
		$members = $galetteBo->getMembers(array("adh_group_ids" => array($notice["not_target_id"])));

		foreach($members as $member) {
			$membersToNotice[$member["id_adh"]] = $member;
		}
	}
	else if ($notice["not_target_type"] == "dlp_themes") {
		$theme = $themeBo->getTheme($notice["not_target_id"]);
		$fixationMembers = $fixationBo->getFixations(array("fix_id" => $theme["the_current_fixation_id"], "with_fixation_members" => true));

		foreach($fixationMembers as $member) {
			$membersToNotice[$member["id_adh"]] = $member;
		}
	}
	else if ($notice["not_target_type"] == "dlp_groups") {
		$group = $groupBo->getGroup($notice["not_target_id"]);

		foreach($group["gro_themes"] as $theme) {
			foreach($theme["fixation"]["members"] as $member) {
				$membersToNotice[$member["id_adh"]] = $member;
			}
		}
	}

	$notice["not_noticed"] = "1";
	$noticeBo->save($notice);
}

// Send a mail to all $membersToNotice in bcc

if (count($membersToNotice)) {
	$message = getMailInstance();
	foreach($membersToNotice as $member) {
		$message->addBCC($member["email_adh"], $member["pseudo_adh"] ? $member["pseudo_adh"] : $member["nom_adh"] . " " . $member["prenom_adh"]);
	}

	$subject = utf8_decode(lang("notice_mail_subject", false));
	$subject = str_replace("{meeting_label}", $meeting["mee_label"], $subject);

	$meetingLink = $config["server"]["base"] . "meeting.php?id=" . $meeting[$meetingBo->ID_FIELD];

	$body = utf8_decode(lang("notice_mail_content", false));
	$body = str_replace("{meeting_label}", $meeting["mee_label"], $body);
	$body = str_replace("{meeting_link}", $meetingLink, $body);
	$body = str_replace("{meeting_date}", $meeting_date, $body);
	
	$locations = $locationBo->getByFilters(array("loc_meeting_id" => $meeting[$meetingBo->ID_FIELD], "loc_principal" => 1));
	if (count($locations)) {
		$location = $locations[0];
	}
	else {
		$location = array("loc_type" => "unknown", "loc_extra" => "");
	}

	$body = str_replace("{location_type}", strtolower(lang("loc_type_" . $location["loc_type"])), $body);
	$body = str_replace("{location_extra}", $location["loc_extra"], $body);

	$message->Subject = $subject;
	$message->Body = $body;
	$message->setFrom($config["smtp"]["from.address"], $config["smtp"]["from.name"]);

	$message->send();
}

$data["number_of_noticed_people"] = count($membersToNotice);
$data["ok"] = "ok";

$memcache->delete($memcacheKey);

echo json_encode($data, JSON_NUMERIC_CHECK);
?>