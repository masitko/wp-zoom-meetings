<?php
/**
 * Meeting Post Type Controller
 *
 * @since 3.0.0
 * @author Deepen.
 * @created_on 11/18/19
 */

class Zoom_Video_Conferencing_Admin_PostType {

	public function __construct() {
		add_action( 'init', array( $this, 'register' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_metabox' ) );
		add_action( 'save_post_zoom-meetings', array( $this, 'save_metabox' ), 10, 2 );
		add_filter( 'single_template', array( $this, 'single' ) );
		add_filter( 'archive_template', array( $this, 'archive' ) );
		add_action( 'before_delete_post', array( $this, 'delete' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
	}

	/**
	 * Register Post Type
	 *
	 * @since 3.0.0
	 * @author Deepen
	 */
	public function register() {
		if ( post_type_exists( 'zoom-meetings' ) ) {
			return;
		}

		$labels = array(
			'name'               => _x( 'Zoom Meetings', 'Zoom Meetings', 'video-conferencing-with-zoom-api' ),
			'singular_name'      => _x( 'Zoom Meeting', 'Zoom Meeting', 'video-conferencing-with-zoom-api' ),
			'menu_name'          => _x( 'Zoom Meeting', 'Zoom Meeting', 'video-conferencing-with-zoom-api' ),
			'name_admin_bar'     => _x( 'Zoom Meeting', 'Zoom Meeting', 'video-conferencing-with-zoom-api' ),
			'add_new'            => __( 'Add New', 'video-conferencing-with-zoom-api' ),
			'add_new_item'       => __( 'Add New meeting', 'video-conferencing-with-zoom-api' ),
			'new_item'           => __( 'New meeting', 'video-conferencing-with-zoom-api' ),
			'edit_item'          => __( 'Edit meeting', 'video-conferencing-with-zoom-api' ),
			'view_item'          => __( 'View meetings', 'video-conferencing-with-zoom-api' ),
			'all_items'          => __( 'All meetings', 'video-conferencing-with-zoom-api' ),
			'search_items'       => __( 'Search meetings', 'video-conferencing-with-zoom-api' ),
			'parent_item_colon'  => __( 'Parent meetings:', 'video-conferencing-with-zoom-api' ),
			'not_found'          => __( 'No meetings found.', 'video-conferencing-with-zoom-api' ),
			'not_found_in_trash' => __( 'No meetings found in Trash.', 'video-conferencing-with-zoom-api' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'menu_icon'          => 'dashicons-video-alt2',
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => 5,
			'supports'           => array(
				'title',
				'editor',
				'author',
				'thumbnail',
			),
		);

		register_post_type( 'zoom-meetings', $args );

		// Add new taxonomy, make it hierarchical (like categories)
		$labels = array(
			'name'          => _x( 'Category', 'Category', 'video-conferencing-with-zoom-api' ),
			'singular_name' => _x( 'Category', 'Category', 'video-conferencing-with-zoom-api' ),
		);

		$args = array(
			'hierarchical'      => true,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
		);

		register_taxonomy( 'zoom-meeting', array( 'zoom-meetings' ), $args );
	}

	/**
	 * Adds the meta box.
	 */
	public function add_metabox() {
		add_meta_box( 'zoom-meeting-meta', __( 'Zoom Details', 'video-conferencing-with-zoom-api' ), array(
			$this,
			'render_metabox'
		), 'zoom-meetings', 'advanced', 'high' );
		add_meta_box( 'zoom-meeting-meta-side', __( 'Meeting Details', 'video-conferencing-with-zoom-api' ), array(
			$this,
			'rendor_sidebox'
		), 'zoom-meetings', 'side', 'high' );
		if ( is_plugin_inactive( 'vczapi-woo-addon/vczapi-woo-addon.php' ) && is_plugin_inactive( 'vczapi-woocommerce-addon/vczapi-woocommerce-addon.php' ) ) {
			add_meta_box( 'zoom-meeting-woo-integration-info', __( 'WooCommerce Integration?', 'video-conferencing-with-zoom-api' ), array(
				$this,
				'render_woo_sidebox'
			), 'zoom-meetings', 'side', 'high' );
		}
	}

	public function render_woo_sidebox() {
		echo "<p>Enable this meeting to be purchased by your users ? </p><p>Check out <a href='" . admin_url( 'edit.php?post_type=zoom-meetings&page=zoom-video-conferencing-addons' ) . "'>WooCommerce addon</a> for this plugin.</p>";
	}

	/**
	 * Renders the meta box.
	 */
	public function render_metabox( $post ) {
		// Add nonce for security and authentication.
		wp_nonce_field( '_zvc_meeting_save', '_zvc_nonce' );

		wp_enqueue_script( 'video-conferencing-with-zoom-api-js' );
		wp_enqueue_script( 'video-conferencing-with-zoom-api-select2-js' );
		wp_enqueue_script( 'video-conferencing-with-zoom-api-timepicker-js' );

		//Check if any transient by name is available
		$users = video_conferencing_zoom_api_get_user_transients();

		$meeting_fields = get_post_meta( $post->ID, '_meeting_fields', true );

		//Get Template
		require_once ZVC_PLUGIN_VIEWS_PATH . '/post-type/tpl-meeting-fields.php';
	}

	function rendor_sidebox( $post ) {
		$meeting_fields = get_post_meta( $post->ID, '_meeting_fields', true );
		// Add nonce for security and authentication.
		wp_nonce_field( '_zvc_meeting_save', '_zvc_nonce' );

		$meeting_details = get_post_meta( $post->ID, '_meeting_zoom_details', true );
		?>
        <div class="zoom-metabox-wrapper">
			<?php
			if ( ! empty( $meeting_details ) ) {
				if ( ! empty( $meeting_details->code ) && ! empty( $meeting_details->message ) ) {
					?>
                    <p><strong>Meeting has not been created for this post yet. Publish your meeting or hit update to create a new one for this post
                            !</strong></p>
					<?php
					echo '<p style="color:red;">Zoom Error:' . $meeting_details->message . '</p>';
				} else {
					?>
                    <div class="zoom-metabox-content">
                        <p><a href="<?php echo $meeting_details->start_url; ?>" title="Start URL">Start Meeting</a></p>
                        <p><a href="<?php echo $meeting_details->join_url; ?>" title="Start URL">Join Meeting</a></p>
                        <p><strong>Meeting ID:</strong> <?php echo $meeting_details->id; ?></p>
                    </div>
                    <hr>
					<?php
				}
			} else { ?>
                <p><strong>Meeting has not been created for this post yet. Publish your meeting or hit update to create a new one for this post
                        !</strong></p>
			<?php } ?>
            <div class="zoom-metabox-content">
                <p><?php _e( 'Requires Login?', 'video-conferencing-with-zoom-api' ); ?>
                    <input type="checkbox" name="option_logged_in" value="1" <?php ! empty( $meeting_fields['site_option_logged_in'] ) ? checked( '1', $meeting_fields['site_option_logged_in'] ) : false; ?> class="regular-text">
                </p>
                <p class="description"><?php _e( 'Only logged in users of this site will be able to join this meeting.', 'video-conferencing-with-zoom-api' ); ?></p>
                <p><?php _e( 'Hide Join via browser link ?', 'video-conferencing-with-zoom-api' ); ?>
                    <input type="checkbox" name="option_browser_join" value="1" <?php ! empty( $meeting_fields['site_option_browser_join'] ) ? checked( '1', $meeting_fields['site_option_browser_join'] ) : false; ?> class="regular-text">
                </p>
                <p class="description"><?php _e( 'This will disable join via browser link in frontend page.', 'video-conferencing-with-zoom-api' ); ?></p>
            </div>
        </div>
		<?php
	}

	/**
	 * Handles saving the meta box.
	 *
	 * @param int $post_id Post ID.
	 * @param WP_Post $post Post object.
	 *
	 * @return null
	 */
	public function save_metabox( $post_id, $post ) {
		// Add nonce for security and authentication.
		$nonce_name   = isset( $_POST['_zvc_nonce'] ) ? $_POST['_zvc_nonce'] : '';
		$nonce_action = '_zvc_meeting_save';

		// Check if nonce is valid.
		if ( ! wp_verify_nonce( $nonce_name, $nonce_action ) ) {
			return;
		}

		// Check if user has permissions to save data.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Check if not an autosave.
		if ( wp_is_post_autosave( $post_id ) ) {
			return;
		}

		// Check if not a revision.
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		$create_meeting_arr = array(
			'userId'                    => sanitize_text_field( filter_input( INPUT_POST, 'userId' ) ),
			'start_date'                => sanitize_text_field( filter_input( INPUT_POST, 'start_date' ) ),
			'timezone'                  => sanitize_text_field( filter_input( INPUT_POST, 'timezone' ) ),
      'password'                  => sanitize_text_field( filter_input( INPUT_POST, 'password' ) ),
			'duration'                  => sanitize_text_field( filter_input( INPUT_POST, 'duration' ) ),
			'join_before_host'          => filter_input( INPUT_POST, 'join_before_host' ),
			'option_host_video'         => filter_input( INPUT_POST, 'option_host_video' ),
			'option_participants_video' => filter_input( INPUT_POST, 'option_participants_video' ),
			'option_mute_participants'  => filter_input( INPUT_POST, 'option_mute_participants' ),
			'option_auto_recording'     => filter_input( INPUT_POST, 'option_auto_recording' ),
			'alternative_host_ids'      => filter_input( INPUT_POST, 'alternative_host_ids', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY )
		);

		$create_meeting_arr['site_option_logged_in']    = filter_input( INPUT_POST, 'option_logged_in' );
		$create_meeting_arr['site_option_browser_join'] = filter_input( INPUT_POST, 'option_browser_join' );
		//Update Post Meta Values
		update_post_meta( $post_id, '_meeting_fields', $create_meeting_arr );

		//Create Zoom Meeting Now
		$meeting_id = get_post_meta( $post_id, '_meeting_zoom_meeting_id', true );
		if ( empty( $meeting_id ) ) {
			//Create new Zoom Meeting
			$this->create_zoom_meeting( $post );
		} else {
			//Update Zoom Meeting
			$this->update_zoom_meeting( $post, $meeting_id );
		}
	}

	/**
	 * Create real time zoom meetings
	 *
	 * @param $post
	 *
	 * @since 3.0.0
	 *
	 * @author Deepen
	 */
	private function create_zoom_meeting( $post ) {
		$mtg_param = array(
			'userId'                    => sanitize_text_field( filter_input( INPUT_POST, 'userId' ) ),
			'meetingTopic'              => $post->post_title,
			'start_date'                => sanitize_text_field( filter_input( INPUT_POST, 'start_date' ) ),
			'timezone'                  => sanitize_text_field( filter_input( INPUT_POST, 'timezone' ) ),
      'password'                  => sanitize_text_field( filter_input( INPUT_POST, 'password' ) ),
			'duration'                  => sanitize_text_field( filter_input( INPUT_POST, 'duration' ) ),
			'join_before_host'          => filter_input( INPUT_POST, 'join_before_host' ),
			'option_host_video'         => filter_input( INPUT_POST, 'option_host_video' ),
			'option_participants_video' => filter_input( INPUT_POST, 'option_participants_video' ),
			'option_mute_participants'  => filter_input( INPUT_POST, 'option_mute_participants' ),
			'option_auto_recording'     => filter_input( INPUT_POST, 'option_auto_recording' ),
			'alternative_host_ids'      => filter_input( INPUT_POST, 'alternative_host_ids', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY )
		);

		$meeting_created = json_decode( zoom_conference()->createAMeeting( $mtg_param ) );
		if ( empty( $meeting_created->error ) ) {
			update_post_meta( $post->ID, '_meeting_zoom_details', $meeting_created );
			update_post_meta( $post->ID, '_meeting_zoom_join_url', $meeting_created->join_url );
			update_post_meta( $post->ID, '_meeting_zoom_start_url', $meeting_created->start_url );
			update_post_meta( $post->ID, '_meeting_zoom_meeting_id', $meeting_created->id );
		}
	}

	/**
	 * Update real time zoom meetings
	 *
	 * @param $post
	 * @param $meeting_id
	 *
	 * @author Deepen
	 * @since 3.0.0
	 *
	 */
	private function update_zoom_meeting( $post, $meeting_id ) {
		$mtg_param = array(
			'meeting_id'                => $meeting_id,
			'topic'                     => $post->post_title,
			'start_date'                => filter_input( INPUT_POST, 'start_date' ),
			'timezone'                  => filter_input( INPUT_POST, 'timezone' ),
      'password'                  => filter_input( INPUT_POST, 'password' ),
			'duration'                  => filter_input( INPUT_POST, 'duration' ),
			'option_jbh'                => filter_input( INPUT_POST, 'join_before_host' ),
			'option_host_video'         => filter_input( INPUT_POST, 'option_host_video' ),
			'option_participants_video' => filter_input( INPUT_POST, 'option_participants_video' ),
			'option_mute_participants'  => filter_input( INPUT_POST, 'option_mute_participants' ),
			'option_auto_recording'     => filter_input( INPUT_POST, 'option_auto_recording' ),
			'alternative_host_ids'      => filter_input( INPUT_POST, 'alternative_host_ids', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY )
		);

		$meeting_updated = json_decode( zoom_conference()->updateMeetingInfo( $mtg_param ) );
		if ( empty( $meeting_updated->error ) ) {
			$meeting_info = json_decode( zoom_conference()->getMeetingInfo( $meeting_id ) );
			if ( ! empty( $meeting_info ) ) {
				update_post_meta( $post->ID, '_meeting_zoom_details', $meeting_info );
				update_post_meta( $post->ID, '_meeting_zoom_join_url', $meeting_info->join_url );
				update_post_meta( $post->ID, '_meeting_zoom_start_url', $meeting_info->start_url );
				update_post_meta( $post->ID, '_meeting_zoom_meeting_id', $meeting_info->id );
			}
		}
	}

	/**
	 * Single Page Template
	 *
	 * @param $template
	 *
	 * @return bool|string
	 * @since 3.0.0
	 *
	 * @author Deepen
	 */
	public function single( $template ) {
		global $post;

		if ( $post->post_type == 'zoom-meetings' ) {
			unset( $GLOBALS['zoom'] );

			$GLOBALS['zoom'] = get_post_meta( $post->ID, '_meeting_fields', true );

			$terms = get_the_terms( $post->ID, 'zoom-meeting' );
			if ( ! empty( $terms ) ) {
				$set_terms = array();
				foreach ( $terms as $term ) {
					$set_terms[] = $term->name;
				}
				$GLOBALS['zoom']['terms'] = $set_terms;
			}

			if ( isset( $_GET['type'] ) && $_GET['type'] === "meeting" && isset( $_GET['join'] ) ) {
				wp_enqueue_script( 'video-conferencing-with-zoom-api-react', ZVC_PLUGIN_VENDOR_ASSETS_URL . '/zoom/react.production.min.js', array( 'jquery' ), '16.8.6', true );
				wp_enqueue_script( 'video-conferencing-with-zoom-api-react-dom', ZVC_PLUGIN_VENDOR_ASSETS_URL . '/zoom/react-dom.production.min.js', array( 'jquery' ), '16.8.6', true );
				wp_enqueue_script( 'video-conferencing-with-zoom-api-redux', ZVC_PLUGIN_VENDOR_ASSETS_URL . '/zoom/redux.min.js', array( 'jquery' ), '16.8.6', true );
				wp_enqueue_script( 'video-conferencing-with-zoom-api-thunk', ZVC_PLUGIN_VENDOR_ASSETS_URL . '/zoom/redux-thunk.min.js', array( 'jquery' ), '16.8.6', true );
				wp_enqueue_script( 'video-conferencing-with-zoom-api-lodash', ZVC_PLUGIN_VENDOR_ASSETS_URL . '/zoom/lodash.min.js', array( 'jquery' ), '16.8.6', true );
				wp_enqueue_script( 'zoom-meeting-source', ZVC_PLUGIN_VENDOR_ASSETS_URL . '/zoom/zoomus-websdk.umd.min.js', array(
					'jquery',
					'video-conferencing-with-zoom-api-react',
					'video-conferencing-with-zoom-api-react-dom',
					'video-conferencing-with-zoom-api-redux',
					'video-conferencing-with-zoom-api-thunk',
					'video-conferencing-with-zoom-api-lodash'
				), '1.0.0', true );
				wp_enqueue_script( 'video-conferencing-with-zoom-api-browser', ZVC_PLUGIN_PUBLIC_ASSETS_URL . '/js/zoom-meeting.min.js', array( 'jquery' ), '16.8.6', true );
				wp_localize_script( 'video-conferencing-with-zoom-api-browser', 'zvc_ajx', array(
					'ajaxurl'       => admin_url( 'admin-ajax.php' ),
					'zvc_security'  => wp_create_nonce( "_nonce_zvc_security" ),
					'redirect_page' => esc_url( get_permalink( $post->ID ) )
				) );

				$GLOBALS['zoom']['meeting_id'] = sanitize_text_field( absint( vczapi_decrypt_url( $_GET['join'] ) ) );
				//Render View
				$templates[] = 'join-web-browser.php';
				?>
                <link rel='stylesheet' type="text/css" href="<?php echo ZVC_PLUGIN_VENDOR_ASSETS_URL . '/zoom/bootstrap.css'; ?>" media='all'>
                <link rel='stylesheet' type="text/css" href="<?php echo ZVC_PLUGIN_VENDOR_ASSETS_URL . '/zoom/react-select.css'; ?>" media='all'>
                <link rel='stylesheet' type="text/css" href="<?php echo ZVC_PLUGIN_PUBLIC_ASSETS_URL . '/css/main.min.css'; ?>" media='all'>
				<?php
				$template = vczapi_get_template( $templates );
			} else {
				//Render View
				$templates[] = 'single-meeting.php';
				$template    = vczapi_get_template( $templates );
			}
		}

		return $template;
	}

	/**
	 * Archive page template
	 *
	 * @param $template
	 *
	 * @return bool|string
	 * @author Deepen
	 * @since 3.0.0
	 *
	 */
	public function archive( $template ) {
		global $post;

		if ( $post->post_type == 'zoom-meetings' ) {
			//Render View
			$templates[] = 'archive-meetings.php';
			$template    = vczapi_get_template( $templates );
		}

		return $template;
	}

	/**
	 * Delete the meeting
	 *
	 * @param $post_id
	 *
	 * @since 3.0.0
	 *
	 * @author Deepen
	 */
	public function delete( $post_id ) {
		if ( get_post_type( $post_id ) === "zoom-meetings" ) {
			$meeting_id = get_post_meta( $post_id, '_meeting_zoom_meeting_id', true );
			if ( ! empty( $meeting_id ) ) {
				zoom_conference()->deleteAMeeting( $meeting_id );
			}
		}
	}

	public function admin_notices() {
		$screen = get_current_screen();

		//If not on the screen with ID 'edit-post' abort.
		if ( $screen->id === 'edit-zoom-meetings' || $screen->id === 'zoom-meetings' ) {
			video_conferencing_zoom_api_show_like_popup();
		} else {
			return;
		}
	}
}

new Zoom_Video_Conferencing_Admin_PostType();