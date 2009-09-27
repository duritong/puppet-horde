class horde::centos inherits horde::base {
    package{'horde-enhanced':
        ensure => installed,
        require => Package['horde'],
    }
}
