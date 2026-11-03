=== XForm ===
Contributors: hunny-g
Tags: forms, contact form, custom forms, webhook, csv
Requires at least: 6.0
Requires PHP: 7.4
Tested up to: 7.1.4
Stable tag: 1.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create custom HTML/CSS forms, publish them with a shortcode, store submissions, and deliver them by email or HTTPS webhook. Includes CSV export and Divit CRM sync.

== Description ==

XForm lets site administrators create forms using HTML markup and optional CSS, then embed them with a shortcode.

Features:
* Custom form markup and CSS.
* Shortcode embedding: [xform id="contact"].
* Submission storage in the WordPress database.
* Email, HTTPS webhook, both, or log-only delivery.
* Submission listing and CSV export.
* Basic honeypot and per-form/per-IP rate limiting.
* Authenticated Divit CRM queue and acknowledgement endpoints.

XForm does not require a paid account or a Pro license to use its existing features.

== Installation ==

1. Upload the xform folder to /wp-content/plugins/, or install the ZIP through Plugins > Add New > Upload Plugin.
2. Activate XForm.
3. Open XForm > Forms.
4. Create a form and copy its shortcode.
5. Add the shortcode to a page or post.

== Frequently Asked Questions ==

= How do I embed a form? =

Use [xform id="your-form-id"] in a page, post, or shortcode-compatible block.

= Can I use custom HTML and CSS? =

Yes. Add the form's inner HTML (without an outer form element) and optional CSS in the form editor. XForm creates the form wrapper.

= Where are submissions stored? =

Submissions are stored in a dedicated WordPress database table and can be viewed under XForm > Submissions.

= Does XForm support webhooks? =

Yes. Configure an HTTPS webhook destination. XForm uses WordPress's safe HTTP API and disables redirects for webhook delivery. Only configure destinations you trust.

= Does it include Divit CRM sync? =

Yes. Configure a long random shared secret under XForm > CRM Integration and use the same secret in your CRM configuration. Protect the secret and use HTTPS.

= Does it include paid-only restrictions? =

No. Existing XForm features, including Divit CRM sync, are included in this edition.

== Privacy ==

XForm stores submitted form fields and the remote IP address associated with each submission. Site owners are responsible for providing appropriate privacy disclosures, setting retention periods, and handling deletion requests under applicable law. The plugin does not send submission data to the XForm developer. Data is sent to the email recipient, webhook URL, or connected CRM configured by the site administrator.

== Changelog ==

= 1.1.1 =
* Use WordPress safe HTTP requests for webhook delivery and disable redirects.
* Validate webhook URL structure and reject embedded URL credentials.
* Restrict CRM acknowledgements to pending records so already-processed records cannot be changed.
* Add WordPress.org readme metadata and GPL license declaration.

= 1.1.0 =
* Added authenticated Divit CRM queue and acknowledgement endpoints.
* Added submission rate limiting and request size limits.
* Added submission storage, email/webhook delivery, and CSV export.
