module.exports = function (grunt) {
    grunt.loadNpmTasks('grunt-phpunit');
    grunt.loadNpmTasks('grunt-phpdocumentor');
    grunt.loadNpmTasks('grunt-phpcs');

    grunt.initConfig({
        phpunit: {
            classes: {
                dir: 'test/'
            },
            options: {
                bin: 'lib/vendor/bin/phpunit',
                bootstrap: 'test/bootstrap.php',
                coverage: true,
                coverageHtml: 'report/test/',
                coverageClover: 'report/test/clover.xml',
                colors: true,
                verbose: true
            }
        },
        phpdocumentor: {
            dist: {
                options: {
                    directory: 'src/class/',
                    target: 'doc/'
                }
            }
        },
        phpcs: {
            application: {
                dir: ['src/class/**/*.php']
            },
            options: {
                bin: 'lib/vendor/bin/phpcs',
                standard: ['PSR1', 'PSR2']
            }
        }
    })
};
