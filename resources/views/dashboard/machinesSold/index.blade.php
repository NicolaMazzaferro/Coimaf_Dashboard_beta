<x-Layouts.layoutDash>

    <x-allert />
    
    <div class="col-12 col-md-11 d-flex justify-content-end w-100">
        <a href="{{route('dashboard.machinesSolds.create')}}"><x-Buttons.buttonBlue type="button" props="NUOVO" /></a>
    </div>

      <x-table :columnTitles="$columnTitles" :rowData="$machines" :direction="$direction" :sortBy="$sortBy" :routeName="$routeName">
        <tr class="align-middle">
            <th colspan="{{ count($columnTitles) }}">
                <form class="d-flex" action="{{ route('dashboard.machinesSolds.index') }}" method="GET">
                    <input type="search" class="form-control me-2" placeholder="Cerca" name="machinesSearch" value="{{ request('query') }}">
                    <x-Buttons.buttonBlue type='submit' props='Cerca' />
                </form>
            </th>
        </tr>
        <tbody>
            @foreach ($machines as $machine)
            <tr class="align-middle">
                <td class="ps-3">
                    <a class="link-underline link-underline-opacity-0 link-dark" href="{{route('dashboard.machinesSolds.show', compact('machine'))}}">
                        {{ $machine->model }}
                    </a>
                </td>
                <td>
                    <a class="link-underline link-underline-opacity-0 link-dark" href="{{route('dashboard.machinesSolds.show', compact('machine'))}}">
                        {{ $machine->brand }}
                    </a>
                </td>
                <td>
                    <a class="link-underline link-underline-opacity-0 link-dark" href="{{route('dashboard.machinesSolds.show', compact('machine'))}}">
                        {{ $machine->buyer }}
                    </a>
                </td>
                <td class="ps-3">
                    <a class="link-underline link-underline-opacity-0 link-dark" href="{{route('dashboard.machinesSolds.show', compact('machine'))}}">
                        {{ $machine->warrantyType->name }}
                    </a>
                </td>
                <td class="ps-3">
                    @if($machine->sale_date)
                        <a class="link-underline link-underline-opacity-0 link-dark" href="{{ route('dashboard.machinesSolds.show', compact('machine')) }}">
                            {{ \Carbon\Carbon::parse($machine->sale_date)->format('d-m-Y') }}
                        </a>
                    @endif
                </td>
                
                <td class="ps-4">
                    <a href="{{ route('dashboard.machinesSolds.edit', $machine->id) }}">
                        <i class='bi bi-pencil-square text-warning'></i>
                    </a>                    
                </td>
                <td class="ps-4">
                    <button type="button" class="btn bi bi-trash3-fill text-danger" data-bs-toggle="modal" data-bs-target="#deleteMachineModal{{ $machine->id }}"></button>
                    
                    <form action="{{route('dashboard.machinesSolds.destroy', compact('machine'))}}" method="post">
                        @csrf
                        @method('delete')
                        <!-- Modal -->
                        <div class="modal fade" id="deleteMachineModal{{ $machine->id }}" tabindex="-1" aria-labelledby="deleteMachineModalLabel{{ $machine->id }}" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title text-black" id="deleteMachineModalLabel{{ $machine->id }}">Conferma eliminazione dipendente</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body text-black" id="machineInfoContainer{{ $machine->id }}">
                                        Sicuro di voler eliminare <b>{{ $machine->model }} {{ $machine->brand }}</b>? <br>L'azione sarà irreversibile.
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                                        <form action="#" method="post">
                                            @csrf
                                            @method('delete')
                                            <button type="submit" class="btn btn-danger">Elimina</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </td>
            </tr>
            @endforeach
        </tbody>
        </x-table>

        <x-pagination :props="$machines" />

</x-Layouts.layoutDash>
    