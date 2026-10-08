<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Rapport décisionnel FOSER</title>
    <style>
        @page { margin: 28px 32px; }
        body { font-family: DejaVu Sans, sans-serif; color:#17313a; font-size:10px; }
        h1 { font-size:22px; margin:0 0 4px; }
        h2 { font-size:14px; margin:18px 0 8px; border-bottom:1px solid #8a9d9c; padding-bottom:4px; }
        p { color:#52676c; margin:4px 0 10px; }
        table { width:100%; border-collapse:collapse; margin-bottom:12px; }
        th,td { text-align:left; padding:6px 7px; border-bottom:1px solid #d6e0df; }
        th { background:#eaf1ef; }
        .metrics td { width:25%; }
        .value { font-weight:bold; font-size:13px; }
        .columns { width:100%; }
        .columns td { width:50%; vertical-align:top; padding:0 8px 0 0; border:0; }
    </style>
</head>
<body>
    <h1>Tableau décisionnel FOSER</h1>
    <p>Généré le {{ $data['generated_at']->format('d/m/Y à H:i') }} · Aucun renseignement personnel n’est inclus dans ce rapport.</p>
    @if($data['filters'])<p>Filtres : {{ collect($data['filters'])->map(fn($value, $key) => $key.'='.$value)->implode(' · ') }}</p>@endif
    <h2>Indicateurs principaux</h2>
    <table class="metrics"><tbody>
        @foreach(array_chunk(array_keys($data['summary']), 4) as $rowIndex => $keys)<tr>@foreach($keys as $columnIndex => $key)<td>{{ $data['summary_labels'][$rowIndex * 4 + $columnIndex] ?? str_replace('_', ' ', ucfirst($key)) }}<br><span class="value">{{ number_format($data['summary'][$key], 0, ',', ' ') }}{{ $key === 'treatment_rate' ? ' %' : '' }}</span></td>@endforeach</tr>@endforeach
    </tbody></table>
    <h2>Analyses</h2>
    <table class="columns"><tbody>@foreach(array_chunk(array_keys($data['charts']), 2) as $chartRow => $chartKeys)<tr>@foreach($chartKeys as $chartColumn => $key)<td><strong>{{ $data['chart_labels'][$chartRow * 2 + $chartColumn] ?? str_replace('_', ' ', ucfirst($key)) }}</strong><table><thead><tr><th>Libellé</th><th>Valeur</th></tr></thead><tbody>@forelse($data['charts'][$key] as $row)<tr><td>{{ $row['label'] }}</td><td>{{ number_format($row['value'], 0, ',', ' ') }}</td></tr>@empty<tr><td colspan="2">Aucune donnée</td></tr>@endforelse</tbody></table></td>@endforeach</tr>@endforeach</tbody></table>
    <h2>Alertes décisionnelles</h2>
    <table><thead><tr><th>Indicateur</th><th>Nombre</th></tr></thead><tbody>@foreach($data['alerts'] as $alert)<tr><td>{{ $alert['label'] }}</td><td>{{ $alert['count'] }}</td></tr>@endforeach</tbody></table>
</body>
</html>
