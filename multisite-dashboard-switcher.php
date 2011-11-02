<?php
/*
	Plugin Name: Multisite Dashboard Switcher
	Plugin URI: http://samjlevy.com/msds
	Description: Adds a menu to the Admin Bar for easy switching between Multisite dashboards.
	Version: 1.0
	Author: Sam J Levy
	Author URI: http://samjlevy.com/
*/
?><?php
/*  Copyright 2011  Sam J Levy  (email : sam@samjlevy.com)

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
?><?php
add_action('admin_bar_menu','msds',1000);
add_action('network_admin_menu','msds_init');
add_action('admin_post_update_my_settings','msds_options_save');
register_uninstall_hook(__FILE__,'msds_uninstall');

function msds_init() {
	add_submenu_page('settings.php','Multisite Dashboard Switcher Options','Multisite Dashboard Switcher','manage_network_options','msds-options','msds_options');
}

function msds_options() {
	$msds_group = get_site_option('msds_group');
	echo "<h2>Multisite Dashboard Switcher - Options</h2>";
	if(isset($_GET['updated'])) echo "<p style='color:green;font-weight:bold;'>Options updated</p>";
	echo "<form action='".admin_url('admin-post.php?action=update_my_settings')."' method='post'>";
	wp_nonce_field('msds_nonce');
	echo "<p><input type='checkbox' name='msds_group'".(($msds_group=="alpha") ? " checked='checked'" : "")." />&nbsp;&nbsp;Group sites by letter</p>";
	echo "<p><input type='submit' name='submit' value='Save Changes' class='button-primary' /></p>";
	echo "</form>";
}

function msds_options_save() {
	check_admin_referer('msds_nonce');
	if(!current_user_can('manage_network_options')) return;
	if(isset($_POST['msds_group'])) update_site_option('msds_group', 'alpha');
	else delete_site_option('msds_group');
	wp_redirect(admin_url('network/settings.php?page=msds-options&updated=true'));
	exit;
}

function msds_pages($type,$id,$url) {
	global $wp_admin_bar;
	if($type == "site") $pages = array('dashboard'=>'index.php','posts'=>'edit.php','media'=>'media.php','links'=>'link-manager.php','pages'=>'edit.php?post_type=page','comments'=>'edit-comments.php','appearance'=>'themes.php','plugins'=>'plugins.php','users'=>'users.php','tools'=>'tools.php','settings'=>'options-general.php');
	elseif($type == "network") $pages = array('dashboard'=>'index.php','sites'=>'sites.php','users'=>'users.php','themes'=>'themes.php','plugins'=>'plugins.php','settings'=>'settings.php','updates'=>'update-core.php');
	else return false;
	foreach($pages as $key=>$value) $wp_admin_bar->add_menu(array('parent'=>'msds_'.$id,'id' =>'msds_'.$id.'_'.$key,'title'=>ucfirst($key),'href'=>$url.'/'.$value));
}

function msds_loop($letter=false) {
	global $wp_admin_bar, $wpdb;

	// add letter menu
	if($letter) {
		$wp_admin_bar->add_menu(array('parent'=>'msds','id'=>'msds_'.$letter.'_letter','title'=>__($letter)));
		$site_parent = "msds_".$letter."_letter";
	} else $site_parent = "msds";

	// query sites
	$blogs = $wpdb->get_results("SELECT domain, path FROM $wpdb->blogs".(($letter) ? " WHERE UPPER(LEFT(REPLACE(path,'/',''), 1) = '$letter')" : "")." ORDER BY path",ARRAY_A);

	// add menu item for each site
	$i = 1;
	foreach($blogs as $b) {
		$url = 'http://'.$b['domain'].$b['path'].'wp-admin';
		$wp_admin_bar->add_menu(array('parent'=>$site_parent,'id'=>'msds_'.$letter.$i,'title'=>str_replace('/','',$b['path']),'href'=>$url));
		msds_pages('site',$letter.$i,$url);
		$i++;
	}
}

function msds() {
	if (!is_multisite() || !is_super_admin() || !is_admin_bar_showing()) return;
	global $wp_admin_bar, $wpdb, $current_blog;
	$domain = "http://".parse_url(get_bloginfo('url'),PHP_URL_HOST);

	// current site path
	if(isset($current_blog->path) && $current_blog->path != "") {
		if(is_network_admin()) {
			$temp = "Network";
		} else {
			if($current_blog->path != "/") $temp = str_replace('/','',$current_blog->path);
			else $temp = "Root Site";
		}
		$current = "<span style='margin-left:8px;padding:4px;background-color:yellow;color:#000;font-weight:bold;text-shadow:none;'>".$temp."</span>";
	}

	// add top menu
	$wp_admin_bar->add_menu(array('parent'=>false,'id'=>'msds','title'=>__('Multisite Switcher').$current));
	
	// add network menu
	$n_url = $domain."/wp-admin/network";
	$wp_admin_bar->add_menu(array('parent'=>'msds','id'=>'msds_network','title'=>__('Network'),'href'=>$n_url));
	msds_pages('network','network',$n_url);
	
	// add root site menu
	$r_url = $domain."/wp-admin";
	$wp_admin_bar->add_menu(array('parent'=>'msds','id'=>'msds_root','title'=>__('Root Site'),'href'=>$r_url));
	msds_pages('site','root',$r_url);

	if(get_site_option('msds_group')=="alpha") {
		// get alphabet
		$alpha = $wpdb->get_results("SELECT DISTINCT UPPER(LEFT(REPLACE(path,'/',''), 1)) AS first_letter FROM $wpdb->blogs WHERE blog_id != 1 ORDER BY first_letter",ARRAY_A);
		
		// call main loop for each letter
		foreach($alpha as $a) msds_loop($a['first_letter']);
	} else {
		msds_loop(); // otherwise call loop by itself
	}
}

function msds_uninstall() {
	delete_option('msds_group');
}
?>