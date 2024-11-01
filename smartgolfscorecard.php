<?php
/*
Plugin Name: Smart Golf Scorecard for WordPress
Plugin URI: http://smartgolfscorecard.com/wordpress/
Description: This plugin enables you to host players scorecards for your golf course
Version: 1.1
Author: Jerry Shkavritko
Author URI: http://jerry.macans.com
License: GPL2
*/
?>
<?php
/*  Copyright 2011  Jerry Shkavritko  (email : jerry@macans.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
?>
<?php
// We need some CSS to position the paragraph
function sgs_wp_css() {
	// This makes sure that the positioning is also good for right-to-left languages
/*	
$x = is_rtl() ? 'left' : 'right';
#dolly {
        float: $x;
        padding-$x: 15px;
        padding-top: 5px;		
        margin: 0;
        font-size: 11px;
} */
    
	echo "
	<style type='text/css'>
	
        .sgs-leaderboard {
            
        }
	</style>
	";
}

add_action( 'admin_head', 'sgs_wp_css' );
    
$sgs_base_url = "http://api.smartgolfscorecard.com/a/";

// [smartgolfscorecard course_id="1"]
function sgs_wp_tag_func( $atts ) {
    global $sgs_base_url;
    extract( shortcode_atts( array(
            'course_id' => '1',
            'golfer_id' => '1',
    ), $atts ) );

    $sgs_client_guid = get_option('sgs_wp_client_guid');
    if ($sgs_client_guid == null) {
        $sgs_client_guid = sgs_register();        
        update_option( 'sgs_wp_client_guid', $sgs_client_guid );
    }

    $sgs_leaderboard_url = $sgs_base_url."leaderboard/";

    $page_result = @file_get_contents($sgs_leaderboard_url."?client_guid=".$sgs_client_guid."&course_id=".$course_id);
    if ($page_result === false) {
        $html .= "<p>No rounds available for course ".$course_id.".</p>";
    } else {
        $jsonStr = json_decode($page_result);
        $html = display_leaderboard($jsonStr);
    }
    $html .= '<p>If you would like to see your round on this list then make sure to have entered it in <a href="http://smartgolfscorecard.com">SmartGolfScorecard.com</a> (registration is free).</p>';
    return $html;   
}
add_shortcode( 'smartgolfscorecard', 'sgs_wp_tag_func' );

function display_leaderboard($rounds) { 
    if ($rounds[0]->Strokes == null) return "No rounds available for this course.";
    $i = 1;
    
    $positions = array();
    $current_strokes = $rounds[0]->Strokes;
    $positions[$i]->Count = 0;
    
    foreach ($rounds as $round) {
        if ($round->Strokes == $current_strokes) {
            $positions[$i]->Count ++;
        } else {
            $i ++;
            $positions[$i]->Count = 1;
        }
        if ($i == 1) $positions[$i]->Position = 1;
        else $positions[$i]->Position = $positions[$i-1]->Position + $positions[$i-1]->Count;
        
        $current_strokes = $round->Strokes;
    }
    
    $html = "<h3>Top 100 Rounds</h3>";
    $html .= '<table class="sgs-leaderboard">
        <tr><th>Position</th><th>Strokes</th><th>Golfer</th><th>Date</th></tr>';
    $i = 0;
    $j = 0;
    foreach ($rounds as $round) {
        if ($j == 0) $i ++;
        $pos_text = ($positions[$i]->Count > 1) ? "T".$positions[$i]->Position : $positions[$i]->Position;
        if ($j < $positions[$i]->Count - 1) $j ++;
        elseif ($j == $positions[$i]->Count - 1) $j = 0;
        $html .= '<tr><td>'.$pos_text.'</td><td>'.$round->Strokes.'</td><td>'.$round->PlayerName.'</td><td><a href="'.$round->URL.'">'.$round->Date.'</a></td></tr>';
    }    
    $html .= '</table>
        ';
    
    return $html;
}

function sgs_register() {
    global $sgs_base_url;
    $application_guid = "539bef18-2dbe-4924-933e-5fe5955f9ca7";
    $version =  "1.0.0";
    $timezone = "-5"; //todo: need to get this from WP
    $serial_number = $_SERVER["HTTP_HOST"];
    
    $sgs_register_url = $sgs_base_url."register/";
    
    $jsonStr = json_decode(file_get_contents($sgs_register_url."?application_guid=".$application_guid."&version=".$version."&timezone=".$timezone."&serial_number=".$serial_number));
    
    $client_guid = $jsonStr->client_guid;
    
    return $client_guid;
    
}


 // ------------------------------------------------------------------
 // Add all your sections, fields and settings during admin_init
 // ------------------------------------------------------------------
 //
 
 function sgs_wp_settings_api_init() {
 	// Add the section to reading settings so we can add our
 	// fields to it
 	add_settings_section('sgs_wp_setting_section',
		'SGS for WordPress settings',
		'sgs_wp_setting_section_callback_function',
		'general');
 	
 	// Add the field with the names and function to use for our new
 	// settings, put it in our new section
 	add_settings_field('sgs_wp_client_guid',
		'Client GUID',
		'eg_setting_callback_function',
		'general',
		'sgs_wp_setting_section');
 	
 	// Register our setting so that $_POST handling is done for us and
 	// our callback function just has to echo the <input>
 	//register_setting('reading','sgs_wp_client_guid');
 	register_setting('general','sgs_wp_client_guid');
 }// sgs_wp_settings_api_init()
 
 add_action('admin_init', 'sgs_wp_settings_api_init');
 
  
 // ------------------------------------------------------------------
 // Settings section callback function
 // ------------------------------------------------------------------
 //
 // This function is needed if we added a new section. This function 
 // will be run at the start of our section
 //
 
 function sgs_wp_setting_section_callback_function() {
 	echo '<p>Smart Golf Scorecard for WordPress</p>';
 }
 
 // ------------------------------------------------------------------
 // Callback function for our example setting
 // ------------------------------------------------------------------
 //
 // creates a checkbox true/false option. Other types are surely possible
 //
 
 function eg_setting_callback_function() {
 	echo '<input name="sgs_wp_client_guid" id="gv_thumbnails_insert_into_excerpt" type="text" class="regular-text code" value="' . get_option('sgs_wp_client_guid') . '" /> ';
 	//echo '<input name="sgs_wp_client_guid" id="gv_thumbnails_insert_into_excerpt" type="text" value="1" class="code" ' . checked( 1, get_option('sgs_wp_client_guid'), false ) . ' /> SGS for WP Client GUID';
 }
?>