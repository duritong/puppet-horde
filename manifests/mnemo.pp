# manifests/mnemo.pp

class horde::mnemo {
    package{'mnemo':
        ensure => installed,
        require => Package['horde'],
    }
}
