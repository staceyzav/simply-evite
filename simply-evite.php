<?php
/**
 * Plugin Name: Simply Evite
 * Plugin URI:  https://simplydesign.com
 * Description: Animated envelope invitation. Drop in the shortcode, point it at your invite image and a Google Form — done.
 * Author:      Simply Design
 * Author URI:  https://simplydesign.com
 * Version:     1.0.7
 * License:     GPL-2.0-or-later
 * Text Domain: simply-evite
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'SE_VERSION', '1.0.7' );
define( 'SE_URL',     plugin_dir_url( __FILE__ ) );

require_once plugin_dir_path( __FILE__ ) . 'includes/class-github-updater.php';
new Simply_GitHub_Updater( 'plugin', plugin_basename( __FILE__ ), 'staceyzav/simply-evite', SE_VERSION );

// TEMP DEBUG — remove after confirming updater works
add_action( 'admin_notices', 'se_debug_updater' );
function se_debug_updater() {
	if ( ! current_user_can( 'manage_options' ) ) return;
	$response = wp_remote_get( 'https://api.github.com/repos/staceyzav/simply-evite/tags', [
		'headers' => [ 'Accept' => 'application/vnd.github.v3+json', 'User-Agent' => 'Simply-Design-Updater/1.0' ],
		'timeout' => 10,
	] );
	if ( is_wp_error( $response ) ) {
		$msg = 'GitHub API error: ' . $response->get_error_message();
	} else {
		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ) );
		$tag  = ( is_array( $data ) && ! empty( $data[0]->name ) ) ? $data[0]->name : 'none';
		$msg  = 'GitHub API status: ' . $code . ' — latest tag: ' . $tag . ' — plugin slug: ' . plugin_basename( __FILE__ );
	}
	echo '<div class="notice notice-info"><p><strong>SE Updater Debug:</strong> ' . esc_html( $msg ) . '</p></div>';
}

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
// date       — date/time string, e.g. "Saturday, June 14 at 6:00 PM"
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

	$image_url = esc_url( $atts['image'] );
	$alt       = esc_attr( $atts['alt'] );
	$inline_tags = [ 'br' => [], 'strong' => [], 'em' => [] ];
	$summary     = wp_kses( $atts['summary'], $inline_tags );
	$hosts       = wp_kses( $atts['hosts'],   $inline_tags );
	$date        = esc_html( $atts['date'] );
	$address     = wp_kses( $atts['address'], $inline_tags );
	$bring       = wp_kses( $atts['bring'],   $inline_tags );
	$attire      = wp_kses( $atts['attire'],  $inline_tags );
	$rsvp_url  = esc_url( $atts['rsvp_url'] );
	$rsvp_text = esc_html( $atts['rsvp_text'] );
	$trigger   = in_array( $atts['trigger'], array( 'click', 'auto' ), true ) ? $atts['trigger'] : 'auto';
	$delay     = absint( $atts['delay'] );
	$prompt    = esc_html( $atts['prompt'] );
	$panel_id  = 'se-panel-' . wp_unique_id();

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

			<!-- Scroll hint — fades in after animation, hides on scroll -->
			<div class="se-scroll-hint" aria-hidden="true">
				<span class="se-scroll-hint__text"><?php esc_html_e( 'more info', 'simply-evite' ); ?></span>
				<svg class="se-scroll-hint__chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<polyline points="6 9 12 15 18 9"></polyline>
				</svg>
			</div>

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
		</button>

		<!-- Sidebar panel — fixed, slides in from right -->
		<div class="se-sidebar-panel" id="<?php echo esc_attr( $panel_id ); ?>" role="complementary" aria-label="<?php esc_attr_e( 'Event details', 'simply-evite' ); ?>">

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
