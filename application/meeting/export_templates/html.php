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
	along with Congressus.  If not, see <http://www.gnu.org/licenses/>.
*/

function showMotion($motions, $id, &$voters) {
	$first = true;

	echo "<div class=\"motion\">\n";

	foreach($motions as $motion) {
		if ($motion["mot_id"] == $id) {
			if ($first) {
				echo "<div class=\"title\">" . $motion["mot_title"] . "</div>";
				echo "<div class=\"description\">" . $motion["mot_description"] . "</div>";
				$first = false;
			}

			$explanation = json_decode($motion["mpr_explanation"], true);

			echo "<div class=\"proposition " . ($motion["mpr_winning"] == 1 ? "winning" : "") . "\">" . $motion["mpr_label"] . "&nbsp;(" . $explanation["power"] . ") : ";

			$voteSeparator = " ";
			foreach($explanation["votes"] as $vote) {
				if ($vote["power"] == 0) continue;

				echo "$voteSeparator<span class=\"vote\">";
				echo $vote["memberLabel"];
				echo "&nbsp;(" . $vote["power"] . ")";
				echo "</span>";
				$voteSeparator = ", ";

				$voters[$vote["memberId"]] = $vote["memberLabel"];
			}

			echo "</div>\n";
		}
	}

	echo "</div>\n";
}

function showChat($chats, $id) {
	foreach($chats as $chat) {
		if ($chat["cha_id"] == $id) {
//					print_r($chat);

			echo "<p class=\"chat\">";

			if ($chat["cha_member_id"]) {
				if ($chat["pseudo_adh"]) {
					echo htmlspecialchars(utf8_encode($chat["pseudo_adh"]), ENT_SUBSTITUTE);
				}
				else {
					echo htmlspecialchars(utf8_encode($chat["nom_adh"]), ENT_SUBSTITUTE);
					echo " ";
					echo htmlspecialchars(utf8_encode($chat["prenom_adh"]), ENT_SUBSTITUTE);
				}
			}
			else {
				echo "Guest";
			}

			echo " : ";

			echo str_replace("\n", "<br>", $chat["cha_text"]) . "</p>\n";

			return;
		}
	}
}

function showConclusion($conclusions, $id) {
	foreach($conclusions as $conclusion) {
		if ($conclusion["con_id"] == $id) {
//			print_r($conclusion);

			echo "<p class=\"conclusion\">" . $conclusion["con_text"] . "</p>\n";

			return;
		}
	}
}

function showLevel($agendas, $level, $parent, &$voters) {
	foreach($agendas as $agenda) {
		if ($agenda["age_parent_id"] == $parent) {
			echo "<h$level>";

			echo $agenda["age_label"];

			echo "</h$level>\n";

			echo "<div class=\"indent\">";

			echo "<p class=\"description\">" . $agenda["age_description"] ."</p>\n";

//			print_r($agenda["age_objects"]);

			foreach($agenda["age_objects"] as $object) {
				if (isset($object["conclusionId"])) {
					showConclusion($agenda["conclusions"], $object["conclusionId"]);
				}
				else if (isset($object["chatId"])) {
					showChat($agenda["chats"], $object["chatId"]);
				}
				else if (isset($object["motionId"])) {
					showMotion($agenda["motions"], $object["motionId"], $voters);
				}
			}

			showLevel($agendas, $level + 1, $agenda["age_id"], $voters);

			echo "</div>";
		}
	}
}

?>
<html>
<head>
<title><?php echo $meeting["mee_label"]; ?></title>
<style>

.indent {
	padding-left: 20px;
}

.notice .powers {
	text-align: right;
}

.motion {
	border: 1px solid black;
	border-radius: 5px;
	padding: 5px;
	margin: 5px 0 5px 0;
}

.motion .title, .notice .label {
	font-size: larger;
}

.motion .description {
	font-style: italic;
}

.proposition {
}

.proposition.winning {
	color: green;
}

</style>
</head>
<body>

<h1><?php echo $meeting["mee_label"]; ?></h1>

<h2>Convoqués</h2>

<?php

foreach ($notices as $notice) {
	echo "<div class=\"indent notice\">";

	echo "<p class=\"label\">" . $notice["not_label"] . "</p>";

	$presentPowers = 0;
	$powers = 0;

	if (isset($notice["not_people"])) {
		echo "<p class=\"indent presents\">";

		$separator = " ";
		foreach($notice["not_people"] as $people) {

			if ($people["mem_voting"] == 1) {
				$powers += $people["mem_power"];
			}
			
			if ($people["mem_present"] != 1) continue;


			echo "$separator<span class=\"people\">";
			echo $people["mem_nickname"];

			if ($people["mem_voting"] == 1) {
				$presentPowers += $people["mem_power"];
				echo "&nbsp;(";
				echo $people["mem_power"];
				echo ")";
			}
			echo "</span>";

			$separator = ", ";
		}

		foreach($notice["not_children"] as $child_notice) {
			echo "<div class=\"indent notice\">";
		
			echo "<p class=\"label\">" . $child_notice["not_label"] . "</p>";
			
			$separator = " ";
				
			$child_presentPowers = 0;
			$child_powers = 0;

			if (isset($child_notice["not_people"])) {
				echo "<p class=\"indent presents\">";
					
				foreach($child_notice["not_people"] as $people) {
					
					if ($people["mem_voting"] == 1) {
						$powers += $people["mem_power"];
						$child_powers += $people["mem_power"];
					}
			
					if ($people["mem_present"] != 1) continue;
			
					echo "$separator<span class=\"people\">";
					echo $people["mem_nickname"];
			
					if ($people["mem_voting"] == 1) {
						$presentPowers += $people["mem_power"];
						$child_presentPowers += $people["mem_power"];
			
						echo " (";
						echo $people["mem_power"];
						echo ")";
					}
					echo "</span>";
	
					$separator = ", ";
				}

				echo "</p>";
				echo "<p class=\"powers\">";
				echo $child_presentPowers . "/" . $child_powers;
				echo "</p>";
			}

			echo "</div>";
		}
		
		echo "</p>";
		echo "<p class=\"powers\">";
		echo $presentPowers . "/" . $powers;
		echo "</p>";
	}

	echo "</div>";
}
?>

<h2>Absents</h2>

<?php

foreach ($notices as $notice) {
	echo "<div class=\"indent notice\">";

	echo "<p class=\"label\">" . $notice["not_label"] . "</p>";

	if (isset($notice["not_people"])) {
		echo "<p class=\"indent presents\">";

		$separator = " ";
		foreach($notice["not_people"] as $people) {

			if ($people["mem_present"] != 0) continue;

			echo "$separator<span class=\"people\">";
			echo $people["mem_nickname"];
			echo "</span>";

			$separator = ", ";
		}

		foreach($notice["not_children"] as $child_notice) {
			echo "<div class=\"indent notice\">";

			echo "<p class=\"label\">" . $child_notice["not_label"] . "</p>";

			$separator = " ";

			if (isset($child_notice["not_people"])) {
				echo "<p class=\"indent presents\">";
	
				foreach($child_notice["not_people"] as $people) {
					
					if ($people["mem_present"] != 0) continue;
			
					echo "$separator<span class=\"people\">";
					echo $people["mem_nickname"];
					echo "</span>";
	
					$separator = ", ";
				}
	
				echo "</p>";
			}

			echo "</div>";
		}
		
		echo "</p>";
	}

	echo "</div>";
}

$voters = array();
?>

<?php showLevel($agendas, 2, null , $voters); ?>

<?php
if (count($voters)) {
?>
<h2>Ayant participé à un vote</h2>
<div class="indent">
<?php 
	foreach($voters as $memberId => $memberLabel) {
?>
<p><span class="people"><?php echo $memberId . " - " . $memberLabel; ?></p>
<?php 
	}
?>
</div>
<?php 
}
?>

</body>
</html>