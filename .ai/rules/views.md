---
paths:
  - 'resources/views/**'
---

# Views

## Layout de página do Livewire vem de config/livewire.php
O layout base vive em resources/views/components/layouts/app.blade.php (também usável como <x-layouts.app>). O padrão do Livewire 4 seria layouts::app (resources/views/layouts/app.blade.php) — sem o layout nesse caminho a renderização de página falha com "No hint path defined for [layouts]". Por isso config/livewire.php (parcial, apenas component_layout) aponta para components.layouts.app. Nenhum componente precisa declarar #[Layout]. Não mova o layout nem remova esse config sem ajustar o outro.
