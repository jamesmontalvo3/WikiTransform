<?php


// > > > > > > > > > > > > > > > > > > > > > > > > 
// ^                                             v
// < < < < < < < < < < < < < < < < < < < < < < < <

require_once 'WikiTransform.php';
$trans = new WikiTransform('Y:/EVA', 'https://mod2.jsc.nasa.gov/wiki/eva', __DIR__ );
$trans->user = "Oscar Rogers";
$trans->change_summary = "Merge synopsis and full text and change file handling due to change in meeting minutes form";
// $trans->is_dry_run = true;

$trans->getPageListFromCategory('Meeting Minutes', 5000);

$trans->addTransform(function($text){

	$new_text = preg_replace_callback (
		'/\{\{Topic from meeting[^\}\}]+\}\}/',
		function($matches){
			$meeting_topic = $matches[0];
			
			$full_text_pattern = '/\|Full text=/';
			$synopsis_pattern = '/\|Synopsis=/';
			$full_synopsis_pattern = '/\|Synopsis=[^\|]+/';
			
			$has_full_text = preg_match($full_text_pattern, $meeting_topic);
			$has_synopsis  = preg_match($synopsis_pattern, $meeting_topic);
			
			if ($has_full_text && $has_synopsis) {
				// get the text of the synopsis
				$synopsis = preg_match($full_synopsis_pattern, $meeting_topic, $syn_matches);
				$synopsis = $syn_matches[0];
				$synopsis = preg_replace($synopsis_pattern, '', $synopsis); // trim off the |Synopsis=
				
				// cut out the whole synopsis field
				$new_text = preg_replace($full_synopsis_pattern, '', $meeting_topic);

				// stick synopsis at front of full text
				$new_text = preg_replace($full_text_pattern, "|Full text=$synopsis\n", $new_text);
				
				return $new_text;
			}
			else if ($has_synopsis) { // implied doesn't have full text
				return preg_replace($synopsis_pattern, '|Full text=', $meeting_topic);
			}
			else { // may or may not have full text, doesn't have synopsis
				return $meeting_topic; // no change
			}
		},
		$text
	);

	return $new_text;
});


$files_for_meeting = '';
$trans->addTransform(function($text){
	global $files_for_meeting;
	$files_for_meeting = '|Meeting files=';

	// within this meeting minutes page, find all {{Local file related to meeting topic ... }}
	$new_text = preg_replace_callback (
		'/\{\{Local file related to meeting topic[^\}\}]+\}\}/',
		function($matches) use (&$files_for_meeting){
			$local_file_text = $matches[0];
			$local_file_text = str_replace('Local file related to meeting topic', 'Meeting Minutes/Files', $local_file_text);
			$local_file_text = str_replace('|Filename=', '|File or URL=File:', $local_file_text);
			global $files_for_meeting;
			$files_for_meeting .= $local_file_text;
			return ''; // remove this file template from meeting (for now)
		},
		$text
	);
	
	// within this meeting minutes page, find all {{External link related to meeting topic ... }}
	$new_text = preg_replace_callback (
		'/\{\{External link related to meeting topic[^\}\}]+\}\}/',
		function($matches) use (&$files_for_meeting){
			$local_file_text = $matches[0];
			$local_file_text = str_replace('External link related to meeting topic', 'Meeting Minutes/Files', $local_file_text);
			$local_file_text = str_replace('|URL=', '|File or URL=', $local_file_text);
			global $files_for_meeting;
			$files_for_meeting .= $local_file_text;
			return ''; // remove this file template from meeting (for now)
		},
		$new_text
	);
	
	global $files_for_meeting;
	$new_text = str_replace("|Uploaded files=\n",'',$new_text);
	$new_text = str_replace("|External links=\n",'',$new_text);
	$new_text = str_replace('{{Meeting minutes',"{{Meeting minutes\n$files_for_meeting",$new_text);

	return $new_text;
});

$trans->execute();