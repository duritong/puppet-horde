class horde::vhost::absent_webconfig {
	file {
		'/etc/httpd/conf.d/horde.conf' :
			ensure => absent,
			notify => Service['apache'],
	}
}