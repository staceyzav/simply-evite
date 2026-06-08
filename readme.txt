=== Simply Evite ===
Contributors: simplydesign
Tags: invitation, evite, envelope, animation, RSVP
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Animated envelope invitation. Drop in the shortcode, point it at your invite image and a Google Form — done.

== Description ==

Simply Evite renders a Paperless Post-style animated envelope on any page or post. Click the envelope to watch the flap open and your invitation card rise into view. The RSVP button appears automatically after the animation completes.

**Shortcode:**

`[simply_evite image="https://yoursite.com/invite.jpg" rsvp_url="https://forms.google.com/..."]`

**All attributes:**

* `image` — URL of your invitation image (required). Any size — it fills the card automatically.
* `alt` — Image alt text. Default: "You're Invited"
* `rsvp_url` — Google Form URL or any RSVP destination. Omit to hide the button.
* `rsvp_text` — RSVP button label. Default: "RSVP Now"
* `trigger` — `click` (default) or `auto` (opens automatically after delay)
* `delay` — Milliseconds before auto-open. Default: 1500
* `prompt` — Hint text below the envelope. Default: "Click to open your invitation"

**Token support:**

The envelope flap color reads from `--client-accent` in the Simply Design token system. On any site using the Simply Branded plugin, the envelope automatically matches the client's brand color.

Part of the [Simply Design](https://simplydesign.com) plugin suite.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate through the Plugins menu
3. Add `[simply_evite image="YOUR-IMAGE-URL"]` to any page or post
4. Add content blocks below the shortcode for event details, maps, etc.

== Frequently Asked Questions ==

= What size should my invite image be? =

Any size works — the image fills the card with `object-fit: cover`. We recommend at least 1200px wide at the aspect ratio of your choice. The card shape matches the envelope opening automatically.

= Can I use it without a Google Form? =

Yes — omit the `rsvp_url` attribute and the RSVP button won't appear.

= Can the envelope open automatically? =

Yes — set `trigger="auto"` and optionally `delay="2000"` (milliseconds).

= Does it work with any theme? =

Yes. The envelope flap color picks up your brand automatically if you use Simply Branded. On any other theme it defaults to a clean blue.

== Screenshots ==

1. Closed envelope with wax seal — waiting for click
2. Flap opening mid-animation
3. Invitation card fully revealed with RSVP button

<!-- TODO: capture screenshots before WP.org submission -->

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial release.
