<x-Layouts.layoutDash>
    
    <div class="container d-flex justify-content-center">
        @if (session('success'))
        <div class="alert alert-success mt-5">
            {{ session('success') }}
        </div>
        @endif
        @if($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif
    </div>
    
    <div class="col-12 col-md-11 d-flex justify-content-end mt-5">
        <a href="{{route('dashboard.tickets.create')}}"><x-Buttons.buttonBlue type="button" props="NUOVO" /></a>
    </div>
    
    <x-table :columnTitles="$columnTitles" :rowData="$tickets">
        <tbody>
            @foreach ($tickets as $ticket)
            <tr class="text-center align-middle">
                <td class="py-4">
                    <a class="link-underline link-underline-opacity-0 link-dark" href="{{route('dashboard.tickets.show', compact('ticket'))}}">
                        {{ $ticket->id }}
                    </a>
                </td>
                <td class="py-4">
                    <a class="link-underline link-underline-opacity-0 link-dark" href="{{route('dashboard.tickets.show', compact('ticket'))}}">
                        {{ $ticket->title }}
                    </a>
                </td>
                <td>
                    <a class="link-underline link-underline-opacity-0 link-dark" href="{{route('dashboard.tickets.show', compact('ticket'))}}">
                        {{ $ticket->status }}
                    </a>
                </td>
                <td>
                    <a class="link-underline link-underline-opacity-0 link-dark" href="{{route('dashboard.tickets.show', compact('ticket'))}}">
                        {{ $ticket->priority }}
                    </a>
                </td>
                <td>
                    <a class="link-underline link-underline-opacity-0 link-dark" href="{{route('dashboard.tickets.show', compact('ticket'))}}">
                        @if ($ticket->technician)
                        {{ $ticket->technician->name }} {{ $ticket->technician->surname }}
                        @else
                        Tecnico non disponibile.
                        @endif
                    </a>
                </td>
                <td>
                    <a href="{{ route('dashboard.tickets.edit', $ticket->id) }}">
                        <i class='bi bi-pencil-square text-warning'></i>
                    </a>                    
                </td>
                <td>
                    <button type="button" class="btn bi bi-trash3-fill text-danger" data-bs-toggle="modal" data-bs-target="#deleteTicketModal{{ $ticket->id }}"></button>
                    
                    <form action="{{route('dashboard.tickets.delete', compact('ticket'))}}" method="post">
                        @csrf
                        @method('delete')
                        <!-- Modal -->
                        <div class="modal fade" id="deleteTicketModal{{ $ticket->id }}" tabindex="-1" aria-labelledby="deleteTicketModalLabel{{ $ticket->id }}" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title text-black" id="deleteTicketModalLabel{{ $ticket->id }}">Conferma eliminazione dipendente</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body text-black" id="ticketInfoContainer{{ $ticket->id }}">
                                        Sicuro di voler eliminare <b>{{ $ticket->title }} </b>? <br>L'azione sarà irreversibile.
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annulla</button>
                                        <form action="{{route('dashboard.tickets.delete', compact('ticket'))}}" method="post">
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
        
        
    </x-Layouts.layoutDash>