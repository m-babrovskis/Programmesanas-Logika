<?php
require_once 'db.php';

// ── 1. Binomiālā koeficienta aprēķins ──────────────────────────────────────
function binomialCoeff(int $n, int $k): int {
    if ($k < 0 || $k > $n) return 0;
    if ($k === 0 || $k === $n) return 1;

    // Izmanto simetriju, lai samazinātu aprēķinu skaitu
    if ($k > $n - $k) $k = $n - $k;

    $result = 1;
    for ($i = 0; $i < $k; $i++) {
        $result = intdiv($result * ($n - $i), $i + 1);
    }
    return $result;
}

// ── 2. Paskāla trijstūra ģenerēšana ───────────────────────────────────────
function buildPascalTriangle(int $rows): array {
    $triangle = [];
    for ($n = 0; $n < $rows; $n++) {
        for ($k = 0; $k <= $n; $k++) {
            $triangle[$n][$k] = binomialCoeff($n, $k);
        }
    }
    return $triangle;
}

// ── 3. Kuponu kodu ģenerēšana ──────────────────────────────────────────────
function generateCodes(int $length, int $numA): array {
    $codes = [];

    // Iterē caur visiem 2^length bitu kombinācijām
    $total = 1 << $length; // 2^length
    for ($mask = 0; $mask < $total; $mask++) {
        // Pārbauda, vai šajā maskā ir tieši $numA biti
        if (substr_count(decbin($mask), '1') !== $numA) continue;

        // Veido kodu: bits 1 → 'A', bits 0 → 'B'
        $code = '';
        for ($pos = $length - 1; $pos >= 0; $pos--) {
            $code .= ($mask >> $pos) & 1 ? 'A' : 'B';
        }
        $codes[] = $code;
    }

    sort($codes);
    return $codes;
}

// ── 4. Formas datu apstrāde ────────────────────────────────────────────────

// Noklusējuma vērtības (uzdevuma nosacījumi: 6 zīmes, 2 A burti)
$length = 6;
$numA   = 2;
$dbMsg  = '';
$dbRows = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validē ievadi: garums 1–10, A skaits 0–4 un ≤ garumam
    $length = max(1, min(10, (int)($_POST['length'] ?? 6)));
    $numA   = max(0, min(4,  (int)($_POST['numA']   ?? 2)));
    if ($numA > $length) $numA = $length;
}

// Aprēķina koeficientu un ģenerē kodus
$coeff  = binomialCoeff($length, $numA);
$codes  = generateCodes($length, $numA);
$pascal = buildPascalTriangle($length + 1); // Paskāla trijstūris līdz $length rindai

// ── 5. Datubāzes operācijas ────────────────────────────────────────────────

try {
    $pdo = getConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
        // Notīra iepriekšējos ierakstus un saglabā jaunos kodus
        $pdo->exec("DELETE FROM Kuponi");
        $stmt = $pdo->prepare("INSERT INTO Kuponi (kods) VALUES (:kods)");
        foreach ($codes as $code) {
            $stmt->execute([':kods' => $code]);
        }
        $dbMsg = "✔ " . count($codes) . " kodi saglabāti datubāzē.";
    }

    // Nolasa no DB: 6 zīmju kodi ar tieši 2 A burtiem
    $dbRows = $pdo->query("
        SELECT id, kods
        FROM Kuponi
        WHERE LENGTH(kods) = 6
          AND kods REGEXP '^[AB]*A[AB]*A[AB]*$'
          AND kods NOT REGEXP 'A.*A.*A'
        ORDER BY kods
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $dbMsg = "⚠ DB kļūda: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Binomiālie koeficienti – Kuponu ģenerators</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<h1>E-veikala atlaižu kuponu ģenerators</h1>

<!-- ── Forma ── -->
<form method="post">
    <label for="length">Koda garums (1–10):</label>
    <input type="number" id="length" name="length"
           min="1" max="10" value="<?= $length ?>">

    <label for="numA">A burtu skaits (0–4):</label>
    <input type="number" id="numA" name="numA"
           min="0" max="4" value="<?= $numA ?>">

    <button type="submit" name="generate">Ģenerēt</button>
    <button type="submit" name="save">Saglabāt DB</button>
</form>

<!-- ── Paskāla trijstūris ── -->
<h2>Paskāla trijstūris (līdz 6. rindai)</h2>
<div class="pascal">
<table class="pascal-table">
<?php
$maxCols = 2 * count($pascal) - 1; // kopējais kolonnu skaits
foreach ($pascal as $rowIdx => $row) {
    echo '<tr>';
    // Kreisā atkāpe
    $pad = count($pascal) - $rowIdx - 1;
    if ($pad > 0) echo "<td colspan='$pad'></td>";
    foreach ($row as $k => $val) {
        $cls = ($rowIdx === $length && $k === $numA) ? ' class="highlight"' : '';
        echo "<td colspan='2'$cls>$val</td>";
    }
    // Labā atkāpe
    if ($pad > 0) echo "<td colspan='$pad'></td>";
    echo '</tr>';
}
?>
</table>
<small>Izcelts: C(<?= $length ?>, <?= $numA ?>) = <span class="highlight"><?= $coeff ?></span></small>
</div>

<!-- ── Ģenerētie kodi ── -->
<h2>Ģenerētie kodi (<?= count($codes) ?> gab.)</h2>
<div class="codes">
    <ul>
        <?php foreach ($codes as $code): ?>
            <li><?= htmlspecialchars($code) ?></li>
        <?php endforeach; ?>
    </ul>
</div>

<!-- ── Datubāze ── -->
<h2>Datubāze – Kuponi (6 zīmes, 2×A)</h2>
<?php if ($dbMsg): ?>
    <p class="<?= str_starts_with($dbMsg, '✔') ? 'msg-ok' : 'msg-err' ?>">
        <?= htmlspecialchars($dbMsg) ?>
    </p>
<?php endif; ?>

<?php if ($dbRows): ?>
    <table>
        <tr><th>ID</th><th>Kods</th></tr>
        <?php foreach ($dbRows as $row): ?>
            <tr>
                <td><?= $row['id'] ?></td>
                <td><?= htmlspecialchars($row['kods']) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php else: ?>
    <p>Datubāzē nav ierakstu. Nospied <em>Saglabāt DB</em>.</p>
<?php endif; ?>

</body>
</html>
