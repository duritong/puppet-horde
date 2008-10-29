# manifests/php.pp

class horde::php {
    include php

    php::pecl{ 'Fileinfo': }
    php::pear{ [ 'MDB2', 'MDB2-Driver-pgsql', 'MDB2-Driver-mysql',
                'Cache-Lite', 'Date-Holidays', 'XML-Serializer' ]:
    }
}
