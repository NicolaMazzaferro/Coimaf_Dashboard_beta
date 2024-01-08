<?php

namespace App\Http\Controllers;

use Throwable;
use Carbon\Carbon;
use App\Models\Role;
use App\Models\Document;
use App\Models\Employee;
use Illuminate\Http\Request;
use App\Models\DocumentEmployee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class EmployeeController extends Controller
{
    
    public function index(Request $request)
    {
        // Ottieni i parametri di ordinamento e direzione
        $sortBy = $request->input('sortBy', 'default');
        $direction = $request->input('direction', 'asc');
        
        $columnTitles = [
            ['text' => 'Nome', 'sortBy' => 'name'],
            'Codice Fiscale',
            'Ruolo',
            'Documenti',
            'Modifica',
            'Elimina'
        ];
        
        $routeName = 'dashboard.employees.index';
        
        // Costruisci la query di base con le relazioni
        $query = Employee::with(['documents', 'roles']);
        
        
        // Altrimenti, applica l'ordinamento predefinito
        if ($sortBy == 'name') {
            $query->orderBy('employees.name', $direction);
        }
        
        // Esegui la query
        $employees = $query->get();
        
        // Restituisci i dati alla vista
        return view('dashboard.employees.index', [
            'employees' => $employees,
            'sortBy' => $sortBy,
            'direction' => $direction,
            'routeName' => $routeName,
            'columnTitles' => $columnTitles,
        ]);
    }
    
    
    
    
    
    public function create()
    {
        $roles = Role::with('documents')->get();
        $documents = Document::all();
        
        return view('dashboard.employees.create', compact('roles', 'documents'));
    }
    
    public function store(Request $request)
    {
        $request->validate([
                'name' => 'required|string|max:255',
                'surname' => 'required|string|max:255',
                'fiscal_code' => 'required|string|max:255',
                'birthday' => 'required|string|max:255',
                'phone' => 'required|string|max:255',
                'address' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'email_work' => 'email|max:255',
                'roles' => 'array',
                'roles.*' => 'exists:roles,id',
                'documents' => 'array',
                'documents.*.id' => 'exists:documents,id',
                'documents.*.pdf_path' => 'required|string|max:255',
                'documents.*.expiry_date' => 'required|date',
            ]);
            
            // Crea un nuovo Employee
            $employee = Employee::create([
                'name' => $request->input('name'),
                'surname' => $request->input('surname'),
                'fiscal_code' => $request->input('fiscal_code'),
                'birthday' => $request->input('birthday'),
                'phone' => $request->input('phone'),
                'address' => $request->input('address'),
                'email' => $request->input('email'),
                'email_work' => $request->input('email_work'),
            ]);
            
            // Aggiungi ruolo associato
            $roleId = $request->input('role');
            $employee->roles()->sync([$roleId]);
            
            // Aggiungi documenti associati con i dettagli
            $role = Role::with('documents')->find($roleId);
            
            foreach ($request->file('documents', []) as $index => $documentFile) {
                $documentName = $request->input('document_names')[$index] ?? null;
                
                // Cerca il documento corrispondente al nome e al ruolo
                $documentModel = $role->documents->firstWhere('name', $documentName);
                
                if ($documentModel && $documentFile->isValid()) {
                    $pdfPath = $documentFile->store('employee_documents', 'public');
                    $employee->documents()->attach($documentModel->id, [
                        'pdf_path' => $pdfPath,
                        'expiry_date' => $request->input('expiry_dates')[$index],
                    ]);
                }
            }
            
            Auth::user()->employees()->save($employee);
            
            return redirect()->route('dashboard.employees.index')->with('success', 'Dipendente aggiunto con successo!');
        }
        
        
        public function show($id)
        {
            $employee = Employee::with(['documents', 'roles'])->findOrFail($id);
            return view('dashboard.employees.show', compact('employee'));
        }
        
        public function edit(Employee $employee)
        {
            // Carica i ruoli
            $roles = Role::all();
        
            // Recupera tutti i documenti associati al dipendente
            $documents = $employee->documents;
        
            return view('dashboard.employees.edit', compact('employee', 'roles', 'documents'));
        }
        
        
        public function update(Request $request, Employee $employee)
{
    $request->validate([
        'name' => 'required|string|max:255',
        'surname' => 'required|string|max:255',
        'fiscal_code' => 'required|string|max:255',
        'birthday' => 'required|string|max:255',
        'phone' => 'required|string|max:255',
        'address' => 'required|string|max:255',
        'email' => 'required|email|max:255',
        'email_work' => 'email|max:255',
        'documents' => 'array',
        'documents.*.id' => 'exists:documents,id',
        'documents.*.pdf_path' => 'required|string|max:255',
        'documents.*.expiry_date' => 'required|date',
    ]);

    // Aggiorna i dati dell'Employee
    $employee->update([
        'name' => $request->input('name'),
        'surname' => $request->input('surname'),
        'fiscal_code' => $request->input('fiscal_code'),
        'birthday' => $request->input('birthday'),
        'phone' => $request->input('phone'),
        'address' => $request->input('address'),
        'email' => $request->input('email'),
        'email_work' => $request->input('email_work'),
    ]);

    // Aggiorna i documenti associati con i dettagli
    foreach ($request->input('documents', []) as $index => $documentData) {
        $documentModel = Document::find($documentData['id']);

        if ($documentModel) {
            $pdfPath = $request->file("documents.$index.pdf_path")->store('employee_documents', 'public');

            // Aggiorna o crea il collegamento con il documento
            $employee->documents()->updateOrCreate(
                ['document_id' => $documentModel->id],
                [
                    'pdf_path' => $pdfPath,
                    'expiry_date' => $documentData['expiry_date'],
                ]
            );
        }
    }

    return redirect()->route('dashboard.employees.index')->with('success', 'Dipendente aggiornato con successo!');
}

           
          
        public function destroy(Employee $employee)
        {
            $employee->delete();
            
            return redirect()->route('dashboard.employees.index')->with('success', 'Dipendente eliminato con successo!');
        }
    }
    