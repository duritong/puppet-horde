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
  $logmode = 'default'
){
  if $ensure == 'present' {
    file{"/etc/cron.hourly/horde_${name}_cache_cleanup.cron":
    content => "#!/bin/bash\n/usr/sbin/tmpwatch 24 /var/www/upload_tmp_dir/${name}/\n",
    owner => apache, group => apache, mode => 0700;
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
  }
  apache::vhost::php::standard{$name:
    ensure => $ensure,
    domainalias => $domainalias,
    manage_docroot => false,
    path => $operatingsystem ? {
      gentoo => '/var/www/localhost/htdocs/horde',
      default => '/usr/share/horde'
    },
    logpath => $operatingsystem ? {
      gentoo => '/var/log/apache2/',
      default => '/var/log/httpd'
    },
    logmode => $logmode,
    manage_webdir => false,
    path_is_webdir => true,
    ssl_mode => $ssl_mode,
    php_use_pear => true,
    template_partial => 'horde/vhost/horde.erb',
    additional_options => $additional_options,
    require => Package['horde'],
    mod_security => false,
  }

  if $use_nagios {
    $real_monitor_url = $monitor_url ? {
      'absent' => $name,
      default => $monitor_url,
    }
    nagios::service::http{"${real_monitor_url}":
      ensure => $ensure,
      check_url => '/imp/login.php',
      ssl_mode => $ssl_mode,
    }
  }
}
