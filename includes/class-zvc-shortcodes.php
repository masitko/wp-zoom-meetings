<?php
/**
 * Shortcodes Controller
 *
 * @since   3.0.0
 * @author  Deepen
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Zoom_Video_Conferencing_Shorcodes {

	/**
	 * Define post type
	 *
	 * @var string
	 */
	private $post_type = 'zoom-meetings';

	/**
	 * Meeting list
	 * @var string
	 */
	public static $meetings_list_number = '0';

	/**
	 * Zoom_Video_Conferencing_Shorcodes constructor.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 100 );
		add_shortcode( 'zoom_api_link', array( $this, 'render_main' ) );
		add_shortcode( 'zoom_list_meetings', array( $this, 'show_meetings' ) );
	}

	public function enqueue_scripts() {
		wp_enqueue_style( 'video-conferencing-with-zoom-api' );
	}

	/**
	 * Render output for shortcode
	 *
	 * @param      $atts
	 * @param null $content
	 *
	 * @return string
	 * @author Deepen
	 * @since  3.0.0
	 */
	function render_main( $atts, $content = null ) {
		ob_start();

		wp_enqueue_script( 'video-conferencing-with-zoom-api-moment' );
		wp_enqueue_script( 'video-conferencing-with-zoom-api-moment-timezone' );
		wp_enqueue_script( 'video-conferencing-with-zoom-api' );

		extract( shortcode_atts( array(
			'meeting_id' => 'javascript:void(0);',
			'link_only'  => 'no',
		), $atts ) );

		unset( $GLOBALS['vanity_uri'] );
		unset( $GLOBALS['zoom_meetings'] );

		$vanity_uri               = get_option( 'zoom_vanity_url' );
		$meeting                  = $this->fetch_meeting( $meeting_id );
		$GLOBALS['vanity_uri']    = $vanity_uri;
		$GLOBALS['zoom_meetings'] = $meeting;

		if ( ! empty( $meeting ) && ! empty( $meeting->code ) && $meeting->code === 3001 ) {
			?>
            <p class="dpn-error dpn-mtg-not-found"><?php echo $meeting->message; ?></p>
			<?php
		} else {
			if ( $link_only === "yes" ) {
				$this->generate_link_only();
			} else {
				if ( $meeting ) {
					//Get Template
					vczapi_get_template( array( 'shortcode/zoom-shortcode.php' ), true );
				} else {
					printf( __( 'Please try again ! Some error occured while trying to fetch meeting with id:  %d', 'video-conferencing-with-zoom-api' ), $meeting_id );
				}
			}
		}

		return ob_get_clean();
	}

	/**
	 * @param $args
	 *
	 * @return string
	 * @since  3.0.0
	 */
	public function show_meetings( $args ) {
		self::$meetings_list_number ++;
		$args = shortcode_atts(
			array(
				'per_page' => 5,
				'category' => '',
			),
			$args, 'zoom_list_meetings'
		);
		if ( is_front_page() ) {
			$paged = ( get_query_var( 'page' ) ) ? get_query_var( 'page' ) : 1;
		} else {
			$paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;
		}

		$query_args = array(
			'post_type'      => $this->post_type,
			'posts_per_page' => $args['per_page'],
			'post_status'    => 'publish',
			'paged'          => $paged,
		);

		if ( ! empty( $args['category'] ) ) {
			$query_args['tax_query'] = [
				[
					'taxonomy' => 'zoom-meeting',
					'field'    => 'slug',
					'terms'    => [
						$args['category']
					]
				]
			];
		}
		$query         = apply_filters( 'vczapi_meeting_list_query_args', $query_args );
		$zoom_meetings = new \WP_Query( $query );
		$content       = '';

		unset( $GLOBALS['zoom_meetings'] );
		$GLOBALS['zoom_meetings'] = $zoom_meetings;

		if ( $zoom_meetings->have_posts() ):
			ob_start();
			vczapi_get_template( array( 'shortcode-listing.php' ), true, false );
			$content .= ob_get_clean();
		endif;

		return $content;
	}

	/**
	 * Pagination
	 *
	 * @param $query
	 */
	public static function pagination( $query ) {
		$big = 999999999999999;
		if ( is_front_page() ) {
			$paged = ( get_query_var( 'page' ) ) ? get_query_var( 'page' ) : 1;
		} else {
			$paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;
		}
		echo paginate_links( array(
			'base'    => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
			'format'  => '?paged=%#%',
			'current' => max( 1, $paged ),
			'total'   => $query->max_num_pages
		) );
	}

	/**
	 * Output only singel link
	 *
	 * @since  3.0.4
	 * @author Deepen
	 */
	public function generate_link_only() {
		//Get Template
		vczapi_get_template( array( 'shortcode/zoom-single-link.php' ), true, false );
	}

	/**
	 * Get Meeting INFO
	 *
	 * @param $meeting_id
	 *
	 * @return bool|mixed|null
	 */
	private function fetch_meeting( $meeting_id ) {
		$meeting = json_decode( zoom_conference()->getMeetingInfo( $meeting_id ) );
		if ( ! empty( $meeting->error ) ) {
			return false;
		}

		return $meeting;
	}
}

new Zoom_Video_Conferencing_Shorcodes();