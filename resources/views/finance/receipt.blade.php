<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Reçu {{ $receiptRecord->receipt_number }}</title>
    <style>
        body { color: #17212b; font-family: DejaVu Sans, sans-serif; font-size: 12px; margin: 48px; }
        h1 { color: #146b58; font-size: 24px; margin-bottom: 8px; }
        .rule { border-top: 3px solid #146b58; margin: 24px 0; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border-bottom: 1px solid #d9e0e4; padding: 12px 8px; text-align: left; }
        th { color: #52616b; width: 34%; }
        .amount { font-size: 20px; font-weight: bold; }
        .foot { color: #52616b; font-size: 10px; margin-top: 32px; }
    </style>
</head>
<body>
    <h1>Reçu de paiement</h1>
    <div>FOSER · {{ $receiptRecord->receipt_number }}</div>
    <div class="rule"></div>
    <table>
        <tr><th>Bénéficiaire</th><td>{{ $beneficiary?->name ?? 'Bénéficiaire enregistré' }}</td></tr>
        <tr><th>Programme</th><td>{{ $program ?? 'Non renseigné' }}</td></tr>
        <tr><th>Engagement</th><td>{{ $commitment?->reference ?? 'Non renseigné' }}</td></tr>
        <tr><th>Référence de paiement</th><td>{{ $payment->provider_reference ?? $payment->id }}</td></tr>
        <tr><th>Moyen de paiement</th><td>{{ $payment->payment_method ?? 'Non renseigné' }}</td></tr>
        <tr><th>Date de confirmation</th><td>{{ $payment->paid_at?->format('d/m/Y H:i') }}</td></tr>
        <tr><th>Montant confirmé</th><td class="amount">{{ number_format((float) $payment->amount, 0, ',', ' ') }} XOF</td></tr>
    </table>
    <div class="foot">Reçu émis le {{ $receiptRecord->issued_at->format('d/m/Y H:i') }}. Ce document atteste un paiement confirmé dans le système FOSER.</div>
</body>
</html>