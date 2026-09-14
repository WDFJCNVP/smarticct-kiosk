<?php

use Livewire\Component;

new class extends Component
{

    public $getRouteList = [];

    public function mount() {

        if (! session()->has('kiosk_card')) {
            $this->redirect(route('login.options'), navigate: true);
            return;
        }

        $this->getRouteList = session('kiosk_route_list', []);

        // dd(session('kiosk_vehicle'));
    }
};
?>

<div>

    <flux:button href="{{ route('menu.options') }}" variant="primary">Back</flux:button>

    <div class="mt-8">
        <h2 class="text-section-heading">Local Routes</h2>

        <flux:card class="mt-3 p-0! overflow-hidden border border-light-bd-default dark:border-dark-bd-default">
            <div class="overflow-x-auto">
                <flux:table container:class="md:max-h-160">
                    <flux:table.columns sticky class="bg-light-secondary/50 items-center bg-light-subtle/50 dark:bg-dark-secondary/50 font-secondary text-nav-label text-light-txt-muted dark:text-dark-txt-muted">
                        <flux:table.column align="center" class="px-2! md:px-4! py-2">City/Municipality</flux:table.column>
                        <flux:table.column align="center" class="px-2 md:px-4 py-2">Vehicle</flux:table.column>
                        <flux:table.column align="center" class="hidden md:table-cell px-2 md:px-4 py-2">First trip</flux:table.column>
                        <flux:table.column align="center" class="hidden md:table-cell px-2 md:px-4 py-2">Last trip</flux:table.column>
                        <flux:table.column align="center" class="px-2! md:px-4! py-2">Fare</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @forelse ($getRouteList as $route)
                            @php

                                $vehicle = strtolower($route['operator_ticket_rate']['vehicle_type']);
                                $badgeColor = match (true) {
                                    str_contains($vehicle, 'jeep') => 'yellow',
                                    str_contains($vehicle, 'multicab') => 'purple',
                                    str_contains($vehicle, 'aircon') => 'indigo',
                                    str_contains($vehicle, 'uv') => 'green',
                                    str_contains($vehicle, 'bus') => 'blue',
                                    default => 'zinc',
                                };
                            @endphp
                            <flux:table.row>
                                <flux:table.cell align="center" class="px-2 md:px-4 py-1.5 md:py-2">
                                    <x-text class="font-secondary text-xs md:text-table-row font-medium text-light-txt-primary dark:text-dark-txt-primary">
                                        {{ $route['terminal'] }}
                                    </x-text>
                                </flux:table.cell>

                                <flux:table.cell align="center" class="px-2 md:px-4 py-1.5 md:py-2">
                                    <flux:badge color="{{ $badgeColor }}" size="sm" class="font-secondary text-badge text-xs">
                                        {{ $route['operator_ticket_rate']['vehicle_type'] }}
                                    </flux:badge>
                                </flux:table.cell>

                                <flux:table.cell align="center" class="hidden md:table-cell px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                                    {{ $route['metadata']['first_trip'] }}
                                </flux:table.cell>

                                <flux:table.cell align="center" class="hidden md:table-cell px-2 md:px-4 py-1.5 md:py-2 font-secondary text-xs md:text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                                    {{ $route['metadata']['last_trip'] }}
                                </flux:table.cell>

                                <flux:table.cell align="center" class="px-2! md:px-4! py-1.5 md:py-2 font-secondary text-xs md:text-table-row font-semibold text-success dark:text-dark-success">
                                    &#8369; {{ number_format($route['fare'], 2) }}
                                </flux:table.cell>

                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="{{ 5 + ($canSeeQueueFee ? 1 : 0) + ($isAdmin ? 1 : 0) }}" class="px-2 md:px-4 py-4">
                                    <div class="flex flex-col items-center justify-center py-6 md:py-12 gap-2">
                                        <flux:icon.map-pin class="w-6 h-6 md:w-8 md:h-8 text-light-txt-muted dark:text-dark-txt-muted" />
                                        <x-text class="font-secondary text-sm md:text-table-row text-light-txt-muted dark:text-dark-txt-muted">
                                            No routes found.
                                        </x-text>
                                        @if ($search)
                                            <x-text class="font-secondary text-xs md:text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                                                Try a different search term.
                                            </x-text>
                                        @elseif ($vehicleFilter)
                                            <x-text class="font-secondary text-xs md:text-timestamp text-light-txt-muted dark:text-dark-txt-muted">
                                                No routes for that vehicle type yet.
                                            </x-text>
                                        @endif
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>
        </flux:card>
    </div>
</div>