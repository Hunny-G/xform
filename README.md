# XForm

XForm is a lightweight WordPress plugin for publishing custom HTML/CSS forms through shortcodes, recording submissions locally, and queueing email/webhook delivery.

## Requirements

- WordPress 6.0+
- PHP 7.4+
- A working WordPress mail transport for email delivery
- HTTPS for webhook destinations and recommended site-wide

## Installation

1. Upload the `xform` folder to `/wp-content/plugins/`, or zip the folder and install it through **Plugins → Add New → Upload Plugin**.
2. Activate **XForm**.
3. Open **XForm → Forms** in the dashboard.

Activation creates the `{prefix}xform_submissions` table using WordPress `dbDelta()`.

## Create a form

1. Go to **XForm → Forms**.
2. Enter a form name and a unique ID such as `contact`.
3. Paste your form's inner HTML into **HTML markup**. Do not include a `<form>` wrapper; XForm creates the secure wrapper.
4. Give each field a `name` attribute. Example:

```html
<div class="contact-form">
  <label for="your-name">Name</label>
  <input id="your-name" name="name" type="text" required>
  <label for="your-email">Email</label>
  <input id="your-email" name="email" type="email" required>
  <button type="submit" class="send-button">Send message</button>
</div>
```

5. Optionally add CSS in the CSS field. CSS is stored separately and printed with the form.
6. Select a delivery destination and configure the email and/or HTTPS webhook.
7. Save the form.

## Shortcode

Use the generated shortcode in a WordPress Shortcode block, page, post, or compatible builder:

```text
[xform id="contact"]
```

The `id` is the form ID you chose. The `name` attribute is accepted as an alternative:

```text
[xform name="contact"]
```

## Delivery routing

Each form has its own destination setting:

- **Email** — sends to the configured recipient.
- **Webhook** — POSTs a JSON payload to the configured HTTPS URL.
- **Both** — performs both delivery actions.
- **Log only** — stores the submission without outbound delivery.

Outbound work is scheduled through WordPress's single-event cron queue. The REST endpoint stores the submission and returns a JSON response without waiting for email or webhook delivery. WordPress cron is traffic-driven unless a real system cron is configured; for dependable background execution, configure a server cron to call `wp-cron.php` or use your host's supported WordPress cron facility.

Webhook payload shape:

```json
{
  "form_id": "contact",
  "form_name": "Contact",
  "submission_id": 123,
  "submitted_at": "2025-01-01 12:00:00",
  "data": {
    "name": "Example",
    "email": "person@example.com"
  }
}
```

## Submissions and CSV export

Open **XForm → Submissions** to view the latest 100 records and use **Export all CSV** to download all entries. The CSV is UTF-8 with a BOM for spreadsheet compatibility. The stored form data is JSON in the `form_data` column.

## Security notes and limitations

- Public submissions use a WordPress REST route and a WordPress nonce header.
- The plugin adds a hidden honeypot field and ignores submissions that fill it.
- Form HTML is filtered through `wp_kses`; script tags and event-handler attributes are not accepted. CSS is filtered to remove common executable/import patterns. Do not paste untrusted code.
- Use HTTPS on the whole site. Nonces are CSRF mitigation, not authentication or a complete spam solution.
- The plugin stores the remote IP address as requested. Update your privacy policy and retention practices to comply with applicable privacy law. Consider shortening or anonymizing IP retention where appropriate.
- WordPress cron is best-effort and not a hard real-time queue. A server cron is recommended for reliable delivery.
- Webhook URLs must use HTTPS. Restrict webhook destinations to trusted endpoints; outbound HTTP requests can have security implications.
- The current release does not include retry/backoff controls, CAPTCHA, attachment uploads, conditional logic, or a visual drag-and-drop builder.

## Developer hooks

- `xform` shortcode: renders the selected form.
- `xform/v1/submit`: public REST endpoint for submissions.
- `xform_deliver_submission`: scheduled action receiving the submission ID.
- `xform_forms` option: stores form definitions.

## Troubleshooting

**Form says unavailable:** Check that the form is saved and the shortcode ID exactly matches its Form ID.

**Form does not submit:** Check browser developer tools for REST errors, confirm HTTPS/site URL consistency, and ensure caching/security plugins do not block `/wp-json/xform/v1/submit`.

**Email does not arrive:** Check WordPress mail configuration and spam folders. Use an SMTP plugin or host mail service if needed.

**Webhook is not delivered:** Confirm the endpoint accepts HTTPS POST with JSON and inspect the site's cron and PHP logs.

**Queued delivery is delayed:** WordPress cron runs on page traffic by default. Configure a real server cron for dependable processing.

## Tests

The `tests/` directory contains a PHPUnit bootstrap and starter test. To run tests, use the WordPress PHPUnit test suite and configure `WP_TESTS_DIR` for your environment.


## Secure Divit CRM sync (XForm 1.1.0)

Open **XForm → CRM Integration** in WordPress and configure a shared secret of at least 32 characters. Configure the same value as `WORDPRESS_SYNC_TOKEN` in the local CRM `.env` file. The plugin exposes authenticated pending-submission and acknowledgement REST endpoints for the local CRM to poll over HTTPS. The secret is never displayed after saving.

Public form submissions are protected with a WordPress nonce, a honeypot field, per-form/per-IP transient rate limiting (5 submissions per 10 minutes), server-side sanitization, field/item count limits and a 32 KB request limit. The nonce is not a replacement for rate limiting or bot detection. For high-volume public forms, add a managed challenge such as Cloudflare Turnstile at the edge.
