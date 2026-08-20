<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Page Layout
    |--------------------------------------------------------------------------
    |
    | Layout usado ao renderizar um componente Livewire como página inteira.
    | O padrão do Livewire 4 é "layouts::app" (resources/views/layouts/app),
    | mas o layout deste projeto vive em resources/views/components/layouts
    | para também ser utilizável como componente Blade (<x-layouts.app>).
    | Apontando aqui, nenhum componente precisa declarar #[Layout].
    |
    | Este arquivo é intencionalmente parcial: as demais chaves continuam
    | vindo da configuração do pacote via mergeConfigFrom.
    |
    */

    'component_layout' => 'components.layouts.app',

];
