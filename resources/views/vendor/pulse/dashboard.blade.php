<x-pulse>
    <livewire:pulse_active_session cols='4' />

    <livewire:requests-graph cols='8' />

    <livewire:pulse.usage cols='4' rows='2' />

    <livewire:apps-load cols='8' rows='2' />

    <livewire:pulse.servers cols="full" />

    <livewire:database cols='6' title="Active threads" :values="['Threads_connected', 'Threads_running']" :graphs="[
    'avg' => ['Threads_connected' => '#ffffff', 'Threads_running' => '#3c5dff'],
]" />

    <livewire:database cols='6' title="Connections" :values="['Connections', 'Max_used_connections']" />

    <livewire:database cols='full' title="Innodb" :values="['Innodb_buffer_pool_reads', 'Innodb_buffer_pool_read_requests', 'Innodb_buffer_pool_pages_total']" :graphs="[
    'avg' => ['Innodb_buffer_pool_reads' => '#ffffff', 'Innodb_buffer_pool_read_requests' => '#3c5dff'],
]" />

    <livewire:pulse.table-info cols='4' rows='2' />

    <livewire:pulse.queues cols="4" />

    <livewire:pulse.cache cols="4" />

    <livewire:pulse.slow-queries cols="8" />

    <livewire:pulse.about-application cols="8"/>

    <livewire:pulse.validation-errors cols="4" />

    <livewire:pulse.exceptions cols="6" />

    <livewire:pulse.slow-requests cols="6" />

    <livewire:pulse.slow-jobs cols="6" />

    <livewire:pulse.slow-outgoing-requests cols="6" />
</x-pulse>
