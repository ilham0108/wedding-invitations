<?php

namespace App\Livewire\Concerns;

/**
 * Show a SweetAlert2 toast from a Livewire action without a page reload.
 */
trait Notifies
{
    /**
     * @param  'success'|'error'|'warning'|'info'  $type
     */
    protected function notify(string $type, string $message): void
    {
        $this->dispatch('notify', type: $type, message: $message);
    }
}
