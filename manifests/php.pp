# manifests/php.pp

class horde::php {
  include php

  include php::extensions::pecl::fileinfo
  include php::extensions::pear::mdb2
  include php::extensions::pear::mdb2::pgsql
  include php::extensions::pear::mdb2::mysql
  include php::extensions::pear::cache_lite
  include php::extensions::pear::date_holidays
  include php::extensions::pear::xml_serializer
}
