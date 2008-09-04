# manifests/ingo.pp

class horde::ingo {
    package{'ingo':
        ensure => installed,
        require => Package['horde'],
    }
}
