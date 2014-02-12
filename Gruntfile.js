module.exports = function(grunt){
	'use strict';
	grunt.initConfig( {
		pkg: grunt.file.readJSON('package.json'),
		compass: {
			css: {
				options: {
					sassDir: 'scss',
					cssDir: 'css/src'
				}
			}
		},
		concat: {
			css: {
				src: ['css/src/**/*.css'],
				dest: 'css/dist/<%= pkg.name %>.css'
			}
		},
		cssmin: {
			minify: {
				options: {
					banner: '/* <%= pkg.name %> <%= pkg.version %> <%= grunt.template.today("yyyy-mm-dd") %> */',
				},
				files: {
					'css/dist/<%= pkg.name %>.min.css' : 'css/dist/<%= pkg.name %>.css'
				}
			}
		},
		checkwpversion: {
			options:{
				readme: 'readme.txt',
				plugin: '<%= pkg.name %>.php',
			},
			check: {
				version1: 'plugin',
				version2: 'readme',
				compare: '=='
			},
			check2: {
				version1: 'plugin',
				version2: '<%= pkg.version %>',
				compare: '==',
			}
		},
		imagemin: {
			dist: {
				options: {
					optimizationLevel: 7,
					progressive: true
				},
				files: [{
					expand: true,
					cwd: 'imgs/',
					src: '**/*',
					dest: 'imgs/'
				}]
			}
		},
		watch: {
			compass: {
				files: ['scss/**/*.{scss,sass}'],
				tasks: ['compass', 'concat:css', 'cssmin']
			},
			css: {
				files: '<%= concat.css.src %>',
				tasks: ['concat:css', 'cssmin']
			}
		},
	} );

	grunt.loadNpmTasks( 'grunt-contrib-imagemin' );
	grunt.loadNpmTasks( 'grunt-contrib-compass' );
	grunt.loadNpmTasks( 'grunt-contrib-concat' );
	grunt.loadNpmTasks( 'grunt-contrib-cssmin' );
	grunt.loadNpmTasks( 'grunt-checkwpversion' );
	grunt.loadNpmTasks( 'grunt-contrib-watch' );

	grunt.registerTask( 'test', [ 'checkwpversion' ] );
	grunt.registerTask( 'default', [ 'checkwpversion', 'compass', 'concat', 'cssmin' ] );
};
