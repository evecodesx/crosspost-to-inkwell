<?php
/**
 * Plugin Name:       Crosspost to Inkwell
 * Plugin URI:        https://wordpress.org/plugins/crosspost-to-inkwell
 * Description:       Automatically crossposts WordPress blog posts to your Inkwell.social journal. Requires an Inkwell Plus subscription for API write access.
 * Version:           1.0.0
 * Author:            evecodes
 * Author URI:        https://github.com/evecodesx
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       crosspost-to-inkwell
 * Requires at least: 5.8
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'INKWELL_VERSION',     '1.0.0' );
define( 'INKWELL_API_BASE',    'https://api.inkwell.social' );
define( 'INKWELL_OPTION_KEY',  'inkwell_crosspost_settings' );

// ============================================================
// TEXTDOMAIN & SCRIPTS
// ============================================================

add_action( 'admin_enqueue_scripts', 'inkwell_enqueue_admin_scripts' );
function inkwell_enqueue_admin_scripts( $hook ) {
	// Meta box script — only load on post edit screens.
	if ( in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
		wp_register_script( 'inkwell-meta-box', false, [], INKWELL_VERSION, true );
		wp_enqueue_script( 'inkwell-meta-box' );
	}

	// Debug log page script — only load on the debug log page.
	if ( 'settings_page_crosspost-to-inkwell-debug' === $hook ) {
		wp_register_script( 'inkwell-debug-log', false, [], INKWELL_VERSION, true );
		wp_enqueue_script( 'inkwell-debug-log' );
		wp_localize_script( 'inkwell-debug-log', 'inkwellDebugData', [
			'clearNonce' => wp_create_nonce( 'inkwell_clear_log' ),
			'fetchNonce' => wp_create_nonce( 'inkwell_fetch_log' ),
			'colors'     => [
				'success'  => '#4ec994',
				'error'    => '#f48771',
				'request'  => '#9cdcfe',
				'response' => '#ce9178',
				'default'  => '#d4d4d4',
			],
			'i18n'       => [
				'noEntries' => __( 'No log entries yet.', 'crosspost-to-inkwell' ),
				'confirm'   => __( 'Clear the entire debug log? This cannot be undone.', 'crosspost-to-inkwell' ),
			],
		] );
		wp_add_inline_script( 'inkwell-debug-log', '
(function(){
	if (typeof inkwellDebugData === "undefined") return;
	var s         = inkwellDebugData;
	var out       = document.getElementById("inkwell_log_output");
	var btnClear  = document.getElementById("inkwell_clear_log");
	var btnRefresh= document.getElementById("inkwell_refresh_log");
	if (!out) return;

	function colorLine(line){
		var c = s.colors["default"];
		if (line.indexOf("\u2705") !== -1)          c = s.colors.success;
		else if (line.indexOf("\u274c") !== -1)      c = s.colors.error;
		else if (line.indexOf("REQUEST") !== -1)     c = s.colors.request;
		else if (line.indexOf("RESPONSE") !== -1)    c = s.colors.response;
		return "<span style=\"color:"+c+"\">" + line.replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;") + "</span>";
	}

	function renderLog(entries){
		if (!entries || entries.length === 0){
			out.innerHTML = "<span style=\"color:#666\">" + s.i18n.noEntries + "</span>";
			if (btnClear) btnClear.disabled = true;
			return;
		}
		out.innerHTML = entries.slice().reverse().map(colorLine).join("\n");
		if (btnClear) btnClear.disabled = false;
	}

	if (btnRefresh){
		btnRefresh.addEventListener("click", function(){
			btnRefresh.disabled = true;
			var fd = new FormData();
			fd.append("action","inkwell_fetch_debug_log");
			fd.append("nonce", s.fetchNonce);
			fetch(ajaxurl,{method:"POST",body:fd})
				.then(function(r){ return r.json(); })
				.then(function(d){ if(d.success) renderLog(d.data); })
				.finally(function(){ btnRefresh.disabled = false; });
		});
	}

	if (btnClear){
		btnClear.addEventListener("click", function(){
			if (!confirm(s.i18n.confirm)) return;
			btnClear.disabled = true;
			var fd = new FormData();
			fd.append("action","inkwell_clear_debug_log");
			fd.append("nonce", s.clearNonce);
			fetch(ajaxurl,{method:"POST",body:fd})
				.then(function(r){ return r.json(); })
				.then(function(d){ if(d.success) renderLog([]); })
				.finally(function(){ btnClear.disabled = false; });
		});
	}
})();
' );
	}
	if ( 'settings_page_crosspost-to-inkwell' === $hook ) {
		wp_register_script( 'inkwell-settings', false, [], INKWELL_VERSION, true );
		wp_enqueue_script( 'inkwell-settings' );
		wp_localize_script( 'inkwell-settings', 'inkwellSettingsData', [
			'nonce' => wp_create_nonce( 'inkwell_verify' ),
			'i18n'  => [
				'checking'  => __( '⏳ Checking…', 'crosspost-to-inkwell' ),
				'reqfailed' => __( '❌ Request failed', 'crosspost-to-inkwell' ),
			],
		] );
		wp_add_inline_script( 'inkwell-settings', '
(function(){
	var btn = document.getElementById("inkwell_verify_btn");
	var out = document.getElementById("inkwell_verify_result");
	if (!btn || typeof inkwellSettingsData === "undefined") return;
	var s = inkwellSettingsData;
	btn.addEventListener("click", function(){
		btn.disabled = true;
		out.textContent = s.i18n.checking;
		var fd = new FormData();
		fd.append("action", "inkwell_verify_key");
		fd.append("nonce", s.nonce);
		var keyField = document.getElementById("inkwell_api_key");
		if (keyField && keyField.value) fd.append("api_key", keyField.value);
		fetch(ajaxurl, {method:"POST", body:fd})
			.then(function(r){ return r.json(); })
			.then(function(d){
				out.style.color = d.success ? "green" : "red";
				out.innerHTML = d.data || "";
				btn.disabled = false;
			})
			.catch(function(){
				out.style.color = "red";
				out.textContent = s.i18n.reqfailed;
				btn.disabled = false;
			});
	});
})();
(function(){
	var field = document.getElementById("inkwell_api_key");
	var btn   = document.getElementById("inkwell_toggle_key");
	if (!field || !btn) return;
	btn.addEventListener("click", function(){
		var show = field.type === "password";
		field.type = show ? "text" : "password";
		btn.textContent = show ? "\uD83D\uDD12" : "\uD83D\uDC41";
		btn.setAttribute("aria-label", show ? "Hide API key" : "Show API key");
	});
	// Clear the saved connected status if the user edits the key field.
	field.addEventListener("input", function(){
		var out = document.getElementById("inkwell_verify_result");
		if (out) out.innerHTML = "";
	});
})();
' );
	}
}

// ============================================================
// ACTIVATION
// ============================================================

register_activation_hook( __FILE__, 'inkwell_activate' );
function inkwell_activate() {
	$defaults = [
		'api_key'              => '',
		'connected_display_name' => '',
		'connected_username'     => '',
		'debug_log_enabled'    => '0',
		'privacy'              => 'public',
		'default_category'     => '',
		'auto_crosspost'       => '1',
		'crosspost_on_update'  => '0',
		'post_types'           => [ 'post' ],
		'send_featured_image'  => '1',
		'send_excerpt'         => '0',
		'hashtag_tags'         => '1',
	];
	if ( ! get_option( INKWELL_OPTION_KEY ) ) {
		add_option( INKWELL_OPTION_KEY, $defaults );
	}
}

// ============================================================
// ADMIN MENU
// ============================================================

add_action( 'admin_menu', 'inkwell_admin_menu' );
function inkwell_admin_menu() {
	add_options_page(
		__( 'Crosspost to Inkwell', 'crosspost-to-inkwell' ),
		__( 'Crosspost to Inkwell', 'crosspost-to-inkwell' ),
		'manage_options',
		'crosspost-to-inkwell',
		'inkwell_settings_page'
	);
	add_submenu_page(
		null, // hidden — accessible via direct URL, linked from settings page
		__( 'Inkwell Debug Log', 'crosspost-to-inkwell' ),
		__( 'Inkwell Debug Log', 'crosspost-to-inkwell' ),
		'manage_options',
		'crosspost-to-inkwell-debug',
		'inkwell_debug_log_page'
	);
}

add_action( 'admin_init', 'inkwell_register_settings' );
function inkwell_register_settings() {
	register_setting( 'inkwell_crosspost_group', INKWELL_OPTION_KEY, [
		'sanitize_callback' => 'inkwell_sanitize_settings',
		'type'              => 'array',
		'description'       => 'Crosspost to Inkwell plugin settings',
	] );
}

function inkwell_sanitize_settings( $input ) {
	$clean = [];
	$clean['api_key']             = sanitize_text_field( trim( $input['api_key'] ?? '' ) );
	$clean['privacy']             = in_array( $input['privacy'] ?? '', [ 'public', 'friends_only', 'private' ], true )
		? $input['privacy'] : 'public';
	$clean['default_category']    = sanitize_text_field( trim( $input['default_category'] ?? '' ) );
	$clean['auto_crosspost']      = ! empty( $input['auto_crosspost'] ) ? '1' : '0';
	$clean['crosspost_on_update'] = ! empty( $input['crosspost_on_update'] ) ? '1' : '0';
	$clean['post_types']          = array_map( 'sanitize_key', (array) ( $input['post_types'] ?? [ 'post' ] ) );
	$clean['send_featured_image'] = ! empty( $input['send_featured_image'] ) ? '1' : '0';
	$clean['send_excerpt']        = ! empty( $input['send_excerpt'] ) ? '1' : '0';
	$clean['hashtag_tags']        = ! empty( $input['hashtag_tags'] ) ? '1' : '0';
	$clean['debug_log_enabled']   = ! empty( $input['debug_log_enabled'] ) ? '1' : '0';
	// Preserve connected account info — set by verify, not by the settings form.
	// Use $input values if present (AJAX verify sets them), otherwise keep existing.
	$existing = get_option( INKWELL_OPTION_KEY, [] );
	$clean['connected_display_name'] = isset( $input['connected_display_name'] )
		? wp_kses( $input['connected_display_name'], [] )
		: wp_kses( $existing['connected_display_name'] ?? '', [] );
	$clean['connected_username'] = isset( $input['connected_username'] )
		? sanitize_text_field( $input['connected_username'] )
		: sanitize_text_field( $existing['connected_username'] ?? '' );
	// Clear connected info if the API key was changed.
	if ( $clean['api_key'] !== ( $existing['api_key'] ?? '' ) ) {
		$clean['connected_display_name'] = '';
		$clean['connected_username']     = '';
	}
	return $clean;
}

// ============================================================
// SETTINGS PAGE
// ============================================================

function inkwell_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) return;

	$opts = get_option( INKWELL_OPTION_KEY, [] );

	$test_result   = '';

	if ( isset( $_POST['inkwell_test_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['inkwell_test_nonce'] ) ), 'inkwell_test' ) ) {
		$test_result = inkwell_send_test_entry( $opts );
	}
	?>
	<div class="wrap">
		<h1 style="display:flex;align-items:center;gap:10px;">
			<svg width="28" height="28" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
				<rect width="32" height="32" rx="8" fill="#2563EB"/>
				<path d="M8 22 Q12 10 16 22 Q20 10 24 22" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
			</svg>
			<?php esc_html_e( 'Crosspost to Inkwell', 'crosspost-to-inkwell' ); ?>
			<a href="<?php echo esc_url( admin_url( 'options-general.php?page=crosspost-to-inkwell-debug' ) ); ?>"
			   style="font-size:13px;font-weight:normal;margin-left:8px;padding:4px 10px;background:#f6f7f7;border:1px solid #ddd;border-radius:4px;text-decoration:none;color:#555;vertical-align:middle;">
				🪲 <?php esc_html_e( 'Debug Log', 'crosspost-to-inkwell' ); ?>
			</a>
		</h1>

		<?php if ( $test_result ) : ?>
			<div class="notice <?php echo esc_attr( strpos( $test_result, '✅' ) !== false ? 'notice-success' : 'notice-error' ); ?> is-dismissible">
				<p><?php echo wp_kses_post( $test_result ); ?></p>
			</div>
		<?php endif; ?>

		<div style="display:flex;gap:24px;margin-top:16px;flex-wrap:wrap;align-items:flex-start;">

			<div style="flex:1;min-width:420px;">
				<form method="post" action="options.php">
					<?php settings_fields( 'inkwell_crosspost_group' ); ?>

					<!-- Connection -->
					<div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px 24px;margin-bottom:20px;">
						<h2 style="margin-top:0;font-size:1.05em;border-bottom:1px solid #eee;padding-bottom:10px;"><?php esc_html_e( '🔗 API Connection', 'crosspost-to-inkwell' ); ?></h2>
						<table class="form-table" role="presentation">
							<tr>
								<th><label for="inkwell_api_key"><?php esc_html_e( 'API Key', 'crosspost-to-inkwell' ); ?></label></th>
								<td>
									<div style="display:flex;align-items:center;gap:6px;">
										<input type="password" id="inkwell_api_key"
											name="<?php echo esc_attr( INKWELL_OPTION_KEY ); ?>[api_key]"
											value="<?php echo esc_attr( $opts['api_key'] ?? '' ); ?>"
											class="regular-text" autocomplete="new-password" placeholder="ink_..." />
										<button type="button" id="inkwell_toggle_key"
											title="<?php esc_attr_e( 'Show/hide API key', 'crosspost-to-inkwell' ); ?>"
											style="background:none;border:1px solid #ddd;border-radius:4px;padding:4px 7px;cursor:pointer;line-height:1;color:#555;font-size:16px;"
											aria-label="<?php esc_attr_e( 'Show API key', 'crosspost-to-inkwell' ); ?>">
											👁
										</button>
									</div>
									<p class="description">
										<?php
										printf(
											/* translators: %s: link to Inkwell API settings */
											esc_html__( 'Create an API key at %s. Keys start with ink_ and are shown only once.', 'crosspost-to-inkwell' ),
											'<a href="https://inkwell.social/settings/api" target="_blank"><strong>inkwell.social → Settings → API</strong></a>'
										);
										?>
										<br>
										<?php
										printf(
											/* translators: %s: link to Inkwell billing page */
											esc_html__( 'Note: write access requires an %s.', 'crosspost-to-inkwell' ),
											'<a href="https://inkwell.social/settings/billing" target="_blank">' . esc_html__( 'Inkwell Plus subscription', 'crosspost-to-inkwell' ) . '</a>'
										);
										?>
									</p>
									<div style="margin-top:10px;">
										<a href="https://inkwell.social/settings/api" target="_blank" class="button button-secondary" style="margin-right:8px;">
											<?php esc_html_e( '🔑 Create API Key', 'crosspost-to-inkwell' ); ?>
										</a>
										<button type="button" id="inkwell_verify_btn" class="button button-secondary">
											<?php esc_html_e( '✔ Verify Key', 'crosspost-to-inkwell' ); ?>
										</button>
										<span id="inkwell_verify_result" style="margin-left:10px;font-size:13px;"><?php
									$saved_name   = $opts['connected_display_name'] ?? '';
									$saved_handle = $opts['connected_username'] ?? '';
									if ( $saved_name && $saved_handle ) {
										$profile = 'https://inkwell.social/@' . $saved_handle;
										echo '✅ ' . sprintf(
											wp_kses(
												/* translators: 1: display name, 2: profile URL, 3: username handle */
												__( 'Connected as <strong>%1$s</strong> (<a href="%2$s" target="_blank">@%3$s</a>)', 'crosspost-to-inkwell' ),
												[ 'strong' => [], 'a' => [ 'href' => [], 'target' => [] ] ]
											),
											esc_html( $saved_name ),
											esc_url( $profile ),
											esc_html( $saved_handle )
										);
									}
								?></span>
									</div>
								</td>
							</tr>
						</table>
					</div>

					<!-- Entry Settings -->
					<div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px 24px;margin-bottom:20px;">
						<h2 style="margin-top:0;font-size:1.05em;border-bottom:1px solid #eee;padding-bottom:10px;"><?php esc_html_e( '📝 Entry Settings', 'crosspost-to-inkwell' ); ?></h2>
						<table class="form-table" role="presentation">

							<tr>
								<th><label for="inkwell_privacy"><?php esc_html_e( 'Default Privacy', 'crosspost-to-inkwell' ); ?></label></th>
								<td>
									<select id="inkwell_privacy" name="<?php echo esc_attr( INKWELL_OPTION_KEY ); ?>[privacy]">
										<option value="public"       <?php selected( 'public',       $opts['privacy'] ?? 'public' ); ?>><?php esc_html_e( 'Public', 'crosspost-to-inkwell' ); ?></option>
										<option value="friends_only" <?php selected( 'friends_only', $opts['privacy'] ?? '' ); ?>><?php esc_html_e( 'Friends only (pen pals)', 'crosspost-to-inkwell' ); ?></option>
										<option value="private"      <?php selected( 'private',      $opts['privacy'] ?? '' ); ?>><?php esc_html_e( 'Private (only me)', 'crosspost-to-inkwell' ); ?></option>
									</select>
								</td>
							</tr>

							<tr>
								<th><label for="inkwell_default_category"><?php esc_html_e( 'Default Category', 'crosspost-to-inkwell' ); ?></label></th>
								<td>
									<input type="text" id="inkwell_default_category"
										name="<?php echo esc_attr( INKWELL_OPTION_KEY ); ?>[default_category]"
										value="<?php echo esc_attr( $opts['default_category'] ?? '' ); ?>"
										class="regular-text"
										placeholder="<?php esc_attr_e( 'e.g. personal, tech, poetry, travel, books', 'crosspost-to-inkwell' ); ?>" />
									<p class="description"><?php esc_html_e( 'Inkwell category for all crossposts. Can be overridden per post. Leave blank to omit.', 'crosspost-to-inkwell' ); ?></p>
								</td>
							</tr>

							<tr>
								<th><?php esc_html_e( 'Featured Image', 'crosspost-to-inkwell' ); ?></th>
								<td>
									<label>
										<input type="checkbox"
											name="<?php echo esc_attr( INKWELL_OPTION_KEY ); ?>[send_featured_image]"
											value="1" <?php checked( '1', $opts['send_featured_image'] ?? '1' ); ?> />
										<?php esc_html_e( "Upload the post's featured image as the Inkwell cover image", 'crosspost-to-inkwell' ); ?>
									</label>
									<p class="description"><?php esc_html_e( 'Uploads via POST /api/images and sets cover_image_id.', 'crosspost-to-inkwell' ); ?></p>
								</td>
							</tr>

							<tr>
								<th><?php esc_html_e( 'Excerpt', 'crosspost-to-inkwell' ); ?></th>
								<td>
									<label>
										<input type="checkbox"
											name="<?php echo esc_attr( INKWELL_OPTION_KEY ); ?>[send_excerpt]"
											value="1" <?php checked( '1', $opts['send_excerpt'] ?? '0' ); ?> />
										<?php esc_html_e( 'Send post excerpt to Inkwell (max 300 chars)', 'crosspost-to-inkwell' ); ?>
									</label>
									<p class="description"><?php esc_html_e( 'If unchecked, Inkwell auto-generates an excerpt from the body.', 'crosspost-to-inkwell' ); ?></p>
								</td>
							</tr>

							<tr>
								<th><?php esc_html_e( 'Hashtag Tags', 'crosspost-to-inkwell' ); ?></th>
								<td>
									<label>
										<input type="checkbox"
											name="<?php echo esc_attr( INKWELL_OPTION_KEY ); ?>[hashtag_tags]"
											value="1" <?php checked( '1', $opts['hashtag_tags'] ?? '1' ); ?> />
										<?php esc_html_e( 'Convert #hashtags in post content to Inkwell tags', 'crosspost-to-inkwell' ); ?>
									</label>
									<p class="description"><?php esc_html_e( 'Any #hashtag found in the post body will be extracted and added to the Inkwell entry tags. Duplicate tags (already set as WP post tags) are ignored.', 'crosspost-to-inkwell' ); ?></p>
								</td>
							</tr>

						</table>
					</div>

					<!-- Behaviour -->
					<div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px 24px;margin-bottom:20px;">
						<h2 style="margin-top:0;font-size:1.05em;border-bottom:1px solid #eee;padding-bottom:10px;"><?php esc_html_e( '⚙️ Behaviour', 'crosspost-to-inkwell' ); ?></h2>
						<table class="form-table" role="presentation">

							<tr>
								<th><?php esc_html_e( 'Auto-Crosspost', 'crosspost-to-inkwell' ); ?></th>
								<td>
									<label>
										<input type="checkbox"
											name="<?php echo esc_attr( INKWELL_OPTION_KEY ); ?>[auto_crosspost]"
											value="1" <?php checked( '1', $opts['auto_crosspost'] ?? '1' ); ?> />
										<?php esc_html_e( 'Automatically crosspost when a post is first published', 'crosspost-to-inkwell' ); ?>
									</label>
								</td>
							</tr>

							<tr>
								<th><?php esc_html_e( 'Crosspost on Update', 'crosspost-to-inkwell' ); ?></th>
								<td>
									<label>
										<input type="checkbox"
											name="<?php echo esc_attr( INKWELL_OPTION_KEY ); ?>[crosspost_on_update]"
											value="1" <?php checked( '1', $opts['crosspost_on_update'] ?? '0' ); ?> />
										<?php esc_html_e( 'Also crosspost when an already-published post is updated', 'crosspost-to-inkwell' ); ?>
									</label>
									<p class="description"><?php esc_html_e( 'Creates a new Inkwell entry on every save — use with care.', 'crosspost-to-inkwell' ); ?></p>
								</td>
							</tr>

							<tr>
								<th><?php esc_html_e( 'Post Types', 'crosspost-to-inkwell' ); ?></th>
								<td>
									<?php
									$all_types = get_post_types( [ 'public' => true ], 'objects' );
									// Ensure Jetpack Social Notes appears even if registered as non-public.
									if ( ! isset( $all_types['jetpack-social-note'] ) && post_type_exists( 'jetpack-social-note' ) ) {
										$all_types['jetpack-social-note'] = get_post_type_object( 'jetpack-social-note' );
									}
									$sel_types = $opts['post_types'] ?? [ 'post' ];
									foreach ( $all_types as $pt ) :
									?>
										<label style="display:block;margin-bottom:4px;">
											<input type="checkbox"
												name="<?php echo esc_attr( INKWELL_OPTION_KEY ); ?>[post_types][]"
												value="<?php echo esc_attr( $pt->name ); ?>"
												<?php checked( in_array( $pt->name, $sel_types, true ) ); ?> />
											<?php echo esc_html( $pt->labels->singular_name ); ?>
											<code style="color:#999;"><?php echo esc_html( $pt->name ); ?></code>
										</label>
									<?php endforeach; ?>
								</td>
							</tr>

						</table>
					</div>

					<?php submit_button( __( 'Save Settings', 'crosspost-to-inkwell' ) ); ?>
				</form>

				<!-- Test -->
				<div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px 24px;margin-top:4px;">
					<h2 style="margin-top:0;font-size:1.05em;border-bottom:1px solid #eee;padding-bottom:10px;"><?php esc_html_e( '🧪 Test Connection', 'crosspost-to-inkwell' ); ?></h2>
					<p><?php esc_html_e( 'Publish a private test entry to your Inkwell journal to confirm everything is working.', 'crosspost-to-inkwell' ); ?></p>
					<form method="post">
						<?php wp_nonce_field( 'inkwell_test', 'inkwell_test_nonce' ); ?>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Send Test Entry', 'crosspost-to-inkwell' ); ?></button>
					</form>
				</div>
			</div>

			<!-- Sidebar -->
			<div style="width:270px;flex-shrink:0;">
				<div style="background:#f0f6ff;border:1px solid #c3d9f5;border-radius:8px;padding:16px 18px;margin-bottom:16px;">
					<h3 style="margin-top:0;color:#1d4ed8;font-size:1em;"><?php esc_html_e( '📖 Quick Start', 'crosspost-to-inkwell' ); ?></h3>
					<ol style="margin:0;padding-left:18px;line-height:1.9;font-size:13px;">
						<li><?php printf( /* translators: %s: link to inkwell.social website */ esc_html__( 'Log into %s', 'crosspost-to-inkwell' ), '<a href="https://inkwell.social" target="_blank">inkwell.social</a>' ); ?></li>
						<li><?php esc_html_e( 'Go to Settings → API', 'crosspost-to-inkwell' ); ?></li>
						<li><?php esc_html_e( 'Click Create API Key', 'crosspost-to-inkwell' ); ?></li>
						<li><?php esc_html_e( 'Copy the key — shown once only', 'crosspost-to-inkwell' ); ?></li>
						<li><?php esc_html_e( 'Paste it here and click Verify Key', 'crosspost-to-inkwell' ); ?></li>
					</ol>
				</div>

				<div style="background:#fef9ec;border:1px solid #fcd34d;border-radius:8px;padding:16px 18px;margin-bottom:16px;">
					<h3 style="margin-top:0;color:#92400e;font-size:1em;"><?php esc_html_e( '⭐ Plus Required for Writing', 'crosspost-to-inkwell' ); ?></h3>
					<p style="margin:0;font-size:13px;line-height:1.6;"><?php
						printf(
							/* translators: %s: link to Inkwell billing */
							esc_html__( 'The write scope (creating entries) requires an %s ($5/mo). The read scope (verifying credentials) works on free accounts.', 'crosspost-to-inkwell' ),
							'<a href="https://inkwell.social/settings/billing" target="_blank"><strong>' . esc_html__( 'Inkwell Plus subscription', 'crosspost-to-inkwell' ) . '</strong></a>'
						);
					?></p>
					<a href="https://inkwell.social/settings/billing" target="_blank" style="font-size:12px;display:inline-block;margin-top:8px;"><?php esc_html_e( 'Upgrade to Plus →', 'crosspost-to-inkwell' ); ?></a>
				</div>

				<div style="background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:16px 18px;margin-bottom:16px;">
					<h3 style="margin-top:0;color:#166534;font-size:1em;"><?php esc_html_e( '📋 What Gets Sent', 'crosspost-to-inkwell' ); ?></h3>
					<ul style="margin:0;padding-left:16px;font-size:13px;line-height:1.9;">
						<li><code>title</code> &mdash; <?php esc_html_e( 'post title', 'crosspost-to-inkwell' ); ?></li>
						<li><code>body_html</code> &mdash; <?php esc_html_e( 'full post content', 'crosspost-to-inkwell' ); ?></li>
						<li><code>tags</code> &mdash; <?php esc_html_e( 'from WP post tags + #hashtags in content', 'crosspost-to-inkwell' ); ?></li>
						<li><code>category</code> &mdash; <?php esc_html_e( 'configured below', 'crosspost-to-inkwell' ); ?></li>
						<li><code>privacy</code> &mdash; <?php esc_html_e( 'configured below', 'crosspost-to-inkwell' ); ?></li>
						<li><code>cover_image_id</code> &mdash; <?php esc_html_e( 'optional', 'crosspost-to-inkwell' ); ?></li>
						<li><code>excerpt</code> &mdash; <?php esc_html_e( 'optional', 'crosspost-to-inkwell' ); ?></li>
					</ul>
				</div>

				<div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:16px 18px;">
					<h3 style="margin-top:0;font-size:1em;"><?php esc_html_e( '🔒 Rate Limits', 'crosspost-to-inkwell' ); ?></h3>
					<p style="margin:0;font-size:13px;line-height:1.6;">
						<?php esc_html_e( 'Plus: 60 writes / 15 min', 'crosspost-to-inkwell' ); ?><br>
						<?php esc_html_e( 'Free: read-only (300 reads / 15 min)', 'crosspost-to-inkwell' ); ?>
					</p>
				</div>
			</div>
		</div>
	</div>
	<?php
}

// ============================================================
// DEBUG LOG — admin page tab, AJAX clear, AJAX fetch
// ============================================================

define( 'INKWELL_DEBUG_LOG_KEY', 'inkwell_debug_log' );
define( 'INKWELL_DEBUG_LOG_MAX', 200 );

/**
 * Append a line to the plugin-wide debug log (only when debug logging is on).
 */
function inkwell_debug_log( $message ) {
	$opts = get_option( INKWELL_OPTION_KEY, [] );
	if ( empty( $opts['debug_log_enabled'] ) || $opts['debug_log_enabled'] !== '1' ) return;
	$log   = (array) get_option( INKWELL_DEBUG_LOG_KEY, [] );
	$log[] = '[' . wp_date( 'Y-m-d H:i:s' ) . '] ' . $message;
	update_option( INKWELL_DEBUG_LOG_KEY, array_slice( $log, - INKWELL_DEBUG_LOG_MAX ), false );
}

add_action( 'wp_ajax_inkwell_clear_debug_log', 'inkwell_ajax_clear_debug_log' );
function inkwell_ajax_clear_debug_log() {
	check_ajax_referer( 'inkwell_clear_log', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();
	delete_option( INKWELL_DEBUG_LOG_KEY );
	wp_send_json_success( esc_html__( 'Log cleared.', 'crosspost-to-inkwell' ) );
}

add_action( 'wp_ajax_inkwell_fetch_debug_log', 'inkwell_ajax_fetch_debug_log' );
function inkwell_ajax_fetch_debug_log() {
	check_ajax_referer( 'inkwell_fetch_log', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();
	$log = (array) get_option( INKWELL_DEBUG_LOG_KEY, [] );
	wp_send_json_success( $log );
}

function inkwell_debug_log_page() {
	if ( ! current_user_can( 'manage_options' ) ) return;

	// Handle enable/disable toggle form submission
	if ( isset( $_POST['inkwell_debug_toggle'] ) ) {
		check_admin_referer( 'inkwell_debug_toggle' );
		$opts = get_option( INKWELL_OPTION_KEY, [] );
		$opts['debug_log_enabled'] = isset( $_POST['debug_log_enabled'] ) ? '1' : '0';
		update_option( INKWELL_OPTION_KEY, $opts );
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'crosspost-to-inkwell' ) . '</p></div>';
	}

	$opts    = get_option( INKWELL_OPTION_KEY, [] );
	$enabled = ! empty( $opts['debug_log_enabled'] ) && $opts['debug_log_enabled'] === '1';
	$log     = (array) get_option( INKWELL_DEBUG_LOG_KEY, [] );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Crosspost to Inkwell — Debug Log', 'crosspost-to-inkwell' ); ?></h1>
		<p>
			<a href="<?php echo esc_url( admin_url( 'options-general.php?page=crosspost-to-inkwell' ) ); ?>">
				← <?php esc_html_e( 'Back to Settings', 'crosspost-to-inkwell' ); ?>
			</a>
		</p>

		<div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;max-width:780px;margin-bottom:20px;">
			<h2 style="margin-top:0;font-size:1.1em;"><?php esc_html_e( 'Debug Logging', 'crosspost-to-inkwell' ); ?></h2>
			<p style="color:#666;font-size:13px;margin-bottom:16px;">
				<?php esc_html_e( 'When enabled, every API request and response is recorded here. Disable when not needed to avoid storing sensitive data.', 'crosspost-to-inkwell' ); ?>
			</p>
			<form method="post">
				<?php wp_nonce_field( 'inkwell_debug_toggle' ); ?>
				<input type="hidden" name="inkwell_debug_toggle" value="1">
				<label>
					<input type="checkbox" name="debug_log_enabled" value="1" <?php checked( $enabled ); ?>>
					<?php esc_html_e( 'Enable debug logging', 'crosspost-to-inkwell' ); ?>
				</label>
				<?php submit_button( __( 'Save', 'crosspost-to-inkwell' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>

		<div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;max-width:780px;">
			<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
				<h2 style="margin:0;font-size:1.1em;">
					<?php
					printf(
						/* translators: %d: number of log entries */
						esc_html__( 'Log Entries (%d)', 'crosspost-to-inkwell' ),
						count( $log )
					);
					?>
				</h2>
				<div style="display:flex;gap:8px;align-items:center;">
					<button id="inkwell_refresh_log" class="button button-secondary" style="font-size:12px;">
						⟳ <?php esc_html_e( 'Refresh', 'crosspost-to-inkwell' ); ?>
					</button>
					<button id="inkwell_clear_log" class="button button-secondary" style="font-size:12px;color:#c00;"
						<?php echo empty( $log ) ? 'disabled' : ''; ?>>
						🗑 <?php esc_html_e( 'Clear Log', 'crosspost-to-inkwell' ); ?>
					</button>
				</div>
			</div>

			<?php if ( ! $enabled ) : ?>
				<div style="background:#fff8e1;border:1px solid #ffe082;border-radius:6px;padding:12px 14px;font-size:13px;margin-bottom:12px;">
					⚠️ <?php esc_html_e( 'Debug logging is currently disabled. Enable it above to start recording.', 'crosspost-to-inkwell' ); ?>
				</div>
			<?php endif; ?>

			<div id="inkwell_log_output" style="font-family:monospace;font-size:12px;line-height:1.7;background:#1e1e1e;color:#d4d4d4;border-radius:6px;padding:16px;min-height:200px;max-height:520px;overflow-y:auto;white-space:pre-wrap;word-break:break-all;">
				<?php if ( empty( $log ) ) : ?>
					<span style="color:#666;"><?php esc_html_e( 'No log entries yet.', 'crosspost-to-inkwell' ); ?></span>
				<?php else : ?>
					<?php foreach ( array_reverse( $log ) as $line ) : ?>
						<?php
						// Color-code by type
						if ( strpos( $line, '✅' ) !== false ) {
							$color = '#4ec994';
						} elseif ( strpos( $line, '❌' ) !== false ) {
							$color = '#f48771';
						} elseif ( strpos( $line, 'REQUEST' ) !== false ) {
							$color = '#9cdcfe';
						} elseif ( strpos( $line, 'RESPONSE' ) !== false ) {
							$color = '#ce9178';
						} else {
							$color = '#d4d4d4';
						}
						?>
						<span style="color:<?php echo esc_attr( $color ); ?>"><?php echo esc_html( $line ); ?></span>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>

			<p style="font-size:11px;color:#999;margin-top:8px;margin-bottom:0;">
				<?php
				printf(
					/* translators: %d: max log entries kept */
					esc_html__( 'Most recent %d entries shown, newest first. Log is stored in wp_options.', 'crosspost-to-inkwell' ),
					absint( INKWELL_DEBUG_LOG_MAX )
				);
				?>
			</p>
		</div>
	</div>
	<?php
}

// ============================================================
// META BOX (per-post controls)
// ============================================================

add_action( 'add_meta_boxes', 'inkwell_add_meta_box' );
function inkwell_add_meta_box() {
	$opts  = get_option( INKWELL_OPTION_KEY, [] );
	$types = $opts['post_types'] ?? [ 'post' ];
	foreach ( $types as $pt ) {
		add_meta_box(
			'inkwell_crosspost_meta',
			__( '🪶 Crosspost to Inkwell', 'crosspost-to-inkwell' ),
			'inkwell_meta_box_html',
			$pt, 'side', 'default'
		);
	}
}

function inkwell_meta_box_html( $post ) {
	wp_nonce_field( 'inkwell_meta_box', 'inkwell_meta_nonce' );

	$opts         = get_option( INKWELL_OPTION_KEY, [] );
	$disable      = get_post_meta( $post->ID, '_inkwell_disable',   true );
	$override_cat = get_post_meta( $post->ID, '_inkwell_category',  true );
	$override_prv = get_post_meta( $post->ID, '_inkwell_privacy',   true );
	$log          = get_post_meta( $post->ID, '_inkwell_log',       true );
	$entry_url    = get_post_meta( $post->ID, '_inkwell_entry_url', true );
	?>
	<div style="font-size:13px;">

		<label style="display:flex;align-items:center;gap:6px;margin-bottom:12px;cursor:pointer;">
			<input type="checkbox" name="inkwell_disable" value="1" <?php checked( '1', $disable ); ?> />
			<?php esc_html_e( 'Disable crosspost for this post', 'crosspost-to-inkwell' ); ?>
		</label>

		<label style="display:block;font-weight:600;margin-bottom:3px;"><?php esc_html_e( 'Category', 'crosspost-to-inkwell' ); ?></label>
		<input type="text" name="inkwell_category_override"
			value="<?php echo esc_attr( $override_cat ); ?>"
			placeholder="<?php echo esc_attr( $opts['default_category'] ?? __( 'e.g. tech', 'crosspost-to-inkwell' ) ); ?>"
			style="width:100%;margin-bottom:10px;" />

		<label style="display:block;font-weight:600;margin-bottom:3px;"><?php esc_html_e( 'Privacy', 'crosspost-to-inkwell' ); ?></label>
		<select name="inkwell_privacy_override" style="width:100%;margin-bottom:12px;">
			<option value=""><?php esc_html_e( '— Use global default —', 'crosspost-to-inkwell' ); ?></option>
			<option value="public"       <?php selected( 'public',       $override_prv ); ?>><?php esc_html_e( 'Public', 'crosspost-to-inkwell' ); ?></option>
			<option value="friends_only" <?php selected( 'friends_only', $override_prv ); ?>><?php esc_html_e( 'Friends only', 'crosspost-to-inkwell' ); ?></option>
			<option value="private"      <?php selected( 'private',      $override_prv ); ?>><?php esc_html_e( 'Private', 'crosspost-to-inkwell' ); ?></option>
		</select>

		<?php if ( $post->post_status === 'publish' ) : ?>
			<hr style="border:0;border-top:1px solid #eee;margin:10px 0;">
			<button type="button" id="inkwell_manual_post" class="button button-secondary" style="width:100%;">
				<?php esc_html_e( '📤 Send to Inkwell Now', 'crosspost-to-inkwell' ); ?>
			</button>
			<div id="inkwell_manual_result" style="margin-top:8px;font-size:12px;"></div>
		<?php endif; ?>

		<?php if ( $log ) : ?>
			<hr style="border:0;border-top:1px solid #eee;margin:10px 0;">
			<strong style="display:block;margin-bottom:4px;"><?php esc_html_e( '📋 Log', 'crosspost-to-inkwell' ); ?></strong>
			<div style="background:#f9f9f9;border:1px solid #eee;border-radius:4px;padding:6px 8px;font-size:11px;max-height:110px;overflow-y:auto;">
				<?php foreach ( array_reverse( (array) $log ) as $line ) : ?>
					<div style="margin-bottom:3px;padding-bottom:3px;border-bottom:1px solid #f0f0f0;"><?php echo esc_html( $line ); ?></div>
				<?php endforeach; ?>
			</div>
			<?php if ( $entry_url ) : ?>
				<a href="<?php echo esc_url( $entry_url ); ?>" target="_blank" style="font-size:11px;display:inline-block;margin-top:6px;"><?php esc_html_e( '↗ View on Inkwell', 'crosspost-to-inkwell' ); ?></a>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
	// Enqueue per-post data and manual-crosspost script via wp_add_inline_script.
	if ( $post->post_status === 'publish' ) {
		wp_localize_script( 'inkwell-meta-box', 'inkwellMetaData', [
			'postId' => $post->ID,
			'nonce'  => wp_create_nonce( 'inkwell_manual_' . $post->ID ),
			'i18n'   => [
				'posting'  => __( '⏳ Posting…', 'crosspost-to-inkwell' ),
				'send'     => __( '📤 Send to Inkwell Now', 'crosspost-to-inkwell' ),
				'done'     => __( '✅ Done', 'crosspost-to-inkwell' ),
				'failed'   => __( '❌ Failed', 'crosspost-to-inkwell' ),
				'reqfail'  => __( '❌ Request failed', 'crosspost-to-inkwell' ),
			],
		] );
		wp_add_inline_script( 'inkwell-meta-box', '
(function(){
	var btn = document.getElementById("inkwell_manual_post");
	if (!btn || typeof inkwellMetaData === "undefined") return;
	var out = document.getElementById("inkwell_manual_result");
	var d   = inkwellMetaData;
	btn.addEventListener("click", function(){
		btn.disabled = true; btn.textContent = d.i18n.posting; out.textContent = "";
		var fd = new FormData();
		fd.append("action", "inkwell_manual_crosspost");
		fd.append("post_id", d.postId);
		fd.append("nonce", d.nonce);
		fetch(ajaxurl, {method:"POST", body:fd})
			.then(function(r){ return r.json(); })
			.then(function(res){
				out.style.color = res.success ? "green" : "red";
				out.innerHTML = res.data || (res.success ? d.i18n.done : d.i18n.failed);
				btn.disabled = false; btn.textContent = d.i18n.send;
				if (res.success) setTimeout(function(){ location.reload(); }, 1800);
			})
			.catch(function(){
				out.style.color = "red"; out.textContent = d.i18n.reqfail;
				btn.disabled = false; btn.textContent = d.i18n.send;
			});
	});
})();
' );
	}
}

add_action( 'save_post', 'inkwell_save_meta', 10, 2 );
function inkwell_save_meta( $post_id, $post ) {
	if ( ! isset( $_POST['inkwell_meta_nonce'] ) ) return;
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['inkwell_meta_nonce'] ) ), 'inkwell_meta_box' ) ) return;
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
	if ( ! current_user_can( 'edit_post', $post_id ) ) return;

	update_post_meta( $post_id, '_inkwell_disable', ! empty( $_POST['inkwell_disable'] ) ? '1' : '0' );

	$cat = isset( $_POST['inkwell_category_override'] ) ? sanitize_text_field( wp_unslash( $_POST['inkwell_category_override'] ) ) : '';
	$cat ? update_post_meta( $post_id, '_inkwell_category', $cat ) : delete_post_meta( $post_id, '_inkwell_category' );

	$prv = isset( $_POST['inkwell_privacy_override'] ) ? sanitize_text_field( wp_unslash( $_POST['inkwell_privacy_override'] ) ) : '';
	$prv ? update_post_meta( $post_id, '_inkwell_privacy', $prv ) : delete_post_meta( $post_id, '_inkwell_privacy' );
}

// ============================================================
// AJAX — VERIFY KEY
// ============================================================

add_action( 'wp_ajax_inkwell_verify_key', 'inkwell_ajax_verify_key' );
function inkwell_ajax_verify_key() {
	check_ajax_referer( 'inkwell_verify', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'Permission denied.', 'crosspost-to-inkwell' ) );
	}

	$opts          = get_option( INKWELL_OPTION_KEY, [] );
	$submitted_key = sanitize_text_field( trim( wp_unslash( $_POST['api_key'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	if ( $submitted_key ) {
		$opts['api_key'] = $submitted_key;
	}

	if ( empty( $opts['api_key'] ) ) {
		wp_send_json_error( '❌ ' . esc_html__( 'No API key entered.', 'crosspost-to-inkwell' ) );
	}
	if ( strpos( $opts['api_key'], 'ink_' ) !== 0 ) {
		wp_send_json_error( '⚠️ ' . esc_html__( 'API key should start with ink_ — please check it was copied correctly.', 'crosspost-to-inkwell' ) );
	}

	// Single /api/me call — use result for both the message and saving account info.
	$me = inkwell_api_get( '/api/me', $opts );

	if ( is_wp_error( $me ) ) {
		// Clear saved account on failure.
		$saved                           = get_option( INKWELL_OPTION_KEY, [] );
		$saved['connected_display_name'] = '';
		$saved['connected_username']     = '';
		update_option( INKWELL_OPTION_KEY, $saved );
		wp_send_json_error( '❌ ' . esc_html( $me->get_error_message() ) );
	}

	$user    = $me['data'] ?? [];
	$handle  = sanitize_text_field( $user['username'] ?? '' );
	$name    = wp_kses( $user['display_name'] ?? $user['username'] ?? $handle, [] );
	if ( ! $name ) {
		$name = $handle;
	}

	inkwell_debug_log( 'VERIFY — saving name="' . $name . '" handle="' . $handle . '"' );

	// Save connected account info so it persists across page reloads.
	$saved                           = get_option( INKWELL_OPTION_KEY, [] );
	$saved['connected_display_name'] = $name;
	$saved['connected_username']     = $handle;
	if ( $submitted_key ) {
		$saved['api_key'] = $submitted_key;
	}
	update_option( INKWELL_OPTION_KEY, $saved );

	$profile = 'https://inkwell.social/@' . $handle;
	$message = '✅ ' . sprintf(
		/* translators: 1: display name, 2: profile URL, 3: username handle */
		__( 'Connected as <strong>%1$s</strong> (<a href="%2$s" target="_blank">@%3$s</a>)', 'crosspost-to-inkwell' ),
		esc_html( $name ),
		esc_url( $profile ),
		esc_html( $handle )
	);
	wp_send_json_success( $message );
}

// ============================================================
// AUTO-CROSSPOST ON PUBLISH
// ============================================================

add_action( 'transition_post_status', 'inkwell_on_status_change', 10, 3 );
function inkwell_on_status_change( $new, $old, $post ) {
	// Static flag prevents double-firing within the same PHP request
	// (Gutenberg makes two rapid REST calls when publishing)
	static $fired = [];
	if ( isset( $fired[ $post->ID ] ) ) return;

	$opts = get_option( INKWELL_OPTION_KEY, [] );
	if ( empty( $opts['auto_crosspost'] ) || $opts['auto_crosspost'] !== '1' ) return;
	if ( empty( $opts['api_key'] ) ) return;

	$types = $opts['post_types'] ?? [ 'post' ];
	if ( ! in_array( $post->post_type, $types, true ) ) return;
	if ( get_post_meta( $post->ID, '_inkwell_disable', true ) === '1' ) return;

	$should_fire  = ( $new === 'publish' && $old !== 'publish' );
	$should_force = ( $new === 'publish' && $old === 'publish' && ! empty( $opts['crosspost_on_update'] ) );

	if ( ! $should_fire && ! $should_force ) return;

	$fired[ $post->ID ] = true;

	// When publishing via the block editor (Gutenberg), WordPress uses the REST API.
	// Running synchronous HTTP requests to the Inkwell API inside that REST request
	// blocks the response and triggers "Publishing failed." in WP 6.9+.
	// Solution: schedule the crosspost to run immediately after the REST response
	// is sent, via a single WP-Cron event. The classic editor is unaffected because
	// it does not use the REST API and REST_REQUEST is not defined.
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		// Defer until after the REST response is sent to the browser.
		// Using the shutdown action avoids blocking Gutenberg and requires no
		// loopback HTTP request (unlike wp-cron), so it works on all hosts.
		$post_id    = $post->ID;
		$force_flag = $should_force;
		add_action( 'shutdown', function() use ( $post_id, $force_flag ) {
			// Keep DB connections and PHP alive after the response is sent.
			ignore_user_abort( true );
			if ( function_exists( 'fastcgi_finish_request' ) ) {
				fastcgi_finish_request();
			}
			inkwell_debug_log( 'CROSSPOST RUNNING via shutdown hook — post_id=' . $post_id );
			try {
				// Avoid the transient lock — fastcgi_finish_request() can make
				// cache/transient functions unavailable. Instead check the meta
				// directly, then call with force=true to skip the transient path.
				if ( ! $force_flag && get_post_meta( $post_id, '_inkwell_crossposted', true ) ) {
					inkwell_debug_log( 'CROSSPOST SKIPPED in shutdown — already crossposted (meta check)' );
					return;
				}
				inkwell_do_crosspost( $post_id, true );
			} catch ( \Throwable $e ) {
				$msg = $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
				inkwell_debug_log( '❌ SHUTDOWN EXCEPTION — ' . $msg );
			}
		} );
		inkwell_debug_log( 'CROSSPOST DEFERRED via shutdown — post_id=' . $post->ID . ' (REST_REQUEST active)' );
		return;
	}

	inkwell_debug_log( 'CROSSPOST TRIGGERED directly (classic editor) — post_id=' . $post->ID );
	inkwell_do_crosspost( $post->ID, $should_force );
}

// Cron handler kept for any events already queued from the previous version.
add_action( 'inkwell_async_crosspost', 'inkwell_do_crosspost', 10, 2 );

// ============================================================
// AJAX — MANUAL CROSSPOST
// ============================================================

add_action( 'wp_ajax_inkwell_manual_crosspost', 'inkwell_ajax_manual' );
function inkwell_ajax_manual() {
	$post_id = absint( $_POST['post_id'] ?? 0 );
	if ( ! $post_id ) wp_send_json_error( __( 'Invalid post ID.', 'crosspost-to-inkwell' ) );
	check_ajax_referer( 'inkwell_manual_' . $post_id, 'nonce' );
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error( __( 'Permission denied.', 'crosspost-to-inkwell' ) );
	}

	$result = inkwell_do_crosspost( $post_id, true );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( $result->get_error_message() );
	}

	$url = $result['url'] ?? '';
	wp_send_json_success( '✅ ' . __( 'Posted to Inkwell!', 'crosspost-to-inkwell' ) . ( $url ? ' <a href="' . esc_url( $url ) . '" target="_blank">' . __( 'View entry →', 'crosspost-to-inkwell' ) . '</a>' : '' ) );
}

// ============================================================
// CORE: DO CROSSPOST
// ============================================================

function inkwell_do_crosspost( $post_id, $force = false ) {
	$opts = get_option( INKWELL_OPTION_KEY, [] );
	$post = get_post( $post_id );

	inkwell_debug_log( '--- CROSSPOST START post_id=' . $post_id . ' force=' . ( $force ? 'true' : 'false' ) . ' status=' . ( $post ? $post->post_status : 'null' ) );

	if ( ! $post || $post->post_status !== 'publish' ) {
		inkwell_debug_log( '❌ CROSSPOST ABORTED — post not published' );
		return new WP_Error( 'not_published', __( 'Post is not published.', 'crosspost-to-inkwell' ) );
	}

	// Guard against double-posting unless forced.
	// Uses a transient lock (add_transient is atomic via INSERT IGNORE in MySQL) to
	// handle the Gutenberg race condition where two separate REST API requests both
	// hit transition_post_status before either has finished writing post meta.
	if ( ! $force ) {
		if ( get_post_meta( $post_id, '_inkwell_crossposted', true ) ) {
			inkwell_debug_log( 'CROSSPOST SKIPPED — already crossposted (meta guard)' );
			return new WP_Error( 'already_posted', __( 'Already crossposted.', 'crosspost-to-inkwell' ) );
		}
		$lock_key = 'inkwell_lock_' . $post_id;
		if ( ! add_transient( $lock_key, 1, 60 ) ) {
			inkwell_debug_log( 'CROSSPOST SKIPPED — transient lock active' );
			return new WP_Error( 'locked', __( 'Crosspost already in progress.', 'crosspost-to-inkwell' ) );
		}
	}

	// Resolve privacy and category (per-post overrides take priority)
	$privacy  = get_post_meta( $post_id, '_inkwell_privacy',  true ) ?: ( $opts['privacy']           ?? 'public' );
	$category = get_post_meta( $post_id, '_inkwell_category', true ) ?: ( $opts['default_category']  ?? '' );

	// Build tags from WP post tags (alphanumeric + hyphens only)
	$wp_tags = wp_get_post_tags( $post_id, [ 'fields' => 'names' ] );
	$tags    = array_values( array_map( function( $t ) {
		return strtolower( preg_replace( '/[^a-zA-Z0-9\-]/', '', str_replace( ' ', '-', $t ) ) );
	}, $wp_tags ) );

	// Extract #hashtags from post content and merge into tags.
	if ( ! empty( $opts['hashtag_tags'] ) && $opts['hashtag_tags'] === '1' ) {
		// Strip HTML first so we only match hashtags in text, not inside attributes.
		$plain = wp_strip_all_tags( $post->post_content );
		preg_match_all( '/#([a-zA-Z][a-zA-Z0-9_\-]*)/u', $plain, $matches );
		if ( ! empty( $matches[1] ) ) {
			$content_tags = array_map( function( $t ) {
				return strtolower( preg_replace( '/[^a-zA-Z0-9\-]/', '', str_replace( '_', '-', $t ) ) );
			}, $matches[1] );
			// Merge and deduplicate — WP post tags take priority.
			$tags = array_values( array_unique( array_merge( $tags, $content_tags ) ) );
			inkwell_debug_log( 'CROSSPOST — extracted ' . count( $content_tags ) . ' hashtag(s) from content: ' . implode( ', ', $content_tags ) );
		}
	}
	// Remove any empty strings that may have resulted from sanitization.
	$tags = array_values( array_filter( $tags ) );

	// Full HTML content, passed through WP content filters (shortcodes, blocks, etc.)
	inkwell_debug_log( 'CROSSPOST — applying the_content filters for post_id=' . $post_id );
	$body_html = apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
	inkwell_debug_log( 'CROSSPOST — content ready, length=' . strlen( $body_html ) );

	// Build payload
	// Social Notes (jetpack-social-note) have no real title — Jetpack assigns
	// a placeholder like "#NuMb3rs". Use a date-based fallback instead.
	$title = get_the_title( $post );
	if ( $post->post_type === 'jetpack-social-note' && ( empty( $title ) || preg_match( '/^#[A-Za-z0-9]+$/', $title ) ) ) {
		$title = wp_date( 'F j, Y g:i A', strtotime( $post->post_date_gmt . ' UTC' ) );
	}

	$payload = [
		'title'     => $title,
		'body_html' => $body_html,
		'privacy'   => $privacy,
	];
	if ( $tags )     $payload['tags']     = $tags;
	if ( $category ) $payload['category'] = $category;

	// Excerpt
	if ( ! empty( $opts['send_excerpt'] ) && $opts['send_excerpt'] === '1' ) {
		$raw             = $post->post_excerpt ?: wp_trim_words( wp_strip_all_tags( $post->post_content ), 55, '…' );
		$payload['excerpt'] = mb_substr( wp_strip_all_tags( $raw ), 0, 300, 'UTF-8' );
	}

	// Featured image → Inkwell cover image
	if ( ! empty( $opts['send_featured_image'] ) && $opts['send_featured_image'] === '1' ) {
		$thumb_id = get_post_thumbnail_id( $post_id );
		if ( $thumb_id ) {
			$cover_id = inkwell_upload_image( $thumb_id, $opts );
			if ( ! is_wp_error( $cover_id ) ) {
				$payload['cover_image_id'] = $cover_id;
			}
		}
	}

	$result = inkwell_api_post( '/api/entries', $payload, $opts );

	if ( is_wp_error( $result ) ) {
		inkwell_add_log( $post_id, '❌ ' . $result->get_error_message() );
		inkwell_debug_log( '❌ ENTRY POST FAILED — ' . $result->get_error_message() );
		return $result;
	}

	$entry     = $result['data'] ?? [];
	$entry_url = $entry['url'] ?? '';

	// Fall back to constructing the URL from username + slug
	if ( ! $entry_url && ! empty( $entry['slug'] ) ) {
		$me = inkwell_api_get( '/api/me', $opts );
		if ( ! is_wp_error( $me ) && ! empty( $me['data']['username'] ) ) {
			$entry_url = 'https://inkwell.social/@' . $me['data']['username'] . '/' . $entry['slug'];
		}
	}

	update_post_meta( $post_id, '_inkwell_crossposted', wp_date( 'Y-m-d H:i:s' ) );
	if ( $entry_url )              update_post_meta( $post_id, '_inkwell_entry_url', esc_url_raw( $entry_url ) );
	if ( ! empty( $entry['id'] ) ) update_post_meta( $post_id, '_inkwell_entry_id', sanitize_text_field( $entry['id'] ) );

	inkwell_add_log( $post_id, '✅ ' . wp_date( 'Y-m-d H:i:s' ) . ( $entry_url ? ' → ' . $entry_url : '' ) );
	inkwell_debug_log( '✅ CROSSPOST SUCCESS post_id=' . $post_id . ( $entry_url ? ' url=' . $entry_url : '' ) );

	return [ 'url' => $entry_url, 'id' => $entry['id'] ?? '' ];
}

// ============================================================
// API HELPERS
// ============================================================

function inkwell_api_post( $path, $payload, $opts ) {
	$url  = INKWELL_API_BASE . $path;
	$body = wp_json_encode( $payload );
	inkwell_debug_log( 'REQUEST POST ' . $url . ' — payload keys: ' . implode( ', ', array_keys( $payload ) ) );
	$response = wp_remote_post( $url, [
		'headers' => [
			'Authorization' => 'Bearer ' . ( $opts['api_key'] ?? '' ),
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
		],
		'body'    => $body,
		'timeout' => 30,
	] );
	if ( is_wp_error( $response ) ) {
		inkwell_debug_log( '❌ RESPONSE ERROR (POST ' . $path . ') — ' . $response->get_error_message() );
	} else {
		inkwell_debug_log( 'RESPONSE ' . wp_remote_retrieve_response_code( $response ) . ' (POST ' . $path . ') — ' . mb_substr( wp_remote_retrieve_body( $response ), 0, 300, 'UTF-8' ) );
	}
	return inkwell_parse_response( $response );
}

function inkwell_api_get( $path, $opts ) {
	$url = INKWELL_API_BASE . $path;
	inkwell_debug_log( 'REQUEST GET ' . $url );
	$response = wp_remote_get( $url, [
		'headers' => [
			'Authorization' => 'Bearer ' . ( $opts['api_key'] ?? '' ),
			'Accept'        => 'application/json',
		],
		'timeout' => 15,
	] );
	if ( is_wp_error( $response ) ) {
		inkwell_debug_log( '❌ RESPONSE ERROR (GET ' . $path . ') — ' . $response->get_error_message() );
	} else {
		inkwell_debug_log( 'RESPONSE ' . wp_remote_retrieve_response_code( $response ) . ' (GET ' . $path . ') — ' . mb_substr( wp_remote_retrieve_body( $response ), 0, 600, 'UTF-8' ) );
	}
	return inkwell_parse_response( $response );
}

function inkwell_parse_response( $response ) {
	if ( is_wp_error( $response ) ) return $response;

	$code = wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( $code === 401 ) return new WP_Error( 'unauthorized', __( 'Invalid or missing API key.', 'crosspost-to-inkwell' ) );
	if ( $code === 403 ) return new WP_Error( 'forbidden',    __( 'Access denied — write access requires an Inkwell Plus subscription.', 'crosspost-to-inkwell' ) );
	if ( $code === 404 ) return new WP_Error( 'not_found',    __( 'Resource not found.', 'crosspost-to-inkwell' ) );
	if ( $code === 422 ) {
		$errors = $body['errors'] ?? [];
		$msg    = $body['error']  ?? __( 'Validation error', 'crosspost-to-inkwell' );
		if ( $errors ) {
			$details = array_map( fn( $f, $e ) => "$f: $e", array_keys( $errors ), $errors );
			$msg    .= ' (' . implode( ', ', $details ) . ')';
		}
		return new WP_Error( 'validation', $msg );
	}
	if ( $code === 429 ) return new WP_Error( 'rate_limit', __( 'Rate limit exceeded. Please wait before trying again.', 'crosspost-to-inkwell' ) );
	if ( $code < 200 || $code >= 300 ) {
		/* translators: %d: HTTP status code number */
		return new WP_Error( 'api_error', $body['error'] ?? sprintf( __( 'Unexpected HTTP %d response from Inkwell API.', 'crosspost-to-inkwell' ), $code ) );
	}

	return $body;
}

/**
 * Upload a WordPress attachment as a base64 data URI to Inkwell Images API.
 * Returns the Inkwell image UUID on success.
 */
function inkwell_upload_image( $attachment_id, $opts ) {
	$file = get_attached_file( $attachment_id );
	if ( ! $file || ! file_exists( $file ) ) {
		inkwell_debug_log( '❌ IMAGE UPLOAD — file not found: attachment ID ' . $attachment_id );
		return new WP_Error( 'no_file', __( 'Featured image file not found on disk.', 'crosspost-to-inkwell' ) );
	}

	$mime    = get_post_mime_type( $attachment_id );
	inkwell_debug_log( 'IMAGE UPLOAD — reading file, mime: ' . $mime . ', attachment ID: ' . $attachment_id );
	global $wp_filesystem;
	if ( empty( $wp_filesystem ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		WP_Filesystem();
	}
	$contents = $wp_filesystem->get_contents( $file );
	if ( false === $contents ) {
		inkwell_debug_log( '❌ IMAGE UPLOAD — could not read file from disk' );
		return new WP_Error( 'read_fail', __( 'Could not read the featured image file.', 'crosspost-to-inkwell' ) );
	}
	$encoded = base64_encode( $contents );
	if ( ! $encoded ) {
		inkwell_debug_log( '❌ IMAGE UPLOAD — base64 encode failed' );
		return new WP_Error( 'encode_fail', __( 'Could not base64-encode the image.', 'crosspost-to-inkwell' ) );
	}

	inkwell_debug_log( 'IMAGE UPLOAD — sending to /api/images, encoded size: ' . strlen( $encoded ) . ' chars' );
	$result = inkwell_api_post( '/api/images', [
		'image' => 'data:' . $mime . ';base64,' . $encoded,
	], $opts );

	if ( is_wp_error( $result ) ) {
		inkwell_debug_log( '❌ IMAGE UPLOAD — API error: ' . $result->get_error_message() );
		return $result;
	}

	$id = $result['data']['id'] ?? null;
	if ( ! $id ) {
		inkwell_debug_log( '❌ IMAGE UPLOAD — no image ID in response' );
		return new WP_Error( 'no_image_id', __( 'Inkwell did not return an image ID.', 'crosspost-to-inkwell' ) );
	}

	inkwell_debug_log( '✅ IMAGE UPLOAD — success, image ID: ' . $id );
	return $id;
}

// ============================================================
// VERIFY CREDENTIALS
// ============================================================

function inkwell_verify_credentials( $opts ) {
	if ( empty( $opts['api_key'] ) ) {
		return '❌ ' . __( 'No API key entered.', 'crosspost-to-inkwell' );
	}
	if ( strpos( $opts['api_key'], 'ink_' ) !== 0 ) {
		return '⚠️ ' . __( 'API key should start with <code>ink_</code> — please check it was copied correctly.', 'crosspost-to-inkwell' );
	}

	$result = inkwell_api_get( '/api/me', $opts );

	if ( is_wp_error( $result ) ) {
		return '❌ ' . esc_html( $result->get_error_message() );
	}

	$user    = $result['data'] ?? [];
	$name    = $user['display_name'] ?? $user['username'] ?? __( 'Unknown', 'crosspost-to-inkwell' );
	$handle  = $user['username'] ?? '';
	$profile = 'https://inkwell.social/@' . $handle;

	return '✅ ' . sprintf(
		/* translators: 1: display name, 2: profile URL, 3: username handle */
		__( 'Connected as <strong>%1$s</strong> (<a href="%2$s" target="_blank">@%3$s</a>)', 'crosspost-to-inkwell' ),
		esc_html( $name ),
		esc_url( $profile ),
		esc_html( $handle )
	);
}

// ============================================================
// TEST ENTRY
// ============================================================

function inkwell_send_test_entry( $opts ) {
	if ( empty( $opts['api_key'] ) ) {
		return '❌ ' . __( 'No API key configured.', 'crosspost-to-inkwell' );
	}

	$result = inkwell_api_post( '/api/entries', [
		'title'     => '🧪 WordPress Crosspost Test',
		'body_html' => '<p>This is a test entry from the <strong>Crosspost to Inkwell</strong> WordPress plugin v' . INKWELL_VERSION . '.</p><p>Connection is working! You can delete this entry.</p>',
		'privacy'   => 'private',
		'tags'      => [ 'test', 'wordpress' ],
	], $opts );

	if ( is_wp_error( $result ) ) {
		return '❌ ' . esc_html( $result->get_error_message() );
	}

	$entry = $result['data'] ?? [];
	$url   = '';

	if ( ! empty( $entry['slug'] ) ) {
		$me = inkwell_api_get( '/api/me', $opts );
		if ( ! is_wp_error( $me ) && ! empty( $me['data']['username'] ) ) {
			$url = 'https://inkwell.social/@' . $me['data']['username'] . '/' . $entry['slug'];
		}
	}

	return '✅ ' . __( 'Test entry created (private).', 'crosspost-to-inkwell' ) . ( $url ? ' <a href="' . esc_url( $url ) . '" target="_blank">' . __( 'View it →', 'crosspost-to-inkwell' ) . '</a>' : '' );
}

// ============================================================
// HELPERS
// ============================================================

function inkwell_add_log( $post_id, $message ) {
	$log   = (array) get_post_meta( $post_id, '_inkwell_log', true );
	$log[] = $message;
	update_post_meta( $post_id, '_inkwell_log', array_slice( $log, -20 ) );
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'inkwell_action_links' );
function inkwell_action_links( $links ) {
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=crosspost-to-inkwell-debug' ) ) . '">' . esc_html__( 'Debug Log', 'crosspost-to-inkwell' ) . '</a>' );
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=crosspost-to-inkwell' ) ) . '">' . esc_html__( 'Settings', 'crosspost-to-inkwell' ) . '</a>' );
	return $links;
}
