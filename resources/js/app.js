// Livewire ships its own Alpine instance; importing both would start Alpine twice.
import { Livewire } from '../../vendor/livewire/livewire/dist/livewire.esm';
import { registerNotifications } from './notifications';
import './preview-frame';
import './builder-preview';
import './marketing-demo';

// Listeners must exist before Livewire starts so early events are not missed.
registerNotifications(Livewire);

Livewire.start();
