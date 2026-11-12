require('bootstrap');
window.Vue = require('vue/dist/vue.esm-bundler.js');
window.axios = require('axios');
window.DataTable = require('datatables.net-bs5');

// Blade-rendered user data must never be reinterpreted as Vue interpolation.
// HTML parsing replaces NUL characters, so these delimiters cannot occur in DOM text.
window.mountMyIdlers = (options, selector) => {
    const app = window.Vue.createApp(options);
    app.config.compilerOptions.delimiters = ['\u0000{', '}\u0000'];
    return app.mount(selector);
};
