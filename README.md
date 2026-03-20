# Crosspost to Inkwell

![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue?logo=wordpress) ![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php) ![License](https://img.shields.io/badge/license-GPLv2%2B-green) ![Version](https://img.shields.io/badge/version-1.0.0-orange)

Automatically crossposts your WordPress blog posts to your [Inkwell.social](https://inkwell.social) journal. Requires an [Inkwell Plus](https://inkwell.social/settings/billing) subscription ($5/month) for API write access.

---

## Features

- Automatically crossposts when a post is published
- Sends the full post content as HTML to Inkwell
- Uploads your featured image as the Inkwell cover image
- Maps WordPress tags to Inkwell tags
- Extracts `#hashtags` from post content and sends them as Inkwell tags
- Per-post privacy control (public, friends only, private)
- Per-post category override
- Disable crossposting on individual posts
- Manually trigger a crosspost from the post editor
- Crosspost log stored per post so you can track what was sent
- API key verification with persistent connected account display
- Show/hide toggle for the API key field
- Debug log page for diagnosing API issues
- Supports Jetpack Social Notes (`jetpack-social-note` post type)

---

## Requirements

| Requirement | Version |
|---|---|
| WordPress | 5.8 or higher |
| PHP | 7.4 or higher |
| Inkwell.social account | Required |
| Inkwell Plus subscription | Required for API write access |

---

## Installation

### From WordPress.org (recommended)

1. In your WordPress dashboard go to **Plugins → Add New**
2. Search for **Crosspost to Inkwell**
3. Click **Install Now**, then **Activate**

### Manual install

1. Download the latest release zip from this repository
2. In your WordPress dashboard go to **Plugins → Add New → Upload Plugin**
3. Upload the zip file and click **Install Now**, then **Activate**

### From source

```bash
cd wp-content/plugins
git clone https://github.com/evecodesx/crosspost-to-inkwell.git
```

Then activate the plugin from your WordPress **Plugins** menu.

---

## Setup

1. Go to **Settings → Crosspost to Inkwell**
2. Enter your Inkwell API key and click **Verify Key**
3. The connected account name will be saved and displayed persistently
4. Configure your default privacy setting and category
5. Click **Save Settings**

### Getting your Inkwell API key

1. Log into [inkwell.social](https://inkwell.social)
2. Go to **Settings → API**
3. Click **Create API Key**
4. Copy the key immediately — it is only shown once
5. Paste it into the plugin settings page

---

## Usage

### Automatic crossposting

Once configured, the plugin will automatically crosspost every new post you publish. No further action needed.

### Per-post controls

Every post has a **Crosspost to Inkwell** panel in the editor sidebar where you can:

- **Disable crosspost** for that specific post
- **Override the default privacy** setting (public, friends only, private)
- **Override the default category**
- **Manually send the post** to Inkwell using the "Send to Inkwell Now" button
- **View the crosspost log** showing when the post was sent and its Inkwell entry URL

### Settings reference

| Setting | Description |
|---|---|
| API Key | Your Inkwell API key (starts with `ink_`) |
| Default Privacy | Public, friends only, or private |
| Default Category | The Inkwell category to file entries under |
| Auto Crosspost | Automatically crosspost on publish |
| Crosspost on Update | Also crosspost when an existing post is updated (creates a new Inkwell entry each time) |
| Post Types | Which post types to crosspost (posts, pages, custom types, Jetpack Social Notes) |
| Send Featured Image | Upload the featured image as the Inkwell entry cover |
| Send Excerpt | Include the post excerpt in the Inkwell entry |
| Hashtag Tags | Extract `#hashtags` from post body and send as Inkwell tags |

---

## FAQ

**Do I need an Inkwell Plus subscription?**
Yes. The Inkwell API requires a Plus subscription to create entries. You can verify your API key without Plus, but posts will not crosspost until you upgrade.

**Will my full post content be sent to Inkwell?**
Yes. The plugin sends your complete post content as HTML. All standard WordPress content (blocks, shortcodes, embeds) is processed before sending.

**Will updating a post create a duplicate on Inkwell?**
Not by default. The plugin only posts when a post is first published. You can optionally enable "Crosspost on Update" in settings, but this creates a new Inkwell entry on every update.

**What happens if the API request fails?**
The error is recorded in the post's crosspost log, visible in the editor sidebar. The post is not marked as crossposted, so you can retry manually using the "Send to Inkwell Now" button.

**Is there a character limit?**
No. Unlike short-form social platforms, Inkwell supports full journal entries with no character limit.

**Can I stop a specific post from being crossposted?**
Yes. Check "Disable crosspost for this post" in the editor sidebar before publishing and it will be skipped.

**Does it work with Jetpack Social Notes?**
Yes. Enable the `jetpack-social-note` post type in Settings → Post Types. Hashtag extraction works on Social Notes too. Since Social Notes have no real title, the plugin uses a date/time string (e.g. `March 16, 2026 1:24 AM`) as the Inkwell entry title instead of Jetpack's placeholder.

**How does hashtag extraction work?**
When the Hashtag Tags setting is enabled, the plugin scans the post body for `#hashtag` patterns, sanitizes them, and merges them with your existing WordPress post tags before sending to Inkwell. Duplicates are removed automatically.

**What is the Debug Log?**
The Debug Log page (**Settings → Crosspost to Inkwell → 🪲 Debug Log**) records every API request and response when enabled. Useful for diagnosing connection issues. Disable it when not needed.

---

## External Service

This plugin connects to the Inkwell.social API (`api.inkwell.social`) to create journal entries on your behalf. By using this plugin you agree to the [Inkwell Terms of Service](https://inkwell.social/terms).

**Data sent to Inkwell.social:**
- Post title
- Full post content (as HTML)
- Post excerpt (optional)
- Post tags + `#hashtags` extracted from content (optional)
- Category
- Featured image (optional, uploaded as base64)

No data is sent to any service other than your own Inkwell.social account. No user tracking occurs. Full API documentation is available at [inkwell.social/developers](https://inkwell.social/developers).

---

## Development Changelog

**v1.0.0** — *Submitted to WordPress.org 03/10/26*

**02/27/26, 1:02 AM EST — Initial build**
- Plugin created with auto-crosspost on publish via `transition_post_status` hook
- Manual crosspost button in the post editor sidebar (AJAX, no page reload)
- Featured image upload via `POST /api/images` before entry creation
- Post tags synced and sanitized to Inkwell's alphanumeric+hyphen format
- Per-post overrides: disable crosspost, custom category, custom privacy
- Crosspost status and Inkwell entry URL stored in post meta and displayed in sidebar
- Configurable default privacy (`public`, `friends_only`, `private`)
- Optional featured image and excerpt sending
- Support for custom post types in addition to standard posts

**03/01/26, 12:55 AM EST — API layer & double-post fix**
- Rebuilt API integration against the official Inkwell API docs
- Added live API key verification on settings page
- Fixed double-posting bug: WordPress fires `transition_post_status` multiple times per save; resolved with static `$fired[]` array + transient lock + `_inkwell_crossposted` meta guard
- Plugin renamed to "WP Inkwell Crosspost"

**03/05/26, 9:12 AM EST — Stability**
- Rolled back an unstable version of the double-post fix; restored stable build

**03/06/26, 12:39 AM EST — Submission prep begins**
- Mapped full WordPress.org plugin directory submission checklist

**03/09/26, 4:02 PM EST — readme & validator**
- `readme.txt` written to WordPress.org spec
- Donate link added
- `Tested up to` updated to 6.9

**03/09/26, 6:15 PM EST — Naming compliance**
- Plugin renamed from "WP Inkwell Crosspost" → "Crosspost to Inkwell" per WP.org Guideline 17

**03/09/26, 7:04 PM EST — Security audit & PCP compliance**
- Full output escaping audit: `esc_html()`, `esc_attr()`, `esc_url()`, `esc_textarea()` applied throughout
- All `$_POST` reads wrapped with `wp_unslash()` + appropriate sanitize function
- `sanitize_key()` for post type slugs; `sanitize_text_field()` for all other string inputs
- All forms and AJAX actions protected by nonces via `check_ajax_referer()`
- `register_setting()` modernized to array format with `sanitize_callback`
- `in_array()` uses strict comparison (`true`) throughout
- `mb_substr()` explicitly specifies `UTF-8` encoding
- `file_get_contents()` replaced with WP Filesystem API

**03/09/26, 8:39 PM EST — i18n, JS & form cleanup**
- Full i18n across all ~99 user-facing strings with `/* translators: */` comments
- `load_plugin_textdomain()` removed (auto-loaded since WP 4.6)
- Nested `<form>` removed — Verify Key button converted to pure AJAX
- All JavaScript moved to properly registered handles via `wp_add_inline_script()`; no raw `<script>` blocks
- Version set to 1.0.0; settings stored in `wp_options`; no custom database tables

**03/10/26, 10:02 PM EST — Final pre-submission fixes**
- All `wp_enqueue_script()` calls moved to `admin_enqueue_scripts` hook
- `current_time('mysql')` replaced with `wp_date('Y-m-d H:i:s')` (deprecated since WP 5.3)
- `wp_register_script()` `src` changed from `''` to `false` for inline-only handles
- `Requires at least: 5.8` and `Requires PHP: 7.4` added to PHP file header
- License URI unified to `gpl-2.0.html` across PHP header and readme
- External service disclosure and privacy notice added to readme.txt
- Plugin slug renamed from `inkwell-crosspost` → `crosspost-to-inkwell` throughout
- Text domain: `crosspost-to-inkwell`
- Submitted to WordPress.org plugin directory ✅

**03/15/26 — "Publishing failed" fix (WordPress 6.9.4)**
- Crosspost deferred via `shutdown` action hook when publishing via block editor (`REST_REQUEST`) — prevents blocking Gutenberg's publish response
- `ignore_user_abort(true)` added to keep DB connections alive after response is sent
- `fastcgi_finish_request()` called when available for immediate response flush on PHP-FPM hosts
- Shutdown execution wrapped in `try/catch (\Throwable)` to surface silent fatal errors
- Fixed `add_transient()` unavailable in shutdown context — replaced with direct `get_post_meta()` check + `force=true`

**03/15/26 — Debug Log page**
- New admin page at **Settings → Crosspost to Inkwell → 🪲 Debug Log**
- Enable/disable toggle — off by default
- Dark terminal-style log viewer, color-coded by entry type
- Refresh (live AJAX) and Clear Log buttons
- Log stored in `wp_options`, capped at 200 entries
- 26 log points throughout the full crosspost flow
- Debug Log link added to settings page header and plugin action links

**03/15/26 — Verify Key reads live field value**
- Fixed "No API key entered" error when clicking Verify Key before saving
- JS now reads the current field value and passes it in the AJAX request

**03/15/26 — API key show/hide toggle**
- 👁 / 🔒 button added inline with the API key field
- Typing in the field clears the connected account status

**03/16/26 — Persistent connected account display**
- Connected account name and handle saved to `wp_options` on successful verify
- Displayed persistently on page load — no longer disappears on refresh
- Fixed sanitize callback silently overwriting newly saved account info on every `update_option` call
- `wp_kses()` used instead of `sanitize_text_field()` to preserve emoji and Unicode in display names
- Account info cleared when API key is changed

**03/16/26 — Hashtag-to-tag extraction**
- New **Hashtag Tags** setting (enabled by default) under Settings → Entry Settings
- Scans post body for `#hashtag` patterns after stripping HTML
- Sanitized and merged with WP post tags; duplicates removed
- Debug log records extracted hashtags by name

**03/16/26 — Jetpack Social Notes support**
- `jetpack-social-note` post type explicitly added to Post Types checklist via `post_type_exists()` fallback
- Hashtag extraction works on Social Notes
- Social Notes with Jetpack placeholder titles (`#NuMb3rs`) replaced with readable date/time string
- Fixed timezone offset in date title — uses `post_date_gmt` with `wp_date()` for correct local time

**03/16/26 — Plugin Check (PCP) fixes**
- `/* translators: */` comment moved directly above `__()` call inside `wp_kses()`
- `INKWELL_DEBUG_LOG_MAX` wrapped in `absint()` for output escaping compliance
- `phpcs:ignore` updated to suppress `InputNotSanitized` on already-sanitized `$_POST['api_key']`
- `error_log()` removed — same data captured by `inkwell_debug_log()`

---

## License

[GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html)
