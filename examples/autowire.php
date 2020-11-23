<?php

use Dakujem\Wire\Attributes\Hot;
use Dakujem\Wire\Attributes\Skip;
use Dakujem\Wire\Attributes\Wire;

class Service {}
interface ServiceInterface {}
class MyService implements ServiceInterface {
    public function __construct(Service $s1, $foo)
    {
        // $foo should be 123
    }
}
class MyOtherService {
    public function __construct(Service $s1)
    {
    }
}

// dalsi atribut `Default`  #[Default(42)] - ma zmysel?
// alebo mozno lepsie pridat nejako moznost spracovat vlastne atributy


$toCall = function (
    ?Service $s1,
    #[Hot(123), Wire(MyService::class)] ServiceInterface $s2,
    $static,
    #[Wire('fokit')] $foo,
    #[Hot] MyOtherService $s3,
    #[Skip] ?Service $skap, // No, NoWire, Skip, Omit, Hop, Nope, Off
    #[Skip] $nah,
    Service|MyService $union, // + union types
    $another
) {
    // do something for foo sake
};

$wire = function(callable $code, ...$args){

    // sluzba je oznacena ako Hot a kontajner ju nema, takze...
    // skusime ju skonstruovat manualne, ak to pojde

    $code(...$args);
};

$wire($toCall, 'another value', static: 42, );

