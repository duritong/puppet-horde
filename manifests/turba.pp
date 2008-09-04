# manifests/turba.pp

class horde::turba {
    package{'turba':
        ensure => installed,
        require => Package['horde'],
    }
}
