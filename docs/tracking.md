# Tracking script

Install TallyMark with one deferred script tag. Replace the example site key
with the public key displayed for the site in the dashboard.

```html
<script defer src="https://analytics.example.com/tm.e387d386.js" data-site="your-public-site-key"></script>
```

The hand-written tracker asset has a filename based on the first eight
characters of its SHA-256 content hash and is served with
`Cache-Control: public, max-age=31536000, immutable`. Deploy a new hashed
asset and update the snippet whenever the tracker changes. `tm.js` is an
equally cacheable compatibility loader for installations that require the
stable path; new installations should use the versioned URL above.

The tracker sends an initial pageview to the `/px.php` beside the script URL;
it does not assume that the tracked site and the analytics host are the same
origin. For custom events, call:

```js
window.tallymark('event', 'signup', { plan: 'pro' });
```

To follow client-side history changes, add `data-spa="true"` to the script
tag. The tracker respects DNT and Global Privacy Control by default. To opt
out of that behaviour for a site, set `data-respect-dnt="false"`. It refuses
to run on localhost and RFC1918 loopback/private hosts unless the installation
explicitly sets `data-allow-private="true"`.

The script uses no cookies or browser storage and does not perform browser
fingerprinting. It tries `sendBeacon`, then `fetch` with `keepalive`, then an
image request. Any tracker error is swallowed so it cannot affect the host
page.

For a same-origin installation, use these CSP directives:

```text
script-src 'self'
connect-src 'self'
img-src 'self'
```

For a tracker hosted at `https://analytics.example.com` and a separate tracked
site, use these directives instead (replace the example origin):

```text
script-src 'self' https://analytics.example.com
connect-src 'self' https://analytics.example.com
img-src 'self' https://analytics.example.com
```
