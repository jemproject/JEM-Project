import { spawn } from 'node:child_process';
import { mkdirSync, readFileSync, readdirSync, rmSync, writeFileSync } from 'node:fs';
import { basename, dirname, join, resolve } from 'node:path';
import { tmpdir } from 'node:os';

function parseArguments(argv) {
    const options = {};

    for (const argument of argv.slice(2)) {
        const match = argument.match(/^--([^=]+)=(.*)$/s);
        if (!match) {
            throw new Error(`Invalid argument: ${argument}`);
        }

        options[match[1]] = match[2];
    }

    return options;
}

function required(options, name) {
    if (!options[name]) {
        throw new Error(`Missing --${name}`);
    }

    return options[name];
}

function delay(milliseconds) {
    return new Promise((resolvePromise) => setTimeout(resolvePromise, milliseconds));
}

class CdpClient {
    constructor(url) {
        this.socket = new WebSocket(url);
        this.nextId = 1;
        this.pending = new Map();
        this.listeners = new Map();
    }

    async connect() {
        await new Promise((resolvePromise, reject) => {
            this.socket.addEventListener('open', resolvePromise, { once: true });
            this.socket.addEventListener('error', reject, { once: true });
        });

        this.socket.addEventListener('message', (event) => {
            const message = JSON.parse(event.data);

            if (message.id && this.pending.has(message.id)) {
                const pending = this.pending.get(message.id);
                this.pending.delete(message.id);

                if (message.error) {
                    pending.reject(new Error(message.error.message));
                } else {
                    pending.resolve(message.result);
                }

                return;
            }

            for (const listener of this.listeners.get(message.method) ?? []) {
                listener(message.params ?? {});
            }
        });
    }

    send(method, params = {}) {
        const id = this.nextId++;

        return new Promise((resolvePromise, reject) => {
            this.pending.set(id, { resolve: resolvePromise, reject });
            this.socket.send(JSON.stringify({ id, method, params }));
        });
    }

    on(method, listener) {
        const listeners = this.listeners.get(method) ?? [];
        listeners.push(listener);
        this.listeners.set(method, listeners);
    }

    once(method, timeout = 20000) {
        return new Promise((resolvePromise, reject) => {
            const listener = (params) => {
                clearTimeout(timer);
                const listeners = this.listeners.get(method) ?? [];
                this.listeners.set(method, listeners.filter((candidate) => candidate !== listener));
                resolvePromise(params);
            };
            const timer = setTimeout(() => {
                const listeners = this.listeners.get(method) ?? [];
                this.listeners.set(method, listeners.filter((candidate) => candidate !== listener));
                reject(new Error(`Timeout waiting for ${method}`));
            }, timeout);

            this.on(method, listener);
        });
    }

    close() {
        this.socket.close();
    }
}

async function waitForDebugger(port) {
    for (let attempt = 0; attempt < 100; attempt++) {
        try {
            const response = await fetch(`http://127.0.0.1:${port}/json/version`);
            if (response.ok) {
                return;
            }
        } catch {
            // Chrome has not opened the debugging endpoint yet.
        }

        await delay(100);
    }

    throw new Error('Chrome debugging endpoint did not become available.');
}

async function evaluate(client, expression) {
    const result = await client.send('Runtime.evaluate', {
        expression,
        awaitPromise: true,
        returnByValue: true,
    });

    if (result.exceptionDetails) {
        throw new Error(result.exceptionDetails.text ?? 'Runtime evaluation failed.');
    }

    return result.result?.value;
}

function unique(values) {
    return [...new Set(values.filter(Boolean))];
}

function viewDirectories(sourceRoot, area) {
    const directory = join(sourceRoot, area === 'BE' ? 'admin' : 'site', 'views');

    return readdirSync(directory, { withFileTypes: true })
        .filter((entry) => entry.isDirectory())
        .map((entry) => entry.name)
        .sort();
}

function routeForView(area, view, ids) {
    const eventId = ids.events ?? 0;
    const venueId = ids.venues ?? 0;
    const categoryId = ids.categories ?? 0;
    const groupId = ids.groups ?? 0;
    const typeId = ids.types ?? 0;
    const specialdayId = ids.specialdays ?? 0;
    const registrationId = ids.registrations ?? 0;
    const attachmentId = ids.attachments ?? 0;

    if (area === 'BE') {
        const parameters = {
            attachment: `view=attachment&layout=edit&id=${attachmentId}`,
            attendee: `view=attendee&layout=edit&id=${registrationId}&eventid=${eventId}`,
            attendees: `view=attendees&eventid=${eventId}`,
            category: `view=category&layout=edit&id=${categoryId}`,
            categoryelement: 'view=categoryelement&tmpl=component&object=jform_categories',
            contactelement: 'view=contactelement&tmpl=component&object=jform_contactid',
            event: `view=event&layout=edit&id=${eventId}`,
            eventelement: 'view=eventelement&tmpl=component&object=jform_eventid',
            group: `view=group&layout=edit&id=${groupId}`,
            imagehandler: 'view=imagehandler&task=selecteventimg&tmpl=component&type=events',
            source: 'view=source&layout=edit&id=0',
            specialday: `view=specialday&layout=edit&id=${specialdayId}`,
            type: `view=type&layout=edit&id=${typeId}`,
            userelement: 'view=userelement&tmpl=component&object=jform_user',
            venue: `view=venue&layout=edit&id=${venueId}`,
            venueelement: 'view=venueelement&tmpl=component&object=jform_locid',
        };

        return `/administrator/index.php?option=com_jem&${parameters[view] ?? `view=${view}`}`;
    }

    const parameters = {
        attendeeregistrations: `view=attendeeregistrations&id=${eventId}&eventid=${eventId}`,
        attendees: `view=attendees&id=${eventId}&eventid=${eventId}`,
        category: `view=category&id=${categoryId}`,
        day: 'view=day&year=2026&month=09&day=14',
        editevent: 'view=editevent&layout=edit&id=0&e_id=0',
        editvenue: 'view=editvenue&layout=edit&id=0&v_id=0',
        event: `view=event&id=${eventId}`,
        mailto: `view=mailto&tmpl=component&id=${eventId}`,
        specialday: `view=specialday&id=${specialdayId}`,
        typeevents: `view=typeevents&id=${typeId}`,
        typevenues: `view=typevenues&id=${typeId}`,
        venue: `view=venue&id=${venueId}`,
    };

    return `/index.php?option=com_jem&${parameters[view] ?? `view=${view}`}`;
}

async function navigate(client, url, state) {
    state.consoleErrors = [];
    state.resourceErrors = [];
    state.documentStatus = null;
    const loaded = client.once('Page.loadEventFired', 30000).catch(() => null);
    const navigation = await client.send('Page.navigate', { url });
    if (navigation.errorText) {
        throw new Error(`Navigation failed for ${url}: ${navigation.errorText}`);
    }
    await loaded;
    await delay(700);

    return evaluate(client, `(() => ({
        url: location.href,
        title: document.title,
        text: (document.body?.innerText || '').slice(0, 250000),
        html: (document.documentElement?.outerHTML || '').slice(0, 500000)
    }))()`);
}

async function waitForStableLocation(client, timeout = 10000) {
    const started = Date.now();
    let previous = '';
    let unchangedSince = Date.now();

    while (Date.now() - started < timeout) {
        const current = await evaluate(client, 'location.href');

        if (current !== previous) {
            previous = current;
            unchangedSince = Date.now();
        } else if (Date.now() - unchangedSince >= 1200) {
            return current;
        }

        await delay(250);
    }

    return evaluate(client, 'location.href');
}

async function submitLogin(client, baseUrl, username, password, administrator, state) {
    const loginUrl = administrator
        ? `${baseUrl}/administrator/index.php`
        : `${baseUrl}/index.php?option=com_users&view=login`;
    await navigate(client, loginUrl, state);
    const loaded = client.once('Page.loadEventFired', 30000).catch(() => null);
    const submitted = await evaluate(client, `(() => {
        const username = document.querySelector('input[name="username"]');
        const password = document.querySelector('input[type="password"]');
        const form = username?.closest('form');
        if (!form || !username || !password) return false;
        username.value = ${JSON.stringify(username)};
        password.value = ${JSON.stringify(password)};
        form.requestSubmit ? form.requestSubmit() : form.submit();
        return true;
    })()`);

    if (!submitted) {
        throw new Error(`${administrator ? 'Administrator' : 'Frontend'} login form was not found.`);
    }

    await loaded;
    await waitForStableLocation(client);
    const dismissedTour = await evaluate(client, `(() => {
        const control = [...document.querySelectorAll('button, a')].find((item) => /^(hide forever|ocultar para siempre)$/i.test((item.textContent || '').trim()));
        if (!control) return false;
        control.click();
        return true;
    })()`);

    if (dismissedTour) {
        await delay(1000);
        await waitForStableLocation(client);
    }

    const page = await evaluate(client, `(() => ({url: location.href, text: document.body?.innerText || ''}))()`);

    if (page.url.includes('view=login') || /incorrect username|username and password do not match|usuario y contraseña no coinciden/i.test(page.text)) {
        throw new Error(`${administrator ? 'Administrator' : 'Frontend'} login failed.`);
    }
}

async function loadSampleData(client, baseUrl, state) {
    let mainPage = await navigate(client, `${baseUrl}/administrator/index.php?option=com_jem`, state);

    if (!mainPage.url.includes('option=com_jem')) {
        throw new Error(`JEM control panel navigation failed: ${mainPage.url}; documents ${state.documentResponses.slice(-12).join(' -> ')}; ${mainPage.text.slice(0, 1200)}`);
    }

    const resultPage = await navigate(client, `${baseUrl}/administrator/index.php?option=com_jem&task=sampledata.load`, state);
    const text = resultPage.text;

    if (/couldn.t load sample data|sample data.*failed|no se.*datos de ejemplo/i.test(text)) {
        throw new Error('JEM reported a Sample Data loading failure.');
    }

    return text;
}

async function installPackage(client, baseUrl, packagePath, state) {
    await navigate(client, `${baseUrl}/administrator/index.php?option=com_installer&view=install`, state);
    const input = await client.send('Runtime.evaluate', {
        expression: 'document.querySelector("input[type=file]")',
        returnByValue: false,
    });
    const objectId = input.result?.objectId;

    if (!objectId) {
        const diagnostic = await evaluate(client, `(() => ({
            text: (document.body?.innerText || '').slice(0, 4000),
            html: (document.documentElement?.outerHTML || '').slice(0, 20000),
            frames: [...document.querySelectorAll('iframe')].map((frame) => frame.src)
        }))()`);
        throw new Error(`The Joomla package upload field was not found. ${JSON.stringify(diagnostic)}`);
    }

    const node = await client.send('DOM.requestNode', { objectId });
    await client.send('DOM.setFileInputFiles', { files: [resolve(packagePath)], nodeId: node.nodeId });
    const loaded = client.once('Page.loadEventFired', 60000).catch(() => null);
    const submitted = await evaluate(client, `(() => {
        const input = document.querySelector('input[type=file]');
        const form = input?.form;
        const button = form?.querySelector('button[type=submit], input[type=submit]');
        if (!form) return false;
        if (button) button.click(); else form.requestSubmit ? form.requestSubmit() : form.submit();
        return true;
    })()`);

    if (!submitted) {
        throw new Error('The Joomla package upload form could not be submitted.');
    }

    await loaded;
    await delay(1500);

    return evaluate(client, `(() => ({
        url: location.href,
        title: document.title,
        text: document.body?.innerText || ''
    }))()`);
}

function analysePage(page, state, administrator) {
    const languageKeys = unique(page.text.match(/\b(?:COM|MOD|PLG)_JEM_[A-Z0-9_]+\b/g) ?? []);
    const serverPatterns = [
        /PHP (?:Warning|Notice|Deprecated|Fatal error)/gi,
        /Fatal error:/gi,
        /Parse error:/gi,
        /Uncaught [A-Za-z\\]+(?:Error|Exception)/gi,
        /Call to undefined (?:function|method)/gi,
        /Unknown column ['`][^'`]+['`]/gi,
        /Base table or view not found/gi,
        /Table ['`][^'`]+['`] doesn't exist/gi,
        /SQLSTATE\[[A-Z0-9]+\]/gi,
        /An error has occurred\.?/gi,
    ];
    const serverErrors = unique(serverPatterns.flatMap((pattern) => page.text.match(pattern) ?? []));
    const redirectedToLogin = administrator
        ? /\/administrator\/index\.php(?:\?|$)/.test(page.url)
            && /name=["']username["']/i.test(page.html)
            && /type=["']password["']/i.test(page.html)
        : page.url.includes('option=com_users') && page.url.includes('view=login');
    const failures = [];
    const warnings = [];

    if (state.documentStatus === null || state.documentStatus >= 400) {
        failures.push(`HTTP ${state.documentStatus ?? 'unknown'}`);
    }
    if (redirectedToLogin) {
        failures.push('redirected to login');
    }
    if (languageKeys.length > 0) {
        failures.push(`raw language keys: ${languageKeys.join(', ')}`);
    }
    if (serverErrors.length > 0) {
        failures.push(`server output: ${serverErrors.join(', ')}`);
    }
    if (state.consoleErrors.length > 0) {
        failures.push(`JavaScript: ${unique(state.consoleErrors).join(' | ')}`);
    }
    if (state.resourceErrors.length > 0) {
        warnings.push(...unique(state.resourceErrors));
    }

    return {
        status: failures.length === 0 ? (warnings.length === 0 ? 'PASS' : 'WARNING') : 'FAIL',
        http: state.documentStatus,
        url: page.url,
        title: page.title,
        language_keys: languageKeys,
        server_errors: serverErrors,
        javascript_errors: unique(state.consoleErrors),
        resource_warnings: unique(state.resourceErrors),
        failures,
        warnings,
    };
}

async function main() {
    const options = parseArguments(process.argv);
    const baseUrl = required(options, 'base').replace(/\/$/, '');
    const username = required(options, 'username');
    const password = process.env.JEM_QA_PASSWORD;
    const sourceRoot = resolve(required(options, 'source-root'));
    const output = resolve(required(options, 'output'));
    const mode = options.mode ?? 'audit';
    const idsFile = options['ids-file'];
    const ids = idsFile ? JSON.parse(readFileSync(idsFile, 'utf8')).ids ?? {} : {};
    const requestedViews = options.views
        ? new Set(options.views.split(',').map((view) => view.trim()).filter(Boolean))
        : null;
    if (options['attachment-id']) {
        ids.attachments = Number.parseInt(options['attachment-id'], 10);
    }
    const chrome = options.chrome ?? 'C:/Program Files/Google/Chrome/Application/chrome.exe';

    if (!password) {
        throw new Error('JEM_QA_PASSWORD is required.');
    }

    const port = 9300 + Math.floor(Math.random() * 500);
    const profile = join(tmpdir(), `jem-qa-chrome-${process.pid}`);
    const chromeProcess = spawn(chrome, [
        '--headless=new',
        '--disable-gpu',
        '--no-first-run',
        '--no-default-browser-check',
        `--remote-debugging-port=${port}`,
        `--user-data-dir=${profile}`,
        'about:blank',
    ], { stdio: 'ignore' });

    let client;
    try {
        await waitForDebugger(port);
        const targetResponse = await fetch(`http://127.0.0.1:${port}/json/new?about:blank`, { method: 'PUT' });
        const target = await targetResponse.json();
        client = new CdpClient(target.webSocketDebuggerUrl);
        await client.connect();
        await Promise.all([
            client.send('Page.enable'),
            client.send('Runtime.enable'),
            client.send('Log.enable'),
            client.send('Network.enable'),
        ]);

        const state = { documentStatus: null, documentResponses: [], consoleErrors: [], resourceErrors: [] };
        client.on('Network.responseReceived', (params) => {
            if (params.type === 'Document') {
                state.documentStatus = params.response.status;
                state.documentResponses.push(`${params.response.status} ${params.response.url}`);
            } else if (params.response.status >= 400) {
                state.resourceErrors.push(`${params.response.status} ${params.response.url}`);
            }
        });
        client.on('Network.loadingFailed', (params) => {
            if (!params.canceled) {
                state.resourceErrors.push(`${params.errorText}: ${params.blockedReason ?? params.requestId}`);
            }
        });
        client.on('Runtime.exceptionThrown', (params) => {
            state.consoleErrors.push(params.exceptionDetails?.exception?.description ?? params.exceptionDetails?.text ?? 'JavaScript exception');
        });
        client.on('Log.entryAdded', (params) => {
            if (params.entry?.level === 'error') {
                state.consoleErrors.push(params.entry.text);
            }
        });

        await submitLogin(client, baseUrl, username, password, true, state);

        if (mode === 'install') {
            const packagePath = required(options, 'package');
            const page = await installPackage(client, baseUrl, packagePath, state);
            const failed = /unable to install|error installing|cannot install|no se puede instalar|error al instalar/i.test(page.text);
            const result = {
                result: failed ? 'FAIL' : 'PASS',
                url: page.url,
                text: page.text.slice(0, 20000),
            };
            mkdirSync(dirname(output), { recursive: true });
            writeFileSync(output, JSON.stringify(result, null, 2));
            console.log(`[Extension install] ${result.result}`);
            console.log(page.text.slice(0, 4000));
            process.exitCode = failed ? 1 : 0;
            return;
        }

        if (mode === 'sample-data') {
            await client.send('Emulation.setScriptExecutionDisabled', { value: true });
            await delay(500);
            const text = await loadSampleData(client, baseUrl, state);
            const successful = /loading of sample data was successful|sample data (?:was )?(?:loaded|installed) successfully|datos de ejemplo.*(?:cargados|instalados).*correctamente/i.test(text);
            const result = {
                result: successful ? 'PASS' : 'FAIL',
                url: await evaluate(client, 'location.href'),
                text: text.slice(0, 4000),
            };
            mkdirSync(dirname(output), { recursive: true });
            writeFileSync(output, JSON.stringify(result, null, 2));
            console.log(`[Sample Data] ${result.result}`);
            process.exitCode = successful ? 0 : 1;
            return;
        }

        await submitLogin(client, baseUrl, username, password, false, state);
        const routes = [];
        for (const area of ['BE', 'FE']) {
            for (const view of viewDirectories(sourceRoot, area)) {
                if (requestedViews && !requestedViews.has(view) && !requestedViews.has(`${area}:${view}`)) {
                    continue;
                }

                routes.push({ area, view, path: routeForView(area, view, ids) });
            }
        }

        const results = [];
        for (let index = 0; index < routes.length; index++) {
            const route = routes[index];
            let result;

            try {
                let targetUrl = baseUrl + route.path;

                if (route.area === 'FE' && route.view === 'mailto') {
                    await navigate(client, `${baseUrl}/index.php?option=com_jem&view=event&id=${ids.events ?? 0}`, state);
                    targetUrl = await evaluate(client, `(() => {
                        const link = [...document.querySelectorAll('a')].find((item) => {
                            const href = item.getAttribute('href') || '';
                            const opensMailModal = item.dataset.bsTarget === '#mailto-modal';
                            const targetsMailView = href.includes('view=mailto') || href.includes('/mailto/');
                            const hasSignedLink = new URL(item.href, location.href).searchParams.has('link');

                            return hasSignedLink && (opensMailModal || targetsMailView);
                        });
                        return link?.href || '';
                    })()`);

                    if (!targetUrl) {
                        throw new Error('A session-bound JEM mail link was not available from the event view.');
                    }
                }

                const page = await navigate(client, targetUrl, state);
                result = analysePage(page, state, route.area === 'BE');
            } catch (error) {
                result = {
                    status: 'FAIL',
                    http: state.documentStatus,
                    url: baseUrl + route.path,
                    title: '',
                    language_keys: [],
                    server_errors: [],
                    javascript_errors: [],
                    resource_warnings: [],
                    failures: [error.message],
                    warnings: [],
                };
            }

            results.push({ ...route, ...result });
            console.log(`[${route.area} ${String(index + 1).padStart(2, '0')}/${routes.length}] ${result.status} ${route.view} HTTP ${result.http ?? 'unknown'}`);

            if (result.status === 'FAIL') {
                const screenshot = await client.send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false });
                const screenshotPath = join(dirname(output), `failure-${route.area.toLowerCase()}-${route.view}.png`);
                writeFileSync(screenshotPath, Buffer.from(screenshot.data, 'base64'));
            }
        }

        const summary = {
            pass: results.filter((result) => result.status === 'PASS').length,
            warning: results.filter((result) => result.status === 'WARNING').length,
            fail: results.filter((result) => result.status === 'FAIL').length,
        };
        const report = {
            generated_at: new Date().toISOString(),
            base_url: baseUrl,
            inventory: {
                backend: viewDirectories(sourceRoot, 'BE'),
                frontend: viewDirectories(sourceRoot, 'FE'),
            },
            summary,
            results,
        };
        mkdirSync(dirname(output), { recursive: true });
        writeFileSync(output, JSON.stringify(report, null, 2));
        console.log(`[View audit] PASS=${summary.pass} WARNING=${summary.warning} FAIL=${summary.fail}`);
        process.exitCode = summary.fail === 0 ? 0 : 1;
    } finally {
        client?.close();
        chromeProcess.kill();
        await delay(300);
        rmSync(profile, { recursive: true, force: true });
    }
}

main().catch((error) => {
    console.error(error.stack ?? error.message);
    process.exitCode = 1;
});
