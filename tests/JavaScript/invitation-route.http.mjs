/** Installed-site regression: optional invitation route inputs must not cause typed-property errors. */
import assert from 'node:assert/strict';

const base = process.env.JEM_TEST_SITE_URL;
if (!base) {
    console.log('SKIP: set JEM_TEST_SITE_URL to the local Joomla test site.');
    process.exit(0);
}
const site = new URL(base.endsWith('/') ? base : base + '/');
assert.ok(['localhost', '127.0.0.1', '[::1]'].includes(site.hostname), 'Local test site required');
for (const values of [{}, {invitation_token: 'invalid'}, {invitation_id: '0'}]) {
    const url = new URL('index.php', site);
    url.search = new URLSearchParams({option: 'com_jem', view: 'invitation', ...values});
    const response = await fetch(url);
    const html = await response.text();
    assert.equal(response.status, 200, 'Invalid invitation renders its controlled response');
    assert.match(html, /jem-invitation-portal/);
    assert.doesNotMatch(html, /Cannot assign null|Fatal error|Uncaught TypeError/);
    assert.doesNotMatch(html, /name="answer" value="accept"/);
}
console.log('PASS: 3 optional-input invitation routes, 12 assertions.');
