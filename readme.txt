=== Crosspost to Inkwell ===
Contributors: puffidredz
Donate link: https://ko-fi.com/evecodes
Tags: inkwell, crosspost, journaling, social, automation
Requires at least: 5.8
Tested up to: 6.9
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically crossposts your WordPress blog posts to your Inkwell.social journal. Requires an Inkwell Plus subscription.

== Description ==

Crosspost to Inkwell connects your WordPress blog to [Inkwell.social](https://inkwell.social), a federated social journaling platform. Every time you publish a post, the plugin automatically creates a new entry in your Inkwell journal — complete with your title, full content, tags, category, and featured image.

**Key features:**

* Automatically crossposts when a post is published
* Sends the full post content as HTML to Inkwell
* Uploads your featured image as the Inkwell cover image
* Maps WordPress tags to Inkwell tags
* Per-post privacy control (public, friends only, private)
* Per-post category override
* Disable crossposting on individual posts
* Manually trigger a crosspost from the post editor
* Crosspost log stored per post so you can track what was sent
* API key verification and test entry tools built into the settings page

**External Service**

This plugin connects to the Inkwell.social API (`api.inkwell.social`) to create journal entries on your behalf. By using this plugin you agree to the [Inkwell Terms of Service](https://inkwell.social/terms). An [Inkwell.social](https://inkwell.social) account and an [Inkwell Plus](https://inkwell.social/settings/billing) subscription are required for API write access.

**What data is sent to Inkwell:**

* Post title
* Full post content (as HTML)
* Post excerpt (optional)
* Post tags
* Category
* Featured image (optional, uploaded as base64)

No data is sent to any service other than your own Inkwell.social account. No user tracking occurs.

== Installation ==

1. Upload the `crosspost-to-inkwell` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the **Plugins** menu in WordPress
3. Go to **Settings → Crosspost to Inkwell**
4. Enter your Inkwell API key and click **Verify Key**
5. Configure your default privacy setting and category, then click **Save Settings**

**Getting your API key:**

1. Log into [inkwell.social](https://inkwell.social)
2. Go to **Settings → API**
3. Click **Create API Key**
4. Copy the key immediately — it is only shown once
5. Paste it into the plugin settings page

== Frequently Asked Questions ==

= Do I need an Inkwell Plus subscription? =

Yes. The Inkwell API requires a Plus subscription ($5/month) to create entries. You can verify your API key without Plus, but posts will not crosspost until you upgrade.

= What data does this plugin send externally? =

When a post is crossposted, the plugin sends the post title, full HTML content, tags, category, and optionally the featured image and excerpt to `api.inkwell.social`. No other data is transmitted and no user tracking takes place.

= Will my full post content be sent to Inkwell? =

Yes. The plugin sends your complete post content as HTML. All standard WordPress content (blocks, shortcodes, embeds) is processed before sending.

= Can I stop a specific post from being crossposted? =

Yes. Every post has a **Crosspost to Inkwell** panel in the editor sidebar. Check "Disable crosspost for this post" before publishing and it will be skipped.

= What happens if the API request fails? =

The error is recorded in the post's crosspost log, visible in the editor sidebar. The post is not marked as crossposted, so you can retry manually using the "Send to Inkwell Now" button.

= Will updating a post create a duplicate on Inkwell? =

Not by default. The plugin only posts when a post is first published. You can optionally enable "Crosspost on Update" in settings, but this creates a new Inkwell entry on every save.

= Is there a character limit? =

No. Unlike short-form social platforms, Inkwell supports full journal entries with no character limit.

== Screenshots ==

1. The settings page showing API connection, entry settings, and behaviour options.
2. The per-post meta box in the block editor sidebar showing privacy, category, and manual crosspost controls.
3. The crosspost log showing the history of when a post was sent to Inkwell.

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial release.
