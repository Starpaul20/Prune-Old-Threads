<?php
/**
 * Prune Old Threads
 * Copyright 2013 Starpaul20
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// Tell MyBB when to run the hooks
$plugins->add_hook("admin_formcontainer_output_row", "pruneoldthreads_forum");
$plugins->add_hook("admin_forum_management_edit_commit", "pruneoldthreads_forum_commit");
$plugins->add_hook("admin_forum_management_add_commit", "pruneoldthreads_forum_commit");

// The information that shows up on the plugin manager
function pruneoldthreads_info()
{
	return array(
		"name"				=> "Prune Old Threads",
		"description"		=> "Allows you to prune old threads from specific forums.",
		"website"			=> "http://galaxiesrealm.com/index.php",
		"author"			=> "Starpaul20",
		"authorsite"		=> "http://galaxiesrealm.com/index.php",
		"version"			=> "1.0",
		"guid"				=> "",
		"compatibility"		=> "16*"
	);
}

// This function runs when the plugin is installed.
function pruneoldthreads_install()
{
	global $db, $cache;
	pruneoldthreads_uninstall();

	$db->add_column("forums", "enablepruning", "int(1) NOT NULL default '0'");
	$db->add_column("forums", "daysprune", "int(100) NOT NULL default '240'");

	$cache->update_forums();
}

// Checks to make sure plugin is installed
function pruneoldthreads_is_installed()
{
	global $db;
	if($db->field_exists("enablepruning", "forums"))
	{
		return true;
	}
	return false;
}

// This function runs when the plugin is uninstalled.
function pruneoldthreads_uninstall()
{
	global $db, $cache;
	if($db->field_exists('enablepruning', 'forums'))
	{
		$db->drop_column("forums", "enablepruning");
	}

	if($db->field_exists('daysprune', 'forums'))
	{
		$db->drop_column("forums", "daysprune");
	}

	$cache->update_forums();
}

// This function runs when the plugin is activated.
function pruneoldthreads_activate()
{
	global $db;

	// Inserts thread pruning task
	require_once MYBB_ROOT."inc/functions_task.php";
	$subscription_insert = array(
		"title"			=> "Thread Pruning",
		"description"	=> "Automatically prunes old threads based on criteria set in the Forum Managment section.",
		"file"			=> "threadpruning",
		"minute"		=> "0",
		"hour"			=> "0",
		"day"			=> "*",
		"month"			=> "*",
		"weekday"		=> "*",
		"enabled"		=> 1,
		"logging"		=> 1,
		"locked"		=> 0
	);

	$subscription_insert['nextrun'] = fetch_next_run($subscription_insert);
	$db->insert_query("tasks", $subscription_insert);
}

// This function runs when the plugin is deactivated.
function pruneoldthreads_deactivate()
{
	global $db;
	$query = $db->simple_select("tasks", "tid", "file='threadpruning'");
	$task = $db->fetch_array($query);

	$db->delete_query("tasks", "tid='{$task['tid']}'");
	$db->delete_query("tasklog", "tid='{$task['tid']}'");
}

// Adds pruning options to Edit Forum Settings page
function pruneoldthreads_forum($row)
{
	global $db, $mybb, $lang, $form_container, $forum_data, $form;
	$lang->load("pruneoldthreads");

	if($mybb->input['module'] == "forum-management" AND $mybb->input['action'] == 'edit' OR $mybb->input['action'] == 'add')
	{
		if($row['label_for'] == 'linkto')
		{
			$pruning_options = array(
			$form->generate_check_box('enablepruning', 1, $lang->enable_pruning."<br />\n<small>{$lang->enable_pruning_desc}</small>", array('checked' => $forum_data['enablepruning'], 'id' => 'enablepruning')),
			$lang->days_prune."<br />\n".$form->generate_text_box('daysprune', $forum_data['daysprune'], array('checked' => $forum_data['daysprune'], 'id' => 'daysprune', 'class' => 'field50')). $lang->days
			);

			$form_container->output_row($lang->pruning_options, "", "<div class=\"forum_settings_bit\">".implode("</div><div class=\"forum_settings_bit\">", $pruning_options)."</div>");
		}
	}

	return $row;
}

function pruneoldthreads_forum_commit()
{
	global $db, $mybb, $cache, $fid;
	$update_array = array(
		"enablepruning" => intval($mybb->input['enablepruning']),
		"daysprune" => intval($mybb->input['daysprune']),
	);

	$db->update_query("forums", $update_array, "fid='{$fid}'");

	$cache->update_forums();
}

?>