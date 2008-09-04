# manifests/dimp.pp

class horde::dimp {
    package{'dimp':
        ensure => installed,
        require => Package['horde'],
    }
}
