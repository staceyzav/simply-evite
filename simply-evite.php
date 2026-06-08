<?php
/**
 * Plugin Name: Simply Evite
 * Plugin URI:  https://simplydesign.com
 * Description: Animated envelope invitation. Drop in the shortcode, point it at your invite image and a Google Form — done.
 * Author:      Simply Design
 * Author URI:  https://simplydesign.com
 * Version:     1.0.15
 * License:     GPL-2.0-or-later
 * Text Domain: simply-evite
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'SE_VERSION', '1.0.15' );
define( 'SE_URL',     plugin_dir_url( __FILE__ ) );

require_once plugin_dir_path( __FILE__ ) . 'includes/class-github-updater.php';
new Simply_GitHub_Updater( 'plugin', plugin_basename( __FILE__ ), 'staceyzav/simply-evite', SE_VERSION );

add_action( 'wp_enqueue_scripts', 'se_enqueue' );
function se_enqueue() {
	wp_enqueue_style(  'simply-evite', SE_URL . 'assets/css/simply-evite.css', array(), SE_VERSION );
	wp_enqueue_script( 'simply-evite', SE_URL . 'assets/js/simply-evite.js',  array(), SE_VERSION, true );
}

// ==========================================================================
// SHORTCODE — [simply_evite]
//
// image      — URL of the invitation image (required) — 1200×1600 portrait
// alt        — image alt text
// summary    — short event description shown in the sidebar
// date       — human-readable date string, e.g. "Saturday, June 14 at 6:00 PM"
// datetime   — machine-readable datetime, e.g. "2026-06-14 18:00:00"
//              drives Add to Calendar links and [simply_evite_countdown]
// duration   — event duration in minutes for calendar end time (default: 60)
// cal_title  — calendar event title (defaults to alt text)
// address    — location / address shown in the sidebar
// rsvp_url   — Google Form or any RSVP destination URL
// rsvp_text  — RSVP button label
// trigger    — "auto" (default) or "click"
// delay      — ms before auto-open (default: 1000)
// prompt     — hint text shown below the envelope (default: none)
// ==========================================================================

add_shortcode( 'simply_evite', 'se_shortcode' );

function se_shortcode( $atts ) {

	$atts = shortcode_atts( array(
		'image'     => '',
		'alt'       => __( "You're Invited", 'simply-evite' ),
		'summary'   => '',
		'hosts'     => '',
		'date'      => '',
		'datetime'  => '',
		'duration'  => '60',
		'cal_title' => '',
		'address'   => '',
		'bring'     => '',
		'attire'    => '',
		'rsvp_url'  => '',
		'rsvp_text' => __( 'RSVP Now', 'simply-evite' ),
		'trigger'   => 'auto',
		'delay'     => '1000',
		'prompt'    => '',
	), $atts, 'simply_evite' );

	if ( empty( $atts['image'] ) ) return '';

	$image_url   = esc_url( $atts['image'] );
	$alt         = esc_attr( $atts['alt'] );
	$inline_tags = [ 'br' => [], 'strong' => [], 'em' => [] ];
	$summary     = wp_kses( $atts['summary'], $inline_tags );
	$hosts       = wp_kses( $atts['hosts'],   $inline_tags );
	$date        = esc_html( $atts['date'] );
	$address     = wp_kses( $atts['address'], $inline_tags );
	$bring       = wp_kses( $atts['bring'],   $inline_tags );
	$attire      = wp_kses( $atts['attire'],  $inline_tags );
	$rsvp_url    = esc_url( $atts['rsvp_url'] );
	$rsvp_text   = esc_html( $atts['rsvp_text'] );
	$trigger     = in_array( $atts['trigger'], array( 'click', 'auto' ), true ) ? $atts['trigger'] : 'auto';
	$delay       = absint( $atts['delay'] );
	$prompt      = esc_html( $atts['prompt'] );
	$panel_id    = 'se-panel-' . wp_unique_id();

	// ── Add to Calendar ────────────────────────────────────────────────
	$cal_links_html = '';
	if ( ! empty( $atts['datetime'] ) ) {
		$ts_start  = strtotime( $atts['datetime'] );
		$ts_end    = $ts_start + ( absint( $atts['duration'] ) * 60 );
		$cal_start = date( 'Ymd\THis', $ts_start );
		$cal_end   = date( 'Ymd\THis', $ts_end );
		$cal_name  = ! empty( $atts['cal_title'] ) ? $atts['cal_title'] : $atts['alt'];

		$gcal_url = add_query_arg( array(
			'action'   => 'TEMPLATE',
			'text'     => rawurlencode( $cal_name ),
			'dates'    => $cal_start . '/' . $cal_end,
			'details'  => rawurlencode( strip_tags( $atts['summary'] ) ),
			'location' => rawurlencode( strip_tags( $atts['address'] ) ),
		), 'https://calendar.google.com/calendar/render' );

		ob_start();
		?>
		<div class="se-sidebar__section">
			<span class="se-sidebar__label"><?php esc_html_e( 'Add to calendar', 'simply-evite' ); ?></span>
			<div class="se-cal-links">
				<a href="<?php echo esc_url( $gcal_url ); ?>"
				   class="se-cal-link"
				   target="_blank"
				   rel="noopener noreferrer">Google</a>
				<a href="#"
				   class="se-cal-link se-cal-ics"
				   data-title="<?php echo esc_attr( $cal_name ); ?>"
				   data-start="<?php echo esc_attr( $cal_start ); ?>"
				   data-end="<?php echo esc_attr( $cal_end ); ?>"
				   data-location="<?php echo esc_attr( strip_tags( $atts['address'] ) ); ?>"
				   data-description="<?php echo esc_attr( strip_tags( $atts['summary'] ) ); ?>">Apple / Outlook</a>
			</div>
		</div>
		<?php
		$cal_links_html = ob_get_clean();
	}

	$has_sidebar = $summary || $hosts || $date || $address || $bring || $attire || $rsvp_url;

	ob_start();
	?>
	<div class="se-evite" data-trigger="<?php echo esc_attr( $trigger ); ?>" data-delay="<?php echo esc_attr( $delay ); ?>">

		<div class="se-stage">

			<!-- Piece 1: envelope back rectangle — always visible -->
			<div class="se-env-back" aria-hidden="true"></div>

			<!-- Piece 2: open flap triangle — peeking above card at top -->
			<div class="se-env-flap" aria-hidden="true"></div>

			<!-- The invite card — centered, animates out and back in -->
			<div class="se-card-wrap">
				<div class="se-card">
					<img src="<?php echo $image_url; ?>" alt="<?php echo $alt; ?>" loading="eager">
				</div>
			</div>

			<!-- Piece 3: envelope front — V-notch cut out at top -->
			<div class="se-env-front" aria-hidden="true"></div>

		</div>

		<?php if ( $prompt ) : ?>
			<p class="se-evite__prompt"><?php echo $prompt; ?></p>
		<?php endif; ?>

		<?php if ( $has_sidebar ) : ?>

		<!-- Sidebar toggle — fixed, fades in after animation -->
		<button class="se-sidebar-toggle"
			aria-label="<?php esc_attr_e( 'Event details', 'simply-evite' ); ?>"
			aria-expanded="false"
			aria-controls="<?php echo esc_attr( $panel_id ); ?>">
			<span class="se-hamburger" aria-hidden="true">
				<span></span><span></span><span></span>
			</span>
			<span class="se-toggle-label" aria-hidden="true"><?php esc_html_e( 'more info', 'simply-evite' ); ?></span>
		</button>

		<!-- Sidebar panel — fixed, slides in from right -->
		<div class="se-sidebar-panel" id="<?php echo esc_attr( $panel_id ); ?>" role="complementary" aria-label="<?php esc_attr_e( 'Event details', 'simply-evite' ); ?>">

			<?php if ( $rsvp_url ) : ?>
			<div class="se-sidebar__section se-sidebar__section--rsvp-top">
				<a href="<?php echo $rsvp_url; ?>" class="se-sidebar__rsvp button" target="_blank" rel="noopener noreferrer">
					<?php echo $rsvp_text; ?>
				</a>
			</div>
			<?php endif; ?>

			<?php if ( $summary ) : ?>
			<div class="se-sidebar__section">
				<span class="se-sidebar__label"><?php esc_html_e( 'What', 'simply-evite' ); ?></span>
				<p class="se-sidebar__summary"><?php echo $summary; ?></p>
			</div>
			<?php endif; ?>

			<?php if ( $hosts ) : ?>
			<div class="se-sidebar__section">
				<span class="se-sidebar__label"><?php esc_html_e( 'Hosted by', 'simply-evite' ); ?></span>
				<span class="se-sidebar__value"><?php echo $hosts; ?></span>
			</div>
			<?php endif; ?>

			<?php if ( $date ) : ?>
			<div class="se-sidebar__section">
				<span class="se-sidebar__label"><?php esc_html_e( 'When', 'simply-evite' ); ?></span>
				<span class="se-sidebar__value"><?php echo $date; ?></span>
			</div>
			<?php endif; ?>

			<?php echo $cal_links_html; ?>

			<?php if ( $address ) : ?>
			<div class="se-sidebar__section">
				<span class="se-sidebar__label"><?php esc_html_e( 'Where', 'simply-evite' ); ?></span>
				<span class="se-sidebar__value"><?php echo $address; ?></span>
			</div>
			<?php endif; ?>

			<?php if ( $attire ) : ?>
			<div class="se-sidebar__section">
				<span class="se-sidebar__label"><?php esc_html_e( 'Attire', 'simply-evite' ); ?></span>
				<span class="se-sidebar__value"><?php echo $attire; ?></span>
			</div>
			<?php endif; ?>

			<?php if ( $bring ) : ?>
			<div class="se-sidebar__section">
				<span class="se-sidebar__label"><?php esc_html_e( 'What to bring', 'simply-evite' ); ?></span>
				<span class="se-sidebar__value"><?php echo $bring; ?></span>
			</div>
			<?php endif; ?>

			<?php if ( $rsvp_url ) : ?>
			<div class="se-sidebar__section">
				<a href="<?php echo $rsvp_url; ?>" class="se-sidebar__rsvp button" target="_blank" rel="noopener noreferrer">
					<?php echo $rsvp_text; ?>
				</a>
			</div>
			<?php endif; ?>

		</div>

		<?php endif; ?>

	</div>
	<?php
	return ob_get_clean();
}

// ==========================================================================
// SHORTCODE — [simply_evite_countdown]
//
// datetime — machine-readable datetime, e.g. "2026-08-15 18:00:00" (required)
// label    — text below the clock, e.g. "Until the Party"
// ==========================================================================

add_shortcode( 'simply_evite_countdown', 'se_countdown_shortcode' );

function se_countdown_shortcode( $atts ) {
	$atts = shortcode_atts( array(
		'datetime' => '',
		'label'    => '',
	), $atts, 'simply_evite_countdown' );

	if ( empty( $atts['datetime'] ) ) return '';

	$target = esc_attr( date( 'Y-m-d\TH:i:s', strtotime( $atts['datetime'] ) ) );
	$label  = esc_html( $atts['label'] );
	$uid    = wp_unique_id( 'se-cd-' );

	ob_start();
	?>
	<div class="se-countdown" data-target="<?php echo $target; ?>">
		<div class="se-countdown__unit">
			<span class="se-countdown__number se-cd-days" id="<?php echo $uid; ?>-days">--</span>
			<span class="se-countdown__label"><?php esc_html_e( 'Days', 'simply-evite' ); ?></span>
		</div>
		<div class="se-countdown__unit">
			<span class="se-countdown__number se-cd-hours" id="<?php echo $uid; ?>-hours">--</span>
			<span class="se-countdown__label"><?php esc_html_e( 'Hours', 'simply-evite' ); ?></span>
		</div>
		<div class="se-countdown__unit">
			<span class="se-countdown__number se-cd-mins" id="<?php echo $uid; ?>-mins">--</span>
			<span class="se-countdown__label"><?php esc_html_e( 'Mins', 'simply-evite' ); ?></span>
		</div>
		<div class="se-countdown__unit">
			<span class="se-countdown__number se-cd-secs" id="<?php echo $uid; ?>-secs">--</span>
			<span class="se-countdown__label"><?php esc_html_e( 'Secs', 'simply-evite' ); ?></span>
		</div>
		<?php if ( $label ) : ?>
		<p class="se-countdown__message"><?php echo $label; ?></p>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}
