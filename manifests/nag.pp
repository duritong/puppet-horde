# manifests/nag.pp

class horde::nag {
    package{'nag':
        ensure => installed,
        require => Package['horde'],
    }
}
