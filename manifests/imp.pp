# manifests/imp.pp

class horde::imp {
    package{'imp':
        ensure => installed,
        require => Package['horde'],
    }
}
