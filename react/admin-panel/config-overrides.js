const fs = require('fs');
const path = require('path');
const {
    override,
    removeModuleScopePlugin,
    babelInclude,
} = require('customize-cra');

module.exports = override(
    removeModuleScopePlugin(),
    babelInclude([
        path.resolve(path.join(__dirname, 'src')),
        fs.realpathSync(path.join(__dirname, '../shared')),
    ]),
);