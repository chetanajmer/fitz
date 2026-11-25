    <x-progress.transformation :data="$transformation" :photo-url="$photoUrl" />

    @include('livewire.partials.progress.reports')

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Body measurements</h2></div>
            @include('livewire.partials.progress.measurements')
        </div>

        <div class="card">
            @include('livewire.partials.progress.photos')
        </div>
    </div>
