// Exercise real admin render functions with a minimal DOM adapter.
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync('public/themes/admin/assets/js/admin.js', 'utf8');
function definition(name) {
    const start = source.indexOf('function ' + name + '(');
    const next = source.indexOf('\nfunction ', start + 1);
    return source.slice(start, next === -1 ? undefined : next);
}
const markup = {};
const dom = selector => ({empty() {}, html(value) { markup[selector] = value; }});
const context = {$$: dom, columnDetails: {VALUE: {DETECTED_TYPE: 'string', NAME: 'VALUE'}}, isColumnVisible: () => true};
vm.createContext(context);
vm.runInContext([definition('escapeHtml'), definition('safeLocalURL'), definition('loadSQLQueryData')].join('\n'), context);
context.loadSQLQueryData('audit', [{VALUE: '<img src=x onerror="alert(1)">'}]);
assert.ok(markup['.tableBody'].includes('&lt;img'));
assert.ok(!markup['.tableBody'].includes('<img'));
assert.equal(context.safeLocalURL('//example.invalid'), '/');
assert.equal(context.escapeHtml('" onfocus="x'), '&quot; onfocus=&quot;x');
console.log('Admin table escaping and local navigation passed.');
