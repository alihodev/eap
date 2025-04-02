<?php

/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('WP_CACHE', false);
define('WPCACHEHOME', '/home/arabella/public_html/wp-content/plugins/wp-super-cache/');
define('DB_NAME', 'arabella_wp_nx4kk');

/** Database username */
define('DB_USER', 'arabella_wp_ljgjd');

/** Database password */
define('DB_PASSWORD', '$dIKyw3s8c1u^FmY');

/** Database hostname */
define('DB_HOST', 'localhost:3306');

/** Database charset to use in creating database tables. */
define('DB_CHARSET', 'utf8');

/** The database collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY', '-%[]v#8l@2t42|eG+8_4tcYh5xTu@1X4FBaNK&Y0r~5aWL1%Z5KZ36ACsLtnU~9/');
define('SECURE_AUTH_KEY', 'g5f!o!0E08EU~0J~4pn8wG#~3DSp-KS/M!-QK2F1zibo@;G5H6)H46u0;XG01zW5');
define('LOGGED_IN_KEY', 'Z257YIl8@l0sS25l+:G-IWjAaJ#r3cJTfdXss_QoID+uT!)DJ892%K9)U0~1dPg|');
define('NONCE_KEY', 'thyL[6&V16jV2][022;0|-jQpY0|r2U]TV#([sp008BLl%xLSbav!L#1z(k[[W9!');
define('AUTH_SALT', 'n0YY@71:i(;nvy:7!l-8%p1A*]f5zwAym7QDD07TSQf[i24ne4]/05QfUAu|1~+6');
define('SECURE_AUTH_SALT', 'Cqq8Hlx1]uxy57rA86@f5fnU8/9386j1M@ruu81749tR|P]vL9ey87h~yn67DJ41');
define('LOGGED_IN_SALT', '6cm-DIE18pWWDTEdz2q*B7T!X4J7@U*Pq2M(b3+!-d[20D1Xsl7xY#LVdZN~VFKB');
define('NONCE_SALT', '!wv6HBu283H%F9zcFxkI&LQEu673vWQv)Km~2~WJ~p]2jHym4VhS4&)o77;!NFaR');


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'zWBJo_';


/* Add any custom values between this line and the "stop editing" line. */

define('WP_ALLOW_MULTISITE', true);
/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments. 
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
if (! defined('WP_DEBUG')) {
	define('WP_DEBUG', true);
	define('WP_DEBUG_LOG', true);
	error_reporting(E_ALL);
	ini_set('display_errors', 1);
	define('WP_DEBUG_DISPLAY', true);
}

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if (! defined('ABSPATH')) {
	define('ABSPATH', __DIR__ . '/');
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
