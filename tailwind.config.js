/** @type {import('tailwindcss').Config} */
module.exports = {
	darkMode: 'class',
	content: [
		'./index.php',
		'./modules/**/*.php',
		'./includes/**/*.php',
		'./**/*.html',
		'./**/*.js',
	],
	theme: {
		extend: {},
	},
	plugins: [],
};


