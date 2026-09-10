from pathlib import Path

path = Path('crosspost-to-inkwell.php')
text = path.read_text(encoding='utf-8')


def replace_once(old: str, new: str) -> None:
    global text
    count = text.count(old)
    if count != 1:
        raise SystemExit(f'Expected exactly one match, found {count}: {old[:100]!r}')
    text = text.replace(old, new, 1)


replace_once(' * Version:           1.0.0', ' * Version:           1.0.1')
replace_once("define( 'INKWELL_VERSION',     '1.0.0' );", "define( 'INKWELL_VERSION',     '1.0.1' );")

replace_once(
    "\t$clean = [];\n\t$clean['api_key']             = sanitize_text_field( trim( $input['api_key'] ?? '' ) );",
    "\t$clean = [];\n\t$existing                     = get_option( INKWELL_OPTION_KEY, [] );\n"
    "\t$submitted_key                = sanitize_text_field( trim( $input['api_key'] ?? '' ) );\n"
    "\t$clean['api_key']             = '' !== $submitted_key ? $submitted_key : (string) ( $existing['api_key'] ?? '' );"
)

replace_once(
    "\t$existing = get_option( INKWELL_OPTION_KEY, [] );\n\t$clean['connected_display_name']",
    "\t$clean['connected_display_name']"
)

replace_once(
    "\t\t\t\t\t\t\tvalue=\"<?php echo esc_attr( $opts['api_key'] ?? '' ); ?>\"",
    "\t\t\t\t\t\t\tvalue=\"\""
)

replace_once(
    "function inkwell_debug_log( $message ) {\n\t$opts = get_option( INKWELL_OPTION_KEY, [] );",
    "function inkwell_debug_log( $message ) {\n\t$message = inkwell_redact_sensitive_log_data( (string) $message );\n\t$opts = get_option( INKWELL_OPTION_KEY, [] );"
)

anchor = "function inkwell_debug_log( $message ) {"
helper = r'''/**
 * Redact credentials and authorization material before writing debug logs.
 */
function inkwell_redact_sensitive_log_data( $message ) {
	$message = preg_replace( '/\bBearer\s+[A-Za-z0-9._~+\/-]+=*/i', 'Bearer [REDACTED]', (string) $message );
	$message = preg_replace(
		'/(["\']?(?:api_key|apikey|client_secret|access_token|refresh_token|token|password|authorization)["\']?\s*[:=]\s*["\']?)[^"\'\s,&}]+/i',
		'$1[REDACTED]',
		$message
	);
	// Inkwell keys have a recognizable prefix; redact them even in unstructured text.
	$message = preg_replace( '/\bink_[A-Za-z0-9._~-]+/i', 'ink_[REDACTED]', $message );
	return $message;
}

'''
replace_once(anchor, helper + anchor)

path.write_text(text, encoding='utf-8')
