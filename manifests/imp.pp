class horde::imp {
  include php::packages::idn
  include php::packages::mail_mimedecode
  include gpg
  horde::module{'imp': }
  if $use_shorewall {
    include shorewall::rules::out::keyserver
    include shorewall::rules::out::imap
    include shorewall::rules::out::pop3
  }
}
