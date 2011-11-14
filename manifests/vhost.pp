define horde::vhost(
  $ensure = 'present',
  $horde_configs = [
    'registry.php',
    'conf.php',
    'prefs.php' ],
  $imp_configs = [
    'imp/servers.php',
    'imp/motd.php',
    'imp/conf.php',
    'imp/conf.xml',
    'imp/prefs.php',
  ],
  $dimp_configs = [
    'dimp/conf.php',
  ],
  $mimp_configs = [
    'mimp/conf.php',
  ],
  $mnemo_configs = [
    'mnemo/conf.php',
    'mnemo/prefs.php',
  ],
  $turba_configs = [
    'turba/sources.php',
    'turba/conf.php',
    'turba/prefs.php',
  ],
  $ingo_configs = [
    'ingo/conf.php',
    'ingo/prefs.php',
    'ingo/backends.php',
  ],
  $ingo_manage_sieve = false,
  $passwd_configs = [
    'passwd/backends.php',
    'passwd/conf.php',
  ],
  $nag_configs = [
    'nag/conf.php',
    'nag/prefs.php',
  ],
  $kronolith_configs = [
    'kronolith/conf.php'
  ],
  $domainalias = 'absent',
  $ssl_mode = 'force',
  $monitor_url = 'absent',
  $additional_options = 'absent',
  $logmode = 'default',
  $run_mode = 'normal',
  $run_uid = 'absent',
  $run_gid = 'absent'
){

  $documentroot = $operatingsystem ? {
    gentoo => '/var/www/localhost/htdocs/horde',
    default => '/usr/share/horde'
  }
  if $horde::install_type == 'pkg' {
    $srcroot = $documentroot
    $additional_open_basedir = ''
  } else {
    $srcroot = '/usr/share/horde_framework'
    $additional_open_basedir = ":${srcroot}"
  }

  include horde::vhost::absent_webconfig
  horde::config{$horde_configs:
      before => Service['apache']
  }
  Horde::Module::Config{
    before => Service['apache']
  }

  if (imp_configs != 'absent') {
    horde::module::config{$imp_configs: }
    if $use_shorewall {
        include shorewall::rules::out::imap
    }
  }
  if (dimp_configs != 'absent') {
      horde::module::config{$dimp_configs: }
    }
  if (mimp_configs != 'absent') {
        horde::module::config{$mimp_configs: }
  }
  if (mnemo_configs != 'absent') {
      horde::module::config{$mnemo_configs: }
    }
  if (turba_configs != 'absent') {
    horde::module::config{$turba_configs: }
  }
  if (ingo_configs != 'absent') {
    horde::module::config{$ingo_configs: }
    if $ingo_manage_sieve {
      include horde::ingo::managesieve
    }
  }
  if (passwd_configs != 'absent') {
    horde::module::config{$passwd_configs: }
  }
  if (nag_configs != 'absent') {
    horde::module::config{$nag_configs: }
  }
  if (kronolith_configs != 'absent') {
    horde::module::config{$kronolith_configs: }
  }

  if ($run_mode == 'fcgid'){
    if (($run_uid == 'absent') or ($run_gid == 'absent')) { fail("Need to configure \$run_uid and \$run_gid if you want to run Phpmyadmin::Vhost[${name}] as fcgid.") }

    user::managed{$name:
      ensure => $ensure,
      uid => $run_uid,
      gid => $run_gid,
      shell => $operatingsystem ? {
        debian => '/usr/sbin/nologin',
        ubuntu => '/usr/sbin/nologin',
        default => '/sbin/nologin'
      },
      before => Apache::Vhost::Php::Standard[$name],
    }
  }

  apache::vhost::php::standard{$name:
    ensure => $ensure,
    domainalias => $domainalias,
    manage_docroot => false,
    path => $documentroot,
    path_is_webdir => true,
    manage_webdir => false,
    logpath => $operatingsystem ? {
      gentoo => '/var/log/apache2/',
      default => '/var/log/httpd'
    },
    logmode => $logmode,
    run_mode => $run_mode,
    run_uid => $name,
    run_gid => $name,
    ssl_mode => $ssl_mode,
    options => '+FollowSymLinks',
    php_settings => {
      safe_mode               => 'Off',
      register_globals        => 'Off',
      magic_quotes_runtime    => 'Off',
      'session.use_trans_sid' => 'Off',
      'session.auto_start'    => 'Off',
      file_uploads            => 'On',
      display_errors          => 'Off',
      register_globals        => 'Off',
      open_basedir            => "/usr/share/php/:${documentroot}/:/etc/horde/:/usr/share/pear/:/var/www/upload_tmp_dir/${name}/:/var/www/session.save_path/${name}:/var/www/vhosts/${name}/logs/${additional_open_basedir}"
    },
    php_options => { use_pear => true },
    additional_options => "
  <Directory /etc/horde>
      Order Deny,Allow
      Deny from all
  </Directory>

  <DirectoryMatch \"^${documentroot}/(.*/)?(config|lib|locale|po|scripts|templates)/(.*)?\">
    Order deny,allow
    Deny  from all
  </DirectoryMatch>

  <LocationMatch \"^/(.*/)?test.php\">
   Order deny,allow
   Deny  from all
   Allow from localhost
  </LocationMatch>
  ${additional_options}",
    require => Package['horde'],
    mod_security => false,
  }
  if $ensure == 'present' {
    file{"/etc/cron.hourly/horde_${name}_cache_cleanup.cron":
      content => "#!/bin/bash\n/usr/sbin/tmpwatch 24 /var/www/upload_tmp_dir/${name}/\n",
      owner => apache, group => apache, mode => 0700;
    }
    if $horde::install_type == 'git4' {
      Apache::Vhost::Php::Standard[$name]{
        allow_override => 'FileInfo'
      }
    }
  }

  if hiera('use_nagios',false) {
    $real_monitor_url = $monitor_url ? {
      'absent' => $name,
      default => $monitor_url,
    }
    nagios::service::http{"${real_monitor_url}":
      ensure => $ensure,
      check_url => '/imp/login.php',
      ssl_mode => $ssl_mode,
      check_code => $horde::install_type ? {
        'git4' => '301',
        default => 'OK',
      }
    }
  }
}
