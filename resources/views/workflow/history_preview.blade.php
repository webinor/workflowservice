<!-- resources/views/documents/history_preview.blade.php -->

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Historique du Workflow</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
        }
        .step {
            border-bottom: 1px solid #ccc;
            margin-bottom: 10px;
            padding-bottom: 5px;
        }
        .step-name {
            font-weight: bold;
            margin-bottom: 3px;
        }
        .action-label {
            display: inline-block;
            background-color: #f0f0f0;
            padding: 2px 6px;
            border-radius: 4px;
            margin-right: 10px;
        }
        .cachet {
            display: inline-block;
            font-weight: bold;
            color: #006400;
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <h2>Historique du document</h2>

    @foreach($history as $entry)
        <div class="step">
            <div class="step-name">{{ $entry['step_name'] }}</div>
            <div>
                <span class="action-label">{{ $entry['action_label'] ?? 'Action inconnue' }}</span>
                <span>par {{ $entry['user'] ?? 'Utilisateur inconnu' }}</span>
                <span>le {{ $entry['executed_at'] ?? 'Date inconnue' }}</span>

                @if(in_array(strtolower($entry['action_label']), ['reconnue', 'validée', 'signée']))
                    <span class="cachet">✔ {{ ucfirst($entry['action_label']) }}</span>
                @endif
            </div>
        </div>
    @endforeach
</body>
</html>
