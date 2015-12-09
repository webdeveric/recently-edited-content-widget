module.exports = function( grunt ) {
    "use strict";

    var openCommand = "open";

    /* jshint ignore:start */
    if ( process.platform === "linux" ) {
        openCommand = "xdg-open";
    }
    /* jshint ignore:end */

    var jsFiles = [ "Gruntfile.js", "./js/src/**/*.js", "!./js/src/**/*.min.js" ],
        imgFilesSrc = "./imgs/",
        imgFilesDest = "./imgs/",
        config = {
            pkg: grunt.file.readJSON("package.json"),

            // https://github.com/gruntjs/grunt-contrib-sass
            sass: {
                css: {
                    options: {
                        style: "compressed"
                    },
                    files: [ {
                        expand: true,
                        cwd: "./scss",
                        src: [ "*.scss", "*.sass" ],
                        dest: "./css",
                        ext: ".css"
                    } ]
                }
            },

            // https://github.com/nDmitry/grunt-autoprefixer
            autoprefixer: {
                options: {
                    browsers: [ "last 3 versions", "ie 8", "ie 9" ]
                },
                css: {
                    expand: true,
                    flatten: true,
                    map: true,
                    src: "./css/*.css",
                    dest: "./css/"
                },
            },

            // https://github.com/gruntjs/grunt-contrib-jshint
            jshint: {
                src: jsFiles,
                options: {
                    jshintrc: "./.jshintrc"
                }
            },

            // https://github.com/jscs-dev/grunt-jscs
            jscs: {
                src: jsFiles,
                options: {
                    config: "./.jscs.json"
                }
            },

            // https://github.com/gruntjs/grunt-contrib-uglify
            uglify: {
                options: {
                    compress: true,
                    banner: "/* <%= pkg.name %> <%= pkg.version %> <%= grunt.template.today('yyyy-mm-dd') %> */",
                    sourceMap: "js/dist/<%= pkg.name %>.js",
                    preserveComments: "some",
                    drop_console: true
                },

                all: {
                    files: [ {
                        expand: true,
                        cwd: "./js/src/",
                        src: "**/*.js",
                        dest: "./js/dist/"
                    } ]
                }
            },

            // https://github.com/gruntjs/grunt-contrib-imagemin
            imagemin: {
                png: {
                    options: {
                        optimizationLevel: 7
                    },
                    files: [ {
                        expand: true,
                        cwd: imgFilesSrc,
                        src: [ "**/*.png" ],
                        dest: imgFilesDest,
                        ext: ".png"
                    } ]
                },
                jpg: {
                    options: {
                        progressive: true
                    },
                    files: [ {
                        expand: true,
                        cwd: imgFilesSrc,
                        src: [ "**/*.jpg" ],
                        dest: imgFilesDest,
                        ext: ".jpg"
                    } ]
                },
                gif: {
                    files: [ {
                        expand: true,
                        cwd: imgFilesSrc,
                        src: [ "**/*.gif" ],
                        dest: imgFilesDest,
                        ext: ".gif"
                    } ]
                },
                svg: {
                    options: {
                        plugins: [
                            {
                                removeViewBox: false
                            }, {
                                removeUselessStrokeAndFill: false
                            }
                        ]
                    },
                    files: [ {
                        expand: true,
                        cwd: imgFilesSrc,
                        src: [ "**/*.svg" ],
                        dest: imgFilesDest,
                        ext: ".svg"
                    } ]
                }
            },

            // https://github.com/jsoverson/grunt-plato
            plato: {
                app: {
                    options: {
                        jshint: grunt.file.readJSON(".jshintrc")
                    },
                    files: {
                        "./.reports/plato": jsFiles
                    }
                }
            },

            // https://github.com/sindresorhus/grunt-shell
            shell: {
                platoreports: {
                    command: openCommand + " ./.reports/plato/index.html"
                }
            },

            checkwpversion: {
                options:{
                    readme: "readme.txt",
                    plugin: "<%= pkg.name %>.php",
                },
                check: {
                    version1: "plugin",
                    version2: "readme",
                    compare: "=="
                },
                check2: {
                    version1: "plugin",
                    version2: "<%= pkg.version %>",
                    compare: "==",
                }
            },

            // https://github.com/gruntjs/grunt-contrib-watch
            watch: {
                sass: {
                    files: [ "scss/**/*.{scss,sass}" ],
                    tasks: [ "style" ]
                },

                js: {
                    files: [ "./js/**/*.js" ],
                    tasks: [ "js" ]
                },

                pngimgs: {
                    files: [ imgFilesSrc + "**/*.png" ],
                    tasks: [ "imagemin:png" ],
                    options: {
                        spawn: false
                    }
                },

                jpgimgs: {
                    files: [ imgFilesSrc + "**/*.jpg" ],
                    tasks: [ "imagemin:jpg" ],
                    options: {
                        spawn: false
                    }
                },

                gifimgs: {
                    files: [ imgFilesSrc + "**/*.gif" ],
                    tasks: [ "imagemin:gif" ],
                    options: {
                        spawn: false
                    }
                },

                svgimgs: {
                    files: [ imgFilesSrc + "**/*.svg" ],
                    tasks: [ "imagemin:svg" ],
                    options: {
                        spawn: false
                    }
                },

                livereload: {
                    options: {
                        livereload: {
                            port: 12345
                        }
                    },
                    files: [
                        imgFilesDest + "/*",
                        "./css/*",
                        "./js/dist/*"
                    ]
                }
            }

        };

    grunt.config.init( config );

    // https://github.com/sindresorhus/load-grunt-tasks
    require("load-grunt-tasks")(grunt);

    // https://www.npmjs.com/package/time-grunt
    require("time-grunt")(grunt);

    grunt.registerTask(
        "default",
        [ "checkwpversion", "assets", "watch" ]
    );

    grunt.registerTask(
        "assets",
        [ "style", "js", "imagemin" ]
    );

    grunt.registerTask(
        "style",
        [ "sass", "autoprefixer" ]
    );

    grunt.registerTask(
        "js",
        [ "jshint", "jscs", "uglify" ]
    );

    grunt.registerTask(
        "reports",
        [ "jshint", "jscs", "plato", "shell:platoreports" ]
    );

};
