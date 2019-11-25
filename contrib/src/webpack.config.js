const Encore = require('@symfony/webpack-encore');

Encore
    .setOutputPath('public/build/')
    .setPublicPath('/build')
    .setManifestKeyPrefix('build/')

    .cleanupOutputBeforeBuild()
    .autoProvidejQuery()
    .enablePostCssLoader()
    .enableSourceMaps(!Encore.isProduction())
    .enableSassLoader(function(sassOptions) {}, {
        resolveUrlLoader: false
    })

    .addStyleEntry('css/app', './assets/css/app.scss')
    .addEntry('js/app', './assets/js/app.js')
;

module.exports = Encore.getWebpackConfig();
