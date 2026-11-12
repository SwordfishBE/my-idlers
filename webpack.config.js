const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const CopyWebpackPlugin = require('copy-webpack-plugin');
const CssMinimizerPlugin = require('css-minimizer-webpack-plugin');

module.exports = (_, argv) => ({
    mode: argv.mode || 'production',
    entry: {
        app: './resources/js/app.js',
        'css/app': './resources/css/app.css',
        'css/icons': './resources/sass/app.scss',
        'css/light': './resources/css/light.css',
        'css/dark': './resources/css/dark.css',
    },
    output: {
        path: path.resolve(__dirname, 'public'),
        filename: (pathData) => pathData.chunk.name === 'app' ? 'js/app.js' : '.build/[name].js',
    },
    module: {
        rules: [
            {
                test: /\.css$/i,
                use: [MiniCssExtractPlugin.loader, { loader: 'css-loader', options: { url: false, import: false } }, {
                    loader: 'postcss-loader',
                    options: { postcssOptions: { plugins: [require('postcss-import'), require('autoprefixer')] } },
                }],
            },
            {
                test: /\.scss$/i,
                use: [MiniCssExtractPlugin.loader, { loader: 'css-loader', options: { url: false } }, {
                    loader: 'sass-loader', options: { sassOptions: { quietDeps: true, silenceDeprecations: ['import', 'global-builtin'] } },
                }],
            },
        ],
    },
    plugins: [
        new MiniCssExtractPlugin({ filename: '[name].css' }),
        new CopyWebpackPlugin({ patterns: [{ from: 'node_modules/@fortawesome/fontawesome-free/webfonts/*.woff2', to: 'webfonts/[name][ext]' }] }),
    ],
    optimization: { minimizer: ['...', new CssMinimizerPlugin()] },
    stats: 'errors-warnings',
});
