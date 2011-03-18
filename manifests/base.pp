class horde::base {
  include php
  include php::packages::geoip
  include php::packages::lzf
  include php::packages::services_weather
  include php::packages::cache
  include php::extensions::pear::file
  include php::extensions::pecl::fileinfo
  include php::extensions::pear::date_holidays
  include php::extensions::pear::http_webdav_server
  include php::extensions::pear::net_dns

  package{'horde':
      ensure => installed,
      require => Package['php'],
  }
}
