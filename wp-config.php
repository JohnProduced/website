<?php
/**
 * The base configurations of the WordPress.
 *
 * This file has the following configurations: MySQL settings, Table Prefix,
 * Secret Keys, WordPress Language, and ABSPATH. You can find more information
 * by visiting {@link http://codex.wordpress.org/Editing_wp-config.php Editing
 * wp-config.php} Codex page. You can get the MySQL settings from your web host.
 *
 * This file is used by the wp-config.php creation script during the
 * installation. You don't have to use the web site, you can just copy this file
 * to "wp-config.php" and fill in the values.
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'produced');

/** MySQL database username */
define('DB_USER', 'root');

/** MySQL database password */
define('DB_PASSWORD', 'root');

/** MySQL hostname */
define('DB_HOST', 'localhost');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         '.sbc8CDfeDp+ieb!r?;[A9@5IaS$u*xTvMM019Ql`}spbSy(5e;kR{K`b:udG;r7');
define('SECURE_AUTH_KEY',  'hv1%Wz$/#*x%{(-Tbrx0ey8>3hj1G`Xu0cD]|7<EHTL4pGp%F0I|J0*]dp%D& q3');
define('LOGGED_IN_KEY',    '=w|=I)/Vwl+Z:*YB{DuyCRg1w .mU?w#SV7F/^!Mm;KUd_0t5z40k=);rfvCtMSL');
define('NONCE_KEY',        '.h#kz+A#QsgX-6[/- [ c!uHvwaJmf@jih]KIEDV=*~6u~+_M*QfMp!h<+[:[1wl');
define('AUTH_SALT',        'Al(ZP;SE#?%b/G|+!4Vvj5V)?]pk}Nbb;.Q(e}O/Z2_~284whNbhlKK$}1$]}2+-');
define('SECURE_AUTH_SALT', 'b%#8}H:dDFhC:tT@nl|VJ>Df/,U:zZoom~(R-dx8fPE7Fpf7h7:AT~/$Bdplar4[');
define('LOGGED_IN_SALT',   'XpQjN8JM< d>X~|;$acOrphs:wy$^+&4CVkJBqK9;wBeN[kAMn/7Rp+@:<-.+qh4');
define('NONCE_SALT',       'F5|M`S61i8PYopAbd@p$2As?+#5,cEl-*oATyY9k>44=/&|Kc-UARy+uQ/q+Qx:6');

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each a unique
 * prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'wp_';

/**
 * WordPress Localized Language, defaults to English.
 *
 * Change this to localize WordPress. A corresponding MO file for the chosen
 * language must be installed to wp-content/languages. For example, install
 * de_DE.mo to wp-content/languages and set WPLANG to 'de_DE' to enable German
 * language support.
 */
define('WPLANG', '');

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 */
define('WP_DEBUG', false);

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
