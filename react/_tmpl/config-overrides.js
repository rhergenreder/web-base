const fs = require('fs');
const path = require('path');
const {
    override,
    removeModuleScopePlugin,
    babelInclude,
    addWebpackModuleRule,
} = require('customize-cra');

const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const addMiniCssExtractPlugin = config => {
    config.plugins.push(new MiniCssExtractPlugin());
    return config;
}

module.exports = override(
    removeModuleScopePlugin(),
    addMiniCssExtractPlugin,
    addWebpackModuleRule({
        test: /\.css$/,
        use: [ MiniCssExtractPlugin.loader, 'css-loader' ]
    }),
    babelInclude([
        path.resolve(path.join(__dirname, 'src')),
        fs.realpathSync(path.join(__dirname, '../shared')),
    ]),
);