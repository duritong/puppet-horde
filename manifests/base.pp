class horde::base {
    include php
    include php::packages::geoip
    include php::packages::lzf
    include php::packages::services_weather
    include php::packages::cache

    package{'horde':
        ensure => installed,
        require => Package['php'],
    }
}
