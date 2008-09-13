# manifests/imp.pp

class horde::imp {
    include php::packages::idn
    include gpg
    horde::module{'imp': }
}
