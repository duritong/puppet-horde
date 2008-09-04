# manifests/vacation.pp

class horde::vacation {
    package{'horde-vacation':
        ensure => installed,
        require => Package['horde'],
    }
}
