<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    */

    'accepted' => 'Il campo :attribute deve essere accettato.',
    'accepted_if' => 'Il campo :attribute deve essere accettato quando :other è :value.',
    'active_url' => 'Il campo :attribute deve essere un URL valido.',
    'after' => 'Il campo :attribute deve essere successivo a :date.',
    'after_or_equal' => 'Il campo :attribute deve essere uguale o successivo a :date.',
    'alpha' => 'Il campo :attribute può contenere solo lettere.',
    'alpha_dash' => 'Il campo :attribute può contenere solo lettere, numeri, trattini e trattini bassi.',
    'alpha_num' => 'Il campo :attribute può contenere solo lettere e numeri.',
    'any_of' => 'Il campo :attribute non è valido.',
    'array' => 'Il campo :attribute deve essere un elenco.',
    'array_keys' => 'Il campo :attribute può contenere solo le chiavi: :values.',
    'ascii' => 'Il campo :attribute può contenere solo caratteri alfanumerici e simboli a un byte.',
    'base64' => 'Il campo :attribute deve essere una stringa Base64 valida.',
    'before' => 'Il campo :attribute deve essere precedente a :date.',
    'before_or_equal' => 'Il campo :attribute deve essere uguale o precedente a :date.',
    'between' => [
        'array' => 'Il campo :attribute deve avere tra :min e :max elementi.',
        'file' => 'Il campo :attribute deve essere tra :min e :max kilobyte.',
        'numeric' => 'Il campo :attribute deve essere tra :min e :max.',
        'string' => 'Il campo :attribute deve avere tra :min e :max caratteri.',
    ],
    'boolean' => 'Il campo :attribute deve essere vero o falso.',
    'can' => 'Il campo :attribute contiene un valore non autorizzato.',
    'confirmed' => 'La conferma del campo :attribute non corrisponde.',
    'contains' => 'Al campo :attribute manca un valore obbligatorio.',
    'current_password' => 'La password non è corretta.',
    'date' => 'Il campo :attribute deve essere una data valida.',
    'date_equals' => 'Il campo :attribute deve essere uguale a :date.',
    'date_format' => 'Il campo :attribute deve rispettare il formato :format.',
    'decimal' => 'Il campo :attribute deve avere :decimal cifre decimali.',
    'declined' => 'Il campo :attribute deve essere rifiutato.',
    'declined_if' => 'Il campo :attribute deve essere rifiutato quando :other è :value.',
    'different' => 'I campi :attribute e :other devono essere diversi.',
    'digits' => 'Il campo :attribute deve avere :digits cifre.',
    'digits_between' => 'Il campo :attribute deve avere tra :min e :max cifre.',
    'dimensions' => 'Le dimensioni dell\'immagine :attribute non sono valide.',
    'distinct' => 'Il campo :attribute contiene un valore duplicato.',
    'doesnt_contain' => 'Il campo :attribute non deve contenere: :values.',
    'doesnt_end_with' => 'Il campo :attribute non deve finire con: :values.',
    'doesnt_start_with' => 'Il campo :attribute non deve iniziare con: :values.',
    'email' => 'Il campo :attribute deve essere un indirizzo email valido.',
    'encoding' => 'Il campo :attribute deve essere codificato in :encoding.',
    'ends_with' => 'Il campo :attribute deve finire con: :values.',
    'enum' => 'Il valore scelto per :attribute non è valido.',
    'exists' => 'Il valore scelto per :attribute non è valido.',
    'extensions' => 'Il campo :attribute deve avere una di queste estensioni: :values.',
    'file' => 'Il campo :attribute deve essere un file.',
    'filled' => 'Il campo :attribute deve avere un valore.',
    'gt' => [
        'array' => 'Il campo :attribute deve avere più di :value elementi.',
        'file' => 'Il campo :attribute deve essere più grande di :value kilobyte.',
        'numeric' => 'Il campo :attribute deve essere maggiore di :value.',
        'string' => 'Il campo :attribute deve avere più di :value caratteri.',
    ],
    'gte' => [
        'array' => 'Il campo :attribute deve avere almeno :value elementi.',
        'file' => 'Il campo :attribute deve essere di almeno :value kilobyte.',
        'numeric' => 'Il campo :attribute deve essere maggiore o uguale a :value.',
        'string' => 'Il campo :attribute deve avere almeno :value caratteri.',
    ],
    'hex_color' => 'Il campo :attribute deve essere un colore esadecimale valido.',
    'image' => 'Il campo :attribute deve essere un\'immagine.',
    'in' => 'Il valore scelto per :attribute non è valido.',
    'in_array' => 'Il campo :attribute deve essere presente in :other.',
    'in_array_keys' => 'Il campo :attribute deve contenere almeno una di queste chiavi: :values.',
    'integer' => 'Il campo :attribute deve essere un numero intero.',
    'ip' => 'Il campo :attribute deve essere un indirizzo IP valido.',
    'ipv4' => 'Il campo :attribute deve essere un indirizzo IPv4 valido.',
    'ipv6' => 'Il campo :attribute deve essere un indirizzo IPv6 valido.',
    'json' => 'Il campo :attribute deve essere una stringa JSON valida.',
    'list' => 'Il campo :attribute deve essere una lista.',
    'lowercase' => 'Il campo :attribute deve essere in minuscolo.',
    'lt' => [
        'array' => 'Il campo :attribute deve avere meno di :value elementi.',
        'file' => 'Il campo :attribute deve essere più piccolo di :value kilobyte.',
        'numeric' => 'Il campo :attribute deve essere minore di :value.',
        'string' => 'Il campo :attribute deve avere meno di :value caratteri.',
    ],
    'lte' => [
        'array' => 'Il campo :attribute non deve avere più di :value elementi.',
        'file' => 'Il campo :attribute non deve superare :value kilobyte.',
        'numeric' => 'Il campo :attribute deve essere minore o uguale a :value.',
        'string' => 'Il campo :attribute non deve superare :value caratteri.',
    ],
    'mac_address' => 'Il campo :attribute deve essere un indirizzo MAC valido.',
    'max' => [
        'array' => 'Il campo :attribute non deve avere più di :max elementi.',
        'file' => 'Il campo :attribute non deve superare :max kilobyte.',
        'numeric' => 'Il campo :attribute non deve essere maggiore di :max.',
        'string' => 'Il campo :attribute non deve superare :max caratteri.',
    ],
    'max_digits' => 'Il campo :attribute non deve avere più di :max cifre.',
    'mimes' => 'Il campo :attribute deve essere un file di tipo: :values.',
    'mimetypes' => 'Il campo :attribute deve essere un file di tipo: :values.',
    'min' => [
        'array' => 'Il campo :attribute deve avere almeno :min elementi.',
        'file' => 'Il campo :attribute deve essere di almeno :min kilobyte.',
        'numeric' => 'Il campo :attribute deve essere almeno :min.',
        'string' => 'Il campo :attribute deve avere almeno :min caratteri.',
    ],
    'min_digits' => 'Il campo :attribute deve avere almeno :min cifre.',
    'missing' => 'Il campo :attribute non deve essere presente.',
    'missing_if' => 'Il campo :attribute non deve essere presente quando :other è :value.',
    'missing_unless' => 'Il campo :attribute non deve essere presente a meno che :other sia :value.',
    'missing_with' => 'Il campo :attribute non deve essere presente quando c\'è :values.',
    'missing_with_all' => 'Il campo :attribute non deve essere presente quando ci sono :values.',
    'multiple_of' => 'Il campo :attribute deve essere un multiplo di :value.',
    'not_in' => 'Il valore scelto per :attribute non è valido.',
    'not_regex' => 'Il formato del campo :attribute non è valido.',
    'numeric' => 'Il campo :attribute deve essere un numero.',
    'password' => [
        'letters' => 'Il campo :attribute deve contenere almeno una lettera.',
        'mixed' => 'Il campo :attribute deve contenere almeno una lettera maiuscola e una minuscola.',
        'numbers' => 'Il campo :attribute deve contenere almeno un numero.',
        'symbols' => 'Il campo :attribute deve contenere almeno un simbolo.',
        'uncompromised' => 'Il valore di :attribute è comparso in una fuga di dati. Scegline uno diverso.',
    ],
    'present' => 'Il campo :attribute deve essere presente.',
    'present_if' => 'Il campo :attribute deve essere presente quando :other è :value.',
    'present_unless' => 'Il campo :attribute deve essere presente a meno che :other sia :value.',
    'present_with' => 'Il campo :attribute deve essere presente quando c\'è :values.',
    'present_with_all' => 'Il campo :attribute deve essere presente quando ci sono :values.',
    'prohibited' => 'Il campo :attribute non è consentito.',
    'prohibited_if' => 'Il campo :attribute non è consentito quando :other è :value.',
    'prohibited_if_accepted' => 'Il campo :attribute non è consentito quando :other è accettato.',
    'prohibited_if_declined' => 'Il campo :attribute non è consentito quando :other è rifiutato.',
    'prohibited_unless' => 'Il campo :attribute non è consentito a meno che :other sia tra :values.',
    'prohibits' => 'Il campo :attribute impedisce la presenza di :other.',
    'regex' => 'Il formato del campo :attribute non è valido.',
    'required' => 'Il campo :attribute è obbligatorio.',
    'required_array_keys' => 'Il campo :attribute deve contenere le voci: :values.',
    'required_if' => 'Il campo :attribute è obbligatorio quando :other è :value.',
    'required_if_accepted' => 'Il campo :attribute è obbligatorio quando :other è accettato.',
    'required_if_declined' => 'Il campo :attribute è obbligatorio quando :other è rifiutato.',
    'required_unless' => 'Il campo :attribute è obbligatorio a meno che :other sia tra :values.',
    'required_with' => 'Il campo :attribute è obbligatorio quando c\'è :values.',
    'required_with_all' => 'Il campo :attribute è obbligatorio quando ci sono :values.',
    'required_without' => 'Il campo :attribute è obbligatorio quando manca :values.',
    'required_without_all' => 'Il campo :attribute è obbligatorio quando mancano tutti i campi :values.',
    'same' => 'Il campo :attribute deve coincidere con :other.',
    'size' => [
        'array' => 'Il campo :attribute deve contenere :size elementi.',
        'file' => 'Il campo :attribute deve essere di :size kilobyte.',
        'numeric' => 'Il campo :attribute deve essere :size.',
        'string' => 'Il campo :attribute deve avere :size caratteri.',
    ],
    'starts_with' => 'Il campo :attribute deve iniziare con: :values.',
    'string' => 'Il campo :attribute deve essere un testo.',
    'timezone' => 'Il campo :attribute deve essere un fuso orario valido.',
    'unique' => 'Il valore di :attribute è già in uso.',
    'uploaded' => 'Il caricamento di :attribute non è riuscito.',
    'uppercase' => 'Il campo :attribute deve essere in maiuscolo.',
    'url' => 'Il campo :attribute deve essere un URL valido.',
    'ulid' => 'Il campo :attribute deve essere un ULID valido.',
    'uuid' => 'Il campo :attribute deve essere un UUID valido.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'slot' => [
            'required' => 'Scegli un orario.',
        ],
        'customerId' => [
            'required' => 'Scegli il cliente.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | Readable names for the Livewire properties validated in the forms.
    |
    */

    'attributes' => [
        'name' => 'nome',
        'email' => 'email',
        'password' => 'password',
        'customerId' => 'cliente',
        'serviceId' => 'servizio',
        'operatorId' => 'operatore',
        'date' => 'giorno',
        'slot' => 'orario',
        'notes' => 'note',
        'form.name' => 'nome',
        'form.description' => 'descrizione',
        'form.durationMinutes' => 'durata',
        'form.price' => 'prezzo',
        'serviceIds' => 'servizi',
        'hourWeekday' => 'giorno',
        'hourStart' => 'inizio',
        'hourEnd' => 'fine',
        'offStartsAt' => 'inizio',
        'offEndsAt' => 'fine',
        'offReason' => 'motivo',
    ],

];
