<?php
/*
Plugin Name: Publish Scheduler
Plugin URI: http://www.willthewebmechanic.com
Description: Replaces default publishing with queued publishing.
Version: 2.1.6
Author: Will Brubaker
Author URI: http://www.willthewebmechanic.com
License: GPL 3.0+
Text Domain: automatic-post-publishing-scheduler
Domain Path: /languages/
*/

/**
	* This program is free software: you can redistribute it and/or modify
	*  it under the terms of the GNU General Public License as published by
	*  the Free Software Foundation, either version 3 of the License, or
	*  (at your option) any later version.
	*
	*  This program is distributed in the hope that it will be useful,
	*  but WITHOUT ANY WARRANTY; without even the implied warranty of
	*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	*  GNU General Public License for more details.
	*
	*  You should have received a copy of the GNU General Public License
	*  along with this program.  If not, see <http://www.gnu.org/licenses/>.
	*/

/**
	* @package PublishScheduler
	*/
class Publish_Scheduler
{

	static private $wwm_plugin_values = array(
		'name' => 'PublishScheduler',
		'version' => '2.1.6',
		'slug' => 'PublishScheduler',
		'dbversion' => '1.5',//db version 1.1 was introduced in version 2.0, 1.2 in 2.1, 1.3 in 2.2, 1.4 in 2.3
		'supplementary' => array(
			),
		);

public $wwm_page_link, $page_title, $menu_title, $menu_slug, $menu_link_text, $text_domain;

	/**
		* Runs every time this plugin is loaded. The bulk of the action/filter hooks go here.
		*/
	public function __construct()
	{

		add_action( 'init', array( $this, 'init_plugin' ) );
		add_action( 'admin_menu', array( $this, 'my_plugin_menu' ) );
		add_action( 'wp_ajax_assign_time_slots', array( $this, 'assign_time_slots' ) );
		add_action( 'wp_ajax_publish_now', array( $this, 'ajax_publish_now' ) );
		add_action( 'wp_ajax_update_excluded_dates', array( $this, 'update_excluded_dates' ) );
		add_action( 'wp_ajax_update_enabled_days', array( $this, 'update_enabled_days' ) );
		add_action( 'wp_ajax_wwm_apps_general_options', array( $this, 'update_general_options' ) );
		add_filter( 'wp_insert_post_data', array( $this, 'queue_post' ), 599, 2 );
		add_filter( 'post_row_actions', array( $this, 'add_publish_now_link' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'wwm_enqueue_admin_scripts' ) );
		add_action( 'admin_footer', array( $this, 'display_scheduled_alert' ) );
		add_action( 'post_submitbox_misc_actions', array( $this, 'post_submit_checkbox' ) );
		if ( is_admin() ) {
			add_action( 'after_wwm_plugin_links', array( $this, 'output_links' ) );
		}

		$this->text_domain = 'automatic-post-publishing-scheduler';
		$this->page_title = __( 'Publishing Scheduler Options', $this->text_domain );
		$this->menu_title = __( 'Scheduler', $this->text_domain );
		$this->menu_slug = 'PublishSchedule.php';
		$this->menu_link_text = __( 'Scheduler', $this->text_domain );
	}

	/**
		* Runs during the WP init action and does
		* any necessary setting/getting of variables
		* as well as setting up the database options
		* for this plugin
		* @since 1.0
		* @author Will the Web Mechanic <will@willthewebmechanic>
		* @link http://willthewebmechanic.com
		*/
	public function init_plugin()
	{

		if ( ! get_option( 'publish_scheduler_options', false ) ) {

			update_option(
				'publish_scheduler_options', array(
					'slots' => array(
						array( '09', '00', 'am' ),
						array( '10', '00', 'am' ),
						array( '11', '00', 'am' ),
						array( '12', '00', 'pm' ),
					),
				)
			);
		}
	}

	/**
		* conditionally enqueues the scripts and css necessary
		* for this plugin
		* @since 1.0
		* @author Will the Web Mechanic <will@willthewebmechanic>
		* @link http://willthewebmechanic.com
		*/
	public function wwm_enqueue_admin_scripts()
	{

		wp_enqueue_script( 'publish-now-ajax', plugins_url( 'js/publishnow.js', __FILE__ ), array( 'jquery', 'jquery-ui-dialog' ), self::$wwm_plugin_values['version'], true );
		wp_enqueue_style( 'jquery-ui-lightness', plugins_url( 'css/jquery-ui.min.css', __FILE__ ), array(), self::$wwm_plugin_values['version'] );
		if ( strpos( $_SERVER['REQUEST_URI'], $this->menu_slug ) || strpos( $_SERVER['REQUEST_URI'], 'post.php?post=' ) ) {
			wp_enqueue_script( 'scheduler-options-js', plugins_url( 'js/scheduleroptions.js', __FILE__ ), array( 'jquery', 'jquery-ui-datepicker', 'jquery-ui-tabs' ), self::$wwm_plugin_values['version'], true );
			wp_enqueue_style( 'scheduler-stylesheet', plugins_url( 'css/scheduler.css', __FILE__ ), null , self::$wwm_plugin_values['version'] );
		}
	}

	/**
		* Adds the WtWM menu item to the admin menu & adds a submenu link to this plugin to that menu item.
		* @since  2.0
		* @author Will the Web Mechanic <will@willthewebmechanic>
		* @link http://willthewebmechanic.com
		*/
	public function my_plugin_menu()
	{

		global $_wwm_plugins_page;
		/**
			* If, in the future, there is an enhancement or improvement,
			* allow other plugins to overwrite the panel by using
			* a higher number version.
			*/
		$plugin_panel_version = 2;
		add_filter( 'wwm_plugin_links', array( $this, 'this_plugin_link' ) );
		if ( empty( $_wwm_plugins_page ) || ( is_array( $_wwm_plugins_page ) && $plugin_panel_version > $_wwm_plugins_page[1] ) ) {
			$_wwm_plugins_page[0] = add_menu_page( 'WtWM Plugins', 'WtWM Plugins', 'manage_options', 'wwm_plugins', array( $this, 'wwm_plugin_links' ), plugins_url( 'images/wwm_wp_menu.png', __FILE__ ), '90.314' );
			$_wwm_plugins_page[1] = $plugin_panel_version;
		}
		add_submenu_page( 'wwm_plugins', $this->page_title, $this->menu_title, 'manage_options', $this->menu_slug, array( $this, 'scheduler_options' ) );
	}

	/**
		* Outputs the configuration/options panel
		* @since 1.0
		* @author Will the Web Mechanic <will@willthewebmechanic>
		* @link http://willthewebmechanic.com
		*/
	public function scheduler_options()
	{

		if ( isset( $_GET['debug'] ) ) {
			echo '<pre>';
			var_dump( get_option( 'publish_scheduler_options', array() ) );
			echo '</pre>';
		}
		$variable_html = $this->get_variable_html();
		extract( $variable_html );
		$general_options = get_option( 'wwm_scheduler_general_options', array() );
		$show_checkbox = ( isset( $general_options['override'] ) ) ? absint( $general_options['override'] ) : 0;
		?>

		<div class="wrap">
			<div id="tabs">
				<ul>
					<li><a href="#slots"><?php _e( 'Configure Time Slots', $this->text_domain ); ?></a></li>
					<li><a href="#weekdays"><?php _e( 'Configure Week Days', $this->text_domain ); ?></a></li>
					<li><a href="#dates"><?php _e( 'Configure Dates', $this->text_domain ); ?></a></li>
					<li><a href="#general"><?php _e( 'General Plugin Options', $this->text_domain ); ?></a></li>
				</ul>
				<div id="slots">
					<h2><?php _e( 'Set Scheduler Options:', $this->text_domain ); ?></h2>
					<div class="updated">
						<p>
						<?php _e( 'Input the number of time slots required.  If the number entered is less than the existing number, slots will be removed from the bottom up.  Changes will not be saved until committed using the \'Assign time slots\' form below.', $this->text_domain ); ?>
						</p>
					</div>
					<form id="set_time_slots" method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
						<div class="overlay"><span class="preloader"></span></div>
						<input type="hidden" name="action" value="set_time_slots">
						<input id="existing_count" type="hidden" name="existing_count" value="">
						<label for="time_slots"><?php _e( 'Number of time slots required:', $this->text_domain ); ?><br>
							<input id="time_slots" type="number" name="time_slots" size="3" maxlength="3">
						</label>
						<p>
							<input type="submit" class="button button-primary" value="<?php _e( 'submit', $this->text_domain ); ?>">
						</p>
					</form>
					<h4><?php _e( 'Assign time slots:', $this->text_domain ); ?></h4>
					<div class="updated">
						<p>
						<?php _e( 'Use the form below to put times into your time slots.  Duplicates are allowed. Invalid values will be discarded.  Ordering will also be done during processing.  Changes will not take effect until the \'submit\' button is pressed.', $this->text_domain ); ?>
						</p>
					</div>
					<form id="assign_time_slots" method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
						<div class="overlay"><span class="preloader"></span></div>
						<input type="hidden" name="action" value="assign_time_slots">
						<?php
						//$html comes from extract( $variable_html )
						echo $html;
						?>
						<div id="new_time_slots"></div>
						<?php wp_nonce_field( 'time-slots', 'time_slots', true, true ); ?>
						<p>
							<input type="submit" class="button button-primary" value="<?php _e( 'submit', $this->text_domain ); ?>">
						</p>
					</form>
					</div><!--#slots-->
				<div id="weekdays">
					<h4><?php _e( 'Enable days of the week in your publishing schedule.', $this->text_domain ); ?></h4>
					<div>
						<p>
							<?php _e( 'Weekdays that are checked are enabled by your publishing schedule.  This can be overridden by specific date in the next section.', $this->text_domain ); ?>
						</p>
					</div>
					<form id="excluded-days" method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
						<div class="overlay"><span class="preloader"></span></div>
						<fieldset id="week-days" name="week-days">
						<?php
						$dow = array( 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' );

						foreach ( $dow as $i => $day ) {
							echo '<label for="' . $day . '">' . $day . '<br /><input id="' . $day . '" type="checkbox" name="weekday[' . $i . ']" value="' . $i . '"' . checked( in_array( $i, $enabled_days ), true, false ) . ' /></label>' . "\n";
						}
						?>
						</fieldset>
						<?php wp_nonce_field( 'days-of-week', 'days_of_week', true, true ); ?>
						<input class="button-primary" type="submit" value="<?php _e( 'Enable Days', $this->text_domain ); ?>">
						<input type="hidden" name="action" value="update_enabled_days">
					</form>
				</div><!--#weekdays-->
				<div id="dates">
					<h3><?php _e( 'Define dates to exclude or allow in your publishing schedule', $this->text_domain ); ?></h3>
					<div>
						<p>
							<?php _e( 'Dates entered below will be excluded from your publishing schedule (for holidays, etc) unless the \'Check to allow\' box is checked.  In that case publishing will be allowed on that specific date (overrides disabled days of the week from the section above).', $this->text_domain ); ?>
						</p>
					</div>

					<form id="excluded-dates" method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
						<div class="overlay"><span class="preloader"></span></div>
						<fieldset id="dates-input" name="dates-input">
							<div class="defined-dates">
							<?php
							//$dates_output comes from earlier call to extract()
							echo $dates_output;
							?>
						</fieldset>
						<input type="hidden" name="action" value="update_excluded_dates">
						<?php wp_nonce_field( 'scheduled-dates', 'scheduled_dates', true, true ); ?>
						<p>
							<input type="submit" value="<?php _e( 'Save the Dates', $this->text_domain ); ?>" class="button-primary">
						</p>
					</form>
				</div><!--#dates-->
				<div id="general">
					<h3><?php _e( 'General Plugin Options', $this->text_domain ); ?></h3>
					<form id="general-options" method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
						<div class="overlay"><span class="preloader"></span></div>
						<label for="override-checkbox"><input type="checkbox" name="override" id="override-checkbox" <?php checked( $show_checkbox ); echo '>'; _e( 'Allow admins & editors to override automatic scheduling?', $this->text_domain ); ?></label>
						<?php wp_nonce_field( 'override-schedule', '_nonce', true, true ); ?>
						<input type="hidden" name="action" value="wwm_apps_general_options">
						<p><input type="submit" value="Update Options" class="button-primary"></p>
					</form>
				</div><!--#general-->
			</div><!--#tabs-->
		</div>

		<?php
	}

	/**
		* generates and returns some html and some arrays that the options panel needs for its display
		* @since 2.0
		* @author Will the Web Mechanic <will@willthewebmechanic>
		* @link http://willthewebmechanic.com
		* @return array an array of stuff needed by the admin panel
		*/
	private function get_variable_html()
	{

		$db_up_to_date = ( get_option( 'wwm_pubscheduler_db_version' ) == self::$wwm_plugin_values['dbversion'] ? true : false );
		if ( ! $db_up_to_date ) {
			$time_slots = get_option( 'publish_scheduler_options' );
			$time_slots['days'] = ( empty( $time_slots ) ) ? array( 0,1,2,3,4,5,6 ) : $time_slots['days'];
			$time_slots['dates_denied'] = ( empty( $time_slots['dates_denied'] ) ) ? array() : $time_slots['dates_denied'];
			$time_slots['dates_allowed'] = ( empty( $time_slots['dates_allowed'] ) ) ? array() : $time_slots['dates_allowed'];

			foreach ( $time_slots['dates_denied'] as $key => $value ) {
				$now = strtotime( date_i18n( 'Y-m-d', current_time( 'timestamp' ) ) );
					if ( strtotime( $now ) > strtotime( $value ) || $value == "" ) {
						unset( $time_slots['dates_denied'][$key] );
					}
				}

			foreach ( $time_slots['dates_allowed'] as $key => $value ) {
				$now = strtotime( date_i18n( 'Y-m-d', current_time( 'timestamp' ) ) );
				if ( strtotime( $now ) > strtotime( $value ) || $value == "" ) {
					unset( $time_slots['dates_allowed'][$key] );
				}
			}
			update_option( 'publish_scheduler_options', $time_slots );
			update_option( 'wwm_pubscheduler_db_version', self::$wwm_plugin_values['dbversion'] );
		}

		//get the time slots that have already been defined:
		//would this be cleaner by using extract? todo
		$time_slots = ( get_option( 'publish_scheduler_options' ) ? get_option( 'publish_scheduler_options' ) : array( 'dates_allowed' => array(), 'dates_denied' => array(), 'days' => array(), ) );
		$slots = $time_slots['slots'];
		if ( ! isset( $time_slots['days'] ) || empty( $time_slots['days'] ) ) {
			$time_slots['days'] = array( 0, 1, 2, 3, 4, 5, 6 );
			update_option( 'publish_scheduler_options', $time_slots );
		}
		if ( empty( $time_slots['dates_allowed'] ) ) $time_slots['dates_allowed'] = array();
		if ( empty( $time_slots['dates_denied'] ) ) $time_slots['dates_denied'] = array();
		$enabled_days = $time_slots['days'];
		$html = null;
		$i = 0;
		foreach ( $slots as $slot ) {
			$html .= '<div class="slot_input"><span class="label"><label for="hh[' . $i . ']" >slot ' . ( $i + 1 ) . ' : </label></span>' . "\n";
			$html .= '<input type="text" class="hour_input" id="hh[' . $i . ']" value="' . $slot[0] . '" name="hh[' . $i . ']" size="2" maxlength="2" autocomplete="off">' . "\n";
			$html .= ' :' . "\n";
			$html .= '<input type="text" id="mn[' . $i . ']" value="' . $slot[1] . '" name="mn[' . $i . ']" class="mn_input" size="2" maxlength="2" autocomplete="off">' . "\n";
			$html .= '  <select name="ampm[' . $i . ']" class="ampm_sel">' . "\n";
			$html .= '      <option value="am"';
											if ( $slot[2] == 'am' ) $html .= ' selected="selected"';
			$html .= '>am</option>';
			$html .= '      <option value="pm"';
											if ( $slot[2] == 'pm' ) $html .= ' selected="selected"';
			$html .= '>pm</option>';
			$html .= "</select><br /></div>\n";

			$i++;
		}

		$i = 0;
					if ( ! empty( $time_slots['dates_denied'] ) || ! empty( $time_slots['dates_allowed'] ) ) {
						$dates_output = __( 'Defined Dates', $this->text_domain ) . '<br>';
					} else {
						$dates_output = __( 'No dates defined yet', $this->text_domain) . '<br>';
					}
					$today = strtotime( 'today' );
					if ( ! empty( $time_slots['dates_allowed'] ) ) {
						foreach ( $time_slots['dates_allowed'] as $date ) {
						$a = strtotime( date( 'Y-m-d', strtotime( $date ) ) );
							if ( $a >= $today ) {//dates in the past can go away.
								$html_outputs[$a] = '<div><input type="hidden" name="altdate[' . $i . ']" ><input type="text" name="dates_allowed[' . $i . ']" class="datepicker" size="10" value="' . $date . '" readonly="readonly"><input id="date[' . $i. ']" class="allow" name="allow[' . $i . ']" type="checkbox" checked="checked"><label for="date[' . $i . ']"> ' . __( 'Check to allow', $this->text_domain ) . '</label> <label class="remove" for="remove[' . $i . ']">' . __( 'Delete?', $this->text_domain ) . '</label><input id="remove[' . $i . ']" type="checkbox" class="remove" name="remove[' . $i . ']" value="' . $date . '"><br></div>' . "\n";
								$i++;
							}
						}
					}

					$k = 0;
					if ( ! empty( $time_slots['dates_denied'] ) ) {
						foreach ( $time_slots['dates_denied'] as $date ) {
							$a = strtotime( date( 'Y-m-d', strtotime( $date ) ) );
							if ( $a >= $today ) {//dates in the past can go away.
								$html_outputs[$a] = '<div><input type="hidden" name="altdate[' . $i . ']"><input type="text" name="dates_denied[' . $k . ']" class="datepicker" size="10" value="' . $date . '" readonly="readonly"><input id="date[' . $i . ']" class="allow" name="allow[' . $i . ']" type="checkbox"><label for="date[' . $i . ']"> ' . __( 'Check to allow', $this->text_domain ) . '</label><label class="remove" for="remove[' . $i . ']">' . __( 'Delete?', $this->text_domain ) . '</label><input id="remove[' . $i . ']" type="checkbox" class="remove" name="remove[' . $i . ']" value="' . $date . '"><br></div>' . "\n";
								$i++;
								$k++;
							}
						}
					}

					if ( ! empty( $html_outputs ) ) {
						ksort( $html_outputs );
						foreach ( $html_outputs as $output ) {
							$dates_output .= $output;
						}
					}
					$dates_output .= '</div><!--defined-dates-->' . "\n";
					$dates_output .= '<div class="lone-date">' . __( 'Add New Date:', $this->text_domain ) . '<br><input type="hidden" name="altdate[' . $i . ']" /><input type="text" name="dates_denied[' . $k . ']" class="datepicker" size="10" readonly="readonly" />  <input id="date[' . $i . ']" class="allow" name="allow[' . $i . ']" type="checkbox"><label for="date[' . $i . ']">' . __( 'Check to allow', $this->text_domain ) . '</label></div>' . "\n";

		return array( 'html' => $html, 'enabled_days' => $enabled_days, 'dates_output' => $dates_output );
	}

	/**
		* Sets a 'post_date' & 'post_date_gmt' date/time based on available time slots as defined in
		* the plugin options panel, also sets 'post_status' to future as needed.
		* @since 1.0
		* @author Will the Web Mechanic <will@willthewebmechanic>
		* @link http://willthewebmechanic.com
		* @param  array $data    an array of post data
		* @param  array $postarr an array of post arguments
		* @return array $data the conditionally modified $data array
		*/
	public function queue_post( $data, $postarr )
	{

		if ( isset( $data['post_type'] ) && 'post' != $data['post_type'] ) {
			return $data;
		}

		if ( ( ! 'XMLRPC_REQUEST' ) && ( ! $this->is_nonce_valid() || ! current_user_can( 'publish_posts' ) ) ) {
			return $data;
		}

		if ( isset( $data['post_status'] ) && 'draft' == $data['post_status'] || 'auto-draft' == $data['post_status'] || isset( $data['post_title'] ) && 'Auto Draft' == $data['post_title'] || ( isset( $_POST['override_schedule'] ) && 1 == $_POST['override_schedule'] ) ) {
			return $data;
		}
		date_default_timezone_set( 'UTC' );
		$current_time = current_time( 'timestamp' );
		$tz = get_option( 'timezone_string' );
		//a user without admin/editor rights cannot alter the post status or the time/date of published posts:
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			$post = get_post( $_POST['post_ID'] );
			if ( 'publish' == $post->post_status && 'draft' != $_POST['post_status'] ) {//authors can put published posts in draft status, but not any other statuses..
				$data['post_status'] = $post->post_status;
				$data['post_date'] = $post->post_date;
				$data['post_date_gmt'] = $post->post_date_gmt;
				return $data;
			}
		}

		if ( isset( $data['post_status'] ) && 'trash' == $data['post_status'] ) {
			return $data;
		}

		if ( isset( $_POST['save'] ) && 'Update' == $_POST['save'] && isset( $data['post_status'] ) && 'future' != $data['post_status'] ) {
			return $data;
		}

		if ( $this->is_admin_overriding( $postarr ) ) {
			return $data;
		}

		$publish_time = null;
		$available_time_slots = $dates_allowed = $dates_denied = $allowed_time_slots = array();

		//force 'seconds' to zero
		if ( isset( $data['post_date'] ) ) {
			$post_date = date_i18n( 'Y-m-d H:i', strtotime( $data['post_date'] ) );
			$post_date_gmt = date_i18n( 'Y-m-d H:i', strtotime( $data['post_date_gmt'] ) );
		}
		$post_date_ts = strtotime( $post_date );
		$post_date_gmt_ts = strtotime( $post_date_gmt );
		$diff = $post_date_gmt_ts - $post_date_ts;
		//check if the time sent is in the past.  If so, return $data.
		$now_timestamp = strtotime( date_i18n( 'Y-m-d H:i', $current_time ) );//the seconds have been stripped from $post_date_timestamp, so they need to be stripped here too.
		if ( ! 'XMLRPC_REQUEST' &&  $post_date_ts < $now_timestamp && current_user_can( 'edit_others_posts' ) ) {
			$data['post_status'] = 'publish';
			return $data;
		}

		if ( isset( $_POST['publish'] ) && 'Schedule' == $_POST['publish'] ) {
			//set current time to the time it asked for...
			$current_time = strtotime( $_POST['aa'] . '-' . $_POST['mm'] . '-' . $_POST['jj'] . ' ' . $_POST['hh'] . ':' . $_POST['mn'] );
		}
		//at this point, anyone who has permission to override a scheduled slot has done so.
		//now to check if the author is trying to alter their own time slot.
		//todo do something to either add a custom capability or otherwise allow plugin users greater granular control over who can alter time slots.
		if ( isset( $_POST['original_post_status'] ) && 'future' == $_POST['original_post_status'] ) {
			$asked_for_time = strtotime( $_POST['aa'] . '-' . $_POST['mm'] . '-' . $_POST['jj'] . ' ' . $_POST['hh'] . ':' . $_POST['mn'] );
			$original_time_slot = strtotime( $_POST['hidden_aa'] . '-' . $_POST['hidden_mm'] . '-' . $_POST['hidden_jj'] . ' ' . $_POST['hidden_hh'] . ':' . $_POST['hidden_mn'] );
			if ( $original_time_slot == $asked_for_time ) {
				return $data;
			}
			elseif ( $asked_for_time < $original_time_slot ) {//Nope, earlier time slot is not yours. Can not has.
				$post_date = date_i18n( 'Y-m-d H:i:s', $original_time_slot );
				$gmt_ts = $original_time_slot + $diff;
				$post_date_gmt = date_i18n( 'Y-m-d H:i:s', $gmt_ts, true );
				$data['post_status'] = 'future';
				$data['post_date'] = $post_date;
				$data['post_date_gmt'] = $post_date_gmt;
				return $data;
			} else {
				$current_time = $asked_for_time;//o.k., you can have a slot further in the future.
			}
		}

		//get defined time slots
		$time_slots = get_option( 'publish_scheduler_options' );
		$dates_allowed = ( ! empty( $time_slots['dates_allowed'] ) ? $time_slots['dates_allowed'] : array() );
		$dates_denied = ( ! empty( $time_slots['dates_denied'] ) ? $time_slots['dates_denied'] : array() );

		$enabled_days = $time_slots['days'];
		$slots = $time_slots['slots'];
		$allowed_time_slots = $this->get_allowed_slots( array( 'dates_allowed' => $dates_allowed, 'slots' => $slots ) );
		$full_time_slots = array();
		//add the denied dates/times to the full_time_slots array:
		foreach ( $dates_denied as $deny ) {
			foreach ( $slots as $slot ) {
				$full_time_slots[] = strtotime( $deny . ' ' . $slot[0] . ':' . $slot[1] . ' ' . $slot[2] );
			}
		}
		foreach ( $slots as $slot ) {
			$ymd = date_i18n( 'Y-m-d', $current_time );
			$stamp = strtotime( $ymd . ' ' . $slot[0] . ':' . $slot[1] . ' ' . $slot[2] );

			$defined_time_slots[] = $stamp;
			if ( $stamp >= $current_time ) { //only looking for slots available in the future
				//and, it's only available if it's NOT on one of our days AND not 'denied'
				$dow = date_i18n( 'w', $stamp );
				if ( ! in_array( $dow, $enabled_days ) ) {//this particular day of the week is excluded
					if ( in_array( $stamp, $allowed_time_slots ) ) {//but this timestamp is explicitly allowed
						$available_time_slots[] = $stamp;//then it is available
					}
				} else {//the day of week is ok to publish on
					if ( ! in_array( $stamp, $full_time_slots) ) {//this particular timestamp is not explicitly denied
						$available_time_slots[] = $stamp;//so it is available
					}
				}
			}
		}

		$args = array(
			'available_time_slots' => $available_time_slots,
			'allowed_time_slots' => $allowed_time_slots,
			'full_time_slots' => $full_time_slots,
		);
		$slots_arrays = $this->get_slots_arrays( $args );
		/**
			* At the time this was written, get_slots_arrays returns
			* an array with three keys, those being 'full_time_slots', 'available_time_slots' and 'allowed_time_slots'
			* which are now broken out into their own variables with extract
			*/
		extract( $slots_arrays );
		$check = array();
		if ( empty( $full_time_slots ) ) $full_time_slots[] = $current_time;
		//There are now four arrays.  $available_time_slots, $full_time_slots $enabled_days and $defined_time_slots(which is a copy of the original available slots).
		//while available times slots is empty, loop through $defined_time_slots
		$i = 1; //start at 1 because that's how many days we want to add each time.
		while ( empty( $available_time_slots ) ) {
			foreach ( $defined_time_slots as $x ) {
				$y = $x + ( 60 * 60 * ( 24 * $i ) );
				$dow = date_i18n( 'w', $y );
				if ( ! in_array( $dow, $enabled_days ) ) {//the day is excluded
					if ( in_array( $y, $allowed_time_slots ) ) {//it is explicitly allowed
						$check[] = $y;
					}
				} else {
					if ( ! in_array( $y, $dates_denied ) ) {
						$check[] = $y;
					}
				}
			}
			$i++;
			foreach ( $check as $x ) {
				if ( ! in_array($x, $full_time_slots) && ( $x >= $current_time ) ) {
					$available_time_slots[] = $x;
					break;
				}
			}
		}

		$publish_time = reset( $available_time_slots );
		$gmt_ts = $publish_time + $diff;
		//take $publish_time which is a timestamp - convert it to a time/date with localization goodness for WP
		$post_date = date_i18n( 'Y-m-d H:i:s', $publish_time );
		//convert the gmt timestamp to a time/date.
		$post_date_gmt = date_i18n( 'Y-m-d H:i:s', $gmt_ts, true );
		//schedule post for that slot
		$data['post_status'] = 'future';
		$data['post_date'] = $post_date;
		$data['post_date_gmt'] = $post_date_gmt;
		//all done - return the modified $data array
		return $data;
	}

	/**
		* This plugin should only be altering publish_date when a human user
		* is creating/updating a post. This applies to the normal post edit screen, quick-edit (inline on the 'posts' screen)
		* and the press-this bookmarklet. WP creates the nonce fields and this method will check all of the ones
		* that it knows about - if a valid nonce is found, the alteration of the publish_date can continue, otherwise
		* this method will return false. As of v2.0.1 media-form was added to the $nonce_actions array as a user was reporting
		* that he was using a plugin that auto-created posts from any images uploaded.
		* @since  2.0
		* @author Will the Web Mechanic <will@willthewebmechanic>
		* @link http://willthewebmechanic.com
		* @return boolean whether or not a valid nonce is found and the current user has the capability to edit posts.
		*/
	private function is_nonce_valid()
	{

		if ( ! current_user_can( 'edit_posts' ) ) {
			return false;
		} elseif ( isset( $_POST['_inline_edit'] ) && wp_verify_nonce( $_POST['_inline_edit'], 'inlineeditnonce' ) ) {
			return true;
		} else {
			$nonce_actions = array( 'press-this', 'add-post', 'media-form', );
			if ( isset( $_POST['post_ID'] ) ) {
				$nonce_actions[] = 'update-post_' . $_POST['post_ID'];
			}
			foreach ( $nonce_actions as $action ) {
				if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], $action ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
		* There are certain cases where an admin user may want to override/edit
		* the scheduled time. This method checks if the current request is one of those cases.
		* If the 'publish now' link is clicked in the post edit screen, the post should be published NOW.
		* If an admin user is altering the scheduled time in the quick edit screen, that request should be
		* respected.
		* @since  2.0
		* @author Will the Web Mechanic <will@willthewebmechanic>
		* @link http://willthewebmechanic.com
		* @param  array  $postarr an array of $post data. See documentation for wp_insert_post_data filter
		* @return boolean whether or not the admin user is overriding the scheduled date/time
		*/
	private function is_admin_overriding( $postarr )
	{

		//admin user can click the 'publish now' link and publish the article 'NOW'
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			return false;
		} elseif ( isset( $_POST['action'] ) && 'publish_now' == $_POST['action'] ) {
			return true;
		} elseif ( isset( $postarr['post_view'] ) && 'list' == $postarr['post_view'] && isset( $postarr['screen'] ) && 'edit-post' == $postarr['screen'] && isset( $postarr['action'] ) && 'inline-save' == $postarr['action'] ) {
			return true;
		}
		return false;
	}

	/**
		* Refines the $dates_allowed array
		* @since  2.0
		* @author Will the Web Mechanic <will@willthewebmechanic>
		* @link http://willthewebmechanic.com
		* @param  array  $args an array of function arguments
		* @return array  $allowed_time_slots  The refined array of time slots
		*/
	private function get_allowed_slots( $args = array() )
	{

		/**
			* $args (at the time of writing) contains 'dates_allowed' and 'slots'
			*/
		extract( $args );
		$allowed_time_slots = array();
		date_default_timezone_set( 'UTC' );
		foreach ( $dates_allowed as $allow ) {
			foreach ( $slots as $slot ) {
			$x = strtotime( $allow . ' ' . $slot[0] . ':' . $slot[1] . ' ' . $slot[2] );
			$allowed_time_slots[] = $x;
			}
		}
		return $allowed_time_slots;
	}

	/**
		* Builds and returns some arrays that queue_post() needs
		* @since  2.0
		* @author Will the Web Mechanic <will@willthewebmechanic>
		* @link http://willthewebmechanic.com
		* @param  array  $args an array of arguments
		* @return array       the arrays of timeslots that queue_post() needs
		*/
	private function get_slots_arrays( $args = array() )
	{

		$defaults = array(
			'full_time_slots' => array(),
			'available_time_slots' => array(),
			'allowed_time_slots' => array(),
		);
		$args = wp_parse_args( $args, $defaults );
		/**
			* At the time of writing, the $args array has three keys, 'full_time_slots', available_time_slots' & 'allowed_time_slots'
			* which are now broken out using extract:
			*/
		extract( $args );
		//get all the posts that are currently scheduled
		date_default_timezone_set( 'UTC' );
		$queryargs = array(
			'post_type' => 'post',
			'post_status' => 'future',
			'posts_per_page' => -1,
			'orderby' => 'date',
			'order' => 'ASC',
		);
		$my_query = new WP_Query( $queryargs );

		if ( $my_query->have_posts() ) {
			while ( $my_query->have_posts() ) {
				$my_query->the_post();
				$value = strtotime( get_the_time( 'Y-m-d g:i:s a') );
				//all I really need here is an array of time slots to check which ones are full
				$full_time_slots[] = $value;
				if ( ( $key = array_search( $value, $available_time_slots ) ) !== false ) {
					unset( $available_time_slots[$key] );
				}
				if ( ( $key = array_search( $value, $allowed_time_slots ) ) !== false ) {
					unset( $allowed_time_slots[$key] );
				}
			}
		}
		return array(
			'allowed_time_slots' => $allowed_time_slots,
			'available_time_slots' => $available_time_slots,
			'full_time_slots' => $full_time_slots,
		);
	}

	/**
		* ajax handler for assigning timeslots
		* @since  1.0
		* @author Will the Web Mechanic <will@willthewebmechanic>
		* @link http://willthewebmechanic.com
		*/
	public function assign_time_slots()
	{

		if ( ! wp_verify_nonce( $_POST['time_slots'], 'time-slots' ) || ( ! current_user_can( 'manage_options' ) ) ) {
			exit;
		}
		$hours = $_POST['hh'];
		//first, validate the values in the 'hh' array.  If they pass, concatenate them with their counterparts
		//in the 'mn' and 'ampm' arrays, building a new array of timestamps provided that 'mn' is also valid.
		$i = 0;
		foreach ( $hours as $hour) {
			if ( is_numeric( $hour ) ) {
				if ( (int) $hour > -1 && (int) $hour < 13 ) {
					if ( is_numeric( $_POST['mn'][$i] ) && (int) $_POST['mn'][$i] > -1 && (int) $_POST['mn'][$i] < 61 ) {
						$timestamps[] = strtotime( $hour . ':' .  $_POST['mn'][$i] . $_POST['ampm'][$i] );
					}
				}
			}
			$i++;
		}
		//the new array contains only *nix style timestamps, order it ascending
		asort( $timestamps );

		foreach ( $timestamps as $time) {
			$new_times[] = array( date( 'h',$time), date( 'i', $time), date( 'a',$time) );
		}

		$old_times = get_option( 'publish_scheduler_options' );
		$old_times['slots'] = $new_times;
		update_option( 'publish_scheduler_options', $old_times );
		echo json_encode( $old_times['slots'] );
		exit;
	}

	/**
		* adds the link to this plugin's management page
		* to the $links array to be displayed on the WWM
		* plugins page:
		* @since 2.0
		* @author Will the Web Mechanic <will@willthewebmechanic>
		* @link http://willthewebmechanic.com
		* @param  array $links the array of links
		* @return array $links the filtered array of links
		*/
	public function this_plugin_link( $links ) {

		$this->wwm_page_link = $menu_page_url = menu_page_url( 'PublishSchedule.php', 0 );
		$links[] = '<a href="' . $this->wwm_page_link . '">' . __( 'Post Scheduler Options', $this->text_domain ) . '</a>' . "\n";
		return $links;
	}

	/**
		* outputs an admin panel and displays links to all
		* admin pages that have been added to the $wwm_plugin_links array
		* via apply_filters
		* @since 2.0
		* @author Will the Web Mechanic <will@willthewebmechanic>
		* @link http://willthewebmechanic.com
		*/
	public function wwm_plugin_links()
	{

		$wwm_plugin_links = apply_filters( 'wwm_plugin_links', array() );
		//set a version here so that everything can be overwritten by future plugins.
		//and pass it via the do_action calls
		$plugin_links_version = 1;
		echo '<div class="wrap">' . "\n";
		echo '<div id="icon-plugins" class="icon32"><br></div>' . "\n";
		echo '<h2>Will the Web Mechanic Plugins</h2>' . "\n";
		do_action( 'before_wwm_plugin_links', $plugin_links_version, $wwm_plugin_links );
		if ( ! empty( $wwm_plugin_links ) ) {
			echo '<ul>' . "\n";
			foreach ( $wwm_plugin_links as $link ) {
				echo '<li>' . $link . '</li>' . "\n";
			}
			echo '</ul>';
		}
		do_action( 'after_wwm_plugin_links', $plugin_links_version );
		echo '</div>' . "\n";
	}

	public function output_links( $plugin_links_version )
	{

		if ( 1 == $plugin_links_version ) {
			echo '<ul>
										<li><a href="https://github.com/WillBrubaker/automatic-post-publishing-scheduler" title="' . __( 'Fork Me on GitHub', $this->text_domain ) . '">' . __( 'Automatic Post Publishing Scheduler on github', $this->text_domain ) . '</a></li>
										<li><a href="http://wordpress.org/support/plugin/automatic-post-publishing-scheduler" title="Get Support">' . __( 'Support for Automatic Post Publishing Scheduler', $this->text_domain ) . '</a></li>
										<li><a href="http://wordpress.org/support/view/plugin-reviews/automatic-post-publishing-scheduler" title="' . __( 'Review The Automatic Post Publishing Scheduler Plugin', $this->text_domain ) . '">' . __( 'Rate Automatic Post Publishing Scheduler', $this->text_domain ) . '</a></li>
										<li><a href="http://ctt.ec/BIYrv" title="' . __( 'Shout it From the Rooftops!' , $this->text_domain ) . '">' . __( 'Tweet this plugin', $this->text_domain ) . '</a></li>
										<li>' . __( 'Donate to the development of the Automatic Post Publishing Scheduler plugin', $this->text_domain ) . '
											<form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
												<input name="cmd" type="hidden" value="_s-xclick" />
												<input name="hosted_button_id" type="hidden" value="634DZTUWQA2ZU" />
												<input alt="PayPal - The safer, easier way to pay online!" name="submit" src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_SM.gif" type="image" />
												<img src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" alt="Donate" width="1" height="1" border="0" />
											</form>
										</li>
										<li><a class="coinbase-button" data-code="39735a28948aab41c695a3550c2c93d4" data-button-style="donation_large" href="https://coinbase.com/checkouts/39735a28948aab41c695a3550c2c93d4">' . __( 'Donate Bitcoins', $this->text_domain ) . '</a><script src="https://coinbase.com/assets/button.js" type="text/javascript"></script></li>
									</ul>';
		}
	}

	/**
		* Adds a 'publish now' link to the post edit page
		* @since  1.0
		* @author Will the Web Mechanic <will@willthewebmechanic>
		* @link http://willthewebmechanic.com
		* @param array $actions the actions available in the post edit screen
		* @param object $post the WordPress post object
		*/
	public function add_publish_now_link( $actions, $post )
	{

		if ( $post->post_status == 'future' && current_user_can( 'edit_others_posts' ) ) {
			$nonce = wp_create_nonce( 'publish-now' );
			$actions['publish_now'] = '<a href="' . admin_url() . 'post.php?post=' .  $post->ID . '&amp;action=publish_now" data-nonce="' . $nonce . '" class="publish-now" title="' . __( 'Publish This Item Now', $this->text_domain ) . '">' . __( 'Publish Now', $this->text_domain ) . '</a>';
		}

		return $actions;
	}

	/**
		* ajax handler for the 'publish_now' links:
		* @since 1.0
		* @author Will the Web Mechanic <will@willthewebmechanic>
		* @link http://willthewebmechanic.com
		*/
	public function ajax_publish_now()
	{

		if ( ( ! current_user_can( 'edit_others_posts' ) ) || ( ! wp_verify_nonce( $_POST['_wp_nonce'], 'publish-now' ) ) ) {
			exit;
		}
		$tz = get_option( 'timezone_string', 'UTC' );
		$phptz_holder = date_default_timezone_get();
		date_default_timezone_set( $tz );
		$post_date = date( 'Y-m-d H:i:s' );
		$args = array(
			'ID' => $_POST['pid'],
			'post_date' => $post_date,
			'post_status' => 'publish',
			'edit_date' => true,
			'post_date_gmt' => gmdate( 'Y-m-d H:i:s', strtotime( $post_date ) )
		);
		$x = wp_update_post( $args );
		if( 0 == $x ) {
			echo json_encode( array( 'error' => __( 'Update failed due to an unknown error', $this->text_domain ) ) );
		}
		else {
			echo json_encode( array( 'success' => __( 'success', $this->text_domain ) ) );
		}
		date_default_timezone_set( $phptz_holder);
		exit;
	}

	/**
		* ajax handler to update excluded/included dates
		* @since 1.0
		* @author Will the Web Mechanic <will@willthewebmechanic>
		* @link http://willthewebmechanic.com
		*/
	public function update_excluded_dates()
	{

		if ( ! wp_verify_nonce( $_POST['scheduled_dates'], 'scheduled-dates' ) || ( ! current_user_can( 'manage_options' ) ) ) {
			exit;
		}

		/**
			* A date can't be both allowed and denied. If a date is
			* duplicated in both arrays, keep the one in the 'denied'
			* array and discard the one in the 'allowed' array.
			*/
		if ( isset( $_POST['dates_allowed'] ) && isset( $_POST['dates_denied'] ) ) {
			foreach ( $_POST['dates_allowed'] as $key => $date ) {
				if ( is_array( $_POST['dates_denied'] ) && in_array( $date, $_POST['dates_denied'] ) ) {
					unset( $_POST['dates_allowed'][$key] );
				}
			}
		}
		$time_slots = get_option( 'publish_scheduler_options', array() );
		$time_slots['dates_allowed'] = ( ! empty( $_POST['dates_allowed'] ) ) ? $_POST['dates_allowed'] : array();
		$time_slots['dates_denied'] = ( ! empty( $_POST['dates_denied'] ) ) ? $_POST['dates_denied'] : array();
		$time_slots['dates_denied'] = ( isset( $_POST['dates_denied'] ) ) ? array_unique( array_merge( $_POST['dates_denied'], $time_slots['dates_denied'] ) ) : $time_slots['dates_denied'];
		$time_slots['dates_allowed'] = ( isset( $_POST['dates_allowed'] ) ) ? array_unique( array_merge( $_POST['dates_allowed'], $time_slots['dates_allowed'] ) ) : $time_slots['dates_allowed'];

		foreach ( $time_slots['dates_denied'] as $key => $date ) {
			$now = strtotime( date_i18n( 'Y-m-d', current_time( 'timestamp' ) ) );
			if ( ( $now > strtotime( $date ) ) || strlen( $date ) != 10 ) {
				unset( $time_slots['dates_denied'][$key] );
			} else {
				$time_slots['dates_denied'][$key] = sanitize_text_field( $date );
			}
		}
		foreach ( $time_slots['dates_allowed'] as $key => $date ) {
			$now = strtotime( date_i18n( 'Y-m-d', current_time( 'timestamp' ) ) );
				if ( ( $now > strtotime( $date ) ) || strlen( $date ) != 10 ) {
					unset( $key );
				} else {
					$time_slots['dates_allowed'][$key] = sanitize_text_field( $date );
				}
			}

		$success = update_option( 'publish_scheduler_options', $time_slots );
		if ( $success ) {
			echo json_encode( array( 'success' => __( 'success', $this->text_domain ) ) );
		}
		else {
			echo json_encode( array( 'error' => __( 'Update failed due to an unknown error', $this->text_domain ) ) );
		}
		exit;
	}

	/**
		* ajax handler for updating enabled/disabled
		* week days.
		* @since 1.0
		* @author Will the Web Mechanic <will@willthewebmechanic>
		* @link http://willthewebmechanic.com
		*/
	public function update_enabled_days()
	{

		if ( ! wp_verify_nonce( $_POST['days_of_week'], 'days-of-week' ) || ( ! current_user_can( 'manage_options' ) ) ) {
			return;
		}
		$time_slots = get_option( 'publish_scheduler_options' );
		if ( empty( $_POST['weekday'] ) ) {
			echo json_encode( array( 'error' => __( 'at least one weekday must be enabled', $this->text_domain ) ) );
			exit;
		}

		$weekdays = array();
		foreach ( $_POST['weekday'] as $integer ) {
			$weekdays[] = absint( $integer );
		}
		$time_slots['days'] = $weekdays;
		$success = update_option( 'publish_scheduler_options', $time_slots );
		if ( $success ) {
			echo json_encode( array( 'success' => __( 'success', $this->text_domain ) ) );
		} else {
			echo json_encode( array( 'error' => __( 'Update failed due to an unknown error', $this->text_domain ) ) );
		}
		exit;
	}

	/**
		* AJAX handler for updating general plugin options
		* @since 2.1
		*/
	public function update_general_options() {
		if ( ! wp_verify_nonce( $_POST['_nonce'], 'override-schedule' ) || ! current_user_can( 'manage_options' ) ) {
			status_header( 403 );
			exit;
		}

		$general_options = get_option( 'wwm_scheduler_general_options', array() );
		$new_value = ( isset( $_POST['override'] ) ) ? 1 : 0;
		$general_options['override'] = $new_value;
		$success = update_option( 'wwm_scheduler_general_options', $general_options );
		wp_send_json_success();
	}

	/**
		* Injects js to display an alert to the article author
		* explaining why their 'Published' article has been 'Scheduled' instead.
		* @since 1.0
		* @author Will the Web Mechanic <will@willthewebmechanic>
		* @link http://willthewebmechanic.com
		*/
	public function display_scheduled_alert()
	{

		$alert_text = '<p>' . __( 'The article you submitted has been scheduled for a later time to ensure articles are spaced out and published during higher traffic periods. Do not be alarmed, we are trying to make sure your article gets the visibility it deserves!', $this->text_domain ) . '</p><p>' . __( 'Lower traffic periods: Late Nights, Early Mornings, and Weekends are generally avoided.', $this->text_domain );
		$alert_title = __( 'Thanks for your article!', $this->text_domain );
		if ( strpos( $_SERVER['REQUEST_URI'], 'message=9' ) ) {
		?>
			<script type="text/javascript">
				jQuery(document).ready(function($){
								function myalert(title, text)
								{
								var div = $( '<div>' ).html(text).dialog({
												title: title,
												modal: false,
												width: '40%',
												close: function() {
																$(div).dialog('destroy' ).remove();
												},
												buttons: [{
																text: "Ok",
																click: function() {
																				$(this).dialog("close");
																}}]
								})
				};
				var alertText = "<?php echo $alert_text; ?>";
				myalert("<?php echo $alert_title; ?>", alertText );
				});
			</script>
		<?php
		}
	}

	/**
		* Inserts a checkbox to override the automatic post scheduler for admins/editors
		* @since 2.0.4
		*/
	public function post_submit_checkbox()
	{

		global $post;
		$general_options = get_option( 'wwm_scheduler_general_options', array() );
		$show_checkbox = ( isset( $general_options['override'] ) ) ? absint( $general_options['override'] ) : 0;
		if ( current_user_can( 'edit_others_posts' ) && 'publish' != $post->post_status && $show_checkbox ) {

			?>
			<div class="post-scheduling-override misc-pub-section">
				<label for="schedule-override">
					<input id="schedule-override" type="checkbox" name="override_schedule" value="1"><?php _e ( 'Override auto-scheduler?', $this->text_domain ); ?>
				</label>
			</div>
			<?php
		}

	}
}
$var = new Publish_Scheduler;
