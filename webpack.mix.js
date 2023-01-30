let mix = require('laravel-mix')

require('./nova.mix')

mix
    .setPublicPath('dist')
    .js('resources/js/field.js', 'js')
    .vue({version: 3})
    .nova('aris/restricted-morph-to-field')
