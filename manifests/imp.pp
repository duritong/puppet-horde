# manifests/imp.pp

class horde::imp {
    include php::packages::idn
    horde::module{'imp': }
}
