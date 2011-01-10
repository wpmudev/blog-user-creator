<?php
/*
Plugin Name: Blog And User Creator
Plugin URI: http://premium.wpmudev.org/project/blog-and-user-creator
Description: The blog and user creator plugin is an amazing powerful feature that allows you, and your users, to batch create gazillions of blogs and/or users while setting passwords, urls, titles and more!
Author: Andrew Billits, Ulrich Sossou
Version: 1.1.9
Network: true
Author URI: http://premium.wpmudev.org/project/
WDP ID: 80
*/

/*
Copyright 2007-2011 Incsub (http://incsub.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

/**
 * Plugin main class
 **/
class Blog_User_Creator {

	/**
	 * Current version of the plugin
	 **/
	var $version = '1.1.9';

	/**
	 * Status of blog types (enabled or disabled): either 1 or 0
	 **/
	var $enable_blog_types = '1';

	/**
	 * Default blog type
	 **/
	var $default_blog_type = 'student';

	/**
	 * Number of field sets to display
	 **/
	var $fields = '';

	/**
	 * PHP4 constructor
	 **/
	function Blog_User_Creator() {
		__construct();
	}

	/**
	 * PHP5 constructor
	 **/
	function __construct() {
		add_action( 'admin_menu', array( &$this, 'plug_pages' ) );
		add_action( 'admin_init', array( &$this, 'make_current' ) );

		// get number of field sets
		$this->fields = isset( $_GET['fields'] ) ? $_GET['fields'] : '';

		// default to 15 field sets
		if ( $this->fields == '' )
			$this->fields = 15;

		// no more than 50 fields sets
		if ( $this->fields > 50 )
			$this->fields = 50;

		// load text domain
		if ( defined( 'WPMU_PLUGIN_DIR' ) && file_exists( WPMU_PLUGIN_DIR . '/blog-user-creator.php' ) ) {
			load_muplugin_textdomain( 'blog_user_creator', 'blog-user-creator-files/languages' );
		} else {
			load_plugin_textdomain( 'blog_user_creator', false, dirname( plugin_basename( __FILE__ ) ) . '/blog-user-creator-files/languages' );
		}
	}

	/**
	 * Update database
	 **/
	function make_current() {
		global $plugin_page;

		if( 'blog-user-creator' !== $plugin_page )
			return;

		if ( get_site_option( 'blog_user_creator_version' ) == '' )
			add_site_option( 'blog_user_creator_version', '0.0.0' );

		if ( get_site_option( 'blog_user_creator_version' ) !== $this->version ) {
			update_site_option( 'blog_user_creator_version', $this->version );
			update_site_option( 'blog_user_creator_installed', 'no' );
		}

		$this->global_install();

		if ( get_option( 'blog_user_creator_version' ) == '' )
			add_option( 'blog_user_creator_version', $this->version );

		if ( get_option( 'blog_user_creator_version' ) !== $this->version )
			update_option( 'blog_user_creator_version', $this->version );
	}

	function global_install() {
		global $wpdb;

		if ( get_site_option( 'blog_user_creator_installed' ) == '' )
			add_site_option( 'blog_user_creator_installed', 'no' );

		if ( get_site_option( 'blog_user_creator_installed' ) !== 'yes' ) {

			if( @is_file( ABSPATH . '/wp-admin/includes/upgrade.php' ) )
				include_once( ABSPATH . '/wp-admin/includes/upgrade.php' );
			else
				die( __( 'We have problem finding your \'/wp-admin/upgrade-functions.php\' and \'/wp-admin/includes/upgrade.php\'', 'blog_user_creator' ) );

			$charset_collate = '';
			if( $wpdb->supports_collation() ) {
				if( !empty( $wpdb->charset ) ) {
					$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
				}
				if( !empty( $wpdb->collate ) ) {
					$charset_collate .= " COLLATE $wpdb->collate";
				}
			}

			$blog_user_creator_table = "CREATE TABLE `{$wpdb->base_prefix}blog_user_creator_queue` (
				`blog_user_creator_ID` bigint(20) unsigned NOT NULL auto_increment,
				`blog_user_creator_site_ID` bigint(20),
				`blog_user_creator_blog_ID` bigint(20),
				`blog_user_creator_batch_ID` varchar(255),
				`blog_user_creator_user_name` varchar(255),
				`blog_user_creator_user_pass` varchar(255),
				`blog_user_creator_user_email` varchar(255),
				`blog_user_creator_blog_name` varchar(255),
				`blog_user_creator_blog_title` varchar(255),
				`blog_user_creator_blog_types` TEXT,
				`blog_user_creator_add_admin` tinyint(1),
				`blog_user_creator_admin_uid` varchar(255),
				`blog_user_creator_stamp` bigint(20),
				`blog_user_creator_error` tinyint(1),
				`blog_user_creator_error_msg` varchar(255),
				PRIMARY KEY  (`blog_user_creator_ID`)
			) $charset_collate;";

			maybe_create_table( "{$wpdb->base_prefix}blog_user_creator_queue", $blog_user_creator_table );

			update_site_option( 'blog_user_creator_installed', 'yes' );

		}

	}

	function plug_pages() {
		add_submenu_page( 'users.php', __( 'Blog & User Creator', 'blog_user_creator' ), __( 'Blog & User Creator', 'blog_user_creator' ), 'manage_options', 'blog-user-creator', array( &$this, 'page_output' ) );
	}

	function queue_insert( $batch_ID, $stamp, $blog_name, $blog_title, $blog_types, $add_admin, $admin_uid, $user_name, $user_pass, $user_email ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->base_prefix}blog_user_creator_queue ( blog_user_creator_site_ID, blog_user_creator_blog_ID, blog_user_creator_batch_ID, blog_user_creator_user_name, blog_user_creator_user_pass, blog_user_creator_user_email, blog_user_creator_blog_name, blog_user_creator_blog_title, blog_user_creator_blog_types, blog_user_creator_add_admin, blog_user_creator_admin_uid, blog_user_creator_stamp ) VALUES ( %d, %d, %d, %s, %s, %s, %s, %s, %s, %d, %d, %s )", $wpdb->siteid, $wpdb->blogid, $batch_ID, $user_name, $user_pass, $user_email, $blog_name, $blog_title, $blog_types, $add_admin, $admin_uid, $stamp ) );
	}

	function queue_process( $tmp_blog_ID, $tmp_site_ID ) {
		global $wpdb, $current_site, $current_user, $base;

		$blog_user_creator_enable_blog_types = $this->enable_blog_types;

		if ( '' == $base )
			$base = '/';

		$query = "SELECT * FROM " . $wpdb->base_prefix . "blog_user_creator_queue WHERE blog_user_creator_site_ID = '" . $tmp_site_ID . "' AND blog_user_creator_blog_ID = '" . $tmp_blog_ID . "' LIMIT 1";
		$tmp_creator_items = $wpdb->get_results( $query, ARRAY_A );

		if (count($tmp_creator_items) > 0){
			foreach ($tmp_creator_items as $tmp_creator_item){

			if ($tmp_creator_item['blog_user_creator_blog_name'] == ''){
				$tmp_create_blog = 'no';
			} else {
				$tmp_create_blog = 'yes';
			}
			if ($tmp_create_blog == 'yes'){
				//setup domain / path
				$tmp_domain = strtolower( esc_html( $tmp_creator_item['blog_user_creator_blog_name'] ) );
				$tmp_blog_title = stripslashes($tmp_creator_item['blog_user_creator_blog_title']);
				if( constant( "VHOST" ) == 'yes' ) {
					$tmp_blog_domain = $tmp_domain.".".$current_site->domain;
					$tmp_blog_path = $base;
				} else {
					$tmp_blog_domain = $current_site->domain;
					$tmp_blog_path = $base.$tmp_domain.'/';
				}
			}
			//user bits
			$tmp_user_email = trim( esc_html( stripslashes( $tmp_creator_item['blog_user_creator_user_email'] ) ) );
			$tmp_user_email_count = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "users WHERE user_email = '" . $tmp_user_email . "'");
			if ($tmp_user_email_count > 0){
				//user already exists for email - use it!
				$user_id = $wpdb->get_var("SELECT ID FROM $wpdb->users WHERE user_email = '" . $tmp_user_email . "'");
				//no new password:
				$tmp_user_pass = '';
			} else {
				if ($tmp_creator_item['blog_user_creator_user_pass'] == '' || $tmp_creator_item['blog_user_creator_user_pass'] == strtolower('null')){
					$tmp_user_pass = wp_generate_password();
				} else {
					$tmp_user_pass = $tmp_creator_item['blog_user_creator_user_pass'];
				}
				$tmp_user_name = $tmp_creator_item['blog_user_creator_user_name'];
				$user_id = wpmu_create_user($tmp_user_name,$tmp_user_pass,$tmp_user_email);

				if( false !== $user_id )
					wp_new_user_notification( $user_id, $tmp_user_pass );
			}
			//blog bits
			$wpdb->hide_errors();
			if ($tmp_create_blog == 'yes'){
				//double check username
				if ($user_id == ''){
					$user_id = $wpdb->get_var("SELECT ID FROM " . $wpdb->users . " WHERE user_email = '" . trim( esc_html( stripslashes( $tmp_creator_item['blog_user_creator_user_email'] ) ) ) . "'" );
				}
				//double check password
				if ($tmp_user_pass == ''){
					$tmp_user_pass = '';
				}
				$blog_id = wpmu_create_blog( $tmp_blog_domain, $tmp_blog_path, esc_html( $tmp_blog_title ), $user_id, 1, $current_site->id );
				//$wpdb->show_errors();
				$wpdb->hide_errors();
				if( !is_wp_error($blog_id) ) {
					$content_mail = sprintf( __( 'New blog created by %1s\n\nAddress: http://%2s\nName: %3s', 'blog_user_creator' ), $current_user->user_login , $tmp_blog_domain.$tmp_blog_path, esc_html( $tmp_blog_title ) );
					@wp_mail( get_option('admin_email'),  sprintf(__( '[%s] New Blog Created', 'blog_user_creator' ), $current_site->site_name), $content_mail );
					//wp_redirect( add_query_arg( "updated", "blogadded", $_SERVER[ 'HTTP_REFERER' ] ) );

					if ($tmp_creator_item['blog_user_creator_add_admin'] == 1){
						add_user_to_blog($blog_id, $tmp_creator_item['blog_user_creator_admin_uid'], 'administrator');
					}
					if ( '1' == $blog_user_creator_enable_blog_types && !empty( $blog_types ) ){
						update_blog_option( $blog_id, 'blog_types', $tmp_creator_item['blog_user_creator_blog_types'] );
					}
					//send email
					wpmu_welcome_notification($blog_id, $user_id, $tmp_user_pass, esc_html( $tmp_blog_title ), '');
				} else {
					echo sprintf( __( 'Error creating blog: %1$s - %2$s', 'blog_user_creator' ), $tmp_add_blog_domain, $blog_id->get_error_message() ) . '<br />';
				}
			}
			//=====================================================================//
			//=====================================================================//
			$wpdb->query( "DELETE FROM " . $wpdb->base_prefix . "blog_user_creator_queue WHERE blog_user_creator_blog_ID = '" . $wpdb->blogid . "' AND blog_user_creator_site_ID = '" . $wpdb->siteid . "' AND blog_user_creator_ID = '" . $tmp_creator_item['blog_user_creator_ID'] . "'" );
			}
		}
	}

	function page_output() {
		global $wpdb, $wp_roles, $current_user, $user_ID, $current_site, $blog_types, $blog_types_selection, $blog_types_branding_singular, $blog_types_branding_plural, $base;

		$blog_user_creator_fields = $this->fields;
		$blog_user_creator_enable_blog_types = $this->enable_blog_types;
		$default_blog_type = $this->default_blog_type;

		if(!current_user_can('manage_options')) {
			?>
			<p><?php _e( 'You do not have permission to access this page', 'blog_user_creator' ) ?></p>
			<?php
			return;
		}

		if (isset($_GET['updated'])) {
			?><div id="message" class="updated fade"><p><?php echo urldecode( $_GET['updatedmsg'] ) ?></p></div><?php
		}
		echo '<div class="wrap">';
		$action = isset( $_GET[ 'action' ] ) ? $_GET[ 'action' ] : '';
		switch( $action ) {
			//---------------------------------------------------//
			default:
				if ( isset( $_GET['advanced'] ) && 'true' == $_GET['advanced'] ) {
				?>
				<h2><?php _e( 'Blog & User Creator', 'blog_user_creator' ) ?> (<a href="users.php?page=blog-user-creator"><?php _e( 'Simple', 'blog_user_creator' ) ?></a>)</h2>

				<p><?php _e( 'Fill out the details below to easily create multiple users and blogs and add yourself as admin to them (if you wish). <br /><br />You can create up to 15 different users and blogs at one time and new users will be emailed their usernames and passwords automatically. If you need more than 15 please simply process this form and then start again.<br /><br /> Please try to use unique usernames and blog URLs as this will make the process a lot quicker for you. An easy way to do this is to add numbers and letters after a regular name - for example \'James\' at \'Kings Heath School\' in year 10 could be \'jameskh10\'.', 'blog_user_creator' ) ?></p>
				<?php
				if ( isset( $_GET['fields'] ) && '' != $_GET['fields'] ) {
					?>
					<form name="form1" method="POST" action="users.php?page=blog-user-creator&action=advanced_process&advanced=true&fields=<?php echo $_GET['fields'];?>">
					<?php
				} else {
					?>
					<form name="form1" method="POST" action="users.php?page=blog-user-creator&action=advanced_process&advanced=true">
					<?php
				}
				?>
				<?php
					for ( $counter = 1; $counter <= $blog_user_creator_fields; $counter += 1) {
						?>
						<h3><?php echo $counter . ':' ?></h3>
							<table class="form-table">
							<tr valign="top">
							<th scope="row"><?php _e( 'User Name', 'blog_user_creator' ) ?></th>
							<td><input type="text" name="user_name_<?php echo $counter; ?>" id="user_name_<?php echo $counter; ?>" style="width: 95%" value="<?php echo isset( $_POST['user_name_' . $counter] ) ? $_POST['user_name_' . $counter] : ''; ?>" />
							<br />
							<?php _e( 'This will be the name used to log in, try to choose something that will be unique - e.g. jamesqt3', 'blog_user_creator' ) ?><br />
							<?php _e( 'For existing users, leave this field blank and just enter the user email below.', 'blog_user_creator' ) ?></td>
							</tr>
							<tr valign="top">
							<th scope="row"><?php _e( 'User Email', 'blog_user_creator' ) ?></th>
							<td><input type="text" name="user_email_<?php echo $counter; ?>" id="user_email_<?php echo $counter; ?>" style="width: 95%"  maxlength="200" value="<?php echo isset( $_POST['user_email_' . $counter]) ? $_POST['user_email_' . $counter] : ''; ?>" />
							<br />
							<?php _e( 'Required - if a user already exists with this email address, that user will be added to the blog you are creating.', 'blog_user_creator' ) ?></td>
							</tr>
							<tr valign="top">
							<th scope="row"><?php _e( 'User Password', 'blog_user_creator' ) ?></th>
							<td><input type="text" name="user_pass_<?php echo $counter; ?>" id="user_pass_<?php echo $counter; ?>" style="width: 95%"  maxlength="14" value="<?php echo isset( $_POST['user_pass_' . $counter] ) ? $_POST['user_pass_' . $counter] : ''; ?>" />
							<br />
							<?php _e( 'Leave this blank for a random password to be automatically generated and emailed to the user.', 'blog_user_creator' ) ?></td>
							</tr>
							<tr valign="top">
							<th scope="row"><?php _e( 'Blog Url', 'blog_user_creator' ) ?></th>
							<td><input type="text" name="blog_url_<?php echo $counter; ?>" id="blog_url_<?php echo $counter; ?>" style="width: 95%"  maxlength="50" value="<?php echo isset( $_POST['blog_url_' . $counter] ) ? $_POST['blog_url_' . $counter] : ''; ?>" />
							<br />
							<?php _e( 'You must fill this in for a blog to be created - try to choose something unique - e.g. myblog1aq will create http://myblog1aq.', 'blog_user_creator' ) ?><?php echo $current_site->domain . $current_site->path; ?></td>
							</tr>
							<tr valign="top">
							<th scope="row"><?php _e( 'Blog Title', 'blog_user_creator' ) ?></th>
							<td><input type="text" name="blog_title_<?php echo $counter; ?>" id="blog_title_<?php echo $counter; ?>" style="width: 95%"  maxlength="100" value="<?php echo isset( $_POST['blog_title_' . $counter] ) ? $_POST['blog_title_' . $counter] : ''; ?>" />
							<br />
							<?php _e( 'Required if you are creating a blog - this will appear as the title and can easily be changed later on - e.g. My School Blog', 'blog_user_creator' ) ?></td>
							</tr>
							<?php
							if ($blog_user_creator_enable_blog_types == '1' && $blog_types != ''){
								if (count($blog_types) > 1){
									if ($blog_types_selection == 'single' || $blog_types_selection == ''){
										?>
										<tr>
										<th scope="row" valign="top"><?php _e($blog_types_branding_singular) ?></th>
										<td><select name="blog_type_<?php echo $counter; ?>" id="blog_type_<?php echo $counter; ?>" style="width: 25%;">
											<?php
											foreach ($blog_types as $blog_type) {
												echo '<option value="' . $blog_type['nicename'] . '"'.(($default_blog_type == $blog_type['nicename']) ? ' selected="selected"' : '').'> ' . $blog_type['name'] . '</option>';
											}
											?>
										</select></td>
										</tr>
										<?php
									} else if ($blog_types_selection == 'multiple') {
										?>
										<tr>
										<th scope="row" valign="top"><?php _e($blog_types_branding_plural) ?></th>
										<td><select name="blog_types_<?php echo $counter; ?>[]" id="blog_types_<?php echo $counter; ?>[]" multiple="multiple" style="width: 25%;"  size="4">
											<?php
											foreach ($blog_types as $blog_type) {
												echo '<option value="' . $blog_type['nicename'] . '"'.(($default_blog_type == $blog_type['nicename']) ? ' selected="selected"' : '').'> ' . $blog_type['name'] . '</option>';
											}
											?>
										</select></td>
										</tr>
										<?php
									}
								} else {
									foreach ($blog_types as $blog_type) {
										?>
										<input type="hidden" name="blog_type_<?php echo $counter; ?>" value="<?php echo  $blog_type['nicename']; ?>"  />
										<?php
									}
								}
							}
							?>
							<tr valign="top">
							<th scope="row"><?php _e( 'Add Admin', 'blog_user_creator' ) ?></th>
							<td><select name="add_admin_<?php echo $counter; ?>" style="width: 25%;">
								<?php $add_admin = isset( $_POST['add_admin_' . $counter] ) ? $_POST['add_admin_' . $counter] : ''; ?>
								<option value="0" <?php selected( $add_admin, '0' ); ?>><?php _e( 'No', 'blog_user_creator' ) ?></option>
								<option value="1" <?php selected( $add_admin, '1' ); ?>><?php _e( 'Yes', 'blog_user_creator' ) ?></option>
								<?php unset( $add_admin ); ?>
							</select>
							<br />
							<?php _e( 'This will add you as a co-administrator of the blog so you can log into it and edit settings / make posts.', 'blog_user_creator' ) ?></td>
							</tr>
							</table>
						<?php
						//==================================================================================================================//
						//==================================================================================================================//
					}
				?>
				<p class="submit">
				<input type="submit" name="Submit" value="<?php _e( 'Create', 'blog_user_creator' ) ?>" />
				</p>
				<p style="text-align:right;"><?php _e( 'This may take some time so please be patient.', 'blog_user_creator' ) ?></p>
				</form>
				<?php
				} else {
				?>
				<h2><?php _e( 'Blog & User Creator', 'blog_user_creator' ) ?> (<a href="users.php?page=blog-user-creator&advanced=true"><?php _e( 'Advanced', 'blog_user_creator' ) ?></a>)</h2>

				<p><?php _e( 'Fill out the details below to easily create multiple users and blogs and add yourself as admin to them (if you wish). <br /><br />You can create up to 15 different users and blogs at one time and new users will be emailed their usernames and passwords automatically. If you need more than 15 please simply process this form and then start again.<br /><br /> Please try to use unique usernames and blog URLs as this will make the process a lot quicker for you. An easy way to do this is to add numbers and letters after a regular name - for example \'James\' at \'Kings Heath School\' in year 10 could be \'jameskh10\'.', 'blog_user_creator' ) ?></p>
				<?php
				if ( !empty( $_GET['fields'] ) ) {
					?>
					<form name="form1" method="POST" action="users.php?page=blog-user-creator&action=simple_process&fields=<?php echo $_GET['fields'];?>">
					<?php
				} else {
					?>
					<form name="form1" method="POST" action="users.php?page=blog-user-creator&action=simple_process">
					<?php
				}
				?>
				<p class="submit">
				<input type="submit" name="Submit" value="<?php _e( 'Create', 'blog_user_creator' ) ?>" />
				</p>
				<p style="text-align:right;"><?php _e( 'This may take some time so please be patient.', 'blog_user_creator' ) ?></p>
				<h3><?php _e( 'Common Options', 'blog_user_creator' ) ?></h3>
					<table class="form-table">
					<?php
					if ($blog_user_creator_enable_blog_types == '1' && $blog_types != ''){
						if (count($blog_types) > 1){
							if ($blog_types_selection == 'single' || $blog_types_selection == ''){
								?>
								<tr>
								<th scope="row" valign="top"><?php _e($blog_types_branding_singular) ?></th>
								<td><select name="blog_type" id="blog_type" style="width: 25%;">
									<?php
									//echo '<option value=""> ' . __('Please select a ') . $blog_types_branding_singular . '</option>';
									foreach ($blog_types as $blog_type) {
										echo '<option value="' . $blog_type['nicename'] . '"'.(($default_blog_type == $blog_type['nicename']) ? ' selected="selected"' : '').'> ' . $blog_type['name'] . '</option>';
									}
									?>
								</select></td>
								</tr>
								<?php
							} else if ($blog_types_selection == 'multiple') {
								?>
								<tr>
								<th scope="row" valign="top"><?php _e($blog_types_branding_plural) ?></th>
								<td><select name="blog_types[]" id="blog_types[]" multiple="multiple" style="width: 25%;"  size="4">
									<?php
									foreach ($blog_types as $blog_type) {
										echo '<option value="' . $blog_type['nicename'] . '"'.(($default_blog_type == $blog_type['nicename']) ? ' selected="selected"' : '').'> ' . $blog_type['name'] . '</option>';
									}
									?>
								</select></td>
								</tr>
								<?php
							}
						} else {
							foreach ($blog_types as $blog_type) {
								?>
								<input type="hidden" name="blog_type" value="<?php echo  $blog_type['nicename']; ?>"  />
								<?php
							}
						}
					}
					?>
					<tr valign="top">
					<th scope="row"><?php _e( 'Add Admin', 'blog_user_creator' ) ?></th>
					<td><select name="add_admin" style="width: 25%;">
						<?php $add_admin = isset( $_POST['add_admin'] ) ? $_POST['add_admin'] : ''; ?>
						<option value="0"<?php selected( $add_admin, '0' ); ?>><?php _e( 'No', 'blog_user_creator' ) ?></option>
						<option value="1"<?php selected( $add_admin, '1' ); ?>><?php _e( 'Yes', 'blog_user_creator' ) ?></option>
					</select>
					<br />
					<?php _e( 'This will add you as a co-administrator of the blog so you can log into it and edit settings / make posts.', 'blog_user_creator' ) ?></td>
					</tr>
					</table>
				<?php
					for ( $counter = 1; $counter <= $blog_user_creator_fields; $counter += 1) {
						?>
						<h3><?php _e($counter . ':') ?></h3>
							<table class="form-table">
							<tr valign="top">
							<th scope="row"><?php _e( 'Blog/User Name', 'blog_user_creator' ) ?></th>
							<td><input type="text" name="blog_user_name_<?php echo $counter; ?>" id="blog_user_name_<?php echo $counter; ?>" style="width: 95%" value="<?php echo isset( $_POST['blog_user_name_' . $counter] ) ? $_POST['blog_user_name_' . $counter] : ''; ?>" />
							<br />
							<?php _e( 'This will be the name used to log in and the name of the blog (ex: name.' . $current_site->domain . $current_site->path . '), try to choose something that will be unique - e.g. jamesqt3', 'blog_user_creator' ) ?><br />
							<?php _e( 'For existing users, leave this field blank and just enter the user email below.', 'blog_user_creator' ) ?></td>
							</tr>
							<tr valign="top">
							<th scope="row"><?php _e( 'User Email', 'blog_user_creator' ) ?></th>
							<td><input type="text" name="user_email_<?php echo $counter; ?>" id="user_email_<?php echo $counter; ?>" style="width: 95%"  maxlength="200" value="<?php echo isset( $_POST['user_email_' . $counter] ) ? $_POST['user_email_' . $counter] : ''; ?>" />
							<br />
							<?php _e( 'Required - if a user already exists with this email address, that user will be added to the blog you are creating.', 'blog_user_creator' ) ?></td>
							</tr>
							</table>
						<?php
					}
				?>
				<p class="submit">
				<input type="submit" name="Submit" value="<?php _e( 'Create', 'blog_user_creator' ) ?>" />
				</p>
				<p style="text-align:right;"><?php _e( 'This may take some time so please be patient.', 'blog_user_creator' ) ?></p>
				</form>

				<?php
				}
			break;
			//---------------------------------------------------//
			case "advanced_process":
				if ( isset($_POST['Cancel']) ) {
					echo "
					<SCRIPT LANGUAGE='JavaScript'>
					window.location='users.php?page=blog-user-creator&advanced=true';
					</script>
					";
				}
				$tmp_batch_ID = md5($wpdb->blogid . time() . '0420i203zm');
				$tmp_admin_uid = $user_ID;
				$tmp_stamp = time();
				$tmp_errors = '';
				$tmp_error_fields = '';
				$tmp_error_messages = '';
				$tmp_global_errors = 0;
				$tmp_creator_items = '';

				for ( $counter = 1; $counter <= $blog_user_creator_fields; $counter += 1) {
					$tmp_user_name = $_POST['user_name_' . $counter];
					$tmp_user_pass = $_POST['user_pass_' . $counter];
					$tmp_user_email = $_POST['user_email_' . $counter];
					$tmp_blog_name = $_POST['blog_url_' . $counter];
					//=============================================//
					$tmp_blog_name = str_replace($current_site->domain,'',$tmp_blog_name);
					$tmp_blog_name = str_replace($current_site->path,'',$tmp_blog_name);
					$tmp_blog_name = str_replace("http://",'',$tmp_blog_name);
					//============================================//
					$tmp_blog_title = $_POST['blog_title_' . $counter];
					$tmp_blog_title = stripslashes( $tmp_blog_title);
					//============================================//
					$tmp_blog_types = '';
					if (count($blog_types) > 1){
						if ($blog_types_selection == 'single' || $blog_types_selection == ''){
							$tmp_blog_types = '|' . $_POST['blog_type_' . $counter] . '|';
						} else if ($blog_types_selection == 'multiple') {
							$tmp_blog_types = '|' . join("|", $_POST['blog_types_' . $counter]) . '|';
						}
					} else {
						$tmp_blog_types = isset( $_POST['blog_type'] ) ? '|' . $_POST['blog_type'] . '|' : '';
					}
					//============================================//
					$tmp_add_admin = $_POST['add_admin_' . $counter];
					$tmp_error = 0;
					$tmp_error_field = '';
					$tmp_error_msg = '';

					if ($tmp_user_name == '' && $tmp_user_pass == '' && $tmp_user_email == '' && $tmp_blog_name == '' && $tmp_blog_title == ''){
						//nothing entered into any box so skip this one
					} else {
						//Check Blog
						//========================================//
						if ($tmp_blog_name != ''){

						preg_match( "/[a-z0-9]+/", $tmp_blog_name, $maybe );
						if( $tmp_blog_name != $maybe[0] ) {
							if ($tmp_error == ''){
								$tmp_error = 1;
								$tmp_error_field = 'blog_url';
								$tmp_error_msg = __( 'Only lowercase letters and numbers allowed', 'blog_user_creator' );
							}
						}

						$illegal_names = get_site_option( "illegal_names" );
						if( $illegal_names == false ) {
							$illegal_names = array( "www", "web", "root", "admin", "main", "invite", "administrator" );
							add_site_option( "illegal_names", $illegal_names );
						}
						if( in_array( $tmp_blog_name, $illegal_names ) == true ) {
							if ($tmp_error == ''){
								$tmp_error = 1;
								$tmp_error_field = 'blog_url';
								$tmp_error_msg = __( 'Sorry, that name is not allowed', 'blog_user_creator' );
							}
						}
						if( strlen( $tmp_blog_name ) < 4 && !is_super_admin() ) {
							if ($tmp_error == ''){
								$tmp_error = 1;
								$tmp_error_field = 'blog_url';
								$tmp_error_msg = __( 'Sorry, blog name must be at least 4 characters', 'blog_user_creator' );
							}
						}

						if ( strpos( ' ' . $tmp_blog_name, '_' ) != false ){
							if ($tmp_error == ''){
								$tmp_error = 1;
								$tmp_error_field = 'blog_url';
								$tmp_error_msg = __( 'Sorry, blog names may not contain the character \'_\'', 'blog_user_creator' );
							}
						}

						// all numeric?
						preg_match( '/[0-9]*/', $tmp_blog_name, $match );
						if ( $match[0] == $tmp_blog_name ){
							if ($tmp_error == ''){
								$tmp_error = 1;
								$tmp_error_field = 'blog_url';
								$tmp_error_msg = __( 'Sorry, blog names must have letters too', 'blog_user_creator' );
							}
						}

						// taken?
						if ($base == ''){
							$base = '/';
						}
						$tmp_domain = strtolower( esc_html( $tmp_blog_name ) );
						if( constant( "VHOST" ) == 'yes' ) {
							$tmp_blog_domain = $tmp_domain.".".$current_site->domain;
							$tmp_blog_path = $base;
						} else {
							$tmp_blog_domain = $current_site->domain;
							$tmp_blog_path = $base.$tmp_domain.'/';
						}
						$tmp_blog_count = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->blogs WHERE domain = '" . $tmp_blog_domain . "' AND path = '" . $tmp_blog_path . "'" );
						if ( $tmp_blog_count > 0 ){
							if ($tmp_error == ''){
								$tmp_error = 1;
								$tmp_error_field = 'blog_url';
								$tmp_error_msg = __( 'Sorry, that blog name already exists', 'blog_user_creator' );
							}
						}

						if (empty($tmp_blog_title))
							if ($tmp_error == ''){
								$tmp_error = 1;
								$tmp_error_field = 'blog_title';
								$tmp_error_msg = __( 'Please provide a blog title', 'blog_user_creator' );
							}
						}
						//========================================//

						//Check User / Email
						//========================================//
						//no username and no email
						if ($tmp_user_name == '' && $tmp_user_email == ''){
								if ($tmp_error == ''){
									$tmp_error = 1;
									$tmp_error_field = 'user_email';
									$tmp_error_msg = __( 'You must provide a valid email address', 'blog_user_creator' );
								}
						}
						//username but no email
						if ($tmp_user_name != '' && $tmp_user_email == ''){
								if ($tmp_error == ''){
									$tmp_error = 1;
									$tmp_error_field = 'user_email';
									$tmp_error_msg = __( 'You must provide a valid email address', 'blog_user_creator' );
								}
						}
						//check user name exists
						$tmp_user_count = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->users WHERE user_login = '" . $tmp_user_name . "'" );
						if ($tmp_user_count > 0){
							$tmp_user_email_count = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->users WHERE user_login = '" . $tmp_user_name . "' AND user_email = '" . $tmp_user_email . "'" );
							if ($tmp_user_email_count > 0){
								//they just entered the username as well
							} else {
								//user exists and emails don't match
								if ($tmp_error == ''){
									$tmp_error = 1;
									$tmp_error_field = 'user_name';
									$tmp_error_msg = __( 'Sorry, that username already exists', 'blog_user_creator' );
								}
							}
						}

						// Has someone already signed up for this username?
						$signup = $wpdb->get_row("SELECT * FROM $wpdb->signups WHERE user_login = '" . $tmp_user_name . "'");
						if ( $signup != null ) {
							$registered_at =  mysql2date('U', $signup->registered);
							$now = current_time( 'timestamp', true );
							$diff = $now - $registered_at;
							// If registered more than two days ago, cancel registration and let this signup go through.
							if ( $diff > 172800 ) {
								$wpdb->query("DELETE FROM $wpdb->signups WHERE user_login = '" . $tmp_user_name . "'");
							} else {
								if ($tmp_error == ''){
									$tmp_error = 1;
									$tmp_error_field = 'user_name';
									$tmp_error_msg = __( 'That username is currently reserved but may be available in a couple of days', 'blog_user_creator' );
								}
							}
						}
						preg_match( '/[a-z0-9]+/', $tmp_user_name, $maybe );
						if( $tmp_user_name != $maybe[0] ) {
							if ($tmp_error == ''){
								$tmp_error = 1;
								$tmp_error_field = 'user_name';
								$tmp_error_msg = __( 'Only lowercase letters and numbers allowed in usernames', 'blog_user_creator' );
							}
						}
						$tmp_email_count = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->users WHERE user_email = '" . $tmp_user_email . "'" );
						if ($tmp_email_count > 0){
							//bypass
						} else {
							//no username?
							if ( $tmp_user_name == '' ){
								if ($tmp_error == ''){
									$tmp_error = 1;
									$tmp_error_field = 'user_name';
									$tmp_error_msg = __( 'You must provide a username', 'blog_user_creator' );
								}
							}
							// all numeric?
							preg_match( '/[0-9]*/', $tmp_user_name, $match );
							if ( $match[0] == $tmp_user_name ){
								if ($tmp_error == ''){
									$tmp_error = 1;
									$tmp_error_field = 'user_name';
									$tmp_error_msg = __( 'Sorry, usernames must have letters too', 'blog_user_creator' );
								}
							}
							if( strlen( $tmp_user_name ) < 4 ) {
								if ($tmp_error == ''){
									$tmp_error = 1;
									$tmp_error_field = 'user_name';
									$tmp_error_msg = __( 'Username must be at least 4 characters', 'blog_user_creator' );
								}
							}
							if ( strpos( ' ' . $tmp_user_name, '_' ) != false ){
								if ($tmp_error == ''){
									$tmp_error = 1;
									$tmp_error_field = 'user_name';
									$tmp_error_msg = __( 'Sorry, usernames may not contain the character \'_\'', 'blog_user_creator' );
								}
							}

							$illegal_names = get_site_option( 'illegal_names' );
							if( is_array( $illegal_names ) == false ) {
								$illegal_names = array(  'www', 'web', 'root', 'admin', 'main', 'invite', 'administrator' );
								add_site_option( 'illegal_names', $illegal_names );
							}
							if( in_array( $tmp_user_name, $illegal_names ) == true ) {
								if ($tmp_error == ''){
									$tmp_error = 1;
									$tmp_error_field = 'user_name';
									$tmp_error_msg = __( 'That username is not allowed', 'blog_user_creator' );
								}
							}
						}
						$tmp_email_count = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->users WHERE user_email = '" . $tmp_user_email . "'" );
						if ($tmp_email_count > 0){
							//email already in system, let it through
						} else {
							if (is_email_address_unsafe($tmp_user_email)){
								if ($tmp_error == ''){
									$tmp_error = 1;
									$tmp_error_field = 'user_email';
									$tmp_error_msg = __( 'You cannot use that email address. We are having problems with them blocking some of our email. Please use another email provider.', 'blog_user_creator' );
								}
							}
							if ( !is_email( $tmp_user_email ) ) {
								if ($tmp_error == ''){
									$tmp_error = 1;
									$tmp_error_field = 'user_email';
									$tmp_error_msg = __( 'Please enter a correct email address', 'blog_user_creator' );
								}
							}
							$limited_email_domains = get_site_option( 'limited_email_domains' );
							if ( is_array( $limited_email_domains ) && empty( $limited_email_domains ) == false ) {
								$emaildomain = substr( $tmp_user_email, 1 + strpos( $tmp_user_email, '@' ) );
								if( in_array( $emaildomain, $limited_email_domains ) == false ) {
									if ($tmp_error == ''){
										$tmp_error = 1;
										$tmp_error_field = 'user_email';
										$tmp_error_msg = __( 'Sorry, that email address is not allowed', 'blog_user_creator' );
									}
								}
							}
						}
						//========================================//
						$tmp_creator_items[$counter]['user_name'] = $tmp_user_name;
						$tmp_creator_items[$counter]['user_email'] = $tmp_user_email;
						$tmp_creator_items[$counter]['user_pass'] = $tmp_user_pass;
						$tmp_creator_items[$counter]['blog_name'] = $tmp_blog_name;
						$tmp_creator_items[$counter]['blog_title'] = $tmp_blog_title;
						$tmp_creator_items[$counter]['blog_types'] = $tmp_blog_types;
						$tmp_creator_items[$counter]['add_admin'] = $tmp_add_admin;

						$tmp_errors[$counter] = $tmp_error;
						$tmp_error_fields[$counter] = $tmp_error_field;
						$tmp_error_messages[$counter] = $tmp_error_msg;
						if ($tmp_error == 1){
							$tmp_global_errors = $tmp_global_errors + 1;
						}
					}
				}
				if ($tmp_global_errors > 0){
					//========================================//
					//houston... we have error(s)
					?>
					<h2><?php _e('Blog & User Creator') ?></h2>

					<p><?php _e('Errors were found. Please fix the errors and hit Create.') ?></p>

					<?php
					if ($_GET['fields'] != ''){
						?>
						<form name="form1" method="POST" action="users.php?page=blog-user-creator&action=advanced_process&advanced=true&fields=<?php echo $_GET['fields'];?>">
						<?php
					} else {
						?>
						<form name="form1" method="POST" action="users.php?page=blog-user-creator&action=advanced_process&advanced=true">
						<?php
					}
					?>
					<?php
						for ( $counter = 1; $counter <= $blog_user_creator_fields; $counter += 1) {
							if ($counter == 1 || $counter == 6 || $counter == 11 || $counter == 16 || $counter == 21 || $counter == 26 || $counter == 31 || $counter == 36 || $counter == 41 || $counter == 46){
							?>
								<p class="submit">
								<input type="submit" name="Submit" value="<?php _e( 'Create', 'blog_user_creator' ) ?>" />
								<input type="submit" name="Cancel" value="<?php _e( 'Cancel', 'blog_user_creator' ) ?>" />
								</p>
								<p style="text-align:right;"><?php _e( 'This may take some time so please be patient.', 'blog_user_creator' ) ?></p>
							<?php
							}
							//==================================================================================================================//
							//==================================================================================================================//
							if ($tmp_errors[$counter] == 1){
								?>
								<h3 style="background-color:#F79696; padding:5px 5px 5px 5px;"><?php echo $counter . ': ' ?><?php echo $tmp_error_messages[$counter]; ?></h3>
								<?php
							} else {
								?>
								<h3><?php echo $counter . ':' ?></h3>
								<?php
							}
							?>
								<table class="form-table">
								<tr valign="top">
								<th scope="row"><?php _e( 'User Name', 'blog_user_creator' ) ?></th>
								<td><input type="text" name="user_name_<?php echo $counter; ?>" id="user_name_<?php echo $counter; ?>" style="width: 95%;<?php if ($tmp_error_fields[$counter] == 'user_name'){ echo ' background-color:#F79696;'; } ?>" value="<?php echo $_POST['user_name_' . $counter]; ?>" />
								<br />
								<?php _e( 'This will be the name used to log in, try to choose something that will be unique - e.g. jamesqt3', 'blog_user_creator' ) ?><br />
								<?php _e( 'For existing users, leave this field blank and just enter the user email below.', 'blog_user_creator' ) ?></td>
								</tr>
								<tr valign="top">
								<th scope="row"><?php _e( 'User Email', 'blog_user_creator' ) ?></th>
								<td><input type="text" name="user_email_<?php echo $counter; ?>" id="user_email_<?php echo $counter; ?>" style="width: 95%;<?php if ($tmp_error_fields[$counter] == 'user_email'){ echo ' background-color:#F79696;'; } ?>"  maxlength="200" value="<?php echo $_POST['user_email_' . $counter]; ?>" />
								<br />
								<?php _e( 'Required - if a user already exists with this email address, that user will be added to the blog you are creating.', 'blog_user_creator' ) ?></td>
								</tr>
								<tr valign="top">
								<th scope="row"><?php _e( 'User Password', 'blog_user_creator' ) ?></th>
								<td><input type="text" name="user_pass_<?php echo $counter; ?>" id="user_pass_<?php echo $counter; ?>" style="width: 95%;<?php if ($tmp_error_fields[$counter] == 'user_pass'){ echo ' background-color:#F79696;'; } ?>"  maxlength="14" value="<?php echo $_POST['user_pass_' . $counter]; ?>" />
								<br />
								<?php _e( 'Leave this blank for a random password to be automatically generated and emailed to the user.', 'blog_user_creator' ) ?></td>
								</tr>
								<tr valign="top">
								<th scope="row"><?php _e( 'Blog Url', 'blog_user_creator' ) ?></th>
								<td><input type="text" name="blog_url_<?php echo $counter; ?>" id="blog_url_<?php echo $counter; ?>" style="width: 95%;<?php if ($tmp_error_fields[$counter] == 'blog_url'){ echo ' background-color:#F79696;'; } ?>"  maxlength="50" value="<?php echo $_POST['blog_url_' . $counter]; ?>" />
								<br />
								<?php _e( 'You must fill this in for a blog to be created - try to choose something unique - e.g. myblog1aq will create http://myblog1aq.', 'blog_user_creator' ) ?><?php echo $current_site->domain . $current_site->path; ?></td>
								</tr>
								<tr valign="top">
								<th scope="row"><?php _e( 'Blog Title', 'blog_user_creator' ) ?></th>
								<td><input type="text" name="blog_title_<?php echo $counter; ?>" id="blog_title_<?php echo $counter; ?>" style="width: 95%;<?php if ($tmp_error_fields[$counter] == 'blog_title'){ echo ' background-color:#F79696;'; } ?>"  maxlength="100" value="<?php echo $_POST['blog_title_' . $counter]; ?>" />
								<br />
								<?php _e( 'Required if you are creating a blog - this will appear as the title and can easily be changed later on - e.g. My School Blog', 'blog_user_creator' ) ?></td>
								</tr>
								<?php
								if ($blog_user_creator_enable_blog_types == '1' && $blog_types != ''){
									if (count($blog_types) > 1){
										if ($blog_types_selection == 'single' || $blog_types_selection == ''){
											$tmp_blog_type = $_POST['blog_type_' . $counter];
											?>
											<tr>
											<th scope="row" valign="top"><?php $blog_types_branding_singular ?></th>
											<td><select name="blog_type_<?php echo $counter; ?>" id="blog_type_<?php echo $counter; ?>" style="width: 25%;">
												<?php
												foreach ($blog_types as $blog_type) {
													echo '<option value="' . $blog_type['nicename'] . '"'.(($tmp_blog_type == $blog_type['nicename']) ? ' selected="selected"' : '').'> ' . $blog_type['name'] . '</option>';
												}
												?>
											</select></td>
											</tr>
											<?php
										} else if ($blog_types_selection == 'multiple') {
											?>
											<tr>
											<th scope="row" valign="top"><?php _e($blog_types_branding_plural) ?></th>
											<td><select name="blog_types_<?php echo $counter; ?>[]" id="blog_types_<?php echo $counter; ?>[]" multiple="multiple" style="width: 25%;"  size="4">
												<?php
												$tmp_blog_types = '|' . join("|", $_POST['blog_types_' . $counter]) . '|';
												$tmp_blog_types = explode('|', $tmp_blog_types);
												foreach ($blog_types as $blog_type) {
													$tmp_found = '0';
													foreach ($tmp_blog_types as $tmp_blog_type) {
														if ($tmp_blog_type == $blog_type['nicename']){
															$tmp_found = '1';
														}
													}
													if ($tmp_found == '1'){
														echo '<option value="' . $blog_type['nicename'] . '"  selected="selected"> ' . $blog_type['name'] . '</option>';
													} else {
														echo '<option value="' . $blog_type['nicename'] . '"> ' . $blog_type['name'] . '</option>';
													}
												}
												?>
											</select></td>
											</tr>
											<?php
										}
									} else {
										foreach ($blog_types as $blog_type) {
											$tmp_blog_type = $_POST['blog_type_' . $counter];
											$tmp_blog_type = str_replace('|','',$tmp_blog_type);
											?>
											<input type="hidden" name="blog_type_<?php echo $counter; ?>" value="<?php echo $tmp_blog_type; ?>"  />
											<?php
										}
									}
								}
								?>
								<tr valign="top">
								<th scope="row"><?php _e( 'Add Admin', 'blog_user_creator' ) ?></th>
								<td><select name="add_admin_<?php echo $counter; ?>" style="width: 25%;">
									<option value="0" <?php if ($_POST['add_admin_' . $counter] == '0' || $_POST['add_admin_' . $counter] == '') echo 'selected="selected"'; ?>><?php _e( 'No', 'blog_user_creator' ) ?></option>
									<option value="1" <?php if ($_POST['add_admin_' . $counter] == '1') echo 'selected="selected"'; ?>><?php _e('Yes', 'blog_user_creator' ) ?></option>
								</select>
								<br />
								<?php _e( 'This will add you as a co-administrator of the blog so you can log into it and edit settings / make posts.', 'blog_user_creator' ) ?></td>
								</tr>
								</table>
							<?php
							//==================================================================================================================//
							//==================================================================================================================//
						}
					?>
					<p class="submit">
					<input type="submit" name="Submit" value="<?php _e( 'Create', 'blog_user_creator' ) ?>" />
					<input type="submit" name="Cancel" value="<?php _e( 'Cancel', 'blog_user_creator' ) ?>" />
					</p>
					<p style="text-align:right;"><?php _e( 'This may take some time so please be patient.', 'blog_user_creator' ) ?></p>
					</form>
					<?php
					//========================================//
				} else {
					//========================================//
					//Process
					echo '<p>' . __( 'Creatings blogs...', 'blog_user_creator' ) . '</p>';

					if( $tmp_creator_items ) {
						foreach ( $tmp_creator_items as $tmp_creator_item ) {
							$this->queue_insert( $tmp_batch_ID, $tmp_stamp, $tmp_creator_item['blog_name'], addslashes( $tmp_creator_item['blog_title'] ),$tmp_creator_item['blog_types'], $tmp_creator_item['add_admin'], $tmp_admin_uid, $tmp_creator_item['user_name'],$tmp_creator_item['user_pass'], addslashes( $tmp_creator_item['user_email'] ) );
						}
					}
					$tmp_queue_count = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "blog_user_creator_queue WHERE blog_user_creator_site_ID = '" . $wpdb->siteid . "' AND blog_user_creator_blog_ID = '" . $wpdb->blogid . "'" );
					if ($tmp_queue_count > 0){
						echo "
						<SCRIPT LANGUAGE='JavaScript'>
						window.location='users.php?page=blog-user-creator&advanced=true&action=process_queue';
						</script>
						";
					} else {
						echo "
						<SCRIPT LANGUAGE='JavaScript'>
						window.location='users.php?page=blog-user-creator&advanced=true';
						</script>
						";
					}

					//========================================//
				}
			break;
			//---------------------------------------------------//
			case "simple_process":
				if ( isset($_POST['Cancel']) ) {
					echo "
					<SCRIPT LANGUAGE='JavaScript'>
					window.location='users.php?page=blog-user-creator';
					</script>
					";
				}
				$tmp_batch_ID = 'sp' . md5($wpdb->blogid . time() . '0420i203zm');
				$tmp_admin_uid = $user_ID;
				$tmp_stamp = time();
				$tmp_errors = '';
				$tmp_error_fields = '';
				$tmp_error_messages = '';
				$tmp_global_errors = 0;
				$tmp_creator_items = '';

				for ( $counter = 1; $counter <= $blog_user_creator_fields; $counter += 1) {
					$tmp_user_name = $_POST['blog_user_name_' . $counter];
					$tmp_user_email = $_POST['user_email_' . $counter];
					$tmp_blog_name = $_POST['blog_user_name_' . $counter];
					//=============================================//
					$tmp_blog_name = str_replace($current_site->domain,'',$tmp_blog_name);
					$tmp_blog_name = str_replace($current_site->path,'',$tmp_blog_name);
					$tmp_blog_name = str_replace("http://",'',$tmp_blog_name);
					//============================================//
					$tmp_blog_title = sprintf( __( '%s \'s Blog', 'blog_user_creator' ), $tmp_blog_name );
					$tmp_blog_title = stripslashes( $tmp_blog_title);
					//============================================//
					$tmp_blog_types = '';
					if (count($blog_types) > 1){
						if ($blog_types_selection == 'single' || $blog_types_selection == ''){
							$tmp_blog_types = '|' . $_POST['blog_type'] . '|';
						} else if ($blog_types_selection == 'multiple') {
							$tmp_blog_types = '|' . join("|", $_POST['blog_types']) . '|';
						}
					} else {
						$tmp_blog_types = isset( $_POST['blog_type'] ) ? '|' . $_POST['blog_type'] . '|' : '';
					}
					//============================================//
					$tmp_add_admin = $_POST['add_admin'];
					$tmp_error = 0;
					$tmp_error_field = '';
					$tmp_error_msg = '';

					if ( !( $tmp_user_name == '' && $tmp_user_email == '' ) ) {
						preg_match( "/[a-z0-9]+/", $tmp_blog_name, $maybe );
						if( isset( $maybe[0] ) && $tmp_blog_name != $maybe[0] ) {
							if ($tmp_error == ''){
								$tmp_error = 1;
								$tmp_error_field = 'blog_user_name';
								$tmp_error_msg = __( 'Only lowercase letters and numbers allowed', 'blog_user_creator' );
							}
						}

						$illegal_names = get_site_option( 'illegal_names' );
						if( $illegal_names == false ) {
							$illegal_names = array( 'www', 'web', 'root', 'admin', 'main', 'invite', 'administrator' );
							add_site_option( 'illegal_names', $illegal_names );
						}
						if( in_array( $tmp_blog_name, $illegal_names ) == true ) {
							if ($tmp_error == ''){
								$tmp_error = 1;
								$tmp_error_field = 'blog_user_name';
								$tmp_error_msg = __( 'Sorry, that name is not allowed', 'blog_user_creator' );
							}
						}
						if( strlen( $tmp_blog_name ) < 4 && !is_super_admin() ) {
							if ($tmp_error == ''){
								$tmp_error = 1;
								$tmp_error_field = 'blog_user_name';
								$tmp_error_msg = __( 'Sorry, blog/user names must be at least 4 characters', 'blog_user_creator' );
							}
						}

						if ( strpos( ' ' . $tmp_blog_name, '_' ) != false ){
							if ($tmp_error == ''){
								$tmp_error = 1;
								$tmp_error_field = 'blog_user_name';
								$tmp_error_msg = __( 'Sorry, blog/user names may not contain the character \'_\'', 'blog_user_creator' );
							}
						}

						// all numeric?
						preg_match( '/[0-9]*/', $tmp_blog_name, $match );
						if ( $match[0] == $tmp_blog_name ){
							if ($tmp_error == ''){
								$tmp_error = 1;
								$tmp_error_field = 'blog_user_name';
								$tmp_error_msg = __( 'Sorry, blog/user names must have letters too', 'blog_user_creator' );
							}
						}

						// taken?
						if ($base == ''){
							$base = '/';
						}
						$tmp_domain = strtolower( esc_html( $tmp_blog_name ) );
						if( constant( 'VHOST' ) == 'yes' ) {
							$tmp_blog_domain = $tmp_domain.'.'.$current_site->domain;
							$tmp_blog_path = $base;
						} else {
							$tmp_blog_domain = $current_site->domain;
							$tmp_blog_path = $base.$tmp_domain.'/';
						}
						$tmp_blog_count = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->blogs WHERE domain = '" . $tmp_blog_domain . "' AND path = '" . $tmp_blog_path . "'" );
						if ( $tmp_blog_count > 0 ){
							if ($tmp_error == ''){
								$tmp_error = 1;
								$tmp_error_field = 'blog_user_name';
								$tmp_error_msg = __( 'Sorry, that blog/user name already exists', 'blog_user_creator' );
							}
						}

						//no username and no email
						if ($tmp_user_name == '' && $tmp_user_email == ''){
								if ($tmp_error == ''){
									$tmp_error = 1;
									$tmp_error_field = 'user_email';
									$tmp_error_msg = __( 'You must provide a valid email address', 'blog_user_creator' );
								}
						}
						//username but no email
						if ($tmp_user_name != '' && $tmp_user_email == ''){
								if ($tmp_error == ''){
									$tmp_error = 1;
									$tmp_error_field = 'user_email';
									$tmp_error_msg = __( 'You must provide a valid email address', 'blog_user_creator' );
								}
						}
						//check user name exists
						$tmp_user_count = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->users WHERE user_login = '" . $tmp_user_name . "'" );
						if ($tmp_user_count > 0){
							$tmp_user_email_count = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->users WHERE user_login = '" . $tmp_user_name . "' AND user_email = '" . $tmp_user_email . "'" );
							if ($tmp_user_email_count > 0){
								//they just entered the username as well
							} else {
								//user exists and emails don't match
								if ($tmp_error == ''){
									$tmp_error = 1;
									$tmp_error_field = 'blog_user_name';
									$tmp_error_msg = __( 'Sorry, that blog/user name already exists', 'blog_user_creator' );
								}
							}
						}

						// Has someone already signed up for this username?
						$signup = $wpdb->get_row("SELECT * FROM $wpdb->signups WHERE user_login = '" . $tmp_user_name . "'");
						if ( $signup != null ) {
							$registered_at =  mysql2date('U', $signup->registered);
							$now = current_time( 'timestamp', true );
							$diff = $now - $registered_at;
							// If registered more than two days ago, cancel registration and let this signup go through.
							if ( $diff > 172800 ) {
								$wpdb->query("DELETE FROM $wpdb->signups WHERE user_login = '" . $tmp_user_name . "'");
							} else {
								if ($tmp_error == ''){
									$tmp_error = 1;
									$tmp_error_field = 'blog_user_name';
									$tmp_error_msg = __( 'That blog/user name is currently reserved but may be available in a couple of days', 'blog_user_creator' );
								}
							}
						}

						preg_match( '/[a-z0-9]+/', $tmp_user_name, $maybe );
						if( isset( $maybe[0] ) && $tmp_user_name != $maybe[0] ) {
							if ($tmp_error == ''){
								$tmp_error = 1;
								$tmp_error_field = 'blog_user_name';
								$tmp_error_msg = __( 'Only lowercase letters and numbers allowed in usernames', 'blog_user_creator' );
							}
						}
						$tmp_email_count = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->users WHERE user_email = '" . $tmp_user_email . "'" );
						if ($tmp_email_count > 0){
							//bypass
						} else {
							//no username?
							if ( $tmp_user_name == '' ){
								if ($tmp_error == ''){
									$tmp_error = 1;
									$tmp_error_field = 'blog_user_name';
									$tmp_error_msg = __( 'You must provide a blog/user name', 'blog_user_creator' );
								}
							}
							// all numeric?
							preg_match( '/[0-9]*/', $tmp_user_name, $match );
							if ( $match[0] == $tmp_user_name ){
								if ($tmp_error == ''){
									$tmp_error = 1;
									$tmp_error_field = 'blog_user_name';
									$tmp_error_msg = __( 'Sorry, blog/user names must have letters too', 'blog_user_creator' );
								}
							}
							if( strlen( $tmp_user_name ) < 4 ) {
								if ($tmp_error == ''){
									$tmp_error = 1;
									$tmp_error_field = 'blog_user_name';
									$tmp_error_msg = __( 'Sorry, blog/user names must be at least 4 characters', 'blog_user_creator' );
								}
							}
							if ( strpos( ' ' . $tmp_user_name, '_' ) != false ){
								if ($tmp_error == ''){
									$tmp_error = 1;
									$tmp_error_field = 'blog_user_name';
									$tmp_error_msg = __( 'Sorry, blog/user names may not contain the character \'_\'', 'blog_user_creator' );
								}
							}

							$illegal_names = get_site_option( 'illegal_names' );
							if( is_array( $illegal_names ) == false ) {
								$illegal_names = array(  'www', 'web', 'root', 'admin', 'main', 'invite', 'administrator' );
								add_site_option( 'illegal_names', $illegal_names );
							}
							if( in_array( $tmp_user_name, $illegal_names ) == true ) {
								if ($tmp_error == ''){
									$tmp_error = 1;
									$tmp_error_field = 'blog_user_name';
									$tmp_error_msg = __( 'Sorry, that blog/user name is not allowed', 'blog_user_creator' );
								}
							}
						}
						$tmp_email_count = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->users WHERE user_email = '" . $tmp_user_email . "'" );
						if ($tmp_email_count > 0){
							//email already in system, let it through
						} else {
							if (is_email_address_unsafe($tmp_user_email)){
								if ($tmp_error == ''){
									$tmp_error = 1;
									$tmp_error_field = 'user_email';
									$tmp_error_msg = __( 'You cannot use that email address. We are having problems with them blocking some of our email. Please use another email provider.', 'blog_user_creator' );
								}
							}
							if ( !is_email( $tmp_user_email ) ) {
								if ($tmp_error == ''){
									$tmp_error = 1;
									$tmp_error_field = 'user_email';
									$tmp_error_msg = __( 'Please enter a correct email address', 'blog_user_creator' );
								}
							}
							$limited_email_domains = get_site_option( 'limited_email_domains' );
							if ( is_array( $limited_email_domains ) && empty( $limited_email_domains ) == false ) {
								$emaildomain = substr( $tmp_user_email, 1 + strpos( $tmp_user_email, '@' ) );
								if( in_array( $emaildomain, $limited_email_domains ) == false ) {
									if ($tmp_error == ''){
										$tmp_error = 1;
										$tmp_error_field = 'user_email';
										$tmp_error_msg = __( 'Sorry, that email address is not allowed', 'blog_user_creator' );
									}
								}
							}
						}
						//========================================//
						$tmp_creator_items[$counter]['user_name'] = $tmp_user_name;
						$tmp_creator_items[$counter]['user_email'] = $tmp_user_email;
						$tmp_creator_items[$counter]['user_pass'] = isset( $tmp_user_pass ) ? $tmp_user_pass : '';
						$tmp_creator_items[$counter]['blog_name'] = $tmp_blog_name;
						$tmp_creator_items[$counter]['blog_title'] = $tmp_blog_title;
						$tmp_creator_items[$counter]['blog_types'] = $tmp_blog_types;
						$tmp_creator_items[$counter]['add_admin'] = $tmp_add_admin;

						$tmp_errors[$counter] = $tmp_error;
						$tmp_error_fields[$counter] = $tmp_error_field;
						$tmp_error_messages[$counter] = $tmp_error_msg;
						if ($tmp_error == 1){
							$tmp_global_errors = $tmp_global_errors + 1;
						}
					}
				}
				if ( $tmp_global_errors > 0 ) {
					?>
					<h2><?php _e( 'Blog & User Creator', 'blog_user_creator' ) ?></h2>

					<p><?php _e( 'Errors were found. Please fix the errors and hit Create.', 'blog_user_creator' ) ?></p>

					<?php
					if ( !empty( $_GET['fields'] ) ){
						?>
						<form name="form1" method="POST" action="users.php?page=blog-user-creator&action=simple_process&fields=<?php echo $_GET['fields'];?>">
						<?php
					} else {
						?>
						<form name="form1" method="POST" action="users.php?page=blog-user-creator&action=simple_process">
						<?php
					}
					?>
					<h3><?php _e( 'Common Options', 'blog_user_creator' ) ?></h3>
						<table class="form-table">
						<?php
						if ($blog_user_creator_enable_blog_types == '1' && $blog_types != ''){
							if (count($blog_types) > 1){
								if ($blog_types_selection == 'single' || $blog_types_selection == ''){
									?>
									<tr>
									<th scope="row" valign="top"><?php echo $blog_types_branding_singular ?></th>
									<td><select name="blog_type" id="blog_type" style="width: 25%;">
										<?php
										foreach ($blog_types as $blog_type) {
											echo '<option value="' . $blog_type['nicename'] . '"'.(($default_blog_type == $blog_type['nicename']) ? ' selected="selected"' : '').'> ' . $blog_type['name'] . '</option>';
										}
										?>
									</select></td>
									</tr>
									<?php
								} else if ($blog_types_selection == 'multiple') {
									?>
									<tr>
									<th scope="row" valign="top"><?php _e($blog_types_branding_plural) ?></th>
									<td><select name="blog_types[]" id="blog_types[]" multiple="multiple" style="width: 25%;"  size="4">
										<?php
										foreach ($blog_types as $blog_type) {
											echo '<option value="' . $blog_type['nicename'] . '"'.(($default_blog_type == $blog_type['nicename']) ? ' selected="selected"' : '').'> ' . $blog_type['name'] . '</option>';
										}
										?>
									</select></td>
									</tr>
									<?php
								}
							} else {
								foreach ($blog_types as $blog_type) {
									?>
									<input type="hidden" name="blog_type" value="<?php echo  $blog_type['nicename']; ?>"  />
									<?php
								}
							}
						}
						?>
						<tr valign="top">
						<th scope="row"><?php _e( 'Add Admin', 'blog_user_creator' ) ?></th>
						<td><select name="add_admin" style="width: 25%;">
							<option value="0" <?php if ($_POST['add_admin'] == '0' || $_POST['add_admin'] == '') echo 'selected="selected"'; ?>><?php _e('No') ?></option>
							<option value="1" <?php if ($_POST['add_admin'] == '1') echo 'selected="selected"'; ?>><?php _e('Yes') ?></option>
						</select>
						<br />
						<?php _e( 'This will add you as a co-administrator of the blog so you can log into it and edit settings / make posts.', 'blog_user_creator' ) ?></td>
						</tr>
						</table>
					<?php
						for ( $counter = 1; $counter <= $blog_user_creator_fields; $counter += 1) {
							if ($counter == 1 || $counter == 6 || $counter == 11 || $counter == 16 || $counter == 21 || $counter == 26 || $counter == 31 || $counter == 36 || $counter == 41 || $counter == 46){
							?>
								<p class="submit">
								<input type="submit" name="Submit" value="<?php _e( 'Create', 'blog_user_creator' ) ?>" />
								<input type="submit" name="Cancel" value="<?php _e( 'Cancel', 'blog_user_creator' ) ?>" />
								</p>
								<p style="text-align:right;"><?php _e( 'This may take some time so please be patient.', 'blog_user_creator' ) ?></p>
							<?php
							}
							//==================================================================================================================//
							//==================================================================================================================//
							if ( !empty( $tmp_errors[$counter] ) ) {
								?>
								<h3 style="background-color:#F79696; padding:5px 5px 5px 5px;"><?php echo $counter . ': ' ?><?php echo $tmp_error_messages[$counter]; ?></h3>
								<?php
							} else {
								?>
								<h3><?php echo $counter . ':' ?></h3>
								<?php
							}
							?>
								<table class="form-table">
								<tr valign="top">
								<th scope="row"><?php _e( 'Blog/User Name', 'blog_user_creator' ) ?></th>
								<td><input type="text" name="blog_user_name_<?php echo $counter; ?>" id="blog_user_name_<?php echo $counter; ?>" style="width: 95%;<?php if ($tmp_error_fields[$counter] == 'blog_user_name'){ echo ' background-color:#F79696;'; } ?>" value="<?php echo $_POST['blog_user_name_' . $counter]; ?>" />
								<br />
								<?php printf( __( 'This will be the name used to log in and the name of the blog (ex: name.%1$s%2$s), try to choose something that will be unique - e.g. jamesqt3', 'blog_user_creator' ), $current_site->domain, $current_site->path ) ?><br />
								<?php _e( 'For existing users, leave this field blank and just enter the user email below.', 'blog_user_creator' ) ?></td>
								</tr>
								<tr valign="top">
								<th scope="row"><?php _e( 'User Email', 'blog_user_creator' ) ?></th>
								<td><input type="text" name="user_email_<?php echo $counter; ?>" id="user_email_<?php echo $counter; ?>" style="width: 95%;<?php if ($tmp_error_fields[$counter] == 'user_email'){ echo ' background-color:#F79696;'; } ?>"  maxlength="200" value="<?php echo $_POST['user_email_' . $counter]; ?>" />
								<br />
								<?php _e( 'Required - if a user already exists with this email address, that user will be added to the blog you are creating.', 'blog_user_creator' ) ?></td>
								</tr>
								</table>
							<?php
							//==================================================================================================================//
							//==================================================================================================================//
						}
					?>
					<p class="submit">
					<input type="submit" name="Submit" value="<?php _e( 'Create', 'blog_user_creator' ) ?>" />
					<input type="submit" name="Cancel" value="<?php _e( 'Cancel', 'blog_user_creator' ) ?>" />
					</p>
					<p style="text-align:right;"><?php _e( 'This may take some time so please be patient.', 'blog_user_creator' ) ?></p>
					</form>
					<?php
					//========================================//
				} else {
					//========================================//
					//Process
					echo '<p>' . __( 'Creatings blogs...', 'blog_user_creator' ) . '</p>';

					if( $tmp_creator_items ) {
						foreach ( $tmp_creator_items as $tmp_creator_item ) {
							$this->queue_insert( $tmp_batch_ID, $tmp_stamp, $tmp_creator_item['blog_name'], addslashes( $tmp_creator_item['blog_title'] ),$tmp_creator_item['blog_types'], $tmp_creator_item['add_admin'], $tmp_admin_uid, $tmp_creator_item['user_name'],$tmp_creator_item['user_pass'], addslashes( $tmp_creator_item['user_email'] ) );
						}
					}
					$tmp_queue_count = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "blog_user_creator_queue WHERE blog_user_creator_site_ID = '" . $wpdb->siteid . "' AND blog_user_creator_blog_ID = '" . $wpdb->blogid . "'" );
					if ($tmp_queue_count > 0){
						echo "
						<SCRIPT LANGUAGE='JavaScript'>
						window.location='users.php?page=blog-user-creator&action=process_queue';
						</script>
						";
					} else {
						echo "
						<SCRIPT LANGUAGE='JavaScript'>
						window.location='users.php?page=blog-user-creator';
						</script>
						";
					}

					//========================================//
				}
			break;
			//---------------------------------------------------//
			case "process_queue":
				echo '<p>' . __( 'Creatings blogs...', 'blog_user_creator' ) . '</p>';
				$tmp_queue_count = $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->base_prefix . "blog_user_creator_queue WHERE blog_user_creator_site_ID = '" . $wpdb->siteid . "' AND blog_user_creator_blog_ID = '" . $wpdb->blogid . "'" );
				$this->queue_process( $wpdb->blogid, $wpdb->siteid );

				if ($tmp_queue_count > 0){
					echo "
					<SCRIPT LANGUAGE='JavaScript'>
					window.location='users.php?page=blog-user-creator&action=process_queue';
					</script>
					";
				} else {
					echo "
					<SCRIPT LANGUAGE='JavaScript'>
					window.location='users.php?page=blog-user-creator&updated=true&updatedmsg=" . urlencode( __( 'Blogs and users created.', 'blog_user_creator' ) ) . "';
					</script>
					";
				}
			break;
		}
		echo '</div>';
	}

}
$blog_user_creator =& new Blog_User_Creator();


/**
 * Show notification if WPMUDEV Update Notifications plugin is not installed
 **/
if ( !function_exists( 'wdp_un_check' ) ) {
	add_action( 'admin_notices', 'wdp_un_check', 5 );
	add_action( 'network_admin_notices', 'wdp_un_check', 5 );

	function wdp_un_check() {
		if ( !class_exists( 'WPMUDEV_Update_Notifications' ) && current_user_can( 'edit_users' ) )
			echo '<div class="error fade"><p>' . __('Please install the latest version of <a href="http://premium.wpmudev.org/project/update-notifications/" title="Download Now &raquo;">our free Update Notifications plugin</a> which helps you stay up-to-date with the most stable, secure versions of WPMU DEV themes and plugins. <a href="http://premium.wpmudev.org/wpmu-dev/update-notifications-plugin-information/">More information &raquo;</a>', 'wpmudev') . '</a></p></div>';
	}
}
