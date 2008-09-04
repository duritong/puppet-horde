# modules/horde/manifests/init.pp - manage horde stuff
# Copyright (C) 2007 admin@immerda.ch
#

class horde {
    case $operatingsystem {
        centos: { include horde::centos }
        default: { include horde::base }
    }
}

class horde::base {

    include php

    package{'horde':
        ensure => installed,
        require => Package['php'],
    }
}

class horde::centos inherits horde::base {
    package{'horde-enhanced':
        ensure => installed,
        require => Package['horde'],
    }
}
