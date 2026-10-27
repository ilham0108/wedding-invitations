<?php

namespace App\View\Components\Ui;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Collects session flashes for the SweetAlert2 toasts (resources/js/notifications.js).
 * Flash with: ->with('success'|'error'|'warning'|'info', __('Message')).
 */
class Toast extends Component
{
    public const TYPES = ['success', 'error', 'warning', 'info'];

    /** @var array<int, array{type: string, message: string}> */
    public array $messages = [];

    public function __construct()
    {
        foreach (self::TYPES as $type) {
            $message = session($type);

            if (is_string($message) && $message !== '') {
                $this->messages[] = ['type' => $type, 'message' => $message];
            }
        }
    }

    public function render(): View|Closure|string
    {
        return view('components.ui.toast');
    }
}
