<?php
/**
 * Lightweight GitHub release updater for Simply Design projects.
 *
 * Usage — plugin:
 *   new Simply_GitHub_Updater( 'plugin', 'simply-evite/simply-evite.php', 'staceyzav/simply-evite', SE_VERSION );
 *
 * Usage — theme:
 *   new Simply_GitHub_Updater( 'theme', 'simply-starter', 'staceyzav/simply-starter', '2.10.1' );
 *
 * Add to wp-config.php to enable auto-updates:
 *   define( 'SIMPLY_AUTO_UPDATE', true );
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Simply_GitHub_Updater' ) ) :

class Simply_GitHub_Updater {

	private $type;
	private $slug;
	private $repo;
	private $version;
	private $cache_key;

	public function __construct( $type, $slug, $repo, $version ) {
		$this->type      = $type;
		$this->slug      = $slug;
		$this->repo      = $repo;
		$this->version   = $version;
		$this->cache_key = 'sghu_' . md5( $repo );

		// Inject update directly into the transient on every admin load.
		// Bypasses WP Engine's wpe-update-source-selector filter interception.
		add_action( 'admin_init', [ $this, 'inject_update' ], 9999 );

		// Clear GitHub cache after an update completes so next version shows immediately.
		add_action( 'upgrader_process_complete', [ $this, 'purge_cache' ], 10, 2 );

		if ( $type === 'plugin' ) {
			add_filter( 'plugins_api', [ $this, 'plugin_info' ], 20, 3 );
			add_filter( 'upgrader_source_selection', [ $this, 'fix_folder_name' ], 10, 4 );
			add_filter( 'auto_update_plugin', [ $this, 'maybe_auto_update' ], 10, 2 );
		} else {
			add_filter( 'upgrader_source_selection', [ $this, 'fix_folder_name' ], 10, 4 );
			add_filter( 'auto_update_theme', [ $this, 'maybe_auto_update' ], 10, 2 );
		}
	}

	// ── Cache purge ───────────────────────────────────────────────────

	public function purge_cache() {
		delete_transient( $this->cache_key );
	}

	// ── Inject update into the transient directly ─────────────────────
	// Reads the existing update transient, adds our entry if needed, and
	// writes it back. Avoids relying on filter hooks WP Engine intercepts.

	public function inject_update() {
		if ( ! current_user_can( 'update_plugins' ) ) return;

		// Honor WP's "Check Again" button — bust our GitHub cache too.
		if ( isset( $_GET['force-check'] ) && '1' === $_GET['force-check'] ) {
			delete_transient( $this->cache_key );
		}

		$release = $this->get_release();
		if ( ! $release || ! version_compare( $release->version, $this->version, '>' ) ) return;

		$option_key = $this->type === 'plugin' ? 'update_plugins' : 'update_themes';
		$transient  = get_site_transient( $option_key );

		if ( ! is_object( $transient ) ) {
			$transient           = new stdClass();
			$transient->checked  = [];
			$transient->response = [];
		}

		if ( ! isset( $transient->response ) ) {
			$transient->response = [];
		}

		// Already registered — nothing to do.
		if ( isset( $transient->response[ $this->slug ] ) ) return;

		if ( $this->type === 'plugin' ) {
			$transient->response[ $this->slug ] = (object) [
				'slug'        => dirname( $this->slug ),
				'plugin'      => $this->slug,
				'new_version' => $release->version,
				'url'         => "https://github.com/{$this->repo}",
				'package'     => $release->zip_url,
			];
		} else {
			$transient->response[ $this->slug ] = [
				'theme'       => $this->slug,
				'new_version' => $release->version,
				'url'         => "https://github.com/{$this->repo}",
				'package'     => $release->zip_url,
			];
		}

		set_site_transient( $option_key, $transient );
	}

	// ── GitHub API ────────────────────────────────────────────────────

	private function get_release() {
		$cached = get_transient( $this->cache_key );
		if ( $cached instanceof stdClass ) return $cached;

		$headers = [
			'Accept'     => 'application/vnd.github.v3+json',
			'User-Agent' => 'Simply-Design-Updater/1.0',
		];

		if ( defined( 'SIMPLY_GITHUB_TOKEN' ) && SIMPLY_GITHUB_TOKEN ) {
			$headers['Authorization'] = 'Bearer ' . SIMPLY_GITHUB_TOKEN;
		}

		$response = wp_remote_get(
			"https://api.github.com/repos/{$this->repo}/tags",
			[ 'headers' => $headers, 'timeout' => 10 ]
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			set_transient( $this->cache_key, 'error', 2 * MINUTE_IN_SECONDS );
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ) );

		if ( empty( $data ) || ! isset( $data[0]->name ) ) {
			set_transient( $this->cache_key, 'error', 2 * MINUTE_IN_SECONDS );
			return null;
		}

		$release = (object) [
			'version'     => ltrim( $data[0]->name, 'v' ),
			'zip_url'     => "https://github.com/{$this->repo}/archive/refs/tags/{$data[0]->name}.zip",
			'description' => '',
			'published'   => '',
		];

		set_transient( $this->cache_key, $release, 5 * MINUTE_IN_SECONDS );
		return $release;
	}

	// ── Plugin info popup ─────────────────────────────────────────────

	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || $args->slug !== dirname( $this->slug ) ) {
			return $result;
		}

		$release = $this->get_release();
		if ( ! $release ) return $result;

		return (object) [
			'name'          => dirname( $this->slug ),
			'slug'          => dirname( $this->slug ),
			'version'       => $release->version,
			'last_updated'  => $release->published,
			'sections'      => [ 'changelog' => nl2br( esc_html( $release->description ) ) ],
			'download_link' => $release->zip_url,
		];
	}

	// ── Auto-update support ───────────────────────────────────────────

	public function maybe_auto_update( $update, $item ) {
		if ( ! defined( 'SIMPLY_AUTO_UPDATE' ) || ! SIMPLY_AUTO_UPDATE ) return $update;
		$id = $this->type === 'plugin' ? ( $item->plugin ?? '' ) : ( $item->theme ?? '' );
		return $id === $this->slug ? true : $update;
	}

	// ── Fix GitHub's auto-generated folder name after install ─────────

	public function fix_folder_name( $source, $remote_source, $upgrader, $hook_extra = [] ) {
		global $wp_filesystem;

		$correct = trailingslashit( $remote_source ) . ( $this->type === 'plugin' ? dirname( $this->slug ) : $this->slug ) . '/';

		if ( $source === $correct || ! is_dir( $source ) ) return $source;

		$expected_plugin = isset( $hook_extra['plugin'] ) && $hook_extra['plugin'] === $this->slug;
		$expected_theme  = isset( $hook_extra['theme'] )  && $hook_extra['theme']  === $this->slug;
		$main_file       = $this->type === 'plugin' ? basename( $this->slug ) : $this->slug . '.php';
		$has_main_file   = $wp_filesystem->exists( trailingslashit( $source ) . $main_file );
		if ( ! $expected_plugin && ! $expected_theme && ! $has_main_file ) return $source;

		if ( $wp_filesystem->move( $source, $correct ) ) {
			return $correct;
		}

		return $source;
	}
}

endif;
