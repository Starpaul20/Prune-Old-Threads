<?php
/**
 * Prune Old Threads
 * Copyright 2013 Starpaul20
 */

function task_threadpruning($task)
{
	global $db, $mybb, $lang, $cache;
	$lang->load("pruneoldthreads");

	// Find only the forums that have pruning enabled
	$query = $db->simple_select("forums", "fid, daysprune", "enablepruning = '1'");
	while($forums = $db->fetch_array($query))
	{
		$and = "";
		$sql_where = "";

		if(intval($forums['daysprune']) > 0)
		{
			$postdate = $forums['daysprune']*60*60*24;

			$sql_where .= "{$and}lastpost <= '".(TIME_NOW-$postdate)."'";
			$and = " AND ";
		}

		$sql_where .= "{$and}sticky = '0'";
		$and = " AND ";

		$sql_where .= "{$and}fid = '{$forums['fid']}'";

		$query2 = $db->simple_select("threads", "tid", $sql_where);
		while($thread = $db->fetch_array($query2))
		{
			require_once MYBB_ROOT."inc/class_moderation.php";
			$moderation = new Moderation();

			$moderation->delete_thread($thread['tid']);
		}
	}

	add_task_log($task, $lang->task_pruning_ran);
}
?>