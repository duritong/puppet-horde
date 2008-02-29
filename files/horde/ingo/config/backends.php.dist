<?php
/**
 * $Horde: ingo/config/backends.php.dist,v 1.20.8.6 2006/10/09 15:52:48 jan Exp $
 *
 * Ingo works purely on a preferred mechanism for server selection. There are
 * a number of properties that you can set for each backend:
 *
 * driver:       The Ingo_Driver driver to use to get the script to the
 *               backend server. Valid options:
 *                   'null'       --  No backend server
 *                   'timsieved'  --  Cyrus timsieved server
 *                   'vfs'        --  Use Horde VFS
 *                   'ldap'       --  LDAP server
 *
 * preferred:    This is the field that is used to choose which server is
 *               used. The value for this field may be a single string or an
 *               array of strings containing the hostnames to use with this
 *               server.
 *
 * hordeauth:    Ingo uses the current logged in username and password. If
 *               you want the full username@realm to be used to connect then
 *               set this to 'full' otherwise set this to true and just the
 *               username will be used to connect to the driver.
 *
 * params:       An array containing any additional information that the
 *               Ingo_Driver class needs.
 *
 * script:       The type of Ingo_Script driver this server uses.
 *               Valid options:
 *                   'imap'      --  IMAP client side filtering
 *                   'maildrop'  --  Maildrop scripts
 *                   'procmail'  --  Procmail scripts
 *                   'sieve'     --  Sieve scripts
 *
 * scriptparams: An array containing any additional information that the
 *               Ingo_Script driver needs.
 */

/* IMAP Example */
$backends['imap'] = array(
    'driver' => 'null',
    'preferred' => 'example.com',
    'hordeauth' => true,
    'params' => array(),
    'script' => 'imap',
    'scriptparams' => array()
);

/* Maildrop Example */
$backends['maildrop'] = array(
    'driver' => 'vfs',
    'preferred' => 'example.com',
    'hordeauth' => true,
    'params' => array(
        // Hostname of the VFS server
        'hostspec' => 'ftp.example.com',
        // Name of the maildrop config file to write
        'filename' => '.mailfilter',
        // Port of the VFS server
        'port' => 21,
        // The VFS driver to use
        'vfstype' => 'ftp',
        // The VFS username to use, defaults to current user
        // 'username' => 'user',
        // The VFS password to use, defaults to current user's password
        // 'password' => 'secret',
        // The path to the .mailfilter filter file, defaults to the current
        // user's home directory.
        // You can use the following variables:
        //   %u = name of the current user
        //   %U = the 'username' from above
        // Example:
        //   '/data/maildrop/filters/%u/'
        //   This would be translated into:
        //   '/data/maildrop/filters/<logged_in_username>/.mailfilter'
        // 'vfs_path' => '/path/to/maildrop/',
    ),
    'script' => 'maildrop',
    'scriptparams' => array(
        // What path style does the IMAP server use ['mbox'|'maildir']?
        'path_style' => 'mbox',
        // An array of variables to append to every generated script.
        // Use if you need to set up specific environment variables.
        'variables' => array(
            // Example for the $PATH variable
            // 'PATH' => '/usr/bin'
        )
    )
);

/* Procmail Example */
$backends['procmail'] = array(
    'driver' => 'vfs',
    'preferred' => 'example.com',
    'hordeauth' => true,
    'params' => array(
        // Hostname of the VFS server
        'hostspec' => 'ftp.example.com',
        // Name of the procmail config file to write
        'filename' => '.procmailrc',
        // Port of the VFS server
        'port' => 21,
        // The VFS driver to use
        'vfstype' => 'ftp'
        // The VFS username to use, defaults to current user
        // 'username' => 'user',
        // The VFS password to use, defaults to current user's password
        // 'password' => 'secret',
        // The path to the .procmailrc filter file, defaults to the current
        // user's home directory.
        // You can use the following variables:
        //   %u = name of the current user
        //   %U = the 'username' from above
        // Example:
        //   '/data/procmail/filters/%u/'
        //   This would be translated into:
        //   '/data/procmail/filters/<logged_in_username>/.procmailrc'
        // 'vfs_path' => '/path/to/procmail/',
    ),
    'script' => 'procmail',
    'scriptparams' => array(
        // What path style does the IMAP server use ['mbox'|'maildir']?
        'path_style' => 'mbox',
        // An array of variables to append to every generated script.
        // Use if you need to set up specific environment variables.
        'variables' => array(
            // Example for the $PATH variable
            // 'PATH' => '/usr/bin',
            // Example for the $DEFAULT variable
            // 'DEFAULT' => '$HOME/Maildir',
        )
    )
);

/* Sieve Example */
$backends['sieve'] = array(
    'driver' => 'timsieved',
    'preferred' => 'example.com',
    'hordeauth' => true,
    'params' => array(
        // Hostname of the timsieved server
        'hostspec' => 'mail.example.com',
        // Login type of the server
        'logintype' => 'PLAIN',
        // Enable/disable TLS encryption
        'usetls' => true,
        // Port number of the timsieved server
        'port' => 2000,
        // Name of the sieve script
        'scriptname' => 'ingo',
        // The following settings can be used to specify an administration
        // user to update all users' scripts.
        // 'admin' => 'cyrus',
        // 'password' => '*****',
        // 'username' => Auth::getAuth(),
    ),
    'script' => 'sieve',
    'scriptparams' => array()
);

/* Sun ONE/JES Example (LDAP/Sieve) */
$backends['ldapsieve'] = array(
    'driver' => 'ldap',
    'preferred' => 'example.com',
    'hordeauth' => false,
    'params' => array(
        //
        // Hostname of the ldap server
        //
        'hostspec' => 'ldap.example.com',
        //
        // Port number of the timsieved server
        //
        'port' => 389,
        //
        // LDAP Protocol Version (default = 2).  3 is required for TLS.
        //
        'version' => 3,
        //
        // Whether or not to use TLS.  If using TLS, you MUST configure
        // OpenLDAP (either /etc/ldap.conf or /etc/ldap/ldap.conf) with the CA
        // certificate which signed the certificate of the server to which you
        // are connecting.  e.g.:
        //
        // TLS_CACERT /usr/share/ca-certificates/mozilla/Equifax_Secure_CA.crt
        //
        // You MAY have problems if you are using TLS and your server is
        // configured to make random referrals, since some OpenLDAP libraries
        // appear to check the certificate against the original domain name,
        // and not the referred-to domain.  This can be worked around by
        // putting the following directive in the ldap.conf:
        //
        // TLS_REQCERT never
        //
        'tls' => true,
        //
        // Bind DN (for bind and script distinguished names, %u is replaced
        // with username, and %d is replaced with the internet domain
        // components (e.g. "dc=example, dc=com") if available).
        //
        'bind_dn' => 'cn=ingo, ou=applications, dc=example, dc=com',
        //
        // Bind password.  If not provided, user's password is used (useful
        // when bind_dn contains %u).
        //
        'bind_password' => 'secret',
        //
        // How to find user object.
        //
        'script_base' => 'ou=People, dc=example, dc=com',
        'script_filter' => '(uid=%u)',
        //
        // Attribute script is stored in.  Will not touch non-Ingo scripts.
        //
        'script_attribute' => 'mailSieveRuleSource'
    ),
    'script' => 'sieve',
    'scriptparams' => array()
);

/* Kolab Example (using Sieve) */
if ($GLOBALS['conf']['kolab']['enabled']) {
    $backends['kolab'] = array(
        'driver' => 'timsieved',
        'preferred' => '',
        'hordeauth' => 'full',
        'params' => array(
            'hostspec' => $GLOBALS['conf']['kolab']['imap']['server'],
            'logintype' => 'PLAIN',
            'usetls' => false,
            'port' => $GLOBALS['conf']['kolab']['imap']['sieveport'],
            'scriptname' => 'kmail-vacation.siv'
        ),
        'script' => 'sieve',
        'scriptparams' => array()
    );
}
