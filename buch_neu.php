<?php
declare(strict_types=1);

// ---------------------------------------------------------------------
// 1. SESSION & CSRF SETUP
// ---------------------------------------------------------------------
session_start();
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf'];

// ---------------------------------------------------------------------
// 2. CONFIGURATION & INCLUDES
// ---------------------------------------------------------------------
require 'db.php'; // Include database connection ($pdo)

const UPLOAD_COVER = __DIR__ . '/uploads/cover/';
const UPLOAD_GALERIE = __DIR__ . '/uploads/galerie/';
const MAX_BYTES = 3 * 1024 * 1024; // 3 MB
const ERLAUBTE_TYPEN = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

// Helper function to save uploaded image
function bildSpeichern(array $datei, string $ordner, array &$fehler, string $feld): ?string
{
    if (($datei['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($datei['error'] !== UPLOAD_ERR_OK) {
        $fehler[$feld] = match ($datei['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Die Datei ist zu groß.',
            UPLOAD_ERR_PARTIAL => 'Die Übertragung war unvollständig.',
            default => 'Der Upload ist fehlgeschlagen.',
        };
        return null;
    }
    if (!is_uploaded_file($datei['tmp_name'])) {
        $fehler[$feld] = 'Ungültiger Upload.';
        return null;
    }
    if ($datei['size'] > MAX_BYTES) {
        $fehler[$feld] = 'Die Datei darf höchstens 3 MB groß sein.';
        return null;
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($datei['tmp_name']);
    if (!isset(ERLAUBTE_TYPEN[$mime])) {
        $fehler[$feld] = 'Erlaubt sind nur JPEG, PNG und WebP.';
        return null;
    }
    if (@getimagesize($datei['tmp_name']) === false) {
        $fehler[$feld] = 'Die Datei ist kein gültiges Bild.';
        return null;
    }
    $neuerName = bin2hex(random_bytes(16)) . '.' . ERLAUBTE_TYPEN[$mime];
    if (!move_uploaded_file($datei['tmp_name'], $ordner . $neuerName)) {
        $fehler[$feld] = 'Die Datei konnte nicht gespeichert werden.';
        return null;
    }
    return $neuerName;
}

// ---------------------------------------------------------------------
// 3. INITIALIZE VARIABLES
// ---------------------------------------------------------------------
$fehler = [];
$buchId = '';
$werte = [
    'titel'        => '',
    'autor'        => '',
    'isbn'         => '',
    'preis'        => '',
    'beschreibung' => ''
];

// ---------------------------------------------------------------------
// 4. FORM POST PROCESSING
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('Ungültige Anfrage.');
    }

    // 4.1 Read Text Inputs
    $werte['titel']        = trim($_POST['titel'] ?? '');
    $werte['autor']        = trim($_POST['autor'] ?? '');
    $werte['isbn']         = trim($_POST['isbn'] ?? '');
    $werte['preis']        = trim($_POST['preis'] ?? '');
    $werte['beschreibung'] = trim($_POST['beschreibung'] ?? '');

    // 4.2 Validate Text Inputs
    if ($werte['titel'] === '') {
        $fehler['titel'] = 'Bitte geben Sie einen Titel an.';
    } elseif (mb_strlen($werte['titel']) > 200) {
        $fehler['titel'] = 'Der Titel darf höchstens 200 Zeichen lang sein.';
    }

    if ($werte['autor'] === '') {
        $fehler['autor'] = 'Bitte geben Sie einen Autor an.';
    }

    if ($werte['isbn'] !== '' && preg_match('/^\d{13}$/', $werte['isbn']) !== 1) {
        $fehler['isbn'] = 'Die ISBN muss aus genau 13 Ziffern bestehen.';
    }

    if ($werte['preis'] !== '' && !is_numeric($werte['preis'])) {
        $fehler['preis'] = 'Der Preis muss eine Zahl sein.';
    }

    // 4.3 Handle Cover Upload
    $coverName = null;
    if (isset($_FILES['cover'])) {
        $coverName = bildSpeichern($_FILES['cover'], UPLOAD_COVER, $fehler, 'cover');
    }

    // 4.4 Save to Database
    if ($fehler === []) {
        $stmt = $pdo->prepare(
            'INSERT INTO buecher (titel, autor, isbn, preis, beschreibung, cover_datei)
             VALUES (:titel, :autor, :isbn, :preis, :beschreibung, :cover_datei)'
        );
        $stmt->execute([
            'titel'        => $werte['titel'],
            'autor'        => $werte['autor'],
            'isbn'         => $werte['isbn'] !== '' ? $werte['isbn'] : null,
            'preis'        => $werte['preis'] !== '' ? (float) $werte['preis'] : null,
            'beschreibung' => $werte['beschreibung'],
            'cover_datei'  => $coverName,
        ]);

        $neuBuchId = $pdo->lastInsertId();

        // 4.5 Handle Gallery Uploads
        if (isset($_FILES['galerie']) && is_array($_FILES['galerie']['name'])) {
            foreach ($_FILES['galerie']['name'] as $index => $name) {
                $einzelfile = [
                    'name'     => $_FILES['galerie']['name'][$index],
                    'type'     => $_FILES['galerie']['type'][$index],
                    'tmp_name' => $_FILES['galerie']['tmp_name'][$index],
                    'error'    => $_FILES['galerie']['error'][$index],
                    'size'     => $_FILES['galerie']['size'][$index],
                ];

                $galerieName = bildSpeichern($einzelfile, UPLOAD_GALERIE, $fehler, 'galerie');

                if ($galerieName !== null) {
                    $stmtBilder = $pdo->prepare(
                        'INSERT INTO buch_bilder (buch_id, datei, position) VALUES (:buch_id, :datei, :pos)'
                    );
                    $stmtBilder->execute([
                        'buch_id' => $neuBuchId,
                        'datei'   => $galerieName,
                        'pos'     => $index,
                    ]);
                }
            }
        }

        header('Location: buch.php?erfolg=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buecherverwaltung</title>
    <style>
        .error { color: red; margin-bottom: 10px; }
    </style>
</head>
<body>

<?php if (!empty($fehler)): ?>
    <div class="error">
        <ul>
            <?php foreach ($fehler as $meldungsFeld => $meldung): ?>
                <li><?= htmlspecialchars($meldung) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form action="buch_neu.php" method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
    <input type="hidden" name="id" value="<?= htmlspecialchars((string) $buchId) ?>">

    <label for="titel">Titel *</label>
    <input type="text" id="titel" name="titel" required maxlength="200" value="<?= htmlspecialchars($werte['titel']) ?>"><br>

    <label for="autor">Autor *</label>
    <input type="text" id="autor" name="autor" required maxlength="150" value="<?= htmlspecialchars($werte['autor']) ?>"><br>

    <label for="isbn">ISBN</label>
    <input type="text" id="isbn" name="isbn" maxlength="13" pattern="[0-9]{13}" value="<?= htmlspecialchars($werte['isbn']) ?>"><br>

    <label for="preis">Preis in Euro</label>
    <input type="number" id="preis" name="preis" step="0.01" min="0" value="<?= htmlspecialchars($werte['preis']) ?>"><br>

    <label for="beschreibung">Beschreibung</label>
    <textarea id="beschreibung" name="beschreibung" rows="5"><?= htmlspecialchars($werte['beschreibung']) ?></textarea><br>

    <label for="cover">Cover (ein Bild)</label>
    <input type="file" id="cover" name="cover" accept="image/jpeg,image/png,image/webp"><br>

    <label for="galerie">Weitere Bilder (mehrere möglich)</label>
    <input type="file" id="galerie" name="galerie[]" multiple accept="image/jpeg,image/png,image/webp"><br>

    <button type="submit" name="aktion" value="speichern">Speichern</button>
</form>

</body>
</html>