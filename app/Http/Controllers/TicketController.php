<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\Technician;
use App\Models\Replacement;
use App\Models\MachinesSold;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Notifications\NewTicketNotification;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $sortBy = $request->input('sortBy', 'default');
        $direction = $request->input('direction', 'asc');
        $tickets = Ticket::with('technician')->get();
        $columnTitles = [
            ['text' => 'Ticket ID', 'sortBy' => 'id'],
            ['text' => 'Titolo', 'sortBy' => 'title'],
            ['text' => 'Stato', 'sortBy' => 'status'],
            ['text' => 'Priorità', 'sortBy' => 'priority'],
            ['text' => 'Tecnico', 'sortBy' => 'technician'],
            'Modifica',
            'Elimina'
        ];
        
        $searchTerm = $request->input('ticketsSearch');
        
        $routeName = 'dashboard.tickets.index';
        
        $queryBuilder = Ticket::with(['machinesSold', 'machineModel', 'technician']);
        
        // Filtraggio per stato se specificato nella richiesta
        if ($request->has('status')) {
            $status = $request->input('status');
            $queryBuilder->where('status', $status);
        }
        
        // Filtraggio per ticket in attesa di ricambio
        if ($request->has('waiting_for_spare_parts')) {
            $queryBuilder->where('status', 'In attesa di un ricambio');
        }
        
        // Filtraggio per ticket urgenti
        if ($request->has('urgent')) {
            $queryBuilder->where('priority', 'Urgente');
        }
        
        if ($searchTerm) {
            $queryBuilder->where('tickets.title', 'like', '%' . $searchTerm . '%')
            ->orWhere('description', 'LIKE', "%$searchTerm%")
            ->orWhere('closed', 'LIKE', "%$searchTerm%")
            ->orWhere('notes', 'LIKE', "%$searchTerm%")
            ->orWhere('descrizione', 'LIKE', "%$searchTerm%")
            ->orWhere('cd_cf', 'LIKE', "%$searchTerm%")
            ->orWhere('status', 'LIKE', "%$searchTerm%")
            ->orWhere('priority', 'LIKE', "%$searchTerm%")
            ->orWhereHas('technician', function ($query) use ($searchTerm) {
                $query->where('name', 'LIKE', "%$searchTerm%");
            })
            ->orWhereHas('technician', function ($query) use ($searchTerm) {
                $query->where('surname', 'LIKE', "%$searchTerm%");
            })
            ->orWhereHas('machinesSold', function ($query) use ($searchTerm) {
                $query->where('model', 'LIKE', "%$searchTerm%");
            })
            ->orWhereHas('machinesSold', function ($query) use ($searchTerm) {
                $query->where('serial_number', 'LIKE', "%$searchTerm%");
            })
            ->orWhereHas('machineModel', function ($query) use ($searchTerm) {
                $query->where('model', 'LIKE', "%$searchTerm%");
            });
        }
        
        $queryBuilder->when($sortBy == 'id', function ($query) use ($direction) {
            $query->orderBy('tickets.id', $direction);
        })->when($sortBy == 'title', function ($query) use ($direction) {
            $query->orderBy('tickets.title', $direction);
        })->when($sortBy == 'status', function ($query) use ($direction) {
            $query->orderBy('tickets.status', $direction);
        })
        ->when($sortBy == 'priority', function ($query) use ($direction) {
            $query->orderBy('tickets.priority', $direction);
        })->when($sortBy == 'technician', function ($query) use ($direction) {
            $query->join('technicians', 'tickets.technician_id', '=', 'technicians.id')
            ->orderBy('technicians.name', $direction);
        });
        
        $tickets = $queryBuilder->paginate(25)->appends([
            'sortBy' => $sortBy,
            'direction' => $direction,
            'ticketsSearch' => $searchTerm,
        ]);
        
        
        return view('dashboard.tickets.index', [
            'tickets' => $tickets,
            'sortBy' => $sortBy,
            'direction' => $direction,
            'routeName' => $routeName,
            'columnTitles' => $columnTitles,
        ]);
    }
    
    
    public function create()
    {
        $machines = MachinesSold::all();
        $technicians = Technician::all();
        $nextTicketNumber = DB::table('tickets')->max('id') + 1;
        $customers = DB::connection('mssql')
        ->table('cf')
        ->where('Cliente', 1)
        ->where('Obsoleto', 0)
        ->get();  
        
        // Crea una collezione fittizia di clienti
        // $customers = new Collection([
            //     (object) ['id' => 1, 'Descrizione' => 'Cliente A', 'Cd_CF' => 'clienteA@example.com'],
            //     (object) ['id' => 2, 'Descrizione' => 'Cliente B', 'Cd_CF' => 'clienteB@example.com'],
            // ]);
            
            return view('dashboard.tickets.create', compact('machines', 'nextTicketNumber', 'technicians', 'customers'));
        }
        
        public function store(Request $request)
        {
            // Validazione dei dati
            $request->validate([
                'selectedCustomer' => 'required',
                'selectedCdCF' => 'required',
            ]);
            
            // Continua con il tuo codice
            $ticket = new Ticket([
                'title' => $request->input('title'),
                'description' => $request->input('description'),
                'intervention_date' => $request->input('intervention_date'),
                'notes' => $request->input('notes'),
                'machine_model_id' => $request->input('machine_model_id'),
                'machine_sold_id' => $request->input('machine_sold_id'),
                'closed' => $request->input('closed'),
                'status' => $request->input('status'),
                'priority' => $request->input('priority'),
                'descrizione' => trim($request->input('selectedCustomer')),
                'cd_cf' => $request->input('selectedCdCF'),
            ]);
            
            // Salva il ticket nel database
            $ticket->save();
            
            // Associa il tecnico al ticket
            $ticket->technician()->associate($request->input('technician_id'));
            $ticket->save();
            
            $ticket->user()->associate(Auth::user());
            
            $ticket->save();
            
            // Invia la notifica passando l'oggetto del ticket
            \Illuminate\Support\Facades\Notification::route('mail', 'nicola.mazzaferro@coimaf.com')
            ->notify(new NewTicketNotification($ticket));
            
            return redirect()->route('dashboard.tickets.index')->with('success', 'Ticket creato con successo!');
        }
        
        
        
        public function show(Ticket $ticket)
        {
            $replacements = Replacement::where('ticket_id', $ticket->id)->get();
            //     $dataInsertedDefault = DB::connection('mssql')
            //     ->table('DOTes')
            //     ->where('Cd_MGEsercizio', 2024)
            //     ->where('Cd_Do', 'RAP')
            //     ->get();
            
            // dd($dataInserted[35]);
            
            return view("dashboard.tickets.show", compact('ticket', 'replacements'));
        }
        
        
        public function edit(Ticket $ticket, Request $request)
        {
            $machines = MachinesSold::all();
            $technicians = Technician::all();
            $customers = DB::connection('mssql')->table('cf')->get();
            $replacements = Replacement::where('ticket_id', $ticket->id)->get();
            
            $id_LSRevisione = DB::connection('mssql')
            ->table('LSRevisione')
            ->whereNotNull('DataPubblicazione')
            ->where('DataPubblicazione', '>', '1990-01-01')
            ->where('cd_ls', 'LSA0001')
            ->value('Id_LSRevisione');
            
            // dd($id_LSRevisione);
            
            $articles = DB::connection('mssql')
            ->table('AR')
            ->join('LSArticolo', 'AR.Cd_AR', '=', 'LSArticolo.Cd_AR')
            ->where('AR.Obsoleto', 0)
            ->where('LSArticolo.Id_LSRevisione', $id_LSRevisione)
            ->select('AR.Descrizione', 'AR.Cd_AR', 'LSArticolo.Prezzo')
            ->get();
            
            // dd($articles[0]);
            
            
            
            return view('dashboard.tickets.edit', compact('ticket', 'replacements', 'machines', 'technicians', 'customers', 'articles'));
        }
        
        public function update(Request $request, $id)
        {
            
            // Trova il ticket da aggiornare
            $ticket = Ticket::findOrFail($id);
            
            $previousStatus = $ticket->status;
            
            // Aggiorna i campi del ticket con i nuovi valori
            $ticket->title = $request->input('title');
            $ticket->description = $request->input('description');
            $ticket->notes = $request->input('notes');
            $ticket->machine_model_id = $request->input('machine_model_id');
            $ticket->machine_sold_id = $request->input('machine_sold_id');
            $ticket->intervention_date = $request->input('intervention_date');
            $ticket->status = $request->input('status');
            $ticket->priority = $request->input('priority');
            $ticket->descrizione = trim($request->input('selectedCustomer'));
            $ticket->cd_cf = $request->input('selectedCdCF');
            $ticket->pagato = $request->has('pagato') ? 1 : 0;
            
            // Aggiorna l'associazione del tecnico al ticket
            $ticket->technician()->associate($request->input('technician_id'));
            
            $ticket->updated_by = Auth::user()->id;
            
            $ticket->save();
            
            $replacement = new Replacement();
            
            // Aggiorna i campi di Replacement solo se i valori non sono vuoti
            if ($request->filled(['art', 'desc', 'qnt', 'prz', 'tot', 'sconto'])) {
                $replacement->ticket_id = $ticket->id;
                $replacement->art = $request->input('art');
                $replacement->desc = $request->input('desc');
                $replacement->qnt = $request->input('qnt');
                $replacement->prz = str_replace(',', '.', $request->input('prz')); // Converte il prezzo nel formato corretto
                $replacement->sconto = $request->input('sconto');
                $replacement->tot = str_replace(',', '.', $request->input('tot')); // Converte il totale nel formato corretto
                $replacement->save();
                
                return redirect()->route('dashboard.tickets.edit', compact('ticket'))->with('success', 'Ticket aggiornato con successo!');
            } 
            
            //~ Aggiorna lo stato del ticket solo se è stato modificato
            if ($previousStatus !== $ticket->status && $ticket->status === 'Da fatturare' && $ticket->rapportino === null) {
                $this->rapportino($ticket);
                $ticket->status = 'Chiuso';
                $ticket->save();
                
            } elseif($previousStatus !== $ticket->status && $ticket->status === 'Da fatturare' && $ticket->rapportino !== null) {
                // Funzione di update anziche insert
            }
            
            return redirect()->route('dashboard.tickets.show', compact('ticket'))->with('success', 'Ticket aggiornato con successo!');
        }
        
        public function destroyReplacement($id)
        {
            $replacement = Replacement::findOrFail($id);
            $replacement->delete();
            
            return redirect()->back()->with('success', 'Ricambio eliminato con successo');
        }
        
        
        
        public function destroy(Ticket $ticket)
        {
            $ticket->delete();
            
            return redirect()->route('dashboard.tickets.index')->with('success', 'Ticket eliminato con successo!');
        }
        
        public function print(Ticket $ticket) {
            $ticketCdCf = $ticket->cd_cf;
            
            $indirizziStampati = [];
            $indirizziFiltrati = [];
            
            $customers = DB::connection('mssql')
            ->table('cfcontatto')
            ->where('cd_cf', $ticketCdCf)
            ->get();  
            $infoCustomers = DB::connection('mssql')
            ->table('cf4mm')
            ->where('cd_cf', $ticketCdCf)
            ->get();
            
            foreach ($infoCustomers as $info) {
                if (!in_array($info->Indirizzo, $indirizziStampati)) {
                    $indirizziFiltrati[] = $info->Indirizzo;
                    $indirizziStampati[] = $info->Indirizzo;
                }
            }
            $indirizziFiltrati = array_unique($indirizziFiltrati);
            
            return view('components.printTicket', compact('ticket', 'customers', 'infoCustomers', 'indirizziFiltrati'));
        }
        
        private function rapportino(Ticket $ticket)
        {
            // $replacements = Replacement::where('ticket_id', $ticket->id)->get();
            
            /***************************** TESTA DOCUMENTO ******************************/
            
            // Formo la query per cercare l'ultimo numero di documento
            $tipo_doumento = 'RAP';
            
            $tsqlDOTes = DB::connection('mssql')
            ->table('DOTes')
            ->where('Cd_Do', $tipo_doumento)
            ->whereYear('EsAnno', date('Y'))
            ->max('NumeroDoc');
            
            //  dd($tsqlDOTes);
            
            // Cerco i dati del cliente
            $clienteDocumento = $ticket->cd_cf;
            
            // Esegui la query per trovare i dati del cliente
            $rowCF = DB::connection('mssql')
            ->table('CF')
            ->select('*')
            ->where('Cd_CF', $clienteDocumento)
            ->first();
            
            $codicepagamento = $rowCF->Cd_PG;
            
            $bancasconto = $rowCF->Cd_CGConto_Banca;
            
            // dd($codicepagamento);
            
            if ($bancasconto  == '')
            {
                $bancasconto = "NULL";
            }
            else
            {
                $bancasconto = $bancasconto;
            }
            
            $newDocNum = $tsqlDOTes + 1;
            
            // dd($newDocNum);
            
            $replacements = Replacement::where('ticket_id', $ticket->id)->get();
            
            // Verifica se il ticket è chiuso
            if ($ticket->closed === null) {
                // Se il ticket non è chiuso, imposta la data di oggi
                $ticket->closed = now(); // ora corrente
            }
            
            $numero_righe_enable = $replacements->count(); // numero di manutenizioni
            $dataDocumento = "".$ticket->closed;
            $EsercizioYear = date('Y', strtotime($ticket->intervention_date));
            $numero_utente_arca = 104;    //numero utente arca di default
            $nome_utente = 'Default user';
            $accontofissov = 0;//$rowMYSQL7["acconto_fisso"]+0;
            $accontov = 0;//$rowMYSQL7["acconto_fisso"]+0;
            $abbuonov = 0;//$rowMYSQL7["abbuono"]+0;
            $totaleImponibile = 0;
            $totaleImponibileLordo = 0;
            
            $Aliquota = '22.0';
            $Cd_CGConto = '51010101008';
            $trasporto = '01';
            $asp_beni = 'AV';
            $porto = '';
            $spedizione = '';
            $prodotto_default = 'MAN.ASSISTENZA';
            $fattore =1;
            $Id_DORig = 0;
            
            
            if ($ticket->pagato == 1)
            {
                $accontoperc = 100;
            }
            else
            {
                $accontoperc = 0;
            }
            
            
            // Creazione dell'array dei dati da inserire
            $dataToInsert = [
                'Cd_Do' => $tipo_doumento,
                'TipoDocumento' => 'D',
                'DoBitMask' => 128,
                'Cd_CF' => $clienteDocumento,
                'CliFor' => 'C',
                'Cd_CN' => $tipo_doumento,
                'Contabile' => 0,
                'TipoFattura' => 0,
                'ImportiIvati' => 0,
                'IvaSospesa' => 0,
                'Esecutivo' => 1,
                'Prelevabile' => 1,
                'Modificabile' => 1,
                'ModificabilePdf' => 1,
                'NumeroDoc' => $newDocNum,
                'DataDoc' => $dataDocumento,
                'Cd_MGEsercizio' => $EsercizioYear,
                'EsAnno' => $EsercizioYear,
                'Cd_CGConto_Banca' => $bancasconto,
                'Cd_VL' => 'EUR',
                'Decimali' => 2,
                'DecimaliPrzUn' => 3,
                'Cambio' => 1,
                'MagPFlag' => 0,
                'MagAFlag' => 0,
                'Cd_LS_1' => 'LSA0001',
                'Cd_LS_2' => 'LSA0002',
                'Cd_PG' => $codicepagamento,
                'Colli' => 0,
                'PesoLordo' => 0,
                'PesoNetto' => 0,
                'VolumeTotale' => 0,
                'AbbuonoV' => $abbuonov,
                'RigheMerce' => $numero_righe_enable,
                'RigheSpesa' => 0,
                'RigheMerceEvadibili' => $numero_righe_enable,
                'AccontoPerc' => $accontoperc,
                'AccontoFissoV' => $accontofissov,
                'AccontoV' => $accontov,
                'CGCorrispondenzaIvaMerce' => 1,
                'UserIns' => $numero_utente_arca,
                'UserUpd' => $numero_utente_arca,
                'IvaSplit' => 0,
                'NotePiede' => null,
                'Cd_DoTrasporto' => $trasporto,
                'Cd_DoAspBene' => $asp_beni,
                'Cd_DoSottoCommessa' => 'ASSISTENZA',
                'NumeroDocRif' => 'TT-' . $ticket->id,
                'DataDocRif' => $ticket->intervention_date . 'T00:00:00',
            ];
            
            // Esecuzione dell'inserimento dei dati utilizzando il query builder
            DB::connection('mssql')->table('DOTes')->insert($dataToInsert);
            
            // Salva il nuovo numero documento nella colonna rapportino del ticket
            $ticket->rapportino = $newDocNum;
            
            $ticket->save();
            
            /***************************** ROW DOCUMENTO ******************************/
            /*********************** RIGA DI DEFAULT DI MANODOPERA *********************************/
            
            // Recupero dati da inserire
            $dataInsertedDefault = DB::connection('mssql')
            ->table('DOTes')
            ->where('Cd_MGEsercizio', $EsercizioYear)
            ->where('Cd_Do', 'RAP')
            ->where('NumeroDoc', $newDocNum)
            ->first();
            
            $Id_DOTes = $dataInsertedDefault->Id_DoTes;
            $dataToInsertDefaults = [];
            
            $cd_ar_values = $replacements->pluck('art'); // Ottieni tutti i valori dell'attributo 'art'
            
            $datiReplacements = DB::connection('mssql')
            ->table('AR')
            ->whereIn('Cd_AR', $cd_ar_values) // Utilizza 'whereIn' per confrontare con un array di valori
            ->get();
            
            
            // dd($datiReplacements);

            $i = 0;
            
            foreach ($replacements as $key => $replacement) {
                $datiReplacements = DB::connection('mssql')
                    ->table('AR')
                    ->where('Cd_AR', $replacement->art)
                    ->first();
                
                // Ottenere dati specifici dell'articolo sostituto
                $contoArticolo = $datiReplacements->Cd_CGConto_VI;
                $nota = $datiReplacements->Note_AR;
                $um  = $datiReplacements->Cd_ARMisura;
                $Cd_aliquota = $datiReplacements->Cd_Aliquota_A;
                $Cd_aliquota_V = $datiReplacements->Cd_Aliquota_V;
                
                // Altri dati da inserire
                $Id_LSArticolo = null;
                $descrizione = $replacement->desc;
                $cd_ar = $replacement->art;
                $qta = $replacement->qnt;
                $prezzoUnitario = $replacement->prz;
                $prezzo = $prezzoUnitario;
                
                // Creazione dell'array dei dati da inserire per un singolo articolo
                $dataToInsertDefault = [
                    'ID_DOTes' => $Id_DOTes,
                    'Contabile' => 0,
                    'NumeroDoc' => $newDocNum,
                    'DataDoc' => $dataDocumento,
                    'Cd_MGEsercizio' => $EsercizioYear,
                    'Cd_DO' => 'RAP',
                    'TipoDocumento' => 'D',
                    'Cd_CF' => $clienteDocumento,
                    'Cd_VL' => 'EUR',
                    'Cambio' => 1,
                    'Decimali' => 2,
                    'DecimaliPrzUn' => 3,
                    'Riga' => $i += 1,
                    'Cd_MGCausale' => 'DDT',
                    'Cd_MG_P' => 'MP',
                    'Cd_AR' => $cd_ar,
                    'Descrizione' => $descrizione,
                    'Cd_ARMisura' => $um,
                    'Cd_CGConto' => $contoArticolo,
                    'Cd_Aliquota' => $Cd_aliquota,
                    'Cd_Aliquota_R' => $Cd_aliquota_V,
                    'Qta' => $qta,
                    'FattoreToUM1' => $fattore,
                    'QtaEvadibile' => $qta,
                    'QtaEvasa' => 0,
                    'PrezzoUnitarioV' => $prezzo,
                    'PrezzoTotaleV' => round($replacement->tot, 2),
                    'PrezzoTotaleMovE' => round($replacement->tot, 2),
                    'Omaggio' => 1,
                    'Evasa' => 0,
                    'Evadibile' => 1,
                    'Esecutivo' => 1,
                    'FattoreScontoRiga' => 0,
                    'FattoreScontoTotale' => 0,
                    'Id_LSArticolo' => null,
                    'UserIns' => $numero_utente_arca,
                    'UserUpd' => $numero_utente_arca,
                    'NoteRiga' => $nota,
                    'ScontoRiga' => $replacement->sconto,
                ];
                
                //! Disabilita il trigger
                DB::connection('mssql')->statement('DISABLE TRIGGER dbo.DORig_atrg_brd ON dbo.DORig');
                
                //& Salva gli articoli in DORig
                DB::connection('mssql')->table('DORig')->insert($dataToInsertDefault);
                
                //* Riabilita il trigger
                DB::connection('mssql')->statement('ENABLE TRIGGER dbo.DORig_atrg_brd ON dbo.DORig');
                
                // Ottieni l'Id_DORig appena inserito
                $row = DB::connection('mssql')
                    ->table('DORig')
                    ->select('Id_DORig')
                    ->where('ID_DOTes', $Id_DOTes)
                    ->where('Riga', $i)
                    ->first();
                
                // Ottieni il flag AR_fittizio
                $rowAR = DB::connection('mssql')
                    ->table('AR')
                    ->select('Fittizio')
                    ->where('Cd_AR', $cd_ar)
                    ->first();
                
                $AR_fittizio = $rowAR ? $rowAR->Fittizio : null;
                
                $Id_DORig = $row->Id_DORig;
                
                $tsqlMGMovINSERT = [
                    'DataMov' => $dataDocumento,
                    'Id_DoRig' => $Id_DORig,
                    'Cd_MGEsercizio' => $EsercizioYear,
                    'Cd_AR' => $cd_ar,
                    'Cd_MG' => 'MP',
                    'Id_MGMovDes' => 18,
                    'PartenzaArrivo' => 'P',
                    'PadreComponente' => 'P',
                    'EsplosioneDB' => 0,
                    'Quantita' => $qta * $fattore,
                    'Valore' => round($prezzo, 2),
                    'Cd_MGCausale' => 'DDT',
                    'Ini' => 0,
                    'Ret' => 0,
                    'CarA' => 0,
                    'CarP' => 0,
                    'CarT' => 0,
                    'ScaV' => 1,
                    'ScaP' => 0,
                    'ScaT' => 0,
                ];
                
                //! Disabilita il trigger
                DB::connection('mssql')->statement('DISABLE TRIGGER dbo.MGMov_atrg ON dbo.MGMov');
                
                //& Salva i movimenti in MGMov
                if ($AR_fittizio == 0 && $Id_DORig != 0) {
                    DB::connection('mssql')->table('MGMov')->insert($tsqlMGMovINSERT);
                }
                
                //* Riabilita il trigger
                DB::connection('mssql')->statement('ENABLE TRIGGER dbo.MGMov_atrg ON dbo.MGMov');
                
                // Aggiorna i totali
                $totaleImponibile += (float)$replacement->tot;
                $totaleImponibileLordo += (float)$replacement->tot;
                // Recupero il totale
                $totaleImposta = ($totaleImponibile * 0.22);
                $totaleDocumento = $totaleImposta + $totaleImponibile;
            } //! fine for
            
            
            
            
            /***************** TOTALI DOCUMENTO **************/
            
            if ($ticket->pagato == 1)
            {
                $acconto_tot = $totaleDocumento;
                $netto_pagare = 0;
            }
            else
            {
                $acconto_tot = 0;
                $netto_pagare = $totaleDocumento;
            }
            
            $tsqlDOTotaliINSERT = [
                'Id_DoTes' => $Id_DOTes,
                'Cambio' => 1,
                'AbbuonoV' => 0,
                'AccontoV' => round($acconto_tot, 2),
                'AccontoE' => round($acconto_tot, 2),
                'TotImponibileV' => round($totaleImponibile, 2),
                'TotImponibileE' => round($totaleImponibile, 2),
                'TotImpostaV' => round($totaleImposta, 2),
                'TotImpostaE' => round($totaleImposta, 2),
                'TotDocumentoV' => round($totaleDocumento, 2),
                'TotDocumentoE' => round($totaleDocumento, 2),
                'TotMerceLordoV' => round($totaleImponibileLordo, 2),
                'TotMerceNettoV' => round($totaleImponibile, 2),
                'TotEsenteV' => 0,
                'TotSpese_TV' => 0,
                'TotSpese_NV' => 0,
                'TotSpese_MV' => 0,
                'TotSpese_BV' => 0,
                'TotSpese_AV' => 0,
                'TotSpese_VV' => 0,
                'TotSpese_ZV' => 0,
                'Totspese_RV' => 0,
                'TotScontoV' => 0,
                'TotOmaggio_MV' => 0,
                'TotOmaggio_IV' => 0,
                'TotaPagareV' => round($netto_pagare, 2),
                'TotaPagareE' => round($netto_pagare, 2),
                'TotProvvigione_1V' => 0,
                'TotProvvigione_2V' => 0,
                'RA_ImportoV' => 0,
                'TotImpostaRCV' => 0,
                'TotImpostaSPV' => 0,
            ];
            
            DB::connection('mssql')->table('DOTotali')->insert($tsqlDOTotaliINSERT);
            
            /***************** IVA DOCUMENTO **************/
            
            // Ultimo numero di documento
            $massimo = DB::connection('mssql')->table('DOIva')->max('Id_DOIva');

            $totali = DB::connection('mssql')->table('DOTotali')->where('ID_DOTes', $Id_DOTes)->get();

            // dd($totali);
            
            $newIvaDocNum = $massimo + 1;
            
            DB::connection('mssql')->table('DOIva')->insert([
                'Id_DOTes' => $Id_DOTes,
                'Cd_Aliquota' => $Cd_aliquota_V,
                'Aliquota' => $Aliquota,
                'Cambio' => '1.000000',
                'ImponibileV' => round($totaleImponibile, 2),
                'ImpostaV' => round($totaleImposta, 2),
                'Omaggio' => '1',
                'Cd_CGConto' => $contoArticolo,
                'Cd_DOSottoCommessa' => 'ASSISTENZA'
            ]);
            
        } // Fine Rapportino
        
        
        
        
    }
    