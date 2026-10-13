// Run directly with node; no browser, database or installed-site changes.
const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const {File} = require('node:buffer');

function environment(overrides = {}) {
    const config = {mode: 'none', maxDimension: 1920, maxBytes: 400 * 1024, minDimension: 1,
        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'], ratioWidth: 4, ratioHeight: 3,
        extensions: {'image/jpeg': 'jpg', 'image/png': 'png', 'image/webp': 'webp'}, ...overrides};
    const listeners = {};
    const urls = new Map();
    const canvases = [];
    const images = [];
    const opened = [];
    const input = {id: 'upload', type: 'file', files: [], dataset: {}, accepted: 0,
        closest: () => null,
        dispatchEvent() {
            this.accepted++;
            listeners.change({target: input}); // Consume the accepted-event marker, as in the browser.
        }};
    const button = {dataset: {jemCameraInput: 'upload', jemCameraConfig: JSON.stringify(config)}};
    const control = {failEncoding: false, deferredImages: false, bytesPerPixel: 0.2};
    class Canvas {
        constructor() { this.width = 0; this.height = 0; canvases.push(this); }
        getContext() { return {drawImage() {}}; }
        toDataURL(mime) { return 'data:' + mime; }
        toBlob(callback, mime, quality) {
            const size = Math.max(1, Math.ceil(this.width * this.height * control.bytesPerPixel
                * (mime === 'image/png' ? 1 : quality)));
            callback(control.failEncoding ? null : new Blob([new Uint8Array(size)], {type: mime}));
        }
    }
    const document = {
        querySelectorAll: selector => selector === '.jem-camera-button' ? [button] : [],
        getElementById: () => null,
        addEventListener: (type, callback) => { listeners[type] = callback; },
        createElement(tag) {
            if (tag === 'canvas') return new Canvas();
            assert.equal(tag, 'img');
            return {
                set src(url) {
                    const file = urls.get(url);
                    this.naturalWidth = file.width;
                    this.naturalHeight = file.height;
                    const loaded = () => file.invalid ? this.onerror() : this.onload();
                    images.push(loaded);
                    if (!control.deferredImages) queueMicrotask(loaded);
                }
            };
        }
    };
    class Transfer {
        constructor() { this.files = []; this.items = {add: file => this.files.push(file)}; }
    }
    const context = vm.createContext({document, window: {}, File, Blob, Event, DataTransfer: Transfer,
        URL: {createObjectURL(file) { const url = String(urls.size); urls.set(url, file); return url; }, revokeObjectURL() {}},
        captureEditor: (...args) => opened.push(args)});
    let source = fs.readFileSync(path.join(__dirname, '../../media/js/image-camera.js'), 'utf8');
    // Expose the real processing functions only in this isolated test context.
    source = source.replace(/\}\(\)\);\s*$/, `
        openImageEditor = captureEditor;
        globalThis.upload = {inspectSelectedFile, editorSourceFiles};
    }());`);
    vm.runInContext(source, context);
    function inspect(file) {
        input.files = [file];
        context.upload.editorSourceFiles.set(input, file);
        return context.upload.inspectSelectedFile(input, file, false);
    }
    return {input, control, canvases, images, opened, inspect, listeners, button, context};
}

function photo(width = 4000, height = 3000, size = 312 * 1024, type = 'image/jpeg') {
    const file = new File([new Uint8Array(size)], 'photo.' + type.split('/')[1], {type});
    return Object.assign(file, {width, height});
}

for (const [name, settings, dimensions] of [
    ['Original', {}, [1920, 1440]],
    ['matching crop ratio', {mode: 'crop', maxDimension: 1200}, [1200, 900]],
    ['matching pad ratio', {mode: 'pad', maxDimension: 1200}, [1200, 900]],
    ['venue resolution', {maxDimension: 1280}, [1280, 960]]
]) {
    test('large image is downscaled without opening editor: ' + name, async () => {
        const e = environment(settings);
        const original = photo();
        await e.inspect(original);
        assert.notEqual(e.input.files[0], original);
        assert.deepEqual([e.canvases[0].width, e.canvases[0].height], dimensions);
        assert.ok(e.input.files[0].size <= 400 * 1024);
        assert.equal(e.input.files[0].name, 'photo-resized.jpg');
        assert.equal(e.input.files[0].type, 'image/jpeg');
        assert.equal(e.input.accepted, 1);
        assert.equal(e.opened.length, 0);
        assert.equal(e.context.upload.editorSourceFiles.get(e.input), original);
    });
}

test('small compliant files are unchanged and never upscaled', async () => {
    const e = environment();
    const original = photo(600, 400, 10000);
    await e.inspect(original);
    assert.equal(e.input.files[0], original);
    assert.equal(e.canvases.length, 0);
});

test('matching fixed ratios remain exact at non-divisible selected resolutions', async () => {
    for (const mode of ['crop', 'pad']) {
        const e = environment({mode, ratioWidth: 16, ratioHeight: 9, maxDimension: 1300});
        await e.inspect(photo(4000, 2250));
        assert.deepEqual([e.canvases[0].width, e.canvases[0].height], [1296, 729]);
        assert.equal(e.opened.length, 0);
    }
});

test('oversized PNG and JPEG use the existing byte-limit compression loop', async () => {
    for (const mime of ['image/png', 'image/jpeg']) {
        const e = environment();
        e.control.bytesPerPixel = 2;
        await e.inspect(photo(1000, 1000, 3000000, mime));
        assert.ok(e.input.files[0].size <= 400 * 1024);
        assert.equal(e.input.files[0].type, mime);
        assert.ok(e.canvases.length > 1, 'Compression should shrink pixels if quality alone is insufficient.');
        assert.ok(e.canvases.every(canvas => canvas.width <= 1000 && canvas.height <= 1000));
    }
});

test('portrait WebP keeps its orientation, aspect ratio and allowed format', async () => {
    const e = environment({maxDimension: 1200});
    await e.inspect(photo(1200, 1800, 300000, 'image/webp'));
    assert.deepEqual([e.canvases[0].width, e.canvases[0].height], [800, 1200]);
    assert.equal(e.input.files[0].type, 'image/webp');
});

test('encoding or decoding failures retain the original for server validation', async () => {
    for (const invalid of [true, false]) {
        const e = environment();
        e.control.failEncoding = !invalid;
        const original = Object.assign(photo(), {invalid});
        await e.inspect(original);
        assert.equal(e.input.files[0], original);
        assert.equal(e.input.accepted, 1);
    }
});

test('minimum dimensions are not bypassed to satisfy the byte limit', async () => {
    const e = environment({minDimension: 1000, maxBytes: 100});
    const original = photo();
    await e.inspect(original);
    assert.equal(e.input.files[0], original);
});

test('unsupported input is not converted and still goes to server validation', async () => {
    const e = environment();
    const original = photo(4000, 3000, 100, 'image/gif');
    await e.inspect(original);
    assert.equal(e.input.files[0], original);
    assert.equal(e.images.length, 0);
});

test('mismatching crop and pad ratios still open the existing editor', async () => {
    for (const mode of ['crop', 'pad']) {
        const e = environment({mode, ratioWidth: 16, ratioHeight: 9});
        const original = photo();
        await e.inspect(original);
        assert.equal(e.opened.length, 1);
        assert.equal(e.opened[0][1], original);
        assert.equal(e.input.accepted, 0);
    }
});

test('a previous slow selection cannot overwrite a newer file', async () => {
    const e = environment();
    e.control.deferredImages = true;
    const first = e.inspect(photo());
    const newer = photo(600, 400, 100);
    const second = e.inspect(newer);
    e.images[1]();
    await second;
    e.images[0]();
    await first;
    assert.equal(e.input.files[0], newer);
    assert.equal(e.input.accepted, 1);
});

test('clearing an image cancels pending selection processing', async () => {
    const e = environment();
    e.control.deferredImages = true;
    const pending = e.inspect(photo());
    e.input.files = [];
    e.listeners.change({target: e.input});
    e.images[0]();
    await pending;
    assert.equal(e.input.files.length, 0);
    assert.equal(e.input.accepted, 0);
});

test('a camera or other programmatic replacement is not overwritten', async () => {
    const e = environment();
    e.control.deferredImages = true;
    const pending = e.inspect(photo());
    const replacement = photo(800, 600, 100);
    e.input.files = [replacement];
    e.images[0]();
    await pending;
    assert.equal(e.input.files[0], replacement);
    assert.equal(e.input.accepted, 0);
});
