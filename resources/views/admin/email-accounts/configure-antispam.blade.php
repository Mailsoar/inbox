@extends('layouts.admin')

@section('title', 'Configuration Anti-spam - ' . $emailAccount->email)

@section('content')
<div class="container-fluid">
    {{-- Breadcrumbs --}}
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.email-accounts.index') }}">Comptes Email</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.email-accounts.edit', $emailAccount) }}">{{ $emailAccount->email }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">Configuration Anti-spam</li>
        </ol>
    </nav>

    {{-- Page Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Configuration de la détection anti-spam</h1>
    </div>

    {{-- Progress indicator --}}
    <div class="progress mb-4" style="height: 30px;">
        <div class="progress-bar bg-success" role="progressbar" style="width: 33%;">
            <span class="h6 mb-0">1. Compte créé</span>
        </div>
        <div class="progress-bar bg-success" role="progressbar" style="width: 33%;">
            <span class="h6 mb-0">2. Compte activé</span>
        </div>
        <div class="progress-bar bg-primary progress-bar-striped progress-bar-animated" role="progressbar" style="width: 34%;">
            <span class="h6 mb-0">3. Configuration anti-spam</span>
        </div>
    </div>


    <div class="row">
        <div class="col-lg-8">
            {{-- Step 1: Folders detection --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0"><i class="fas fa-folder"></i> Étape 1 : Sélection du dossier à analyser</h5>
                </div>
                <div class="card-body">
                    @if(!empty($detectionData['folders']))
                        <p class="text-muted">Sélectionnez le dossier contenant des emails filtrés (spam/junk) pour une meilleure détection :</p>
                        
                        <div class="row">
                            <div class="col-md-8">
                                <select id="folderSelect" class="form-select">
                                    <option value="">-- Sélectionnez un dossier --</option>
                                    @php
                                        // Trier pour mettre les dossiers spam en premier
                                        $sortedFolders = collect($detectionData['folders'])->sort(function($a, $b) {
                                            $spamKeywords = ['spam', 'junk', 'bulk', 'indésirable'];
                                            $aIsSpam = Str::contains(strtolower($a), $spamKeywords);
                                            $bIsSpam = Str::contains(strtolower($b), $spamKeywords);
                                            
                                            if ($aIsSpam && !$bIsSpam) return -1;
                                            if (!$aIsSpam && $bIsSpam) return 1;
                                            return strcasecmp($a, $b);
                                        });
                                    @endphp
                                    @foreach($sortedFolders as $folder)
                                        <option value="{{ $folder }}" 
                                            @if(Str::contains(strtolower($folder), ['spam', 'junk', 'bulk'])) 
                                                class="fw-bold text-warning" 
                                            @endif>
                                            {{ $folder }}
                                            @if(Str::contains(strtolower($folder), ['spam', 'junk', 'bulk']))
                                                ⚠️ (Recommandé)
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button type="button" class="btn btn-primary w-100" id="analyzeBtn" disabled>
                                    <i class="fas fa-search"></i> Analyser ce dossier
                                </button>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <details>
                                <summary class="cursor-pointer text-secondary">Voir tous les dossiers</summary>
                                <div class="bg-light p-3 rounded mt-2" style="max-height: 200px; overflow-y: auto;">
                                    <code>
                                        @foreach($detectionData['folders'] as $folder)
                                            {{ $folder }}<br>
                                        @endforeach
                                    </code>
                                </div>
                            </details>
                        </div>
                    @else
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> Aucun dossier trouvé. Vérifiez la connexion au compte.
                        </div>
                    @endif
                </div>
            </div>

            {{-- Step 2: Email analysis results --}}
            <div class="card shadow-sm mb-4" id="analysisResults" style="display: none;">
                <div class="card-header bg-info text-white">
                    <h5 class="card-title mb-0"><i class="fas fa-envelope-open-text"></i> Étape 2 : Résultats de l'analyse</h5>
                </div>
                <div class="card-body">
                    <div id="analysisContent">
                        {{-- Contenu chargé via AJAX --}}
                    </div>
                </div>
            </div>

            {{-- Step 3: Configuration form --}}
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="card-title mb-0">Étape 3 : Configuration finale</h5>
                </div>
                <div class="card-body">
                    <div id="detectionAlert"></div>

                    <form action="{{ route('admin.email-accounts.save-antispam', $emailAccount) }}" method="POST" 
                        id="antispamForm" onsubmit="handleFormSubmit(event)">
                        @csrf
                        
                        {{-- Anti-spam Systems --}}
                        <div class="mb-4">
                            <label class="form-label">Systèmes anti-spam détectés</label>
                            <p class="text-muted small">Sélectionnez tous les systèmes anti-spam utilisés par ce compte email</p>
                            
                            @php
                                $antispamSystems = \App\Models\AntispamSystem::active()->orderBy('display_name')->get();
                            @endphp
                            
                            <div class="row">
                                @foreach($antispamSystems as $system)
                                    <div class="col-md-6 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input antispam-checkbox" type="checkbox" 
                                                name="antispam_systems[]" 
                                                value="{{ $system->id }}" 
                                                id="antispam_{{ $system->id }}"
                                                data-system-name="{{ $system->name }}"
                                                @if(in_array($system->id, $detectionData['existing_antispam'] ?? [])) checked @endif>
                                            <label class="form-check-label" for="antispam_{{ $system->id }}">
                                                {{ $system->display_name }}
                                                @if($system->is_custom)
                                                    <span class="badge bg-info ms-1">Personnalisé</span>
                                                @endif
                                            </label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            
                            
                            <div class="mt-2">
                                <a href="{{ route('admin.antispam-systems.create') }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-plus"></i> Créer un nouveau système
                                </a>
                            </div>
                        </div>

                        {{-- Folder Mapping --}}
                        <div class="mb-4">
                            <h6>Mapping des dossiers</h6>
                            <p class="text-muted small">Indiquez les noms exacts des dossiers dans ce compte email</p>
                            
                            {{-- Main folders --}}
                            <div id="mainFolders">
                                {{-- Inbox folder --}}
                                <div class="mb-3">
                                    <label for="folder_inbox" class="form-label">Dossier Boîte de réception <span class="text-danger">*</span></label>
                                    @php
                                        $existingInbox = $detectionData['existing_mappings']->where('folder_type', 'inbox')->first();
                                    @endphp
                                    <select name="folder_mappings[0][folder_name]" id="folder_inbox" class="form-select" required>
                                        <option value="">-- Sélectionnez --</option>
                                        @foreach($detectionData['folders'] ?? [] as $folder)
                                            <option value="{{ $folder }}" 
                                                @if($existingInbox && $existingInbox->folder_name == $folder) selected
                                                @elseif(!$existingInbox && $emailAccount->provider === 'gmail' && $folder === 'INBOX') selected
                                                @elseif(!$existingInbox && $emailAccount->provider !== 'gmail' && isset($detectionData['suggested_mapping'][0]) && $detectionData['suggested_mapping'][0]['folder_name'] == $folder) selected 
                                                @endif>{{ $folder }}</option>
                                        @endforeach
                                    </select>
                                    <input type="hidden" name="folder_mappings[0][folder_type]" value="inbox">
                                    <input type="hidden" name="folder_mappings[0][display_name]" value="Boîte de réception">
                                </div>

                                {{-- Spam folder --}}
                                <div class="mb-3">
                                    <label for="folder_spam" class="form-label">Dossier Spam/Indésirables <span class="text-danger">*</span></label>
                                    @php
                                        $existingSpam = $detectionData['existing_mappings']->where('folder_type', 'spam')->first();
                                    @endphp
                                    <select name="folder_mappings[1][folder_name]" id="folder_spam" class="form-select" required>
                                        <option value="">-- Sélectionnez --</option>
                                        @foreach($detectionData['folders'] ?? [] as $folder)
                                            <option value="{{ $folder }}" 
                                                @if($existingSpam && $existingSpam->folder_name == $folder) selected
                                                @elseif(!$existingSpam && $emailAccount->provider === 'gmail' && $folder === '[Gmail]/Spam') selected
                                                @elseif(!$existingSpam && $emailAccount->provider !== 'gmail' && isset($detectionData['suggested_mapping'][1]) && $detectionData['suggested_mapping'][1]['folder_name'] == $folder) selected 
                                                @endif>{{ $folder }}</option>
                                        @endforeach
                                    </select>
                                    <input type="hidden" name="folder_mappings[1][folder_type]" value="spam">
                                    <input type="hidden" name="folder_mappings[1][display_name]" value="Spam/Indésirables">
                                </div>
                            </div>
                            
                            {{-- Additional inboxes --}}
                            <div class="mt-4">
                                <h6>Dossiers supplémentaires personnalisés</h6>
                                <p class="text-muted small">Ajoutez d'autres dossiers qui doivent être traités comme des boîtes de réception (ex: Promotions, Social, Forums pour Gmail)</p>
                                
                                <div id="additionalInboxes">
                                    @php
                                        $existingAdditional = $detectionData['existing_mappings']->where('folder_type', 'additional_inbox');
                                    @endphp
                                    @if($existingAdditional->count() > 0)
                                        {{-- Show existing additional inbox mappings --}}
                                        @foreach($existingAdditional as $index => $mapping)
                                            <div class="additional-folder-item mb-2">
                                                <div class="row">
                                                    <div class="col-md-5">
                                                        <label class="form-label">Dossier</label>
                                                        <select name="folder_mappings[{{ $index + 2 }}][folder_name]" class="form-select" required>
                                                            @foreach($detectionData['folders'] ?? [] as $folder)
                                                                <option value="{{ $folder }}" @if($mapping->folder_name == $folder) selected @endif>{{ $folder }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="col-md-5">
                                                        <label class="form-label">Nom d'affichage</label>
                                                        <input type="text" name="folder_mappings[{{ $index + 2 }}][display_name]" class="form-control" value="{{ $mapping->display_name }}" required>
                                                    </div>
                                                    <div class="col-md-2 d-flex align-items-end">
                                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeAdditionalInbox(this)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                                <input type="hidden" name="folder_mappings[{{ $index + 2 }}][folder_type]" value="additional_inbox">
                                                <input type="hidden" name="folder_mappings[{{ $index + 2 }}][is_additional_inbox]" value="1">
                                            </div>
                                        @endforeach
                                    @elseif($emailAccount->provider === 'gmail')
                                        {{-- Gmail categories pré-remplies --}}
                                        <div class="additional-folder-item mb-2">
                                            <div class="row">
                                                <div class="col-md-5">
                                                    <label class="form-label">Dossier <span class="badge bg-success ms-1">recommandé</span></label>
                                                    <select name="folder_mappings[2][folder_name]" class="form-select" required>
                                                        <option value="INBOX (Promotions)" selected>INBOX (Promotions)</option>
                                                        @foreach($detectionData['folders'] ?? [] as $folder)
                                                            <option value="{{ $folder }}">{{ $folder }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-5">
                                                    <label class="form-label">Nom d'affichage</label>
                                                    <input type="text" name="folder_mappings[2][display_name]" class="form-control" value="Promotions" required>
                                                </div>
                                                <div class="col-md-2 d-flex align-items-end">
                                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeAdditionalInbox(this)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <input type="hidden" name="folder_mappings[2][folder_type]" value="additional_inbox">
                                            <input type="hidden" name="folder_mappings[2][is_additional_inbox]" value="1">
                                        </div>
                                        
                                        <div class="additional-folder-item mb-2">
                                            <div class="row">
                                                <div class="col-md-5">
                                                    <label class="form-label">Dossier <span class="badge bg-success ms-1">recommandé</span></label>
                                                    <select name="folder_mappings[3][folder_name]" class="form-select" required>
                                                        <option value="INBOX (Social)" selected>INBOX (Social)</option>
                                                        @foreach($detectionData['folders'] ?? [] as $folder)
                                                            <option value="{{ $folder }}">{{ $folder }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-5">
                                                    <label class="form-label">Nom d'affichage</label>
                                                    <input type="text" name="folder_mappings[3][display_name]" class="form-control" value="Social" required>
                                                </div>
                                                <div class="col-md-2 d-flex align-items-end">
                                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeAdditionalInbox(this)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <input type="hidden" name="folder_mappings[3][folder_type]" value="additional_inbox">
                                            <input type="hidden" name="folder_mappings[3][is_additional_inbox]" value="1">
                                        </div>
                                        
                                        <div class="additional-folder-item mb-2">
                                            <div class="row">
                                                <div class="col-md-5">
                                                    <label class="form-label">Dossier <span class="badge bg-success ms-1">recommandé</span></label>
                                                    <select name="folder_mappings[4][folder_name]" class="form-select" required>
                                                        <option value="INBOX (Updates)" selected>INBOX (Updates)</option>
                                                        @foreach($detectionData['folders'] ?? [] as $folder)
                                                            <option value="{{ $folder }}">{{ $folder }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-5">
                                                    <label class="form-label">Nom d'affichage</label>
                                                    <input type="text" name="folder_mappings[4][display_name]" class="form-control" value="Updates" required>
                                                </div>
                                                <div class="col-md-2 d-flex align-items-end">
                                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeAdditionalInbox(this)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <input type="hidden" name="folder_mappings[4][folder_type]" value="additional_inbox">
                                            <input type="hidden" name="folder_mappings[4][is_additional_inbox]" value="1">
                                        </div>
                                        
                                        <div class="additional-folder-item mb-2">
                                            <div class="row">
                                                <div class="col-md-5">
                                                    <label class="form-label">Dossier <span class="badge bg-success ms-1">recommandé</span></label>
                                                    <select name="folder_mappings[5][folder_name]" class="form-select" required>
                                                        <option value="INBOX (Forums)" selected>INBOX (Forums)</option>
                                                        @foreach($detectionData['folders'] ?? [] as $folder)
                                                            <option value="{{ $folder }}">{{ $folder }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-5">
                                                    <label class="form-label">Nom d'affichage</label>
                                                    <input type="text" name="folder_mappings[5][display_name]" class="form-control" value="Forums" required>
                                                </div>
                                                <div class="col-md-2 d-flex align-items-end">
                                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeAdditionalInbox(this)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <input type="hidden" name="folder_mappings[5][folder_type]" value="additional_inbox">
                                            <input type="hidden" name="folder_mappings[5][is_additional_inbox]" value="1">
                                        </div>
                                    @endif
                                    {{-- Dynamic content will be added here --}}
                                </div>
                                
                                <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="addAdditionalInbox()">
                                    <i class="fas fa-plus"></i> Ajouter un dossier inbox
                                </button>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary" id="submitBtn">
                                <i class="fas fa-save"></i> Enregistrer et terminer
                            </button>
                            <a href="{{ route('admin.email-accounts.edit', $emailAccount) }}" class="btn btn-outline-secondary">
                                Retour
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Side panel --}}
        <div class="col-lg-4">
            {{-- Account info --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Informations du compte</h5>
                </div>
                <div class="card-body">
                    <dl class="mb-0">
                        <dt>Email</dt>
                        <dd>{{ $emailAccount->email }}</dd>
                        
                        <dt>Provider</dt>
                        <dd>
                            <span class="badge 
                                @if($emailAccount->provider === 'gmail') bg-danger
                                @elseif($emailAccount->provider === 'outlook') bg-primary
                                @elseif($emailAccount->provider === 'yahoo') bg-purple
                                @else bg-secondary
                                @endif">
                                {{ $emailAccount->getProviderDisplayName() }}
                            </span>
                        </dd>
                        
                        <dt>Statut</dt>
                        <dd>
                            <span class="badge bg-success">Actif</span>
                        </dd>
                    </dl>
                </div>
            </div>

            {{-- Help --}}
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="card-title mb-0">Aide</h5>
                </div>
                <div class="card-body">
                    <h6>Processus de configuration :</h6>
                    <ol class="small text-muted">
                        <li>Sélectionnez un dossier contenant des emails filtrés (spam/junk)</li>
                        <li>Analysez les en-têtes pour détecter le système anti-spam</li>
                        <li>Configurez le mapping des dossiers</li>
                        <li>Enregistrez la configuration</li>
                    </ol>
                    
                    <h6 class="mt-3">Pourquoi analyser le spam ?</h6>
                    <p class="small text-muted">
                        Les emails dans le dossier spam contiennent généralement plus d'en-têtes 
                        de filtrage qui permettent d'identifier le système anti-spam utilisé.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Custom styles --}}
<style>
.bg-purple {
    background-color: #6f42c1 !important;
}
.cursor-pointer {
    cursor: pointer;
}
details summary:hover {
    text-decoration: underline;
}
.loading-spinner {
    display: inline-block;
    width: 1rem;
    height: 1rem;
    border: 2px solid #f3f3f3;
    border-top: 2px solid #3498db;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-right: 0.5rem;
}
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
.analyzing {
    position: relative;
    overflow: hidden;
}
.analyzing::after {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
    animation: shimmer 2s infinite;
}
@keyframes shimmer {
    0% { left: -100%; }
    100% { left: 100%; }
}
</style>

{{-- JavaScript --}}
<script>
// Track folder mapping index
// Calculate based on existing mappings
let folderMappingIndex = {{ 2 + $detectionData['existing_mappings']->where('folder_type', 'additional_inbox')->count() }};

// Enable analyze button when folder is selected
document.getElementById('folderSelect').addEventListener('change', function() {
    document.getElementById('analyzeBtn').disabled = !this.value;
});

// Handle antispam checkbox changes
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.antispam-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updatePrimaryAntispamSelect);
    });
});

function updatePrimaryAntispamSelect() {
    // Function no longer needed - keeping empty for compatibility
}

// Add additional inbox folder
function addAdditionalInbox() {
    const container = document.getElementById('additionalInboxes');
    const folders = @json($detectionData['folders'] ?? []);
    
    const newItem = document.createElement('div');
    newItem.className = 'additional-inbox-item mb-3 p-3 border rounded';
    newItem.innerHTML = `
        <div class="row">
            <div class="col-md-5">
                <label class="form-label">Dossier</label>
                <select name="folder_mappings[${folderMappingIndex}][folder_name]" class="form-select" required>
                    <option value="">-- Sélectionnez --</option>
                    ${folders.map(folder => `<option value="${folder}">${folder}</option>`).join('')}
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label">Nom d'affichage</label>
                <input type="text" name="folder_mappings[${folderMappingIndex}][display_name]" 
                    class="form-control" placeholder="Ex: Promotions" required>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeAdditionalInbox(this)">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
        <input type="hidden" name="folder_mappings[${folderMappingIndex}][folder_type]" value="additional_inbox">
        <input type="hidden" name="folder_mappings[${folderMappingIndex}][is_additional_inbox]" value="1">
    `;
    
    container.appendChild(newItem);
    folderMappingIndex++;
}

// Remove additional inbox
function removeAdditionalInbox(button) {
    button.closest('.additional-inbox-item').remove();
}

// Analyze folder
document.getElementById('analyzeBtn').addEventListener('click', function() {
    const folder = document.getElementById('folderSelect').value;
    if (!folder) return;
    
    const btn = this;
    const originalHtml = btn.innerHTML;
    const originalClasses = btn.className;
    btn.disabled = true;
    btn.classList.add('analyzing');
    
    // Start timer
    let startTime = Date.now();
    let timerInterval = setInterval(() => {
        const elapsed = Math.floor((Date.now() - startTime) / 1000);
        const minutes = Math.floor(elapsed / 60);
        const seconds = elapsed % 60;
        const timeStr = minutes > 0 ? `${minutes}m ${seconds}s` : `${seconds}s`;
        btn.innerHTML = `<span class="loading-spinner"></span> Analyse en cours... (${timeStr})`;
    }, 1000);
    
    // Initial display
    btn.innerHTML = '<span class="loading-spinner"></span> Analyse en cours... (0s)';
    
    // Clear previous results
    document.getElementById('analysisResults').style.display = 'none';
    document.getElementById('analysisContent').innerHTML = '';
    document.getElementById('detectionAlert').innerHTML = '';
    
    // Make AJAX request
    fetch('{{ route("admin.email-accounts.analyze-folder", $emailAccount) }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ folder: folder })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data); // Debug
        
        if (data.success) {
            displayAnalysisResults(data.data);
            
            // Update form if systems were detected
            if (data.data.detected_antispam && Object.keys(data.data.detected_antispam).length > 0) {
                // Get antispam systems mapping
                const systemsMapping = @json(\App\Models\AntispamSystem::pluck('id', 'name')->toArray());
                
                // Uncheck all first
                document.querySelectorAll('.antispam-checkbox').forEach(cb => cb.checked = false);
                
                // Check detected systems
                let detectedCount = 0;
                let detectedNames = [];
                const systemDisplayNames = @json(\App\Models\AntispamSystem::pluck('display_name', 'name')->toArray());
                
                for (const [systemName, count] of Object.entries(data.data.detected_antispam)) {
                    if (count > 0 && systemsMapping[systemName]) {
                        const checkbox = document.getElementById(`antispam_${systemsMapping[systemName]}`);
                        if (checkbox) {
                            checkbox.checked = true;
                            detectedCount++;
                            // Use the proper display name from the database
                            detectedNames.push(systemDisplayNames[systemName] || systemName);
                        }
                    }
                }
                
                // Update primary select
                updatePrimaryAntispamSelect();
                
                // Show detection alert
                if (detectedCount > 0) {
                    document.getElementById('detectionAlert').innerHTML = `
                        <div class="alert alert-success mb-4">
                            <i class="fas fa-check-circle"></i> <strong>Détection réussie :</strong> 
                            ${detectedCount} système(s) anti-spam détecté(s) : <strong>${detectedNames.join(', ')}</strong>
                        </div>
                    `;
                    
                } else {
                    document.getElementById('detectionAlert').innerHTML = `
                        <div class="alert alert-warning mb-4">
                            <i class="fas fa-exclamation-triangle"></i> <strong>Attention :</strong> 
                            Aucun système anti-spam spécifique n'a pu être détecté. Veuillez sélectionner manuellement les systèmes utilisés.
                        </div>
                    `;
                }
            } else {
                // No provider detected
                document.getElementById('detectionAlert').innerHTML = `
                    <div class="alert alert-warning mb-4">
                        <i class="fas fa-exclamation-triangle"></i> <strong>Attention :</strong> 
                        Aucun système anti-spam spécifique n'a pu être détecté. Veuillez sélectionner manuellement les systèmes utilisés.
                    </div>
                `;
            }
            
            // Suggest folder mapping
            suggestFolderMapping(folder, data.data.suggested_provider);
        } else {
            console.error('Analysis failed:', data.error);
            document.getElementById('analysisResults').style.display = 'block';
            document.getElementById('analysisContent').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> <strong>Erreur :</strong> ${data.error || 'Erreur inconnue lors de l\'analyse'}
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        document.getElementById('analysisResults').style.display = 'block';
        document.getElementById('analysisContent').innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <strong>Erreur de connexion :</strong> ${error.message}
            </div>
        `;
    })
    .finally(() => {
        // Clear timer
        clearInterval(timerInterval);
        
        // Restore button
        btn.disabled = false;
        btn.className = originalClasses;
        btn.innerHTML = originalHtml;
    });
});

// Store headers separately to avoid inline issues
window.emailHeaders = {};
window.emailEvidence = {};

function displayAnalysisResults(data) {
    const resultsDiv = document.getElementById('analysisResults');
    const contentDiv = document.getElementById('analysisContent');
    
    // Store data globally for downloads
    if (data.emails) {
        data.emails.forEach((email, index) => {
            window.emailHeaders[index] = email.headers || '';
            window.emailEvidence[index] = email.evidence || {};
        });
    }
    
    let html = '';
    
    if (data.emails && data.emails.length > 0) {
        html += `<p class="mb-3">Analyse de ${data.emails.length} email(s) :</p>`;
        
        data.emails.forEach((email, index) => {
            html += `
                <div class="mb-4 border-bottom pb-3">
                    <h6>Email ${index + 1} : ${escapeHtml(email.subject)}</h6>
                    ${email.date ? `<p class="text-muted small mb-2">Date : ${escapeHtml(email.date)}</p>` : ''}
                    `;
            
            // Display detected systems
            const systemDisplayNames = @json(\App\Models\AntispamSystem::pluck('display_name', 'name')->toArray());
            
            if (email.detected_antispam && Object.keys(email.detected_antispam).length > 0) {
                html += '<p class="mb-2"><strong>Systèmes anti-spam détectés :</strong> ';
                let detectedSystems = [];
                for (const [system, detected] of Object.entries(email.detected_antispam)) {
                    if (detected) {
                        detectedSystems.push(system);
                    }
                }
                if (detectedSystems.length > 0) {
                    detectedSystems.forEach(system => {
                        const displayName = systemDisplayNames[system] || system;
                        html += `<span class="badge bg-info me-1">${displayName}</span>`;
                    });
                } else {
                    html += '<span class="badge bg-secondary">Aucun</span>';
                }
                html += '</p>';
            } else {
                html += '<p class="mb-2"><strong>Systèmes anti-spam détectés :</strong> <span class="badge bg-secondary">Aucun</span></p>';
            }
            
            // Check if there's any evidence for this email
            let hasEvidence = false;
            if (email.evidence) {
                for (const [system, lines] of Object.entries(email.evidence)) {
                    if (Array.isArray(lines) && lines.length > 0) {
                        hasEvidence = true;
                        break;
                    }
                }
            }
            
            // Download buttons
            html += `
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-primary ${!hasEvidence ? 'disabled' : ''}" 
                            onclick="${hasEvidence ? `downloadEvidence(${index})` : 'return false'}"
                            ${!hasEvidence ? 'disabled title="Aucune preuve de détection disponible"' : ''}>
                        <i class="fas fa-download"></i> Télécharger les preuves
                    </button>
                    <button class="btn btn-sm btn-outline-primary" onclick="downloadHeaders(${index})">
                        <i class="fas fa-download"></i> Télécharger l'en-tête complet
                    </button>
                </div>
                </div>
            `;
        });
    } else {
        html = '<div class="alert alert-warning">Aucun email trouvé dans ce dossier.</div>';
    }
    
    contentDiv.innerHTML = html;
    resultsDiv.style.display = 'block';
}

// Toggle functions for headers and evidence
function toggleHeaders(index) {
    try {
        const content = document.getElementById(`headers-content-${index}`);
        const icon = document.getElementById(`headers-icon-${index}`);
        const pre = document.getElementById(`headers-pre-${index}`);
        
        if (!content || !icon || !pre) {
            console.error(`Elements not found for index ${index}`);
            return false;
        }
        
        if (content.style.display === 'none' || content.style.display === '') {
            // Load headers content from global variable
            const headers = window.emailHeaders[index] || 'Aucun en-tête disponible';
            pre.textContent = headers; // Use textContent to avoid HTML injection
            
            content.style.display = 'block';
            icon.className = 'fas fa-chevron-down';
        } else {
            content.style.display = 'none';
            icon.className = 'fas fa-chevron-right';
        }
    } catch (e) {
        console.error('Error in toggleHeaders:', e);
    }
    return false;
}

function toggleEvidence(index) {
    try {
        const content = document.getElementById(`evidence-content-${index}`);
        const icon = document.getElementById(`evidence-icon-${index}`);
        const detail = document.getElementById(`evidence-detail-${index}`);
        
        if (!content || !icon || !detail) {
            console.error(`Elements not found for index ${index}`);
            return false;
        }
        
        if (content.style.display === 'none' || content.style.display === '') {
            // Build evidence HTML from global variable
            const evidence = window.emailEvidence[index] || {};
            let evidenceHtml = '';
            
            for (const [system, lines] of Object.entries(evidence)) {
                if (Array.isArray(lines) && lines.length > 0) {
                    evidenceHtml += `<h6 class="mt-2">${escapeHtml(system.toUpperCase())}</h6>`;
                    evidenceHtml += '<ul class="small">';
                    lines.forEach(line => {
                        evidenceHtml += `<li><code>${escapeHtml(line)}</code></li>`;
                    });
                    evidenceHtml += '</ul>';
                }
            }
            
            detail.innerHTML = evidenceHtml || '<p class="text-muted">Aucune preuve disponible</p>';
            
            content.style.display = 'block';
            icon.className = 'fas fa-chevron-down';
        } else {
            content.style.display = 'none';
            icon.className = 'fas fa-chevron-right';
        }
    } catch (e) {
        console.error('Error in toggleEvidence:', e);
    }
    return false;
}

// Display with download buttons for providers with problematic headers
function displayWithDownloads(data, contentDiv, providerName) {
    let html = '';
    
    if (data.emails && data.emails.length > 0) {
        // Store data for download
        window.emailHeaders = {};
        window.emailEvidence = {};
        
        html += `<p class="mb-3">Analyse de ${data.emails.length} email(s) :</p>`;
        
        data.emails.forEach((email, index) => {
            // Store data
            window.emailHeaders[index] = email.headers || '';
            window.emailEvidence[index] = email.evidence || {};
            
            html += `
                <div class="mb-4">
                    <h6>Email ${index + 1} : ${escapeHtml(email.subject)}</h6>
                    ${email.date ? `<p class="text-muted small">Date : ${escapeHtml(email.date)}</p>` : ''}
                `;
            
            // Display detected systems
            if (email.detected_antispam && Object.keys(email.detected_antispam).length > 0) {
                html += '<p class="mb-2"><strong>Systèmes anti-spam détectés :</strong> ';
                for (const [system, detected] of Object.entries(email.detected_antispam)) {
                    if (detected) {
                        html += `<span class="badge bg-info me-1">${system}</span>`;
                    }
                }
                html += '</p>';
            } else {
                html += `<p class="mb-2"><strong>Système détecté :</strong> <span class="badge bg-info">${providerName}</span></p>`;
            }
            
            // Check if there's any evidence
            let hasEvidence = false;
            let evidenceCount = 0;
            if (email.evidence) {
                for (const [system, lines] of Object.entries(email.evidence)) {
                    if (Array.isArray(lines) && lines.length > 0) {
                        hasEvidence = true;
                        evidenceCount += lines.length;
                    }
                }
            }
            
            if (hasEvidence) {
                html += `
                    <div class="mb-3">
                        <strong>Preuves de détection :</strong><br>
                        ${evidenceCount} en-têtes de détection trouvés<br>
                        <button class="btn btn-sm btn-outline-primary mt-2" onclick="downloadEvidence(${index})">
                            <i class="fas fa-download"></i> Télécharger les preuves (.txt)
                        </button>
                    </div>
                `;
            }
            
            html += `
                <div class="mb-3">
                    <strong>En-têtes techniques :</strong><br>
                    Taille : ${email.headers ? email.headers.length : 0} caractères<br>
                    <button class="btn btn-sm btn-outline-primary mt-2" onclick="downloadHeaders(${index})">
                        <i class="fas fa-download"></i> Télécharger les en-têtes (.txt)
                    </button>
                </div>
                </div>
            `;
        });
        
        // Summary based on detected systems
        if (data.detected_antispam) {
            const detectedCount = Object.values(data.detected_antispam).reduce((sum, count) => sum + count, 0);
            if (detectedCount > 0) {
                html += '<div class="alert alert-success"><strong>Résumé de la détection :</strong><ul class="mb-0">';
                for (const [system, count] of Object.entries(data.detected_antispam)) {
                    if (count > 0) {
                        html += `<li>${system} : détecté dans ${count} email(s)</li>`;
                    }
                }
                html += '</ul></div>';
            }
        }
    } else {
        html = '<div class="alert alert-warning">Aucun email trouvé dans ce dossier.</div>';
    }
    
    contentDiv.innerHTML = html;
}

// Download functions
function downloadHeaders(index) {
    const headers = window.emailHeaders[index] || 'Aucun en-tête disponible';
    const filename = `headers_email_${index + 1}.txt`;
    downloadTextFile(headers, filename);
}

function downloadEvidence(index) {
    const evidence = window.emailEvidence[index] || {};
    let content = 'PREUVES DE DÉTECTION\n';
    content += '====================\n\n';
    
    let hasEvidence = false;
    for (const [system, lines] of Object.entries(evidence)) {
        if (Array.isArray(lines) && lines.length > 0) {
            hasEvidence = true;
            content += `=== ${system.toUpperCase()} ===\n`;
            content += `${lines.length} en-tête(s) détecté(s)\n\n`;
            lines.forEach((line, lineIndex) => {
                content += `${lineIndex + 1}. ${line}\n`;
            });
            content += '\n';
        }
    }
    
    if (!hasEvidence) {
        content += 'Aucune preuve de détection trouvée dans cet email.\n';
    }
    
    const filename = `evidence_email_${index + 1}.txt`;
    downloadTextFile(content, filename);
}

function downloadTextFile(content, filename) {
    // Add BOM for UTF-8 to ensure proper display of special characters
    const BOM = '\uFEFF';
    const blob = new Blob([BOM + content], { type: 'text/plain;charset=utf-8' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
}

function suggestFolderMapping(spamFolder, detectedProvider) {
    // Set spam folder - check if element exists
    const spamSelect = document.getElementById('folder_spam');
    if (spamSelect) {
        spamSelect.value = spamFolder;
    }
    
    // Get all folders from options - check if element exists
    const inboxSelect = document.getElementById('folder_inbox');
    if (!inboxSelect) return;
    
    const folders = Array.from(inboxSelect.options)
        .map(opt => opt.value)
        .filter(v => v);
    
    // Clear previous recommendations
    document.querySelectorAll('.folder-recommendation').forEach(el => el.remove());
    
    // Inbox folder mapping based on provider
    let inboxFound = false;
    const inboxPatterns = {
        'gmail': ['INBOX'],
        'outlook': ['INBOX', 'Inbox'],
        'yahoo': ['INBOX', 'Inbox'],
        'default': ['inbox', 'boîte de réception', 'boite de reception', 'INBOX']
    };
    
    const patterns = inboxPatterns[detectedProvider] || inboxPatterns.default;
    for (const folder of folders) {
        if (patterns.some(pattern => folder.toLowerCase() === pattern.toLowerCase())) {
            inboxSelect.value = folder;
            inboxFound = true;
            // Add recommendation badge
            addRecommendationBadge(inboxSelect);
            break;
        }
    }
    
    // Spam folder - already set but add badge
    if (spamSelect && spamSelect.value) {
        addRecommendationBadge(spamSelect);
    }
    
    // Promotions folder (mainly for Gmail) - check if element exists
    const promotionsSelect = document.getElementById('folder_promotions');
    if (promotionsSelect && detectedProvider === 'gmail') {
        const promotionsPatterns = ['INBOX (Promotions)', 'promotions', '[gmail]/promotions'];
        for (const folder of folders) {
            if (promotionsPatterns.some(pattern => folder.toLowerCase().includes(pattern.toLowerCase()))) {
                promotionsSelect.value = folder;
                addRecommendationBadge(promotionsSelect);
                break;
            }
        }
    }
}

function addRecommendationBadge(selectElement) {
    // Remove existing badge if any
    const existingBadge = selectElement.parentElement.querySelector('.folder-recommendation');
    if (existingBadge) {
        existingBadge.remove();
    }
    
    // Get the selected folder name
    const selectedOption = selectElement.options[selectElement.selectedIndex];
    const folderName = selectedOption ? selectedOption.text : '';
    
    // Add new badge
    const badge = document.createElement('span');
    badge.className = 'badge bg-success ms-2 folder-recommendation';
    badge.innerHTML = `<i class="fas fa-check"></i> Le dossier "${folderName}" est recommandé`;
    selectElement.parentElement.appendChild(badge);
}

function escapeHtml(text) {
    if (!text) return '';
    if (typeof text !== 'string') {
        text = String(text);
    }
    
    // Remove any potential script tags or dangerous content
    text = text.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '');
    
    // Escape HTML entities
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    
    return text.replace(/[&<>"']/g, m => map[m]);
}

function handleFormSubmit(event) {
    event.preventDefault();
    
    const form = event.target;
    const button = document.getElementById('submitBtn');
    const originalHtml = button.innerHTML;
    
    // Désactiver le bouton et ajouter le spinner
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Configuration en cours...';
    
    // Rendre les champs en lecture seule au lieu de les désactiver
    const inputs = form.querySelectorAll('input[type="text"], input[type="email"], input[type="password"], textarea');
    inputs.forEach(input => {
        input.readOnly = true;
        input.style.backgroundColor = '#f5f5f5';
        input.style.cursor = 'not-allowed';
    });
    
    // Désactiver les select et buttons
    const selects = form.querySelectorAll('select');
    selects.forEach(select => {
        select.style.pointerEvents = 'none';
        select.style.backgroundColor = '#f5f5f5';
    });
    
    const buttons = form.querySelectorAll('button:not(#submitBtn)');
    buttons.forEach(btn => btn.disabled = true);
    
    // Soumettre le formulaire
    form.submit();
}
</script>
@endsection