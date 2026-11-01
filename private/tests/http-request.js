// Exercise HTTP failures and cancellation using the real browser request helper.
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const context = {document: {addEventListener() {}}, __CSRF__: 'fixture', AbortController};
vm.createContext(context);
vm.runInContext(fs.readFileSync('public/global/js/global.js', 'utf8'), context);
(async function () {
    let received;
    const failure = async response => { received = await response.json(); };
    context.fetch = async () => { throw new TypeError('offline'); };
    await context.httpRequest('/test', {}, undefined, failure);
    assert.equal(received.success, false);
    context.fetch = async () => ({ok: false, status: 502, json: async () => { throw new SyntaxError('HTML gateway response'); }});
    await context.httpRequest('/test', {}, undefined, failure);
    assert.equal(received.success, false);
    let completed = false;
    context.fetch = async () => ({ok: true});
    await context.httpRequest('/test', {}, async () => {
        await new Promise(resolve => setTimeout(resolve, 5));
        completed = true;
    }, failure);
    assert.equal(completed, true);
    await context.httpRequest('/test', {}, async () => { throw new SyntaxError('invalid success body'); }, failure);
    assert.equal(received.success, false);
    const controller = new AbortController();
    controller.abort();
    received = null;
    await context.httpRequest('/test', {signal: controller.signal}, () => { throw new Error('Stale callback ran.'); }, failure);
    assert.equal(received, null);
    // The admin coordinator aborts only earlier requests for the same view.
    const source = fs.readFileSync('public/themes/admin/assets/js/admin.js', 'utf8');
    vm.runInContext(source.slice(0, source.indexOf('// Framework7 app instance')), context);
    const first = context.startAdminRead('table');
    const other = context.startAdminRead('logs');
    const latest = context.startAdminRead('table');
    assert.equal(first.signal.aborted, true);
    assert.equal(other.signal.aborted, false);
    assert.equal(latest.signal.aborted, false);
    console.log('HTTP errors, awaited callbacks and superseded reads passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
