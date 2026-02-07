import 'bootstrap';

// Инициализация компонентов Bootstrap
import { Tooltip, Popover, Modal, Toast } from 'bootstrap';

// FullCalendar
import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import timeGridPlugin from '@fullcalendar/timegrid';
import listPlugin from '@fullcalendar/list';

window.FullCalendar = {
    Calendar,
    dayGridPlugin,
    interactionPlugin,
    timeGridPlugin,
    listPlugin
};
