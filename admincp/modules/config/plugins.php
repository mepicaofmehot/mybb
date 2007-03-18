<?php
/**
 * MyBB 1.2
 * Copyright � 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/license.php
 *
 * $Id$
 */


$page->add_breadcrumb_item("Plugins", "index.php?".SID."&amp;module=config/plugins");

if($mybb->input['action'] == "check")
{		
	$plugins_list = get_plugins_list();
	
	$info = array();
	
	if($plugins_list)
	{
		foreach($plugins_list as $plugin_file)
		{
			require_once MYBB_ROOT."inc/plugins/".$plugin_file;
			$codename = str_replace(".php", "", $plugin_file);
			$infofunc = $codename."_info";
			if(!function_exists($infofunc))
			{
				continue;
			}
			$plugininfo = $infofunc();
			
			if($plugininfo['pid'])
			{
				$info[$plugininfo['pid']] = array('pid' => $plugininfo['pid'], 'ver' => $plugininfo['version']);
			}
		}
	}
	
	if(empty($info))
	{
		flash_message("There are no supported plugins available to check for updates.", 'error');
		admin_redirect("index.php?".SID."&module=config/plugins");
	}
		
	$contents = @implode("", @file("http://mods.mybboard.com/version_check.php?info=".urlencode(serialize($info))));
	if(!$contents)
	{
		flash_message("There was a problem communicating with the mod version server. Please try again in a few minutes.", 'error');
		admin_redirect("index.php?".SID."&module=config/plugins");
	}	
	elseif($contents == "error1")
	{
		flash_message("There was a problem communicating with the mod version server. Error code 1: No input specified.", 'error');
		admin_redirect("index.php?".SID."&module=config/plugins");
	}	
	elseif($contents == "error2")
	{
		flash_message("There was a problem communicating with the mod version server. Error code 2: No plugin ids specified.", 'error');
		admin_redirect("index.php?".SID."&module=config/plugins");
	}	
	elseif($contents == "error3")
	{
		flash_message("There was a problem communicating with the mod version server. Error code 3: Specified plugins not found.", 'error');
		admin_redirect("index.php?".SID."&module=config/plugins");
	}
	else if($contents == "success")
	{
		flash_message("Congratulations, all of your plugins are up to date.", 'error');
		admin_redirect("index.php?".SID."&module=config/plugins");
	}

	$page->add_breadcrumb_item("Plugin Updates");
	
	$page->output_header("Plugins Updates");
	
	$sub_tabs['update_plugins'] = array(
		'title' => "Plugin Updates",
		'link' => "index.php?".SID."&amp;module=config/plugin&amp;action=check",
		'description' => "This section allows you to check for updates on all your plugins."
	);
	
	$page->output_nav_tabs($sub_tabs, 'update_plugins');

	$table = new Table;
	$table->construct_header("Plugin");
	$table->construct_header("Your Version", array("class" => "align_center", 'width' => 125));
	$table->construct_header("Latest Version", array("class" => "align_center", 'width' => 125));
	$table->construct_header("Controls", array("class" => "align_center", 'width' => 125));

	$plugins = unserialize($contents);

	foreach($plugins as $pid => $plugin)
	{
		$table->construct_cell("<strong>{$plugininfo['name']}</strong>");
		$table->construct_cell("{$info[$pid]['ver']}", array("class" => "align_center"));
		$table->construct_cell("<strong><span style=\"color: #C00\">{$plugin}</span></strong>", array("class" => "align_center"));
		$table->construct_cell("<strong><a href=\"http://mods.mybboard.com/view.php?did={$pid}\" target=\"_blank\">Download</a></strong>", array("class" => "align_center"));
		$table->construct_row();
	}
	
	$table->output("Plugins Updates");
	
	$page->output_footer();
}

// Activates or deactivates a specific plugin
if($mybb->input['action'] == "activate" || $mybb->input['action'] == "deactivate")
{
	$codename = $mybb->input['plugin'];
	$codename = str_replace(array(".", "/", "\\"), "", $codename);
	$file = basename($codename.".php");
	if($mybb->input['action'] == "activate")
	{
		$active_plugins[$codename] = $codename;
		$userfunc = $codename."_activate";
		$message = "The plugin has been successfully activated.";
	}
	else if($mybb->input['action'] == "deactivate")
	{
		unset($active_plugins[$codename]);
		$userfunc = $codename."_deactivate";
		$message = "The plugin has been successfully deactivated.";
	}

	// Check if the file exists and throw an error if it doesn't
	if(!file_exists(MYBB_ROOT."inc/plugins/$file"))
	{
		flash_message('The specified plugin does not exist', 'error');
		admin_redirect("index.php?".SID."&module=config/plugins");
	}

	require_once MYBB_ROOT."inc/plugins/$file";

	// If this plugin has an activate/deactivate function then run it
	if(function_exists($userfunc))
	{
		$userfunc();
	}

	// Update plugin cache
	$plugins_cache['active'] = $active_plugins;
	$cache->update("plugins", $plugins_cache);

	flash_message($message, 'success');
	admin_redirect("index.php?".SID."&module=config/plugins");
}

if(!$mybb->input['action'])
{
	$page->output_header("Plugins");

	$sub_tabs['manage_plugins'] = array(
		'title' => "Manage Plugins",
		'link' => "index.php?".SID."&amp;module=config/plugins",
		'description' => "This section allows you to activate, deactivate, and manage the plugins that you have uploaded to your forum's <strong>inc/plugins</strong> directory."
	);
	$sub_tabs['update_plugins'] = array(
		'title' => "Plugin Updates",
		'link' => "index.php?".SID."&amp;module=config/plugins&amp;action=check",
		'description' => "This section allows you to check for updates on all your plugins."
	);
	
	$page->output_nav_tabs($sub_tabs, 'manage_plugins');
	
	$plugins_cache = $cache->read("plugins");
	$active_plugins = $plugins_cache['active'];
	
	$plugins_list = get_plugins_list();

	$table = new Table;
	$table->construct_header("Plugin");
	$table->construct_header("Controls", array("class" => "align_center"));
	
	if($plugins_list)
	{
		foreach($plugins_list as $plugin_file)
		{
			require_once MYBB_ROOT."inc/plugins/".$plugin_file;
			$codename = str_replace(".php", "", $plugin_file);
			$infofunc = $codename."_info";
			if(!function_exists($infofunc))
			{
				continue;
			}
			$plugininfo = $infofunc();
			if($plugininfo['website'])
			{
				$plugininfo['name'] = "<a href=\"".$plugininfo['website']."\">".$plugininfo['name']."</a>";
			}
			if($plugininfo['authorsite'])
			{
				$plugininfo['author'] = "<a href=\"".$plugininfo['authorsite']."\">".$plugininfo['author']."</a>";
			}
			if(isset($active_plugins[$codename]))
			{
				$pluginbuttons = "<a href=\"index.php?".SID."&amp;module=config/plugins&amp;action=deactivate&amp;plugin={$codename}\">Deactivate</a>";
			}
			else
			{
				$pluginbuttons = "<a href=\"index.php?".SID."&amp;module=config/plugins&amp;action=activate&amp;plugin={$codename}\">Activate</a>";
			}
			
			$table->construct_cell("<strong>{$plugininfo['name']}</strong> ({$plugininfo['version']})<br /><small>{$plugininfo['description']}</small><br /><i><small>Created by {$plugininfo['author']}</small></i>");
			$table->construct_cell($pluginbuttons, array("class" => "align_center"));
			$table->construct_row();
		}
	}
	else
	{
		$table->contruct_cell("There are no plugins on your forum at this time.", array('colspan' => 2));
		$table->construct_row();
	}
	$table->output("Plugins");

	$page->output_footer();
}

function get_plugins_list()
{
	// Get a list of the plugin files which exist in the plugins directory
	$dir = @opendir(MYBB_ROOT."inc/plugins/");
	if($dir)
	{
		while($file = readdir($dir))
		{
			$ext = get_extension($file);
			if($ext == "php")
			{
				$plugins_list[] = $file;
			}
		}
		@sort($plugins_list);
	}
	@closedir($dir);
	
	return $plugins_list;
}
?>