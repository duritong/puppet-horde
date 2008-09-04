# manifests/kronolith.pp

class horde::kronolith {
    package{'kronolith':
        ensure => installed,
        require => Package['horde'],
    }
}
