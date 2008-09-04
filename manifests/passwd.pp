# manifests/passwd.pp

class horde::passwd {
    package{'horde-passwd':
        ensure => installed,
        require => Package['horde'],
    }
}
